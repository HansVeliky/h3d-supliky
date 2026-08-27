<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Validation of everything that arrives from the browser.
 *
 * The client does its own checks so the UI can react instantly, but those
 * checks are a convenience, not a guarantee. Anything posted to the export
 * endpoint is re-validated here from scratch.
 */
final class Layout
{
    /**
     * What every studio parameter may be, in millimetres.
     *
     * Read from the settings, so the panel, the sliders in the studio and
     * this validator cannot drift apart: raising a maximum here raises what
     * the server accepts, which is the whole point of having it in one
     * place. The fallbacks are the historical values, so the class still
     * works if it is ever used without a settings table.
     *
     * The outer inset gets the smaller of its own maximum and half the
     * drawer; the real "does it leave room" test needs the drawer size and
     * lives in parse().
     *
     * @return array<string,array{0:float,1:float}>
     */
    public static function limits(): array
    {
        $s = static function (string $key, float $fallback): float {
            return class_exists('Settings') && Settings::get($key) !== ''
                ? max(0.0, (float) Settings::get($key))
                : $fallback;
        };

        return [
            'dw'     => [1.0, $s('studio_dw_max', 1000.0)],
            'dd'     => [1.0, $s('studio_dd_max', 1000.0)],
            'dh'     => [1.0, $s('studio_dh_max', 500.0)],
            'gap'    => [0.0, $s('studio_gap_max', 2.0)],
            'wall'   => [$s('studio_wall_min', 2.0), $s('studio_wall_max', 10.0)],
            'bottom' => [$s('studio_bottom_min', 2.0), $s('studio_bottom_max', 10.0)],
            // Radius is a supported geometry feature even when an older
            // database contains studio_radius_max = 0 from the previous
            // wall/radius restriction. Never let that legacy value disable
            // radius in the studio or make the exporter reject a rounded box.
            'radius' => [0.0, max(20.0, $s('studio_radius_max', 20.0))],
            'outer'  => [0.0, $s('studio_outer_max', 20.0)],
            'print'  => [0.0, $s('studio_print_max', 500.0)],
        ];
    }

    // Admin-configurable, with the historical values as the fallback so
    // the class still works if it is used without the settings table.
    private static function maxCells(): int
    {
        return class_exists('Settings') ? max(1, Settings::int('max_cells')) : 12;
    }

    private static function maxBoxes(): int
    {
        return class_exists('Settings') ? max(1, Settings::int('max_boxes')) : 200;
    }

    /**
     * @return array{cfg: array, boxes: array}
     * @throws InvalidArgumentException
     */
    /**
     * What each parameter is called, in the language the studio is using.
     *
     * The messages built from these carry numbers, so they cannot go through
     * the fixed translation table in api/export.php - which is exactly why a
     * Czech user used to be told "dh must be between 1 and 500 mm": the key
     * from the wire, in English, in the middle of a Czech interface.
     */
    private const FIELD_NAMES = [
        'dw'     => ['cs' => 'Šířka šuplíku',      'en' => 'Drawer width'],
        'dd'     => ['cs' => 'Hloubka šuplíku',    'en' => 'Drawer depth'],
        'dh'     => ['cs' => 'Výška boxu',         'en' => 'Box height'],
        'gap'    => ['cs' => 'Mezera mezi boxy',   'en' => 'Gap between boxes'],
        'wall'   => ['cs' => 'Tloušťka stěny',     'en' => 'Wall thickness'],
        'bottom' => ['cs' => 'Tloušťka dna',       'en' => 'Floor thickness'],
        'radius' => ['cs' => 'Zaoblení rohů',      'en' => 'Corner radius'],
        'outer'  => ['cs' => 'Venkovní odsazení',  'en' => 'Outer inset'],
    ];

    public static function fieldName(string $key, string $lang = 'en'): string
    {
        return self::FIELD_NAMES[$key][$lang === 'cs' ? 'cs' : 'en'] ?? $key;
    }

    /** Millimetres as a person writes them: 2,5 in Czech, 2.5 in English. */
    private static function mm(float $v, string $lang): string
    {
        $s = rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
        return $lang === 'cs' ? str_replace('.', ',', $s) : $s;
    }

