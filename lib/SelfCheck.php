<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Verifies that files which must never be public really are unreachable.
 *
 * The .htaccess rules only work on Apache. On nginx, or on a host where
 * AllowOverride is off, they are silently ignored and the database becomes
 * a download link. Rather than assume the rules took effect, this asks the
 * running server for the files over HTTP and reports what actually happens.
 */
final class SelfCheck
{
    /** Paths relative to the installation root that must not be served. */
    private const PROTECTED = [
        'data/'              => 'Data directory listing',
        'data/instance.php'  => 'Database name token',
        'lib/client.js.php'  => 'Unminified client bundle',
        'lib/Db.php'         => 'Server source',
        'lib/Geometry.php'   => 'Mesh generator source',
        'test_core.php'      => 'Test script',
        'reset.php'          => 'Reset script',
        'test_mail.php'      => 'Test script',
    ];

    /**
     * @return array<int, array{path: string, what: string, status: string, exposed: bool, tested: bool}>
     */
    public static function run(string $baseUrl): array
    {
        $out = [];

        foreach (self::PROTECTED as $path => $what) {
            $full = rtrim($baseUrl, '/') . '/' . $path;
            [$code, $err] = self::head($full);

            // A test that could not run is NOT a pass. Reporting "safe"
            // when nothing was actually verified is worse than reporting
            // nothing at all.
            if ($code === 200) {
                $status  = 'REACHABLE (HTTP 200)';
                $exposed = true;
                $tested  = true;
            } elseif ($code > 0) {
                $status  = 'blocked (HTTP ' . $code . ')';
                $exposed = false;
                $tested  = true;
            } else {
                $status  = 'inconclusive' . ($err !== '' ? ' (' . $err . ')' : ' (no response)');
                $exposed = false;
                $tested  = false;
            }

            $out[] = [
                'path'    => $path,
                'what'    => $what,
                'status'  => $status,
                'exposed' => $exposed,
                'tested'  => $tested,
            ];
        }

        return $out;
    }

    /** @return array{0:int,1:string} status code and error text */
    private static function head(string $url): array
    {
        $ctx = stream_context_create([
            'http' => [
                'method'        => 'GET',
                'timeout'       => 3,
                'ignore_errors' => true,
                'header'        => "Range: bytes=0-0\r\nUser-Agent: h3d-selfcheck\r\n",
            ],
            'ssl' => [
                // The loopback request may hit a self-signed certificate on a
                // NAS; the certificate is irrelevant to what is being tested.
                'verify_peer'      => false,
                'verify_peer_name' => false,
            ],
        ]);

        $prev = set_error_handler(static fn() => true);
        $body = @file_get_contents($url, false, $ctx);
        set_error_handler($prev);

        if (!isset($http_response_header) || !is_array($http_response_header)) {
            return [0, $body === false ? 'request failed' : ''];
        }

        foreach ($http_response_header as $line) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $m)) {
                $code = (int) $m[1];
            }
        }

        // 206 means the range request succeeded, which is still reachable.
        if (isset($code) && $code === 206) {
            $code = 200;
        }

        return [$code ?? 0, ''];
    }
}
