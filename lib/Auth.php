<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

final class Auth
{
    private const MAX_ATTEMPTS = 8;      // per window, per IP
    private const MAX_ACCOUNT_ATTEMPTS = 5;
    private const WINDOW       = 900;    // 15 minutes
    private const IDLE_TIMEOUT = 7200;   // two hours without activity
    private const REKEY_EVERY  = 900;    // rotate an active session every 15 min
    private const REMEMBER_DAYS = 30;
    private const REMEMBER_TTL  = 2592000; // 30 days
    private const REMEMBER_COOKIE = 'h3d_remember';
    private const VERIFY_GRACE = 86400;
    private const VERIFY_DELETE_AFTER_BLOCK = 86400;
    private static ?array $userCache = null;
    private static ?int $userCacheId = null;

    /**
     * Applies the unverified-account lifecycle opportunistically.
     *
     * - after 24 h without verification: disable the account
     * - 24 h after disabling: delete the account and all cascaded data
     *
     * The sweep is deliberately small and runs before session restoration, so
     * a remembered cookie cannot resurrect an account that has expired.
     */
    public static function sweepUnverifiedAccounts(): void
    {
        try {
            $pdo = Db::pdo();
            $now = time();

            $pdo->beginTransaction();
            try {
                $pdo->prepare(
                    'UPDATE users
                        SET is_blocked = 1, verify_blocked_at = ?
                      WHERE verified_at IS NULL
                        AND verify_expires_at IS NOT NULL
                        AND verify_expires_at <= ?
                        AND verify_blocked_at IS NULL
                        AND is_blocked = 0'
                )->execute([$now, $now]);
                $pdo->commit();
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                throw $e;
            }

            $st = $pdo->prepare(
                'SELECT id FROM users
                  WHERE verified_at IS NULL
                    AND verify_blocked_at IS NOT NULL
                    AND verify_blocked_at <= ?
                  LIMIT 100'
            );
            $st->execute([$now - self::VERIFY_DELETE_AFTER_BLOCK]);
            $ids = $st->fetchAll(PDO::FETCH_COLUMN);
            if ($ids) {
                $del = $pdo->prepare('DELETE FROM users WHERE id = ? AND verified_at IS NULL');
                foreach ($ids as $id) {
                    $del->execute([(int) $id]);
                }
            }
        } catch (Throwable $e) {
            // Account cleanup must never take the site down. The next request
            // gets another chance to perform the sweep.
        }
    }

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.cookie_httponly', '1');
        /*
         * Who really decides when a session ends.
         *
         * This class promises two hours of idle time, but PHP's own collector
         * sweeps session files on its own schedule and its default is 1440
         * seconds - twenty-four minutes. Left alone it signs people out at a
         * time the code never agreed to, and at random, because collection is
         * probabilistic: sometimes at twenty-five minutes and sometimes not
         * for hours. That is the shape of "sometimes I open the site and I am
         * not signed in".
         *
         * (On hosting where every site shares one session directory, a
         * neighbour's shorter setting can still sweep these files. The cure
         * there is a session.save_path of one's own, which is a matter for
         * the server rather than for this file.)
         */
        ini_set('session.gc_maxlifetime', (string) self::IDLE_TIMEOUT);
        session_name('h3d_session');
        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => self::isHttps(),
            'path'     => '/',
        ]);
        session_start();

        // A persistent login is deliberately separate from the PHP session.
        // The browser only carries a random opaque token; the database stores
        // its SHA-256 hash, so a database leak does not hand out usable cookies.
        if (empty($_SESSION['uid'])) {
            self::restoreRememberedLogin();
        }
    }

    /**
     * Hands the session lock back while the page is still being built.
     *
     * PHP holds an exclusive lock on the session file for the whole request,
     * so two requests from the same browser never run side by side. A panel
     * screen that spends a second on its queries therefore blocks everything
     * else that browser asks for - the next tab, the studio, the API - and
     * the site looks frozen rather than slow.
     *
     * Everything already in $_SESSION stays readable; only writes after this
     * point are lost, so it goes after the last thing that writes (the CSRF
     * token is minted here for exactly that reason) and after any redirect.
     */
    public static function releaseSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        self::csrfToken();   // the page below will ask for it in every form
        @session_write_close();
    }

    private static function rememberCookieOptions(int $expires): array
    {
        return [
            'expires'  => $expires,
            'path'     => '/',
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    private static function clearRememberCookie(): void
    {
        if (!headers_sent()) {
            setcookie(self::REMEMBER_COOKIE, '', self::rememberCookieOptions(time() - 3600));
        }
        unset($_COOKIE[self::REMEMBER_COOKIE]);
    }

    private static function issueRememberToken(int $userId): void
    {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $now = time();
        $expires = $now + self::REMEMBER_TTL;

        // A user can keep several devices signed in. Replacing only the token
        // from this browser would otherwise log their other devices out.
        Db::pdo()->prepare(
            'INSERT INTO remember_tokens (user_id, token_hash, created_at, expires_at, last_used_at) VALUES (?, ?, ?, ?, ?)'
        )->execute([$userId, $hash, $now, $expires, $now]);

        if (!headers_sent()) {
            setcookie(self::REMEMBER_COOKIE, $token, self::rememberCookieOptions($expires));
        }
        $_COOKIE[self::REMEMBER_COOKIE] = $token;
    }

    private static function revokeCurrentRememberToken(): void
    {
        $token = trim((string) ($_COOKIE[self::REMEMBER_COOKIE] ?? ''));
        if ($token !== '' && preg_match('/^[a-f0-9]{64}$/', $token)) {
            Db::pdo()->prepare('DELETE FROM remember_tokens WHERE token_hash = ?')
                ->execute([hash('sha256', $token)]);
        }
        self::clearRememberCookie();
    }

    private static function restoreRememberedLogin(): void
    {
        $token = trim((string) ($_COOKIE[self::REMEMBER_COOKIE] ?? ''));
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/', $token)) {
            return;
        }

        $hash = hash('sha256', $token);
        $st = Db::pdo()->prepare(
            'SELECT rt.user_id, rt.expires_at, u.*
             FROM remember_tokens rt
             JOIN users u ON u.id = rt.user_id
             WHERE rt.token_hash = ?
             LIMIT 1'
        );
        $st->execute([$hash]);
        $row = $st->fetch();

        if (!$row || (int) $row['expires_at'] <= time() || (int) $row['is_blocked'] === 1 || empty($row['verified_at'])) {
            Db::pdo()->prepare('DELETE FROM remember_tokens WHERE token_hash = ?')->execute([$hash]);
            self::clearRememberCookie();
            return;
        }

        $uid = (int) $row['user_id'];
        $_SESSION['uid'] = $uid;
        $_SESSION['issued_at'] = time();
        $_SESSION['seen_at'] = time();
        $_SESSION['rotated_at'] = time();
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $_SESSION['h3d_token'] = bin2hex(random_bytes(32));
        self::$userCache = $row;
        self::$userCacheId = $uid;

        Db::pdo()->prepare('UPDATE remember_tokens SET last_used_at = ? WHERE token_hash = ?')
            ->execute([time(), $hash]);
    }

    /** One answer for the whole application; see Security::isHttps(). */
    public static function isHttps(): bool
    {
        return Security::isHttps();
    }

    /** IPs are only ever stored hashed — the raw address is never written. */
    public static function ipHash(): string
    {
        // X-Forwarded-For is supplied by the client unless a reverse proxy
        // is explicitly trusted. Accepting it unconditionally made it easy
        // to bypass an IP-based login limit by inventing a new header.
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        if (getenv('H3D_TRUST_PROXY') === '1') {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $ip;
        }
        $ip = trim(explode(',', $ip)[0]);
        return substr(hash('sha256', 'h3d|' . $ip), 0, 32);
    }

    public static function user(): ?array
    {
        self::start();
        if (empty($_SESSION['uid'])) {
            return null;
        }

        $uid = (int) $_SESSION['uid'];
        $now = time();
        $seen = (int) ($_SESSION['seen_at'] ?? 0);
        if ($seen > 0 && $now - $seen > self::IDLE_TIMEOUT) {
            self::clearSession();
            /*
             * The session is short on purpose; the remembered login is what
             * is supposed to carry a signed-in browser across the gap. Ask
             * for it here, in the request that just threw the session away.
             *
             * Leaving it to the next request is what produced the report of
             * opening the site signed out and then being signed in by the
             * mere act of clicking Sign in: start() consults the token when
             * it finds no user in the session, so the SECOND request always
             * came back signed in. The first one - the page the person was
             * actually looking at - did not.
             *
             * A token belonging to a blocked or unverified account is
             * refused inside restoreRememberedLogin(), so this cannot let
             * anyone back in through the timeout.
             */
            self::restoreRememberedLogin();
            if (empty($_SESSION['uid'])) {
                return null;
            }
            $uid = (int) $_SESSION['uid'];
            $seen = $now;
        }

        if (self::$userCacheId === $uid && self::$userCache !== null) {
            $u = self::$userCache;
        } else {
            $st = Db::pdo()->prepare('SELECT * FROM users WHERE id = ?');
            $st->execute([$uid]);
            $u = $st->fetch() ?: null;
            self::$userCache = $u;
            self::$userCacheId = $uid;
        }
        if (!$u || (int) $u['is_blocked'] === 1 || empty($u['verified_at'])) {
            self::clearSession();
            return null;
        }

        $_SESSION['seen_at'] = $now;
        $_SESSION['issued_at'] = (int) ($_SESSION['issued_at'] ?? $now);
        $rotated = (int) ($_SESSION['rotated_at'] ?? 0);
        if ($now - $rotated >= self::REKEY_EVERY && !headers_sent()) {
            session_regenerate_id(true);
            $_SESSION['rotated_at'] = $now;
        }

        // The daily allowance is applied on the first request after local
        // midnight. Doing it here means every entry point gets it without
        // having to remember to call it.
        if (DailyCredits::grant($u) > 0) {
            $st = Db::pdo()->prepare('SELECT * FROM users WHERE id = ?');
            $st->execute([$uid]);
            $u = $st->fetch() ?: $u;
            self::$userCache = $u;
        }

        return $u;
    }

    public static function requireUser(string $redirect = 'login.php', bool $allowPending = false): array
    {
        $u = self::user();
        if (!$u) {
            header('Location: ' . $redirect);
            exit;
        }

        // A handed-out password gets the account no further than the page
        // where it is replaced. Enforced here rather than on each page, so a
        // page added later cannot forget about it.
        if (!$allowPending && self::mustChangePassword($u)) {
            header('Location: change-password.php');
            exit;
        }

        return $u;
    }

    /** How long a reset link stays usable. */
    private const RESET_TTL = 7200;

    /**
     * Starts a password reset.
     *
     * Returns the token so the caller can build the link, or null when the
     * address is unknown. The caller must not tell the visitor which it was:
     * a form that answers differently for a known address is a way to find
     * out who has an account here.
     */
    public static function beginReset(string $email): ?string
    {
        $u = self::byEmail(trim($email));
        if (!$u) {
            return null;
        }

        $token = bin2hex(random_bytes(24));

        Db::pdo()->prepare(
            'UPDATE users SET reset_token = ?, reset_sent_at = ? WHERE id = ?'
        )->execute([$token, time(), (int) $u['id']]);

        return $token;
    }

    /**
     * Is this display name one of the words reserved for the shop?
     *
     * Folded to bare ASCII and lower case first, so Správce, spravce and
     * SPRAVCE are one word. The list is editable in the panel.
     */
    public static function nameReserved(string $name): bool
    {
        $fold = static function (string $s): string {
            $s = mb_strtolower(trim($s), 'UTF-8');
            $map = ['á'=>'a','č'=>'c','ď'=>'d','é'=>'e','ě'=>'e','í'=>'i','ň'=>'n','ó'=>'o',
                    'ř'=>'r','š'=>'s','ť'=>'t','ú'=>'u','ů'=>'u','ý'=>'y','ž'=>'z'];
            return strtr($s, $map);
        };
        $wanted = $fold($name);
        if ($wanted === '') { return false; }
        foreach (explode(',', (string) Settings::get('reserved_names')) as $word) {
            if ($fold($word) !== '' && $fold($word) === $wanted) { return true; }
        }
        return false;
    }

    /**
     * Check a display name. Empty is allowed - it simply means "no name".
     *
     * Deliberately NOT unique. It used to be, because it was also a way to
     * sign in; now that the e-mail is the only identifier, insisting on
     * uniqueness would only reject a perfectly good name because a stranger
     * typed it first. Identity in the panel and on orders is the address.
     *
     * @return array{0:bool,1:string} ok + message key
     */
    public static function checkDisplayName(string $nick): array
    {
        if ($nick === '') { return [true, '']; }
        /*
         * Latin letters, so Tomáš and Müller can write their own names, and
         * digits, space and _ . - . Deliberately NOT every script: the
         * reserved list folds Czech diacritics, and Cyrillic а in "аdmin"
         * would sail past it looking identical to the Latin one.
         */
        if (!preg_match('/^[\p{Latin}\p{N}_.\- ]{3,20}$/u', $nick)) {
            return [false, 'Display name must be 3-20 letters, numbers or _ . -'];
        }
        if (filter_var($nick, FILTER_VALIDATE_EMAIL)) {
            return [false, 'Display name cannot be an email address.'];
        }
        if (self::nameReserved($nick)) {
            return [false, 'That name is reserved for the shop. Please pick another.'];
        }
        return [true, ''];
    }

    /** Change the public display name. @return array{0:bool,1:string} */
    public static function changeNickname(int $userId, string $nick): array
    {
        $nick = trim($nick);
        [$ok, $msg] = self::checkDisplayName($nick);
        if (!$ok) { return [false, $msg]; }
        Db::pdo()->prepare('UPDATE users SET nickname = ? WHERE id = ?')->execute([$nick, $userId]);
        return [true, ''];
    }

    /** Start an e-mail change: store it pending and hand back a confirm token.
     *  @return array{0:bool,1:string} success + (token on success, else error) */
    public static function beginEmailChange(int $userId, string $newEmail): array
    {
        $newEmail = self::normaliseEmail($newEmail);
        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
            return [false, 'That does not look like an email address.'];
        }
        $st = Db::pdo()->prepare('SELECT 1 FROM users WHERE email = ? AND id <> ?');
        $st->execute([$newEmail, $userId]);
        if ($st->fetchColumn()) {
            return [false, 'That email is already registered.'];
        }

        $token = bin2hex(random_bytes(24));
        Db::pdo()->prepare(
            'UPDATE users SET pending_email = ?, email_change_token = ?, email_change_at = ? WHERE id = ?'
        )->execute([$newEmail, $token, time(), $userId]);

        return [true, $token];
    }

    /** Apply a pending e-mail change once its link is used (valid 24 h).
     *  @return array{0:bool,1:string} */
    public static function confirmEmailChange(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            return [false, 'Missing token.'];
        }
        $st = Db::pdo()->prepare("SELECT * FROM users WHERE email_change_token = ? AND pending_email <> ''");
        $st->execute([$token]);
        $u = $st->fetch();
        if (!$u) {
            return [false, 'This confirmation link is invalid or has already been used.'];
        }
        if (time() - (int) ($u['email_change_at'] ?? 0) > 86400) {
            return [false, 'This confirmation link has expired.'];
        }
        // The address could have been taken in the meantime.
        $taken = Db::pdo()->prepare('SELECT 1 FROM users WHERE email = ? AND id <> ?');
        $taken->execute([$u['pending_email'], (int) $u['id']]);
        if ($taken->fetchColumn()) {
            return [false, 'That email is now registered to another account.'];
        }

        Db::pdo()->prepare(
            "UPDATE users SET email = ?, pending_email = '', email_change_token = '', email_change_at = NULL WHERE id = ?"
        )->execute([$u['pending_email'], (int) $u['id']]);

        return [true, (string) $u['pending_email']];
    }

    /** The account a reset token belongs to, if it is still valid. */
    public static function userForResetToken(string $token): ?array
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }

        $st = Db::pdo()->prepare('SELECT * FROM users WHERE reset_token = ?');
        $st->execute([$token]);
        $u = $st->fetch();

        if (!$u) {
            return null;
        }

        if (time() - (int) $u['reset_sent_at'] > self::RESET_TTL) {
            return null;
        }

        return $u;
    }

    /**
     * Sets a password.
     *
     * Clearing the reset token here is what makes a link single use, and
     * mustChange marks a password somebody was given rather than chose.
     */
    public static function setPassword(int $userId, string $password, bool $mustChange = false): void
    {
        Db::pdo()->prepare(
            'UPDATE users
             SET pass_hash = ?, reset_token = NULL, reset_sent_at = NULL,
                 must_change_password = ?
             WHERE id = ?'
        )->execute([password_hash($password, PASSWORD_DEFAULT), $mustChange ? 1 : 0, $userId]);

        // Password changes invalidate every persistent login for this account.
        Db::pdo()->prepare('DELETE FROM remember_tokens WHERE user_id = ?')->execute([$userId]);
    }

    /** The shortest password the site will take. */
    public const PASSWORD_MIN = 8;

    /**
     * What is wrong with this password, as a translation key, or null.
     *
     * One place for the rules, because they used to live at the registration
     * form alone: the reset link, the change-password screen and the
     * installer each asked for eight characters and nothing else, so an
     * account could come out of a password reset weaker than the same
     * account was allowed to be created. A rule enforced at one door is not
     * a rule.
     *
     * The answer names the ONE thing to fix rather than reciting all four,
     * because "the password needs a digit" is something a person can act on
     * and a list of requirements is something they have to re-read.
     */
    public static function passwordProblem(string $password): ?string
    {
        if (strlen($password) < self::PASSWORD_MIN) { return 'pw.short'; }
        if (!preg_match('/[a-z]/', $password))      { return 'pw.nolower'; }
        if (!preg_match('/[A-Z]/', $password))      { return 'pw.noupper'; }
        if (!preg_match('/\d/', $password))         { return 'pw.nodigit'; }
        return null;
    }

    /** A temporary password: readable aloud, still not worth guessing. */
    public static function temporaryPassword(): string
    {
        // No l, I, 0 or O - these get read off a screen and typed by hand,
        // and a password nobody can transcribe is a support request.
        $alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $out = '';

        for ($i = 0; $i < 12; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    }

    public static function mustChangePassword(?array $u): bool
    {
        return $u !== null && (int) ($u['must_change_password'] ?? 0) === 1;
    }

    /** Roles, in order of privilege. */
    public const ROLES = [
        'user'    => 'Uživatel',
        'super'   => 'Super user',
        'manager' => 'Správce',      // orders only
        'admin'   => 'Administrátor',
    ];

    public static function role(?array $u): string
    {
        if (!$u) {
            return 'user';
        }

        // The flag wins when it is set. It is the older of the two, so
        // anything that writes only is_admin - a migration, a backup restore,
        // a hand-edited row - still produces an administrator rather than
        // silently demoting one.
        if ((int) ($u['is_admin'] ?? 0) === 1) {
            return 'admin';
        }

        $role = (string) ($u['role'] ?? '');

        return isset(self::ROLES[$role]) ? $role : 'user';
    }

    public static function isAdmin(?array $u): bool
    {
        return self::role($u) === 'admin';
    }

    /**
     * Admins and super users export without limits.
     *
     * Kept as one question rather than two checks scattered about, so a
     * later fourth role cannot end up privileged in one place and not
     * another.
     */
    public static function isPrivileged(?array $u): bool
    {
        return in_array(self::role($u), ['admin', 'super', 'manager'], true);
    }

    /**
     * The language to address this account in - their own choice, or the site
     * default when they have not made one. Used to pick the e-mail language.
     */
    public static function userLang(?array $u): string
    {
        $l = $u['lang'] ?? null;
        if ($l === 'cs' || $l === 'en') {
            return $l;
        }
        return Settings::get('default_lang') === 'cs' ? 'cs' : 'en';
    }

    /**
     * When a time-based package's cover runs out, or null if there is none
     * running. A subscription buys the same unlimited exports a privileged
     * role has, but only until this moment.
     */
    public static function subscribedUntil(?array $u): ?int
    {
        if (!$u) {
            return null;
        }
        $ts = $u['subscription_until'] ?? null;
        return ($ts !== null && (int) $ts > time()) ? (int) $ts : null;
    }

    public static function isSubscribed(?array $u): bool
    {
        return self::subscribedUntil($u) !== null;
    }

    /**
     * Unlimited exports, whichever way it was earned - a privileged role or a
     * running subscription. The quota check and the account page both ask this
     * one question so the two can never disagree about who is metered.
     */
    public static function isUnlimited(?array $u): bool
    {
        return self::isPrivileged($u) || self::isSubscribed($u);
    }

    /**
     * Managers get into the panel, but only as far as the orders they are
     * there to process. Kept separate from isAdmin so nothing else opens up
     * by accident.
     */
    public static function canManageOrders(?array $u): bool
    {
        return in_array(self::role($u), ['admin', 'manager'], true);
    }

    /** Sets a role and keeps the legacy flag in step. */
    public static function setRole(int $userId, string $role): void
    {
        if (!isset(self::ROLES[$role])) {
            throw new InvalidArgumentException('Neznámá role.');
        }

        Db::pdo()->prepare('UPDATE users SET role = ?, is_admin = ? WHERE id = ?')
            ->execute([$role, $role === 'admin' ? 1 : 0, $userId]);
    }

    /** Panel access for an administrator, or a manager on the orders tab. */
    public static function requirePanel(bool $ordersOnly = false): array
    {
        $u = self::user();

        if ($u && $ordersOnly && self::canManageOrders($u)) {
            return $u;
        }

        return self::requireAdmin();
    }

    public static function requireAdmin(): array
    {
        $u = self::user();

        if (!$u) {
            // The dedicated login endpoint stays available during planned
            // maintenance, unlike the studio. This also gives an operator a
            // reliable way to enter the panel and turn maintenance off.
            header('Location: ../login.php?next=' . urlencode('admin/index.php'));
            exit;
        }

        if (!self::isAdmin($u)) {
            // A normal customer never needs an administration error page.
            // Send them straight back to their workspace; managers are
            // handled by requirePanel() above and retain their order view.
            header('Location: ../index.php');
            exit;
        }

        return $u;
    }

    public static function anyAdminExists(): bool
    {
        return (bool) Db::pdo()->query('SELECT 1 FROM users WHERE is_admin = 1 LIMIT 1')->fetchColumn();
    }

    public static function throttled(): bool
    {
        $st = Db::pdo()->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE ip_hash = ? AND ok = 0 AND created_at > ?'
        );
        $st->execute([self::ipHash(), time() - self::WINDOW]);
        return (int) $st->fetchColumn() >= self::MAX_ATTEMPTS;
    }

    private static function loginKey(string $identifier): string
    {
        return substr(hash('sha256', 'h3d-login|' . self::normaliseEmail($identifier)), 0, 32);
    }

    private static function identifierThrottled(string $identifier): bool
    {
        $st = Db::pdo()->prepare(
            'SELECT COUNT(*) FROM login_attempts WHERE email = ? AND ok = 0 AND created_at > ?'
        );
        $st->execute([self::loginKey($identifier), time() - self::WINDOW]);
        return (int) $st->fetchColumn() >= self::MAX_ACCOUNT_ATTEMPTS;
    }

    private static function logAttempt(string $email, bool $ok): void
    {
        $st = Db::pdo()->prepare(
            'INSERT INTO login_attempts (ip_hash, email, ok, created_at) VALUES (?, ?, ?, ?)'
        );
        // The old column name stays for compatibility, but values are an
        // opaque identifier hash: a failed-login log must not become a list
        // of addresses that were typed into the form.
        $st->execute([self::ipHash(), self::loginKey($email), $ok ? 1 : 0, time()]);
    }

    /** @return array{0:bool,1:string} success + message key */
    public static function login(string $email, string $password, bool $remember = false): array
    {
        if (strlen($email) > 254 || strlen($password) > 1024
            || self::throttled() || self::identifierThrottled($email)) {
            return [false, 'Too many attempts. Try again later.'];
        }

        /*
         * The e-mail address is the only identifier there is.
         *
         * Signing in with a display name as well meant the name had to be
         * unique and public, which turned it into a second, weaker password
         * field: it could be looked up, guessed, and reused. An address is
         * something the account already proves ownership of.
         */
        $id = trim($email);
        $st = Db::pdo()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $st->execute([self::normaliseEmail($id)]);
        $u = $st->fetch();

        // password_verify against a dummy hash keeps the timing of an unknown
        // email similar to a wrong password.
        $hash = $u['pass_hash'] ?? '$2y$10$invalidinvalidinvalidinvalidinvalidinvalidinvalidinvalidin';

        if (!password_verify($password, $hash) || !$u) {
            self::logAttempt($email, false);
            return [false, 'Wrong email or password.'];
        }

        if ((int) $u['is_blocked'] === 1) {
            self::logAttempt($email, false);
            return [false, 'Wrong email or password.'];
        }

        if (empty($u['verified_at'])) {
            self::logAttempt($email, false);
            return [false, 'Please verify your email address before signing in.'];
        }

        self::start();

        // Rotate the session id so a fixated id cannot survive the login.
        // Skipped when output has already begun, which only happens under
        // CLI test runs; warning there would be noise, not a real problem.
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION['uid'] = (int) $u['id'];
        $_SESSION['issued_at'] = time();
        $_SESSION['seen_at'] = time();
        $_SESSION['rotated_at'] = time();
        // The anonymous-page token must not survive into this authenticated
        // session after the identifier has just been rotated.
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        $_SESSION['h3d_token'] = bin2hex(random_bytes(32));
        self::$userCache = $u;
        self::$userCacheId = (int) $u['id'];

        Db::pdo()->prepare('UPDATE users SET last_login_at = ? WHERE id = ?')
                 ->execute([time(), (int) $u['id']]);

        // The checkbox controls only the 30-day persistent cookie. A normal
        // login keeps the existing session-cookie behaviour.
        if ($remember) {
            self::issueRememberToken((int) $u['id']);
        } else {
            // If this browser previously asked to be remembered, explicitly
            // revoke that token so unchecking the box really means 'this time only'.
            self::revokeCurrentRememberToken();
        }

        self::logAttempt($email, true);
        return [true, ''];
    }

    public static function logout(): void
    {
        self::start();
        self::revokeCurrentRememberToken();
        self::clearSession();
    }

    private static function clearSession(): void
    {
        self::$userCache = null;
        self::$userCacheId = null;
        unset($_SESSION['uid'], $_SESSION['issued_at'], $_SESSION['seen_at'], $_SESSION['rotated_at'], $_SESSION['csrf']);
        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }
    }

    public static function normaliseEmail(string $e): string
    {
        $e = trim($e);
        // mbstring is not guaranteed to be enabled on every PHP build,
        // and email local parts are ASCII in practice.
        return function_exists('mb_strtolower') ? mb_strtolower($e, 'UTF-8') : strtolower($e);
    }

    /** @return array{0:bool,1:string} success + message */
    public static function register(string $email, string $password, bool $asAdmin = false, string $nickname = '', string $refCode = ''): array
    {
        $email = self::normaliseEmail($email);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [false, 'That does not look like an email address.'];
        }
        $weak = self::passwordProblem($password);
        if ($weak !== null) {
            // Localised, unlike the rest of this method's answers: the sign-up
            // form prints it straight out, and an English sentence about
            // digits in the middle of a Czech page is just noise.
            return [false, __($weak)];
        }

        /*
         * The form no longer asks for a name; what goes in is the part of
         * the address before the @, purely so the support chat has something
         * friendlier than the whole address to show. It is a label, not an
         * identifier: not unique, and never used to sign in.
         *
         * A derived name that happens to be reserved (info@, support@) is
         * dropped rather than refused - the address is perfectly legitimate,
         * and refusing the registration over an automatic label would be
         * absurd. The member can set a proper one later.
         */
        $nickname = trim($nickname);
        if ($nickname === '') {
            $nickname = (string) strstr($email, '@', true);
            if (self::nameReserved($nickname) || !preg_match('/^[\p{Latin}\p{N}_.\- ]{3,20}$/u', $nickname)) {
                $nickname = '';
            }
        } else {
            [$nickOk, $nickMsg] = self::checkDisplayName($nickname);
            if (!$nickOk) { return [false, $nickMsg]; }
        }

        $exists = Db::pdo()->prepare('SELECT 1 FROM users WHERE email = ?');
        $exists->execute([$email]);
        if ($exists->fetchColumn()) {
            return [false, 'That email is already registered.'];
        }

        // A friend's invite code links the new account to its inviter; the
        // reward itself waits for the e-mail verification. A wrong code is a
        // hard error - silently dropping it would cost both sides the bonus.
        $referrerId = null;
        $refCode    = trim($refCode);
        if ($refCode !== '' && Referral::enabled()) {
            $referrerId = Referral::resolve($refCode);
            if ($referrerId === null) {
                return [false, 'That referral code does not exist.'];
            }
        }

        // The welcome credits are held back until the address is confirmed.
        // Granting them at registration would let one person mint credits by
        // signing up with addresses they do not own.
        $token    = bin2hex(random_bytes(20));
        $verified = $asAdmin || !Settings::bool('require_verify') ? time() : null;
        $verifyExpiresAt = $verified === null ? time() + self::VERIFY_GRACE : null;

        // The bonus is written down now, at sign-up, and paid out later from
        // what is written here. The e-mail names an amount, and that promise
        // has to survive the operator changing the offer in the meantime.
        $bonus     = max(0, Settings::int('signup_bonus'));
        $bonusDays = max(0, Settings::int('signup_bonus_days'));

        Db::transact(function (PDO $pdo) use ($email, $nickname, $password, $asAdmin, $token, $verified, $verifyExpiresAt, $referrerId, $bonus, $bonusDays) {
            $st = $pdo->prepare(
                'INSERT INTO users (email, nickname, pass_hash, credits, is_admin, role, created_at,
                                    verified_at, verify_token, verify_expires_at, referred_by, signup_bonus, signup_bonus_days)
                 VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $st->execute([
                $email,
                $nickname,
                password_hash($password, PASSWORD_DEFAULT),
                $asAdmin ? 1 : 0,
                $asAdmin ? 'admin' : 'user',
                time(),
                $verified,
                $verified === null ? $token : null,
                $verifyExpiresAt,
                $referrerId,
                $bonus,
                $bonusDays,
            ]);
        });

        $user = self::byEmail($email);

        if ($verified !== null) {
            // Verification is switched off (or this is the first admin), so
            // the bonus is due immediately - the referral reward too.
            Verify::grantBonus($user);
            Referral::rewardOnVerify(self::byEmail($email));
        } else {
            Verify::sendLink($user);
        }

        return [true, ''];
    }

    public static function byEmail(string $email): ?array
    {
        $st = Db::pdo()->prepare('SELECT * FROM users WHERE email = ?');
        $st->execute([strtolower(trim($email))]);
        return $st->fetch() ?: null;
    }

    public static function byId(int $id): ?array
    {
        $st = Db::pdo()->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    // ---- CSRF ----

    public static function csrfToken(): string
    {
        self::start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['csrf'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf" value="' . htmlspecialchars(self::csrfToken(), ENT_QUOTES) . '">';
    }

    public static function csrfIsValid(?string $sent = null): bool
    {
        self::start();
        $sent = (string) ($sent ?? ($_POST['csrf'] ?? ''));
        return $sent !== ''
            && !empty($_SESSION['csrf'])
            && hash_equals((string) $_SESSION['csrf'], $sent);
    }

    public static function checkCsrf(): void
    {
        self::start();
        if (!Security::sameOriginRequest()) {
            http_response_code(403);
            exit('Cross-origin request denied.');
        }
        if (!self::csrfIsValid()) {
            http_response_code(400);
            exit('Invalid CSRF token. Reload the page and try again.');
        }
    }
}
