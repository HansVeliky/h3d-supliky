<?php
declare(strict_types=1);

/**
 * Serves the client bundle.
 *
 * Be clear about what this does and does not achieve: any JavaScript the
 * browser runs can be read by whoever is running that browser. Stripping
 * comments raises the effort required to reuse the code, but it is not a
 * lock. The actual protection is that the mesh generator is not in here at
 * all — it lives in lib/Geometry.php and only ever runs on the server.
 */

/**
 * Remove whole-line comments.
 *
 * A previous version tested each line independently, which silently
 * destroyed multi-line block comments: the opening "/*" line was dropped
 * while the prose lines below it survived and were parsed as JavaScript.
 * Block state has to be carried across lines, so this walks the file and
 * only ever removes lines that are entirely comment.
 *
 * Lines are left untouched unless they are unambiguously comment-only,
 * which keeps regex literals, template strings and any "//" inside a
 * string safe.
 */
function stripComments(string $js): string
{
    $out     = [];
    $inBlock = false;

    foreach (explode("\n", $js) as $line) {
        $trimmed = trim($line);

        if ($inBlock) {
            // Stay inside the block until a line closes it. If anything
            // follows the terminator, keep the line rather than guess.
            $pos = strpos($trimmed, '*/');
            if ($pos !== false) {
                $inBlock = false;
                $rest    = trim(substr($trimmed, $pos + 2));
                if ($rest !== '') {
                    $out[] = $rest;
                }
            }
            continue;
        }

        if ($trimmed === '' || str_starts_with($trimmed, '//')) {
            continue;
        }

        if (str_starts_with($trimmed, '/*')) {
            $pos = strpos($trimmed, '*/', 2);
            if ($pos === false) {
                $inBlock = true;   // multi-line block, skip until closed
            } else {
                $rest = trim(substr($trimmed, $pos + 2));
                if ($rest !== '') {
                    $out[] = $rest;
                }
            }
            continue;
        }

        $out[] = rtrim($line);
    }

    // An unterminated block means the input was not what we assumed.
    // Serving the original is always better than serving a broken bundle.
    if ($inBlock) {
        return $js;
    }

    return implode("\n", $out);
}

// This bundle contains interface code, not a secret. Requiring a PHP session
// to serve it brought no real protection (a browser can always read its JS),
// but it did add a second session request to every studio load and could make
// the entire UI inert when that request raced the page response. APIs remain
// protected server-side by their own CSRF/session/origin checks.

define('H3D_APP', true);

// The source lives outside the served asset tree and behind a PHP guard.
$source = dirname(__DIR__) . '/lib/client.js.php';
// A timestamp alone is not a safe cache key: deployment tools commonly keep
// file mtimes, and two quick edits can share a one-second timestamp. That
// leaves new HTML running an old client bundle, which is especially harmful
// when the markup differs for a guest. The content hash changes on every
// actual edit while still allowing the generated bundle to be reused.
$cache  = sys_get_temp_dir() . '/h3d_bundle_' . sha1_file($source) . '.js';

if (is_readable($cache)) {
    $js = file_get_contents($cache);
} else {
    // Executing the file strips the guard header and leaves the JS.
    ob_start();
    require $source;
    $raw = (string) ob_get_clean();
    $js  = stripComments($raw);

    $js = "/* Honza3D Drawer Organizer Studio - hans.jecool.net */\n" . $js;

    @file_put_contents($cache, $js);
}

header('Content-Type: application/javascript; charset=utf-8');
header('X-Content-Type-Options: nosniff');
// index.php puts the content hash in the URL. It is therefore safe to keep
// this large bundle locally until its URL changes; re-generating it on every
// navigation was needless PHP/IO work and made the studio feel sluggish.
header('Cache-Control: public, max-age=604800, immutable');
header('Content-Length: ' . strlen($js));
// PHP's built-in dev server on Windows intermittently resets a kept-alive
// connection halfway through this (large) response; closing it per request
// costs nothing and makes local testing dependable.
if (PHP_SAPI === 'cli-server') {
    header('Connection: close');
}

echo $js;