    public static function parse(array $input): array
    {
        $cfg    = [];
        $limits = self::limits();
        // The studio says which language it is in; every message below is
        // built in that language rather than translated afterwards.
        $lang = (($input['lang'] ?? 'en') === 'cs') ? 'cs' : 'en';

        foreach (['dw', 'dd', 'dh', 'gap', 'wall', 'bottom', 'radius'] as $key) {
            [$min, $max] = $limits[$key];
            $name = self::fieldName($key, $lang);
            if (!isset($input[$key]) || !is_numeric($input[$key])) {
                throw new InvalidArgumentException($lang === 'cs'
                    ? sprintf('%s: chybí nebo je to nesmyslná hodnota.', $name)
                    : sprintf('%s: missing or not a number.', $name));
            }
            $v = (float) $input[$key];
            if (!is_finite($v) || $v < $min || $v > $max) {
                throw new InvalidArgumentException($lang === 'cs'
                    ? sprintf('%s musí být mezi %s a %s mm.', $name, self::mm($min, 'cs'), self::mm($max, 'cs'))
                    : sprintf('%s must be between %s and %s mm.', $name, self::mm($min, 'en'), self::mm($max, 'en')));
            }
            $cfg[$key] = $v;
        }

        // The margin round the outside. Optional: a project saved before it
        // existed has none, and none is the old behaviour exactly.
        $outer = isset($input['outer']) && is_numeric($input['outer']) ? (float) $input['outer'] : 0.0;
        if (!is_finite($outer) || $outer < 0 || $outer > $limits['outer'][1]) {
            throw new InvalidArgumentException(
                sprintf('outer must be between 0 and %s mm.', $limits['outer'][1])
            );
        }
        if (2 * $outer >= min($cfg['dw'], $cfg['dd'])) {
            throw new InvalidArgumentException('The outer inset leaves no room for any box.');
        }
        $cfg['outer'] = $outer;

        foreach (['cols', 'rows'] as $key) {
            $v = isset($input[$key]) ? (int) $input[$key] : 0;
            if ($v < 1 || $v > self::maxCells()) {
                throw new InvalidArgumentException("$key must be between 1 and " . self::maxCells() . '.');
            }
            $cfg[$key] = $v;
        }

        // The cell must survive both the gaps between the boxes and the
        // margin taken off each side.
        $cw = ($cfg['dw'] - 2 * $outer - ($cfg['cols'] - 1) * $cfg['gap']) / $cfg['cols'];
        $ch = ($cfg['dd'] - 2 * $outer - ($cfg['rows'] - 1) * $cfg['gap']) / $cfg['rows'];
        if ($cw <= 0 || $ch <= 0) {
            throw new InvalidArgumentException('The gap and the outer inset are too large for this grid.');
        }

        /*
         * A box has to be taller than its own floor, and by enough to hold
         * something: floor plus one millimetre is the lowest that still has
         * a cavity worth the name. Said with both numbers in it, because
         * "must be smaller than the box height" left people guessing which
         * of the two they were supposed to change.
         */
        $minHeight = $cfg['bottom'] + 1.0;
        if ($cfg['dh'] < $minHeight) {
            throw new InvalidArgumentException($lang === 'cs'
                ? sprintf('Výška boxu musí být aspoň %s mm: dno má %s mm a nad ním musí zůstat aspoň 1 mm prostoru.',
                          self::mm($minHeight, 'cs'), self::mm($cfg['bottom'], 'cs'))
                : sprintf('Box height must be at least %s mm: the floor is %s mm and at least 1 mm has to be left above it.',
                          self::mm($minHeight, 'en'), self::mm($cfg['bottom'], 'en')));
        }

        $rawBoxes = $input['boxes'] ?? null;
        if (!is_array($rawBoxes) || $rawBoxes === []) {
            throw new InvalidArgumentException('There are no boxes to export.');
        }
        if (count($rawBoxes) > self::maxBoxes()) {
            throw new InvalidArgumentException('Too many boxes in one export.');
        }

        $boxes = [];
        $grid  = [];

        foreach ($rawBoxes as $raw) {
            if (!is_array($raw)) {
                throw new InvalidArgumentException('Malformed box definition.');
            }

            // Free-shape box: an explicit list of cells (an L, a T…). It is
            // re-validated from scratch: inside the grid, no overlaps, one
            // edge-connected piece, and no enclosed hole a printer could
            // never clear out.
            if (isset($raw['cells']) && is_array($raw['cells']) && $raw['cells'] !== []) {
                $cells = [];
                $seen  = [];
                foreach ($raw['cells'] as $rc) {
                    if (!is_array($rc)) {
                        throw new InvalidArgumentException('Malformed box definition.');
                    }
                    $cx = (int) ($rc['x'] ?? -1);
                    $cy = (int) ($rc['y'] ?? -1);
                    if ($cx < 0 || $cy < 0 || $cx >= $cfg['cols'] || $cy >= $cfg['rows']) {
                        throw new InvalidArgumentException('A box lies outside the grid.');
                    }
                    if (isset($seen["$cx,$cy"])) {
                        continue;
                    }
                    $seen["$cx,$cy"] = true;
                    if (isset($grid["$cx,$cy"])) {
                        throw new InvalidArgumentException('Two boxes overlap.');
                    }
                    $grid["$cx,$cy"] = true;
                    $cells[]         = ['x' => $cx, 'y' => $cy];
                }

                // Cells may also hold together corner to corner - the mesh
                // builds a bridge there - and a shape is allowed to close
                // into a ring around a hole.
                if (!self::cellsConnected($cells)) {
                    throw new InvalidArgumentException('A free-shape box must be one connected piece.');
                }

                $xs = array_column($cells, 'x');
                $ys = array_column($cells, 'y');
                $x  = min($xs);
                $y  = min($ys);
                $w  = max($xs) - $x + 1;
                $h  = max($ys) - $y + 1;

                /*
                 * A free shape carries only boundary dividers. The middle one
                 * and the fine 2:1 ones are measured against the bounding
                 * rectangle, which for an L covers cells the box does not own;
                 * the studio does not draw them there, so the export must not
                 * build them either.
                 */
                $isShape = count($cells) !== $w * $h;
                $walls = self::parseWalls(
                    $isShape
                        ? (array) ($raw['walls'] ?? [])
                        : array_merge((array) ($raw['walls'] ?? []), (array) ($raw['midWalls'] ?? [])),
                    $isShape ? [] : (array) ($raw['halfWalls'] ?? []),
                    $cells, $x, $y, $w, $h, (float) $cfg['radius']
                );

                // A shape that fills its bounding box is just a rectangle -
                // hand it to the rectangle path, which also knows radius.
                $box = ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
                if (count($cells) !== $w * $h) {
                    $box['cells'] = $cells;
                }
                $boxes[] = $walls === null ? $box : $box + $walls;
                continue;
            }

            $x = (int) ($raw['x'] ?? -1);
            $y = (int) ($raw['y'] ?? -1);
            $w = (int) ($raw['w'] ?? 0);
            $h = (int) ($raw['h'] ?? 0);

            if ($w < 1 || $h < 1) {
                throw new InvalidArgumentException('A box must span at least one cell.');
            }
            if ($x < 0 || $y < 0 || $x + $w > $cfg['cols'] || $y + $h > $cfg['rows']) {
                throw new InvalidArgumentException('A box lies outside the grid.');
            }

            $cells = [];
            for ($i = $x; $i < $x + $w; $i++) {
                for ($j = $y; $j < $y + $h; $j++) {
                    $key = "$i,$j";
                    if (isset($grid[$key])) {
                        throw new InvalidArgumentException('Two boxes overlap.');
                    }
                    $grid[$key] = true;
                    $cells[]    = ['x' => $i, 'y' => $j];
                }
            }

            $walls = self::parseWalls(
                array_merge((array) ($raw['walls'] ?? []), (array) ($raw['midWalls'] ?? [])),
                (array) ($raw['halfWalls'] ?? []), $cells, $x, $y, $w, $h, (float) $cfg['radius']
            );

            $box = ['x' => $x, 'y' => $y, 'w' => $w, 'h' => $h];
            $boxes[] = $walls === null ? $box : $box + $walls;
        }

        return ['cfg' => $cfg, 'boxes' => $boxes];
    }

