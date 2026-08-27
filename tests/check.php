<?php
declare(strict_types=1);

/**
 * One command that says whether the portal is still whole.
 *
 *   php tests/check.php
 *
 * It exists because everything on the roadmap - splitting the administration
 * into modules, moving features into packages, reorganising the CSS - means
 * touching files that nothing currently watches. This is the net under that
 * work: every PHP file is parsed, the browser bundle is built and parsed,
 * the two languages are compared key by key, the mesh generator is hammered,
 * and every administration tab is rendered looking for a fatal.
 *
 * Nothing here touches the live database. The tab pass runs each tab in its
 * own process against a throwaway data directory.
 */

$root = dirname(__DIR__);
$fail = 0;
$warn = 0;

function head(string $s): void { echo "\n$s\n" . str_repeat('-', 60) . "\n"; }
function ok(string $s): void { echo "  OK    $s\n"; }
function bad(string $s): void { global $fail; $fail++; echo "  CHYBA $s\n"; }
function note(string $s): void { global $warn; $warn++; echo "  -     $s\n"; }

$php = PHP_BINARY;

// ---- 1. every PHP file parses -------------------------------------------
head('1. Syntaxe PHP');
$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    $path = str_replace('\\', '/', $f->getPathname());
    if (!str_ends_with($path, '.php') || str_contains($path, '/data/')) { continue; }
    $files[] = $path;
}
sort($files);
$broken = 0;
foreach ($files as $f) {
    $out = [];
    exec(escapeshellarg($php) . ' -l ' . escapeshellarg($f) . ' 2>&1', $out, $rc);
    if ($rc !== 0) { bad(basename($f) . ': ' . implode(' ', $out)); $broken++; }
}
if (!$broken) { ok(count($files) . ' souborů bez chyby'); }

// ---- 2. the browser bundle is valid JavaScript --------------------------
head('2. Klientský balík');
if (!defined('H3D_APP')) { define('H3D_APP', true); }
ob_start();
require $root . '/lib/client.js.php';
$js = (string) ob_get_clean();
if (strlen($js) < 20000) {
    bad('bundle je podezřele malý (' . strlen($js) . ' B)');
} else {
    $tmp = sys_get_temp_dir() . '/h3d_check_bundle.js';
    file_put_contents($tmp, $js);
    $o = [];
    exec('node --check ' . escapeshellarg($tmp) . ' 2>&1', $o, $rc);
    $said = implode(' ', $o);
    if ($rc === 0) {
        ok('bundle se parsuje (' . round(strlen($js) / 1024) . ' kB)');
    } elseif (str_contains($said, 'not recognized') || str_contains($said, 'nenalezen')) {
        note('node není k dispozici, kontrola JS přeskočena');
    } else {
        bad('bundle: ' . $said);
    }
    @unlink($tmp);
}

// ---- 3. the two languages say the same things ---------------------------
head('3. Jazyky (CZ = EN)');
/*
 * Every key on one side must exist on the other. A missing translation is
 * invisible until somebody switches language and finds an English sentence
 * in a Czech interface - or the bare key.
 */
$src = file_get_contents($root . '/lib/client.js.php');
$keysOf = static function (string $block): array {
    preg_match_all('/\'([a-zA-Z0-9_.]+)\'\s*:/', $block, $m);
    return array_values(array_unique($m[1]));
};
/*
 * A block ends at ITS closing brace, found by counting. Reading to the end
 * of the file instead drags in every quoted string that follows the
 * dictionary and reports "missing keys" that were never keys.
 */
