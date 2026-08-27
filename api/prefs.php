<?php
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user = Auth::user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false]);
    exit;
}

$pdo = Db::pdo();
$allowed = ['dw', 'dd', 'dh', 'gap', 'wall', 'bottom', 'radius', 'cols', 'rows', 'maxPrintW', 'maxPrintD', 'outer', 'workspaceUpdatedAt'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // A Studio tab can survive a login/logout in another tab and therefore
    // carry the previous session's CSRF token. Refresh it once instead of
    // turning an otherwise valid workspace save into a visible CSRF error.
    if (!Auth::csrfIsValid()) {
        http_response_code(419);
        echo json_encode([
            'ok' => false,
            'csrf_refresh' => Auth::csrfToken(),
            'error' => 'CSRF token refreshed.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // PHP serialises requests that hold the same session open. The studio
    // saves preferences while you keep working, so holding the lock here
    // made the next page (account, admin, sign-in) wait for no reason -
    // everything below only needs the database.
    if (function_exists('session_write_close')) {
        @session_write_close();
    }

    $raw = (string) ($_POST['prefs'] ?? '');
    if (strlen($raw) > 20000) {
        http_response_code(413);
        echo json_encode(['ok' => false]);
        exit;
    }

    // Only whitelisted values are stored - never arbitrary content. Keys the
    // payload does not mention keep their stored value (merge), so the
    $in  = json_decode($raw, true);
    $cur = json_decode((string) ($user['prefs'] ?? ''), true);
    $out = is_array($cur) ? $cur : [];
    if (is_array($in)) {
        // F5/Ctrl+F5 may send both the page-unload beacon and the normal
        // debounced save. Never let an older request, or an equal duplicate,
        // overwrite the newer stored workspace.
        $incomingWorkspaceAt = isset($in['workspaceUpdatedAt']) && is_numeric($in['workspaceUpdatedAt'])
            ? (int) $in['workspaceUpdatedAt'] : 0;
        $storedWorkspaceAt = (int) ($out['workspaceUpdatedAt'] ?? 0);
        if ($incomingWorkspaceAt > 0 && $storedWorkspaceAt > 0 && $incomingWorkspaceAt <= $storedWorkspaceAt) {
            echo json_encode(['ok' => true, 'ignored' => true]);
            exit;
        }
        foreach ($allowed as $k) {
            if (isset($in[$k]) && is_numeric($in[$k]) && is_finite((float) $in[$k])) {
                $out[$k] = 0 + $in[$k];
            }
        }
        // Wall mode and radius are independent workspace settings: signing
        // back in should restore the studio exactly as it was left.
        if (isset($in['wallMode'])) {
            $out['wallMode'] = $in['wallMode'] ? 1 : 0;
        }
        if (isset($in['wallStash']) && is_numeric($in['wallStash']) && is_finite((float) $in['wallStash'])) {
            $out['wallStash'] = max(0.0, min(1000.0, (float) $in['wallStash']));
        }
        if (isset($in['wallGrid']) && in_array((int) $in['wallGrid'], [1, 2], true)) {
            $out['wallGrid'] = (int) $in['wallGrid'];
        }
        // Appearance travels with the account, so signing in on another
        // machine (or after a guest fiddled with the cookie) looks right.
        foreach (['lang' => ['en', 'cs'], 'unit' => ['mm', 'in']] as $k => $vals) {
            if (isset($in[$k]) && in_array($in[$k], $vals, true)) {
                $out[$k] = $in[$k];
            }
        }
        // The current layout, kept so the workspace comes back as left.
        if (isset($in['boxes']) && is_array($in['boxes'])) {
            $bx = [];
            foreach (array_slice($in['boxes'], 0, 400) as $b) {
                if (is_array($b) && isset($b['x'], $b['y'], $b['w'], $b['h'])) {
                    $row = ['x' => (int) $b['x'], 'y' => (int) $b['y'], 'w' => (int) $b['w'], 'h' => (int) $b['h']];
                    // A box keeps its own visual colour when it moves or
                    // changes size. Only the ten client palette slots are
                    // accepted, never an arbitrary CSS value.
                    if (isset($b['colorSlot']) && is_int($b['colorSlot']) && $b['colorSlot'] >= 0 && $b['colorSlot'] < 10) {
                        $row['colorSlot'] = $b['colorSlot'];
                    }
                    // L/T/free-shape boxes are stored as their owned cells.
                    if (isset($b['cells']) && is_array($b['cells'])) {
                        $cells = [];
                        foreach (array_slice($b['cells'], 0, 400) as $c) {
                            if (is_array($c) && isset($c['x'], $c['y'])) {
                                $cells[] = ['x' => (int) $c['x'], 'y' => (int) $c['y']];
                            }
                        }
                        if ($cells !== []) {
                            $row['cells'] = $cells;
                        }
                    }
                    // Dividers, as the grid lines they are ('v:3,2'). Kept
                    // verbatim in that one shape and nothing else; the client
                    // checks on load that the box still owns each line.
                    if (isset($b['walls']) && is_array($b['walls'])) {
                        $walls = [];
                        foreach (array_slice($b['walls'], 0, 400) as $k) {
                            if (is_string($k) && preg_match('/^[vh]:\d{1,3},\d{1,3}$/', $k)) {
                                $walls[] = $k;
                            }
                        }
                        if ($walls !== []) {
                            $row['walls'] = $walls;
                        }
                    }
                    if (isset($b['midWalls']) && is_array($b['midWalls'])) {
                        $midWalls = [];
                        foreach ($b['midWalls'] as $k) {
                            if ($k === 'm:h' || $k === 'm:v') {
                                $midWalls[] = $k;
                            }
                        }
                        if ($midWalls !== []) {
                            $row['midWalls'] = array_values(array_unique($midWalls));
                        }
                    }
                    // Lines in the additional half-cell grid. They are
                    // local to the box (for example v:3), never arbitrary
                    // coordinates supplied by a client.
                    if (isset($b['halfWalls']) && is_array($b['halfWalls'])) {
                        $halfWalls = [];
                        foreach (array_slice($b['halfWalls'], 0, 400) as $k) {
                            if (is_string($k) && preg_match('/^[vh]:\d{1,3}(?:,\d{1,3})?$/', $k)) {
                                $halfWalls[] = $k;
                            }
                        }
                        if ($halfWalls !== []) {
                            $row['halfWalls'] = array_values(array_unique($halfWalls));
                        }
                    }
                    $bx[] = $row;
                }
            }
            $out['boxes'] = $bx;

            // A signed-in user's saved design is server-side state, not a
            // trusted browser cache. Re-run the same geometry validation used
            // by export before accepting a non-empty workspace. This prevents
            // a malformed/stale client from permanently corrupting the saved
            // grid, including out-of-bounds or overlapping boxes/dividers.
            if ($bx !== []) {
                try {
                    Layout::parse([
                        'dw' => (float) ($out['dw'] ?? 250),
                        'dd' => (float) ($out['dd'] ?? 250),
                        'dh' => (float) ($out['dh'] ?? 50),
                        'gap' => (float) ($out['gap'] ?? 0.4),
                        'wall' => (float) ($out['wall'] ?? 2),
                        'bottom' => (float) ($out['bottom'] ?? 2),
                        'radius' => (float) ($out['radius'] ?? 0),
                        'outer' => (float) ($out['outer'] ?? 0),
                        'cols' => (int) ($out['cols'] ?? 1),
                        'rows' => (int) ($out['rows'] ?? 1),
                        'boxes' => $bx,
                    ]);
                } catch (InvalidArgumentException $e) {
                    http_response_code(422);
                    echo json_encode(['ok' => false, 'error' => 'Návrh nebyl uložen: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
                    exit;
                }
            }
        }
    }

    // Monotonic server-side workspace revision. This is the signal used by
    // other open devices to know that something changed; unlike the browser
    // timestamp it cannot collide when two devices save within the same ms.
    $previousRevision = max(0, (int) ($out['workspaceRevision'] ?? 0));
    $out['workspaceRevision'] = $previousRevision + 1;

    $st = $pdo->prepare('UPDATE users SET prefs = ? WHERE id = ?');
    $st->execute([json_encode($out), (int) $user['id']]);

    // The interface language doubles as the e-mail language: changing it in
    // the studio must reach the users.lang column the mailer reads.
    if (isset($out['lang']) && ($out['lang'] === 'cs' || $out['lang'] === 'en')) {
        $pdo->prepare('UPDATE users SET lang = ? WHERE id = ?')
            ->execute([$out['lang'], (int) $user['id']]);
    }

    echo json_encode(['ok' => true, 'workspaceRevision' => (int) $out['workspaceRevision']]);
    exit;
}

// GET — hand back the stored preferences (or an empty object). Billing
// details live in the same JSON but belong to the account page only, so the
// studio never even receives them.
$st = $pdo->prepare('SELECT prefs FROM users WHERE id = ?');
$st->execute([(int) $user['id']]);
$raw   = (string) ($st->fetchColumn() ?: '');
$prefs = $raw !== '' ? json_decode($raw, true) : null;
if (is_array($prefs)) {
    unset($prefs['billing']);
}

$prefs = is_array($prefs) ? $prefs : [];
$revision = max(0, (int) ($prefs['workspaceRevision'] ?? 0));
$since = max(0, (int) ($_GET['since'] ?? 0));
if ($since > 0 && $revision <= $since) {
    echo json_encode(['ok' => true, 'changed' => false, 'workspaceRevision' => $revision]);
    exit;
}

// A watch request gets the full workspace only when its revision changed.
echo json_encode([
    'ok' => true,
    'changed' => $since > 0 ? true : null,
    'workspaceRevision' => $revision,
    'prefs' => $prefs,
]);
