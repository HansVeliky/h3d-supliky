<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * QR code encoder, byte mode, error correction level M.
 *
 * Written here rather than pulled in because the alternatives all fail on
 * this deployment: an online generator would send the customer's payment
 * details to a third party and would not load on a network that blocks
 * foreign hosts, and a Composer package would break the "copy a folder"
 * property the whole build rests on.
 *
 * Scope is deliberately narrow - byte mode, level M, versions 1 to 10. A
 * Czech payment string is 60 to 120 characters, which fits comfortably, and
 * every feature left out is one that cannot be wrong.
 *
 * Verified module-for-module against a reference implementation in
 * test_qr.php; a QR code that is subtly wrong looks identical to a right one
 * until somebody's banking app refuses it.
 */
final class Qr
{
    /** Data codewords available per version at EC level M. */
    private const DATA_CODEWORDS_M = [
        1 => 16, 2 => 28, 3 => 44, 4 => 64, 5 => 86,
        6 => 108, 7 => 124, 8 => 154, 9 => 182, 10 => 216,
    ];

    /** EC codewords per block at level M. */
    private const EC_PER_BLOCK_M = [
        1 => 10, 2 => 16, 3 => 26, 4 => 18, 5 => 24,
        6 => 16, 7 => 18, 8 => 22, 9 => 22, 10 => 26,
    ];

    /** [group1 blocks, group2 blocks] at level M. */
    private const BLOCKS_M = [
        1 => [1, 0], 2 => [1, 0], 3 => [1, 0], 4 => [2, 0], 5 => [2, 0],
        6 => [4, 0], 7 => [4, 0], 8 => [2, 2], 9 => [3, 2], 10 => [4, 1],
    ];

