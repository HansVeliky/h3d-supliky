<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Referral program.
 *
 * Every account owns a short invite code. A newcomer who registers with one
 * is linked to the inviter, and once the newcomer confirms their e-mail both
 * sides receive the configured bonus - exactly once, guarded by a flag on the
 * invited account. Rewards stop counting for an inviter past the cap, which
 * keeps a farm of throwaway inboxes from milking the program.
 */
final class Referral
{
    /** Codes avoid 0/O/1/I so they survive being read aloud or retyped. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public static function enabled(): bool
    {
        return Settings::bool('referral_enabled') && Settings::int('referral_bonus') > 0;
    }

    /** The user's invite code, minted on first use. */
    public static function codeFor(array $user): string
    {
        $code = trim((string) ($user['ref_code'] ?? ''));
        if ($code !== '') {
            return $code;
        }

        $pdo = Db::pdo();
        // Collisions are astronomically unlikely at 8 chars of 32, but a
        // retry loop costs nothing and removes the "astronomically".
        for ($i = 0; $i < 5; $i++) {
            $code = '';
            for ($c = 0; $c < 8; $c++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $st = $pdo->prepare('SELECT 1 FROM users WHERE ref_code = ?');
            $st->execute([$code]);
            if (!$st->fetchColumn()) {
                $pdo->prepare('UPDATE users SET ref_code = ? WHERE id = ?')
                    ->execute([$code, (int) $user['id']]);
                return $code;
            }
        }

        throw new RuntimeException('Could not mint a referral code.');
    }

    /** Resolves a typed code to the inviting user's id, or null. */
    public static function resolve(string $code): ?int
    {
        $code = strtoupper(trim($code));
        if ($code === '' || !preg_match('/^[A-Z2-9]{4,16}$/', $code)) {
            return null;
        }
        $st = Db::pdo()->prepare('SELECT id FROM users WHERE ref_code = ?');
        $st->execute([$code]);
        $id = $st->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /**
     * Pays the one-off reward for a freshly verified account, if it was
     * referred and nothing has been paid for it yet.
     *
     * @return int tenths granted to the invited user (0 = nothing happened)
     */
    public static function rewardOnVerify(array $user): int
    {
        if (!self::enabled()) {
            return 0;
        }
        $referrerId = (int) ($user['referred_by'] ?? 0);
        if ($referrerId <= 0 || !empty($user['ref_rewarded'])) {
            return 0;
        }

        $pdo = Db::pdo();

        // Claim the flag first: whoever loses this race pays nobody twice.
        $claim = $pdo->prepare(
            'UPDATE users SET ref_rewarded = 1 WHERE id = ? AND ref_rewarded = 0'
        );
        $claim->execute([(int) $user['id']]);
        if ($claim->rowCount() === 0) {
            return 0;
        }

        $st = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$referrerId]);
        $referrer = $st->fetch();
        if (!$referrer) {
            return 0;
        }

        $bonus = max(0, Settings::int('referral_bonus'));
        if ($bonus === 0) {
            return 0;
        }

        // The invited side is always paid; the inviter only under the cap.
        Credits::apply((int) $user['id'], $bonus, 'referral', 'od ' . self::who($referrer));

        // Counted from the users table, not the ledger: the inviter's own
        // invitee-side bonus must not eat into their cap. The current invitee
        // is excluded because their flag was already claimed above.
        $cap  = max(0, Settings::int('referral_max'));
        $st   = $pdo->prepare(
            'SELECT COUNT(*) FROM users WHERE referred_by = ? AND ref_rewarded = 1 AND id != ?'
        );
        $st->execute([$referrerId, (int) $user['id']]);
        $paid = (int) $st->fetchColumn();
        if ($cap === 0 || $paid < $cap) {
            Credits::apply($referrerId, $bonus, 'referral', self::who($user));

            if (Mailer::enabled()) {
                Notify::$lang = Auth::userLang($referrer);
                Mailer::notify(
                    (string) $referrer['email'],
                    Notify::referralReward(Cred::fmt($bonus), Cred::fmt(Credits::balance($referrerId))),
                    'referral'
                );
            }
        }

        return $bonus;
    }

    /** How many invited accounts verified, what that earned, and the cap. */
    public static function statsFor(int $userId): array
    {
        $pdo = Db::pdo();
        $st  = $pdo->prepare(
            'SELECT COUNT(*) FROM users WHERE referred_by = ? AND ref_rewarded = 1'
        );
        $st->execute([$userId]);
        $count = (int) $st->fetchColumn();

        // Earned AS an inviter - the bonus this user may have received for
        // being invited themselves (rows marked "od …") is not counted here.
        $st = $pdo->prepare(
            "SELECT COALESCE(SUM(delta),0) FROM ledger
             WHERE user_id = ? AND reason = 'referral' AND delta > 0
               AND (ref IS NULL OR ref NOT LIKE 'od %')"
        );
        $st->execute([$userId]);
        $earned = (int) $st->fetchColumn();

        return [
            'invited' => $count,
            'earned'  => $earned,
            'cap'     => max(0, Settings::int('referral_max')),
        ];
    }

    /**
     * The people this user invited, newest first: masked identity, when they
     * registered and whether the reward already fired (= they verified).
     *
     * @return list<array{who:string,at:int,verified:bool}>
     */
    public static function invitedBy(int $userId, int $limit = 20): array
    {
        $st = Db::pdo()->prepare(
            'SELECT nickname, email, created_at, ref_rewarded, verified_at
             FROM users WHERE referred_by = ? ORDER BY id DESC LIMIT ?'
        );
        $st->execute([$userId, $limit]);

        $out = [];
        foreach ($st->fetchAll() as $r) {
            $out[] = [
                'who'      => self::who($r),
                'at'       => (int) $r['created_at'],
                'verified' => !empty($r['ref_rewarded']) || !empty($r['verified_at']),
            ];
        }

        return $out;
    }

    /** A human name for the other side of the reward, for the ledger note. */
    private static function who(array $u): string
    {
        $nick = trim((string) ($u['nickname'] ?? ''));
        if ($nick !== '') {
            return $nick;
        }
        // Mask the mailbox so the ledger note does not leak a full address.
        $email = (string) ($u['email'] ?? '');
        $at    = strpos($email, '@');

        return $at > 1 ? substr($email, 0, 2) . '…' . substr($email, $at) : 'user';
    }
}
