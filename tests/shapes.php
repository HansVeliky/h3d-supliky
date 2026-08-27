<?php
declare(strict_types=1);

/**
 * FREE MODE shapes, with and without dividers, all the way to the mesh.
 *
 *   php tests/shapes.php
 *
 * The geometry pass in check.php builds plain shapes; this one builds the
 * combination the studio actually produces - an L, a cross or a staircase
 * that also carries dividers - and it goes through Layout::parse() first, so
 * it takes the same road an export does rather than calling the mesh builder
 * directly.
 *
 * What it insists on, for every shape and every setting:
 *   - the mesh is closed (a slicer refuses anything else),
 *   - nothing sticks out past the shape's own cells,
 *   - no wall anywhere is thinner than the one that was asked for,
 *   - a square box comes out as ONE welded solid.
 */

$dir = sys_get_temp_dir() . '/h3d_shapes_' . bin2hex(random_bytes(4));
@mkdir($dir, 0777, true);
putenv('H3D_DATA_DIR=' . $dir);

require dirname(__DIR__) . '/lib/bootstrap.php';
require_once dirname(__DIR__) . '/lib/Geometry.php';

$pass = 0;
$fail = 0;
function ok(string $what, bool $cond, string $detail = ''): void
{
    global $pass, $fail;
    if ($cond) { $pass++; return; }
    $fail++;
    echo "  CHYBA $what" . ($detail !== '' ? " ($detail)" : '') . "\n";
}

echo "Tvary a příčky\n" . str_repeat('-', 60) . "\n";

/** How many separate shells the mesh has - a slicer's "split to parts". */
$pieces = static function (array $m): int {
    $par = range(0, count($m['vertices']) - 1);
    $find = static function (int $i) use (&$par, &$find): int {
        while ($par[$i] !== $i) { $par[$i] = $par[$par[$i]]; $i = $par[$i]; }
        return $i;
    };
    foreach ($m['tris'] as [$a, $b, $c]) {
        foreach ([[$a, $b], [$b, $c], [$c, $a]] as [$p, $q]) {
            $rp = $find($p); $rq = $find($q);
            if ($rp !== $rq) { $par[$rp] = $rq; }
        }
    }
    $roots = [];
    foreach ($m['tris'] as [$a]) { $roots[$find($a)] = true; }
    return count($roots);
};

/** Distance from a point to the nearest of a set of segments. */
$distTo = static function (array $pt, array $segs): float {
    $min = INF;
    foreach ($segs as [$a, $b]) {
        $vx = $b[0] - $a[0]; $vy = $b[1] - $a[1];
        $len = $vx * $vx + $vy * $vy;
        $t = $len > 0 ? max(0.0, min(1.0, (($pt[0] - $a[0]) * $vx + ($pt[1] - $a[1]) * $vy) / $len)) : 0.0;
        $min = min($min, hypot($pt[0] - ($a[0] + $t * $vx), $pt[1] - ($a[1] + $t * $vy)));
    }
    return $min;
};

/**
 * The outside surface as segments: vertical faces running from bed to rim.
 *
 * Deduplicated, because a face is two triangles and both of them report the
 * same pair of ground points. Left in, every edge of the outline is crossed
 * twice, and a ray cast for point-in-polygon comes back with the wrong
 * parity - which is how an earlier version of this file managed to declare
 * several hundred perfectly ordinary vertices to be outside the box.
 */
$outerFaces = static function (array $m, float $topZ): array {
    $segs = [];
    foreach ($m['tris'] as [$ia, $ib, $ic]) {
        $p  = [$m['vertices'][$ia], $m['vertices'][$ib], $m['vertices'][$ic]];
        $zs = array_column($p, 2);
        if (abs(max($zs) - $topZ) > 1e-9 || abs(min($zs)) > 1e-9) { continue; }
        $pts = [];
        foreach ($p as $v) { $pts[sprintf('%.5f|%.5f', $v[0], $v[1])] = [$v[0], $v[1]]; }
        $pts = array_values($pts);
        if (count($pts) !== 2) { continue; }
        $k = [sprintf('%.5f|%.5f', $pts[0][0], $pts[0][1]), sprintf('%.5f|%.5f', $pts[1][0], $pts[1][1])];
        sort($k);
        $segs[implode('>', $k)] = $pts;
    }
    return array_values($segs);
};

