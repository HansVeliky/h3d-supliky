<?php
declare(strict_types=1);

/**
 * First run: make the database and the first administrator.
 *
 * The whole site sends people here while there is no account to sign in as,
 * which is the only moment this page will do anything. Once an administrator
 * exists it refuses outright - so it can be left on the server without being
 * a way in.
 */

require_once __DIR__ . '/lib/bootstrap.php';

/**
 * Is the portal still waiting to be set up?
 *
 * An EMPTY user table, not merely a missing administrator. That difference
 * is the whole security of this page: while it answers yes, anybody who can
 * reach it may create an administrator, and the form below promotes an
 * existing address rather than failing on a duplicate. On a site that has
 * users but has somehow lost its admin - a bad settings save, a row deleted
 * by hand - the old test said yes, and the first passer-by could take over
 * any account by typing its e-mail.
 *
 * Losing the administrator on a live site is recovered from the command
 * line instead (php reset.php --list), which cannot be reached over HTTP.
 */
function install_needed(): bool
{
    try {
        $pdo    = Db::pdo();
        $admins = (int) $pdo->query(
            "SELECT COUNT(*) FROM users WHERE role = 'admin' OR is_admin = 1"
        )->fetchColumn();
        $users  = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
        return $admins === 0 && $users === 0;
    } catch (Throwable $e) {
        // No database yet at all - that certainly counts as needing setup.
        return true;
    }
}

$done  = !install_needed();
$error = '';
$ok    = '';

if (!$done && $_SERVER['REQUEST_METHOD'] === 'POST') {
    // No CSRF token here on purpose: there is no session worth protecting
    // yet, and the guard that matters is the one above - this only ever runs
    // while the site has no administrator.
    $email = Auth::normaliseEmail((string) ($_POST['email'] ?? ''));
    $nick  = trim((string) ($_POST['nickname'] ?? ''));
    $pass  = (string) ($_POST['password'] ?? '');
    $site  = trim((string) ($_POST['site_name'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Zadej platný e-mail.';
    } elseif (($weak = Auth::passwordProblem($pass)) !== null) {
        // The operator account gets the same rules as everybody else.
        $error = __($weak);
    } else {
        try {
            $pdo = Db::pdo();
            $st  = $pdo->prepare('SELECT id FROM users WHERE email = ?');
            $st->execute([$email]);
            $existing = (int) ($st->fetchColumn() ?: 0);

            if ($existing > 0) {
                // An account with this address is already here (a fresh
                // install that got half way, say) - promote it instead of
                // failing on a duplicate.
                $pdo->prepare(
                    'UPDATE users SET pass_hash = ?, role = "admin", is_admin = 1,
                            verified_at = COALESCE(verified_at, ?) WHERE id = ?'
                )->execute([password_hash($pass, PASSWORD_DEFAULT), time(), $existing]);
            } else {
                $pdo->prepare(
                    'INSERT INTO users (email, nickname, pass_hash, role, is_admin, credits,
                                        created_at, verified_at, lang)
                     VALUES (?, ?, ?, "admin", 1, 0, ?, ?, "cs")'
                )->execute([$email, $nick, password_hash($pass, PASSWORD_DEFAULT), time(), time()]);
            }

            if ($site !== '') {
                Settings::set(['site_name' => $site]);
            }

            $done = true;
            $ok   = 'Hotovo. Přihlas se a rovnou si projdi Nastavení.';
        } catch (Throwable $e) {
            $error = 'Instalace selhala: ' . $e->getMessage();
        }
    }
}

$assetVersion = @filemtime(__DIR__ . '/assets/panel.css') ?: time();
header('X-Content-Type-Options: nosniff');
?>
<!doctype html>
<html lang="cs">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Instalace · <?= e(Settings::get('site_name')) ?></title>
<link rel="icon" href="assets/icons/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/panel.css?v=<?= $assetVersion ?>">
<meta name="robots" content="noindex">
</head>
<body>
<div class="wrap narrow" style="padding-top:60px">

  <h1>Instalace portálu</h1>

  <?php if ($done && $ok === ''): ?>
    <div class="msg info">
      Portál je už nastavený - v databázi jsou účty. Tahle stránka proto nic
      nedělá; přihlas se běžnou cestou. Kdyby se ztratil administrátor,
      obnovíš ho z příkazové řádky: <code>php reset.php --list</code>.
    </div>
    <p><a class="btn primary" href="login.php">Přejít na přihlášení</a></p>

  <?php elseif ($done): ?>
    <div class="msg ok"><?= e($ok) ?></div>
    <p class="lede">
      Doporučuju hned zkontrolovat: <strong>Nastavení</strong> (název webu,
      e-maily), <strong>Exporty a kredity</strong> (co dostane nový uživatel)
      a <strong>Prodej a peníze</strong>, pokud budeš prodávat.
    </p>
    <p><a class="btn primary" href="login.php">Přihlásit se</a></p>

  <?php else: ?>
    <p class="lede">
      Zatím tu není žádný administrátor, takže se do portálu nedá dostat.
      Založ ho tady - potom už tahle stránka nic neudělá.
    </p>

    <?php if ($error !== ''): ?><div class="msg err"><?= e($error) ?></div><?php endif; ?>

    <form method="post" class="card" data-busy="Zakládám…">
      <label for="site_name">Název webu</label>
      <input id="site_name" name="site_name" type="text"
             value="<?= e(Settings::get('site_name')) ?>">

      <label for="email">E-mail administrátora</label>
      <input id="email" name="email" type="email" required autocomplete="username">

      <label for="nickname">Zobrazované jméno</label>
      <input id="nickname" name="nickname" type="text" maxlength="20" autocomplete="off">

      <label for="password">Heslo (aspoň 8 znaků, velké a malé písmeno, číslice)</label>
      <input id="password" name="password" type="password" minlength="8" required autocomplete="new-password">

      <button class="btn primary" type="submit" style="margin-top:16px">Založit administrátora</button>
    </form>

    <p class="hint">
      Databáze se vytvoří sama při prvním načtení, včetně všech tabulek.
      Pokud tenhle formulář hlásí chybu zápisu, nemá web právo psát do
      složky <code>data/</code>.
    </p>
  <?php endif; ?>

</div>
<script src="assets/panel.js?v=<?= $assetVersion ?>"></script>
</body>
</html>
