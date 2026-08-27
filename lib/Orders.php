<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Order states and the sweep that closes stale ones.
 *
 * An order moves pending -> accepted -> paid, or falls out to cancelled at
 * any point before payment. "Accepted" means somebody has taken it in hand:
 * the customer can no longer withdraw it, and a clock starts.
 */
final class Orders
{
    public const PENDING   = 'pending';
    public const ACCEPTED  = 'accepted';
    public const PAID      = 'paid';
    public const CANCELLED = 'cancelled';
    // A refund in flight: the grant was taken back, the money still has to
    // be returned by hand. REFUNDED closes it once that is done.
    public const REFUND    = 'refund';
    public const REFUNDED  = 'refunded';

    /**
     * Tab key => [label, statuses it covers].
     *
     * "Vše" leads and is the default: the panel opens showing everything, and
     * the narrower groups are there to filter down from that rather than being
     * the first thing you have to click past.
     */
    public const GROUPS = [
        'all'       => ['Vše',           []],
        'open'      => ['Otevřené',      [self::PENDING]],
        'accepted'  => ['Zpracovává se', [self::ACCEPTED]],
        'paid'      => ['Zpracované',    [self::PAID]],
        'refund'    => ['Refundy',       [self::REFUND, self::REFUNDED]],
        'cancelled' => ['Zrušené',       [self::CANCELLED]],
    ];

    /**
     * Orders still on somebody's plate - the number on the badge.
     *
     * Waiting to be accepted or waiting to be paid. Paid, cancelled and
     * refunded ones are finished business and would only make the badge a
     * permanent decoration.
     */
    public static function openCount(): int
    {
        $st = Db::pdo()->prepare(
            'SELECT COUNT(*) FROM orders WHERE status IN (?, ?)'
        );
        $st->execute([self::PENDING, self::ACCEPTED]);
        return (int) $st->fetchColumn();
    }
    /** Statuses a group covers; empty means everything. */
    public static function statusesFor(string $group): array
    {
        return self::GROUPS[$group][1] ?? [];
    }

    /**
     * Cancels accepted orders that were never paid in time.
     *
     * Run lazily when somebody opens a page rather than from cron: this
     * install has no scheduler, and an order that expires the moment anyone
     * looks is close enough for a deadline measured in days.
     *
     * @return int how many were closed
     */
    public static function expireStale(): int
    {
        $days = Settings::int('accept_expiry_days');
        if ($days <= 0) {
            return 0;   // expiry switched off
        }

        $cutoff = time() - $days * 86400;

        try {
            $st = Db::pdo()->prepare(
                'SELECT * FROM orders
                 WHERE status = ? AND accepted_at IS NOT NULL AND accepted_at < ?'
            );
            $st->execute([self::ACCEPTED, $cutoff]);
            $stale = $st->fetchAll();
        } catch (Throwable $e) {
            return 0;
        }

        if (!$stale) {
            return 0;
        }

        $note = 'Automaticky zrušeno - nezaplaceno do ' . $days . ' dní od přijetí.';
        $up = Db::pdo()->prepare(
            'UPDATE orders SET status = ?, cancelled_at = ?, admin_note = ?
             WHERE id = ? AND status = ?'
        );

        $closed = 0;

        foreach ($stale as $order) {
            // The status is re-checked in the UPDATE so a payment confirmed
            // in the same moment wins rather than being cancelled underneath.
            $up->execute([self::CANCELLED, time(), $note, (int) $order['id'], self::ACCEPTED]);

            if ($up->rowCount() === 0) {
                continue;
            }

            $closed++;

            $buyer = Auth::byId((int) $order['user_id']);
            if ($buyer && Mailer::enabled()) {
                $order['status']       = self::CANCELLED;
                $order['cancelled_at'] = time();

                Notify::$lang = Auth::userLang($buyer);
                Mailer::notify(
                    (string) $buyer['email'],
                    Notify::orderCancelled($order, $note, false, orderStatusUrl($order)),
                    'order_expired'
                );
            }
        }

        return $closed;
    }

