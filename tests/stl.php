<?php
declare(strict_types=1);

/**
 * Reads an exported STL back and says what is wrong with it.
 *
 *   php tests/stl.php cesta/k/modelu.stl
 *
 * The test suite builds meshes from a layout this file knows nothing about,
 * so it can only check the layouts someone thought to write down. This one
 * goes the other way: it takes the file that actually came out of the studio
 * and measures it, which is the only way to look at a fault that shows up on
 * somebody else's drawer.
 *
 * What it reports, per solid found in the file:
 *   - closed or not, and where the holes are,
 *   - degenerate triangles, non-manifold edges, duplicates,
 *   - the thinnest wall, with the coordinates,
 *   - vertical faces that do not sit on a straight line with their
 *     neighbours - the step in the middle of a wall a slicer draws,
 *   - how close two separate solids come to each other.
 */

$path = $argv[1] ?? '';
if ($path === '' || !is_file($path)) {
    fwrite(STDERR, "Použití: php tests/stl.php cesta/k/modelu.stl\n");
    exit(2);
}

$raw = (string) file_get_contents($path);
$tris = [];

if (strlen($raw) > 84 && str_starts_with(strtolower(ltrim($raw)), 'solid') && !preg_match('/facet\s+normal/i', substr($raw, 0, 512))) {
    // "solid" in the header of a binary file: the count decides, not the word.
    $raw = $raw;
}
if (preg_match('/facet\s+normal/i', $raw)) {
    // ASCII
    preg_match_all('/vertex\s+(\S+)\s+(\S+)\s+(\S+)/i', $raw, $m, PREG_SET_ORDER);
    for ($i = 0; $i + 2 < count($m); $i += 3) {
        $tris[] = [
            [(float) $m[$i][1], (float) $m[$i][2], (float) $m[$i][3]],
            [(float) $m[$i + 1][1], (float) $m[$i + 1][2], (float) $m[$i + 1][3]],
            [(float) $m[$i + 2][1], (float) $m[$i + 2][2], (float) $m[$i + 2][3]],
        ];
    }
} else {
    $n = unpack('V', substr($raw, 80, 4))[1] ?? 0;
    if (84 + $n * 50 > strlen($raw)) {
        fwrite(STDERR, "Soubor je useknutý: hlavička hlásí $n trojúhelníků, ale data končí dřív.\n");
        $n = intdiv(strlen($raw) - 84, 50);
    }
    for ($i = 0; $i < $n; $i++) {
        $f = unpack('f9', substr($raw, 84 + $i * 50 + 12, 36));
        $tris[] = [
            [$f[1], $f[2], $f[3]], [$f[4], $f[5], $f[6]], [$f[7], $f[8], $f[9]],
        ];
    }
}
if (!$tris) {
    fwrite(STDERR, "V souboru nejsou žádné trojúhelníky.\n");
    exit(2);
}

printf("%s\n%s\n", basename($path), str_repeat('-', 60));
printf("trojúhelníků: %d\n", count($tris));

/* Weld to indices the same way the exporter does, so the report speaks about
   the same vertices the builder made. */
$vmap = [];
$V    = [];
$T    = [];
$key  = static fn (array $p): string => sprintf('%.4f|%.4f|%.4f', $p[0], $p[1], $p[2]);
foreach ($tris as $t) {
    $idx = [];
    foreach ($t as $p) {
        $k = $key($p);
        if (!isset($vmap[$k])) { $V[] = $p; $vmap[$k] = count($V) - 1; }
        $idx[] = $vmap[$k];
    }
    $T[] = $idx;
}
printf("vrcholů po svaření: %d\n", count($V));

$xs = array_column($V, 0); $ys = array_column($V, 1); $zs = array_column($V, 2);
printf("rozměry: %.2f x %.2f x %.2f mm  (x %.2f..%.2f, y %.2f..%.2f, z %.2f..%.2f)\n",
    max($xs) - min($xs), max($ys) - min($ys), max($zs) - min($zs),
    min($xs), max($xs), min($ys), max($ys), min($zs), max($zs));