    /** Centres of the alignment patterns, per version. */
    private const ALIGN = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46],
        10 => [6, 28, 50],
    ];

    private static array $exp = [];
    private static array $log = [];

    // ------------------------------------------------------------------
    // Public
    // ------------------------------------------------------------------

    /**
     * Renders the data as an SVG QR code.
     *
     * @param int $size    pixel size of the finished square
     * @param int $quiet   quiet zone in modules; 4 is the spec minimum and
     *                     scanners genuinely need it
     */
    public static function svg(string $data, int $size = 220, int $quiet = 4): string
    {
        $m = self::matrix($data);
        $n = count($m);
        $total = $n + $quiet * 2;

        $rects = '';
        foreach ($m as $y => $row) {
            // Runs of dark modules become one rect, which keeps the SVG
            // small enough to inline in an email without bloating it.
            $x = 0;
            while ($x < $n) {
                if (!$row[$x]) { $x++; continue; }
                $start = $x;
                while ($x < $n && $row[$x]) { $x++; }
                $rects .= '<rect x="' . ($start + $quiet) . '" y="' . ($y + $quiet)
                        . '" width="' . ($x - $start) . '" height="1"/>';
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size
             . '" viewBox="0 0 ' . $total . ' ' . $total . '" shape-rendering="crispEdges"'
             . ' role="img" aria-label="QR code">'
             . '<rect width="' . $total . '" height="' . $total . '" fill="#ffffff"/>'
             . '<g fill="#000000">' . $rects . '</g></svg>';
    }

    /** Matrix with a specific mask, used by the tests. */
    public static function matrixWithMask(string $data, int $mask): array
    {
        $version = self::pickVersion($data);
        return self::place(self::interleave(self::encodeData($data, $version), $version), $version, $mask);
    }

    /**
     * The module matrix: true is dark.
     *
     * @return array<int, array<int, bool>>
     */
    public static function matrix(string $data): array
    {
        $version = self::pickVersion($data);
        $bits    = self::encodeData($data, $version);
        $final   = self::interleave($bits, $version);

        $best = null;
        $bestPenalty = PHP_INT_MAX;

        // The spec does not say which mask to use, only that the one with
        // the lowest penalty wins, so all eight are built and scored.
        for ($mask = 0; $mask < 8; $mask++) {
            $m = self::place($final, $version, $mask);
            $p = self::penalty($m);
            if ($p < $bestPenalty) {
                $bestPenalty = $p;
                $best = $m;
            }
        }

        return $best;
    }

    // ------------------------------------------------------------------
    // Encoding
    // ------------------------------------------------------------------

    private static function pickVersion(string $data): int
    {
        $len = strlen($data);
        foreach (self::DATA_CODEWORDS_M as $v => $cap) {
            // 4 bits mode + 8 or 16 bits length + the data itself.
            $header = 4 + ($v < 10 ? 8 : 16);
            if ((int) ceil(($header + $len * 8) / 8) <= $cap) {
                return $v;
            }
        }
        throw new RuntimeException('Data too long for a version 10 QR code.');
    }

    /** @return int[] data codewords, padded to the version capacity */
    private static function encodeData(string $data, int $version): array
    {
        $bits = '';
        $bits .= '0100';                                   // byte mode
        $lenBits = $version < 10 ? 8 : 16;
        $bits .= str_pad(decbin(strlen($data)), $lenBits, '0', STR_PAD_LEFT);

        foreach (str_split($data) as $ch) {
            $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
        }

        $capacityBits = self::DATA_CODEWORDS_M[$version] * 8;

        // Terminator: up to four zero bits, or fewer if the end is close.
        $bits .= str_repeat('0', min(4, $capacityBits - strlen($bits)));
        // Pad to a byte boundary.
        if (strlen($bits) % 8 !== 0) {
            $bits .= str_repeat('0', 8 - strlen($bits) % 8);
        }

        $codewords = [];
        foreach (str_split($bits, 8) as $byte) {
            $codewords[] = bindec($byte);
        }

        // The spec's alternating pad bytes fill the rest.
        $pads = [0xEC, 0x11];
        $i = 0;
        while (count($codewords) < self::DATA_CODEWORDS_M[$version]) {
            $codewords[] = $pads[$i++ % 2];
        }

        return $codewords;
    }

    /**
     * Splits into blocks, computes error correction, and interleaves both
     * back together in the order the spec requires.
     *
     * @param int[] $codewords
     * @return int[]
     */
    private static function interleave(array $codewords, int $version): array
    {
        [$g1, $g2] = self::BLOCKS_M[$version];
        $totalBlocks = $g1 + $g2;
        $ecLen = self::EC_PER_BLOCK_M[$version];

        $shortLen = intdiv(count($codewords), $totalBlocks);

        $blocks = [];
        $ecBlocks = [];
        $pos = 0;

        for ($b = 0; $b < $totalBlocks; $b++) {
            // Group 2 blocks hold one more codeword than group 1.
            $len = $b < $g1 ? $shortLen : $shortLen + 1;
            $block = array_slice($codewords, $pos, $len);
            $pos += $len;
            $blocks[] = $block;
            $ecBlocks[] = self::reedSolomon($block, $ecLen);
        }

        $out = [];
        $maxLen = max(array_map('count', $blocks));
        for ($i = 0; $i < $maxLen; $i++) {
            foreach ($blocks as $block) {
                if (isset($block[$i])) {
                    $out[] = $block[$i];
                }
            }
        }
        for ($i = 0; $i < $ecLen; $i++) {
            foreach ($ecBlocks as $block) {
                $out[] = $block[$i];
            }
        }

        return $out;
    }

    private static function initGf(): void
    {
        if (self::$exp !== []) {
            return;
        }
        $x = 1;
        for ($i = 0; $i < 256; $i++) {
            self::$exp[$i] = $x;
            self::$log[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) {
                $x ^= 0x11D;   // the QR field polynomial
            }
        }
    }

    /**
     * @param int[] $data
     * @return int[]
     */
    private static function reedSolomon(array $data, int $ecLen): array
    {
        self::initGf();

        // Generator polynomial for the requested number of EC codewords.
        $gen = [1];
        for ($i = 0; $i < $ecLen; $i++) {
            $next = array_fill(0, count($gen) + 1, 0);
            foreach ($gen as $j => $coef) {
                $next[$j] ^= $coef;
                $next[$j + 1] ^= self::mul($coef, self::$exp[$i]);
            }
            $gen = $next;
        }

        $rem = array_merge($data, array_fill(0, $ecLen, 0));

        for ($i = 0; $i < count($data); $i++) {
            $factor = $rem[$i];
            if ($factor === 0) {
                continue;
            }
            foreach ($gen as $j => $coef) {
                $rem[$i + $j] ^= self::mul($coef, $factor);
            }
        }

        return array_slice($rem, count($data));
    }

    private static function mul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::$exp[(self::$log[$a] + self::$log[$b]) % 255];
    }

    // ------------------------------------------------------------------
    // Matrix
    // ------------------------------------------------------------------

    /**
     * @param int[] $codewords
     * @return array<int, array<int, bool>>
     */
    private static function place(array $codewords, int $version, int $mask): array
    {
        $n = $version * 4 + 17;

        $m   = array_fill(0, $n, array_fill(0, $n, false));
        $res = array_fill(0, $n, array_fill(0, $n, false));   // reserved

        $setFn = static function (int $x, int $y, bool $v) use (&$m, &$res, $n): void {
            if ($x < 0 || $y < 0 || $x >= $n || $y >= $n) {
                return;
            }
            $m[$y][$x] = $v;
            $res[$y][$x] = true;
        };

        // Finder patterns and their separators.
        foreach ([[0, 0], [$n - 7, 0], [0, $n - 7]] as [$fx, $fy]) {
            for ($dy = -1; $dy <= 7; $dy++) {
                for ($dx = -1; $dx <= 7; $dx++) {
                    $x = $fx + $dx;
                    $y = $fy + $dy;
                    if ($x < 0 || $y < 0 || $x >= $n || $y >= $n) {
                        continue;
                    }
                    $inRing = ($dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6)
                        && ($dx === 0 || $dx === 6 || $dy === 0 || $dy === 6
                            || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4));
                    $setFn($x, $y, $inRing);
                }
            }
        }

        // Timing patterns.
        for ($i = 8; $i < $n - 8; $i++) {
            $setFn($i, 6, $i % 2 === 0);
            $setFn(6, $i, $i % 2 === 0);
        }

        // Alignment patterns, skipping the ones that collide with finders.
        $centres = self::ALIGN[$version];
        foreach ($centres as $cy) {
            foreach ($centres as $cx) {
                $nearFinder = ($cx <= 8 && $cy <= 8)
                    || ($cx <= 8 && $cy >= $n - 9)
                    || ($cx >= $n - 9 && $cy <= 8);
                if ($nearFinder) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $dark = max(abs($dx), abs($dy)) !== 1;
                        $setFn($cx + $dx, $cy + $dy, $dark);
                    }
                }
            }
        }

        // The dark module, always set.
        $setFn(8, $n - 8, true);

        // Versions 7 and up carry an 18-bit version block twice. Leaving it
        // out produced a code that looked plausible and matched the
        // reference for every version below 7, which is exactly the kind of
        // bug that reaches a customer's banking app before it is noticed.
        if ($version >= 7) {
            for ($i = 0; $i < 18; $i++) {
                $a = intdiv($i, 3);
                $b = $i % 3;
                $res[$n - 11 + $b][$a] = true;
                $res[$a][$n - 11 + $b] = true;
            }
        }

        // Reserve the format areas; the bits go in afterwards.
        for ($i = 0; $i < 9; $i++) {
            if ($i !== 6) {
                $res[$i][8] = true;
                $res[8][$i] = true;
            }
        }
        for ($i = 0; $i < 8; $i++) {
            $res[$n - 1 - $i][8] = true;
            $res[8][$n - 1 - $i] = true;
        }

        // Data, snaking up and down in two-module columns.
        $bitString = '';
        foreach ($codewords as $cw) {
            $bitString .= str_pad(decbin($cw), 8, '0', STR_PAD_LEFT);
        }

        $idx = 0;
        $len = strlen($bitString);
        $up = true;

        for ($col = $n - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col--;   // the vertical timing column is skipped entirely
            }
            for ($i = 0; $i < $n; $i++) {
                $row = $up ? $n - 1 - $i : $i;
                foreach ([$col, $col - 1] as $c) {
                    if ($res[$row][$c]) {
                        continue;
                    }
                    $bit = $idx < $len ? $bitString[$idx] === '1' : false;
                    $idx++;
                    $m[$row][$c] = $bit !== self::maskAt($mask, $row, $c);
                }
            }
            $up = !$up;
        }

        if ($version >= 7) {
            $ver = self::versionBits($version);
            for ($i = 0; $i < 18; $i++) {
                $bit = (bool) (($ver >> $i) & 1);
                $a = intdiv($i, 3);
                $b = $i % 3;
                $m[$n - 11 + $b][$a] = $bit;
                $m[$a][$n - 11 + $b] = $bit;
            }
        }

        // Format information, written twice.
        $fmt = self::formatBits($mask);
        for ($i = 0; $i < 15; $i++) {
            $bit = (bool) (($fmt >> $i) & 1);

            if ($i < 6)        { $m[$i][8] = $bit; }
            elseif ($i === 6)  { $m[7][8] = $bit; }
            elseif ($i === 7)  { $m[8][8] = $bit; }
            elseif ($i === 8)  { $m[8][7] = $bit; }
            else               { $m[8][14 - $i] = $bit; }

            if ($i < 8) { $m[8][$n - 1 - $i] = $bit; }
            else        { $m[$n - 15 + $i][8] = $bit; }
        }

        return $m;
    }

    private static function maskAt(int $mask, int $row, int $col): bool
    {
        return match ($mask) {
            0 => ($row + $col) % 2 === 0,
            1 => $row % 2 === 0,
            2 => $col % 3 === 0,
            3 => ($row + $col) % 3 === 0,
            4 => (intdiv($row, 2) + intdiv($col, 3)) % 2 === 0,
            5 => (($row * $col) % 2) + (($row * $col) % 3) === 0,
            6 => ((($row * $col) % 2 + ($row * $col) % 3) % 2) === 0,
            7 => ((($row + $col) % 2) + (($row * $col) % 3)) % 2 === 0,
        };
    }

    /** Format string: EC level M plus the mask, BCH protected and XORed. */
    private static function formatBits(int $mask): int
    {
        $data = (0b00 << 3) | $mask;   // 00 is level M
        $rem = $data;
        for ($i = 0; $i < 10; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 9) & 1) * 0x537);
        }
        return (($data << 10) | $rem) ^ 0x5412;
    }

    /** Version string: 6 version bits plus 12 BCH bits. */
    private static function versionBits(int $version): int
    {
        $rem = $version;
        for ($i = 0; $i < 12; $i++) {
            $rem = ($rem << 1) ^ ((($rem >> 11) & 1) * 0x1F25);
        }
        return ($version << 12) | $rem;
    }

    /** @param array<int, array<int, bool>> $m */
    private static function penalty(array $m): int
    {
        $n = count($m);
        $score = 0;

        // Rule 1: runs of five or more identical modules.
        for ($pass = 0; $pass < 2; $pass++) {
            for ($a = 0; $a < $n; $a++) {
                $run = 1;
                for ($b = 1; $b < $n; $b++) {
                    $cur  = $pass === 0 ? $m[$a][$b] : $m[$b][$a];
                    $prev = $pass === 0 ? $m[$a][$b - 1] : $m[$b - 1][$a];
                    if ($cur === $prev) {
                        $run++;
                    } else {
                        if ($run >= 5) { $score += 3 + ($run - 5); }
                        $run = 1;
                    }
                }
                if ($run >= 5) { $score += 3 + ($run - 5); }
            }
        }

        // Rule 2: 2x2 blocks of one colour.
        for ($y = 0; $y < $n - 1; $y++) {
            for ($x = 0; $x < $n - 1; $x++) {
                if ($m[$y][$x] === $m[$y][$x + 1]
                    && $m[$y][$x] === $m[$y + 1][$x]
                    && $m[$y][$x] === $m[$y + 1][$x + 1]) {
                    $score += 3;
                }
            }
        }

        // Rule 3: the finder-like sequence, in both directions.
        $p1 = [true, false, true, true, true, false, true, false, false, false, false];
        $p2 = array_reverse($p1);
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n - 10; $x++) {
                $rowSlice = array_slice($m[$y], $x, 11);
                if ($rowSlice === $p1 || $rowSlice === $p2) { $score += 40; }

                $colSlice = [];
                for ($k = 0; $k < 11; $k++) { $colSlice[] = $m[$x + $k][$y]; }
                if ($colSlice === $p1 || $colSlice === $p2) { $score += 40; }
            }
        }

        // Rule 4: how far the dark ratio strays from half.
        $dark = 0;
        foreach ($m as $row) {
            foreach ($row as $v) { if ($v) { $dark++; } }
        }
        $percent = ($dark * 100) / ($n * $n);
        $score += (int) (abs($percent - 50) / 5) * 10;

        return $score;
    }
}