    /**
     * Cancel a customer's unpaid pending order.
     *
     * This is deliberately the single customer-facing cancellation path:
     * account.php and order.php both use it, so the same rule applies from
     * the order list and from the order detail page.
     *
     * @return array{0:bool,1:string,2:array|null}
     */
    public static function cancelPending(array $order, array $user): array
    {
        if ((int) ($order['user_id'] ?? 0) !== (int) ($user['id'] ?? 0)
            || (string) ($order['status'] ?? '') !== self::PENDING) {
            return [false, Lang::current() === 'cs'
                ? 'Tuto objednávku už nelze zrušit.'
                : 'This order can no longer be cancelled.', null];
        }

        $now = time();
        $note = Lang::current() === 'cs'
            ? 'Zrušeno zákazníkem.'
            : 'Cancelled by the customer.';

        $st = Db::pdo()->prepare(
            'UPDATE orders
             SET status = ?, cancelled_at = ?, admin_note = ?
             WHERE id = ? AND user_id = ? AND status = ?'
        );
        $st->execute([
            self::CANCELLED, $now, $note,
            (int) $order['id'], (int) $user['id'], self::PENDING
        ]);

        if ($st->rowCount() !== 1) {
            return [false, Lang::current() === 'cs'
                ? 'Objednávku se nepodařilo zrušit. Možná už byla mezitím zpracována.'
                : 'The order could not be cancelled. It may have been processed already.', null];
        }

        $order['status'] = self::CANCELLED;
        $order['cancelled_at'] = $now;
        $order['admin_note'] = $note;

        if (Mailer::enabled()) {
            Mailer::notify(
                (string) $user['email'],
                Notify::orderCancelled($order, '', true, orderStatusUrl($order)),
                'order_cancelled'
            );
        }

        return [true, Lang::current() === 'cs'
            ? 'Objednávka ' . $order['reference'] . ' byla zrušena.'
            : 'Order ' . $order['reference'] . ' was cancelled.', $order];
    }

    /**
     * Extends a subscription by a number of days.
     *
     * Time already paid for is never lost: a renewal bought early stacks onto
     * whatever is left rather than resetting the clock to today. Returns the
     * new expiry, or null when there was nothing to grant.
     */
    /**
     * Time runs to the exact hour and minute: a 1-day package is 24 hours
     * from its start, not "until midnight". With a plan already running the
     * new days stack onto its end; otherwise the buyer's choice applies -
     * start right away, or exactly 24 hours from now ("od zítřka").
     */
    public static function grantSubscription(int $userId, int $days, string $start = '', ?int &$padding = null): ?int
    {
        $padding = 0;
        if ($days <= 0) {
            return null;
        }

        $st = Db::pdo()->prepare('SELECT subscription_until FROM users WHERE id = ?');
        $st->execute([$userId]);
        $cur = $st->fetchColumn();
        // Let go of the statement before writing: while it is alive this
        // connection sits on an old snapshot of the database, and the write
        // below would be refused the moment anybody else commits.
        $st = null;

        if ($cur !== false && (int) $cur > time()) {
            $base = (int) $cur;
        } elseif ($start === 'tomorrow') {
            // "Od zítřka" runs from the next midnight, not now+24h - the
            // customer asked for a calendar day, so the clock starts at 00:00.
            $base    = (int) strtotime('tomorrow 00:00');
            $padding = max(0, $base - time());
        } else {
            $base = time();
        }
        $until = $base + $days * 86400;

        Db::write(static function (PDO $pdo) use ($until, $userId): void {
            $pdo->prepare('UPDATE users SET subscription_until = ? WHERE id = ?')
                ->execute([$until, $userId]);

            // A plan can also expire again later; make sure the "it ended"
            // mail fires for this new run too.
            $pdo->prepare('UPDATE users SET sub_expiry_notified = NULL WHERE id = ?')
                ->execute([$userId]);
        });

        return $until;
    }

    /**
     * A subscription length in days, worded the tidy way: whole years, months
     * and weeks collapse to those units, anything else stays in days.
     */
    /**
     * "Confirming an order can take up to three days", or nothing at all
     * when the operator set 0 days.
     *
     * $lang is passed explicitly by the mailer, which builds a message in
     * the recipient's language rather than the current reader's.
     */
    public static function processingNote(?string $lang = null): string
    {
        $days = max(0, Settings::int('order_process_days'));
        if ($days === 0) {
            return '';
        }

        $lang  = $lang === 'cs' || $lang === 'en' ? $lang : Lang::current();
        $spell = self::durationLabel($days, $lang);

        return $lang === 'cs'
            ? 'Platby páruji ručně, potvrzení objednávky proto může trvat až ' . $spell . '.'
            : 'Payments are checked by hand, so confirming an order can take up to ' . $spell . '.';
    }

