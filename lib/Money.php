<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Currencies.
 *
 * Package prices are held in the base currency. Every other currency has a
 * rate against it, entered by hand: there is no exchange-rate feed here, and
 * inventing one would mean a network call this deployment cannot make and a
 * number nobody checked. A stale rate you set yourself is at least a rate you
 * know about, which is why the panel shows when each one was last touched.
 *
 * Rates are stored in parts per million rather than as floats, for the same
 * reason credits are stored as tenths: money that does not add up exactly is
 * worse than money that is slightly inconvenient to store.
 */
final class Money
{
    private const PPM = 1000000;

    /** Symbols for the currencies people are most likely to add. */
    private const SYMBOLS = [
        'CZK' => 'Kč', 'EUR' => '€', 'USD' => '$', 'GBP' => '£',
        'PLN' => 'zł', 'HUF' => 'Ft', 'CHF' => 'CHF',
    ];

    public static function base(): string
    {
        $code = strtoupper(trim(Settings::get('currency')));
        return self::isCode($code) ? $code : 'CZK';
    }

    public static function isCode(string $code): bool
    {
        return (bool) preg_match('/^[A-Z]{3}$/', $code);
    }

    /** @return array<int, array> */
    public static function all(): array
    {
        return Db::pdo()->query('SELECT * FROM currencies ORDER BY sort, code')->fetchAll();
    }

    /** @return array<int, array> */
    public static function active(): array
    {
        return Db::pdo()->query(
            'SELECT * FROM currencies WHERE active = 1 ORDER BY sort, code'
        )->fetchAll();
    }

    public static function get(string $code): ?array
    {
        $st = Db::pdo()->prepare('SELECT * FROM currencies WHERE code = ?');
        $st->execute([strtoupper(trim($code))]);
        return $st->fetch() ?: null;
    }

    /**
     * @param string $rate how much base currency one unit of this currency
     *                     costs, e.g. "24.50" for 1 EUR = 24.50 CZK
     */
    public static function save(string $code, string $symbol, string $rate, int $decimals, bool $active, int $sort): void
    {
        $code = strtoupper(trim($code));
        if (!self::isCode($code)) {
            throw new InvalidArgumentException('Kód měny musí být tři písmena, například EUR.');
        }

        $ppm = (int) round(((float) str_replace(',', '.', trim($rate))) * self::PPM);

        // The base currency is the yardstick; anything but 1 would make
        // every price ambiguous.
        if ($code === self::base()) {
            $ppm = self::PPM;
        }
        if ($ppm <= 0) {
            throw new InvalidArgumentException('Kurz musí být větší než nula.');
        }

        $symbol = trim($symbol) !== '' ? trim($symbol) : (self::SYMBOLS[$code] ?? $code);

        Db::pdo()->prepare(
            'INSERT INTO currencies (code, symbol, per_unit_ppm, decimals, active, sort)
             VALUES (?, ?, ?, ?, ?, ?)
             ON CONFLICT(code) DO UPDATE SET
               symbol = excluded.symbol, per_unit_ppm = excluded.per_unit_ppm,
               decimals = excluded.decimals, active = excluded.active, sort = excluded.sort'
        )->execute([$code, $symbol, $ppm, max(0, min(2, $decimals)), $active ? 1 : 0, $sort]);
    }

    public static function delete(string $code): void
    {
        if (strtoupper($code) === self::base()) {
            throw new InvalidArgumentException('Základní měnu smazat nelze.');
        }
        Db::pdo()->prepare('DELETE FROM currencies WHERE code = ?')->execute([strtoupper($code)]);
    }

    /** Rate as a display string: how much base one unit costs, e.g. "24.5". */
    public static function rateOf(array $currency): string
    {
        return rtrim(rtrim(number_format((int) $currency['per_unit_ppm'] / self::PPM, 4, '.', ''), '0'), '.');
    }

    /**
     * Converts an amount in the base currency into another one.
     *
     * The result is rounded to the currency's own precision, so a price in
     * forints does not come out with fillér on the end.
     */
    public static function convert(int $baseCents, string $code): int
    {
        $c = self::get($code);
        if (!$c || $code === self::base()) {
            return $baseCents;
        }

        $perUnit = (int) $c['per_unit_ppm'];
        if ($perUnit <= 0) {
            return $baseCents;
        }

        // Dividing, because the stored figure is the price of one unit.
        $value = $baseCents * self::PPM / $perUnit;
        $step  = 10 ** (2 - max(0, min(2, (int) $c['decimals'])));

        return (int) (round($value / $step) * $step);
    }

    /** Formats an amount that is already in the given currency. */
    public static function fmt(int $cents, ?string $code = null): string
    {
        $code = strtoupper(trim((string) ($code ?: self::base())));
        $c    = self::get($code);

        $dec    = $c ? max(0, min(2, (int) $c['decimals'])) : 2;
        $symbol = $c && trim((string) $c['symbol']) !== '' ? $c['symbol'] : $code;

        return number_format($cents / 100, $dec, ',', ' ') . ' ' . $symbol;
    }

    /** The plain three-letter code, for links and payment strings. */
    public static function code(?string $code = null): string
    {
        $code = strtoupper(trim((string) ($code ?: self::base())));
        return self::isCode($code) ? $code : self::base();
    }

    /**
     * The currency the visitor is shopping in. Their choice is remembered in
     * the session; anything unknown or switched off falls back to the base.
     */
    public static function current(): string
    {
        Auth::start();
        $chosen = strtoupper((string) ($_SESSION['currency'] ?? ''));

        if ($chosen !== '' && $chosen !== self::base()) {
            $c = self::get($chosen);
            if ($c && (int) $c['active'] === 1) {
                return $chosen;
            }
        }

        return self::base();
    }

    public static function choose(string $code): void
    {
        Auth::start();
        $code = strtoupper(trim($code));
        $c    = self::get($code);

        if ($code === self::base() || ($c && (int) $c['active'] === 1)) {
            $_SESSION['currency'] = $code;
        }
    }

    /**
     * Re-expresses every rate against a new base currency.
     *
     * Without this, switching the base leaves every other rate quietly
     * wrong: they were all measured against the old one. Called when the
     * base setting changes.
     */
    public static function rebase(string $oldBase, string $newBase): void
    {
        $oldBase = strtoupper($oldBase);
        $newBase = strtoupper($newBase);
        if ($oldBase === $newBase) {
            return;
        }

        $target = self::get($newBase);
        if (!$target) {
            return;
        }

        // How much of the old base one unit of the new base cost.
        $factor = (int) $target['per_unit_ppm'];
        if ($factor <= 0) {
            return;
        }

        $pdo = Db::pdo();
        foreach (self::all() as $c) {
            $ppm = $c['code'] === $newBase
                ? self::PPM
                : (int) round(((int) $c['per_unit_ppm']) * self::PPM / $factor);

            $pdo->prepare('UPDATE currencies SET per_unit_ppm = ? WHERE code = ?')
                ->execute([max(1, $ppm), $c['code']]);
        }
    }

    /** Suggested symbol when adding a new currency. */
    public static function suggestSymbol(string $code): string
    {
        return self::SYMBOLS[strtoupper($code)] ?? strtoupper($code);
    }
}