/** Is the point inside the outline those segments close? */
$insideFaces = static function (array $pt, array $segs): bool {
    $in = false;
    foreach ($segs as [$a, $b]) {
        if (($a[1] > $pt[1]) !== ($b[1] > $pt[1])) {
            $x = $a[0] + ($pt[1] - $a[1]) * ($b[0] - $a[0]) / ($b[1] - $a[1]);
            if ($pt[0] < $x) { $in = !$in; }
        }
    }
    return $in;
};

/**
 * The thinnest wall in the model, measured off the finished triangles.
 *
 * Vertical faces come in two kinds - the outside runs from the bed to the
 * top, a cavity from its floor up - so the mesh can be read back as two sets
 * of outlines without knowing how it was built. For rectilinear outlines the
 * closest approach always happens at a corner, so corner-to-edge in both
 * directions is not a sample: it is the minimum.
 */
$gauge = static function (array $m, float $floorZ, float $topZ) use ($distTo): float {
    $outer = [];
    $cav   = [];
    foreach ($m['tris'] as [$ia, $ib, $ic]) {
        $p  = [$m['vertices'][$ia], $m['vertices'][$ib], $m['vertices'][$ic]];
        $zs = array_column($p, 2);
        if (abs(max($zs) - $topZ) > 1e-9) { continue; }
        $kind = abs(min($zs)) < 1e-9 ? 'outer' : (abs(min($zs) - $floorZ) < 1e-9 ? 'cav' : null);
        if ($kind === null) { continue; }
        $pts = [];
        foreach ($p as $v) { $pts[sprintf('%.4f|%.4f', $v[0], $v[1])] = [$v[0], $v[1]]; }
        $pts = array_values($pts);
        if (count($pts) !== 2) { continue; }
        if ($kind === 'outer') { $outer[] = $pts; } else { $cav[] = $pts; }
    }
    if (!$outer || !$cav) { return INF; }

    $min = INF;
    foreach ([[$outer, $cav], [$cav, $outer]] as [$from, $to]) {
        foreach ($from as $s) {
            foreach ($s as $pt) { $min = min($min, $distTo($pt, $to)); }
        }
    }
    return $min;
};

