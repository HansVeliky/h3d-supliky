<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

final class Codes
{
    /** Ambiguous characters are left out so codes survive being read aloud. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Accented letters folded to their bare form.
     *
     * Written out rather than done with iconv or mbstring, neither of which
     * is guaranteed on this hosting. Both cases are listed because
     * strtoupper only knows ASCII.
     */
    private const FOLD = [
        'á'=>'a','ä'=>'a','à'=>'a','â'=>'a','ã'=>'a','å'=>'a','ą'=>'a',
        'č'=>'c','ć'=>'c','ç'=>'c','ď'=>'d','đ'=>'d',
        'é'=>'e','ě'=>'e','è'=>'e','ê'=>'e','ë'=>'e','ę'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ĺ'=>'l','ľ'=>'l','ł'=>'l','ň'=>'n','ń'=>'n','ñ'=>'n',
        'ó'=>'o','ô'=>'o','ö'=>'o','ò'=>'o','õ'=>'o','ø'=>'o',
        'ŕ'=>'r','ř'=>'r','š'=>'s','ś'=>'s','ß'=>'ss','ť'=>'t',
        'ú'=>'u','ů'=>'u','ü'=>'u','ù'=>'u','û'=>'u',
        'ý'=>'y','ÿ'=>'y','ž'=>'z','ź'=>'z','ż'=>'z',
        'Á'=>'A','Ä'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Å'=>'A','Ą'=>'A',
        'Č'=>'C','Ć'=>'C','Ç'=>'C','Ď'=>'D','Đ'=>'D',
        'É'=>'E','Ě'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E','Ę'=>'E',
        'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I',
        'Ĺ'=>'L','Ľ'=>'L','Ł'=>'L','Ň'=>'N','Ń'=>'N','Ñ'=>'N',
        'Ó'=>'O','Ô'=>'O','Ö'=>'O','Ò'=>'O','Õ'=>'O','Ø'=>'O',
        'Ŕ'=>'R','Ř'=>'R','Š'=>'S','Ś'=>'S','Ť'=>'T',
        'Ú'=>'U','Ů'=>'U','Ü'=>'U','Ù'=>'U','Û'=>'U',
        'Ý'=>'Y','Ž'=>'Z','Ź'=>'Z','Ż'=>'Z',
    ];

    /** Human-readable form, falling back to the key for older rows. */
    public static function label(array $row): string
    {
        $label = trim((string) ($row['label'] ?? ''));
        return $label !== '' ? $label : (string) $row['code'];
    }

    /**
     * The key a code is matched by: accents folded, punctuation dropped,
     * upper case.
     *
     * Folding rather than deleting matters. "Vánoce-2026" used to key as
     * VNOCE2026, so the code printed on the card could only be redeemed by
     * somebody who typed the á - anyone writing "vanoce 2026" was told the
     * code does not exist, and "Vánoce" and "Vnoce" were the same code.
     */
    public static function normalise(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', strtr($code, self::FOLD)) ?? '');
    }

