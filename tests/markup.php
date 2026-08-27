<?php
declare(strict_types=1);

/**
 * Do the administration pages close the tags they open?
 *
 *   php tests/markup.php
 *
 * A mismatched tag is not a parse error anywhere: PHP is happy, the browser
 * quietly repairs it, and the only symptom is a block that stretches across
 * the screen or a card that swallows the one after it. That is exactly how
 * the ratings table in Zprávy ended up full width - an <article> closed with
 * a </div> - so it is worth one pass over the rendered HTML.
 *
 * The check is deliberately blunt: it counts opening and closing tags of the
 * elements that carry layout. Anything uneven is reported with the tab it
 * came from.
 */

$root = dirname(__DIR__);
$php  = PHP_BINARY;

$pass = 0;
$fail = 0;
function ok(string $what, bool $cond, string $detail = ''): void
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok    $what\n"; return; }
    $fail++;
    echo "  CHYBA $what" . ($detail !== '' ? " ($detail)" : '') . "\n";
}

echo "Značky v administraci\n" . str_repeat('-', 60) . "\n";

$tabs = [];
$src = (string) file_get_contents($root . '/admin/index.php');
if (preg_match('/const ADMIN_TABS = \[(.*?)\];/s', $src, $m)
    && preg_match_all("/'([a-z]+)'/", $m[1], $mm)) {
    $tabs = array_values(array_unique($mm[1]));
}

$harness = sys_get_temp_dir() . '/h3d_markup_tab.php';
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

/*
 * A page with nothing on it proves nothing.
 *
 * Half of what the panel draws sits behind "if there is any". An empty
 * database renders the empty branch of every one of them, which is how the
 * first version of this check passed a tab whose <article> was closed with a
 * </div>: that markup never rendered. So: one customer, one conversation,
 * one rating, one order.
 */
$pdo->prepare('INSERT INTO users (email, nickname, pass_hash, role, is_admin, credits, created_at, verified_at, lang)
               VALUES (?, ?, ?, "user", 0, 25, ?, ?, "cs")')
    ->execute(['zakaznik@example.com', 'Zákazník', password_hash('x', PASSWORD_DEFAULT), time(), time()]);
$customer = (int) $pdo->lastInsertId();

// Through the application's own API: a hand-written INSERT here guessed a
// column that does not exist, threw, and the ratings block this check exists
// for was never rendered at all.
$msg = UserMessages::create($customer, 'Nejde mi export', 'Zdravím, nejde mi stáhnout model.');

foreach ([['good', 'Bylo to rychlé.'], ['partial', ''], ['bad', 'Nepomohlo mi to.']] as [$rating, $comment]) {
    $pdo->prepare('INSERT INTO support_feedback (user_id, message_id, rating, comment, created_at)
                   VALUES (?, ?, ?, ?, ?)')
        ->execute([$customer, $msg, $rating, $comment, time()]);
}

$cols = [];
foreach ($pdo->query('PRAGMA table_info(orders)')->fetchAll(PDO::FETCH_ASSOC) as $c) {
    if ((int) $c['pk'] === 1 || $c['dflt_value'] !== null || (int) $c['notnull'] !== 1) { continue; }
    $type = strtoupper((string) $c['type']);
    $cols[$c['name']] = str_contains($type, 'INT') || str_contains($type, 'REAL') || str_contains($type, 'NUM') ? 0 : 'x';
}
$cols['user_id'] = $customer;
$cols['status'] = 'pending';
$cols['created_at'] = time();
if (array_key_exists('reference', $cols))    { $cols['reference'] = 'TESTREF'; }
if (array_key_exists('access_token', $cols)) { $cols['access_token'] = 'testtoken'; }
$pdo->prepare('INSERT INTO orders (' . implode(',', array_keys($cols)) . ') VALUES ('
    . implode(',', array_fill(0, count($cols), '?')) . ')')->execute(array_values($cols));

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
foreach (['uid', 'h3d_uid', 'user_id'] as $k) { $_SESSION[$k] = $id; }
ob_start();
require $root . '/admin/index.php';
echo (string) ob_get_clean();
HARNESS);

// Elements that hold layout together. Void and text-level tags are left out:
// they are either self-closing or harmless when a browser repairs them.
$watch = ['div', 'article', 'section', 'aside', 'form', 'table', 'thead', 'tbody',
          'tr', 'td', 'th', 'nav', 'main', 'header', 'footer', 'ul', 'ol', 'li', 'fieldset'];

$broken = 0;
foreach ($tabs as $tab) {
    $dir = sys_get_temp_dir() . '/h3d_markup_' . bin2hex(random_bytes(4));
    @mkdir($dir, 0777, true);
    putenv('H3D_DATA_DIR=' . $dir);
    $out = [];
    exec(escapeshellarg($php) . ' ' . escapeshellarg($harness) . ' '
         . escapeshellarg($root) . ' ' . escapeshellarg($tab) . ' 2>&1', $out, $rc);
    $html = implode("\n", $out);
    foreach (glob($dir . '/*') ?: [] as $f) { @unlink($f); }
    @rmdir($dir);

    // A fatal produces almost no HTML, and no HTML has no unpaired tags:
    // without this the pass would call a broken page tidy.
    if ($rc !== 0 || strlen($html) < 1500
        || stripos($html, 'Fatal error') !== false || stripos($html, 'Uncaught') !== false) {
        $broken++;
        ok("$tab se vykreslila", false, substr(trim($html), 0, 120));
        continue;
    }

    foreach ($watch as $tag) {
        $open  = preg_match_all('/<' . $tag . '(?=[\s>])/i', $html);
        $close = preg_match_all('/<\/' . $tag . '\s*>/i', $html);
        if ($open !== $close) {
            $broken++;
            ok("$tab: <$tag>", false, "otevřeno $open, zavřeno $close");
        }
    }
}
@unlink($harness);
putenv('H3D_DATA_DIR');

if (!$broken) { ok(count($tabs) . ' záložek má spárované značky', true); }

echo str_repeat('-', 60) . "\n";
echo $fail ? "NEPROŠLO: $fail z " . ($pass + $fail) . "\n" : "všech $pass kontrol prošlo\n";
exit($fail ? 1 : 0);