/* The shapes the studio can produce, with the dividers a user would draw. */
$cases = [
    'L bez příček'   => [[[0, 0], [0, 1], [0, 2], [1, 2]], []],
    'L s příčkou'    => [[[0, 0], [0, 1], [0, 2], [1, 2]], ['h:0,0']],
    'L dvě příčky'   => [[[0, 0], [0, 1], [0, 2], [1, 2]], ['h:0,0', 'h:0,1']],
    'T s příčkou'    => [[[0, 0], [1, 0], [2, 0], [1, 1], [1, 2]], ['v:0,0', 'v:1,0']],
    'S s příčkou'    => [[[0, 0], [1, 0], [1, 1], [2, 1]], ['v:0,0']],
    'U s příčkou'    => [[[0, 0], [2, 0], [0, 1], [1, 1], [2, 1]], ['v:0,1', 'v:1,1']],
    'obdélník 2x2'   => [[[0, 0], [1, 0], [0, 1], [1, 1]], ['v:0,0', 'v:0,1']],

    /*
     * The shapes FREE MODE really produces once it has taken two neighbours:
     * crosses, blobs and pieces that wrap around each other. These have more
     * concave corners than an L, and a concave corner is where a wall goes
     * thin if the outline is offset even slightly wrong.
     */
    'kříž'             => [[[1, 0], [0, 1], [1, 1], [2, 1], [1, 2]], []],
    'kříž s příčkou'   => [[[1, 0], [0, 1], [1, 1], [2, 1], [1, 2]], ['h:1,0']],
    'kříž široký'      => [[[1, 0], [2, 0], [0, 1], [1, 1], [2, 1], [3, 1], [1, 2], [2, 2]], []],
    'schody'           => [[[0, 0], [1, 0], [1, 1], [2, 1], [2, 2], [3, 2]], []],
    'schody s příčkou' => [[[0, 0], [1, 0], [1, 1], [2, 1], [2, 2], [3, 2]], ['v:0,0']],
    'vidlička'         => [[[0, 0], [2, 0], [0, 1], [1, 1], [2, 1], [0, 2], [2, 2]], []],
    'Z'                => [[[0, 0], [1, 0], [1, 1], [1, 2], [2, 2]], []],
    'plný blok 3x2'    => [[[0, 0], [1, 0], [2, 0], [0, 1], [1, 1], [2, 1]], ['v:1,0', 'v:1,1']],

    /*
     * The eight-cell piece from the drawer in the bug report, cell for cell:
     * a bump on top of a wide body with a bite taken out of one corner. It
     * and its neighbour were the two boxes whose walls came out 0.96 mm
     * instead of 2 mm, and the only two the slicer showed a fault on.
     */
    'osmice z hlášení' => [[[2, 0], [0, 1], [1, 1], [2, 1], [3, 1], [0, 2], [1, 2], [2, 2]], []],
    'osmice s příčkou' => [[[2, 0], [0, 1], [1, 1], [2, 1], [3, 1], [0, 2], [1, 2], [2, 2]], ['v:0,1', 'v:0,2']],
];

$checked = 0;

/**
 * One shape, one set of numbers, all the way to the triangles.
 *
 * The grid is a parameter because it decides whether the coordinates land on
 * round numbers: five columns across 250 mm do, seven rows do not
 * (250/7 = 35.714285...), and a fault that only shows in the digits past the
 * fifth decimal stays invisible until something divides unevenly.
 */