/* ---- separate solids -------------------------------------------------- */
$par = range(0, count($V) - 1);
$find = static function (int $i) use (&$par, &$find): int {
    while ($par[$i] !== $i) { $par[$i] = $par[$par[$i]]; $i = $par[$i]; }
    return $i;
};
foreach ($T as [$a, $b, $c]) {
    foreach ([[$a, $b], [$b, $c], [$c, $a]] as [$p, $q]) {
        $rp = $find($p); $rq = $find($q);
        if ($rp !== $rq) { $par[$rp] = $rq; }
    }
}
$parts = [];
foreach ($T as $i => [$a]) { $parts[$find($a)][] = $i; }
printf("samostatných těles: %d\n\n", count($parts));

/* ---- per solid --------------------------------------------------------- */
$partNo = 0;
$rings  = [];
foreach ($parts as $tl) {
    $partNo++;
    $px = []; $py = []; $pz = [];
    foreach ($tl as $ti) { foreach ($T[$ti] as $vi) { $px[] = $V[$vi][0]; $py[] = $V[$vi][1]; $pz[] = $V[$vi][2]; } }
    printf("těleso %d: %d trojúhelníků, %.2f x %.2f x %.2f mm na (%.2f, %.2f)\n",
        $partNo, count($tl), max($px) - min($px), max($py) - min($py), max($pz) - min($pz), min($px), min($py));

    // closed?
    $edge = [];
    foreach ($tl as $ti) {
        [$a, $b, $c] = $T[$ti];
        foreach ([[$a, $b], [$b, $c], [$c, $a]] as [$p, $q]) { $edge[$p . '>' . $q] = ($edge[$p . '>' . $q] ?? 0) + 1; }
    }
    $open = [];
    foreach ($edge as $k => $n) {
        [$p, $q] = array_map('intval', explode('>', $k));
        if ($n !== 1 || ($edge[$q . '>' . $p] ?? 0) !== 1) { $open[] = [$p, $q]; }
    }
    printf("   uzavřené: %s\n", $open ? 'NE, ' . count($open) . ' vadných hran' : 'ano');
    foreach (array_slice($open, 0, 5) as [$p, $q]) {
        printf("      díra u (%.3f, %.3f, %.3f)\n", $V[$p][0], $V[$p][1], $V[$p][2]);
    }

    // degenerate / duplicate
    $deg = 0; $seen = []; $dup = 0;
    foreach ($tl as $ti) {
        [$a, $b, $c] = $T[$ti];
        $u = [$V[$b][0] - $V[$a][0], $V[$b][1] - $V[$a][1], $V[$b][2] - $V[$a][2]];
        $v = [$V[$c][0] - $V[$a][0], $V[$c][1] - $V[$a][1], $V[$c][2] - $V[$a][2]];
        $nx = $u[1] * $v[2] - $u[2] * $v[1]; $ny = $u[2] * $v[0] - $u[0] * $v[2]; $nz = $u[0] * $v[1] - $u[1] * $v[0];
        if (sqrt($nx * $nx + $ny * $ny + $nz * $nz) / 2 < 1e-9) { $deg++; }
        $s = [$a, $b, $c]; sort($s); $k = implode(',', $s);
        if (isset($seen[$k])) { $dup++; } else { $seen[$k] = 1; }
    }
    if ($deg || $dup) { printf("   zdegenerované: %d, duplicitní: %d\n", $deg, $dup); }

    /*
     * Vertical faces, read as a plan at the rim. The outside runs from the
     * bed to the top; anything starting higher is a cavity or a divider.
     */
    $topZ = max($pz); $botZ = min($pz);
    $outer = []; $inner = [];
    foreach ($tl as $ti) {
        $p = [$V[$T[$ti][0]], $V[$T[$ti][1]], $V[$T[$ti][2]]];
        $tz = array_column($p, 2);
        if (abs(max($tz) - $topZ) > 1e-6) { continue; }
        $pts = [];
        foreach ($p as $v) { $pts[sprintf('%.4f|%.4f', $v[0], $v[1])] = [$v[0], $v[1]]; }
        $pts = array_values($pts);
        if (count($pts) !== 2) { continue; }
        if (abs(min($tz) - $botZ) < 1e-6) { $outer[] = $pts; } else { $inner[] = $pts; }
    }
    $rings[$partNo] = $outer;

    $d2 = static function (array $pt, array $s): float {
        [$a, $b] = $s;
        $vx = $b[0] - $a[0]; $vy = $b[1] - $a[1];
        $len = $vx * $vx + $vy * $vy;
        $t = $len > 0 ? max(0.0, min(1.0, (($pt[0] - $a[0]) * $vx + ($pt[1] - $a[1]) * $vy) / $len)) : 0.0;
        return hypot($pt[0] - ($a[0] + $t * $vx), $pt[1] - ($a[1] + $t * $vy));
    };
    if ($outer && $inner) {
        $min = INF; $at = null;
        foreach ([[$outer, $inner], [$inner, $outer]] as [$f, $t]) {
            foreach ($f as $s) {
                foreach ($s as $pt) {
                    foreach ($t as $g) { $d = $d2($pt, $g); if ($d < $min) { $min = $d; $at = $pt; } }
                }
            }
        }
        printf("   nejtenčí stěna: %.4f mm u (%.2f, %.2f)\n", $min, $at[0], $at[1]);
    }

    /*
     * A step in the middle of a wall. Axis-aligned faces are collected per
     * line; two lines a hair apart that both carry a long run mean the wall
     * jumps sideways somewhere along it - the fault a slicer draws as a
     * wedge or a notch, and the one that is invisible in a list of numbers.
     */
    foreach ([['svislá', 0, 1], ['vodorovná', 1, 0]] as [$what, $ax, $other]) {
        $lines = [];
        foreach ($outer as [$a, $b]) {
            if (abs($a[$ax] - $b[$ax]) > 1e-6 || abs($a[$other] - $b[$other]) < 1e-6) { continue; }
            $lines[sprintf('%.3f', $a[$ax])] = ($lines[sprintf('%.3f', $a[$ax])] ?? 0) + abs($a[$other] - $b[$other]);
        }
        ksort($lines, SORT_NUMERIC);
        $keys = array_keys($lines);
        for ($i = 0; $i + 1 < count($keys); $i++) {
            $gap = (float) $keys[$i + 1] - (float) $keys[$i];
            if ($gap > 1e-3 && $gap < 1.0 && $lines[$keys[$i]] > 2.0 && $lines[$keys[$i + 1]] > 2.0) {
                printf("   SKOK: dvě %s lica %.3f mm od sebe na %s a %s (délky %.1f a %.1f mm)\n",
                    $what, $gap, $keys[$i], $keys[$i + 1], $lines[$keys[$i]], $lines[$keys[$i + 1]]);
            }
        }
    }
    echo "\n";
}