    /**
     * Dividers, from the wire form to the form the mesh wants.
     *
     * The browser sends them as the lines they are, in absolute grid
     * coordinates ('v:3,2' is the boundary between cells (3,2) and (4,2)).
     * Everything is re-checked here: the line has to lie between two cells
     * this very box owns, and it may not stop in mid-air.
     *
     * @param array $cells cells of the box, absolute
     * @return array{vWalls: array, hWalls: array, halfWalls: array}|null
     */
    private static function parseWalls(array $raw, array $halfRaw, array $cells, int $bx, int $by, int $bw, int $bh, float $radius): ?array
    {
        $own = [];
        foreach ($cells as $c) { $own[$c['x'] . ',' . $c['y']] = true; }
        $has = static fn (int $x, int $y): bool => isset($own["$x,$y"]);

        $v = [];
        $h = [];
        $half = ['h' => [], 'v' => []];
        $any = false;
        foreach ($raw as $key) {
            if ($key === 'm:h' || $key === 'm:v') {
                // Middle dividers cross one rectangular cavity. On a free
                // shape they could end in open air, so fail closed here.
                if (count($cells) !== $bw * $bh) {
                    throw new InvalidArgumentException('A centred divider requires a rectangular box.');
                }
                $axis = $key[2];
                // A divider through an even span is exactly on an existing
                // grid boundary. Older saved projects can contain one from
                // the brief UI bug, so discard it instead of doubling a wall.
                $span = $axis === 'h' ? $bh : $bw;
                if ($span < 3 || $span % 2 === 0) {
                    continue;
                }
                // Old saved centre dividers become a normal line in the new
                // half-cell grid. This keeps existing projects intact while
                // avoiding a separate geometry path for the same wall.
                $count = ($axis === 'v' ? $bh : $bw) * 2;
                for ($i = 0; $i < $count; $i++) {
                    $half[$axis][$axis === 'v' ? "$span,$i" : "$i,$span"] = true;
                }
                $any = true;
                continue;
            }
            if (!is_string($key) || !preg_match('/^([vh]):(\d+),(\d+)$/', $key, $mm)) {
                throw new InvalidArgumentException('Malformed box definition.');
            }
            [$type, $x, $y] = [$mm[1], (int) $mm[2], (int) $mm[3]];
            $ok = $type === 'v' ? ($has($x, $y) && $has($x + 1, $y)) : ($has($x, $y) && $has($x, $y + 1));
            if (!$ok) {
                // A line the box does not own is not a divider - and never
                // something the studio can produce.
                throw new InvalidArgumentException('A divider must lie between two cells of the same box.');
            }
            if ($type === 'v') { $v[$y - $by][$x - $bx] = true; } else { $h[$y - $by][$x - $bx] = true; }
            $any = true;
        }
        foreach ($halfRaw as $key) {
            if (!is_string($key) || !preg_match('/^([vh]):(\d+)(?:,(\d+))?$/', $key, $mm)) {
                throw new InvalidArgumentException('Malformed half-grid divider.');
            }
            if (count($cells) !== $bw * $bh) {
                throw new InvalidArgumentException('A half-grid divider requires a rectangular box.');
            }
            $axis = $mm[1];
            $first = (int) $mm[2];
            $second = isset($mm[3]) && $mm[3] !== '' ? (int) $mm[3] : null;
            // Keys retain drawing coordinates: v:x,y and h:x,y. The
            // coordinate perpendicular to the wall is its fine-grid line.
            $line = $axis === 'v' ? $first : ($second ?? $first);
            $along = $second === null ? null : ($axis === 'v' ? $second : $first);
            $span = ($axis === 'v' ? $bw : $bh) * 2;
            // In 2:1 mode both the former middle line and original cell
            // boundaries are independently editable half segments.
            if ($line <= 0 || $line >= $span) {
                throw new InvalidArgumentException('Invalid half-grid divider position.');
            }
            if ($along === null) {
                // Compatibility with the brief full-line implementation:
                // expand an old v:3 / h:3 into its individual segments.
                $count = ($axis === 'v' ? $bh : $bw) * 2;
                for ($i = 0; $i < $count; $i++) {
                    $half[$axis][$axis === 'v' ? "$line,$i" : "$i,$line"] = true;
                }
            } else {
                $limit = ($axis === 'v' ? $bh : $bw) * 2;
                if ($along < 0 || $along >= $limit) {
                    throw new InvalidArgumentException('Invalid half-grid divider segment.');
                }
                $half[$axis][$axis === 'v' ? "$line,$along" : "$along,$line"] = true;
            }
            $any = true;
        }
        if (!$any) {
            return null;
        }
        // No end may stop in the open: at a junction where exactly one wall
        // arrives, the box itself has to end there so the wall runs into the
        // outer wall.
        $on = static fn (array $a, int $r, int $c): bool => !empty($a[$r][$c]);
        for ($r = 1; $r < $bh; $r++) {
            for ($c = 1; $c < $bw; $c++) {
                $up    = $on($v, $r - 1, $c - 1);
                $down  = $on($v, $r, $c - 1);
                $left  = $on($h, $r - 1, $c - 1);
                $right = $on($h, $r - 1, $c);
                if ((int) $up + (int) $down + (int) $left + (int) $right !== 1) {
                    continue;
                }
                $cell = static fn (int $dc, int $dr): bool => $has($bx + $c + $dc, $by + $r + $dr);
                if (($up    && $cell(-1, 0)  && $cell(0, 0))
                    || ($down  && $cell(-1, -1) && $cell(0, -1))
                    || ($left  && $cell(0, -1)  && $cell(0, 0))
                    || ($right && $cell(-1, -1) && $cell(-1, 0))) {
                    throw new InvalidArgumentException('A divider cannot stop in mid-air.');
                }
            }
        }

        // Dense arrays, so the mesh can index them without checking.
        $fill = static function (array $sparse, int $rows, int $cols): array {
            $out = [];
            for ($r = 0; $r < $rows; $r++) {
                $out[$r] = [];
                for ($c = 0; $c < $cols; $c++) { $out[$r][$c] = !empty($sparse[$r][$c]); }
            }
            return $out;
        };

        return [
            'vWalls' => $fill($v, $bh, max(0, $bw - 1)),
            'hWalls' => $fill($h, max(0, $bh - 1), $bw),
            'halfWalls' => [
                'v' => array_map(static fn (string $key): array => array_map('intval', explode(',', $key)), array_keys($half['v'])),
                'h' => array_map(static fn (string $key): array => array_map('intval', explode(',', $key)), array_keys($half['h'])),
            ],
        ];
    }

