<?php
declare(strict_types=1);

/**
 * Mesh generation for the Honza3D Drawer Organizer.
 *
 * This file must never be reachable over HTTP. It is the part of the
 * application that is actually worth protecting, which is why it lives
 * server side instead of in the browser bundle.
 */
if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

final class Geometry
{
    /** Arc subdivisions per rounded corner. */
    private const SEGMENTS = 32;

    /** Coordinate precision used when welding coincident vertices. */
    private const WELD_DECIMALS = 4;

    /**
     * How far a point has to sit off the line through its neighbours before
     * it counts as a corner (mm).
     *
     * A cross product cannot be compared against a fixed number, because it
     * grows with the lengths of the two edges: the same 1e-9 that is strict
     * on a 1 mm edge is meaningless on a 50 mm one. Divided by the span it
     * becomes a distance, and a distance can be judged - a tenth of a micron
     * is far below anything a printer can lay down and far above the noise a
     * double carries after a few divisions.
     */
    private const FLAT_MM = 1e-4;

    /**
     * How far $q sits off the straight line through $p and $r, in mm.
     * Sign follows the cross product: positive turns left (convex on a CCW
     * loop), negative right.
     */
    private static function cornerOffset(array $p, array $q, array $r): float
    {
        $cross = ($q[0] - $p[0]) * ($r[1] - $q[1]) - ($q[1] - $p[1]) * ($r[0] - $q[0]);
        $span  = hypot($r[0] - $p[0], $r[1] - $p[1]);
        if ($span < 1e-12) {
            // The loop doubles back on itself; the offset is the whole edge.
            return hypot($q[0] - $p[0], $q[1] - $p[1]);
        }
        return $cross / $span;
    }

    /**
     * Closed CCW perimeter loop of a rounded rectangle at height $z.
     *
     * Always returns exactly 4 * SEGMENTS points, even when $r is zero.
     * The inner loop of a box can collapse to r = 0 while the outer loop
     * stays rounded (corner radius <= wall thickness); if the two loops had
     * different lengths the wall strips would index past the end of the
     * array. At r = 0 the corner arcs degenerate into repeated points,
     * which the vertex welding below merges away.
     */
    public static function roundedRectLoop(
        float $x0, float $y0,
        float $x1, float $y1,
        float $r, float $z
    ): array {
        $maxR = min(($x1 - $x0) / 2, ($y1 - $y0) / 2);
        $r    = max(0.0, min($r, $maxR));

        $corners = [
            [$x1 - $r, $y0 + $r, -M_PI / 2],
            [$x1 - $r, $y1 - $r, 0.0],
            [$x0 + $r, $y1 - $r, M_PI / 2],
            [$x0 + $r, $y0 + $r, M_PI],
        ];

        $pts = [];
        foreach ($corners as [$cx, $cy, $startAngle]) {
            for ($i = 0; $i < self::SEGMENTS; $i++) {
                $a = $startAngle + (M_PI / 2) * ($i / self::SEGMENTS);
                $pts[] = [
                    $cx + $r * cos($a),
                    $cy + $r * sin($a),
                    $z,
                ];
            }
        }

        return $pts;
    }

    /**
     * Build one watertight mesh for a single box.
     *
     * @param array $cfg dw, dd, dh, gap, wall, bottom, radius, cols, rows (mm)
     * @param array $b   x, y, w, h (grid cells)
     * @return array{vertices: array, tris: array}
     */
    public static function boxMesh(array $cfg, array $b): array
    {
        // A box with dividers is a different solid: one outside, several
        // compartments. It has its own builder.
        if (self::hasDividers($b)) {
            return self::dividedMesh($cfg, $b);
        }

        $DW = $cfg['dw'];
        $DD = $cfg['dd'];
        $DH = $cfg['dh'];
        $G  = $cfg['gap'];
        $W  = $cfg['wall'];
        $B  = $cfg['bottom'];
        $R  = $cfg['radius'];

        $cols = $cfg['cols'];
        $rows = $cfg['rows'];

        // The margin left free round the whole layout. The boxes are laid
        // out inside what is left, and the whole set sits one inset in from
        // the origin.
        $O  = max(0.0, (float) ($cfg['outer'] ?? 0.0));
        $UW = $DW - 2 * $O;
        $UD = $DD - 2 * $O;

        $cw = ($UW - ($cols - 1) * $G) / $cols;
        $ch = ($UD - ($rows - 1) * $G) / $rows;

        $x0 = $O + $b['x'] * ($cw + $G);
        $x1 = $x0 + $b['w'] * $cw + ($b['w'] - 1) * $G;

        // The screen draws row 0 at the TOP while a slicer's Y axis points
        // up, so rows are flipped here - the arrangement on the print bed
        // then matches the design exactly instead of being mirrored.
        $boxD = $b['h'] * $ch + ($b['h'] - 1) * $G;
        $y0   = $O + ($UD - ($b['y'] * ($ch + $G) + $boxD));
        $y1   = $y0 + $boxD;

        $outerR = min($R, ($x1 - $x0) / 2, ($y1 - $y0) / 2);

        $ix0 = $x0 + $W;
        $iy0 = $y0 + $W;
        $ix1 = $x1 - $W;
        $iy1 = $y1 - $W;

        // A uniform wall needs an inner radius smaller by its thickness.
        // With the same radius on both loops the rim swells in the corner.
        $innerR = min(max(0.0, $outerR - $W), ($ix1 - $ix0) / 2, ($iy1 - $iy0) / 2);

        if ($ix1 <= $ix0 || $iy1 <= $iy0) {
            throw new InvalidArgumentException('Wall thickness is too large for one of the boxes.');
        }
        if ($B <= 0 || $B >= $DH) {
            throw new InvalidArgumentException('Bottom thickness must be greater than 0 and smaller than box height.');
        }

        /*
         * Corner radius belongs to the OUTER wall only.
         *
         * The cavity deliberately stays square. This keeps the usable
         * inside volume rectangular and avoids the rounded "balloon" corners
         * that waste space when boxes are used next to each other or in a
         * future modular/grid system.
         *
         * The outer contour is rounded, while the inner contour is always
         * 90 degrees. The wall therefore changes shape naturally around the
         * outside corner instead of rounding the inside of the box too.
         */
        $outerBottom = self::roundedRectLoop($x0, $y0, $x1, $y1, $outerR, 0.0);
        $outerTop    = self::roundedRectLoop($x0, $y0, $x1, $y1, $outerR, $DH);
        $innerBottom = self::roundedRectLoop($ix0, $iy0, $ix1, $iy1, $innerR, $B);
        $innerTop    = self::roundedRectLoop($ix0, $iy0, $ix1, $iy1, $innerR, $DH);

        $vertices = [];
        $tris     = [];
        $vmap     = [];

        // Weld coincident vertices: two points sharing a position must share
        // an index, otherwise degenerate triangles survive and the solid is
        // not manifold.
        $addVertex = static function (array $v) use (&$vertices, &$vmap): int {
            $key = sprintf(
                '%.' . self::WELD_DECIMALS . 'f|%.' . self::WELD_DECIMALS . 'f|%.' . self::WELD_DECIMALS . 'f',
                $v[0], $v[1], $v[2]
            );
            if (!isset($vmap[$key])) {
                $vertices[] = $v;
                $vmap[$key] = count($vertices) - 1;
            }
            return $vmap[$key];
        };

        $addTri = static function (int $a, int $bb, int $c) use (&$tris): void {
            if ($a !== $bb && $bb !== $c && $c !== $a) {
                $tris[] = [$a, $bb, $c];
            }
        };

        $ob = array_map($addVertex, $outerBottom);
        $ot = array_map($addVertex, $outerTop);
        $ib = array_map($addVertex, $innerBottom);
        $it = array_map($addVertex, $innerTop);

        $n = count($ob);

        // Outer wall
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $addTri($ob[$i], $ob[$j], $ot[$j]);
            $addTri($ob[$i], $ot[$j], $ot[$i]);
        }

