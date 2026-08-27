<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Free-form links to your other services, edited in the panel as one line
 * per link: "Label | https://example.com".
 *
 * Kept as plain text rather than a table because that is the shape that
 * matches how it is actually used — a short list you occasionally reorder
 * by editing, not something worth a CRUD screen.
 */
final class Links
{
    /**
     * @return array<int, array{label: string, url: string}>
     */
    public static function all(): array
    {
        $raw = trim(Settings::get('custom_links'));
        if ($raw === '') {
            return [];
        }

        $out = [];

        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // "Label | url", or just a url on its own.
            if (str_contains($line, '|')) {
                [$label, $url] = array_map('trim', explode('|', $line, 2));
            } else {
                $label = '';
                $url   = $line;
            }

            $url = self::normaliseUrl($url);
            if ($url === null) {
                continue;
            }

            if ($label === '') {
                $label = (string) (parse_url($url, PHP_URL_HOST) ?: $url);
            }

            $out[] = ['label' => $label, 'url' => $url];
        }

        return $out;
    }

    /**
     * Only http(s) is allowed through. A "javascript:" line in a settings
     * field would otherwise become a working script link on every page.
     */
    private static function normaliseUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        if (!preg_match('~^[a-z][a-z0-9+.-]*://~i', $url)) {
            $url = 'https://' . $url;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }
}
