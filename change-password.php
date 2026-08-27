<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/View.php';

/*
 * Two ways in:
 *
 *  - with a token from a reset email, signed in or not;
 *  - signed in with a password an administrator handed out, which the account
 *    holder has to replace before going anywhere else.
 */
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));

$byToken = $token !== '' ? Auth::userForResetToken($token) : null;
$signedIn = Auth::user();

// requireUser would bounce straight back here, so this page asks for the
// session itself and tolerates the pending state.
$target = $byToken ?: $signedIn;
$forced = $byToken === null && Auth::mustChangePassword($signedIn);

if (!$target) {
    page_head(__('reset.title'));
    echo '<div class="wrap narrow"><h1>' . e(__('reset.badlink')) . '</h1>';
    flash('err', __('reset.badlinkbody'));
    echo '<a class="btn primary" href="forgot.php">' . e(__('reset.again')) . '</a> ';
    echo '<a class="btn" href="login.php">' . e(__('auth.signin.btn')) . '</a></div>';
    page_foot();
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::checkCsrf();

    $new    = (string) ($_POST['new'] ?? '');
    $repeat = (string) ($_POST['new2'] ?? '');

    // The same rules as the sign-up form; Auth::passwordProblem() owns them.
    if (($weak = Auth::passwordProblem($new)) !== null) {
        $error = __($weak);
    } elseif ($new !== $repeat) {
        $error = __('reset.mismatch');
    } elseif ($forced && password_verify($new, (string) $target['pass_hash'])) {
        // Keeping the handed-out password would leave it valid in an inbox.
        $error = __('reset.samepass');
    } else {
        Auth::setPassword((int) $target['id'], $new, false);

        // A reset is also how somebody locked out gets back in, so the token
        // path signs them in rather than asking for the password they have
        // just set.
        Auth::start();
        $_SESSION['uid'] = (int) $target['id'];
        session_regenerate_id(true);

        redirect_after_post('account.php', __('reset.done'));
    }
}

page_head(__('reset.settitle'));
?>
<div class="wrap narrow">
  <h1><?= e(__('reset.settitle')) ?></h1>

  <?php if ($forced): ?>
    <div class="msg warn"><?= e(__('reset.forced')) ?></div>
  <?php endif; ?>

  <form method="post" class="card" data-busy="<?= e(__('busy.reset')) ?>">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="token" value="<?= e($token) ?>">
    <?php flash('err', $error); ?>

    <p class="hint" style="margin-top:0"><?= e($target['email']) ?></p>

    <label for="new"><?= e(__('reset.newpass')) ?></label>
    <input id="new" name="new" type="password" required autocomplete="new-password" minlength="8">

    <label for="new2"><?= e(__('reset.repeat')) ?></label>
    <input id="new2" name="new2" type="password" required autocomplete="new-password" minlength="8">

    <div class="hint"><?= e(__('reset.rule')) ?></div>

    <button class="btn primary" type="submit" style="margin-top:14px"><?= e(__('reset.save')) ?></button>
    <?php if (!$forced): ?>
      <a class="btn" href="login.php"><?= e(__('acc.cancel')) ?></a>
    <?php else: ?>
      <a class="btn" href="logout.php"><?= e(__('nav.signout')) ?></a>
    <?php endif; ?>
  </form>
</div>
<?php page_foot();
