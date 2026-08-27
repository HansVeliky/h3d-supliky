<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Decides whether an export may run.
 *
 * The model is deliberately simple to explain to a user: exports are free
 * but spaced out by a cooldown, and a credit buys the right to skip the
 * wait. That way credits have an obvious value without ever making the tool
 * unusable for someone who does not have any.
 */
final class Quota
{
    public const FREE    = 'free';
    public const CREDIT  = 'credit';
    public const BLOCKED = 'blocked';
    public const LOGIN   = 'login_required';

    /**
     * @return array{mode:string, cost:int, retry_after:int, cooldown:int, balance:int, limit:int, free_left:int, message:string}
     */
    public static function check(?array $user): array
    {
        // A signed-in account without a verified e-mail is deliberately
        // treated as a visitor for export rules: it gets the guest free
        // allowance/cooldown and cannot spend account credits or use a
        // subscription to bypass visitor limits.
        $user = self::effectiveUser($user);

        $out = [
            'mode'        => self::FREE,
            'cost'        => 0,
            'retry_after' => 0,
            'cooldown'    => 0,
            'balance'     => $user ? (int) $user['credits'] : 0,
            'limit'       => 0,
            'free_left'   => 0,
            'window_reset'=> 0,
            'unlimited'   => false,
            'message'     => '',
        ];

        if (!$user && (Settings::bool('require_login') || !Settings::bool('guest_export_enabled'))) {
            $out['mode'] = self::LOGIN;
            $out['message'] = 'Sign in to export.';
            return $out;
        }

        $window = $user
            ? max(0, Settings::int('cooldown_user'))
            : max(0, Settings::int('cooldown_guest'));

        $limit = $user
            ? max(0, Settings::int('free_per_window_user'))
            : max(0, Settings::int('free_per_window_guest'));

        // Not metered at all: no window, no charge, no confirmation. Either a
        // privileged role (staff testing the shop) or a running subscription
        // that was bought precisely to lift the limit for a while.
        if (Auth::isUnlimited($user)) {
            $out['mode']      = self::FREE;
            $out['cooldown']  = 0;
            $out['limit']     = 0;
            $out['free_left'] = 0;
            $out['unlimited'] = true;
            return $out;
        }

        $freeEnabled = $user
            ? Settings::bool('free_enabled_user')
            : Settings::bool('free_enabled_guest');

        $out['cooldown']  = $window;
        $out['limit']     = $limit;
        $out['free_paid'] = !$freeEnabled || $limit === 0;

        // No free exports at all: skip the window entirely and go straight to
        // charging. retry_after stays 0 because there is nothing to wait for,
        // and the interface must not promise a free export that never comes.
        if (!$freeEnabled || $limit === 0) {
            $out['limit']     = 0;
            $out['free_left'] = 0;
            return self::charge($out, $user);
        }

        if ($window === 0) {
            $out['free_left'] = $limit;
            return $out;   // unlimited
        }

        // Timestamps of the free exports still inside the window, oldest
        // first. Counting them (rather than looking only at the last one)
        // is what allows an allowance such as three per thirty minutes.
        $recent = self::freeExportsInWindow($user, $window);
        $used   = count($recent);

        $out['free_left'] = max(0, $limit - $used);

        // When the oldest export in the window ages out, one free export
        // comes back. Worth reporting even while some are still available,
        // so the page can say when the count goes back up instead of only
        // saying so once there is nothing left.
        $out['window_reset'] = $recent !== []
            ? max(1, ($recent[0] + $window) - time())
            : 0;

        if ($used < $limit) {
            return $out;   // free allowance still available
        }

        // The allowance frees up when the oldest export in the window ages
        // out, not a full window from now.
        $retry = $limit > 0
            ? max(1, ($recent[0] + $window) - time())
            : $window;

        $out['retry_after'] = $retry;

        return self::charge($out, $user);
    }

