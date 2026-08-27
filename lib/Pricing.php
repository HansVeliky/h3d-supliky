<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Package pricing: the site-wide discount and the 30-day price history.
 *
 * The history exists because the EU price-indication rules require a
 * discounted price to be shown next to the lowest price the item was sold
 * for in the 30 days before the discount. That number cannot be worked out
 * afterwards, so every price the shop has actually offered gets recorded as
 * it changes.
 */
final class Pricing
{
    private const WINDOW = 30 * 86400;

    /**
     * The discount in force right now, or null.
     *
     * Scheduled promotions are the source of truth once the manager is in use;
     * only before then - a fresh install, or a site still on the old single
     * discount - does it fall back to the legacy settings, so nothing changes
     * for anyone who has not touched the new screen yet.
     *
     * @return array{label:string,percent:int,from:string,until:string}|null
     */
    public static function activePromo(): ?array
    {
        if (Promotions::configured()) {
            return Promotions::active();
        }
        return self::legacyPromo();
    }

    /** The legacy single discount, read from settings, if it is live now. */
    private static function legacyPromo(): ?array
    {
        $pct = max(0, min(950, Settings::int('discount_percent')));
        if ($pct <= 0) {
            return null;
        }

        $from  = trim(Settings::get('discount_from'));
        $until = trim(Settings::get('discount_until'));
        $now   = time();

        if ($from !== '' && ($ts = strtotime($from)) !== false && $now < $ts) {
            return null;
        }
        // An end date means the end of that day, not midnight at its start —
        // "until 14 February" should include the 14th.
        if ($until !== '' && ($ts = strtotime($until . ' 23:59:59')) !== false && $now > $ts) {
            return null;
        }

        return ['label' => trim(Settings::get('discount_label')), 'percent' => $pct,
                'from' => $from, 'until' => $until];
    }

    /** Is a discount configured and currently within its dates? */
    public static function discountActive(): bool
    {
        return self::activePromo() !== null;
    }

    /** Discount in tenths of a percent, so 12.5% is 125. */
    public static function percentTenths(): int
    {
        $p = self::activePromo();
        return $p ? $p['percent'] : 0;
    }

    /** Display form, e.g. "12.5". */
    public static function percent(): string
    {
        return Cred::fmtPercent(self::percentTenths());
    }

    public static function label(): string
    {
        $p = self::activePromo();
        $l = $p ? trim($p['label']) : '';
        return $l !== '' ? $l : 'Sale';
    }

    /** End date of the running discount as 'Y-m-d', or '' when open-ended. */
    public static function until(): string
    {
        $p = self::activePromo();
        return $p ? trim($p['until']) : '';
    }

    /**
     * Everything needed to show and charge for a package.
     *
     * @return array{base:int, final:int, percent:string, on:bool, label:string, lowest30:?int}
     */
    public static function of(array $package): array
    {
        $base = (int) $package['price_cents'];
        $on   = self::discountActive() && $base > 0;
        $pct  = $on ? self::percentTenths() : 0;   // tenths of a percent

        // Kept to the cent rather than rounded to whole currency units.
        // Rounding to units looked tidier but silently swallowed small
        // discounts: 1% off 49 rounds straight back to 49, so the campaign
        // appeared to do nothing on the cheapest package. An exact figure
        // always applies, which matters more than a round number.
        // 1000 = 100.0% in tenths.
        $final = $on ? (int) round($base * (1000 - $pct) / 1000) : $base;
        if ($final < 0) {
            $final = 0;
        }

        return [
            'base'     => $base,
            'final'    => $final,
            'percent'  => Cred::fmtPercent($pct),
            'on'       => $on && $final < $base,
            'label'    => self::label(),
            // The lowest price actually on offer in the window, the current
            // one included. Excluding it meant a fresh price cut kept
            // advertising the old, higher figure as "the lowest" until the
            // history caught up, which is plainly wrong the moment you set
            // a price below anything charged before.
            'lowest30' => min($final, self::lowest30((int) $package['id']) ?? $final),
        ];
    }

    /**
     * Records the price actually on offer, skipping a write when it has not
     * moved. Called whenever a package or the discount is saved, and lazily
     * when the shop is rendered, so the history reflects what visitors saw
     * rather than only what an admin edited.
     */
    public static function record(int $packageId, int $priceCents): void
    {
        try {
            $st = Db::pdo()->prepare(
                'SELECT price_cents FROM price_history WHERE package_id = ? ORDER BY id DESC LIMIT 1'
            );
            $st->execute([$packageId]);
            $last = $st->fetchColumn();

            if ($last !== false && (int) $last === $priceCents) {
                return;
            }

            Db::pdo()->prepare(
                'INSERT INTO price_history (package_id, price_cents, created_at) VALUES (?, ?, ?)'
            )->execute([$packageId, $priceCents, time()]);
        } catch (Throwable $e) {
            // Price history is a record, not a gate: never block a sale.
            error_log('[h3d] price history failed: ' . $e->getMessage());
        }
    }

    /**
     * Lowest price offered in the 30 days before now, or null when the
     * current price is the lowest there has been.
     *
     * The current discounted price is excluded from the comparison — quoting
     * the sale price as its own reference would make every discount look like
     * a saving of zero and defeats the point of showing it.
     */
    public static function lowest30(int $packageId, ?int $excluding = null): ?int
    {
        try {
            $st = Db::pdo()->prepare(
                'SELECT MIN(price_cents) FROM price_history
                 WHERE package_id = ? AND created_at >= ?'
                . ($excluding !== null ? ' AND price_cents != ?' : '')
            );
            $params = [$packageId, time() - self::WINDOW];
            if ($excluding !== null) {
                $params[] = $excluding;
            }
            $st->execute($params);

            $v = $st->fetchColumn();
            return $v === null || $v === false ? null : (int) $v;
        } catch (Throwable $e) {
            return null;
        }
    }

    /** Re-records every active package, e.g. after the discount changes. */
    public static function recordAll(): void
    {
        try {
            $rows = Db::pdo()->query('SELECT * FROM packages')->fetchAll();
            foreach ($rows as $p) {
                self::record((int) $p['id'], self::of($p)['final']);
            }
        } catch (Throwable $e) {
            error_log('[h3d] price history sweep failed: ' . $e->getMessage());
        }
    }

    /**
     * How many times this package may still be bought.
     *
     * @return array{0:bool,1:string} allowed, reason
     */
    public static function mayOrder(array $package, int $userId): array
    {
        $pdo = Db::pdo();

        $perAccount = (int) ($package['uses_per_account'] ?? 0);
        if ($perAccount > 0) {
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM orders
                 WHERE package_id = ? AND user_id = ? AND status != "cancelled"'
            );
            $st->execute([(int) $package['id'], $userId]);
            if ((int) $st->fetchColumn() >= $perAccount) {
                return [false, $perAccount === 1
                    ? 'You have already used this offer.'
                    : "You have already used this offer $perAccount times."];
            }
        }

        $maxUses = (int) ($package['max_uses'] ?? 0);
        if ($maxUses > 0) {
            $st = $pdo->prepare(
                'SELECT COUNT(*) FROM orders WHERE package_id = ? AND status != "cancelled"'
            );
            $st->execute([(int) $package['id']]);
            if ((int) $st->fetchColumn() >= $maxUses) {
                return [false, 'This offer is no longer available.'];
            }
        }

        return [true, ''];
    }
}
