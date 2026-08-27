<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Email confirmation.
 *
 * The account is usable straight after registration - only the welcome
 * bonus waits for confirmation. That keeps the tool available to someone
 * whose mail is slow or whose provider swallowed the message, while still
 * making the bonus worth something.
 */
final class Verify
{
    /** Minimum gap between verification mails, in seconds. */
    private const RESEND_COOLDOWN = 300;

    /**
     * The bonus this account was promised: credits and free days.
     *
     * Taken from the account, not from the settings, because the settings
     * are what the offer is *now* and this is what was offered *then*. The
     * two differ the moment an operator edits the panel while somebody has
     * an unconfirmed message sitting in their inbox.
     *
     * Accounts created before the columns existed have NULL in them; for
     * those the current settings are the honest answer, since that is what
     * they were promised too.
     *
     * @return array{0:int,1:int} credits, days
     */
    public static function promisedBonus(?array $user): array
    {
        $credits = $user['signup_bonus'] ?? null;
        $days    = $user['signup_bonus_days'] ?? null;

        if ($credits === null && $days === null) {
            return [
                max(0, Settings::int('signup_bonus')),
                max(0, Settings::int('signup_bonus_days')),
            ];
        }

        return [max(0, (int) $credits), max(0, (int) $days)];
    }

    public static function isVerified(?array $user): bool
    {
        return $user !== null && !empty($user['verified_at']);
    }

    /** Absolute URL carrying the token. */
    public static function link(array $user): string
    {
        return originUrl() . '/verify.php?token=' . urlencode((string) $user['verify_token']);
    }

    /**
     * Sends (or re-sends) the confirmation link.
     *
     * @return array{0:bool,1:string} sent, message
     */
    public static function sendLink(?array $user, bool $force = false): array
    {
        if (!$user) {
            return [false, 'Unknown account.'];
        }
        if (self::isVerified($user)) {
            return [false, 'This address is already confirmed.'];
        }
        if (!Mailer::enabled()) {
            return [false, 'Mail sending is not configured.'];
        }

        $lastSent = (int) ($user['verify_sent_at'] ?? 0);
        if (!$force && $lastSent > 0 && time() - $lastSent < self::RESEND_COOLDOWN) {
            $wait = self::RESEND_COOLDOWN - (time() - $lastSent);
            return [false, "Please wait $wait s before requesting another message."];
        }

        // A missing token would make the link useless; mint one rather than
        // send a broken message.
        if (empty($user['verify_token'])) {
            $token = bin2hex(random_bytes(20));
            Db::pdo()->prepare('UPDATE users SET verify_token = ? WHERE id = ?')
                     ->execute([$token, (int) $user['id']]);
            $user['verify_token'] = $token;
        }

        Notify::$lang = Auth::userLang($user);
        [$bonus, $days] = self::promisedBonus($user);
        $msg = Notify::verification(self::link($user), $bonus, $days);

        // Use the same notification pipeline as every other transactional
        // message. This keeps verification mail identical to the mail path
        // already proven by the working admin test and preserves the HTML +
        // plain-text alternative for normal mail clients.
        [$ok, $err] = Mailer::notify((string) $user['email'], $msg, 'verify');

        if ($ok) {
            Db::pdo()->prepare('UPDATE users SET verify_sent_at = ? WHERE id = ?')
                     ->execute([time(), (int) $user['id']]);
            return [true, 'Confirmation email sent.'];
        }

        return [false, $err];
    }

    /**
     * Confirms a token.
     *
     * @return array{0:bool,1:string,2:?array} ok, message, user
     */
    public static function confirm(string $token): array
    {
        $token = trim($token);
        if ($token === '') {
            return [false, 'Missing confirmation token.', null];
        }

        $st = Db::pdo()->prepare(
            'SELECT * FROM users
              WHERE verify_token = ?
                AND verified_at IS NULL
                AND is_blocked = 0
                AND (verify_expires_at IS NULL OR verify_expires_at > ?)
              LIMIT 1'
        );
        $st->execute([$token, time()]);
        $user = $st->fetch();

        if (!$user) {
            // Either already used or never valid. The two cases are not
            // distinguished on purpose, so the endpoint cannot be used to
            // probe for live tokens.
            return [false, 'This confirmation link is not valid or has already been used.', null];
        }

        Db::pdo()->prepare(
            'UPDATE users
                SET verified_at = ?, verify_token = NULL, verify_expires_at = NULL, verify_blocked_at = NULL
              WHERE id = ?'
        )->execute([time(), (int) $user['id']]);

        $user = Auth::byId((int) $user['id']);
        $grantedDays = 0;
        $granted = self::grantBonus($user, $grantedDays);

        // A referred newcomer confirming their address triggers the one-off
        // reward for both sides of the referral.
        Referral::rewardOnVerify($user);

        // Days alone are a bonus too - a "first week free" account used to
        // confirm in silence because only credits counted as worth a word.
        if (($granted > 0 || $grantedDays > 0) && Mailer::enabled()) {
            Notify::$lang = Auth::userLang($user);
            Mailer::notify(
                (string) $user['email'],
                Notify::verified($granted, Credits::balance((int) $user['id']), $grantedDays),
                'verified'
            );
        }

        return [true, 'Your email address is confirmed.', Auth::byId((int) $user['id'])];
    }

    /**
     * Releases the welcome bonus, once per account.
     *
     * Pays what the account was promised at sign-up (see promisedBonus), so
     * the amount in somebody's inbox is the amount they get.
     *
     * @param ?int $daysGiven filled with the free days granted, if any - the
     *                        return value only counts credits, and a bonus
     *                        can be days alone.
     * @return int credits granted
     */
    public static function grantBonus(?array $user, ?int &$daysGiven = null): int
    {
        $daysGiven = 0;

        if (!$user || !empty($user['verify_bonus_at'])) {
            return 0;
        }

        [$bonus, $days] = self::promisedBonus($user);
        if ($bonus === 0 && $days === 0) {
            return 0;
        }

        try {
            $paid = Db::transact(function (PDO $pdo) use ($user, $bonus, $days) {
                // Claim the grant and pay it in the same transaction, so two
                // clicks on the link cannot pay the bonus twice.
                $st = $pdo->prepare(
                    'UPDATE users SET verify_bonus_at = ? WHERE id = ? AND verify_bonus_at IS NULL'
                );
                $st->execute([time(), (int) $user['id']]);

                if ($st->rowCount() === 0) {
                    // Somebody else claimed it first: nothing paid here, and
                    // no free days either.
                    return null;
                }

                if ($bonus > 0) {
                    $st = $pdo->prepare('SELECT credits FROM users WHERE id = ?');
                    $st->execute([(int) $user['id']]);
                    $balance = (int) $st->fetchColumn() + $bonus;

                    $pdo->prepare('UPDATE users SET credits = ? WHERE id = ?')
                        ->execute([$balance, (int) $user['id']]);

                    $pdo->prepare(
                        'INSERT INTO ledger (user_id, delta, balance_after, reason, ref, created_at)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    )->execute([(int) $user['id'], $bonus, $balance, 'signup', null, time()]);
                }

                // A time bonus is unlimited exports for a stretch, the same as
                // a short pass - handy for a "first week free" on sign-up.
                if ($days > 0) {
                    Orders::grantSubscription((int) $user['id'], $days);
                }

                return $bonus;
            });
        } catch (Throwable $e) {
            error_log('[h3d] signup bonus failed: ' . $e->getMessage());
            return 0;
        }

        // null = the grant was already claimed, so neither half was paid.
        if ($paid === null) {
            return 0;
        }

        $daysGiven = $days;

        return (int) $paid;
    }
}
