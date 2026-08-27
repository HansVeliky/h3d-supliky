<?php
declare(strict_types=1);

/**
 * The panel: badge counts and the tab router.
 *
 *   php tests/panel.php
 *
 * The badges are the reason this file exists. A number on a button is only
 * useful if it means one thing, and "how many rows are unread" is not the
 * same as "how many conversations need an answer" - the first counts five
 * for one talkative customer.
 */

$dir = sys_get_temp_dir() . '/h3d_panel_' . bin2hex(random_bytes(4));
@mkdir($dir, 0777, true);
putenv('H3D_DATA_DIR=' . $dir);

require dirname(__DIR__) . '/lib/bootstrap.php';

$pass = 0;
$fail = 0;
function ok(string $what, bool $cond, string $detail = ''): void
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  ok    $what\n"; return; }
    $fail++;
    echo "  CHYBA $what" . ($detail !== '' ? " ($detail)" : '') . "\n";
}

echo "Panel\n" . str_repeat('-', 60) . "\n";

$pdo = Db::pdo();
$pdo->prepare('INSERT INTO users (email, nickname, pass_hash, role, is_admin, credits, created_at, verified_at, lang)
               VALUES (?, ?, ?, "user", 0, 0, ?, ?, "cs")')
    ->execute(['zakaznik@example.com', 'Zákazník', password_hash('x', PASSWORD_DEFAULT), time(), time()]);
$uid = (int) $pdo->lastInsertId();

// ---- messages -----------------------------------------------------------
ok('bez zpráv je odznak prázdný', UserMessages::waitingCount() === 0);

$m1 = UserMessages::create($uid, 'Nejde mi export', 'Zdravím, nejde mi stáhnout model.');
ok('nová konverzace = 1', UserMessages::waitingCount() === 1, (string) UserMessages::waitingCount());

/*
 * The point of the change: one talkative customer is still one thing to
 * deal with. The old count said three here.
 */
$pdo->prepare('UPDATE user_messages SET read_at = ? WHERE id = ?')->execute([time(), $m1]);
UserMessages::addUserReply($uid, $m1, 'A ještě jedna věc.');
UserMessages::addUserReply($uid, $m1, 'A ještě tohle.');
ok('tři zprávy v jedné konverzaci = 1', UserMessages::waitingCount() === 1, (string) UserMessages::waitingCount());

$pdo->prepare("UPDATE user_message_posts SET read_at = ? WHERE message_id = ?")->execute([time(), $m1]);
ok('přečtené = 0', UserMessages::waitingCount() === 0, (string) UserMessages::waitingCount());

/*
 * A member may only have one conversation open at a time, so the first one
 * is closed before the next is opened - which is also how the "closed does
 * not count" rule gets exercised.
 */
$pdo->prepare('UPDATE user_messages SET closed_at = ? WHERE id = ?')->execute([time(), $m1]);
$m2 = UserMessages::create($uid, 'Druhý dotaz', 'Ještě něco.');
ok('nová konverzace po uzavřené = 1', UserMessages::waitingCount() === 1, (string) UserMessages::waitingCount());
$pdo->prepare('UPDATE user_messages SET closed_at = ? WHERE id = ?')->execute([time(), $m2]);
ok('uzavřená konverzace se nepočítá', UserMessages::waitingCount() === 0, (string) UserMessages::waitingCount());

// ---- orders -------------------------------------------------------------
ok('bez objednávek je odznak prázdný', Orders::openCount() === 0);

/*
 * Built from the table itself rather than from a guessed list of columns:
 * orders has grown a few NOT NULL fields over time, and a test that has to
 * be edited every time one appears is a test people stop running.
 */
$mk = static function (string $status) use ($pdo, $uid): void {
    $f = [];
    foreach ($pdo->query('PRAGMA table_info(orders)')->fetchAll(PDO::FETCH_ASSOC) as $c) {
        if ((int) $c['pk'] === 1 || $c['dflt_value'] !== null || (int) $c['notnull'] !== 1) { continue; }
        $type = strtoupper((string) $c['type']);
        $f[$c['name']] = str_contains($type, 'INT') || str_contains($type, 'REAL') || str_contains($type, 'NUM')
            ? 0 : 'x';
    }
    $f['user_id']    = $uid;
    $f['status']     = $status;
    $f['created_at'] = time();
    if (array_key_exists('reference', $f))    { $f['reference'] = bin2hex(random_bytes(4)); }
    if (array_key_exists('access_token', $f)) { $f['access_token'] = bin2hex(random_bytes(8)); }
    $pdo->prepare('INSERT INTO orders (' . implode(',', array_keys($f)) . ') VALUES ('
        . implode(',', array_fill(0, count($f), '?')) . ')')->execute(array_values($f));
};
$mk(Orders::PENDING);
$mk(Orders::ACCEPTED);
$mk(Orders::PAID);
$mk(Orders::CANCELLED);
$mk(Orders::REFUNDED);
ok('počítají se jen nevyřízené (2 z 5)', Orders::openCount() === 2, (string) Orders::openCount());

// ---- the workspace saved to a profile -----------------------------------
/*
 * The print area is part of the workspace, and it is the one number people
 * notice missing: it decides whether a box is flagged as too big, so a
 * forgotten one silently turns the check off.
 */
$allowed = [];
if (preg_match('/\$allowed = \[(.*?)\];/s', (string) file_get_contents(dirname(__DIR__) . '/api/prefs.php'), $pm)
    && preg_match_all("/'([a-zA-Z]+)'/", $pm[1], $pk)) {
    $allowed = $pk[1];
}
foreach (['dw', 'dd', 'dh', 'gap', 'wall', 'bottom', 'radius', 'cols', 'rows', 'maxPrintW', 'maxPrintD', 'outer'] as $key) {
    ok("profil ukládá $key", in_array($key, $allowed, true));
}

$pdo->prepare('UPDATE users SET prefs = ? WHERE id = ?')
    ->execute([json_encode(['maxPrintW' => 256, 'maxPrintD' => 180.5]), $uid]);
$back = json_decode((string) $pdo->query('SELECT prefs FROM users WHERE id = ' . $uid)->fetchColumn(), true);
ok('tisková plocha se vrátí i s desetinou', ($back['maxPrintD'] ?? null) === 180.5, var_export($back['maxPrintD'] ?? null, true));

$client = (string) file_get_contents(dirname(__DIR__) . '/lib/client.js.php');
ok('studio ji posílá', str_contains($client, "maxPrintW:(\$('maxPrintW')?getMM('maxPrintW'):0)||0"));
ok('studio ji načítá', str_contains($client, "['maxPrintW','maxPrintD'].forEach(k=>{"));
// ---- the tab router -----------------------------------------------------
/*
 * $tab decides a filename now, so a value from the query string that is not
 * on the list must never reach the filesystem.
 */
$src = (string) file_get_contents(dirname(__DIR__) . '/admin/index.php');
preg_match('/const ADMIN_TABS = \[(.*?)\];/s', $src, $m);
preg_match_all("/'([a-z]+)'/", $m[1] ?? '', $mm);
$tabs = $mm[1] ?? [];
ok('ADMIN_TABS má všech dvacet záložek', count($tabs) === 20, (string) count($tabs));
ok('router má whitelist', str_contains($src, 'in_array($tab, ADMIN_TABS, true)'));
ok('neznámá záložka spadne na výchozí', str_contains($src, "\$tab = 'overview';"));

foreach ($tabs as $t) {
    if (!is_file(dirname(__DIR__) . '/admin/tabs/' . $t . '.php')) {
        ok("soubor záložky $t", false);
    }
}
ok('každá záložka má svůj soubor', true);

echo str_repeat('-', 60) . "\n";
echo $fail ? "NEPROŠLO: $fail z " . ($pass + $fail) . "\n" : "všech $pass kontrol prošlo\n";

foreach (glob($dir . '/*') ?: [] as $f) { @unlink($f); }
@rmdir($dir);
exit($fail ? 1 : 0);
