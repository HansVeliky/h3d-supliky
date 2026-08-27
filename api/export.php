<?php
declare(strict_types=1);

require __DIR__ . '/../lib/bootstrap.php';
// Layout is already loaded by bootstrap; Geometry and Exporters are export-only.
require_once __DIR__ . '/../lib/Geometry.php';
require_once __DIR__ . '/../lib/Exporters.php';

/**
 * Server-side messages the user will actually read, so they have to follow
 * the language chosen in the interface.
 */
const MESSAGES = [
    'cs' => [
        'POST only.'                        => 'Povolena je jen metoda POST.',
        'Cross-origin requests are not allowed.' => 'Požadavky z jiné domény nejsou povoleny.',
        'Request payload is too large.'      => 'Odesílaná data jsou příliš velká.',
        'Invalid JSON payload.'              => 'Neplatná data požadavku.',
        'Invalid session token. Reload the page and try again.'
            => 'Neplatný token relace. Načti stránku znovu a zkus to zase.',
        'Unknown export format.'             => 'Neznámý formát exportu.',
        'Export failed on the server.'       => 'Export na serveru selhal.',
        'Generated mesh is not watertight. Please report this layout.'
            => 'Vygenerovaná síť není uzavřená. Nahlas prosím toto rozvržení.',
        'There are no boxes to export.'      => 'Není co exportovat.',
        'Too many boxes in one export.'      => 'Příliš mnoho boxů v jednom exportu.',
        'Malformed box definition.'          => 'Poškozená definice boxu.',
        'A box must span at least one cell.' => 'Box musí zabírat alespoň jednu buňku.',
        'A box lies outside the grid.'       => 'Box leží mimo mřížku.',
        'Two boxes overlap.'                 => 'Dva boxy se překrývají.',
        'The gap is too large for this grid.'=> 'Mezera je pro tuto mřížku příliš velká.',
        'Bottom thickness must be smaller than the box height.'
            => 'Tloušťka dna musí být menší než výška boxu.',
        'Wall thickness is too large for one of the boxes.'
            => 'Tloušťka stěny je pro jeden z boxů příliš velká.',
        'Bottom thickness must be greater than 0 and smaller than box height.'
            => 'Tloušťka dna musí být větší než 0 a menší než výška boxu.',
        'Sign in to export.'                     => 'Pro export se přihlas.',
        'Please wait before exporting again.'    => 'Před dalším exportem chvíli počkej.',
        'Please wait before exporting again, or sign in to use credits.'
            => 'Před dalším exportem chvíli počkej, nebo se přihlas a použij kredity.',
        'No credits left. Wait for the free export or redeem a code.'
            => 'Došly kredity. Počkej na volný export, nebo uplatni kód.',
        'Not enough credits.'                    => 'Nedostatek kreditů.',
        'Custom shapes are available after signing in.'
            => 'Vlastní tvary boxů jsou dostupné po přihlášení.',
        'Dividers are available after signing in.'
            => 'Příčky v boxu jsou dostupné po přihlášení.',
        'A divider must lie between two cells of the same box.'
            => 'Příčka musí ležet mezi dvěma políčky téhož boxu.',
        'A divider cannot stop in mid-air.'
            => 'Příčka nesmí končit v prázdnu.',
    ],
];

$LANG = 'en';

/** Send a JSON error and stop. */
function fail(int $code, string $message, array $extra = []): never
{
    global $LANG;

    // Untranslated strings fall through unchanged rather than disappearing.
    $text = MESSAGES[$LANG][$message] ?? $message;

    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $text] + $extra, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    fail(405, 'POST only.');
}

// Same-origin guard: the endpoint should not be usable as a free
// mesh-generating service from someone else's page.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    // Compare the whole origin, port included - HTTP_HOST carries the port
    // ("localhost:8000") while parse_url's host does not, so matching only
    // hosts rejected every same-origin POST on a non-default port.
    $expected = (Auth::isHttps() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
    if (strcasecmp(rtrim($origin, '/'), $expected) !== 0) {
        fail(403, 'Cross-origin requests are not allowed.');
    }
}