$run = static function (array $cells, array $walls, string $name, float $gap, float $wall,
                        float $radius, int $cols, int $rows, float $dh)
    use (&$checked, $pieces, $gauge, $distTo, $outerFaces, $insideFaces): void {
    // A shape wider than the drawer is this file's own mistake, not a fault.
    if (max(array_column($cells, 0)) >= $cols || max(array_column($cells, 1)) >= $rows) {
        return;
    }

    $mk = static function (array $w) use ($cells, $gap, $wall, $radius, $cols, $rows, $dh): array {
        $payload = [
            'lang' => 'cs',
            'dw' => 250, 'dd' => 250, 'dh' => $dh, 'gap' => $gap, 'wall' => $wall,
            'bottom' => 2, 'radius' => $radius, 'outer' => 0, 'cols' => $cols, 'rows' => $rows,
            'boxes' => [[
                'cells' => array_map(static fn ($c) => ['x' => $c[0], 'y' => $c[1]], $cells),
                'walls' => $w,
            ]],
        ];
        ['cfg' => $cfg, 'boxes' => $boxes] = Layout::parse($payload);
        $b = $boxes[0];
        return isset($b['cells']) ? Geometry::polyMesh($cfg, $b) : Geometry::boxMesh($cfg, $b);
    };

    $label = sprintf('%s (%dx%d, mezera %.1f, stěna %.1f, r %.1f)',
        $name, $cols, $rows, $gap, $wall, $radius);
    try {
        $m = $mk($walls);
    } catch (Throwable $e) {
        /*
         * "Wall thickness is too large" is a refusal, not a fault: the studio
         * shows it and no file is written.
         */
        if (!str_contains($e->getMessage(), 'tlouš') && !str_contains($e->getMessage(), 'Wall thickness')) {
            ok($label . ': stavba', false, $e->getMessage());
        }
        return;
    }
    $checked++;

    ok($label . ': uzavřený', Geometry::isWatertight($m));

    /* Nothing may stick out past the cells the shape owns. */
    $cw = (250 - ($cols - 1) * $gap) / $cols;
    $ch = (250 - ($rows - 1) * $gap) / $rows;
    $xs = array_column($m['vertices'], 0);
    $ys = array_column($m['vertices'], 1);
    $left   = min(array_column($cells, 0)) * ($cw + $gap) - 1e-6;
    $right  = max(array_column($cells, 0)) * ($cw + $gap) + $cw + 1e-6;
    $top    = 250 - (min(array_column($cells, 1)) * ($ch + $gap)) + 1e-6;
    $bottom = 250 - (max(array_column($cells, 1)) * ($ch + $gap) + $ch) - 1e-6;
    ok($label . ': nepřesahuje do stran', min($xs) >= $left && max($xs) <= $right,
        sprintf('%.2f..%.2f mimo %.2f..%.2f', min($xs), max($xs), $left, $right));
    ok($label . ': nepřesahuje do hloubky', min($ys) >= $bottom && max($ys) <= $top,
        sprintf('%.2f..%.2f mimo %.2f..%.2f', min($ys), max($ys), $bottom, $top));

    /*
     * A rounded corner is a 32-sided polygon, so a vertex of one arc measured
     * against the chord of the other falls short of the true circle. Measured
     * at the shipped resolution that is 0.6 um on a free shape and 2.4 um on
     * a rectangle, and it falls by a factor of sixteen every time the segment
     * count doubles - the signature of a chord, not of a thin wall. Five
     * microns covers it and is still two orders below anything a nozzle can
     * lay down.
     */
    $sag = $radius > 0 ? 5e-3 : 1e-4;

    if ($radius <= 0.0) {
        // Square box: one welded solid, and the gauge reads every wall in
        // it, dividers included.
        ok($label . ': jeden kus', $pieces($m) === 1, 'kusů: ' . $pieces($m));
        $thin = $gauge($m, 2.0, $dh);
        ok($label . ': nikde tenčí stěna', !is_finite($thin) || $thin >= $wall - $sag,
            sprintf('nejtenčí %.4f, nastaveno %.1f', $thin, $wall));
        return;
    }

    /*
     * Rounded box: the shell stays smooth and each divider is its own volume
     * standing in it, so "one piece" is not what this path promises - an arc
     * rasterised onto the divider lattice would come out as a staircase, and
     * on a free shape it multiplies the lattice until the mesh runs out of
     * memory. What the path does promise is checked instead: the shell's own
     * walls are full thickness, and no divider comes nearer to the outside
     * than a wall, bar the hair of overlap that welds it to the side.
     */
    $shell = $mk([]);
    $thin  = $gauge($shell, 2.0, $dh);
    ok($label . ': skořepina má plnou stěnu', !is_finite($thin) || $thin >= $wall - $sag,
        sprintf('nejtenčí %.4f, nastaveno %.1f', $thin, $wall));

    if ($walls) {
        /*
         * What matters about a divider volume is that it stays inside the
         * box. How deep into the wall its end is buried does not: it is a
         * union, and at a rounded inside corner the cavity curves away, so a
         * rectangular divider naturally sits deeper there. Measuring the
         * distance to the outside face called that a fault; being outside
         * the box would be one.
         */
        $have = [];
        foreach ($shell['vertices'] as $v) {
            $have[sprintf('%.4f|%.4f|%.4f', $v[0], $v[1], $v[2])] = true;
        }
        $faces = $outerFaces($shell, $dh);
        $out   = 0;
        $worst = null;
        foreach ($m['vertices'] as $v) {
            if (isset($have[sprintf('%.4f|%.4f|%.4f', $v[0], $v[1], $v[2])])) { continue; }
            // On the outline counts as in; the ends are meant to touch it.
            if ($insideFaces([$v[0], $v[1]], $faces) || $distTo([$v[0], $v[1]], $faces) < 1e-6) { continue; }
            $out++;
            $worst = $v;
        }
        ok($label . ': příčka zůstává uvnitř boxu', $out === 0,
            $worst === null ? '' : sprintf('%d vrcholů venku, např. (%.2f, %.2f)', $out, $worst[0], $worst[1]));
    }
};

