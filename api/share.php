<?php
declare(strict_types=1);

/* Shared projects are private. index.php redirects signed-out users to login before the API is called. */
require __DIR__ . '/../lib/bootstrap.php';
if (!Auth::user()) { http_response_code(401); exit; }

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user = Auth::user();

function shareKey(): string
{
    return rtrim(strtr(base64_encode(random_bytes(12)), '+/', '-_'), '=');
}

function jsonOut(array $data, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$pdo = Db::pdo();

if ($method === 'POST') {
    if (!$user) {
        jsonOut(['ok' => false, 'error' => 'Přihlášení je nutné pro vytvoření odkazu.'], 401);
    }
    Auth::checkCsrf();

    $raw = (string) ($_POST['project'] ?? '');
    if ($raw === '' || strlen($raw) > 50000) {
        jsonOut(['ok' => false, 'error' => 'Návrh je příliš velký nebo neplatný.'], 413);
    }

    $project = json_decode($raw, true);
    if (!is_array($project) || !isset($project['boxes']) || !is_array($project['boxes'])) {
        jsonOut(['ok' => false, 'error' => 'Návrh se nepodařilo připravit.'], 400);
    }

    // The client already builds the compact project representation. Store it
    // as JSON, but cap the number of boxes/cells so a crafted request cannot
    // turn a share link into an arbitrary database blob.
    if (count($project['boxes']) > 400) {
        jsonOut(['ok' => false, 'error' => 'Návrh obsahuje příliš mnoho boxů.'], 413);
    }

    $key = '';
    for ($i = 0; $i < 5; $i++) {
        $candidate = shareKey();
        try {
            $pdo->prepare(
                'INSERT INTO shared_projects (share_key, user_id, project_json, created_at) VALUES (?, ?, ?, ?)'
            )->execute([$candidate, (int) $user['id'], json_encode($project, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), time()]);
            $key = $candidate;
            break;
        } catch (PDOException $e) {
            if (stripos($e->getMessage(), 'UNIQUE') === false) {
                throw $e;
            }
        }
    }

    if ($key === '') {
        jsonOut(['ok' => false, 'error' => 'Odkaz se nepodařilo vytvořit.'], 500);
    }

    // The admin can explicitly define the public base URL used for share
    // links. This is useful when the application is behind a reverse proxy,
    // has a public alias, or the detected Host is not the canonical address.
    // Empty setting keeps the automatic deployment-aware behaviour.
    $configuredBase = trim((string) Settings::get('share_base_url'));
    if ($configuredBase !== '') {
        $parts = parse_url($configuredBase);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])
            || !in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)
            || isset($parts['query']) || isset($parts['fragment'])) {
            jsonOut(['ok' => false, 'error' => 'Základ sdíleného odkazu v administraci není platná HTTP(S) adresa.'], 500);
        }
        $base = rtrim($configuredBase, '/');
    } else {
        // Detect the actual deployed application path instead of hard-coding
        // a development/hosting URL. This also works in /d_beta/ etc.
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/api/share.php'));
        $appPath = rtrim(str_replace('\\', '/', dirname(dirname($script))), '/');
        if ($appPath === '/' || $appPath === '.') { $appPath = ''; }
        $https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
            || ((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $scheme = $https ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        if ($host === '') {
            jsonOut(['ok' => false, 'error' => 'Nelze určit adresu webu pro sdílení.'], 500);
        }
        $base = $scheme . '://' . $host . $appPath;
    }
    // Always use the query form. It works both with normal Apache/PHP
    // installs and with hosts that do not have a rewrite rule for /<key>.
    // The configured base may point either to the app directory or directly
    // to index.php.
    if (preg_match('~/index\.php$~i', $base)) {
        $url = $base . '?share=' . rawurlencode($key);
    } else {
        $url = $base . '/index.php?share=' . rawurlencode($key);
    }

    jsonOut([
        'ok' => true,
        'key' => $key,
        'url' => $url,
    ]);
}

if ($method === 'GET') {
    $key = trim((string) ($_GET['key'] ?? ''));
    if (!preg_match('/^[A-Za-z0-9_-]{16}$/', $key)) {
        jsonOut(['ok' => false, 'error' => 'Neplatný odkaz.'], 404);
    }

    $st = $pdo->prepare('SELECT project_json FROM shared_projects WHERE share_key = ? LIMIT 1');
    $st->execute([$key]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        jsonOut(['ok' => false, 'error' => 'Sdílený návrh neexistuje.'], 404);
    }

    $pdo->prepare('UPDATE shared_projects SET last_access_at = ? WHERE share_key = ?')
        ->execute([time(), $key]);

    $project = json_decode((string) $row['project_json'], true);
    if (!is_array($project)) {
        jsonOut(['ok' => false, 'error' => 'Sdílený návrh je poškozený.'], 500);
    }

    jsonOut(['ok' => true, 'project' => $project]);
}

header('Allow: GET, POST');
jsonOut(['ok' => false], 405);
