<?php
declare(strict_types=1);

if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }

/** HTTP protections shared by the web, API and administration entry points. */
final class Security
{
    /**
     * Is this request really on HTTPS?
     *
     * X-Forwarded-Proto is a header the client can invent, so it counts only
     * where a reverse proxy is explicitly trusted - the same switch that
     * already guards X-Forwarded-For in Auth::ipHash(). Set H3D_TRUST_PROXY=1
     * in the environment when the site sits behind a proxy that terminates
     * TLS; leave it unset and only the server's own HTTPS flag is believed.
     */
    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') { return true; }
        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) { return true; }
        return getenv('H3D_TRUST_PROXY') === '1'
            && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    public static function headers(): void
    {
        if (headers_sent()) { return; }
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: DENY');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Cross-Origin-Resource-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        // Protect browser boundaries without breaking the application's existing inline UI.
        header("Content-Security-Policy: base-uri 'self'; form-action 'self'; frame-ancestors 'none'; object-src 'none'");
        if (self::isHttps()) { header('Strict-Transport-Security: max-age=15552000; includeSubDomains'); }
    }

    /** Extra gate for POSTs; CSRF remains the fallback for old clients. */
    public static function sameOriginRequest(): bool
    {
        if (strtolower((string) ($_SERVER['HTTP_SEC_FETCH_SITE'] ?? '')) === 'cross-site') { return false; }
        $origin = trim((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        if ($origin === '') { return true; }
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') { return false; }
        /*
         * The HOST has to match; the scheme is deliberately not compared.
         * Reconstructing it means guessing whether a proxy terminated TLS,
         * and guessing wrong turns every POST on the site into a 403. The
         * guarantee that matters is unaffected: a page on another origin
         * cannot make the browser send our host in Origin.
         */
        $sent = strtolower(rtrim($origin, '/'));
        foreach (['https://', 'http://'] as $scheme) {
            if (hash_equals(strtolower($scheme . rtrim($host, '/')), $sent)) { return true; }
        }
        return false;
    }
}