    /**
     * Are the cells one piece, joined along shared edges? Touching at a bare
     * corner is not a join - two pieces meeting there would hold together by
     * nothing - so a shape has to be walkable edge to edge. A shape closing
     * into a ring is fine; the hole in the middle is allowed.
     */
    private static function cellsConnected(array $cells): bool
    {
        if ($cells === []) {
            return false;
        }
        $set = [];
        foreach ($cells as $c) {
            $set[$c['x'] . ',' . $c['y']] = true;
        }
        $stack = [$cells[0]];
        $seen  = [$cells[0]['x'] . ',' . $cells[0]['y'] => true];
        while ($stack) {
            $c = array_pop($stack);
            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $k = ($c['x'] + $dx) . ',' . ($c['y'] + $dy);
                if (isset($set[$k]) && !isset($seen[$k])) {
                    $seen[$k] = true;
                    $stack[]  = ['x' => $c['x'] + $dx, 'y' => $c['y'] + $dy];
                }
            }
        }
        return count($seen) === count($set);
    }

    /** Does the shape fully enclose at least one empty cell? */
    private static function cellsHaveHole(array $cells): bool
    {
        $xs = array_column($cells, 'x');
        $ys = array_column($cells, 'y');
        $x0 = min($xs) - 1;
        $y0 = min($ys) - 1;
        $x1 = max($xs) + 1;
        $y1 = max($ys) + 1;

        $set = [];
        foreach ($cells as $c) {
            $set[$c['x'] . ',' . $c['y']] = true;
        }

        // Flood the emptiness from outside; any empty cell it cannot reach
        // is sealed inside the shape.
        $seen  = ["$x0,$y0" => true];
        $stack = [[$x0, $y0]];
        while ($stack) {
            [$px, $py] = array_pop($stack);
            foreach ([[1, 0], [-1, 0], [0, 1], [0, -1]] as [$dx, $dy]) {
                $nx = $px + $dx;
                $ny = $py + $dy;
                if ($nx < $x0 || $ny < $y0 || $nx > $x1 || $ny > $y1) {
                    continue;
                }
                $k = "$nx,$ny";
                if (isset($set[$k]) || isset($seen[$k])) {
                    continue;
                }
                $seen[$k] = true;
                $stack[]  = [$nx, $ny];
            }
        }

        $emptyTotal = ($x1 - $x0 + 1) * ($y1 - $y0 + 1) - count($set);
        return count($seen) !== $emptyTotal;
    }
}
