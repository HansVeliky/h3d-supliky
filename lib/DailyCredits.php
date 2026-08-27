<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Daily credit allowance.
 *
 * Applied on the first request a user makes after local midnight rather than
 * by a scheduled job. A NAS install should not depend on someone remembering
 * to set up cron, and a lazy grant has a useful property a cron job does not:
 * accounts nobody uses never get topped up, so the ledger stays quiet.
 *
 * The trade-off is that the credits appear when the user shows up, not at
 * 00:00 exactly. For an allowance that is spent by the person receiving it,
 * that difference is invisible.
 */
final class DailyCredits
{
    /**
     * Grants today's credits if they are due.
     *
     * @return int number of credits granted (0 when nothing was due)
     */
    public static function grant(array $user): int
    {
        $amount = max(0, Cred::parse(Settings::get('daily_credits')));
        if ($amount === 0 || empty($user['id'])) {
            return 0;
        }

        $today = self::today();
        $cap   = max(0, Cred::parse(Settings::get('daily_credits_cap')));

        try {
            return Db::transact(function (PDO $pdo) use ($user, $today, $amount, $cap) {
                $uid = (int) $user['id'];

                // Re-read the account in the transaction. created_at is the
                // hard lower bound: daily credits can never be generated for
                // days before the account existed.
                $st = $pdo->prepare('SELECT credits, created_at, daily_granted_on FROM users WHERE id = ?');
                $st->execute([$uid]);
                $row = $st->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    return 0;
                }

                $tz = self::timezone();
                $todayDate = new DateTimeImmutable($today . ' 00:00:00', $tz);
                $created   = (new DateTimeImmutable('@' . max(0, (int) $row['created_at'])))->setTimezone($tz);
                $firstDay  = new DateTimeImmutable($created->format('Y-m-d') . ' 00:00:00', $tz);

                // Ledger rows are authoritative. This also repairs old
                // installations where daily_granted_on says "today" but one
                // or more earlier days were never credited.
                $st = $pdo->prepare(
                    "SELECT ref FROM ledger
                     WHERE user_id = ? AND reason = 'daily' AND ref >= ? AND ref <= ?"
                );
                $st->execute([$uid, $firstDay->format('Y-m-d'), $today]);
                $granted = [];
                foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $ref) {
                    $granted[(string) $ref] = true;
                }

                $missing = [];
                for ($day = $firstDay; $day <= $todayDate; $day = $day->modify('+1 day')) {
                    $date = $day->format('Y-m-d');
                    if (!isset($granted[$date])) {
                        $missing[] = $date;
                    }
                }

                if (!$missing) {
                    if ((string) ($row['daily_granted_on'] ?? '') !== $today) {
                        $pdo->prepare('UPDATE users SET daily_granted_on = ? WHERE id = ?')
                            ->execute([$today, $uid]);
                    }
                    return 0;
                }

                $balance = (int) $row['credits'];
                $total   = 0;
                $insert  = $pdo->prepare(
                    'INSERT INTO ledger (user_id, delta, balance_after, reason, ref, created_at)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );

                $rateStmt = $pdo->prepare(
                    'SELECT credits FROM daily_credit_rates
                     WHERE effective_at <= ? ORDER BY effective_at DESC LIMIT 1'
                );

                foreach ($missing as $date) {
                    // The rate effective at the beginning of the calendar day
                    // is used. A change made during a day therefore takes
                    // effect from the following day, which mirrors the
                    // "once after midnight" grant semantics.
                    $dayStart = (new DateTimeImmutable($date . ' 00:00:00', $tz))->getTimestamp();
                    $rateStmt->execute([$dayStart]);
                    $historical = $rateStmt->fetchColumn();
                    $dayAmount = $historical === false ? $amount : max(0, (int) $historical);

                    $give = $dayAmount;
                    if ($cap > 0) {
                        $give = max(0, min($dayAmount, $cap - $balance));
                    }

                    // Record even a capped day as processed. Otherwise the
                    // same historical day would be reconsidered at every login.
                    if ($give > 0) {
                        $balance += $give;
                        $total   += $give;
                    }

                    $insert->execute([$uid, $give, $balance, 'daily', $date, time()]);
                }

                $pdo->prepare('UPDATE users SET credits = ?, daily_granted_on = ? WHERE id = ?')
                    ->execute([$balance, $today, $uid]);

                return $total;
            });
        } catch (Throwable $e) {
            // A failed top-up must never block login or the rest of the app.
            error_log('[h3d] daily credit catch-up failed: ' . $e->getMessage());
            return 0;
        }
    }

    /** Configured timezone with a safe fallback. */
    private static function timezone(): DateTimeZone
    {
        try {
            return new DateTimeZone(Settings::get('timezone') ?: 'Europe/Prague');
        } catch (Throwable) {
            return new DateTimeZone('UTC');
        }
    }

    /**
     * Return the daily-credit rate that was effective at the start of a
     * calendar day. Values are stored in tenths, exactly like ledger amounts.
     */
    public static function rateForDate(string $date): int
    {
        try {
            $tz = self::timezone();
            $start = (new DateTimeImmutable($date . ' 00:00:00', $tz))->getTimestamp();
            $st = Db::pdo()->prepare(
                'SELECT credits FROM daily_credit_rates
                 WHERE effective_at <= ?
                 ORDER BY effective_at DESC LIMIT 1'
            );
            $st->execute([$start]);
            $v = $st->fetchColumn();
            if ($v !== false) {
                return max(0, (int) $v);
            }
        } catch (Throwable) {
            // The ledger row itself remains authoritative even if the
            // historical-rate table is temporarily unavailable.
        }

        return max(0, Cred::parse(Settings::get('daily_credits')));
    }

    /**
     * Historical daily allocations for one account.
     *
     * The ledger is deliberately the source of truth: a row exists even when
     * a day yielded 0 because of the configured cap. That prevents a missed
     * day from being silently retried forever.
     *
     * @return list<array{date:string,amount:int,balance:int,created_at:int,rate:int,backfilled:bool}>
     */
    public static function historyForUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            $st = Db::pdo()->prepare(
                "SELECT ref AS date, delta AS amount, balance_after AS balance, created_at
                 FROM ledger
                 WHERE user_id = ? AND reason = 'daily'
                 ORDER BY ref DESC, id DESC"
            );
            $st->execute([$userId]);

            $rows = [];
            $tz = self::timezone();
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $date = (string) ($r['date'] ?? '');
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    continue;
                }

                $createdDate = (new DateTimeImmutable('@' . max(0, (int) $r['created_at'])))
                    ->setTimezone($tz)->format('Y-m-d');

                $rows[] = [
                    'date'       => $date,
                    'amount'     => (int) $r['amount'],
                    'balance'    => (int) $r['balance'],
                    'created_at' => (int) $r['created_at'],
                    'rate'       => self::rateForDate($date),
                    'backfilled' => $createdDate !== $date,
                ];
            }
            return $rows;
        } catch (Throwable) {
            return [];
        }
    }

    /** Today's date in the configured timezone. */
    public static function today(): string
    {
        return (new DateTimeImmutable('now', self::timezone()))->format('Y-m-d');
    }

    /** Seconds until the next local midnight, for display. */
    public static function secondsUntilMidnight(): int
    {
        $tz   = self::timezone();
        $now  = new DateTimeImmutable('now', $tz);
        $next = $now->modify('tomorrow midnight');
        return max(0, $next->getTimestamp() - $now->getTimestamp());
    }
}