$blockAt = static function (string $src, string $marker): ?string {
    $at = strpos($src, $marker);
    if ($at === false) { return null; }
    $open  = strpos($src, '{', $at);
    $depth = 0;
    for ($i = $open, $n = strlen($src); $i < $n; $i++) {
        if ($src[$i] === '{') { $depth++; }
        elseif ($src[$i] === '}') {
            $depth--;
            if ($depth === 0) { return substr($src, $open, $i - $open); }
        }
    }
    return null;
};
$enBlock = $blockAt($src, "\n  en:{");
$csBlock = $blockAt($src, "\n  cs:{");
if ($enBlock === null || $csBlock === null) {
    note('slovník I18N nenalezen, kontrola přeskočena');
} else {
    $en = $keysOf($enBlock);
    $cs = $keysOf($csBlock);
    $missingCs = array_values(array_diff($en, $cs));
    $missingEn = array_values(array_diff($cs, $en));
    if ($missingCs) {
        bad('chybí česky (' . count($missingCs) . '): ' . implode(', ', array_slice($missingCs, 0, 10)));
    }
    if ($missingEn) {
        bad('chybí anglicky (' . count($missingEn) . '): ' . implode(', ', array_slice($missingEn, 0, 10)));
    }
    if (!$missingCs && !$missingEn) { ok(count($en) . ' klíčů v obou jazycích'); }
}

// ---- 4. the mesh generator ----------------------------------------------
head('4. Geometrie');
require_once $root . '/lib/Geometry.php';
$shapes = [
    'obdélník' => [[0, 0], [1, 0], [0, 1], [1, 1]],
    'L'        => [[0, 0], [0, 1], [0, 2], [1, 2]],
    'T'        => [[0, 0], [1, 0], [2, 0], [1, 1], [1, 2]],
    'S'        => [[0, 0], [1, 0], [1, 1], [2, 1]],
    'kruh'     => [[0, 0], [1, 0], [2, 0], [0, 1], [2, 1], [0, 2], [1, 2], [2, 2]],
];
$checked = 0;
foreach ([0.0, 4.0] as $R) {
    foreach ([0.0, 0.4, 2.0] as $gap) {
        foreach ([1.6, 2.0, 3.0] as $wall) {
            foreach ($shapes as $name => $cells) {
                $cfg = ['dw' => 250, 'dd' => 250, 'dh' => 50, 'gap' => $gap, 'wall' => $wall,
                        'bottom' => 2, 'radius' => $R, 'cols' => 5, 'rows' => 5, 'outer' => 0.0];
                $box = ['cells' => array_map(static fn($c) => ['x' => $c[0], 'y' => $c[1]], $cells)];
                try {
                    $m = Geometry::polyMesh($cfg, $box);
                } catch (Throwable $e) {
                    // A wall too thick for the cell is a legitimate refusal.
                    if (!str_contains($e->getMessage(), 'Wall thickness')) {
                        bad(sprintf('%s R=%.0f mezera=%.1f stěna=%.1f: %s', $name, $R, $gap, $wall, $e->getMessage()));
                    }
                    continue;
                }
                $checked++;
                if (!Geometry::isWatertight($m)) {
                    bad(sprintf('%s R=%.0f mezera=%.1f stěna=%.1f: model není uzavřený', $name, $R, $gap, $wall));
                }
            }
        }
    }
}
if ($checked) { ok("$checked tvarů prošlo"); }

// ---- 5. every administration tab renders --------------------------------
head('5. Administrace');
/*
 * Each tab is rendered in its own process against a throwaway database with
 * an administrator faked into the session. This is the pass that catches a
 * fatal on a tab nobody happened to open after a change - exactly what
 * splitting this file into modules is going to risk.
 */
/*
 * The list comes from ADMIN_TABS, with the directory checked against it.
 *
 * It used to be scraped out of the if/elseif chain in admin/index.php. The
 * moment that chain moved into admin/tabs/, the scrape found one branch and
 * this pass went on printing OK while checking almost nothing. A net that
 * quietly stops catching is worse than no net, so the count is asserted too.
 */
