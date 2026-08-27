<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Credit amounts, stored as tenths.
 *
 * Credits used to be whole numbers everywhere. Allowing half a credit could
 * have been done with a float column, but money-like values in floats drift:
 * add 0.1 ten times and you do not get 1. The ledger has to add up exactly,
 * and the overdraw check compares balances, so everything is kept as an
 * integer count of tenths and only turned into "1.5" at the edges.
 *
 * One decimal place is the limit on purpose. Two would invite pricing a
 * fraction of an export that nobody can reason about, and the scale factor
 * is the one number that must never quietly change once data exists.
 */
final class Cred
{
    public const SCALE = 10;

    /**
     * Reads a user-entered amount into tenths.
     *
     * Accepts "1.5", "1,5", " 2 " and a bare "-3". Anything unparseable
     * becomes 0 rather than throwing, because these come from form fields
     * where a silent zero is easier to spot and correct than an error page.
     */
    public static function parse(string|int|float|null $input): int
    {
        if ($input === null) {
            return 0;
        }

        $s = trim(str_replace([',', ' ', "\u{00A0}"], ['.', '', ''], (string) $input));
        if ($s === '' || !is_numeric($s)) {
            return 0;
        }

        // round() rather than a cast: (int)(0.7 * 10) is 6 on some builds
        // because 0.7 has no exact binary representation.
        return (int) round(((float) $s) * self::SCALE);
    }

    /** Formats tenths for display: 15 -> "1.5", 20 -> "2". */
    public static function fmt(int $tenths): string
    {
        if ($tenths % self::SCALE === 0) {
            return (string) intdiv($tenths, self::SCALE);
        }

        return rtrim(rtrim(number_format($tenths / self::SCALE, 1, '.', ''), '0'), '.');
    }

    /** Same, but with a comma, for Czech admin screens. */
    public static function fmtCs(int $tenths): string
    {
        return str_replace('.', ',', self::fmt($tenths));
    }

    /**
     * The amount with the Czech noun in the right form: "1 kredit",
     * "3 kredity", "30 kreditů", "1,5 kreditu".
     *
     * Worth the few lines because it goes into e-mails people read once and
     * judge the whole shop by, and "3 kreditů" is the sort of thing that
     * makes a message look machine-made.
     */
    public static function amountCs(int $tenths): string
    {
        $n    = self::fmtCs($tenths);
        $whole = $tenths % self::SCALE === 0;

        if (!$whole) {
            return "$n kreditu";
        }

        $units = intdiv($tenths, self::SCALE);
        if ($units === 1) {
            return "$n kredit";
        }
        if ($units >= 2 && $units <= 4) {
            return "$n kredity";
        }

        return "$n kreditů";
    }

    /** English counterpart, for the same sentences. */
    public static function amountEn(int $tenths): string
    {
        return self::fmt($tenths) . ($tenths === self::SCALE ? ' credit' : ' credits');
    }

    /** Value for a number input, which always wants a dot. */
    public static function input(int $tenths): string
    {
        return self::fmt($tenths);
    }

    /**
     * Percentages allow one decimal too, kept as tenths of a percent so the
     * discount maths stays in integers until the final division.
     */
    public static function parsePercent(string|int|float|null $input): int
    {
        return max(0, min(950, self::parse($input)));
    }

    public static function fmtPercent(int $tenths): string
    {
        return self::fmt($tenths);
    }
}