/* Square cells, no rounding: gaps and wall thicknesses. */
foreach ([0.0, 0.4, 1.2] as $gap) {
    foreach ([2.0, 2.5, 3.0] as $wall) {
        foreach ($cases as $name => [$cells, $walls]) {
            $run($cells, $walls, $name, $gap, $wall, 0.0, 5, 5, 50.0);
        }
    }
}

/*
 * Grids that do not divide evenly, with rounding on.
 *
 * This is the pass that matters. A shape in a seven-row drawer sits on
 * coordinates like 35.714285714285715, and rounding one of them to five
 * decimals - which the outline tracer used to do to its starting point -
 * moves it a few microns off the straight edge it lies on. It then counts as
 * a corner, a corner gets a fillet, and the fillet is cut through the middle
 * of a straight wall: on the drawer in the bug report two boxes came out with
 * a 0.96 mm wall where 2 mm was asked for. Square cells hid it, because
 * 250/5 is exact to five decimals.
 */
foreach ([[5, 7, 35.0], [3, 7, 40.0], [7, 7, 30.0], [4, 6, 45.0]] as [$cols, $rows, $dh]) {
    foreach ([0.0, 1.5, 3.0] as $radius) {
        foreach ($cases as $name => [$cells, $walls]) {
            $run($cells, $walls, $name, 0.4, 2.0, $radius, $cols, $rows, $dh);
        }
    }
}

/*
 * A whole drawer, not one box at a time.
 *
 * This is the layout from the bug report, cell for cell. Boxes are separate
 * models, so nothing here welds them together - what has to hold is that
 * every neighbour keeps the gap it was given. A wall pushed even a fraction
 * of a millimetre into the gap is what a slicer draws as a wedge lying along
 * a wall, and it is the shape people notice first.
 */
$drawer = [
    '1x1 a'  => [[0, 0]],
    '1x1 b'  => [[1, 0]],
    '3x1'    => [[2, 0], [3, 0], [4, 0]],
    '2x2'    => [[0, 1], [1, 1], [0, 2], [1, 2]],
    '5'      => [[2, 1], [3, 1], [4, 1], [2, 2], [4, 2]],
    '8 kříž' => [[3, 2], [1, 3], [2, 3], [3, 3], [4, 3], [1, 4], [2, 4], [3, 4]],
    '1x2'    => [[0, 3], [0, 4]],
    '4'      => [[0, 5], [1, 5], [2, 5], [0, 6]],
    '3'      => [[4, 4], [3, 5], [4, 5]],
    '4x1'    => [[1, 6], [2, 6], [3, 6], [4, 6]],
];
$names = array_keys($drawer);
foreach ([0.0, 1.0, 2.0, 3.0] as $radius) {
    $payload = [
        'lang' => 'cs',
        'dw' => 250, 'dd' => 250, 'dh' => 35, 'gap' => 0.4, 'wall' => 2.0,
        'bottom' => 2, 'radius' => $radius, 'outer' => 0, 'cols' => 5, 'rows' => 7,
        'boxes' => [],
    ];
    foreach ($drawer as $cells) {
        $payload['boxes'][] = [
            'cells' => array_map(static fn ($c) => ['x' => $c[0], 'y' => $c[1]], $cells),
            'walls' => [],
        ];
    }
    ['cfg' => $cfg, 'boxes' => $boxes] = Layout::parse($payload);

    $rings = [];
    foreach ($boxes as $i => $b) {
        $m = isset($b['cells']) ? Geometry::polyMesh($cfg, $b) : Geometry::boxMesh($cfg, $b);
        ok(sprintf('šuplík r%.1f: %s uzavřený', $radius, $names[$i]), Geometry::isWatertight($m));
        $thin = $gauge($m, 2.0, 35.0);
        ok(sprintf('šuplík r%.1f: %s má plnou stěnu', $radius, $names[$i]),
            !is_finite($thin) || $thin >= 2.0 - ($radius > 0 ? 5e-3 : 1e-4),
            sprintf('nejtenčí %.4f', $thin));
        $rings[$i] = $outerFaces($m, 35.0);
    }

    $segGap = static function (array $a, array $b) use ($distTo): float {
        return min($distTo($a[0], [$b]), $distTo($a[1], [$b]),
                   $distTo($b[0], [$a]), $distTo($b[1], [$a]));
    };
    $worst = INF;
    $where = '';
    for ($i = 0; $i < count($rings); $i++) {
        for ($j = $i + 1; $j < count($rings); $j++) {
            $min = INF;
            foreach ($rings[$i] as $a) {
                foreach ($rings[$j] as $b) { $min = min($min, $segGap($a, $b)); }
            }
            // Only actual neighbours; boxes across the drawer say nothing.
            if ($min < 5.0 && $min < $worst) { $worst = $min; $where = $names[$i] . '/' . $names[$j]; }
        }
    }
    ok(sprintf('šuplík r%.1f: sousedé drží mezeru', $radius), $worst >= 0.4 - 1e-4,
        sprintf('nejužší %.4f mm u %s, nastaveno 0.400', $worst, $where));
}