    /**
     * Falls back to credits, or blocks.
     *
     * Shared by the two ways of getting here - the allowance being used up,
     * and free exports being switched off - so the wording and the balance
     * check cannot drift apart between them.
     */
    private static function charge(array $out, ?array $user): array
    {
        $cost   = max(0, Settings::int('credit_cost'));
        $noFree = !empty($out['free_paid']);

        if ($user && Settings::bool('credits_enabled') && $cost > 0) {
            if ((int) $user['credits'] >= $cost) {
                $out['mode'] = self::CREDIT;
                $out['cost'] = $cost;
                return $out;
            }
            $out['mode'] = self::BLOCKED;
            $out['message'] = $noFree
                ? 'No credits left. Buy credits or redeem a code.'
                : 'No credits left. Wait for the free export or redeem a code.';
            return $out;
        }

        $out['mode'] = self::BLOCKED;

        if ($noFree) {
            $out['message'] = $user
                ? 'Exporting requires credits.'
                : 'Sign in and use credits to export.';
        } else {
            $out['message'] = $user
                ? 'Please wait before exporting again.'
                : 'Please wait before exporting again, or sign in to use credits.';
        }

        return $out;
    }

    /**
     * Timestamps of free exports inside the current window, oldest first.
     * Exports paid for with credits do not count against the allowance.
     *
     * @return int[]
     */
    private static function freeExportsInWindow(?array $user, int $window): array
    {
        // The lower bound is the window, unless the admin reset quotas more
        // recently: exports made before that reset are kept for the records
        // but no longer count against anyone's allowance.
        $floor = max(time() - $window, Settings::int('quota_reset_at'));

        if ($user) {
            $st = Db::pdo()->prepare(
                'SELECT created_at FROM exports
                 WHERE user_id = ? AND credits_spent = 0 AND created_at > ?
                 ORDER BY created_at ASC'
            );
            $st->execute([(int) $user['id'], $floor]);
        } else {
            $st = Db::pdo()->prepare(
                'SELECT created_at FROM exports
                 WHERE ip_hash = ? AND user_id IS NULL AND credits_spent = 0 AND created_at > ?
                 ORDER BY created_at ASC'
            );
            $st->execute([Auth::ipHash(), $floor]);
        }

        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    }

    /**
     * Consume the allowance and log the export.
     *
     * The credit is spent before the file is generated. Charging afterwards
     * would leave a window where a slow response or a dropped connection
     * hands out a free export.
     */
    public static function consume(?array $user, array $decision, string $format, int $boxes): void
    {
        $spent = 0;

        if ($decision['mode'] === self::CREDIT) {
            // Credits::apply re-checks the balance inside its own
            // transaction, so a race here fails loudly rather than
            // overdrawing.
            Credits::apply((int) $user['id'], -$decision['cost'], 'export', $format);
            $spent = $decision['cost'];
        }

        // Through Db::write: this is the busiest write on the site, and a
        // rejected one would mean a credit already taken for an export that
        // is missing from the log - and from the allowance it counts against.
        Db::write(static function (PDO $pdo) use ($user, $format, $boxes, $spent): void {
            $pdo->prepare(
                'INSERT INTO exports (user_id, ip_hash, format, boxes, credits_spent, created_at)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                $user ? (int) $user['id'] : null,
                Auth::ipHash(),
                $format,
                $boxes,
                $spent,
                time(),
            ]);
        });
    }

    /**
     * Whether to ask before a credit is spent.
     *
     * The account holder's choice wins; NULL means they have not made one,
     * so the shop-wide default applies. Privileged accounts never see it -
     * nothing is being spent.
     */
    public static function confirmSpend(?array $user): bool
    {
        $user = self::effectiveUser($user);

        if (Auth::isPrivileged($user)) {
            return false;
        }

        $own = $user['confirm_spend'] ?? null;
        if ($own !== null && $own !== '') {
            return (int) $own === 1;
        }

        return Settings::bool('confirm_credit_spend');
    }

    /**
     * Only verified customer accounts (and privileged staff) receive the
     * normal account quota. Until verification, behave exactly like a guest.
     */
    private static function effectiveUser(?array $user): ?array
    {
        if (!$user) {
            return null;
        }

        if (Auth::isPrivileged($user) || !empty($user['verified_at'])) {
            return $user;
        }

        return null;
    }

    public static function humanDuration(int $seconds): string
    {
        if ($seconds <= 0) {
            return 'now';
        }
        if ($seconds < 60) {
            return $seconds . ' s';
        }
        if ($seconds < 3600) {
            return (int) ceil($seconds / 60) . ' min';
        }
        $h = intdiv($seconds, 3600);
        $m = (int) round(($seconds % 3600) / 60);
        return $m ? "{$h} h {$m} min" : "{$h} h";
    }
}
