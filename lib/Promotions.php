<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Scheduled promotions.
 *
 * A promotion is a site-wide percentage off every package for a stretch of
 * time. Several can be planned ahead, but they may never overlap, so at most
 * one is ever running - which is what lets the shop ask a single, unambiguous
 * question: "is there a discount right now, and how much?"
 *
 * They live as a JSON list in settings rather than their own table: there are
 * only ever a handful, they carry no relations, and keeping them out of the
 * schema means no migration to get wrong. Each entry is
 *   { label: string, percent: int (tenths of a %), from: 'Y-m-d'|'', until: 'Y-m-d'|'' }
 * with an empty "from" meaning "already" and an empty "until" meaning "forever".
 */
final class Promotions
{
    /** @return list<array{label:string,percent:int,from:string,until:string}> */
    public static function all(): array
    {
        $raw = trim(Settings::get('promotions_json'));
        if ($raw === '') {
            return [];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return [];
        }

        $out = [];
        foreach ($data as $p) {
            if (!is_array($p)) {
                continue;
            }
            $out[] = self::clean($p);
        }

        // Chronological, so the manager reads top-to-bottom like a calendar
        // and the "active" scan meets the soonest window first.
        usort($out, static function (array $a, array $b): int {
            [$as] = self::window($a);
            [$bs] = self::window($b);
            return ($as ?? PHP_INT_MIN) <=> ($bs ?? PHP_INT_MIN);
        });

        return $out;
    }

    /** Whether the manager is in use at all, or the legacy discount still rules. */
    public static function configured(): bool
    {
        return trim(Settings::get('promotions_json')) !== '';
    }

    /** @param array<int,array{label:string,percent:int,from:string,until:string}> $list */
    public static function saveAll(array $list): void
    {
        $clean = array_map([self::class, 'clean'], array_values($list));
        // Always write a JSON array, even when empty: an empty string would
        // read as "not configured" and hand control back to the legacy
        // discount, which is not what clearing the last promotion means.
        Settings::set(['promotions_json' => json_encode($clean, JSON_UNESCAPED_UNICODE)]);
    }

    /**
     * The promotion in force right now, or null.
     *
     * @return array{label:string,percent:int,from:string,until:string}|null
     */
    public static function active(): ?array
    {
        $now = time();
        foreach (self::all() as $p) {
            if ($p['percent'] <= 0) {
                continue;
            }
            [$start, $end] = self::window($p);
            if ($start !== null && $now < $start) {
                continue;
            }
            if ($end !== null && $now > $end) {
                continue;
            }
            return $p;
        }
        return null;
    }

    /**
     * Start and end of a promotion as unix timestamps, null meaning open-ended.
     * The end is the final moment of its last day, so "until 14 Feb" includes
     * the whole of the 14th.
     *
     * @return array{0:?int,1:?int}
     */
    public static function window(array $p): array
    {
        $from  = trim((string) ($p['from'] ?? ''));
        $until = trim((string) ($p['until'] ?? ''));

        $start = ($from !== '' && ($t = strtotime($from)) !== false) ? $t : null;
        $end   = ($until !== '' && ($t = strtotime($until . ' 23:59:59')) !== false) ? $t : null;

        return [$start, $end];
    }

    /** Do two promotions share any moment in time? Open ends count as infinity. */
    public static function overlaps(array $a, array $b): bool
    {
        [$as, $ae] = self::window($a);
        [$bs, $be] = self::window($b);

        $as ??= PHP_INT_MIN; $ae ??= PHP_INT_MAX;
        $bs ??= PHP_INT_MIN; $be ??= PHP_INT_MAX;

        // A start after its own end is a nonsense range that touches nothing.
        if ($as > $ae || $bs > $be) {
            return false;
        }

        return $as <= $be && $bs <= $ae;
    }

    /**
     * Where a candidate would collide, ignoring the row being edited.
     *
     * @return int|null index of the first clashing promotion, or null
     */
    public static function firstClash(array $candidate, int $ignoreIndex = -1): ?int
    {
        foreach (self::all() as $i => $p) {
            if ($i === $ignoreIndex) {
                continue;
            }
            if (self::overlaps($candidate, $p)) {
                return $i;
            }
        }
        return null;
    }

    /** How a promotion reads right now: 'running' | 'planned' | 'ended'. */
    public static function state(array $p): string
    {
        $now = time();
        [$start, $end] = self::window($p);

        if ($end !== null && $now > $end) {
            return 'ended';
        }
        if ($start !== null && $now < $start) {
            return 'planned';
        }
        return 'running';
    }

    /** @return array{label:string,percent:int,from:string,until:string} */
    private static function clean(array $p): array
    {
        return [
            'label'   => trim((string) ($p['label'] ?? '')),
            // Stored in tenths of a percent, capped like the legacy discount.
            'percent' => max(0, min(950, (int) ($p['percent'] ?? 0))),
            'from'    => self::cleanDate((string) ($p['from'] ?? '')),
            'until'   => self::cleanDate((string) ($p['until'] ?? '')),
        ];
    }

    /** Normalise a date to Y-m-d, or empty when it is not a real date. */
    private static function cleanDate(string $d): string
    {
        $d = trim($d);
        if ($d === '') {
            return '';
        }
        $ts = strtotime($d);
        return $ts === false ? '' : date('Y-m-d', $ts);
    }
}
