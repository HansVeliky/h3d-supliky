<?php
declare(strict_types=1);

/**
 * Accounts: registration, signing in, display names.
 *
 *   php tests/accounts.php
 *
 * Runs against a throwaway database, so it can be run on a live machine
 * without touching anything real. It covers the rules that are easy to
 * break silently: that the address is the only way in, that a display name
 * is a label rather than an identifier, and that the names reserved for the
 * shop stay reserved however they are spelled.
 */

$dir = sys_get_temp_dir() . '/h3d_accounts_' . bin2hex(random_bytes(4));
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

echo "Účty\n" . str_repeat('-', 60) . "\n";

// ---- registration -------------------------------------------------------
[$r, $msg] = Auth::register('Jan.Novak@Example.com', 'Heslo123');
ok('registrace projde', $r, $msg);

[$r, $msg] = Auth::register('jan.novak@example.com', 'Heslo123');
ok('stejná adresa podruhé neprojde', !$r);

[$r, $msg] = Auth::register('kratke@example.com', 'krat1A');
ok('krátké heslo neprojde', !$r);

// ---- signing in ---------------------------------------------------------
/*
 * The portal can insist on a confirmed address before the first sign-in.
 * That rule is not what this file is about, so the account is confirmed
 * here the way clicking the link in the mail would.
 */
Db::pdo()->prepare('UPDATE users SET verified_at = ? WHERE verified_at IS NULL')->execute([time()]);

[$r, $msg] = Auth::login('jan.novak@example.com', 'Heslo123');
ok('přihlášení e-mailem', $r, $msg);

[$r, $msg] = Auth::login('JAN.NOVAK@EXAMPLE.COM', 'Heslo123');
ok('e-mail nezávisí na velikosti písmen', $r, $msg);

/*
 * The point of the change: the display name is not a way in. It used to be,
 * and the login query still has to prove it no longer is.
 */
$me = Db::pdo()->query("SELECT id, nickname FROM users WHERE email = 'jan.novak@example.com'")->fetch();
ok('registrace odvodila jméno z adresy', ($me['nickname'] ?? '') !== '', 'nickname=' . ($me['nickname'] ?? ''));

[$r, $msg] = Auth::login((string) $me['nickname'], 'Heslo123');
ok('přihlášení jménem NEPROJDE', !$r);

[$r, $msg] = Auth::login('jan.novak@example.com', 'spatne');
ok('špatné heslo neprojde', !$r);

// ---- display names ------------------------------------------------------
[$r, $msg] = Auth::changeNickname((int) $me['id'], 'Konstruktér');
ok('diakritika ve jménu projde', $r, $msg);

[$r, $msg] = Auth::changeNickname((int) $me['id'], 'ab');
ok('dvouznakové jméno neprojde', !$r);

[$r, $msg] = Auth::changeNickname((int) $me['id'], 'kdo@example.com');
ok('adresa jako jméno neprojde', !$r);

foreach (['admin', 'Admin', 'SPRÁVCE', 'spravce', ' Podpora '] as $word) {
    [$r, $msg] = Auth::changeNickname((int) $me['id'], $word);
    ok("zabrané jméno '$word' neprojde", !$r);
}

[$r, $msg] = Auth::changeNickname((int) $me['id'], '');
ok('prázdné jméno se dá uložit', $r, $msg);

/*
 * Two people may look the same in the chat, and that is deliberate: a name
 * that is only a label has no reason to be first-come-first-served.
 */
Auth::register('druhy@example.com', 'Heslo123');
$other = (int) Db::pdo()->query("SELECT id FROM users WHERE email = 'druhy@example.com'")->fetchColumn();
Auth::changeNickname((int) $me['id'], 'Jan');
[$r, $msg] = Auth::changeNickname($other, 'Jan');
ok('stejné jméno dvakrát je v pořádku', $r, $msg);

// ---- reserved list is editable ------------------------------------------
Settings::set(['reserved_names' => 'sef']);
[$r, $msg] = Auth::changeNickname($other, 'Šéf');
ok('seznam z nastavení platí (Šéf ~ sef)', !$r);
[$r, $msg] = Auth::changeNickname($other, 'Admin');
ok('slovo mimo seznam už projde', $r, $msg);

// ---- password rules -----------------------------------------------------
/*
 * Eight characters, an upper-case letter, a lower-case letter and a digit.
 * The rules used to be enforced at the sign-up form alone: the reset link,
 * the change-password screen and the installer each asked for eight
 * characters and nothing else, so an account could come out of a reset
 * weaker than it was allowed to be created.
 */
foreach ([
    ['Krat1A',        'pw.short',   'krátké'],
    ['HESLO12345',    'pw.nolower', 'bez malého písmene'],
    ['heslo12345',    'pw.noupper', 'bez velkého písmene'],
    ['HesloBezCisla', 'pw.nodigit', 'bez číslice'],
] as [$pw, $key, $why]) {
    ok("heslo $why neprojde", Auth::passwordProblem($pw) === $key,
        var_export(Auth::passwordProblem($pw), true));
}
ok('heslo se vším projde', Auth::passwordProblem('Heslo123') === null);