/* ---- clearance between solids ------------------------------------------ */
if (count($rings) > 1) {
    $d2 = static function (array $pt, array $s): float {
        [$a, $b] = $s;
        $vx = $b[0] - $a[0]; $vy = $b[1] - $a[1];
        $len = $vx * $vx + $vy * $vy;
        $t = $len > 0 ? max(0.0, min(1.0, (($pt[0] - $a[0]) * $vx + ($pt[1] - $a[1]) * $vy) / $len)) : 0.0;
        return hypot($pt[0] - ($a[0] + $t * $vx), $pt[1] - ($a[1] + $t * $vy));
    };
    $worst = INF; $where = '';
    $ids = array_keys($rings);
    for ($i = 0; $i < count($ids); $i++) {
        for ($j = $i + 1; $j < count($ids); $j++) {
            $min = INF;
            foreach ($rings[$ids[$i]] as $a) {
                foreach ($rings[$ids[$j]] as $b) {
                    $min = min($min, $d2($a[0], $b), $d2($a[1], $b), $d2($b[0], $a), $d2($b[1], $a));
                }
            }
            if ($min < 5.0 && $min < $worst) { $worst = $min; $where = $ids[$i] . ' a ' . $ids[$j]; }
        }
    }
    if (is_finite($worst)) {
        printf("nejmenší mezera mezi sousedními tělesy: %.4f mm (těleso %s)\n", $worst, $where);
    }
}