    /**
     * The key as it was built before accents were folded.
     *
     * Codes made under the old rule are stored under this form, and they are
     * out there on printed cards, so redeeming still tries it.
     */
    public static function legacyKey(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code) ?? '');
    }

    public static function generate(int $groups = 3, int $len = 4): string
    {
        $parts = [];
        for ($g = 0; $g < $groups; $g++) {
            $s = '';
            for ($i = 0; $i < $len; $i++) {
                $s .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $parts[] = $s;
        }
        return implode('-', $parts);
    }

    public static function create(
        int $credits,
        int $maxUses = 1,
        ?int $expiresAt = null,
        string $note = '',
        ?string $code = null,
        int $usesPerAccount = 1,
        int $subDays = 0
    ): string {
        // Two forms are kept: `label` is what a human reads off a card and
        // what the panel shows, `code` is the normalised key everything is
        // matched against. Storing only the normalised form meant an admin
        // who created H3D-WHO-10 saw H3DWHO10 in the list and could no
        // longer copy out the version they had printed.
        $label = $code !== null && trim($code) !== '' ? trim($code) : self::generate();
        $code  = self::normalise($label);

        if ($credits === 0 && $subDays <= 0) {
            throw new InvalidArgumentException('A code must grant credits or days.');
        }
        if (strlen($code) < 4) {
            throw new InvalidArgumentException('The code is too short.');
        }

        $st = Db::pdo()->prepare(
            'INSERT INTO codes (code, label, credits, sub_days, max_uses, uses_per_account, uses,
                                expires_at, active, note, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, ?, 1, ?, ?)'
        );

        try {
            $st->execute([
                $code, $label, $credits, max(0, $subDays), max(0, $maxUses), max(0, $usesPerAccount),
                $expiresAt, $note, time(),
            ]);
        } catch (PDOException $e) {
            throw new InvalidArgumentException('That code already exists.');
        }

        return $label;
    }

    /**
     * Redeem a code for a user.
     *
     * The whole thing runs in one immediate transaction: the use count, the
     * per-user uniqueness and the credit movement all commit together, so a
     * code with one use left cannot be claimed twice.
     *
     * @return array{0:bool,1:string,2:int,3:int} success, message, credits, days granted
     */
    public static function redeem(int $userId, string $input): array
    {
        // Matching ignores case and punctuation, so "h3d who 10" finds
        // H3D-WHO-10. What gets written down afterwards is the code's own
        // spelling, never this stripped key - see the ledger entry below.
        $code = self::normalise($input);
        if ($code === '') {
            return [false, 'Enter a code.', 0, 0];
        }

        // Guessing guard: more than 10 tries inside a minute freezes the
        // feature for this account for 24 hours. Legitimate use never gets
        // near that pace; a script cycling through codes does immediately.
        $pdo = Db::pdo();
        $now = time();

        $st = $pdo->prepare(
            'SELECT created_at FROM code_attempts
             WHERE user_id = ? AND is_block = 1 AND created_at > ?
             ORDER BY id DESC LIMIT 1'
        );
        $st->execute([$userId, $now - 86400]);
        $blockedAt = $st->fetchColumn();
        if ($blockedAt !== false) {
            $left = 86400 - ($now - (int) $blockedAt);
            $h    = max(1, (int) ceil($left / 3600));
            return [false, Lang::current() === 'cs'
                ? "Zadávání kódů je kvůli příliš mnoha pokusům zablokované. Zkus to znovu za ~{$h} h."
                : "Code entry is blocked after too many attempts. Try again in ~{$h} h.", 0, 0];
        }

        $st = $pdo->prepare(
            'SELECT COUNT(*) FROM code_attempts WHERE user_id = ? AND is_block = 0 AND created_at > ?'
        );
        $st->execute([$userId, $now - 60]);
        if ((int) $st->fetchColumn() >= 10) {
            Db::write(static function (PDO $pdo) use ($userId, $now): void {
                $pdo->prepare('INSERT INTO code_attempts (user_id, is_block, created_at) VALUES (?, 1, ?)')
                    ->execute([$userId, $now]);
            });
            return [false, Lang::current() === 'cs'
                ? 'Příliš mnoho pokusů za sebou. Zadávání kódů je na 24 hodin zablokované.'
                : 'Too many attempts in a row. Code entry is blocked for 24 hours.', 0, 0];
        }

        // Through Db::write: the reads just above leave this connection on an
        // old snapshot, and a write on top of that is refused outright the
        // moment anybody else commits. See the note on Db::write.
        Db::write(static function (PDO $pdo) use ($userId, $now): void {
            $pdo->prepare('INSERT INTO code_attempts (user_id, is_block, created_at) VALUES (?, 0, ?)')
                ->execute([$userId, $now]);
            // Opportunistic sweep so the table never grows without bound.
            $pdo->prepare('DELETE FROM code_attempts WHERE created_at < ?')->execute([$now - 90000]);
        });

        $legacy = self::legacyKey($input);

        try {
            $granted = Db::transact(function (PDO $pdo) use ($userId, $code, $legacy) {
                // Two keys, because codes created before accents were folded
                // are stored under the older form.
                $st = $pdo->prepare('SELECT * FROM codes WHERE code = ? OR code = ? ORDER BY code = ? DESC LIMIT 1');
                $st->execute([$code, $legacy, $code]);
                $c = $st->fetch();

                if (!$c) {
                    throw new RuntimeException('That code does not exist.');
                }
                if ((int) $c['active'] !== 1) {
                    throw new RuntimeException('That code is no longer active.');
                }
                if ($c['expires_at'] !== null && (int) $c['expires_at'] < time()) {
                    throw new RuntimeException('That code has expired.');
                }
                if ((int) $c['max_uses'] > 0 && (int) $c['uses'] >= (int) $c['max_uses']) {
                    throw new RuntimeException('That code has already been used up.');
                }

                // How many times this account has already used this code.
                // 0 in uses_per_account means no per-account limit at all.
                $perAccount = (int) ($c['uses_per_account'] ?? 1);

                $mine = $pdo->prepare('SELECT COUNT(*) FROM code_uses WHERE code_id = ? AND user_id = ?');
                $mine->execute([(int) $c['id'], $userId]);
                $used = (int) $mine->fetchColumn();

                if ($perAccount > 0 && $used >= $perAccount) {
                    throw new RuntimeException($perAccount === 1
                        ? 'You have already used that code.'
                        : "You have already used that code $perAccount times.");
                }

                $pdo->prepare('INSERT INTO code_uses (code_id, user_id, created_at) VALUES (?, ?, ?)')
                    ->execute([(int) $c['id'], $userId, time()]);

                $pdo->prepare('UPDATE codes SET uses = uses + 1 WHERE id = ?')
                    ->execute([(int) $c['id']]);

                $credits = (int) $c['credits'];
                $days    = (int) ($c['sub_days'] ?? 0);

                // Credits, if the code carries any. A days-only code skips the
                // ledger entirely rather than writing a zero movement.
                if ($credits !== 0) {
                    $bal = $pdo->prepare('SELECT credits FROM users WHERE id = ?');
                    $bal->execute([$userId]);
                    $before  = (int) $bal->fetchColumn();
                    $balance = max(0, $before + $credits);
                    // A negative (storno) code cannot take an account below
                    // zero. The ledger must record what was ACTUALLY taken,
                    // not the larger requested amount, otherwise its sum no
                    // longer matches the protected working balance.
                    $applied = $balance - $before;

                    $pdo->prepare('UPDATE users SET credits = ? WHERE id = ?')
                        ->execute([$balance, $userId]);

                    // The code as it is written on the card, not the stripped
                    // key: the history is read by the customer, and
                    // "H3DWHO10" is not a code anybody was ever given.
                    $pdo->prepare(
                        'INSERT INTO ledger (user_id, delta, balance_after, reason, ref, created_at)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    )->execute([$userId, $applied, $balance, 'code', self::label($c), time()]);

                    $credits = $applied;
                }

                // Time, if the code carries any - stacks onto whatever cover
                // the account already has.
                if ($days > 0) {
                    Orders::grantSubscription($userId, $days);
                }

                return ['credits' => $credits, 'days' => $days];
            });
        } catch (RuntimeException $e) {
            return [false, $e->getMessage(), 0, 0];
        }

        return [true, '', (int) $granted['credits'], (int) $granted['days']];
    }

    public static function all(): array
    {
        return Db::pdo()->query('SELECT * FROM codes ORDER BY id DESC')->fetchAll();
    }

    /**
     * Who redeemed a given code, newest first.
     *
     * A deleted account leaves its row behind with a null email rather than
     * removing the redemption, so the usage count and the list never
     * disagree about what happened.
     *
     * @return array<int, array{email: ?string, user_id: ?int, created_at: int}>
     */
    public static function usedBy(int $codeId): array
    {
        $st = Db::pdo()->prepare(
            'SELECT cu.user_id, cu.created_at, u.email
             FROM code_uses cu
             LEFT JOIN users u ON u.id = cu.user_id
             WHERE cu.code_id = ?
             ORDER BY cu.created_at DESC'
        );
        $st->execute([$codeId]);
        return $st->fetchAll();
    }

    /** Redemptions for every code in one query, keyed by code id. */
    public static function usageMap(): array
    {
        $rows = Db::pdo()->query(
            'SELECT cu.code_id, cu.user_id, cu.created_at, u.email
             FROM code_uses cu
             LEFT JOIN users u ON u.id = cu.user_id
             ORDER BY cu.created_at DESC'
        )->fetchAll();

        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['code_id']][] = $r;
        }
        return $map;
    }
}