        // Inner wall
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $addTri($ib[$i], $it[$j], $ib[$j]);
            $addTri($ib[$i], $it[$i], $it[$j]);
        }

        // Top rim
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $addTri($ot[$i], $ot[$j], $it[$j]);
            $addTri($ot[$i], $it[$j], $it[$i]);
        }

        // Interior floor. The perimeter is CCW seen from above, so the floor
        // keeps the same winding and points up into the cavity.
        for ($i = 1; $i < $n - 1; $i++) {
            $addTri($ib[0], $ib[$i], $ib[$i + 1]);
        }

        // Outer underside. Reversed so the normal points down.
        for ($i = 1; $i < $n - 1; $i++) {
            $addTri($ob[0], $ob[$i + 1], $ob[$i]);
        }

        return ['vertices' => $vertices, 'tris' => $tris];
    }

    /**
     * Watertight mesh for a polyomino box (an L, T or any edge-connected
     * cell shape without enclosed holes).
     *
     * The footprint is the envelope of the shape's grid pitches inset by
     * half a gap, the outer wall stands on that outline, and the cavity is
     * the same outline one wall further in. Both visible contours are
     * rounded with the configured radius, so the inside is not forced to
     * square 90-degree corners.
     *
     * @param array $cfg dw, dd, dh, gap, wall, bottom, cols, rows (mm)
     * @param array $b   cells: list of ['x' => int, 'y' => int]
     * @return array{vertices: array, tris: array}
     */
    public static function polyMesh(array $cfg, array $b): array
    {
        if (self::hasDividers($b)) {
            return self::dividedMesh($cfg, $b);
        }

        $DD = $cfg['dd'];
        $DH = $cfg['dh'];
        $G  = $cfg['gap'];
        $W  = $cfg['wall'];
        $B  = $cfg['bottom'];

        $cols = $cfg['cols'];
        $rows = $cfg['rows'];

        // Same outer margin as boxMesh - the two have to agree cell for
        // cell, or an L would no longer line up with the rectangle beside it.
        $O  = max(0.0, (float) ($cfg['outer'] ?? 0.0));
        $UW = $cfg['dw'] - 2 * $O;
        $UD = $DD - 2 * $O;

        $cw = ($UW - ($cols - 1) * $G) / $cols;
        $ch = ($UD - ($rows - 1) * $G) / $rows;

        if ($B <= 0 || $B >= $DH) {
            throw new InvalidArgumentException('Bottom thickness must be greater than 0 and smaller than box height.');
        }
        if (2 * $W >= $cw || 2 * $W >= $ch) {
            throw new InvalidArgumentException('Wall thickness is too large for one of the boxes.');
        }

        $R = max(0.0, (float) ($cfg['radius'] ?? 0.0));

        // Same row flip as boxMesh: the bed must match the design, not
        // mirror it.
        $set = [];
        foreach ($b['cells'] as $c) {
            $set[((int) $c['x']) . ',' . ($rows - 1 - (int) $c['y'])] = true;
        }
        $has = static fn (int $x, int $y): bool => isset($set["$x,$y"]);

        $X0 = static fn (int $x): float => $O + $x * ($cw + $G);
        $Y0 = static fn (int $y): float => $O + $y * ($ch + $G);

        // The shape is the envelope of whole GRID PITCHES (cell + its share
        // of the gap), pulled in by half a gap. A rectangle comes out at the
        // familiar w*cw + (w-1)*gap, an L meets itself cleanly in the corner
        // instead of carrying a gap-wide step, and any two boxes still keep
        // a full gap between each other - each gives up gap/2.
        $minX = PHP_INT_MAX; $minY = PHP_INT_MAX;
        $maxX = PHP_INT_MIN; $maxY = PHP_INT_MIN;
        foreach (array_keys($set) as $key) {
            [$x, $y] = array_map('intval', explode(',', $key));
            $minX = min($minX, $x); $maxX = max($maxX, $x);
            $minY = min($minY, $y); $maxY = max($maxY, $y);
        }
        $gw = $maxX - $minX + 1;
        $gh = $maxY - $minY + 1;

        $xs = [];
        $ys = [];
        for ($i = 0; $i <= $gw; $i++) { $xs[] = $O + ($minX + $i) * ($cw + $G) - $G / 2; }
        for ($j = 0; $j <= $gh; $j++) { $ys[] = $O + ($minY + $j) * ($ch + $G) - $G / 2; }

        $grid = [];
        for ($j = 0; $j < $gh; $j++) {
            for ($i = 0; $i < $gw; $i++) {
                $grid[$j][$i] = $has($minX + $i, $minY + $j);
            }
        }

        // Pitch outlines, then the outer surface half a gap inside each and
        // the cavity one wall further - all offsets of the SAME loop, so the
        // three stay corner-for-corner in step and the wall is uniform
        // everywhere, concave corners included. A ring-shaped piece traces
        // more than one loop: the outside plus one per enclosed hole.
        $pitchLoops = self::traceLoops($grid, $xs, $ys);

        $outerLoops = [];
        $cavLoops   = [];
        foreach ($pitchLoops as $pitch) {
            $o = self::offsetInward($pitch, $G / 2);
            $c = self::offsetInward($pitch, $G / 2 + $W);
            // Both rounded in lockstep. Convex corners: radius outside,
            // radius minus wall inside; concave ones mirrored, so a set
            // radius means no sharp edge anywhere.
            [$o, $c] = self::roundLoops($o, $c, $R, $W);
            // A corner whose radius clamped to nothing leaves repeated
            // points; left in they become zero-area triangles - the
            // "ghosts" a slicer draws on a floor.
            [$o, $c] = self::cleanLoops($o, $c);
            $outerLoops[] = $o;
            $cavLoops[]   = $c;
        }

        $vertices = [];
        $tris     = [];
        $vmap     = [];
        $addVertex = static function (array $v) use (&$vertices, &$vmap): int {
            $key = sprintf(
                '%.' . self::WELD_DECIMALS . 'f|%.' . self::WELD_DECIMALS . 'f|%.' . self::WELD_DECIMALS . 'f',
                $v[0], $v[1], $v[2]
            );
            if (!isset($vmap[$key])) {
                $vertices[] = $v;
                $vmap[$key] = count($vertices) - 1;
            }
            return $vmap[$key];
        };
        $vi = static fn (array $p, float $z): int => $addVertex([$p[0], $p[1], $z]);
        $addTri = static function (int $a, int $bq, int $c) use (&$tris): void {
            if ($a !== $bq && $bq !== $c && $c !== $a) {
                $tris[] = [$a, $bq, $c];
            }
        };

        foreach ($outerLoops as $li => $outerLoop) {
            $cavLoop = $cavLoops[$li];

            // Outer wall: the loop keeps material on its left, so "right of
            // travel" faces away from the solid - true for the outside and
            // for the wall of a hole alike.
            $n = count($outerLoop);
            for ($i = 0; $i < $n; $i++) {
                $p = $outerLoop[$i];
                $q = $outerLoop[($i + 1) % $n];
                $addTri($vi($p, 0.0), $vi($q, 0.0), $vi($q, $DH));
                $addTri($vi($p, 0.0), $vi($q, $DH), $vi($p, $DH));
            }

            // Inner wall, from the floor up, facing the cavity.
            $m = count($cavLoop);
            for ($i = 0; $i < $m; $i++) {
                $p = $cavLoop[$i];
                $q = $cavLoop[($i + 1) % $m];
                $addTri($vi($q, $B), $vi($p, $B), $vi($p, $DH));
                $addTri($vi($q, $B), $vi($p, $DH), $vi($q, $DH));
            }

            // Top rim: the two loops run corner-for-corner in step, so the
            // ring between them zips shut with one quad per segment.
            for ($i = 0; $i < $n; $i++) {
                $o1 = $outerLoop[$i];
                $o2 = $outerLoop[($i + 1) % $n];
                $c1 = $cavLoop[$i % $m];
                $c2 = $cavLoop[($i + 1) % $m];
                $addTri($vi($o1, $DH), $vi($o2, $DH), $vi($c2, $DH));
                $addTri($vi($o1, $DH), $vi($c2, $DH), $vi($c1, $DH));
            }
        }

        // The caps are triangulated over each loop with its repeats dropped:
        // a repeated point welds to the same vertex anyway, so skipping it
        // costs no seam but avoids a zero-area triangle in the surface. A
        // ring's holes are spliced in so one polygon covers the lot.
        // Underside, normal down (triangulation flipped).
        foreach (self::withHoles(array_map([self::class, 'dropRepeats'], $outerLoops)) as $poly) {
            foreach (self::earClip($poly) as [$a, $bq, $c]) {
                $addTri($vi($poly[$a], 0.0), $vi($poly[$c], 0.0), $vi($poly[$bq], 0.0));
            }
        }

        // Interior floor, normal up.
        foreach (self::withHoles(array_map([self::class, 'dropRepeats'], $cavLoops)) as $poly) {
            foreach (self::earClip($poly) as [$a, $bq, $c]) {
                $addTri($vi($poly[$a], $B), $vi($poly[$bq], $B), $vi($poly[$c], $B));
            }
        }

        return ['vertices' => $vertices, 'tris' => $tris];
    }

    /**
     * Watertight mesh for a box with internal dividers.
     *
     * The body is built exactly like polyMesh: the outer surface is the
     * shape's pitch outline pulled in half a gap, and the cavity is the SAME
     * outline one wall further in. Both come from offsetInward, which is a
     * true erosion - that is what keeps the wall a full wall in a concave
     * corner of an L. (Working the cavity out cell by cell instead, as an
     * earlier version did, looks equivalent and is not: the union of the
     * inset cells leaves a square of cavity poking into every inside corner,
     * which is the wall that came out too thin right where an L bends.)
     *
     * A divider is then TAKEN OUT of that cavity: one wall thick - the
     * thickness from the panel, not half of it and not two of them - centred
     * on the line between the two cells, running the full pitch of the cell
     * it stands in so it meets the outer wall, or the next divider, without
     * a step. The cavity that is left is traced again, so each compartment
     * gets its own floor and its own inside wall, and the whole box is still
     * ONE piece of material: a slicer splitting the plate by parts keeps the
     * dividers in the box instead of scattering them over the bed.
     *
     * The outer box corners may be rounded. Internal divider corners remain
     * square and connect into the rounded outer wall.
     *
     * @param array $cfg dw, dd, dh, gap, wall, bottom, cols, rows (mm)
     * @param array $b   x, y, w, h, optional cells, vWalls, hWalls
     * @return array{vertices: array, tris: array}
     */
    public static function dividedMesh(array $cfg, array $b): array
    {
        $DH = $cfg['dh'];
        $G  = $cfg['gap'];
        $W  = $cfg['wall'];
        $B  = $cfg['bottom'];
        $R  = max(0.0, (float) ($cfg['radius'] ?? 0.0));

        $cols = (int) $cfg['cols'];
        $rows = (int) $cfg['rows'];

        $O  = max(0.0, (float) ($cfg['outer'] ?? 0.0));
        $cw = (($cfg['dw'] - 2 * $O) - ($cols - 1) * $G) / $cols;
        $ch = (($cfg['dd'] - 2 * $O) - ($rows - 1) * $G) / $rows;

        if ($B <= 0 || $B >= $DH) {
            throw new InvalidArgumentException('Bottom thickness must be greater than 0 and smaller than box height.');
        }
        if (2 * $W >= $cw || 2 * $W >= $ch) {
            throw new InvalidArgumentException('Wall thickness is too large for one of the boxes.');
        }

        $bx = (int) $b['x'];
        $by = (int) $b['y'];
        $bw = (int) $b['w'];
        $bh = (int) $b['h'];

        // Which cells the box owns, in the box's own column/row numbering as
        // the studio shows it (row 0 on top).
        $own = [];
        if (!empty($b['cells'])) {
            foreach ($b['cells'] as $c) {
                $own[((int) $c['x'] - $bx) . ',' . ((int) $c['y'] - $by)] = true;
            }
        } else {
            for ($r = 0; $r < $bh; $r++) {
                for ($c = 0; $c < $bw; $c++) { $own["$c,$r"] = true; }
            }
        }
        $has = static fn (int $c, int $r): bool => isset($own["$c,$r"]);

        // Pitch lines: a cell plus its share of the gap. The line between two
        // cells is the middle of the gap - the boundary the user clicks, and
        // the line a divider is centred on.
        $PX = static fn (int $i): float => $O + ($bx + $i) * ($cw + $G) - $G / 2;
        // Rows are flipped: the screen draws row 0 at the top, a slicer's Y
        // points up, so PY falls as the row number grows.
        $PY = static fn (int $j): float => $O + ($rows - $by - $j) * ($ch + $G) - $G / 2;

        // Pitch outline of the shape, then the two surfaces offset from it.
        $xs = [];
        for ($i = 0; $i <= $bw; $i++) { $xs[] = $PX($i); }
        $ys = [];
        for ($j = $bh; $j >= 0; $j--) { $ys[] = $PY($j); }

        $pitchGrid = [];
        for ($j = 0; $j < $bh; $j++) {
            for ($i = 0; $i < $bw; $i++) {
                $pitchGrid[$j][$i] = $has($i, $bh - 1 - $j);
            }
        }

        $outerLoops = [];
        $cavBase    = [];
        foreach (self::traceLoops($pitchGrid, $xs, $ys) as $pitch) {
            $outer = self::offsetInward($pitch, $G / 2);
            $cavity = self::offsetInward($pitch, $G / 2 + $W);

            // Round the outside AND the inside. The cavity is no longer a
            // square 90-degree corner when Corner Radius is enabled.
            [$outer, $cavity] = self::roundLoops($outer, $cavity, $R, $W);
            [$outer, $cavity] = self::cleanLoops($outer, $cavity);

            $outerLoops[] = $outer;
            $cavBase[]    = $cavity;
        }

        /*
         * Where a divider sits on its line.
         *
         * On a plain rectangle it is centred: both compartments come out the
         * same, which is the only fair answer when nothing else decides it.
         *
         * In an L or a T the line usually CONTINUES an outer wall - the notch
         * of the L is a wall standing on that very line - and there the
         * divider takes that wall's exact place instead, so the two are one
         * straight wall with one straight face. Centred, it stuck out half a
         * wall plus half a gap past the outer wall's face: the step at the
         * inside corner that never looked right.
         *
         * Which side: the box ends on the side the outer wall is on, so if
         * the box has cells only to the LEFT of the line somewhere along it,
         * the wall belongs to the left and the divider joins it there.
         *
         * @return array{0: float, 1: float} the two faces of the band
         */
        $place = static function (bool $lowOnly, bool $highOnly, float $line) use ($G, $W): array {
            if ($lowOnly && !$highOnly) { return [$line - $G / 2 - $W, $line - $G / 2]; }
            if ($highOnly && !$lowOnly) { return [$line + $G / 2, $line + $G / 2 + $W]; }
            return [$line - $W / 2, $line + $W / 2];
        };

        $bands  = [];
        $vWalls = $b['vWalls'] ?? [];
        $hWalls = $b['hWalls'] ?? [];
        $halfWalls = (array) ($b['halfWalls'] ?? []);
        for ($r = 0; $r < $bh; $r++) {
            for ($c = 0; $c < $bw - 1; $c++) {
                if (empty($vWalls[$r][$c]) || !$has($c, $r) || !$has($c + 1, $r)) { continue; }
                // The whole boundary decides, not this one cell, or a straight
                // divider would jog where the shape happens to change.
                $leftOnly = false; $rightOnly = false;
                for ($k = 0; $k < $bh; $k++) {
                    if ($has($c, $k) && !$has($c + 1, $k)) { $leftOnly = true; }
                    if (!$has($c, $k) && $has($c + 1, $k)) { $rightOnly = true; }
                }
                [$x0, $x1] = $place($leftOnly, $rightOnly, $PX($c + 1));
                // Half a wall past each end. A band that stops on the pitch
                // line stops at the CENTRE of the wall it runs into, which
                // leaves a quarter of a square unfilled on the far side of
                // every crossing - the notch a snake of dividers shows at
                // each of its corners. Anything past the cavity is ignored,
                // so the ends that meet the outer wall cost nothing.
                // Extend to the OUTER FACE of an intersecting horizontal
                // divider, but never beyond the box perimeter. This removes
                // the tiny notch at T/cross junctions while keeping outer
                // edges exact.
                // Keep the divider on its own pitch span here. A later
                // junction pass extends only to the actual face of a
                // perpendicular INTERNAL divider.
                $bands[] = [$x0, $PY($r + 1), $x1, $PY($r)];
            }
        }
        for ($r = 0; $r < $bh - 1; $r++) {
            for ($c = 0; $c < $bw; $c++) {
                if (empty($hWalls[$r][$c]) || !$has($c, $r) || !$has($c, $r + 1)) { continue; }
                // Rows are flipped, so the row ABOVE on screen is the higher Y
                // on the bed - it is the "high" side here.
                $upOnly = false; $downOnly = false;
                for ($k = 0; $k < $bw; $k++) {
                    if ($has($k, $r) && !$has($k, $r + 1)) { $upOnly = true; }
                    if (!$has($k, $r) && $has($k, $r + 1)) { $downOnly = true; }
                }
                [$y0, $y1] = $place($downOnly, $upOnly, $PY($r + 1));
                // Extend to the OUTER FACE of an intersecting vertical
                // divider, but never beyond the box perimeter.
                // Keep the divider on its own pitch span here. A later
                // junction pass extends only to the actual face of a
                // perpendicular INTERNAL divider.
                $bands[] = [$PX($c), $y0, $PX($c + 1), $y1];
            }
        }

        // Fine-grid dividers are independent half-cell segments. That keeps
        // the drawing controls and the exported wall one-to-one.
        foreach ((array) ($halfWalls['v'] ?? []) as $line) {
            if (!is_array($line) || count($line) !== 2) { continue; }
            [$x2, $y2] = array_map('intval', $line);
            $x = $O + ($bx + $x2 / 2) * ($cw + $G) - $G / 2;
            $y0 = $O + ($rows - $by - ($y2 + 1) / 2) * ($ch + $G) - $G / 2;
            $y1 = $O + ($rows - $by - $y2 / 2) * ($ch + $G) - $G / 2;
            $bands[] = [$x - $W / 2, $y0, $x + $W / 2, $y1];
        }
        foreach ((array) ($halfWalls['h'] ?? []) as $line) {
            if (!is_array($line) || count($line) !== 2) { continue; }
            [$x2, $y2] = array_map('intval', $line);
            $x0 = $O + ($bx + $x2 / 2) * ($cw + $G) - $G / 2;
            $x1 = $O + ($bx + ($x2 + 1) / 2) * ($cw + $G) - $G / 2;
            $y = $O + ($rows - $by - $y2 / 2) * ($ch + $G) - $G / 2;
            $bands[] = [$x0, $y - $W / 2, $x1, $y + $W / 2];
        }

        /*
         * Close internal perpendicular junctions from the actual generated
         * divider geometry, not from the wall-array indices.
         *
         * This is important for L/T layouts: the endpoint of one divider
         * must reach the OUTER FACE of the perpendicular divider, but must
         * never be pushed farther than that. No arbitrary overlap is used.
         */
        $junctionBands = [];
        $bandCount = count($bands);
        for ($a = 0; $a < $bandCount; $a++) {
            [$ax0, $ay0, $ax1, $ay1] = $bands[$a];
            $vertical = abs(($ax1 - $ax0) - $W) < 1e-6;
            $horizontal = abs(($ay1 - $ay0) - $W) < 1e-6;

            if ($vertical) {
                foreach ($bands as $bb) {
                    [$bx0, $by0, $bx1, $by1] = $bb;
                    if (abs(($by1 - $by0) - $W) >= 1e-6) { continue; }

                    // Horizontal divider must actually cross the vertical
                    // divider's X span.
                    if ($bx1 <= $ax0 || $bx0 >= $ax1) { continue; }

                    if ($by0 <= $ay0 + 1e-6 && $by1 >= $ay0 - 1e-6) {
                        $ay0 = min($ay0, $by0);
                    }
                    if ($by0 <= $ay1 + 1e-6 && $by1 >= $ay1 - 1e-6) {
                        $ay1 = max($ay1, $by1);
                    }
                }
            } elseif ($horizontal) {
                foreach ($bands as $bb) {
                    [$bx0, $by0, $bx1, $by1] = $bb;
                    if (abs(($bx1 - $bx0) - $W) >= 1e-6) { continue; }

                    if ($by1 <= $ay0 || $by0 >= $ay1) { continue; }

                    if ($bx0 <= $ax0 + 1e-6 && $bx1 >= $ax0 - 1e-6) {
                        $ax0 = min($ax0, $bx0);
                    }
                    if ($bx0 <= $ax1 + 1e-6 && $bx1 >= $ax1 - 1e-6) {
                        $ax1 = max($ax1, $bx1);
                    }
                }
            }
            $junctionBands[] = [$ax0, $ay0, $ax1, $ay1];
        }
        $bands = $junctionBands;

        /*
         * Dividers use the same rounded outer and inner corner treatment
         * as an undivided box. Divider bands are clipped by the box
         * footprint so they cannot protrude past a rounded outer corner.
         */
        if (!$bands) {
            unset($b['vWalls'], $b['hWalls'], $b['midWalls'], $b['halfWalls']);
            return isset($b['cells'])
                ? self::polyMesh($cfg, $b)
                : self::boxMesh($cfg, $b);
        }

        /*
         * Rounded corners and the lattice below do not mix.
         *
         * The lattice cuts everything on one grid of X and Y lines, and it
         * has to hold a line for every point of every outline. An arc has a
         * point every few degrees, so a rounded rectangle comes out of it as
         * a staircase - the faceted edge a slicer shows - and a rounded free
         * shape, with a fillet at each of its many corners, multiplies the
         * grid until the mesh no longer fits in memory.
         *
         * So a rounded box, rectangle or free shape alike, keeps its smooth
         * shell and takes its dividers as separate volumes. A square box
         * goes the lattice way and comes out as ONE welded solid, which is
         * the better answer where it is available: separate volumes print as
         * a union, but "split to parts" scatters them across the plate and a
         * repair pass may decide the overlapping faces are a fault and shave
         * them. Most divided boxes are square, so most get the solid.
         */
        if ($R > 0.0) {
            return self::smoothDividedMesh($cfg, $b, $bands, $cavBase);
        }

        /*
         * IMPORTANT: the box and all divider walls are built as ONE closed
         * shell below. Do not append separate divider prisms to a finished
         * box. The separate-solid approach creates overlapping faces and
         * small steps/gaps at internal corners in slicers.
         */
        // Everything is cut on ONE lattice, built from every line that
        // matters: the outside, the cavity outline, and the two faces of
        // every divider. The coordinates are the real ones - the lattice
        // decides which squares are material, never where an edge lies - and
        // because both surfaces and every cap come off the same squares, the
        // solid closes by construction instead of by luck. (Splicing the
        // compartments into the top face as holes and triangulating that
        // polygon is the obvious alternative; with more than one hole it
        // tears, which is how a divided box came out open at the top.)
        $lx = [];
        $ly = [];
        foreach (array_merge($outerLoops, $cavBase) as $loop) {
            foreach ($loop as $p) { $lx[] = $p[0]; $ly[] = $p[1]; }
        }
        foreach ($bands as [$x0, $y0, $x1, $y1]) {
            $lx[] = $x0; $lx[] = $x1;
            $ly[] = $y0; $ly[] = $y1;
        }
        $uniq = static function (array $v): array {
            sort($v);
            $out = [];
            foreach ($v as $x) {
                if (!$out || abs($x - end($out)) > 1e-7) { $out[] = $x; }
            }
            return $out;
        };
        $lx = $uniq($lx);
        $ly = $uniq($ly);

        // Even-odd over every loop of a set: inside the outline but inside a
        // hole as well counts as outside, which is what a ring-shaped box
        // needs.
        $inside = static function (array $loops, float $x, float $y): bool {
            $in = false;
            foreach ($loops as $loop) {
                if (self::pointInPoly([$x, $y], $loop)) { $in = !$in; }
            }
            return $in;
        };

        $nx = count($lx) - 1;
        $ny = count($ly) - 1;
        $foot = [];   // is there any material here at all?
        $open = [];   // is this square a compartment (no material above the floor)?
        for ($j = 0; $j < $ny; $j++) {
            $my = ($ly[$j] + $ly[$j + 1]) / 2;
            for ($i = 0; $i < $nx; $i++) {
                $mx = ($lx[$i] + $lx[$i + 1]) / 2;
                $foot[$j][$i] = $inside($outerLoops, $mx, $my);
                $hollow = $foot[$j][$i] && $inside($cavBase, $mx, $my);

                if ($hollow) {
                    foreach ($bands as [$x0, $y0, $x1, $y1]) {
                        if ($mx > $x0 && $mx < $x1 && $my > $y0 && $my < $y1) {
                            // A divider cell must fit completely inside the
                            // rounded outer footprint. Midpoint-only testing
                            // allows a rectangular divider cell to protrude
                            // through a rounded corner.
                            $cornersInside =
                                $inside($outerLoops, $lx[$i] + 1e-7, $ly[$j] + 1e-7) &&
                                $inside($outerLoops, $lx[$i + 1] - 1e-7, $ly[$j] + 1e-7) &&
                                $inside($outerLoops, $lx[$i] + 1e-7, $ly[$j + 1] - 1e-7) &&
                                $inside($outerLoops, $lx[$i + 1] - 1e-7, $ly[$j + 1] - 1e-7);
                            if ($cornersInside) {
                                $hollow = false;
                            }
                            break;
                        }
                    }
                }
                $open[$j][$i] = $hollow;
            }
        }

        $isFoot = static fn (int $i, int $j): bool =>
            $i >= 0 && $i < $nx && $j >= 0 && $j < $ny && !empty($foot[$j][$i]);
        $isOpen = static fn (int $i, int $j): bool =>
            $i >= 0 && $i < $nx && $j >= 0 && $j < $ny && !empty($open[$j][$i]);

        $vertices = [];
        $tris     = [];
        $vmap     = [];
        $addVertex = static function (array $v) use (&$vertices, &$vmap): int {
            $key = sprintf(
                '%.' . self::WELD_DECIMALS . 'f|%.' . self::WELD_DECIMALS . 'f|%.' . self::WELD_DECIMALS . 'f',
                $v[0], $v[1], $v[2]
            );
            if (!isset($vmap[$key])) {
                $vertices[] = $v;
                $vmap[$key] = count($vertices) - 1;
            }
            return $vmap[$key];
        };
        $addTri = static function (int $a, int $bq, int $c) use (&$tris): void {
            if ($a !== $bq && $bq !== $c && $c !== $a) {
                $tris[] = [$a, $bq, $c];
            }
        };
        // A quad, given its four corners in order round the face. The winding
        // is what tells a slicer which side is the outside, so every caller
        // below hands the corners over in the order that puts the normal
        // where the material is not.
        $quad = static function (array $a, array $bq, array $c, array $d) use ($addVertex, $addTri): void {
            $ia = $addVertex($a); $ib = $addVertex($bq);
            $ic = $addVertex($c); $id = $addVertex($d);
            $addTri($ia, $ib, $ic);
            $addTri($ia, $ic, $id);
        };

        for ($j = 0; $j < $ny; $j++) {
            $y0 = $ly[$j]; $y1 = $ly[$j + 1];
            for ($i = 0; $i < $nx; $i++) {
                if (!$isFoot($i, $j)) { continue; }
                $x0 = $lx[$i]; $x1 = $lx[$i + 1];

                // Underside, facing down.
                $quad([$x0, $y0, 0.0], [$x0, $y1, 0.0], [$x1, $y1, 0.0], [$x1, $y0, 0.0]);

                // Facing up: a compartment shows its floor, everything else
                // is the rim - the top of the outer wall and of every divider.
                $z = $isOpen($i, $j) ? $B : $DH;
                $quad([$x0, $y0, $z], [$x1, $y0, $z], [$x1, $y1, $z], [$x0, $y1, $z]);

                // The outside: from the bed to the top, wherever the box ends.
                if (!$isFoot($i + 1, $j)) {
                    $quad([$x1, $y0, 0.0], [$x1, $y1, 0.0], [$x1, $y1, $DH], [$x1, $y0, $DH]);
                }
                if (!$isFoot($i - 1, $j)) {
                    $quad([$x0, $y1, 0.0], [$x0, $y0, 0.0], [$x0, $y0, $DH], [$x0, $y1, $DH]);
                }
                if (!$isFoot($i, $j + 1)) {
                    $quad([$x1, $y1, 0.0], [$x0, $y1, 0.0], [$x0, $y1, $DH], [$x1, $y1, $DH]);
                }
                if (!$isFoot($i, $j - 1)) {
                    $quad([$x0, $y0, 0.0], [$x1, $y0, 0.0], [$x1, $y0, $DH], [$x0, $y0, $DH]);
                }

                // The inside: from the floor up, wherever a compartment ends.
                // These are the walls the user sees - the sides of the outer
                // wall and both sides of every divider, all at once. Same
                // corner order as the outside faces above: a normal points
                // away from the material either way, which for a cavity face
                // means into the cavity.
                if ($isOpen($i, $j)) { continue; }
                if ($isOpen($i + 1, $j)) {
                    $quad([$x1, $y0, $B], [$x1, $y1, $B], [$x1, $y1, $DH], [$x1, $y0, $DH]);
                }
                if ($isOpen($i - 1, $j)) {
                    $quad([$x0, $y1, $B], [$x0, $y0, $B], [$x0, $y0, $DH], [$x0, $y1, $DH]);
                }
                if ($isOpen($i, $j + 1)) {
                    $quad([$x1, $y1, $B], [$x0, $y1, $B], [$x0, $y1, $DH], [$x1, $y1, $DH]);
                }
                if ($isOpen($i, $j - 1)) {
                    $quad([$x0, $y0, $B], [$x1, $y0, $B], [$x1, $y0, $DH], [$x0, $y0, $DH]);
                }
            }
        }

        return ['vertices' => $vertices, 'tris' => $tris];
    }

    /**
     * A box whose corners are rounded, plus its dividers.
     *
     * The lattice builder cuts everything on one grid of X and Y lines, and
     * an arc has a point every few degrees - each of which would become a
     * line of its own. On a rectangle that turns a smooth corner into a
     * staircase; on a free shape with several corners the grid squares
     * multiply until the mesh runs out of memory. So a rounded box keeps
     * its finished shell and each divider is added as its own closed
     * volume standing on the floor.
     *
     * The volumes overlap the shell by a hair, which a slicer unions, and
     * the outside contour stays the arc it was drawn as. The price is that
     * the model is several volumes rather than one solid - "split to parts"
     * in a slicer would separate them - which is why square boxes, the
     * common case, still go the welded way.
     *
     * @param array $cavLoops the cavity outline the dividers must stop at
     */
    private static function smoothDividedMesh(array $cfg, array $b, array $bands, array $cavLoops): array
    {
        $W  = (float) $cfg['wall'];
        $B  = (float) $cfg['bottom'];
        $DH = (float) $cfg['dh'];

        $base = $b;
        unset($base['vWalls'], $base['hWalls'], $base['midWalls'], $base['halfWalls']);
        $mesh = isset($base['cells']) ? self::polyMesh($cfg, $base) : self::boxMesh($cfg, $base);

        // A hair of overlap: enough for a slicer to read the divider and the
        // side wall as one solid, too little to move the outside face.
        $join = min(0.04, max(0.005, $W / 20));

        foreach ($bands as [$x0b, $y0b, $x1b, $y1b]) {
            /*
             * Where this divider has to stop, read off the cavity itself
             * rather than off a bounding rectangle. An L or a cross has no
             * single rectangle to clip against, and its cavity ends in a
             * different place along every line - so the line the divider
             * runs on is intersected with the cavity outline and the
             * divider is cut to the stretch it lies in, one wall deep.
             */
            $vertical = abs(($x1b - $x0b) - $W) < 1e-6;
            if ($vertical) {
                $span = self::scanSpan($cavLoops, ($x0b + $x1b) / 2, ($y0b + $y1b) / 2, true);
                if ($span !== null) {
                    $y0b = max($y0b, $span[0] - $join);
                    $y1b = min($y1b, $span[1] + $join);
                }
            } else {
                $span = self::scanSpan($cavLoops, ($y0b + $y1b) / 2, ($x0b + $x1b) / 2, false);
                if ($span !== null) {
                    $x0b = max($x0b, $span[0] - $join);
                    $x1b = min($x1b, $span[1] + $join);
                }
            }
            if ($x1b - $x0b <= 1e-6 || $y1b - $y0b <= 1e-6) {
                continue;
            }
            self::appendMesh($mesh, self::rectPrismMesh($x0b, $y0b, $x1b, $y1b, $B, $DH));
        }

        return $mesh;
    }

    /**
     * The stretch of a straight scan line that lies inside a set of loops.
     *
     * $fixed is the line's constant coordinate - X when the line runs up the
     * bed, Y when it runs across - and $at a point on it known to be inside.
     * Returns the two boundary crossings around that point, or null when the
     * line misses the region.
     *
     * @return array{0: float, 1: float}|null
     */
    private static function scanSpan(array $loops, float $fixed, float $at, bool $vertical): ?array
    {
        $hits = [];
        foreach ($loops as $loop) {
            $n = count($loop);
            for ($i = 0; $i < $n; $i++) {
                $p = $loop[$i];
                $q = $loop[($i + 1) % $n];
                $pc = $vertical ? $p[0] : $p[1];
                $qc = $vertical ? $q[0] : $q[1];
                // Half-open: an edge counts when it starts on one side of
                // the line and ends on the other, so a vertex sitting
                // exactly on the line is counted once, not twice.
                if (($pc <= $fixed) === ($qc <= $fixed)) {
                    continue;
                }
                $t = ($fixed - $pc) / ($qc - $pc);
                $hits[] = $vertical
                    ? $p[1] + $t * ($q[1] - $p[1])
                    : $p[0] + $t * ($q[0] - $p[0]);
            }
        }
        if (count($hits) < 2) {
            return null;
        }
        sort($hits);
        $lo = null;
        $hi = null;
        foreach ($hits as $h) {
            if ($h <= $at) { $lo = $h; } elseif ($hi === null) { $hi = $h; }
        }
        return ($lo === null || $hi === null) ? null : [$lo, $hi];
    }
    /** @return array{vertices: array, tris: array} */
    private static function rectPrismMesh(float $x0, float $y0, float $x1, float $y1, float $z0, float $z1): array
    {
        $v = [
            [$x0, $y0, $z0], [$x1, $y0, $z0], [$x1, $y1, $z0], [$x0, $y1, $z0],
            [$x0, $y0, $z1], [$x1, $y0, $z1], [$x1, $y1, $z1], [$x0, $y1, $z1],
        ];
        return ['vertices' => $v, 'tris' => [
            [0, 2, 1], [0, 3, 2], // bottom
            [4, 5, 6], [4, 6, 7], // top
            [0, 1, 5], [0, 5, 4], // south
            [1, 2, 6], [1, 6, 5], // east
            [2, 3, 7], [2, 7, 6], // north
            [3, 0, 4], [3, 4, 7], // west
        ]];
    }

    /** Append a separate closed component without welding its vertices. */
    private static function appendMesh(array &$into, array $part): void
    {
        $offset = count($into['vertices']);
        foreach ($part['vertices'] as $vertex) {
            $into['vertices'][] = $vertex;
        }
        foreach ($part['tris'] as [$a, $b, $c]) {
            $into['tris'][] = [$a + $offset, $b + $offset, $c + $offset];
        }
    }

    /** Does this box carry any divider at all? */
    public static function hasDividers(array $b): bool
    {
        foreach (['vWalls', 'hWalls'] as $key) {
            foreach ((array) ($b[$key] ?? []) as $row) {
                foreach ((array) $row as $on) {
                    if ($on) { return true; }
                }
            }
        }
        return !empty($b['midWalls']['h']) || !empty($b['midWalls']['v'])
            || !empty($b['halfWalls']['h']) || !empty($b['halfWalls']['v']);
    }

    /**
     * Inward offset of a CCW rectilinear loop by $d - which, for right-angle
     * corners, is exactly its erosion. Throws when the shape is too tight
     * for the wall (an edge would flip direction).
     */
    private static function offsetInward(array $pts, float $d): array
    {
        $n = count($pts);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $p = $pts[($i + $n - 1) % $n];
            $q = $pts[$i];
            $s = $pts[($i + 1) % $n];
            $v1 = [$q[0] - $p[0], $q[1] - $p[1]];
            $v2 = [$s[0] - $q[0], $s[1] - $q[1]];
            $l1 = hypot($v1[0], $v1[1]);
            $l2 = hypot($v2[0], $v2[1]);
            // Interior is left of travel on a CCW loop; the left normal of
            // (x, y) is (-y, x).
            $n1 = [-$v1[1] / $l1, $v1[0] / $l1];
            $n2 = [-$v2[1] / $l2, $v2[0] / $l2];
            /*
             * The mitre, divided by (1 + n1.n2).
             *
             * Without that divisor the formula is only right at a square
             * corner, where the two normals are perpendicular and the sum
             * happens to land at distance d from both edges. On a point
             * lying along a straight edge the two normals are the same, the
             * sum is twice the normal, and the point is pushed twice as far
             * as the wall is thick. One point of an otherwise straight wall
             * then stands 0.2 mm out on the outside surface and 2.2 mm out
             * in the cavity, and the wall tapers to it from the real corner
             * at each end: the wedge lying on top of a wall in a slicer,
             * and the gap between two boxes closing up along it.
             *
             * With the divisor the offset is exact at every angle, so a
             * point that slips through the collinear filter costs nothing.
             */
            $mitre = 1.0 + $n1[0] * $n2[0] + $n1[1] * $n2[1];
            if ($mitre < 1e-9) {
                // The loop doubles back on itself; there is no inside here.
                throw new InvalidArgumentException('Wall thickness is too large for one of the boxes.');
            }
            $out[] = [
                $q[0] + ($n1[0] + $n2[0]) * $d / $mitre,
                $q[1] + ($n1[1] + $n2[1]) * $d / $mitre,
            ];
        }
        // Every edge must keep its direction, or the wall ate the shape.
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $dot = ($pts[$j][0] - $pts[$i][0]) * ($out[$j][0] - $out[$i][0])
                 + ($pts[$j][1] - $pts[$i][1]) * ($out[$j][1] - $out[$i][1]);
            if ($dot < 1e-9) {
                throw new InvalidArgumentException('Wall thickness is too large for one of the boxes.');
            }
        }
        return $out;
    }

    /**
     * Rounds two corner-matched loops in lockstep: every convex corner emits
     * the same number of points on both (an arc, or that many repeats of the
     * corner when its radius clamps to nothing). Concave corners stay sharp:
     * rounding them adds material into an L/T notch and shows up as a bulge
     * in the wall. Repeated points keep both loops corner-matched so the rim
     * can still be zipped into a watertight surface.
     *
     * @return array{0: array, 1: array}
     */
    private static function roundLoops(array $outer, array $inner, float $R, float $W): array
    {
        $n = count($outer);
        $a = [];
        $bLoop = [];
        for ($i = 0; $i < $n; $i++) {
            $p = $outer[($i + $n - 1) % $n];
            $q = $outer[$i];
            $s = $outer[($i + 1) % $n];
            $turn    = self::cornerOffset($p, $q, $s);
            $convex  = $turn > self::FLAT_MM;
            $concave = $turn < -self::FLAT_MM;
            if (!$convex && !$concave) {
                $a[] = $q;
                $bLoop[] = $inner[$i];
                continue;
            }

            // Only exposed (convex) corners are filleted. A concave fillet
            // fills the notch of a free shape and creates the reported
            // balloon-like wall. The matching inner convex arc is smaller
            // by one wall thickness, keeping that wall uniform.
            $rOut = $convex ? $R : 0.0;
            $rIn  = ($convex && $R > 1e-6) ? max(0.0, $R - $W) : 0.0;

            foreach ([[$outer, $rOut, &$a], [$inner, $rIn, &$bLoop]] as [$loop, $r, &$dst]) {
                $p2 = $loop[($i + $n - 1) % $n];
                $q2 = $loop[$i];
                $s2 = $loop[($i + 1) % $n];
                $v1 = [$q2[0] - $p2[0], $q2[1] - $p2[1]];
                $v2 = [$s2[0] - $q2[0], $s2[1] - $q2[1]];
                $l1 = hypot($v1[0], $v1[1]);
                $l2 = hypot($v2[0], $v2[1]);
                $rr = max(0.0, min($r, $l1 / 2, $l2 / 2));
                if ($rr <= 1e-4 || $l1 < 1e-9 || $l2 < 1e-9) {
                    for ($k = 0; $k <= self::SEGMENTS; $k++) {
                        $dst[] = $q2;
                    }
                    continue;
                }
                $u1 = [$v1[0] / $l1, $v1[1] / $l1];
                $start = [$q2[0] - $u1[0] * $rr, $q2[1] - $u1[1] * $rr];
                $sgn = 1.0;
                $ctr = [$start[0] - $u1[1] * $rr * $sgn, $start[1] + $u1[0] * $rr * $sgn];
                $a0 = atan2($start[1] - $ctr[1], $start[0] - $ctr[0]);
                for ($k = 0; $k <= self::SEGMENTS; $k++) {
                    $ang = $a0 + $sgn * (M_PI / 2) * ($k / self::SEGMENTS);
                    $dst[] = [$ctr[0] + $rr * cos($ang), $ctr[1] + $rr * sin($ang)];
                }
            }
            unset($dst);
        }
        return [$a, $bLoop];
    }

    /**
     * Boundary of a region of compressed-grid cells as ONE CCW corner loop
     * (collinear points merged). Throws when the region is empty or falls
     * apart into several loops - a shape like that cannot be built.
     */
    /**
     * Every boundary loop of the region: the outside first, then one per
     * enclosed hole. Material always stays on the left of travel, so the
     * outside comes out counter-clockwise and a hole clockwise - which is
     * what the offsets, the rim and the triangulation all rely on.
     */
    private static function traceLoops(array $grid, array $xs, array $ys): array
    {
        $nx = count($xs) - 1;
        $ny = count($ys) - 1;
        $in = static fn (int $i, int $j): bool =>
            $i >= 0 && $i < $nx && $j >= 0 && $j < $ny && !empty($grid[$j][$i]);

        $key   = static fn (float $x, float $y): string => sprintf('%.5f|%.5f', $x, $y);
        $edges = [];
        /*
         * The key is rounded to five decimals so that the same corner reached
         * from two cells lands in the same bucket. It must never be read back
         * as a coordinate: 250/7 gives 35.714285..., and a point moved by the
         * rounding is no longer collinear with the edge it sits on. It then
         * survives as a corner in the middle of a straight wall, and a corner
         * gets a fillet - which is how a 2 mm wall ended up 0.96 mm thin.
         */
        $exact = [];
        for ($j = 0; $j < $ny; $j++) {
            for ($i = 0; $i < $nx; $i++) {
                if (!$in($i, $j)) {
                    continue;
                }
                $a = $xs[$i]; $c = $xs[$i + 1];
                $bY = $ys[$j]; $d = $ys[$j + 1];
                if (!$in($i, $j - 1)) { $edges[$key($a, $bY)][] = [$c, $bY]; $exact[$key($a, $bY)] = [$a, $bY]; }
                if (!$in($i + 1, $j)) { $edges[$key($c, $bY)][] = [$c, $d];  $exact[$key($c, $bY)] = [$c, $bY]; }
                if (!$in($i, $j + 1)) { $edges[$key($c, $d)][]  = [$a, $d];  $exact[$key($c, $d)]  = [$c, $d]; }
                if (!$in($i - 1, $j)) { $edges[$key($a, $d)][]  = [$a, $bY]; $exact[$key($a, $d)]  = [$a, $d]; }
            }
        }
        if (!$edges) {
            throw new InvalidArgumentException('Wall thickness is too large for one of the boxes.');
        }

        $loops = [];
        $guard = 4 * $nx * $ny + 64;
        while ($edges && $guard-- > 0) {
            $startKey = array_key_first($edges);
            [$cx, $cy] = $exact[$startKey];
            $loop = [];
            $step = 4 * $nx * $ny + 64;
            $prev = null;
            do {
                $k = $key($cx, $cy);
                if (empty($edges[$k])) {
                    break;
                }

                // A shape can touch itself corner to corner, and then this
                // vertex has two ways out. Take the sharpest right turn: that
                // walks the two parts as separate outlines, which each then
                // shrink by half a gap - so the corner ends up with a gap
                // between the parts instead of a joint holding on nothing.
                $pick = 0;
                if (count($edges[$k]) > 1 && $prev !== null) {
                    $inAng = atan2($cy - $prev[1], $cx - $prev[0]);
                    $best  = INF;
                    foreach ($edges[$k] as $idx => [$tx, $ty]) {
                        $turn = $inAng - atan2($ty - $cy, $tx - $cx);
                        while ($turn <= -M_PI) { $turn += 2 * M_PI; }
                        while ($turn > M_PI) { $turn -= 2 * M_PI; }
                        if ($turn < $best) { $best = $turn; $pick = $idx; }
                    }
                }

                [$nx2, $ny2] = $edges[$k][$pick];
                array_splice($edges[$k], $pick, 1);
                if (!$edges[$k]) {
                    unset($edges[$k]);
                }
                $loop[] = [$cx, $cy];
                $prev = [$cx, $cy];
                $cx = $nx2;
                $cy = $ny2;
            } while ($key($cx, $cy) !== $startKey && $step-- > 0);

            if (count($loop) < 4) {
                continue;
            }

            // Collinear points carry no shape and only make work later.
            $out = [];
            $cnt = count($loop);
            for ($i = 0; $i < $cnt; $i++) {
                $p = $loop[($i + $cnt - 1) % $cnt];
                $q = $loop[$i];
                $r = $loop[($i + 1) % $cnt];
                if (abs(self::cornerOffset($p, $q, $r)) > self::FLAT_MM) {
                    $out[] = $q;
                }
            }
            if (count($out) >= 4) {
                $loops[] = $out;
            }
        }

        if (!$loops) {
            throw new InvalidArgumentException('Wall thickness is too large for one of the boxes.');
        }

        // Largest enclosed area first - that is the outside.
        usort($loops, static function (array $a, array $b): int {
            $area = static function (array $l): float {
                $s = 0.0;
                $n = count($l);
                for ($i = 0; $i < $n; $i++) {
                    $j = ($i + 1) % $n;
                    $s += $l[$i][0] * $l[$j][1] - $l[$j][0] * $l[$i][1];
                }
                return abs($s) / 2;
            };
            return $area($b) <=> $area($a);
        });

        return $loops;
    }

    /**
     * Splices hole loops into their outer loop through bridge edges, giving
     * one simple polygon the ear clipper can chew through. With no holes the
     * outer loop is handed straight back.
     */
    private static function withHoles(array $loops): array
    {
        $area = static function (array $l): float {
            $s = 0.0;
            $n = count($l);
            for ($i = 0; $i < $n; $i++) {
                $j = ($i + 1) % $n;
                $s += $l[$i][0] * $l[$j][1] - $l[$j][0] * $l[$i][1];
            }
            return $s / 2;
        };

        // Orientation tells the two apart: material-on-the-left means a
        // filled part runs counter-clockwise and a hole clockwise. A shape
        // that touches itself at a corner has SEVERAL filled parts, and
        // treating the extra ones as holes punched the floor full of them.
        $parts = [];
        $holes = [];
        foreach ($loops as $l) {
            if ($area($l) > 0) {
                $parts[] = $l;
            } else {
                $holes[] = $l;
            }
        }
        if (!$parts) {
            return [];
        }

        foreach ($holes as $hole) {
            $target = 0;
            foreach ($parts as $i => $p) {
                if (self::pointInPoly($hole[0], $p)) {
                    $target = $i;
                    break;
                }
            }
            $parts[$target] = self::bridgeHole($parts[$target], $hole);
        }

        return $parts;
    }

    /** Ray-cast point-in-polygon, used to match a hole to its part. */
    private static function pointInPoly(array $pt, array $poly): bool
    {
        $in = false;
        $n = count($poly);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $a = $poly[$i];
            $b = $poly[$j];
            if (($a[1] > $pt[1]) !== ($b[1] > $pt[1])
                && $pt[0] < ($b[0] - $a[0]) * ($pt[1] - $a[1]) / ($b[1] - $a[1]) + $a[0]) {
                $in = !$in;
            }
        }
        return $in;
    }

    /**
     * Splices one clockwise hole into a CCW outer loop: the hole's rightmost
     * point is joined to the nearest outer point whose connecting segment
     * crosses nothing, and the hole is walked into the outline there.
     */
    private static function bridgeHole(array $outer, array $hole): array
    {
        // Try every point of the hole, not just its rightmost one: with an
        // awkward outline the obvious bridge can be blocked while another is
        // perfectly clear. Dropping the hole instead (the old fallback) left
        // its walls attached to nothing, which is how a ring could come out
        // "not watertight".
        $hOrder = array_keys($hole);
        usort($hOrder, static fn ($a, $b) => $hole[$b][0] <=> $hole[$a][0]);

        $fallback = null;
        foreach ($hOrder as $hi) {
            $h = $hole[$hi];
            $order = array_keys($outer);
            usort($order, static fn ($a, $b) =>
                (($outer[$a][0] - $h[0]) ** 2 + ($outer[$a][1] - $h[1]) ** 2)
                <=> (($outer[$b][0] - $h[0]) ** 2 + ($outer[$b][1] - $h[1]) ** 2));

            foreach ($order as $oi) {
                $clear = self::segClear($h, $outer[$oi], [$outer, $hole]);
                if (!$clear && $fallback === null) {
                    $fallback = [$hi, $oi];
                }
                if (!$clear) {
                    continue;
                }
                return self::spliceHole($outer, $hole, $hi, $oi);
            }
        }

        // Nothing was provably clear; the nearest pair still keeps the hole
        // in the outline, which matters more than a tidy bridge.
        if ($fallback !== null) {
            return self::spliceHole($outer, $hole, $fallback[0], $fallback[1]);
        }

        return $outer;
    }

    /** Walks the hole into the outer loop at the chosen bridge. */
    private static function spliceHole(array $outer, array $hole, int $hi, int $oi): array
    {
        $merged = [];
        for ($k = 0; $k <= $oi; $k++) {
            $merged[] = $outer[$k];
        }
        $cnt = count($hole);
        for ($k = 0; $k <= $cnt; $k++) {
            $merged[] = $hole[($hi + $k) % $cnt];
        }
        for ($k = $oi; $k < count($outer); $k++) {
            $merged[] = $outer[$k];
        }
        return $merged;
    }

    /** True when segment a-b crosses no edge of any given ring. */
    private static function segClear(array $a, array $b, array $rings): bool
    {
        $same = static fn (array $p, array $q): bool =>
            abs($p[0] - $q[0]) < 1e-7 && abs($p[1] - $q[1]) < 1e-7;
        $orient = static fn (array $p, array $q, array $r): float =>
            ($q[0] - $p[0]) * ($r[1] - $p[1]) - ($q[1] - $p[1]) * ($r[0] - $p[0]);

        foreach ($rings as $ring) {
            $n = count($ring);
            for ($i = 0; $i < $n; $i++) {
                $p = $ring[$i];
                $q = $ring[($i + 1) % $n];
                if ($same($p, $a) || $same($q, $a) || $same($p, $b) || $same($q, $b)) {
                    continue;
                }
                $o1 = $orient($a, $b, $p);
                $o2 = $orient($a, $b, $q);
                $o3 = $orient($p, $q, $a);
                $o4 = $orient($p, $q, $b);
                if ((($o1 > 1e-9 && $o2 < -1e-9) || ($o1 < -1e-9 && $o2 > 1e-9))
                    && (($o3 > 1e-9 && $o4 < -1e-9) || ($o3 < -1e-9 && $o4 > 1e-9))) {
                    return false;
                }
            }
        }
        return true;
    }

    private static function traceLoop(array $grid, array $xs, array $ys): array
    {
        $nx = count($xs) - 1;
        $ny = count($ys) - 1;
        $in = static fn (int $i, int $j): bool =>
            $i >= 0 && $i < $nx && $j >= 0 && $j < $ny && !empty($grid[$j][$i]);

        $key   = static fn (float $x, float $y): string => sprintf('%.5f|%.5f', $x, $y);
        $edges = [];
        /*
         * The key is rounded to five decimals so that the same corner reached
         * from two cells lands in the same bucket. It must never be read back
         * as a coordinate: 250/7 gives 35.714285..., and a point moved by the
         * rounding is no longer collinear with the edge it sits on. It then
         * survives as a corner in the middle of a straight wall, and a corner
         * gets a fillet - which is how a 2 mm wall ended up 0.96 mm thin.
         */
        $exact = [];
        for ($j = 0; $j < $ny; $j++) {
            for ($i = 0; $i < $nx; $i++) {
                if (!$in($i, $j)) {
                    continue;
                }
                $a = $xs[$i]; $c = $xs[$i + 1];
                $bY = $ys[$j]; $d = $ys[$j + 1];
                // Directed so the interior stays on the left -> CCW loop.
                if (!$in($i, $j - 1)) { $edges[$key($a, $bY)][] = [$c, $bY]; $exact[$key($a, $bY)] = [$a, $bY]; }
                if (!$in($i + 1, $j)) { $edges[$key($c, $bY)][] = [$c, $d];  $exact[$key($c, $bY)] = [$c, $bY]; }
                if (!$in($i, $j + 1)) { $edges[$key($c, $d)][]  = [$a, $d];  $exact[$key($c, $d)]  = [$c, $d]; }
                if (!$in($i - 1, $j)) { $edges[$key($a, $d)][]  = [$a, $bY]; $exact[$key($a, $d)]  = [$a, $d]; }
            }
        }
        if (!$edges) {
            throw new InvalidArgumentException('Wall thickness is too large for one of the boxes.');
        }

        $startKey = array_key_first($edges);
        [$cx, $cy] = array_map('floatval', explode('|', $startKey));
        $loop  = [];
        $guard = 8 * $nx * $ny + 16;
        do {
            $k = $key($cx, $cy);
            if (empty($edges[$k])) {
                throw new InvalidArgumentException('Wall thickness is too large for one of the boxes.');
            }
            [$nx2, $ny2] = array_shift($edges[$k]);
            if (!$edges[$k]) {
                unset($edges[$k]);
            }
            $loop[] = [$cx, $cy];
            $cx = $nx2;
            $cy = $ny2;
        } while ($key($cx, $cy) !== $startKey && $guard-- > 0);

        if ($edges) {
            // A second, separate loop means the region is not one piece.
            throw new InvalidArgumentException('Wall thickness is too large for one of the boxes.');
        }

        $out = [];
        $cnt = count($loop);
        for ($i = 0; $i < $cnt; $i++) {
            $p = $loop[($i + $cnt - 1) % $cnt];
            $q = $loop[$i];
            $r = $loop[($i + 1) % $cnt];
            $cross = ($q[0] - $p[0]) * ($r[1] - $q[1]) - ($q[1] - $p[1]) * ($r[0] - $q[0]);
            if (abs($cross) > 1e-9) {
                $out[] = $q;
            }
        }
        return $out;
    }

    /**
     * Drops points that carry no shape - repeats and straight-line
     * stragglers - from two corner-matched loops at once, so they stay in
     * step. A point only goes when it is redundant in BOTH: removing it
     * from one alone would tear the rim between them.
     *
     * @return array{0: array, 1: array}
     */
    private static function cleanLoops(array $a, array $b): array
    {
        $same = static fn (array $p, array $q): bool =>
            abs($p[0] - $q[0]) < 1e-4 && abs($p[1] - $q[1]) < 1e-4;
        $flat = static fn (array $p, array $q, array $r): bool =>
            abs(($q[0] - $p[0]) * ($r[1] - $q[1]) - ($q[1] - $p[1]) * ($r[0] - $q[0])) < 1e-7;

        $changed = true;
        while ($changed && count($a) > 3) {
            $changed = false;
            $n = count($a);
            for ($i = 0; $i < $n; $i++) {
                $prev = ($i + $n - 1) % $n;
                $next = ($i + 1) % $n;
                $dropA = $same($a[$prev], $a[$i]) || $flat($a[$prev], $a[$i], $a[$next]);
                $dropB = $same($b[$prev], $b[$i]) || $flat($b[$prev], $b[$i], $b[$next]);
                if ($dropA && $dropB) {
                    array_splice($a, $i, 1);
                    array_splice($b, $i, 1);
                    $changed = true;
                    break;
                }
            }
        }

        return [array_values($a), array_values($b)];
    }

    /** Consecutive points closer than the weld tolerance, collapsed to one. */
    private static function dropRepeats(array $pts): array
    {
        $out = [];
        foreach ($pts as $p) {
            $last = $out ? end($out) : null;
            if ($last !== null && abs($last[0] - $p[0]) < 1e-4 && abs($last[1] - $p[1]) < 1e-4) {
                continue;
            }
            $out[] = $p;
        }
        while (count($out) > 3) {
            $first = $out[0];
            $last  = $out[count($out) - 1];
            if (abs($first[0] - $last[0]) < 1e-4 && abs($first[1] - $last[1]) < 1e-4) {
                array_pop($out);
                continue;
            }
            break;
        }
        return $out;
    }

    /**
     * Ear-clipping triangulation of a simple CCW polygon (indices into $pts).
     *
     * Signs matter here more than anywhere else in this file: with the cross
     * products the wrong way round the "is anything inside this ear" test
     * answers backwards, ears get clipped straight across concave corners,
     * and the triangles end up overlapping in one place and leaving a hole
     * in another - which is exactly what a slicer shows as a missing floor.
     * There is deliberately no fan fallback: fanning a concave polygon
     * guarantees that damage rather than avoiding it.
     */
    private static function earClip(array $pts): array
    {
        $n = count($pts);
        if ($n < 3) {
            return [];
        }

        // cross(a,b,c) > 0 means the turn a->b->c is a left turn.
        $cross = static fn (array $a, array $bq, array $c): float =>
            ($bq[0] - $a[0]) * ($c[1] - $a[1]) - ($bq[1] - $a[1]) * ($c[0] - $a[0]);

        // Work on a CCW copy; if the caller handed a CW loop the triangles
        // are flipped back at the end so the normal still comes out right.
        $area = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $area += $pts[$i][0] * $pts[$j][1] - $pts[$j][0] * $pts[$i][1];
        }
        $flip = $area < 0;
        $idx  = range(0, $n - 1);
        if ($flip) {
            $idx = array_reverse($idx);
        }

        // A point inside the ear, or sitting on one of its edges, blocks it:
        // clipping across such a point would leave the walls that still use
        // it meeting nothing (a T-junction, and a leak).
        $inside = static function (array $p, array $a, array $bq, array $c) use ($cross): bool {
            return $cross($a, $bq, $p) >= -1e-9
                && $cross($bq, $c, $p) >= -1e-9
                && $cross($c, $a, $p) >= -1e-9;
        };

        $tris  = [];
        $guard = 4 * $n + 16;

        while (count($idx) > 3 && $guard-- > 0) {
            $m = count($idx);
            $best = -1;

            for ($k = 0; $k < $m; $k++) {
                $ia = $idx[($k + $m - 1) % $m];
                $ib = $idx[$k];
                $ic = $idx[($k + 1) % $m];
                $a = $pts[$ia]; $bq = $pts[$ib]; $c = $pts[$ic];

                if ($cross($a, $bq, $c) <= 1e-12) {
                    continue; // reflex or straight: never an ear
                }

                $blockers = 0;
                foreach ($idx as $iv) {
                    if ($iv === $ia || $iv === $ib || $iv === $ic) {
                        continue;
                    }
                    // Only a reflex vertex can sit inside an ear of a simple
                    // polygon, so those are the only ones worth testing.
                    $pos = array_search($iv, $idx, true);
                    $pv  = $pts[$idx[($pos + $m - 1) % $m]];
                    $nv  = $pts[$idx[($pos + 1) % $m]];
                    if ($cross($pv, $pts[$iv], $nv) > 1e-12) {
                        continue;
                    }
                    if ($inside($pts[$iv], $a, $bq, $c)) {
                        $blockers++;
                    }
                }

                if ($blockers === 0) {
                    $best = $k;
                    break;
                }
            }

            if ($best < 0) {
                // Nothing convex left - the loop has degenerated. Drop the
                // flattest vertex and carry on rather than fanning.
                $drop = 0;
                $flat = PHP_FLOAT_MAX;
                for ($k = 0; $k < $m; $k++) {
                    $v = abs($cross(
                        $pts[$idx[($k + $m - 1) % $m]],
                        $pts[$idx[$k]],
                        $pts[$idx[($k + 1) % $m]]
                    ));
                    if ($v < $flat) { $flat = $v; $drop = $k; }
                }
                array_splice($idx, $drop, 1);
                continue;
            }

            $m2 = count($idx);
            $tris[] = [
                $idx[($best + $m2 - 1) % $m2],
                $idx[$best],
                $idx[($best + 1) % $m2],
            ];
            array_splice($idx, $best, 1);
        }

        if (count($idx) === 3) {
            $tris[] = [$idx[0], $idx[1], $idx[2]];
        }

        if ($flip) {
            foreach ($tris as &$t) {
                $t = [$t[2], $t[1], $t[0]];
            }
            unset($t);
        }

        return $tris;
    }

    /**
     * Manifold self check. Every edge of a closed solid must be shared by
     * exactly two triangles, once in each direction.
     */
    public static function isWatertight(array $mesh): bool
    {
        $edges = [];
        foreach ($mesh['tris'] as [$a, $b, $c]) {
            foreach ([[$a, $b], [$b, $c], [$c, $a]] as [$p, $q]) {
                $key = $p < $q ? "$p:$q" : "$q:$p";
                $dir = $p < $q ? 0 : 1;
                $edges[$key][$dir] = ($edges[$key][$dir] ?? 0) + 1;
            }
        }
        foreach ($edges as $e) {
            if (($e[0] ?? 0) !== 1 || ($e[1] ?? 0) !== 1) {
                return false;
            }
        }
        return true;
    }
}