    public static function durationLabel(int $days, string $lang = 'en'): string
    {
        if ($days <= 0) {
            return '';
        }

        $cs = $lang === 'cs';

        $pick = static function (int $n, array $csForms, array $enForms) use ($cs): string {
            if ($cs) {
                // 1 / 2-4 / 5+
                $form = $n === 1 ? $csForms[0] : ($n < 5 ? $csForms[1] : $csForms[2]);
            } else {
                $form = $n === 1 ? $enForms[0] : $enForms[1];
            }
            return $n . ' ' . $form;
        };

        if ($days % 365 === 0) {
            return $pick((int) ($days / 365), ['rok', 'roky', 'let'], ['year', 'years']);
        }
        if ($days % 30 === 0) {
            return $pick((int) ($days / 30), ['měsíc', 'měsíce', 'měsíců'], ['month', 'months']);
        }
        if ($days % 7 === 0) {
            return $pick((int) ($days / 7), ['týden', 'týdny', 'týdnů'], ['week', 'weeks']);
        }
        return $pick($days, ['den', 'dny', 'dní'], ['day', 'days']);
    }

    /**
     * The withdrawal window, in days.
     *
     * Fourteen days is the EU distance-selling right. Digital goods are the
     * exception everybody forgets: the right ends the moment the customer
     * starts using what they bought, which is why refundBlocker() checks the
     * usage as well as the clock.
     */
    public const REFUND_DAYS = 14;

    /**
     * Why this order cannot be refunded, or '' when it can be.
     *
     * One implementation for both doors - the customer asking from their
     * account and the operator starting a refund from the panel - so the
     * answer is the same whoever asks. The rules:
     *
     *  - the order is paid, and was paid within the withdrawal window;
     *  - a time package must be untouched: a day on which more exports were
     *    made than the free allowance covers is a day the plan was used;
     *  - the credits it granted must all still be on the balance. Spending
     *    even one of them is using the service.
     */
    public static function refundBlocker(array $order, ?array $user): string
    {
        if ((string) ($order['status'] ?? '') !== self::PAID) {
            return 'Vrátit peníze jde jen u zaplacené objednávky.';
        }

        $paidAt = (int) ($order['paid_at'] ?? $order['created_at']);
        if (time() - $paidAt > self::REFUND_DAYS * 86400) {
            return 'Od zaplacení uplynulo víc než ' . self::REFUND_DAYS
                 . ' dní (zaplaceno ' . when($paidAt) . ').';
        }

        // Nobody left to take the grant back from - the account is gone.
        if (!$user) {
            return '';
        }

        $uid  = (int) $order['user_id'];
        $days = (int) ($order['sub_days'] ?? 0);

        // A package still sitting on the shelf was never used by definition,
        // and nothing was granted for it - so there is nothing to check and
        // nothing to take back.
        if ($days > 0 && empty($order['activated_at'])) {
            return '';
        }

        if ($days > 0) {
            $since    = (int) $order['activated_at'];
            $cooldown = Settings::int('cooldown_user');
            $freePerW = Settings::int('free_per_window_user');
            // Exports up to the free allowance would have been free without
            // the package too, so they do not count as using it.
            $freePerDay = $cooldown > 0 ? $freePerW : PHP_INT_MAX;

            $st = Db::pdo()->prepare(
                "SELECT date(created_at, 'unixepoch') AS d, COUNT(*) AS n
                 FROM exports WHERE user_id = ? AND created_at >= ?
                 GROUP BY d"
            );
            $st->execute([$uid, $since]);

            $usedDays = 0;
            foreach ($st->fetchAll() as $r) {
                if ((int) $r['n'] > $freePerDay || $freePerDay === 0) {
                    $usedDays++;
                }
            }
            if ($usedDays > 0) {
                return 'Balíček už byl využitý (' . $usedDays . ' '
                     . ($usedDays === 1 ? 'den' : 'dní') . ' s exporty nad rámec volné dávky).';
            }
        }

        $granted = (int) $order['credits'];
        if ($granted > 0 && $days === 0 && Credits::balance($uid) < $granted) {
            return 'Část kreditů z objednávky už byla utracena.';
        }

        return '';
    }