/*
 * A rounded box with dividers is deliberately built differently: the smooth
 * shell is kept and each divider is added as its own closed volume, because
 * rasterising the arc onto the divider lattice would turn the outer contour
 * into visible steps. The union prints correctly, but it IS several volumes,
 * so this part checks what that path can promise - every volume closed and
 * nothing outside the box - instead of pretending it is one solid.
 */
foreach ([2.0, 4.0] as $radius) {
    $payload = [
        'lang' => 'cs',
        'dw' => 250, 'dd' => 250, 'dh' => 50, 'gap' => 0.4, 'wall' => 2.0,
        'bottom' => 2, 'radius' => $radius, 'outer' => 0, 'cols' => 5, 'rows' => 5,
        'boxes' => [['x' => 0, 'y' => 0, 'w' => 2, 'h' => 2, 'walls' => ['v:0,0', 'v:0,1']]],
    ];
    try {
        ['cfg' => $cfgR, 'boxes' => $boxesR] = Layout::parse($payload);
        $mR = Geometry::boxMesh($cfgR, $boxesR[0]);
    } catch (Throwable $e) {
        ok(sprintf('zaoblený box R%.1f s příčkou: stavba', $radius), false, $e->getMessage());
        continue;
    }
    ok(sprintf('zaoblený box R%.1f: uzavřený', $radius), Geometry::isWatertight($mR));
    $cwR = (250 - 4 * 0.4) / 5;
    $xsR = array_column($mR['vertices'], 0);
    ok(sprintf('zaoblený box R%.1f: nepřesahuje', $radius),
        min($xsR) >= -1e-6 && max($xsR) <= 2 * $cwR + 0.4 + 1e-6,
        sprintf('%.2f..%.2f', min($xsR), max($xsR)));
    printf("  -     zaoblený box R%.1f je %d objemů (jeden svařený kus je jen hranatý)\n",
        $radius, $pieces($mR));
}
echo str_repeat('-', 60) . "\n";
echo $fail
    ? "NEPROŠLO: $fail z " . ($pass + $fail) . " kontrol ($checked tvarů)\n"
    : "všech $pass kontrol prošlo ($checked tvarů)\n";

foreach (glob($dir . '/*') ?: [] as $f) { @unlink($f); }
@rmdir($dir);
exit($fail ? 1 : 0);
