<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/View.php';

if (Auth::user()) { header('Location: account.php'); exit; }

$lang = $_COOKIE['h3d_lang'] ?? Settings::get('default_lang');
$lang = ($lang === 'cs') ? 'cs' : 'en';
$theme = 'light';$sent  = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::checkCsrf();

    [$capOk, $capMsg] = Captcha::verify($_POST);
    $email = trim((string) ($_POST['email'] ?? ''));

    if (!$capOk) {
        $error = $capMsg;
    } elseif ($email === '') {
        $error = __('reset.needemail');
    } elseif (!Mailer::enabled()) {
        $error = __('reset.nomail');
    } else {
        $token = Auth::beginReset($email);
        if ($token !== null) {
            Notify::$lang = Auth::userLang(Auth::byEmail($email));
            Mailer::notify(
                $email,
                Notify::passwordReset(originUrl() . '/change-password.php?token=' . urlencode($token), 2),
                'password_reset'
            );
        }
        // Same answer either way, so this can't be used to probe for accounts.
        $sent = true;
    }
}

$assetVersion = max(array_map(
    static fn(string $f): int => @filemtime(__DIR__ . '/' . $f) ?: 0,
    ['assets/base.css', 'assets/ui.css', 'assets/auth.css', 'assets/captcha.js']
)) ?: time();
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e(__('reset.title')) ?> · <?= e(Settings::get('brand_label')) ?></title>
<link rel="icon" href="assets/icons/favicon.svg" type="image/svg+xml">
<link rel="stylesheet" href="assets/base.css?v=<?= $assetVersion ?>">
<link rel="stylesheet" href="assets/ui.css?v=<?= $assetVersion ?>">
<link rel="stylesheet" href="assets/auth.css?v=<?= $assetVersion ?>">
</head>
<body class="<?= $theme === 'light' ? 'light' : '' ?>">
<div class="auth-page">
  <div class="auth-card">
    <div class="auth-brand">
      <img src="assets/icons/favicon.svg" alt="">
      <span><b><?= e(Settings::get('brand_label')) ?></b></span>
    </div>
    <p class="auth-lede"><?= e(__('reset.title')) ?></p>

    <?php if ($sent): ?>
      <div class="auth-ok"><?= e(__('reset.sent')) ?></div>
      <p class="auth-lede" style="margin-bottom:0"><?= e(__('reset.senthint')) ?></p>
      <a class="btn primary" href="login.php" style="text-align:center;display:block;text-decoration:none"><?= e(__('auth.signin.btn')) ?></a>
    <?php else: ?>
      <form method="post" class="auth-form solo">
        <?= Auth::csrfField() ?>
        <?php if ($error !== ''): ?><div class="auth-err"><?= e($error) ?></div><?php endif; ?>
        <p class="hint" style="margin:0 0 4px"><?= e(__('reset.lede')) ?></p>
        <label for="email"><?= e(__('auth.email')) ?></label>
        <input id="email" name="email" type="email" required autocomplete="email"
               value="<?= e((string) ($_POST['email'] ?? '')) ?>">
        <div style="margin-top:14px"><?= Captcha::field() ?></div>
        <button class="btn primary" type="submit"><?= e(__('reset.send')) ?></button>
      </form>
    <?php endif; ?>

    <a class="auth-back" href="login.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
      <?= e(__('auth.signin.btn')) ?>
    </a>
  </div>
</div>
<?php if (Captcha::enabled() && !$sent) { echo '<script src="assets/captcha.js"></script>'; } ?>
</body>
</html>