$raw = file_get_contents('php://input');
if ($raw === false || strlen($raw) > 262144) {
    fail(413, 'Request payload is too large.');
}

$input = json_decode($raw, true);
if (!is_array($input)) {
    fail(400, 'Invalid JSON payload.');
}

if (!isset($input['token']) || !isset($_SESSION['h3d_token'])
    || !hash_equals($_SESSION['h3d_token'], (string) $input['token'])) {
    fail(403, 'Invalid session token. Reload the page and try again.');
}

$LANG = (($input['lang'] ?? 'en') === 'cs') ? 'cs' : 'en';

$format = strtolower((string) ($input['format'] ?? '3mf'));
if (!in_array($format, ['3mf', 'stl'], true)) {
    fail(400, 'Unknown export format.');
}

$user = Auth::user();
// Unverified accounts may use the Studio, but exports follow visitor rules.
$featureUser = ($user && (!empty($user['verified_at']) || Auth::isPrivileged($user))) ? $user : null;

// The allowance is checked before any geometry work, so a throttled request
// costs the server nothing.
$decision = Quota::check($featureUser);

if ($decision['mode'] === Quota::LOGIN) {
    fail(401, $decision['message'], ['need_login' => true]);
}

if ($decision['mode'] === Quota::BLOCKED) {
    fail(429, $decision['message'], [
        'retry_after' => $decision['retry_after'],
        'balance'     => Cred::fmt((int) $decision['balance']),
    ]);
}

// Dividers remain a verified-account feature; unverified accounts are
// intentionally handled exactly like visitors.
if (!$featureUser) {
    foreach ((array) ($input['boxes'] ?? []) as $rb) {
        if (!is_array($rb)) {
            continue;
        }
        if (!empty($rb['walls']) || !empty($rb['midWalls']) || !empty($rb['halfWalls'])) {
            fail(401, 'Dividers are available after signing in.', ['need_login' => true]);
        }
        if (!empty($rb['cells'])) {
            fail(400, 'Malformed box definition.');
        }
    }
}

try {
    ['cfg' => $cfg, 'boxes' => $boxes] = Layout::parse($input);

    // The credit is taken first: charging after the file is built would
    // leave a window where a dropped connection means a free export.
    Quota::consume($featureUser, $decision, $format, count($boxes));

    // Geometry generation can be the longest request in the application.
    // Its billing decision is already committed, so never keep this user's
    // PHP session lock while mesh vertices and the archive are being built.
    Auth::releaseSession();

    $meshes = [];
    foreach ($boxes as $b) {
        $mesh = isset($b['cells'])
            ? Geometry::polyMesh($cfg, $b)
            : Geometry::boxMesh($cfg, $b);

        // Refuse to ship a model that would fail in the slicer.
        if (!Geometry::isWatertight($mesh)) {
            fail(500, 'Generated mesh is not watertight. Please report this layout.');
        }

        $meshes[] = $mesh;
    }

    $stamp = date('Ymd_His');

    if ($format === 'stl') {
        $data     = Exporters::binarySTL($meshes);
        $filename = "Honza3D_Drawer_Organizer_$stamp.stl";
        $mime     = 'model/stl';
    } else {
        $data     = Exporters::package3MF($meshes);
        $filename = "Honza3D_Drawer_Organizer_$stamp.3mf";
        $mime     = 'application/vnd.ms-package.3dmanufacturing-3dmodel+xml';
    }
} catch (InvalidArgumentException $e) {
    fail(422, $e->getMessage());
} catch (RuntimeException $e) {
    fail(429, $e->getMessage());
} catch (Throwable $e) {
    error_log('[h3d] export failed: ' . $e->getMessage());
    fail(500, 'Export failed on the server.');
}

header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($data));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// Lets the interface refresh the balance without a second round trip.
header('X-H3D-Credits: ' . ($user ? Cred::fmt(Credits::balance((int) $user['id'])) : '0'));
header('X-H3D-Charged: ' . ($decision['mode'] === Quota::CREDIT ? Cred::fmt((int) $decision['cost']) : '0'));

echo $data;
