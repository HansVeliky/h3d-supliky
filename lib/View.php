<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Minimal layout helpers for the account and admin screens.
 * $base is the relative path back to the installation root.
 */
/**
 * Appends the file's modification time to an asset URL.
 *
 * Without it a changed stylesheet keeps serving from the browser cache after
 * a deploy, which looks exactly like a broken layout and wastes an hour
 * before somebody thinks to hard-refresh.
 */
function asset(string $base, string $path): string
{
    $file = dirname(__DIR__) . '/' . $path;
    $v    = is_file($file) ? (string) filemtime($file) : '0';

    return e($base . $path) . '?v=' . $v;
}

function page_head(string $title, string $base = ''): void
{
    $user = Auth::user();

    // H3D Light is the single application appearance.
    $isAdminPage = str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/');
    $theme = 'light';
    ?>
<!doctype html>
<html lang="<?= e(Lang::current()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?> · <?= e(Settings::get('site_name')) ?></title>
<link rel="icon" href="<?= e($base) ?>assets/icons/favicon.svg" type="image/svg+xml">
<link rel="icon" href="<?= e($base) ?>assets/icons/favicon.ico" sizes="32x32">
<link rel="stylesheet" href="<?= asset($base, 'assets/panel.css') ?>">
<?php if ($isAdminPage): ?>
<link rel="stylesheet" href="<?= asset($base, 'assets/admin-theme.css') ?>">
<?php endif; ?>
<?php
  // Room the administrator reserved for the host's advertising strip.
  $mt = Settings::int('page_margin_top');
  $mb = Settings::int('page_margin_bottom');
  if ($mt > 0 || $mb > 0):
?>
<style>:root{--page-top:<?= max(0, min(300, $mt)) ?>px;--page-bottom:<?= max(0, min(300, $mb)) ?>px}</style>
<?php endif; ?>
<meta name="robots" content="noindex">
</head>
<body class="<?= trim(($isAdminPage ? 'h3d-light-admin' : '')) ?>">
<div class="topbar">
  <?php $pendingVerification = $user && !Auth::canManageOrders($user) && empty($user['verified_at']); ?>
  <a class="brand" href="<?= e($base . ($pendingVerification ? 'account.php' : 'index.php')) ?>">
    <img src="<?= e($base) ?>assets/icons/icon-48.png" alt="">
    <span><?= e(Settings::get('site_name')) ?></span>
  </a>
  <nav>
    <?php if (!$pendingVerification): ?>
      <a href="<?= e($base) ?>index.php"><?= e(__('nav.studio')) ?></a>
    <?php endif; ?>
    <?php if ($user): ?>
      <a href="<?= e($base) ?>account.php"><?= e(__('nav.account')) ?></a>
      <?php if (!$pendingVerification && Auth::canManageOrders($user)): ?>
        <a href="<?= e($base) ?>admin/index.php"><?= e(__('nav.admin')) ?></a>
      <?php endif; ?>
      <span class="who"><?= e($user['email']) ?> · <?= Auth::isPrivileged($user) ? '&infin;' : e(Cred::fmt((int) $user['credits'])) . ' ' . e(__('nav.credits')) ?></span>
      <a href="<?= e($base) ?>logout.php"><?= e(__('nav.signout')) ?></a>
    <?php else: ?>
      <a href="<?= e($base) ?>login.php"><?= e(__('nav.signin')) ?></a>
      <?php if (Settings::bool('registration_open')): ?>
        <a href="<?= e($base) ?>register.php"><?= e(__('auth.register')) ?></a>
      <?php endif; ?>
    <?php endif; ?>
  </nav>
</div>
    <?php
}

function page_foot(string $base = ''): void
{
    $links = Links::all();
    $cs    = Lang::current() === 'cs';

    echo '<div class="site-links">';
    if ($links) {
        echo '<span class="hint">Další služby:</span>';
        foreach ($links as $l) {
            echo '<a href="' . e($l['url']) . '" target="_blank" rel="noopener">'
               . e($l['label']) . '</a>';
        }
    }
    // Reachable from every page, whether or not the banner is still showing:
    // a choice you cannot find again is not a choice.
    echo '<a href="' . e($base) . 'cookies.php">' . ($cs ? 'Cookies' : 'Cookies') . '</a>';
    // Which build this is. Worth the few pixels: "is what I see what I
    // uploaded?" is otherwise a guess, and a cached page on the host looks
    // exactly like a change that did not work.
    echo '<span class="hint foot-ver" title="' . ($cs ? 'Verze webu' : 'Site version') . '">v'
       . e(H3D_VERSION) . '</span>';
    echo "</div>\n";

    // Loaded at the end so data-confirm buttons work on every panel page
    // without each page having to remember the script tag.
    echo '<script src="' . asset($base, 'assets/confirm.js') . '"></script>' . "\n";
    echo '<script src="' . asset($base, 'assets/adspace.js') . '"></script>' . "\n";
    echo '<script src="' . asset($base, 'assets/panel.js') . '"></script>' . "\n";

    // Only the admin needs the live settings behaviour; the account page has
    // no settings forms for it to act on.
    if (str_contains((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/')) {
        echo '<script src="' . asset($base, 'assets/admin.js') . '"></script>' . "\n";
        echo '<script src="' . asset($base, 'assets/admin-nav.js') . '"></script>' . "\n";
    }

    // Analytics (only with consent), the banner (only until answered) and
    // the few lines that store the answer.
    echo Consent::render($base);

    echo "</body>\n</html>\n";
}

function flash(string $type, string $text): void
{
    if ($text === '') {
        return;
    }
    echo '<div class="msg ' . e($type) . '">' . e($text) . '</div>';
}