    /**
     * What a running plan is still worth in money, if it were cancelled now.
     *
     * The days left are matched against the paid time orders that bought
     * them, newest first - the last package bought is the one still running.
     * Each order gives back the share of its price that was not used up.
     *
     * This is the figure somebody has to send back after clicking "cancel
     * the whole plan", and working it out by hand from a list of orders and
     * a date is exactly the sort of arithmetic that gets it wrong.
     *
     * @return array{cents:int, currency:?string, days:int, parts:array<int, array{ref:string, days:int, cents:int}>}
     */
    public static function planRefundDue(array $user): array
    {
        $until = (int) ($user['subscription_until'] ?? 0);
        $left  = $until > time() ? (int) ceil(($until - time()) / 86400) : 0;

        $out = ['cents' => 0, 'currency' => null, 'days' => $left, 'parts' => []];
        if ($left <= 0) {
            return $out;
        }

        $st = Db::pdo()->prepare(
            'SELECT reference, sub_days, price_cents, currency FROM orders
             WHERE user_id = ? AND status = ? AND sub_days > 0
             ORDER BY COALESCE(activated_at, paid_at, created_at) DESC, id DESC'
        );
        $st->execute([(int) $user['id'], self::PAID]);

        $remaining = $left;
        foreach ($st->fetchAll() as $o) {
            if ($remaining <= 0) {
                break;
            }
            $days = (int) $o['sub_days'];
            if ($days <= 0) {
                continue;
            }
            // Never more than the package itself covered: days from a code or
            // a hand-set date have no order behind them and no money either.
            $take  = min($remaining, $days);
            $cents = (int) round((int) $o['price_cents'] * $take / $days);

            $out['parts'][] = ['ref' => (string) $o['reference'], 'days' => $take, 'cents' => $cents];
            $out['cents']  += $cents;
            $out['currency'] = $out['currency'] ?? ($o['currency'] ?? null);
            $remaining     -= $take;
        }

        return $out;
    }

    /**
     * Takes the grant back and freezes it: the credits leave the balance and
     * the unused days come off the plan, both immediately.
     *
     * Called the moment a refund starts, before anybody sends money back, so
     * what is being refunded cannot be spent while the refund is in flight.
     * refund_undo in the panel puts all of it back if the refund is dropped.
     *
     * @return string[] short notes of what was taken, for the order's log
     */
    public static function reclaimGrant(array $order, array $user): array
    {
        $uid  = (int) $order['user_id'];
        $ref  = (string) $order['reference'];
        $days = (int) ($order['sub_days'] ?? 0);
        $bits = [];

        // Never started, so nothing was ever handed over.
        if ($days > 0 && empty($order['activated_at'])) {
            return ['nic nebylo aktivované'];
        }

        $granted = (int) $order['credits'];
        if ($granted > 0) {
            Credits::apply($uid, -$granted, 'refund', $ref);
            $bits[] = 'kredity -' . Cred::fmtCs($granted);
        }

        // Unused days come off the running plan, including the gap a deferred
        // start added - otherwise the plan stays "active" until midnight even
        // though it was fully refunded.
        if ($days > 0) {
            $until = (int) ($user['subscription_until'] ?? 0);
            if ($until > time()) {
                $pad = (int) ($order['sub_padding'] ?? 0);
                if ($pad === 0 && (string) ($order['sub_start'] ?? '') === 'tomorrow') {
                    $pad = 86400;   // legacy order from before padding was recorded
                }
                $newUntil = $until - $days * 86400 - $pad;
                Db::pdo()->prepare('UPDATE users SET subscription_until = ? WHERE id = ?')
                    ->execute([$newUntil > time() ? $newUntil : null, $uid]);

                Credits::apply($uid, 0, 'sub_cancel',
                    '-' . $days . ' dní (refund ' . $ref . ')'
                    . ' [g:o:' . preg_replace('/[^A-Za-z0-9._-]/', '', $ref) . ']');
                $bits[] = 'čas -' . $days . ' dní';
            }
        }

        return $bits;
    }

