<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/View.php';

$lang = $_COOKIE['h3d_lang'] ?? Settings::get('default_lang');
$lang = ($lang === 'cs') ? 'cs' : 'en';
$theme = 'light';[$ok, $result] = Auth::confirmEmailChange((string) ($_GET['token'] ?? ''));

$title = $ok
    ? ($lang === 'cs' ? 'E-mail změněn' : 'Email changed')
    : ($lang === 'cs' ? 'Odkaz neplatný' : 'Link not valid');
$msg = $ok
    ? ($lang === 'cs' ? 'Adresa účtu je teď ' : 'Your account e-mail is now ') . $result . '.'
    : $result;

$assetVersion = max(array_map(
    static fn(string $f): int => @filemtime(__DIR__ . '/' . $f) ?: 0,
    ['assets/base.css', 'assets/ui.css', 'assets/auth.css']
)) ?: time();
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> · <?= e(Settings::get('brand_label')) ?></title>
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
    <p class="auth-lede"><?= e($title) ?></p>
    <div class="<?= $ok ? 'auth-ok' : 'auth-err' ?>" style="margin-top:6px"><?= e($msg) ?></div>
    <a class="auth-back" href="account.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
      <?= e($lang === 'cs' ? 'Zpět do účtu' : 'Back to account') ?>
    </a>
  </div>
</div>
</body>
</html>