[$r, $msg] = Auth::register('slabe@example.com', 'heslo123');
ok('registrace slabé heslo odmítne', !$r);
ok('a řekne, co chybí', stripos($msg, 'upper') !== false || stripos($msg, 'velké') !== false, $msg);
ok('slabé heslo účet nezaložilo',
    (int) Db::pdo()->query("SELECT COUNT(*) FROM users WHERE email = 'slabe@example.com'")->fetchColumn() === 0);

/*
 * Every screen that sets a password has to ask the same question. A bare
 * length test creeping back into one of them is how the rules drifted apart
 * in the first place, so it is worth failing on sight.
 */
foreach (['account.php', 'change-password.php', 'install.php'] as $file) {
    $src = (string) file_get_contents(dirname(__DIR__) . '/' . $file);
    ok("$file se ptá Auth::passwordProblem()", str_contains($src, 'Auth::passwordProblem('));
    ok("$file nemá vlastní délkovou kontrolu",
        !preg_match('/strlen\(\$(new|pass|password)\)\s*<\s*\d/', $src));
}

// ---- staying signed in --------------------------------------------------
/*
 * The session times out after two hours; the remembered login is what is
 * meant to carry the browser across that. It has to happen in the request
 * that finds the session stale, not the one after it.
 *
 * The report this covers: open the site, be told you are signed out, click
 * Sign in, and be signed in without typing anything - because by then it was
 * a second request, and start() consults the token whenever it finds no user
 * in the session. The page the person was actually looking at did not.
 */
$uid = (int) Db::pdo()->query("SELECT id FROM users WHERE email = 'jan.novak@example.com'")->fetchColumn();
$remember = static function (int $userId, int $expiresAt): string {
    $token = bin2hex(random_bytes(32));
    Db::pdo()->prepare('INSERT INTO remember_tokens (user_id, token_hash, created_at, expires_at, last_used_at)
                        VALUES (?, ?, ?, ?, ?)')
        ->execute([$userId, hash('sha256', $token), time(), $expiresAt, time()]);
    return $token;
};
/** A browser that has been away longer than the idle timeout. */
$goStale = static function (int $userId): void {
    $_SESSION['uid'] = $userId;
    $_SESSION['seen_at'] = time() - 3 * 3600;
    $_SESSION['issued_at'] = time() - 3 * 3600;
};

$_COOKIE['h3d_remember'] = $remember($uid, time() + 86400);
$goStale($uid);
$back = Auth::user();
ok('po vypršení session vrátí trvalé přihlášení uživatele hned',
    $back !== null && (int) $back['id'] === $uid, $back === null ? 'null' : 'id=' . $back['id']);

unset($_COOKIE['h3d_remember']);
$goStale($uid);
ok('bez trvalé cookie vyprší session normálně', Auth::user() === null);

$_COOKIE['h3d_remember'] = $remember($uid, time() - 60);
$goStale($uid);
ok('prošlý token nepustí zpět', Auth::user() === null);

$_COOKIE['h3d_remember'] = $remember($uid, time() + 86400);
Db::pdo()->prepare('UPDATE users SET is_blocked = 1 WHERE id = ?')->execute([$uid]);
$goStale($uid);
ok('zablokovaný účet nepustí zpět', Auth::user() === null);
Db::pdo()->prepare('UPDATE users SET is_blocked = 0 WHERE id = ?')->execute([$uid]);
unset($_COOKIE['h3d_remember']);
unset($_SESSION['uid'], $_SESSION['seen_at'], $_SESSION['issued_at']);

/*
 * And PHP's own collector has to agree with the two hours. Its default is
 * 1440 seconds, so without this the session file could be swept at
 * twenty-four minutes whatever the code above says.
 */
Auth::start();
ok('PHP uklízí sessions až po IDLE_TIMEOUT',
    (int) ini_get('session.gc_maxlifetime') >= 7200, ini_get('session.gc_maxlifetime') . ' s');

// ---- the endpoint that answers about names ------------------------------
/*
 * api/nickname.php was removed once, on the grounds that a display name is
 * nobody's business, and has since been added back to drive a live
 * "is this taken" hint on the forms.
 *
 * Its own comment says nicknames are login identifiers and so not secret.
 * They are not: the sign-in test above proves the name is not a way in, and
 * the address is the only identifier there is. Whether the endpoint should
 * exist at all is therefore an open question for whoever owns the product.
 *
 * What can be pinned down meanwhile is the part that would be a fault under
 * either answer: it must say yes or no and never whose name it is, and it
 * must not answer at all for something that is not a nickname.
 */
$nickApi = dirname(__DIR__) . '/api/nickname.php';
if (is_file($nickApi)) {
    $src = (string) file_get_contents($nickApi);
    ok('api/nickname.php neprozradí, komu jméno patří',
        !preg_match('/[\'"]owner[\'"]\s*=>/', $src) && !str_contains($src, "'user'"),
        'v odpovědi je vlastník');
    ok('api/nickname.php odmítne, co není přezdívka', str_contains($src, 'preg_match'));
    ok('api/nickname.php vrací jen ano/ne', str_contains($src, "'available'") && str_contains($src, "'valid'"));
} else {
    ok('api/nickname.php je pryč', true);
}

echo str_repeat('-', 60) . "\n";
echo $fail ? "NEPROŠLO: $fail z " . ($pass + $fail) . "\n" : "všech $pass kontrol prošlo\n";

foreach (glob($dir . '/*') ?: [] as $f) { @unlink($f); }
@rmdir($dir);
exit($fail ? 1 : 0);