    /**
     * Hands a paid order its rewards - credits, subscription time, or both.
     *
     * The single place both the admin "mark paid" and the automatic free-order
     * path go through, so a package that grants a subscription is never settled
     * one way in one place and another way in the other.
     *
     * A time package is the exception: paying for it only puts it on the
     * shelf. Its clock starts when the customer says so, because payments
     * are confirmed by hand here and a week that began while somebody was
     * waiting for their order to be checked is a week they did not get.
     */
    public static function fulfil(array $order): void
    {
        if ((int) ($order['sub_days'] ?? 0) > 0) {
            return;   // waits on the account until activate() is called
        }

        $userId = (int) $order['user_id'];

        if ((int) $order['credits'] > 0) {
            Credits::apply($userId, (int) $order['credits'], 'order', (string) $order['reference']);
        }
    }

    /**
     * Starts a paid time package: the days begin now and any credits that
     * came with it are paid out.
     *
     * @return array{0:bool,1:string} started, and why not when it did not
     */
    public static function activate(array $order): array
    {
        if ((string) $order['status'] !== self::PAID) {
            return [false, 'Aktivovat jde jen zaplacený balíček.'];
        }
        if ((int) ($order['sub_days'] ?? 0) <= 0) {
            return [false, 'Tahle objednávka není časový balíček.'];
        }
        if (!empty($order['activated_at'])) {
            return [false, 'Balíček už běží.'];
        }

        $userId = (int) $order['user_id'];
        $days   = (int) $order['sub_days'];
        $ref    = (string) $order['reference'];

        // Stamped first: if anything below fails, the worst case is a
        // package that was paid out once, not one that pays out twice.
        $done = Db::pdo()->prepare(
            'UPDATE orders SET activated_at = ? WHERE id = ? AND activated_at IS NULL'
        );
        $done->execute([time(), (int) $order['id']]);
        if ($done->rowCount() === 0) {
            return [false, 'Balíček už běží.'];
        }

        self::grantSubscription($userId, $days);

        if ((int) $order['credits'] > 0) {
            Credits::apply($userId, (int) $order['credits'], 'order', $ref);
        }

        return [true, ''];
    }

    /**
     * Paid time packages this account has not started yet, oldest first.
     *
     * @return array<int, array>
     */
    public static function waitingFor(int $userId): array
    {
        // Only genuinely purchased time packages belong here.  A package is
        // waiting for activation when the payment is completed, it grants
        // time, and its activation timestamp is still empty.  Credit-only
        // orders are intentionally excluded because their credits are
        // granted immediately and there is nothing to activate.
        $st = Db::pdo()->prepare(
            'SELECT * FROM orders
             WHERE user_id = ?
               AND status = ?
               AND paid_at IS NOT NULL
               AND sub_days > 0
               AND activated_at IS NULL
             ORDER BY COALESCE(paid_at, created_at) DESC, id DESC'
        );
        $st->execute([$userId, self::PAID]);

        return $st->fetchAll();
    }

    /** How long an accepted order has left, or null when nothing expires. */
    public static function expiresIn(array $order): ?int
    {
        $days = Settings::int('accept_expiry_days');

        if ($days <= 0
            || ($order['status'] ?? '') !== self::ACCEPTED
            || empty($order['accepted_at'])) {
            return null;
        }

        return max(0, ((int) $order['accepted_at'] + $days * 86400) - time());
    }

    /** Counts per group, for the tab labels. */
    public static function counts(): array
    {
        $rows = Db::pdo()->query(
            'SELECT status, COUNT(*) AS n FROM orders GROUP BY status'
        )->fetchAll();

        $byStatus = [];
        $total    = 0;
        foreach ($rows as $r) {
            $byStatus[(string) $r['status']] = (int) $r['n'];
            $total += (int) $r['n'];
        }

        $out = [];
        foreach (self::GROUPS as $key => [$label, $statuses]) {
            $out[$key] = $statuses === []
                ? $total
                : array_sum(array_map(static fn($s) => $byStatus[$s] ?? 0, $statuses));
        }

        return $out;
    }
}