$tabs = [];
$adminSrc = (string) file_get_contents($root . '/admin/index.php');
if (preg_match('/const ADMIN_TABS = \[(.*?)\];/s', $adminSrc, $m)
    && preg_match_all("/'([a-z]+)'/", $m[1], $mm)) {
    $tabs = array_values(array_unique($mm[1]));
}
foreach (glob($root . '/admin/tabs/*.php') ?: [] as $f) {
    $name = basename($f, '.php');
    if (!in_array($name, $tabs, true)) { bad("záložka $name má soubor, ale není v ADMIN_TABS"); }
}
foreach ($tabs as $name) {
    if (!is_file($root . '/admin/tabs/' . $name . '.php')) { bad("záložka $name je v ADMIN_TABS, ale soubor chybí"); }
}
if (count($tabs) < 15) { bad('nalezeno jen ' . count($tabs) . ' záložek - kontrola sama je nejspíš rozbitá'); }
$harness = sys_get_temp_dir() . '/h3d_admin_tab.php';
file_put_contents($harness, <<<'HARNESS'
<?php
$root = $argv[1];
$tab  = $argv[2];
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['HTTP_HOST']      = 'localhost';
$_SERVER['REQUEST_URI']    = '/admin/index.php?tab=' . $tab;
$_SERVER['SCRIPT_NAME']    = '/admin/index.php';
$_GET['tab'] = $tab;
require $root . '/lib/bootstrap.php';
$pdo = Db::pdo();
$pdo->prepare('INSERT INTO users (email, nickname, pass_hash, role, is_admin, credits, created_at, verified_at, lang)
               VALUES (?, ?, ?, "admin", 1, 0, ?, ?, "cs")')
    ->execute(['check@local', 'check', password_hash('x', PASSWORD_DEFAULT), time(), time()]);
$id = (int) $pdo->lastInsertId();
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
foreach (['uid', 'h3d_uid', 'user_id'] as $k) { $_SESSION[$k] = $id; }
ob_start();
require $root . '/admin/index.php';
$html = (string) ob_get_clean();
if (strlen($html) < 200) { fwrite(STDERR, 'prazdny vystup'); exit(2); }
exit(0);
HARNESS);

if (!$tabs) {
    note('žádné záložky nenalezeny');
} else {
    $failed = [];
    foreach ($tabs as $tab) {
        $dir = sys_get_temp_dir() . '/h3d_check_' . bin2hex(random_bytes(4));
        @mkdir($dir, 0777, true);
        putenv('H3D_DATA_DIR=' . $dir);
        $out = [];
        exec(escapeshellarg($php) . ' ' . escapeshellarg($harness) . ' '
             . escapeshellarg($root) . ' ' . escapeshellarg($tab) . ' 2>&1', $out, $rc);
        $said = trim(implode(' ', $out));
        if ($rc !== 0 || stripos($said, 'Fatal error') !== false || stripos($said, 'Uncaught') !== false) {
            $failed[] = $tab . ': ' . substr($said, 0, 130);
        }
        foreach (glob($dir . '/*') ?: [] as $f) { @unlink($f); }
        @rmdir($dir);
    }
    putenv('H3D_DATA_DIR');
    if ($failed) {
        foreach ($failed as $t) { bad('záložka ' . $t); }
    } else {
        ok(count($tabs) . ' záložek se vykreslilo');
    }
}
@unlink($harness);

// ---- 6+7. behaviour suites ----------------------------------------------
/*
 * The rules that are easy to break without noticing, each in its own file
 * and its own process: accounts (the address is the only way in, a display
 * name is a label) and the panel (a badge counts things to do, and $tab can
 * never name a file off the list). Failures are repeated here line by line,
 * so one command still says everything.
 */
$suite = static function (string $file, string $title) use ($php): void {
    head($title);
    $out = [];
    exec(escapeshellarg($php) . ' ' . escapeshellarg(__DIR__ . '/' . $file) . ' 2>&1', $out, $rc);
    if ($rc === 0) { ok(trim((string) end($out))); return; }
    $any = false;
    foreach ($out as $line) {
        if (str_contains($line, 'CHYBA')) { bad(trim($line)); $any = true; }
    }
    if (!$any) { bad($file . ' selhalo: ' . substr(trim(implode(' ', $out)), 0, 150)); }
};
$suite('accounts.php', '6. Účty');
$suite('panel.php', '7. Panel');
$suite('markup.php', '8. Značky');
$suite('shapes.php', '9. Tvary a příčky');

echo "\n" . str_repeat('-', 60) . "\n";
if ($fail) {
    echo "NEPROŠLO: $fail " . ($fail === 1 ? 'chyba' : 'chyb')
        . ($warn ? ", $warn přeskočeno" : '') . "\n";
    exit(1);
}
echo 'VŠE V POŘÁDKU' . ($warn ? " ($warn přeskočeno)" : '') . " - můžeš nahrávat.\n";
