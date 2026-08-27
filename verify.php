<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/View.php';

$token = (string) ($_GET['token'] ?? '');
[$ok, $message, $user] = Verify::confirm($token);

// Confirming from a different browser than the one that registered is
// normal — people open mail on their phone — so this never requires a
// session and never signs anybody in.
page_head(__('ver.title'));
?>
<div class="wrap narrow">
  <h1><?= e(__('ver.title')) ?></h1>

  <?php if ($ok): ?>
    <div class="msg ok"><?= e($message) ?></div>
    <?php if ($user && (int) $user['credits'] > 0): ?>
      <p class="hint"><?= e(__('ver.balance', Cred::fmt((int) $user['credits']))) ?></p>
    <?php endif; ?>
    <a class="btn primary" href="login.php"><?= e(__('auth.signin.btn')) ?></a>
    <a class="btn" href="index.php"><?= e(__('ver.continue')) ?></a>
  <?php else: ?>
    <div class="msg err"><?= e($message) ?></div>
    <p class="hint">
      If the link has expired you can request a new one from your account page
      after signing in.
    </p>
    <a class="btn" href="login.php"><?= e(__('auth.signin.btn')) ?></a>
  <?php endif; ?>
</div>
<?php page_foot();
