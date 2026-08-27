<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/bootstrap.php';

// The Studio embeds a session-bound CSRF token in its JavaScript bootstrap.
// Never let a browser/proxy reuse an authenticated page after the session
// token has rotated during login/logout/remembered-login restoration.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// One token per session. The export endpoint refuses requests without it,
// which stops the API from being driven straight from curl or someone
// else's page.
if (empty($_SESSION['h3d_token'])) {
    $_SESSION['h3d_token'] = bin2hex(random_bytes(16));
}
$token = $_SESSION['h3d_token'];

// The client stores its choice in a cookie so the correct <html lang> is
// present in the very first response, before the bundle runs.
$lang = $_COOKIE['h3d_lang'] ?? 'en';
$lang = ($lang === 'cs') ? 'cs' : 'en';
$theme = 'light';
$palette = 'light'; // Single visual theme: H3D Light.

$me       = Auth::user();

// Registration redirects here as an anonymous visitor. Keep the one-time
// confirmation notice in the session so the Studio can open the verification
// dialog immediately after registration, then consume it.
$verificationNotice = $_SESSION['verification_notice'] ?? null;
if (is_array($verificationNotice)) {
    unset($_SESSION['verification_notice']);
}

$quota    = Quota::check($me);
$isAdmin  = $me && Auth::canManageOrders($me);
// Unverified signed-in users get exactly the same locked studio features as guests.
$studioAccessLocked = !$me || (!$isAdmin && empty($me['verified_at']));
$maintenanceWarning = $me && !$isAdmin && Settings::bool('maintenance_mode');

/**
 * The little "i" that sits beside a setting and explains it.
 *
 * Only the key is written here; the sentence itself lives in the studio's
 * translation table, so it follows the language switch without a reload and
 * one place holds every explanation. The button carries no text of its own
 * for a screen reader to read twice - applyLang() gives it the label.
 */
function info(string $key): string
{
    return '<button type="button" class="info-dot" data-i18n-tip="' . e($key) . '"'
         . ' tabindex="0" aria-label="?">i</button>';
}

/*
 * The range of every studio parameter, from the settings.
 *
 * The same array validates the export (Layout::parse) and is handed to the
 * client, so the slider, the field and the server cannot disagree about
 * what is allowed - which they did while these numbers were typed into
 * three different files.
 */
$lim = Layout::limits();
/** Trims the trailing zeros so a limit reads "20" rather than "20.00". */
$mm  = static fn (float $v): string => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');

// Keep the language cookie synchronized with the signed-in profile.
if ($me) {
    $p = json_decode((string) ($me['prefs'] ?? ''), true);
    if (is_array($p) && in_array($p['lang'] ?? '', ['en', 'cs'], true) && $p['lang'] !== $lang) {
        $lang = $p['lang'];
        setcookie('h3d_lang', $lang, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
    }
}

// A share URL contains only the random key. The project itself is fetched
// from the server after authentication, so even the share URL never carries
// box/layout data. Shared projects are intentionally available only to signed-in users.
$shareKey = trim((string) ($_GET['share'] ?? ''));
if ($shareKey === '') {
    $pathInfo = trim((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? ''), '/');
    $lastPath = $pathInfo === '' ? '' : basename($pathInfo);
    if (preg_match('/^[A-Za-z0-9_-]{16}$/', $lastPath)) {
        $shareKey = $lastPath;
    }
}
if ($shareKey !== '' && !preg_match('/^[A-Za-z0-9_-]{16}$/', $shareKey)) {
    $shareKey = '';
}
// Shared projects are private. If somebody opens a share link while signed
// out, send them through the normal login page and preserve the exact share
// URL so the same project opens immediately after authentication.
if ($shareKey !== '' && !$me) {
    $returnUrl = 'index.php?share=' . rawurlencode($shareKey);
    header('Location: login.php?next=' . rawurlencode($returnUrl));
    exit;
}

// The whole studio can be put behind a login from the admin panel.
if (!$me && Settings::bool('require_login')) {
    header('Location: login.php?next=' . rawurlencode('index.php'));
    exit;
}

// Cache buster so a redeploy never serves a stale bundle.
/*
 * The bundle is assembled from lib/client.js.php, so editing the script does
 * not touch assets/app.js.php at all - the version never moved and browsers
 * kept serving yesterday's code. Every file that can change the output gets
 * a look in.
 */
$assetMtime = max(array_map(
    static fn(string $f): int => @filemtime(__DIR__ . '/' . $f) ?: 0,
    ['assets/app.js.php', 'assets/base.css', 'assets/ui.css', 'lib/client.js.php']
)) ?: time();
// Keep the URL unique even when a deployment preserves mtimes or two edits
// land within one second. Otherwise browsers keep a previous app bundle for
// its one-hour cache lifetime while the page itself already has new markup.
$assetVersion = $assetMtime . '-' . substr(
    sha1_file(__DIR__ . '/assets/app.js.php') . sha1_file(__DIR__ . '/lib/client.js.php'),
    0,
    12
);

// Open Graph requires absolute URLs, so the origin has to be derived from
// the request rather than hard coded - the app may sit in a subfolder.
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https' ? 'https' : 'http';
$dir    = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
$origin = htmlspecialchars($scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir,
    ENT_QUOTES, 'UTF-8');

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');

// The studio has all identity-derived values now. Release the PHP session
// before rendering its sizeable markup so autosaves, exports and a second
// tab are never queued behind HTML generation.
$studioCsrf = Auth::csrfToken();
Auth::releaseSession();
?>
<!doctype html>
<html lang="<?= $lang ?>">
<head>

<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-374N2V0T9Y"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-374N2V0T9Y');
</script>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e(Settings::get('site_name')) ?></title>
<meta name="description" content="Design a parametric drawer organizer and export it as a print-ready 3MF or STL file.">

<!-- Icons. SVG is preferred by modern browsers; the ICO covers the rest. -->
<link rel="icon" href="assets/icons/favicon.ico" sizes="32x32">
<link rel="icon" href="assets/icons/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
<link rel="manifest" href="site.webmanifest">

<meta name="theme-color" content="<?= $theme === 'light' ? '#ffffff' : '#0b131e' ?>">
<meta name="apple-mobile-web-app-title" content="Organizer">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="mobile-web-app-capable" content="yes">

<meta property="og:type" content="website">
<meta property="og:title" content="Honza3D · Drawer Organizer Studio">
<meta property="og:description" content="Design a parametric drawer organizer and export it as a print-ready 3MF or STL file.">
<meta property="og:image" content="<?= $origin ?>/assets/icons/og-image.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:url" content="<?= $origin ?>/">
<meta name="twitter:card" content="summary_large_image">

<link rel="stylesheet" href="assets/base.css?v=<?= $assetVersion ?>">
<link rel="stylesheet" href="assets/ui.css?v=<?= $assetVersion ?>">
<?php
  // Room the administrator reserved for the host's advertising strip.
  $pmT = max(0, min(300, Settings::int('page_margin_top')));
  $pmB = max(0, min(300, Settings::int('page_margin_bottom')));
  if ($pmT > 0 || $pmB > 0):
?>
<style>:root{--page-top:<?= $pmT ?>px;--page-bottom:<?= $pmB ?>px}</style>
<?php endif; ?>
<?php
$paletteCss = [
  'bg'=>Settings::get('palette_bg'),'panel'=>Settings::get('palette_panel'),'panel2'=>Settings::get('palette_panel2'),
  'line'=>Settings::get('palette_line'),'text'=>Settings::get('palette_text'),'muted'=>Settings::get('palette_muted'),
  'accent'=>Settings::get('palette_accent'),'accentv'=>Settings::get('palette_accent'),'accentv2'=>Settings::get('palette_accent_dark'),
  'accentText'=>Settings::get('palette_accent_text'),'button'=>Settings::get('palette_button'),'buttonText'=>Settings::get('palette_button_text'),
  'buttonHover'=>Settings::get('palette_button_hover'),'good'=>Settings::get('palette_good'),'goodText'=>Settings::get('palette_good_text'),
  'goodHover'=>Settings::get('palette_good_hover'),'bad'=>Settings::get('palette_bad'),'canvas'=>Settings::get('palette_canvas'),'grid'=>Settings::get('palette_grid'),
];

// The studio is now H3D Light only. Older installations may still have a
// dark/Ocean palette stored in settings. Do not let those legacy values leak
// into the public studio; custom light palettes remain untouched.
$hexLuma = static function (string $hex): float {
    $hex = ltrim(trim($hex), '#');
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) return 1.0;
    $r = hexdec(substr($hex, 0, 2)) / 255;
    $g = hexdec(substr($hex, 2, 2)) / 255;
    $b = hexdec(substr($hex, 4, 2)) / 255;
    return (0.2126 * $r) + (0.7152 * $g) + (0.0722 * $b);
};
if ($hexLuma((string) $paletteCss['bg']) < 0.45) {
    $paletteCss = [
      'bg'=>'#eef1f5','panel'=>'#ffffff','panel2'=>'#f5f7fa','line'=>'#d5dce5',
      'text'=>'#20242a','muted'=>'#68707a','accent'=>'#1769d1','accentv'=>'#1769d1',
      'accentv2'=>'#0f57b4','accentText'=>'#ffffff','button'=>'#e8eef5',
      'buttonText'=>'#20242a','buttonHover'=>'#dbe5ef','good'=>'#168b57',
      'goodText'=>'#ffffff','goodHover'=>'#117247','bad'=>'#d64555',
      'canvas'=>'#eef1f5','grid'=>'#b5c1cf',
    ];
}
?>
<style id="h3-global-palette">
:root{--bg:<?=e($paletteCss['bg'])?>;--panel:<?=e($paletteCss['panel'])?>;--panel2:<?=e($paletteCss['panel2'])?>;--line:<?=e($paletteCss['line'])?>;--text:<?=e($paletteCss['text'])?>;--muted:<?=e($paletteCss['muted'])?>;--accent:<?=e($paletteCss['accent'])?>;--accentv:<?=e($paletteCss['accentv'])?>;--accentv2:<?=e($paletteCss['accentv2'])?>;--good:<?=e($paletteCss['good'])?>;--bad:<?=e($paletteCss['bad'])?>;--track:<?=e($paletteCss['line'])?>;--palette-button:<?=e($paletteCss['button'])?>;--palette-button-text:<?=e($paletteCss['buttonText'])?>;--palette-button-hover:<?=e($paletteCss['buttonHover'])?>;--palette-good-text:<?=e($paletteCss['goodText'])?>;--palette-good-hover:<?=e($paletteCss['goodHover'])?>;--canvas-bg:<?=e($paletteCss['canvas'])?>;--grid-color:<?=e($paletteCss['grid'])?>;--accentText:<?=e($paletteCss['accentText'])?>;--goodText:<?=e($paletteCss['goodText'])?>;--goodHover:<?=e($paletteCss['goodHover'])?>}
body{background:var(--bg);color:var(--text)}
.canvasWrap{background:var(--canvas-bg)}
.bg-grid line{stroke:color-mix(in srgb,var(--grid-color) 55%,transparent)}
.btn:not(.primary),.ct-btn,.ct-modes,.ct-zoom,.ct-hist,.ct-fs{background:var(--palette-button);color:var(--palette-button-text)}
.btn:not(.primary):hover,.ct-btn:hover{background:var(--palette-button-hover);color:var(--text)}
.btn.primary,.studio-tour-setup-continue,.studio-tour-next{background:var(--accent);border-color:var(--accent);color:var(--accentText,#fff)}
.nav-item.active .nav-ico,.nav-item.active{color:var(--accent)}
.workspace-sync-toast{background:var(--panel)!important;color:var(--text)!important;border-color:var(--line)!important}
.workspace-sync-toast strong{color:var(--text)!important}.workspace-sync-toast span{color:var(--muted)!important}
.cell-add{fill:var(--good)!important}
.cell-add-hover{filter:drop-shadow(0 0 5px color-mix(in srgb,var(--good) 55%,transparent))}
.edge-wall-dot,.cellmod-dot{fill:var(--accent)!important}
.edge-wall-action:hover .edge-wall-dot,.cellmod-btn:hover .cellmod-dot{fill:color-mix(in srgb,var(--accent) 82%,white)!important}
.edge-shrink-dot,.cellmod-remove .cellmod-dot{fill:var(--bad)!important}
.edge-shrink-icon:hover .edge-shrink-dot,.cellmod-remove:hover .cellmod-dot{fill:color-mix(in srgb,var(--bad) 82%,white)!important}
.edge-wall-sign,.edge-shrink-sign,.cellmod-sign,.grow-arrow{stroke:var(--accentText,#fff)!important}
.statusbar{background:var(--panel2)!important}
.sb-ico{background:var(--panel)!important;color:var(--accent)!important}
.nav-item:hover,.nav-collapse:hover{background:var(--panel2)!important;color:var(--text)!important}
.nav-item.active{background:color-mix(in srgb,var(--accent) 24%,transparent)!important;color:var(--text)!important}
.nav-item.active .nav-ico{color:var(--accent)!important}

</style>
</head>
<body class="<?= $palette === 'light' ? 'light' : '' ?>" data-palette="<?= e($palette) ?>">

<?php
  $brandUrl   = trim(Settings::get('brand_url'));
  $brandLabel = trim(Settings::get('brand_label')) !== ''
      ? trim(Settings::get('brand_label'))
      : trim(Settings::get('site_name'));

  // Shown under the brand, so a site name that already begins with the brand
  // would read "Honza3D - Honza3D Drawer Organizer Studio". Trimming the
  // repeat means the default settings look right out of the box.
  $brandTail = trim(Settings::get('site_name'));
  if ($brandLabel !== '' && stripos($brandTail, $brandLabel) === 0) {
      $brandTail = trim(substr($brandTail, strlen($brandLabel)));
  }
  if ($brandTail === '') {
      $brandTail = trim(Settings::get('site_name'));
  }
?>
<div class="workspace">
<div class="studio-body">

<nav class="studio-nav" id="studioNav" aria-label="Studio">
  <!-- The brand lives at the top of the rail; there is no separate header bar.
       It goes home, to the studio - where a logo is expected to go. The
       configured brand_url is a link like any other and belongs in the
       footer, not on the one thing people click to start over. -->
  <a class="nav-brand" href="index.php" title="<?= e($brandLabel) ?>">
      <img class="brand-logo" src="assets/icons/favicon.svg" alt="" width="30" height="30">
      <span class="brand-text">
        <span class="brand-mark"><?= e($brandLabel) ?></span>
        <?php if ($brandTail !== '' && $brandTail !== $brandLabel): ?><span class="brand-sub"><?= e($brandTail) ?></span><?php endif; ?>
      </span>
  </a>
  <div class="nav-sep"></div>
  <div class="nav-items">
    <button class="nav-item" type="button" data-flyout="dims">
      <span class="nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="8" width="18" height="8" rx="1.5"/><path d="M7 8v3M11 8v4M15 8v3M19 8v4"/></svg></span>
      <span class="nav-label" data-i18n="nav.dims">Rozměry</span>
    </button>
    <button class="nav-item<?= $studioAccessLocked ? ' guest-lock' : '' ?>" type="button" data-flyout="print">
      <span class="nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9V3h12v6"/><rect x="4" y="9" width="16" height="8" rx="1.5"/><path d="M8 15h8v6H8z"/></svg></span>
      <span class="nav-label" data-i18n="nav.print">Print area</span>
      <?php if ($studioAccessLocked): ?><span class="nav-lock" aria-hidden="true"><svg viewBox="0 0 24 24" width="10" height="10" fill="currentColor"><path d="M12 2a4 4 0 0 0-4 4v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V6a4 4 0 0 0-4-4Zm-2 6V6a2 2 0 1 1 4 0v2h-4Z"/></svg></span><?php endif; ?>
    </button>
    <button class="nav-item" type="button" data-flyout="grid">
      <span class="nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></span>
      <span class="nav-label" data-i18n="nav.grid">Grid</span>
    </button>
    <button class="nav-item<?= $studioAccessLocked ? ' guest-lock' : '' ?>" type="button" data-flyout="layout">
      <span class="nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 3l2.2 5.8L21 11l-5.8 2.2L13 19l-2.2-5.8L5 11l5.8-2.2L13 3Z"/><path d="M5 4v3M3.5 5.5h3"/></svg></span>
      <span class="nav-label" data-i18n="nav.layout">Layout</span>
      <?php if ($studioAccessLocked): ?><span class="nav-lock" aria-hidden="true"><svg viewBox="0 0 24 24" width="10" height="10" fill="currentColor"><path d="M12 2a4 4 0 0 0-4 4v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V6a4 4 0 0 0-4-4Zm-2 6V6a2 2 0 1 1 4 0v2h-4Z"/></svg></span><?php endif; ?>
    </button>
    <button class="nav-item<?= $studioAccessLocked ? ' guest-lock' : '' ?>" type="button" data-flyout="inspector">
      <span class="nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a8 8 0 0 1 16 0"/><path d="M12 14l3.5-3.5"/><circle cx="12" cy="14" r="1.3" fill="currentColor" stroke="none"/><path d="M4 14h1.6M18.4 14H20M12 6.2V4.6"/></svg></span>
      <span class="nav-label" data-i18n="nav.inspector">Overview</span>
      <?php if ($studioAccessLocked): ?><span class="nav-lock" aria-hidden="true"><svg viewBox="0 0 24 24" width="10" height="10" fill="currentColor"><path d="M12 2a4 4 0 0 0-4 4v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V6a4 4 0 0 0-4-4Zm-2 6V6a2 2 0 1 1 4 0v2h-4Z"/></svg></span><?php endif; ?>
    </button>
  </div>
  <div class="nav-sep"></div>
  <div class="nav-items">
    <button class="nav-item" type="button" data-flyout="export">
      <span class="nav-ico nav-ico-export" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
          <path d="M4 16v3a1.5 1.5 0 0 0 1.5 1.5h13A1.5 1.5 0 0 0 20 19v-3"/>
          <path d="M12 3v10"/>
          <path d="m8.5 9.5 3.5 3.5 3.5-3.5"/>
        </svg>
      </span>
      <span class="nav-label" data-i18n="nav.export">Export</span>
    </button>
  </div>
  <div class="nav-sep"></div>
  <div class="nav-items">
    <?php if ($me): ?>
      <?php
        /*
         * The diamond means unlimited exports - an admin, a super user or a
         * live time plan - and nothing else. Somebody paying per export sees
         * the credit mark and their balance instead, because a diamond over
         * a number like 0.5 promised something the account does not have.
         *
         * Both marks are in the markup and CSS shows one, so the rail can
         * follow a plan that starts or runs out while the page is open.
         */
        $unlimited = Auth::isUnlimited($me);
        $credUnit  = ($ucs ?? false) ? 'kr' : 'cr';
      ?>
      <?php $hasFree = !$unlimited && (int) $quota['free_left'] > 0; ?>
      <button class="nav-item nav-credits<?= $unlimited ? ' is-unlimited' : '' ?><?= $hasFree ? ' has-free' : '' ?>" type="button" data-flyout="credits" title="<?= e($lang === 'cs' ? 'Kredity' : 'Credits') ?>">
        <span class="nav-ico">
          <svg class="cred-gem" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3.5h12l3.2 5.2L12 21 2.8 8.7 6 3.5Z"/><path d="M2.8 8.7h18.4M9 3.5 6.5 8.7 12 21l5.5-12.3L15 3.5"/></svg>
          <span class="nav-free-rail" aria-hidden="true"><b data-signed-free><?= (int) $quota['free_left'] ?></b><i>/</i><b data-signed-limit><?= (int) $quota['limit'] ?></b></span>
          <span class="nav-credit-rail" aria-hidden="true"><b data-credit-rail><?= e(Cred::fmt((int) $me['credits'])) ?></b><i><?= e(strtoupper($credUnit)) ?></i></span>
          <b class="cred-mark" aria-hidden="true">FREE</b>
        </span>
        <span class="nav-label">
          <span class="nav-unlimited-label"><?= e($lang === 'cs' ? 'Neomezené exporty' : 'Unlimited exports') ?></span>
          <span class="nav-free-label"><span><?= e($lang === 'cs' ? 'Volné exporty' : 'Free exports') ?></span><span class="nav-free-detail"><b data-signed-free><?= (int) $quota['free_left'] ?></b><i>/</i><b data-signed-limit><?= (int) $quota['limit'] ?></b></span></span>
          <span class="nav-credit-label"><span id="navCreditValue"><?= e(Cred::fmt((int) $me['credits'])) ?></span><span class="nav-cred-unit"> <?= e($credUnit) ?> / <?= e(Cred::fmt((int) $quota['cost'])) ?> <?= e($credUnit) ?> <?= e($lang === 'cs' ? 'za export' : 'per export') ?></span></span>
        </span>
      </button>
      <a class="nav-item" href="account.php?tab=nastaveni">
        <span class="nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="3.4"/><path d="M5.5 20a6.5 6.5 0 0 1 13 0"/></svg></span>
        <span class="nav-label" data-i18n="nav.account">Account</span>
      </a>
      <?php if ($isAdmin): ?>
      <a class="nav-item nav-admin" href="admin/index.php">
        <span class="nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.7 20 6v5.4c0 4.7-3.1 8.4-8 10-4.9-1.6-8-5.3-8-10V6l8-3.3Z"/><path d="m8.5 12 2.2 2.2 4.8-5"/></svg></span>
        <span class="nav-label" data-i18n="nav.admin">Administration</span>
      </a>
      <?php endif; ?>
      <a class="nav-item nav-signout" href="logout.php">
        <span class="nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 4H6a1 1 0 0 0-1 1v14a1 1 0 0 0 1 1h3"/><path d="M14 12H21"/><path d="M18 8l4 4-4 4"/></svg></span>
        <span class="nav-label" data-i18n="nav.signout">Sign out</span>
      </a>
    <?php else: ?>
      <!-- Guest quota carries its own compact notation. The rail shows a
           useful number even while collapsed; the expanded state turns the
           mark into EXP and spells the allowance out beside it. -->
      <button class="nav-item nav-credits nav-guest-quota<?= ((int) $quota['free_left'] <= 0) ? ' is-empty' : '' ?>" type="button" data-flyout="credits" title="<?= e(($lang === 'cs' ? 'Volné exporty ' : 'Free exports ') . (int) $quota['free_left'] . ' / ' . (int) $quota['limit']) ?>">
        <span class="nav-ico"><span class="nav-guest-count"><b data-guest-free><?= (int) $quota['free_left'] ?></b><i>/</i><b data-guest-limit><?= (int) $quota['limit'] ?></b></span><b class="nav-guest-exp" aria-hidden="true">EXP</b></span>
        <span class="nav-label"><b><?= e($lang === 'cs' ? 'Volné exporty' : 'Free exports') ?></b><span class="nav-guest-detail"><b data-guest-free><?= (int) $quota['free_left'] ?></b><i>/</i><b data-guest-limit><?= (int) $quota['limit'] ?></b></span></span>
      </button>
      <a class="nav-item nav-signin" href="login.php?next=index.php">
        <span class="nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4.5 20a7.5 7.5 0 0 1 15 0"/></svg></span>
        <span class="nav-label" data-i18n="nav.signin">Sign in</span>
      </a>
    <?php endif; ?>
    <button class="nav-item" type="button" data-flyout="prefs" title="Rychlé nastavení">
      <span class="nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0 .33 1.82V15a1.65 1.65 0 0 0 .4.6Z"/></svg></span>
      <span class="nav-label" data-i18n="nav.quickSettings">Rychlé nastavení</span>
    </button>
  </div>
  <div class="nav-sep"></div>
  <div class="nav-items">
    <button class="nav-item" type="button" data-flyout="howto">
      <span class="nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M9.6 9.4a2.5 2.5 0 1 1 3.4 2.4c-.8.4-1 .9-1 1.6"/><circle cx="12" cy="16.6" r="0.6" fill="currentColor" stroke="none"/></svg></span>
      <span class="nav-label" data-i18n="nav.howto">How to use</span>
    </button>
    <?php if (!$me): ?>
    <button class="nav-item nav-verification-mail" id="verificationMailBtn" type="button"
            title="<?= e($lang === 'cs' ? 'Ověření e-mailu' : 'Email verification') ?>"
            aria-label="<?= e($lang === 'cs' ? 'Ověření e-mailu' : 'Email verification') ?>">
      <span class="nav-ico">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
          <rect x="3" y="5" width="18" height="14" rx="2"/>
          <path d="m4 7 8 6 8-6"/>
        </svg>
      </span>
      <span class="nav-label"><?= e($lang === 'cs' ? 'Ověřit e-mail' : 'Verify email') ?></span>
    </button>
    <?php endif; ?>
    <?php if ($me): ?>
    <button class="nav-item nav-messages<?= $studioAccessLocked ? ' guest-lock' : '' ?>" type="button" data-flyout="messageAdmin" title="<?= e($lang === 'cs' ? 'Chat s živým kolegou' : 'Live colleague chat') ?>">
      <span class="nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5.5A2.5 2.5 0 0 1 6.5 3h11A2.5 2.5 0 0 1 20 5.5v8A2.5 2.5 0 0 1 17.5 16H10l-5 4v-4.2A2.5 2.5 0 0 1 4 13.5v-8Z"/><path d="M8 8h8M8 11.5h5"/></svg></span>
      <span class="nav-label">Chat</span>
      <?php if ($studioAccessLocked): ?><span class="nav-lock" aria-hidden="true"><svg viewBox="0 0 24 24" width="10" height="10" fill="currentColor"><path d="M12 2a4 4 0 0 0-4 4v2H7a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2v-8h-1V6a4 4 0 0 0-4-4Zm-2 6V6a2 2 0 1 1 4 0v2h-4Z"/></svg></span><?php endif; ?>
      <?php $unreadReplies = UserMessages::unreadReplyCount((int) $me['id']); ?>
      <?php if ($unreadReplies > 0): ?><span id="messageBadge" class="message-badge" aria-label="<?= $unreadReplies ?> <?= e($lang === 'cs' ? 'nových odpovědí' : 'new replies') ?>"><?= $unreadReplies ?></span><?php endif; ?>
    </button>
    <?php endif; ?>
  </div>
  <button class="nav-toggle" id="navToggle" type="button" aria-label="Toggle menu labels">
    <span class="nav-ico"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M13 6l6 6-6 6"/><path d="M5 6l6 6-6 6"/></svg></span>
    <span class="nav-label" data-i18n="nav.collapse">Collapse menu</span>
  </button>
</nav>

<?php if ($me): ?>
<?php
  $activeMessage = UserMessages::activeForUser((int) $me['id']);
  $activePosts = $activeMessage ? UserMessages::postsFor((int)$activeMessage['id']) : [];
  if (count($activePosts) > 20) $activePosts = array_slice($activePosts, -20);
  if ($activeMessage) { UserMessages::markRepliesRead((int)$me['id']); }
?>
<div class="panel message-admin-panel h3d-support-panel" data-panel="messageAdmin" hidden>
  <div class="support-chat-shell">
    <div class="support-chat-head">
      <div class="support-avatar" aria-hidden="true">◇</div>
      <div class="support-chat-title">
        <strong>H3D Pomocník</strong>
        <span>Pomocník a podpora</span>
      </div>
      <button type="button" class="support-archive-btn support-chat-btn" id="supportChatBtn">Chat</button>
      <button type="button" class="support-archive-btn" id="supportArchiveBtn">Archiv</button>
      <button type="button" class="support-info-btn" id="supportInfoBtn" aria-label="Informace o H3D Pomocníkovi" title="Co může H3D Pomocník řešit?">i</button>
      <button type="button" class="support-chat-close" data-close-panel="messageAdmin" aria-label="Zavřít">×</button>
    </div>

    <div class="support-info-panel" id="supportInfoPanel" hidden>
      <div class="support-info-head">
        <strong>Co může H3D Pomocník řešit?</strong>
        <button type="button" id="supportInfoClose" aria-label="Zavřít">×</button>
      </div>
      <p>Vyber si téma z nabídky přímo v chatu. H3D Pomocník odpoví podle aktuálních informací uložených v administraci. Pokud potřebuješ člověka, zvol <strong>Předat živému kolegovi</strong>.</p>
    </div>

    <div class="support-archive-panel" id="supportArchivePanel" hidden>
      <div class="support-archive-head"><strong>Archiv konverzací</strong><button type="button" id="supportArchiveClose" aria-label="Zavřít">×</button></div>
      <div class="support-archive-table-head"><span>Předmět</span><span>Hodnocení</span></div>
      <div id="supportArchiveList" class="support-archive-list"><div class="support-archive-empty">Načítám archiv…</div></div>
      <div id="supportArchiveDetail" class="support-archive-detail" hidden></div>
    </div>

    <div class="support-chat-body" id="supportChatBody">
      <div class="support-live-title" id="supportLiveTitle" <?= $activeMessage ? "" : "hidden" ?>>Konverzace s živým kolegou</div>
      <div class="support-message-log" id="supportMessageLog">
      <?php if ($activeMessage): ?>
        <?php foreach ($activePosts as $post): ?>
          <div class="support-message <?= $post['author_kind'] === 'staff' ? 'support-message-admin' : 'support-message-user' ?>">
            <div class="support-avatar support-avatar-small"><span class="support-avatar-glyph" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.2"/><path d="M5.5 20c.7-4.1 2.9-6.2 6.5-6.2s5.8 2.1 6.5 6.2"/></svg></span></div>
            <div class="support-message-content"><div class="support-message-name"><?= $post['author_kind'] === 'staff' ? 'Živý kolega' : 'Já' ?></div><div class="support-bubble"><?= nl2br(e((string)$post['body'])) ?></div></div>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="support-message support-message-helper">
          <div class="support-avatar support-avatar-small"><span class="support-avatar-glyph" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.2"/><path d="M5.5 20c.7-4.1 2.9-6.2 6.5-6.2s5.8 2.1 6.5 6.2"/></svg></span></div>
          <div class="support-message-content">
            <div class="support-message-name">H3D Pomocník</div>
            <div class="support-bubble">Ahoj! 👋 Vítám tě v H3D Studiu. Jsem tvůj virtuální asistent a rád ti okamžitě pomůžu s dotazy ohledně účtu, kreditů nebo exportů. Co tě zajímá?</div>
          </div>
        </div>
      <?php endif; ?>
      </div>
      <div class="support-quick" id="supportQuick">
        <button type="button" class="support-chip" data-support-text="Moje balíčky">Moje balíčky</button>
        <button type="button" class="support-chip" data-support-text="Moje aktivní předplatné">Moje aktivní předplatné</button>
        <button type="button" class="support-chip" data-support-text="Moje objednávky">Moje objednávky</button>
        <button type="button" class="support-chip" data-support-text="Moje kredity">Moje kredity</button>
        <button type="button" class="support-chip" data-support-text="Moje exporty">Moje exporty</button>
        <button type="button" class="support-chip" data-support-text="Nabídka kreditů / časového plánu">Nabídka kreditů / časového plánu</button>
        <button type="button" class="support-chip" data-support-text="Podmínky">Podmínky</button>
        <button type="button" class="support-chip" data-support-text="Promo akce">Promo akce</button>
        <button type="button" class="support-chip" data-support-text="Předat živému kolegovi">Předat živému kolegovi</button>
      </div>


      <div class="support-feedback" id="supportFeedback" hidden>
        <strong>Jak ti H3D Pomocník pomohl?</strong>
        <div class="support-rating">
          <button type="button" data-rating="good">🙂<span>Pomohl</span></button>
          <button type="button" data-rating="partial">😐<span>Částečně</span></button>
          <button type="button" data-rating="bad">🙁<span>Nepomohl</span></button>
        </div>
        <textarea id="supportFeedbackComment" rows="3" placeholder="Nepovinně napiš, co můžeme zlepšit…"></textarea>
        <div class="support-feedback-actions">
          <button type="button" class="btn primary" id="supportFeedbackSend">Odeslat hodnocení</button>
          <button type="button" class="btn" id="supportFeedbackSkip">Nechci hodnotit</button>
        </div>
        <span class="support-feedback-status" id="supportFeedbackStatus" hidden></span>
        <button type="button" class="btn primary support-feedback-close" id="supportFeedbackClose" hidden>Zavřít chat</button>
      </div>
    </div>

    <form class="support-chat-compose" id="supportChatForm" autocomplete="off" hidden>
      <div class="support-compose-main">
        <textarea id="supportChatInput" rows="2" maxlength="2000" placeholder="Napiš zprávu…" hidden></textarea>
        <button type="submit" class="support-send" aria-label="Odeslat" hidden>➤</button>
      </div>
      <div class="support-compose-footer">
        <span class="support-message-limit" id="supportMessageLimit" hidden></span>
        <button type="button" class="support-end" id="supportEndChat" hidden>Ukončit chat</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="flyout-layer" id="flyoutLayer" aria-hidden="true"></div>

<!-- Units and language moved off the top bar into a rail popover. -->
<div class="panel" data-panel="prefs" hidden>
  <div class="panel-head"><h3 data-i18n="nav.prefs">Preferences</h3></div>
  <div class="panel-body">
    <label data-i18n="pref.units">Units</label>
    <label class="unit-picker">
      <select id="unitSelect" data-i18n-aria="aria.units" aria-label="Display units">
        <option value="mm" selected>mm</option>
        <option value="in">in</option>
      </select>
    </label>

    <label style="margin-top:14px" data-i18n="pref.language">Language</label>
    <div class="lang-switch" role="group" data-i18n-aria="aria.language" aria-label="Language">
      <button type="button" class="lang-btn" data-lang="en">EN</button>
      <button type="button" class="lang-btn" data-lang="cs">CZ</button>
    </div></div>
</div>

<?php if ($me): ?>
<!-- Credits overview: what this account has and what an export costs. -->
<div class="panel" data-panel="credits" hidden>
  <div class="panel-head"><h3 data-i18n="credits.title">Credits &amp; exports</h3></div>
  <div class="panel-body">
    <div class="cred-rows">
      <div class="cred-row"><span data-i18n="credits.balance">Credits</span><b id="cpBalance">-</b></div>
      <div class="cred-row"><span data-i18n="credits.free">Free exports</span><b id="cpFree">-</b></div>
      <div class="cred-row" id="cpResetRow" hidden><span data-i18n="credits.reset">Allowance resets in</span><b id="cpReset">-</b></div>
      <div class="cred-row" id="cpCostRow"><span data-i18n="credits.cost">Price per export</span><b id="cpCost">-</b></div>
    </div>
    <p class="hint" id="cpNote" data-i18n="credits.note">Free exports are used first; credits only when they run out.</p>
    <a class="btn full" href="account.php" data-i18n="credits.manage">Manage account &amp; credits</a>
  </div>
</div>
<?php else: ?>
<!-- A guest has no credit balance, but can still inspect their live allowance. -->
<div class="panel" data-panel="credits" hidden>
  <div class="panel-head"><h3 data-i18n="quota.freeExports"><?= e($lang === "cs" ? "Volné exporty" : "Free exports") ?></h3></div>
  <div class="panel-body">
    <div class="cred-rows">
      <div class="cred-row"><span data-i18n="quota.freeExports"><?= e($lang === "cs" ? "Volné exporty" : "Free exports") ?></span><b id="guestQuotaFree">-</b></div>
      <div class="cred-row" id="guestQuotaResetRow" hidden><span data-i18n="credits.reset">Obnova limitu za</span><b id="guestQuotaReset">-</b></div>
    </div>
    <p class="hint" id="guestQuotaNote"></p>
  </div>
</div>
<?php endif; ?>

<!-- Export options live in a popover opened from the rail's Export icon. -->
<div class="panel" data-panel="export" hidden>
  <div class="panel-head"><h3 data-i18n="btn.export">Export</h3></div>
  <div class="panel-body">
    <a class="export-quota flyout-quota" id="exportQuota" hidden></a>
    <button class="export-option recommended" type="button" data-export="3mf">
      <span class="fmt">3MF</span>
      <span>
        <span class="label" data-i18n="export.3mf.label">3MF package</span>
        <span class="desc" data-i18n="export.3mf.desc">Every box stays a separate object. Best for Bambu Studio and OrcaSlicer.</span>
      </span>
    </button>
    <button class="export-option" type="button" data-export="stl">
      <span class="fmt">STL</span>
      <span>
        <span class="label" data-i18n="export.stl.label">Binary STL</span>
        <span class="desc" data-i18n="export.stl.desc">All boxes in one file as separate shells. Use Split to Parts after import.</span>
      </span>
    </button>
    <?php if ($me): ?>
    <button class="export-option recommended" type="button" id="shareProjectBtn">
      <span class="fmt share-url-icon" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
          <path d="M10 13.9a4 4 0 0 0 5.7.1l2.1-2.1a4 4 0 0 0-5.7-5.7l-1.2 1.2"/>
          <path d="M14 10.1a4 4 0 0 0-5.7-.1l-2.1 2.1a4 4 0 0 0 5.7 5.7l1.2-1.2"/>
        </svg>
      </span>
      <span>
        <span class="label" data-i18n="share.title">Sdílet návrh</span>
        <span class="desc" data-i18n="share.desc">Vytvoří odkaz bez dat v URL. Návrh je uložen pod bezpečným klíčem.</span>
      </span>
    </button>
    <?php else: ?>
    <a class="export-option locked" href="login.php?next=index.php">
      <span class="fmt">↗</span>
      <span>
        <span class="label" data-i18n="share.title">Sdílet návrh</span>
        <span class="desc" data-i18n="share.locked">Přihlas se pro vytvoření odkazu na návrh.</span>
      </span>
      <span class="opt-lock" aria-hidden="true"><svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="5" y="11" width="14" height="9" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/></svg></span>
    </a>
    <?php endif; ?>
    <div class="export-menu-note" data-i18n="export.note">Millimetres. The model is generated on the server.</div>
  </div>
</div>

<main>

<aside>
  <div class="panel panel-print" data-panel="print">
    <div class="panel-head">
      <h3 data-i18n="panel.print">Print area</h3>
    </div>
    <div class="panel-body">
      <label><span data-i18n="field.maxprint">Max print area</span> (<span class="unit-label">mm</span>) - <span data-i18n="field.maxprint.wd">width × depth</span></label>
      <div class="field slim">
        <label><span data-i18n="field.width">Width</span> (<span class="unit-label">mm</span>)<?= info('tip.printw') ?></label>
        <div class="slim-row">
          <input id="maxPrintW" type="text" inputmode="decimal" value="0" placeholder="0">
          <input id="maxPrintWSlider" type="range" min="0" max="<?= $mm($lim['print'][1]) ?>" step="1" value="0">
        </div>
        <div class="range-ends"><span>0</span><span id="maxPrintWMax"><?= $mm($lim['print'][1]) ?> mm</span></div>
      </div>
      <div class="field slim">
        <label><span data-i18n="field.depth">Depth</span> (<span class="unit-label">mm</span>)<?= info('tip.printd') ?></label>
        <div class="slim-row">
          <input id="maxPrintD" type="text" inputmode="decimal" value="0" placeholder="0">
          <input id="maxPrintDSlider" type="range" min="0" max="<?= $mm($lim['print'][1]) ?>" step="1" value="0">
        </div>
        <div class="range-ends"><span>0</span><span id="maxPrintDMax"><?= $mm($lim['print'][1]) ?> mm</span></div>
      </div>
      <div class="hint" data-i18n="field.maxprint.hint">0 = no limit. A box larger than the printer bed is flagged and cannot be exported.</div>
    </div>
  </div>

  <div class="panel" data-panel="dims">
    <div class="panel-head">
      <h3 data-i18n="panel.sizecon">Size &amp; construction</h3>
    </div>
    <div class="panel-body">
      <?php if ($me): ?>
      <section class="wall-mode-control" aria-label="Box editing mode">
        <label data-i18n="walls.mode.label">Box editing mode</label>
        <div class="wall-mode-choice" role="group" aria-label="Box editing mode">
          <button type="button" id="wallEditWallsBtn" class="wall-edit-mode-btn active" data-wall-mode="walls" aria-pressed="true" data-i18n="walls.mode.walls">
            <svg class="wall-edit-mode-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="1.5"/><path d="M3 12h18M12 5v7M8 12v7M16 12v7"/></svg>
            <span>Walls</span>
          </button>
          <button type="button" id="wallEditFreeBtn" class="wall-edit-mode-btn" data-wall-mode="free" aria-pressed="false" data-i18n="walls.mode.free">
            <svg class="wall-edit-mode-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4v15h14"/><path d="M10 4v10h9"/><path d="M5 14h5"/></svg>
            <span>FREE MODE</span>
          </button>
        </div>
        <div class="wall-grid-field">
          <small class="wall-grid-state" id="wallGridState" hidden data-i18n="walls.grid.freeHint">Switch to Walls to change divider grid detail.</small>
        </div>
      </section>
      <?php endif; ?>

      <div class="field">
        <label><span data-i18n="field.width">Width</span> (<span class="unit-label">mm</span>)<?= info('tip.dw') ?></label>
        <div class="stepper">
          <button type="button" data-step="dw" data-dir="-1" data-i18n-aria="aria.decrease.dw" aria-label="Decrease width">-</button>
          <input id="dw" type="text" value="250.00" min="0.01" max="<?= $mm($lim['dw'][1]) ?>" step="0.01" inputmode="decimal">
          <button type="button" data-step="dw" data-dir="1" data-i18n-aria="aria.increase.dw" aria-label="Increase width">+</button>
        </div>
        <div class="range-wrap">
          <div class="range-value"><span id="dwMin">0 mm</span><b id="dwValue">250.00 mm</b><span id="dwMax"><?= $mm($lim['dw'][1]) ?> mm</span></div>
          <input id="dwSlider" type="range" min="0" max="<?= $mm($lim['dw'][1]) ?>" step="0.01" value="250">
        </div>
      </div>

      <div class="field">
        <label><span data-i18n="field.depth">Depth</span> (<span class="unit-label">mm</span>)<?= info('tip.dd') ?></label>
        <div class="stepper">
          <button type="button" data-step="dd" data-dir="-1" data-i18n-aria="aria.decrease.dd" aria-label="Decrease depth">-</button>
          <input id="dd" type="text" value="250.00" min="0.01" max="<?= $mm($lim['dd'][1]) ?>" step="0.01" inputmode="decimal">
          <button type="button" data-step="dd" data-dir="1" data-i18n-aria="aria.increase.dd" aria-label="Increase depth">+</button>
        </div>
        <div class="range-wrap">
          <div class="range-value"><span id="ddMin">0 mm</span><b id="ddValue">250.00 mm</b><span id="ddMax"><?= $mm($lim['dd'][1]) ?> mm</span></div>
          <input id="ddSlider" type="range" min="0" max="<?= $mm($lim['dd'][1]) ?>" step="0.01" value="250">
        </div>
      </div>

      <div class="field">
        <label><span data-i18n="field.height">Height</span> (<span class="unit-label">mm</span>)<?= info('tip.dh') ?></label>
        <div class="stepper">
          <button type="button" data-step="dh" data-dir="-1" data-i18n-aria="aria.decrease.dh" aria-label="Decrease height">-</button>
          <input id="dh" type="text" value="50.00" min="0.01" max="<?= $mm($lim['dh'][1]) ?>" step="0.01" inputmode="decimal">
          <button type="button" data-step="dh" data-dir="1" data-i18n-aria="aria.increase.dh" aria-label="Increase height">+</button>
        </div>
        <div class="range-wrap">
          <div class="range-value"><span id="dhMin">0 mm</span><b id="dhValue">50.00 mm</b><span id="dhMax"><?= $mm($lim['dh'][1]) ?> mm</span></div>
          <input id="dhSlider" type="range" min="0" max="<?= $mm($lim['dh'][1]) ?>" step="0.01" value="50">
        </div>
      </div>

      <div class="construction-section">
        <h3 data-i18n="panel.construction">Construction</h3>

        <div class="field slim">
          <label><span data-i18n="field.wall">Wall</span> (<span class="unit-label">mm</span>)<?= info('tip.wall') ?></label>
          <div class="slim-row">
            <input id="wall" type="text" inputmode="decimal" value="2.00" min="<?= $mm($lim['wall'][0]) ?>" step="0.1">
            <input id="wallSlider" type="range" min="<?= $mm($lim['wall'][0]) ?>" max="<?= $mm($lim['wall'][1]) ?>" step="0.1" value="2">
          </div>
          <div class="range-ends"><span id="wallMin"><?= $mm($lim['wall'][0]) ?> mm</span><span id="wallMax"><?= $mm($lim['wall'][1]) ?> mm</span></div>
        </div>

        <div class="field slim">
          <label><span data-i18n="field.bottom">Bottom</span> (<span class="unit-label">mm</span>)<?= info('tip.bottom') ?></label>
          <div class="slim-row">
            <input id="bottom" type="text" inputmode="decimal" value="2.00" min="<?= $mm($lim['bottom'][0]) ?>" step="0.1">
            <input id="bottomSlider" type="range" min="<?= $mm($lim['bottom'][0]) ?>" max="<?= $mm($lim['bottom'][1]) ?>" step="0.1" value="2">
          </div>
          <div class="range-ends"><span id="bottomMin"><?= $mm($lim['bottom'][0]) ?> mm</span><span id="bottomMax"><?= $mm($lim['bottom'][1]) ?> mm</span></div>
        </div>

        <div class="field slim">
          <label><span data-i18n="field.radius">Corner radius</span> (<span class="unit-label">mm</span>)<?= info('tip.radius') ?></label>
          <div class="slim-row">
            <input id="radius" type="text" inputmode="decimal" value="0.00" min="<?= $mm($lim['radius'][0]) ?>" step="0.1">
            <input id="radiusSlider" type="range" min="<?= $mm($lim['radius'][0]) ?>" max="<?= $mm($lim['radius'][1]) ?>" step="0.1" value="0">
          </div>
          <div class="range-ends"><span id="radiusMin"><?= $mm($lim['radius'][0]) ?> mm</span><span id="radiusMax"><?= $mm($lim['radius'][1]) ?> mm</span></div>
        </div>

        <!-- The gap belongs with the wall and the floor: all three are
             millimetres of the printed piece. Columns and rows are the
             raster, which is a different question. -->
        <div class="field slim">
          <label><span data-i18n="field.gap">Gap</span> (<span class="unit-label">mm</span>)<?= info('tip.gap') ?></label>
          <div class="slim-row">
            <input id="gap" type="text" inputmode="decimal" value="0.40" min="0" step="0.05">
            <input id="gapSlider" type="range" min="0" max="<?= $mm($lim['gap'][1]) ?>" step="0.05" value="0.4">
          </div>
          <div class="range-ends"><span id="gapMin">0 mm</span><span id="gapMax"><?= $mm($lim['gap'][1]) ?> mm</span></div>
        </div>

        <!-- The margin left free round the outside. The gap only ever sat
             BETWEEN boxes, so the outermost ones ran into the drawer walls
             and a set printed to the millimetre would not drop in. -->
        <div class="field slim">
          <label><span data-i18n="field.outer">Outer inset</span> (<span class="unit-label">mm</span>)<?= info('tip.outer') ?></label>
          <div class="slim-row">
            <input id="outer" type="text" inputmode="decimal" value="0.00" min="0" step="0.1">
            <input id="outerSlider" type="range" min="0" max="<?= $mm($lim['outer'][1]) ?>" step="0.1" value="0">
          </div>
          <div class="range-ends"><span id="outerMin">0 mm</span><span id="outerMax"><?= $mm($lim['outer'][1]) ?> mm</span></div>
          <div class="hint" id="outerHint" data-i18n="field.outer.hint">0 = boxes reach the drawer walls.</div>
        </div>
      </div>
    </div>
  </div>

  <div class="panel" data-panel="grid">
    <div class="panel-head">
      <h3 data-i18n="panel.grid">Grid</h3>
    </div>
    <div class="panel-body">
      <div class="field slim">
        <?php /* The ceiling comes from the settings, not from a 15 typed in
                 here: with the panel set to 13 the slider used to go on to
                 15 and build a layout the export then refused. */
              $maxCells = max(1, Settings::int('max_cells')); ?>
        <?php /* Same shape as every other parameter: name and "i" on the
                 left, the number in a box on the right, slider under them.
                 The count used to hide inside the label as "· 4" and could
                 only be changed by dragging. */ ?>
        <label><span data-i18n="field.columns">Columns</span><?= info('tip.cols') ?></label>
        <div class="slim-row">
          <input id="colsVal" type="text" inputmode="numeric" value="4" aria-label="Columns">
          <input id="cols" type="range" min="1" max="<?= $maxCells ?>" step="1" value="4">
        </div>
        <div class="range-ends"><span>1</span><span><?= $maxCells ?></span></div>
      </div>
      <div class="field slim">
        <label><span data-i18n="field.rows">Rows</span><?= info('tip.rows') ?></label>
        <div class="slim-row">
          <input id="rowsVal" type="text" inputmode="numeric" value="4" aria-label="Rows">
          <input id="rows" type="range" min="1" max="<?= $maxCells ?>" step="1" value="4">
        </div>
        <div class="range-ends"><span>1</span><span><?= $maxCells ?></span></div>
      </div>
    </div>
  </div>

  

  </aside>

<section class="center">
  <div class="canvasWrap"><svg id="svg" viewBox="0 0 900 650"></svg></div>

  <!--
    Nothing sits above the drawing any more: the whole column is working
    area. The drawer size waits quietly in the corner, and what is selected
    speaks from a bubble that hangs off the box itself, where it plainly
    belongs. The + / - handles on the grid are untouched.
  -->
  <div class="ct-bubble" id="drawerChip">
    <span class="ct-chip-label" data-i18n="ct.drawer">Drawer size</span>
    <span class="badge" id="sizeBadge">250 × 250 × 50 mm</span>
  </div>

  <div class="sel-bubble" id="selTools" hidden>
    <span class="sel-tools-info" id="selInfo"></span>
    <button class="sel-tool" id="topClone" type="button" title="Duplikovat box" data-i18n-title="box.clone" aria-label="Duplikovat box" data-i18n-aria="box.clone">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
    </button>
    <button class="sel-tool danger" id="topDelete" type="button" title="Smazat box" data-i18n-title="box.delete" aria-label="Smazat box" data-i18n-aria="box.delete">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2m2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6M14 11v6"/></svg>
    </button>
    <!-- Let the selection go without having to find empty canvas to click. -->
    <button class="sel-tool" id="topDeselect" type="button" title="Zrušit výběr" data-i18n-title="box.deselect" aria-label="Zrušit výběr">
      <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
    </button>
  </div>

  <div class="canvas-tools" id="canvasTools">
    <div class="ct-left">
      <div class="ct-modes" role="group" aria-label="Tools" data-i18n-aria="aria.tools">
        <button class="ct-btn active" type="button" data-tool="select" title="Výběr a úpravy" data-i18n-title="tool.select">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M5 3l14 8.5-6.2 1.4L10 20 5 3Z"/></svg>
        </button>
        <button class="ct-btn" type="button" data-tool="pan" title="Posun plátna" data-i18n-title="tool.pan">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11V5.5a1.5 1.5 0 0 1 3 0V11m0-1.5a1.5 1.5 0 0 1 3 0V12m0-1a1.5 1.5 0 0 1 3 0v4a5 5 0 0 1-5 5h-2.2a4 4 0 0 1-3.1-1.5L5 15.5a1.6 1.6 0 0 1 2.3-2.2L9 15"/></svg>
        </button>
        <!-- Dividers. Signed-in only, so script unhides it; a brick wall
             reads as "wall" at 20 pixels better than anything abstract. -->
        <button class="ct-btn wall-mode-btn" type="button" id="wallModeBtn" title="Příčky v boxu" data-i18n-title="tool.walls" data-lockkey="walls" aria-pressed="false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="1.5"/><path d="M3 12h18M12 5v7M8 12v7M16 12v7"/></svg>
          <?php if ($studioAccessLocked): ?><span class="ct-lock" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 0 0-5-5Zm-3 8V7a3 3 0 1 1 6 0v3H9Z"/></svg></span><?php endif; ?>
        </button>
        <button class="ct-btn free-mode-btn" type="button" id="freeModeBtn" title="FREE MODE" data-i18n-title="walls.mode.free" data-lockkey="walls" aria-pressed="false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 4v15h14"/><path d="M10 4v10h9"/><path d="M5 14h5"/></svg>
          <?php if ($studioAccessLocked): ?><span class="ct-lock" aria-hidden="true"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1V7a5 5 0 1 1 6 0v3H9Z"/></svg></span><?php endif; ?>
        </button>
      </div>
    </div>
    <div class="ct-right">
      <button class="ct-btn ct-danger" type="button" id="clearLayoutBtn" title="Smazat layout" data-i18n-title="btn.new">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18M8 6V4a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2m2 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M10 11v6M14 11v6"/></svg>
      </button>
      <!-- Undo / redo, between the trash and the view controls. Signed-in
           only, and unhidden from script - a guest's work is not kept, so
           offering to step back through it would be a lie. -->
      <div class="ct-hist" id="ctHist" role="group" aria-label="Undo and redo" data-i18n-aria="aria.history" hidden>
        <button class="ct-btn" type="button" id="undoBtn" title="Zpět" data-i18n-title="tool.undo" disabled>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="M9 14 4 9l5-5"/><path d="M4 9h9a6 6 0 0 1 0 12h-3"/></svg>
        </button>
        <button class="ct-btn" type="button" id="redoBtn" title="Vpřed" data-i18n-title="tool.redo" disabled>
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"><path d="m15 14 5-5-5-5"/><path d="M20 9h-9a6 6 0 0 0 0 12h3"/></svg>
        </button>
      </div>
      <!-- Zoom first, then centre: the two re-framing buttons (centre and
           fit) sit together at the end instead of one on each side of the
           stepper. -->
      <div class="ct-zoom" role="group" aria-label="Zoom" data-i18n-aria="aria.zoom">
        <button class="ct-btn" type="button" id="zoomOut" title="Zoom out" data-i18n-title="tool.zoomout">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M5 12h14"/></svg>
        </button>
        <span class="ct-zoom-val" id="zoomVal">100%</span>
        <button class="ct-btn" type="button" id="zoomIn" title="Zoom in" data-i18n-title="tool.zoomin">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 5v14M5 12h14"/></svg>
        </button>
      </div>
      <button class="ct-btn ct-fs" type="button" id="viewCenter" title="Na střed" data-i18n-title="tool.center">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="2.6"/><path d="M12 3v4M12 17v4M3 12h4M17 12h4"/></svg>
      </button>
      <button class="ct-btn ct-fs" type="button" id="zoomReset" title="Zobrazit celý návrh" data-i18n-title="tool.fit">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 9V5a1 1 0 0 1 1-1h4M15 4h4a1 1 0 0 1 1 1v4M20 15v4a1 1 0 0 1-1 1h-4M9 20H5a1 1 0 0 1-1-1v-4"/></svg>
      </button>
    </div>
  </div>

</section>

<aside class="right">
  <!-- Export allowance. Lives here rather than above the canvas: as a full
       width strip it was a banner nobody read, and it pushed the drawing
       area down every time it appeared. -->
  <div class="quota-card" id="quotaBar" hidden></div>

  <div class="panel panel-collapsible" data-panel="layout" id="layoutPanel">
    <div class="panel-head">
      <h3 data-i18n="panel.layout">Layout</h3>
      <!-- On a phone the settings fill the screen and hide the very thing
           being generated. This folds them away and leaves the buttons. -->
      <button class="panel-fold" id="layoutFold" type="button"
              aria-controls="layoutFoldable" aria-expanded="true" title="Sbalit nastavení">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="M6 15l6-6 6 6M6 9l6-6 6 6"/>
        </svg>
      </button>
    </div>
    <div class="panel-body">
      

      <?php if ($me): ?>
        <div class="layout-divider"><span data-i18n="panel.random">Random Layout</span></div>

        <!-- Everything the fold hides. The Generate button lives outside it,
             so a folded panel is still a working one. -->
        <div class="random-panel" id="layoutFoldable">
          <div><label><span data-i18n="field.minw">Min width (cells)</span><?= info('tip.minw') ?></label><select id="randomMinW"></select></div>
          <div><label><span data-i18n="field.maxw">Max width (cells)</span><?= info('tip.maxw') ?></label><select id="randomMaxW"></select></div>
          <div><label><span data-i18n="field.mind">Min depth (cells)</span><?= info('tip.mind') ?></label><select id="randomMinD"></select></div>
          <div><label><span data-i18n="field.maxd">Max depth (cells)</span><?= info('tip.maxd') ?></label><select id="randomMaxD"></select></div>
          <div>
            <label><span data-i18n="field.fill">Fill level</span><?= info('tip.fill') ?></label>
            <select id="randomFill">
              <option value="0.55">55%</option>
              <option value="0.65">65%</option>
              <option value="0.75">75%</option>
              <option value="0.85">85%</option>
              <option value="1" selected>100%</option>
            </select>
          </div>
          <div>
            <label><span data-i18n="field.variation">Variation</span><?= info('tip.variation') ?></label>
            <select id="randomVariation">
              <option value="balanced" selected data-i18n="var.balanced">Balanced</option>
              <option value="mixed" data-i18n="var.mixed">Mixed</option>
              <option value="large" data-i18n="var.large">Large boxes</option>
              <option value="small" data-i18n="var.small">Small boxes</option>
            </select>
          </div>
          <!-- FREE MODE: the generator may fuse neighbouring boxes into L and
               T pieces. Spans both columns of the panel grid, because it is a
               switch about the whole result rather than one more number. -->
          <label class="random-free" for="randomFree">
            <input type="checkbox" id="randomFree">
            <span><strong data-i18n="field.freemode">FREE MODE</strong>
              <small data-i18n="field.freemode.hint">Some boxes come out as L and T shapes.</small></span>
          </label>
          <button class="btn full" id="autoBtn" type="button" data-i18n="btn.autofill">Auto Fill</button>
        </div>
        <button class="btn primary full" id="randomBtn" type="button" data-i18n="btn.random">Generate Random Layout</button>

        <div class="random-note">
          <span data-i18n="random.note">Generates a new arrangement that fits the current grid.</span>
          <span class="random-seed" id="randomSeed"></span>
        </div>
      <?php else: ?>
        <!-- Visitors get the working fill button above; the generator is what
             an account adds, so it is described rather than shown disabled. -->
        <div class="layout-locked">
          <div class="layout-locked-head">
            <span class="lock" aria-hidden="true">&#128274;</span>
            <span data-i18n="locked.title">With an account</span>
          </div>
          <p data-i18n="locked.body">Generate random arrangements with your own minimum and maximum box sizes, fill level and style.</p>
          <a class="btn full" href="login.php?next=index.php" data-i18n="acct.signin">Sign in</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="panel" data-panel="inspector">
    <div class="panel-head"><h3 data-i18n="panel.inspector">Inspector</h3></div>
    <div class="panel-body">
      <div class="stat-grid">
        <div class="stat-tile"><span data-i18n="stat.boxes">Boxes</span><b id="boxCount">0</b></div>
        <div class="stat-tile"><span data-i18n="stat.used">Used cells</span><b id="used">0%</b></div>
        <div class="stat-tile"><span data-i18n="stat.free">Free cells</span><b id="free">0</b></div>
        <div class="stat-tile"><span data-i18n="stat.status">Status</span><b id="status" class="ok">READY</b></div>
      </div>
      <div class="inspector-error" id="inspectorError" hidden></div>
      <button id="fixBtn" class="btn full" type="button" hidden style="margin-top:10px" data-i18n="btn.fix">Fix</button>
    </div>
  </div>

  <div class="panel" data-panel="howto">
    <div class="panel-head"><h3 data-i18n="panel.howto">How To</h3></div>
    <div class="panel-body">
      <div class="help">
        <p class="help-lead" data-i18n="help.intro">Design a custom drawer organizer and export it as a print-ready 3MF or STL file. Everything updates live as you change it.</p>

        <div class="help-limits" id="helpLimits"></div>

        <h4 data-i18n="help.dims.t">Drawer size</h4>
        <p data-i18n="help.dims.b">Measure the drawer inside, not the front, and type the width, depth and height. Below them sit wall thickness, floor thickness, corner radius and the gap between boxes - the gap is why finished boxes are a little smaller than the cell they stand in, and why they drop in instead of wedging.</p>

        <h4 data-i18n="help.grid.t">Grid</h4>
        <p data-i18n="help.grid.b">Columns and rows are the raster every box snaps to. Changing them keeps what you have already drawn. The + and - squares around the edge of the drawing add or remove a whole column or row on that side; a column that still holds a box will not go.</p>

        <h4 data-i18n="help.edit.t">Drawing and editing</h4>
        <p data-i18n="help.edit.b">Click an empty cell to put a 1x1 box there. Drag a box to move it, drag an edge or a corner to resize it - boxes snap to the grid and never overlap. A selected box gets a bubble beside it with its size, a duplicate button and a bin, arrows on every edge it can grow into, and a minus in each cell it can give back. That is how an L or a T is sculpted out of a rectangle.</p>

        <h4 data-i18n="help.generate.t">Generator</h4>
        <p data-i18n="help.generate.b">Fills the whole drawer for you. Set the smallest and largest box in cells, how full it should be and the style, then Generate; every run comes out different. At 100 percent the leftover gaps are taken by smaller boxes than the minimum, because a hole one cell wide can hold nothing else.</p>

        <h4 data-i18n="help.tools.t">Tools under the drawing</h4>
        <p data-i18n="help.tools.b">The arrow selects and edits; the hand moves the canvas and lets go of the selected box. Then the bin that clears the whole layout, undo and redo, the zoom stepper, centre the view, and fit the whole design on screen.</p>

        <h4 data-i18n="help.print.t">Print area and fixing</h4>
        <p data-i18n="help.print.b">Enter your printer bed and the studio stops you from making a box that will not fit on it: it turns red, export is blocked and the Overview icon gets a red mark. Its Fix button adds columns and rows until everything fits, without touching the drawer size.</p>

        <h4 data-i18n="help.export.t">Export</h4>
        <p data-i18n="help.export.b">3MF keeps every box as a separate object, which is what Bambu Studio and OrcaSlicer like best. STL puts them in one file as separate shells. The model is always built at its real size in millimetres, whatever unit the editor is showing you.</p>

        <h4 data-i18n="help.project.t">Save and load a project</h4>
        <p data-i18n="help.project.b">Save project writes the whole design into a small file - drawer, grid, boxes and settings. Load project reads it back, so you can carry on another day or send the design to somebody else.</p>

        <!-- The signed-in features have a category of their own rather than a
             paragraph in the middle: for a visitor this is the reason to
             make an account, and it should be readable at a glance. -->
        <div class="help-pro">
          <h4 data-i18n="help.pro.t">What a signed-in account adds</h4>
          <ul>
            <li data-i18n="help.pro.1">The generator, the print-area check and the Overview with live figures and one-click Fix.</li>
            <li data-i18n="help.pro.2">Undo and redo of the last five steps, in the strip under the drawing (Ctrl+Z, Ctrl+Shift+Z).</li>
            <li data-i18n="help.pro.3">The + and - handles for growing and shrinking the grid.</li>
            <li data-i18n="help.pro.4">Parametric rectangular boxes with adjustable dimensions.</li>
            <li data-i18n="help.pro.8">Dividers inside a box, from the wall button under the drawing.</li>
            <li data-i18n="help.pro.5">Saving and loading a project as a file.</li>
            <li data-i18n="help.pro.6">Your drawer, grid, print area and current layout are kept in your profile and come back next time - the account icon turns green when everything is saved.</li>
            <li data-i18n="help.pro.7">More free exports than a visitor gets, plus credits, codes and time passes.</li>
          </ul>
        </div>

        <h4 data-i18n="help.walls.t">Dividers inside a box</h4>
        <p data-i18n="help.walls.b">The wall button under the drawing turns a box into something you draw walls in: every line between two of its cells offers a plus, and a click puts a divider there. It is one wall thick, exactly the thickness set in Construction, and it is printed as part of the box - so a box divided into four is one piece, not four. Each end has to run into the outer wall or into another divider; one left hanging in mid-air is reported in the Overview and Fix removes it. Dividers use square internal corners while the outer corners can remain rounded.</p>

        <h4 data-i18n="help.account.t">Account and credits</h4>
        <p data-i18n="help.account.b">Every export first uses your free allowance; once that runs out it costs a credit. The counter inside Export says what is left, and credits are topped up on your account page or with a code. The limits that apply right now are at the top of this help.</p>
      </div>
    </div>
  </div>
</aside>

</main>

</div><!-- /studio-body -->


</div><!-- /workspace -->

<footer class="site-foot">
  <div class="foot-inner">
    <div class="foot-brand">
      <?php if ($brandUrl !== ''): ?>
        <a href="<?= e($brandUrl) ?>" target="_blank" rel="noopener"><?= e($brandLabel) ?></a>
      <?php else: ?>
        <span><?= e($brandLabel) ?></span>
      <?php endif; ?>
      <span class="foot-sep">·</span>
      <span class="foot-sub"><?= e(Settings::get('site_name')) ?></span>
    </div>

    <nav class="foot-links">
      <?php if ($me): ?>
        <a href="account.php" data-i18n="foot.account">My account</a>
      <?php else: ?>
        <a href="login.php?next=index.php" data-i18n="foot.account">My account</a>
      <?php endif; ?>
      <a href="cookies.php" data-i18n="foot.cookies">Cookies</a>
      <?php foreach (Links::all() as $l): ?>
        <a href="<?= e($l['url']) ?>" target="_blank" rel="noopener"><?= e($l['label']) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="foot-meta">
      <!-- No data-i18n: the sentence carries the chosen unit, so script
           fills it (refreshUnitCopy) instead of a plain key lookup. -->
      <span id="footUnits">All dimensions in millimetres.</span>
      <!-- Next to the version, where the small print belongs. -->
      <a class="foot-terms" href="terms.php" data-i18n="foot.terms">Terms of use</a>
      <span class="foot-ver" title="Verze webu">v<?= e(H3D_VERSION) ?></span>
      <span class="foot-year">&copy; <?= date('Y') ?></span>
    </div>
  </div>
</footer>

<div class="export-error" id="exportError" role="alert"></div>

<div class="share-link-backdrop" id="shareLinkBackdrop" hidden>
  <div class="share-link-modal" role="dialog" aria-modal="true" aria-labelledby="shareLinkTitle">
    <button class="share-link-close" id="shareLinkClose" type="button" aria-label="Zavřít">×</button>
    <div class="share-link-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">
        <path d="M10 13.9a4 4 0 0 0 5.7.1l2.1-2.1a4 4 0 0 0-5.7-5.7l-1.2 1.2"/>
        <path d="M14 10.1a4 4 0 0 0-5.7-.1l-2.1 2.1a4 4 0 0 0 5.7 5.7l1.2-1.2"/>
      </svg>
    </div>
    <h3 id="shareLinkTitle" data-i18n="share.popup.title">Odkaz na návrh</h3>
    <p class="share-link-status" data-i18n="share.popup.copied">Odkaz byl zkopírován do schránky.</p>
    <div class="share-link-value" id="shareLinkValue" tabindex="0"></div>
  </div>
</div>

<script>window.H3D_TOKEN=<?= json_encode($token) ?>;window.H3D_LANG=<?= json_encode($lang) ?>;
window.H3D_MAXBOXES=<?= (int) Settings::int('max_boxes') ?>;window.H3D_MAXCELLS=<?= (int) Settings::int('max_cells') ?>;window.H3D_SHARE_KEY=<?= json_encode($shareKey !== '' ? $shareKey : null) ?>;
window.H3D_WORKSPACE_REVISION=<?= (int) (($me && is_array(json_decode((string)($me['prefs'] ?? ''), true))) ? (json_decode((string)($me['prefs'] ?? ''), true)['workspaceRevision'] ?? 0) : 0) ?>;
window.H3D_LIMITS=<?= json_encode([
    'cooldownGuest' => Settings::int('cooldown_guest'),
    'freeGuest'     => Settings::int('free_per_window_guest'),
    'guestExport'   => Settings::bool('guest_export_enabled'),
    'cooldownUser'  => Settings::int('cooldown_user'),
    'freeUser'      => Settings::int('free_per_window_user'),
    'creditCost'    => Cred::fmt(Settings::int('credit_cost')),
    'signupBonus'   => Cred::fmt(Settings::int('signup_bonus')),
    // The daily grant, so the help can state the real number instead of
    // a sentence somebody has to remember to update.
    'dailyCredits'  => Cred::fmt(Settings::int('daily_credits')),
    'dailyCap'      => Cred::fmt(Settings::int('daily_credits_cap')),
    'maxBoxes'      => Settings::int('max_boxes'),
    'maxCells'      => Settings::int('max_cells'),
    // The parameter ranges, so the studio clamps against the same numbers
    // the export is validated with instead of its own copy.
    'ranges'        => Layout::limits(),
]) ?>;
<?php
/*
 * "What's new", written in the admin. Sent only when it is switched on and
 * actually says something - an empty card is worse than none. The English
 * text falls back to the Czech one, because a missing translation should
 * still tell people what changed.
 */
$newsBody = Settings::get($lang === 'en' && Settings::get('news_body_en') !== ''
    ? 'news_body_en' : 'news_body');
$newsVersion = Settings::int('news_version');
$newsSeenVersion = $me ? (int) ($me['news_seen_version'] ?? 0) : $newsVersion;
if ($me && Settings::bool('news_enabled') && trim($newsBody) !== '' && $newsVersion > $newsSeenVersion):
?>
window.H3D_NEWS=<?= json_encode([
    'version' => $newsVersion,
    'webVersion' => H3D_VERSION,
    'tag'   => Settings::get('news_tag'),
    'title' => Settings::get($lang === 'en' && Settings::get('news_title_en') !== ''
        ? 'news_title_en' : 'news_title'),
    'body'  => $newsBody,
]) ?>;
<?php endif; ?>
window.H3D_QUOTA=<?= json_encode([
    'mode'       => $quota['mode'],
    'cost'       => Cred::fmt($quota['cost']),
    'retryAfter' => $quota['retry_after'],
    'cooldown'   => $quota['cooldown'],
    'balance'    => Cred::fmt($quota['balance']),
    'limit'        => $quota['limit'],
    'noFree'       => !empty($quota['free_paid']),
    'windowReset'  => (int) ($quota['window_reset'] ?? 0),
    'freeLeft'     => $quota['free_left'],
    'confirmSpend' => Quota::confirmSpend($me ?? null),
    // Unverified accounts deliberately behave like visitors for Studio
    // feature gates and export rules until their e-mail is confirmed.
    // Authentication unlocks project protection/save/history. Verification
    // remains a separate gate for paid/advanced Studio features.
    'signedIn'   => (bool) ($me !== null),
    'authenticated' => $me !== null,
    'verified'   => (bool) ($me && !empty($me['verified_at'])),
    'userId'     => $me ? (int) $me['id'] : 0,
    'unlimited'  => !empty($quota['unlimited']),
]) ?>;</script>
<script src="assets/adspace.js?v=<?= $assetVersion ?>"></script>
<script src="assets/app.js.php?v=<?= $assetVersion ?>"></script>
<script src="assets/confirm.js?v=<?= $assetVersion ?>"></script>

<!-- First-run welcome. Shown once (tracked in this browser) so a new visitor
     picks language and units up front instead of hunting for them.
     Every choice previews live; the same controls stay in Preferences. -->
<div class="onboard-backdrop" id="onboardBackdrop" hidden>
  <div class="onboard-card" role="dialog" aria-modal="true" aria-labelledby="onboardTitle">
    <img class="onboard-logo" src="assets/icons/favicon.svg" alt="" width="48" height="48">
    <h2 id="onboardTitle" data-i18n="onboard.title">Welcome to the studio</h2>
    <p class="onboard-lede" data-i18n="onboard.lede">Choose how the studio should look. You can change any of this later under Preferences.</p>

    <div class="onboard-group">
      <span class="onboard-label" data-i18n="onboard.language">Language</span>
      <div class="lang-switch onboard-lang" role="group" data-i18n-aria="aria.language" aria-label="Language">
        <button type="button" class="lang-btn" data-lang="en">EN</button>
        <button type="button" class="lang-btn" data-lang="cs">CZ</button>
      </div>
    </div>

    <div class="onboard-group">
      <span class="onboard-label" data-i18n="onboard.units">Units</span>
      <div class="onboard-seg" role="group" id="onboardUnits" aria-label="Units">
        <button type="button" class="onboard-opt" data-unit="mm">mm</button>
        <button type="button" class="onboard-opt" data-unit="in">in</button>
      </div>
    </div>

    <div class="onboard-group"></div>

    <button class="btn primary full onboard-start" id="onboardStart" type="button" data-i18n="onboard.start">Start designing</button>
  </div>
</div>

<!-- Sign-in without leaving the studio. Mistyping /admin lands here with the
     dialog already open, so the layout on the canvas is not thrown away. -->
<div class="signin-backdrop" id="signinBackdrop" hidden>
  <div class="signin-dialog" role="dialog" aria-modal="true" aria-labelledby="signinTitle">
    <h2 id="signinTitle" data-i18n="signin.title">Sign in</h2>
    <p class="signin-lede" data-i18n="signin.lede">Credits and redeemed codes are tied to your account.</p>

    <div class="signin-error" id="signinError" hidden></div>

    <label for="signinEmail" data-i18n="signin.email">Email</label>
    <input id="signinEmail" type="email" autocomplete="email">

    <label for="signinPass" data-i18n="signin.password">Password</label>
    <input id="signinPass" type="password" autocomplete="current-password">

    <div class="signin-actions">
      <button class="btn primary" id="signinSubmit" type="button">
        <span class="signin-spinner" id="signinBusy" hidden aria-hidden="true"></span>
        <span id="signinSubmitLabel" data-i18n="signin.submit">Sign in</span>
      </button>
      <button class="btn" id="signinCancel" type="button" data-i18n="signin.cancel">Cancel</button>
    </div>

    <div class="signin-foot">
      <?php if (Settings::bool('registration_open')): ?>
        <a href="register.php" data-i18n="foot.register">Create an account</a>
      <?php endif; ?>
      <a href="forgot.php" data-i18n="signin.forgot">Forgotten password?</a>
      <a href="login.php" data-i18n="signin.full">Open the full page</a>
    </div>
  </div>
</div>

<!-- First-visit guided tour. It is intentionally independent from the
     preference onboarding: the tour is tracked with a cookie so it only
     appears once per browser and can be skipped at any time. -->
<div class="studio-tour" id="studioTour" hidden aria-hidden="true">
  <div class="studio-tour-shade" id="studioTourShade"></div>
  <div class="studio-tour-spotlight" id="studioTourSpotlight" aria-hidden="true"></div>
  <div class="studio-tour-setup" id="studioTourSetup" hidden role="dialog" aria-labelledby="studioTourSetupTitle">
    <div class="studio-tour-setup-progress"><span id="studioTourSetupStep">1</span><span>/</span><span id="studioTourSetupTotal">1</span></div>
    <div class="studio-tour-setup-kicker" id="studioTourSetupKicker">H3D STUDIO</div>
    <h2 id="studioTourSetupTitle">Studio setup</h2>
    <p class="studio-tour-setup-lede">Before we start, choose your language and units. You can change these settings later in Quick settings.</p>
    <div class="studio-tour-setup-grid">
      <div class="studio-tour-setup-group">
        <span>Language</span>
        <div class="studio-tour-setup-seg" id="tourSetupLanguage" role="group" aria-label="Jazyk">
          <button type="button" data-lang="en">EN</button>
          <button type="button" data-lang="cs">CZ</button>
        </div>
      </div>
      <div class="studio-tour-setup-group">
        <span>Units</span>
        <div class="studio-tour-setup-seg" id="tourSetupUnits" role="group" aria-label="Jednotky">
          <button type="button" data-unit="mm">mm</button>
          <button type="button" data-unit="in">in</button>
        </div>
      </div>
    </div>
    <button class="studio-tour-setup-continue" id="studioTourSetupContinue" type="button">Continue</button>
  </div>
  <div class="studio-tour-card" id="studioTourCard" role="dialog" aria-modal="true" aria-labelledby="studioTourTitle">
    <div class="studio-tour-progress"><span id="studioTourStep">1</span><span>/</span><span id="studioTourTotal">1</span></div>
    <h2 id="studioTourTitle">Vítej ve studiu</h2>
    <p id="studioTourText"></p>
    <div class="studio-tour-actions">
      <button class="studio-tour-skip" id="studioTourSkip" type="button">Skip tour</button>
      <div class="studio-tour-nav">
        <button class="studio-tour-prev" id="studioTourPrev" type="button">Back</button>
        <button class="studio-tour-next" id="studioTourNext" type="button">Next</button>
      </div>
    </div>
  </div>
</div>

<?php if ($maintenanceWarning): ?>
<!-- Maintenance is intentionally not dismissible. The public API is closed
     at this point, so continuing to edit could look saved when it is not. -->
<div class="maintenance-backdrop" role="alertdialog" aria-modal="true" aria-labelledby="maintenanceTitle" aria-describedby="maintenanceBody">
  <section class="maintenance-dialog">
    <span class="maintenance-kicker">H3D · ÚDRŽBA SLUŽBY</span>
    <h2 id="maintenanceTitle">Na webu právě probíhají práce</h2>
    <p id="maintenanceBody">Aplikace může být dočasně nestabilní a některé funkce nemusí fungovat. Nedoporučujeme ji teď používat ani upravovat návrh — kvůli ochraně dat můžeš být kdykoliv přesměrován.</p>
    <p class="maintenance-note">Tvůj účet zůstává v bezpečí. Až údržba skončí, služba se znovu zpřístupní automaticky.</p>
    <a class="btn maintenance-exit" href="logout.php">Odhlásit se</a>
  </section>
</div>
<?php endif; ?>

<script>
(function(){
 const f=document.getElementById('messageAdminForm'); if(!f) return;
 const status=document.getElementById('messageAdminStatus');
 f.addEventListener('submit', async function(e){
   e.preventDefault();
   if(window.H3DConfirm && !await window.H3DConfirm('Odeslat tuto zprávu živému kolegovi?', 'Odeslat zprávu')) return;
   const btn=f.querySelector('button[type=submit]'); btn.disabled=true; status.hidden=true;
   const fd=new FormData(f); fd.append('csrf', window.H3D_CSRF || '');
   try {
     const r=await fetch('api/user-message.php',{method:'POST',body:fd,credentials:'same-origin'});
     const j=await r.json();
     if(!r.ok || !j.ok) throw new Error(j.error || 'Zprávu se nepodařilo odeslat.');
     status.textContent='Zpráva byla odeslána živému kolegovi.'; status.hidden=false;
     const thread=document.createElement('article'); thread.className='message-thread';
     const subject=document.createElement('h4'); subject.className='message-subject'; subject.textContent=fd.get('subject')||'Zpráva'; thread.append(subject);
     const mine=document.createElement('div'); mine.className='message-bubble mine';
     const title=document.createElement('b'); title.textContent='Moje zpráva';
     const body=document.createElement('div'); body.textContent=fd.get('body')||'';
     const when=document.createElement('small'); when.textContent=new Date().toLocaleString();
     mine.append(title,body,when); thread.append(mine);
     const wait=document.createElement('p'); wait.className='hint message-wait'; wait.textContent='Čeká na odpověď administrace.'; thread.append(wait);
     const list=document.querySelector('.my-messages'); if(list){ const empty=list.querySelector('.hint'); if(empty)empty.remove(); list.append(thread); }
     const panelTitle=document.getElementById('messagePanelTitle'); if(panelTitle)panelTitle.textContent=fd.get('subject')||'Zpráva';
     const note=document.createElement('div'); note.id='activeChatNote'; note.className='message-console-note'; note.innerHTML='<span>AKTIVNÍ CHAT</span><b>Nejdřív dokonči tuto konverzaci se správcem.</b>';
     f.replaceWith(note);
   } catch(err){ status.textContent=err.message; status.hidden=false; }
   finally { btn.disabled=false; }
 });
})();
</script>
<script>
(function(){
  const f=document.getElementById('messageReplyForm');
  if(!f) return;
  const status=document.getElementById('messageReplyStatus');
  f.addEventListener('submit',async function(e){
    e.preventDefault();
    if(window.H3DConfirm && !await window.H3DConfirm('Odeslat odpověď živému kolegovi?', 'Odeslat odpověď')) return;
    const btn=f.querySelector('button[type=submit]');
    btn.disabled=true;
    status.hidden=true;
    const fd=new FormData(f);
    fd.append('action','reply_chat');
    fd.append('csrf',window.H3D_CSRF||'');
    try {
      const r=await fetchSupportWithDelay('api/user-message.php',{method:'POST',body:fd,credentials:'same-origin'},dramatic);
      const data=await r.json().catch(()=>({}));
      if(!r.ok || !data.ok) throw new Error(data.error||'Odpověď se nepodařilo odeslat.');
      window.location.reload();
    } catch(err) {
      status.textContent=err.message;
      status.hidden=false;
      btn.disabled=false;
    }
  });
})();
</script>
<script>
(function(){
  const tabs=document.querySelectorAll('.message-tab');
  const panes=document.querySelectorAll('.message-tab-pane');
  tabs.forEach(tab=>{
    tab.addEventListener('click',()=>{
      const name=tab.dataset.messageTab;
      tabs.forEach(t=>{
        const on=t===tab;
        t.classList.toggle('is-on',on);
        t.setAttribute('aria-selected',on?'true':'false');
      });
      panes.forEach(p=>{
        const on=p.dataset.messagePane===name;
        p.classList.toggle('is-on',on);
        p.hidden=!on;
      });
    });
  });

  document.querySelectorAll('.message-history-info').forEach(btn=>{
    btn.addEventListener('click',()=>{
      const el=document.getElementById('message-history-detail-'+btn.dataset.messageHistory);
      if(!el)return;
      const open=!el.hidden;
      document.querySelectorAll('.message-history-detail').forEach(x=>x.hidden=true);
      document.querySelectorAll('.message-history-info').forEach(x=>x.setAttribute('aria-expanded','false'));
      el.hidden=open;
      btn.setAttribute('aria-expanded',open?'false':'true');
    });
  });
})();
</script>
<script>
(function(){
  document.querySelectorAll('.close-chat-form').forEach(form=>form.addEventListener('submit',async e=>{
    e.preventDefault();
    if(window.H3DConfirm && !await window.H3DConfirm('Opravdu označit tuto konverzaci jako vyřešenou?', 'Ano, vyřešit')) return;
    const fd=new FormData(form); fd.append('action','close_chat'); fd.append('csrf',window.H3D_CSRF||'');
    const r=await fetch('api/user-message.php',{method:'POST',body:fd,credentials:'same-origin'});
    if(r.ok){
      const thread=form.closest('.message-thread'); if(thread)thread.classList.add('is-closed');
      form.outerHTML='<p class="message-resolved">✓ Konverzace je označená jako vyřešená.</p>';
      const note=document.getElementById('activeChatNote');
      if(note)note.outerHTML='<div class="message-console-note is-closed"><span>UZAVŘENO</span><b>Konverzace byla označena jako vyřešená.</b></div>';
    }
  }));
})();
</script>
<script>
(function(){
  const button=document.querySelector('.nav-item[data-flyout="messageAdmin"]');
  if(!button) return;
  button.addEventListener('click',function(){
    const badge=document.getElementById('messageBadge');
    if(!badge) return;
    const fd=new FormData(); fd.append('action','read_replies'); fd.append('csrf',window.H3D_CSRF||'');
    fetch('api/user-message.php',{method:'POST',body:fd,credentials:'same-origin'}).then(r=>{if(r.ok)badge.remove();}).catch(()=>{});
  });
})();
</script>
<script>
(function(){
  const TOUR_COOKIE='h3d_studio_tour_seen';
  const tour=document.getElementById('studioTour');
  if(!tour) return;
  const card=document.getElementById('studioTourCard');
  const setup=document.getElementById('studioTourSetup');
  const setupContinue=document.getElementById('studioTourSetupContinue');
  const spotlight=document.getElementById('studioTourSpotlight');
  const title=document.getElementById('studioTourTitle');
  const text=document.getElementById('studioTourText');
  const stepEl=document.getElementById('studioTourStep');
  const totalEl=document.getElementById('studioTourTotal');
  const prev=document.getElementById('studioTourPrev');
  const next=document.getElementById('studioTourNext');
  const skip=document.getElementById('studioTourSkip');
  let target=null, index=0, steps=[];
  let tourBlockers=[];
  let setupOpen=false;

  const navInfo={
    dims:{title:'Rozměry',text:'Nastavíš zde základní rozměry návrhu: šířku, hloubku a konstrukční parametry boxů.'},
    print:{title:'Tisková plocha',text:'Nastavíš rozměry tiskové plochy a limity, podle kterých studio hlídá velikost návrhu.'},
    grid:{title:'Mřížka',text:'Zapneš nebo upravíš zobrazení mřížky na pracovní ploše, aby se ti návrh lépe zarovnával.'},
    layout:{title:'Layout',text:'Spravuješ rozložení boxů a další nastavení celého návrhu.'},
    inspector:{title:'Inspektor',text:'Zobrazí podrobnosti vybraného boxu a jeho parametry, které můžeš upravovat.'},
    export:{title:'Export',text:'Odtud exportuješ hotový návrh a můžeš použít sdílení návrhu přes odkaz.'},
    credits:{title:'Volné exporty',text:'Zde vidíš, kolik bezplatných exportů ti zbývá a kolik jich máš k dispozici celkem.'},
    prefs:{title:'Rychlé nastavení',text:'Rychle zde změníš jazyk a zobrazované jednotky.'},
    howto:{title:'Návod',text:'Otevřeš zde praktickou nápovědu k práci se Studiem.'},
    messageAdmin:{title:'Zprávy',text:'Otevřeš zde komunikaci s administrací a můžeš číst nebo posílat zprávy.'}
  };
  const tourEn={
    'Důležité pro návštěvníky':['Important for visitors','This draft is temporary. If you refresh or close the page, the grid contents will be cleared. Sign in if you want to keep the design and return to it later.'],
    'Rozměry':['Drawer size','Set the main design dimensions: width, depth and the construction parameters of the boxes.'],
    'Tisková plocha':['Print area','Set the printer bed size and limits used to warn you when a design is too large.'],
    'Volné exporty':['Free exports','See how many free exports you have left and how many exports are available in total.'],
    'Sign in':['Sign in','Sign in to save your designs, use account features and return to your work later.'],
    'Account':['Account','Open your account settings, preferences and saved profile information.'],
    'Administration':['Administration','Open the administration area for managing the Studio and its settings.'],
    'Sign out':['Sign out','Sign out of your H3D account.'],
    'Quick settings':['Quick settings','Quickly change language and display units.'],
    'How to use':['How to use','Open the help and usage guide with practical information about working in the Studio.'],
    'Neomezené exporty':['Unlimited exports','See that your account has unlimited exports available.'],
    'Kredity':['Credits','View your current export credits and the cost of an export.'],
    'Účet':['Account','Open your account settings, preferences and saved profile information.'],
    'Administrace':['Administration','Open the administration area for managing the Studio and its settings.'],
    'Odhlásit':['Sign out','Sign out of your H3D account.'],
    'Přihlásit':['Sign in','Sign in to save your designs, use account features and return to your work later.'],
    'Rychlé nastavení':['Quick settings','Quickly change language and display units.'],
    'Návod':['How to use','Open the help and usage guide with practical information about working in the Studio.'],
    'Zprávy':['Messages','Open your conversation with the administrator and read or send messages.'],
    'Mřížka':['Grid','Turn the workspace grid on or off and use it to align boxes accurately.'],
    'Layout':['Layout','Manage the arrangement of boxes and the overall layout settings.'],
    'Inspektor':['Inspector','See a live overview of the design, including box count, used cells, free cells and validity.'],
    'Export':['Export','Export the finished design and create a share link for the current layout.'],
    'Rozbalit / sbalit menu':['Expand / collapse menu','Expand the left menu to see the tool names. Click again to return to icon-only mode.'],
    'Výběr':['Select','Activate the selection tool. Click a box to select it and edit, duplicate or remove it.'],
    'Posun plátna':['Pan canvas','Move around the workspace without changing the design itself.'],
    'Příčky':['Dividers','Enable divider mode to add, remove and edit internal dividers inside a box.'],
    'FREE MODE':['FREE MODE','Resize boxes freely by individual cells using the controls on the box edges.'],
    'Smazat layout':['Clear layout','Remove the current layout and start a new design.'],
    'Zpět':['Undo','Undo the last change made to the design.'],
    'Vpřed':['Redo','Redo a change that you previously undid.'],
    'Oddálit':['Zoom out','Zoom out to see more of the workspace at once.'],
    'Přiblížit':['Zoom in','Zoom in for more precise work with boxes.'],
    'Na střed':['Center view','Center the view on the workspace.'],
    'Přizpůsobit':['Fit to screen','Fit the whole design into the available screen space.']
  };
  const canvasInfo={
    '#canvasTools [data-tool="select"]':['Výběr','Aktivuješ výběrový nástroj. Kliknutím na box ho vybereš a můžeš ho upravovat, duplikovat nebo odstranit.'],
    '#canvasTools [data-tool="pan"]':['Posun plátna','Přepneš do režimu posouvání pracovní plochy bez změny samotného návrhu.'],
    '#wallModeBtn':['Příčky','Zapneš režim příček. V boxu pak můžeš přidávat, odebírat a upravovat vnitřní příčky.'],
    '#freeModeBtn':['FREE MODE','Zapneš volné rozšiřování boxu po jednotlivých buňkách pomocí ovládacích bodů na hranách.'],
    '#clearLayoutBtn':['Smazat layout','Smažeš celý aktuální layout a můžeš začít nový návrh.'],
    '#undoBtn':['Zpět','Vrátíš poslední provedenou změnu v návrhu.'],
    '#redoBtn':['Vpřed','Znovu provedeš změnu, kterou jsi předtím vrátil.'],
    '#zoomOut':['Oddálit','Oddálíš pracovní plochu, abys viděl větší část návrhu.'],
    '#zoomIn':['Přiblížit','Přiblížíš pracovní plochu pro přesnější práci s boxy.'],
    '#viewCenter':['Na střed','Vycentruješ pohled na pracovní plochu.'],
    '#zoomReset':['Přizpůsobit','Přizpůsobíš celý návrh dostupnému prostoru na obrazovce.']
  };

  function getCookie(name){return document.cookie.split('; ').some(v=>v.indexOf(name+'=')===0);}
  function markSeen(){document.cookie=TOUR_COOKIE+'=1;path=/;max-age=31536000;samesite=lax';}
  function visible(el){
    if(!el) return false;
    const cs=getComputedStyle(el),r=el.getBoundingClientRect();
    return cs.display!=='none'&&cs.visibility!=='hidden'&&r.width>2&&r.height>2;
  }
  function add(el,t,d){if(visible(el))steps.push({el,title:t,text:d});}
  function tourTotal(){return steps.length+1;}
  function buildSteps(){
    steps=[];
    if(!(window.H3D_QUOTA&&window.H3D_QUOTA.signedIn)){
      const canvas=document.querySelector('.canvasWrap');
      if(visible(canvas)) add(canvas,'Důležité pro návštěvníky','Tento návrh je dočasný. Pokud obnovíš nebo zavřeš stránku, obsah gridu se smaže. Chceš-li návrh zachovat a vracet se k němu později, přihlas se.');
    }
    document.querySelectorAll('.studio-nav .nav-item').forEach(el=>{
      if(el.classList.contains('nav-collapse') || el.matches('.nav-collapse')) return;
      if(!visible(el)) return;
      const key=el.dataset.flyout;
      const locked=el.classList.contains('guest-lock')||!!el.querySelector('.nav-lock');
      const special=el.classList.contains('nav-signin')?['Přihlásit se','Přihlásíš se, aby se návrhy a účetní funkce mohly uložit a byly dostupné i později.']
        :el.classList.contains('nav-account')?['Účet','Otevřeš nastavení svého účtu a uložené osobní preference.']
        :el.classList.contains('nav-admin')?['Administrace','Otevřeš administraci Studia a jeho správu.']
        :el.classList.contains('nav-signout')?['Odhlásit se','Odhlásíš se ze svého H3D účtu.']
        :el.classList.contains('nav-messages')?['Zprávy','Otevřeš komunikaci s administrací a můžeš číst nebo posílat zprávy.']
        :null;
      const info=navInfo[key];
      if(special){
        add(el,special[0],locked?special[1]+' Tato funkce je dostupná po přihlášení.':special[1]);
      }else if(info){
        add(el,info.title,locked?info.text+' Tato funkce je dostupná po přihlášení.':info.text);
      }else{
        add(el,(el.querySelector('.nav-label')?.innerText||'Nástroj').trim(),locked?'Tato funkce je dostupná po přihlášení.':'Otevřeš zde příslušný nástroj.');
      }
    });
    const navToggle=document.getElementById('navToggle');
    if(visible(navToggle)) add(navToggle,'Rozbalit / sbalit menu','Tlačítkem rozbalíš levé menu a zobrazíš názvy všech nástrojů. Dalším kliknutím ho zase sbalíš na samotné ikony.');
    Object.entries(canvasInfo).forEach(([sel,[t,d]])=>add(document.querySelector(sel),t,d));
    const total=tourTotal();
    totalEl.textContent=String(total);
    const setupTotal=document.getElementById('studioTourSetupTotal');
    if(setupTotal) setupTotal.textContent=String(total);
  }
  function syncSetup(){
    const unit=document.getElementById('unitSelect')?.value||'mm';
    const light=document.body.classList.contains('light');
    let currentLang='en';
    try{ currentLang=localStorage.getItem('honza3d-lang')||'en'; }catch(_){ currentLang='en'; }
    document.querySelectorAll('#tourSetupLanguage button').forEach(b=>b.classList.toggle('active',b.dataset.lang===currentLang));
    document.querySelectorAll('#tourSetupUnits button').forEach(b=>b.classList.toggle('active',b.dataset.unit===unit));const en=currentLang!=='cs';
    const st=document.getElementById('studioTourSetupTitle');
    const sl=document.querySelector('.studio-tour-setup-lede');
    const labels=document.querySelectorAll('.studio-tour-setup-group>span');
    if(st)st.textContent=en?'Studio setup':'Nastavení studia';
    if(sl)sl.textContent=en?'Before we start, choose your language and units.':'Než začneme, vyber si jazyk a jednotky.';
    ['Language','Units'].forEach((v,i)=>{if(labels[i])labels[i].textContent=en?v:['Jazyk','Jednotky'][i];});if(setupContinue)setupContinue.textContent=en?'Continue':'Pokračovat';
    if(skip)skip.textContent=en?'Skip tour':'Přeskočit tour';
    if(prev)prev.textContent=en?'Back':'Zpět';
    if(next)next.textContent=(index===steps.length-1)?(en?'Done':'Hotovo'):(en?'Next':'Další');
    if(target){const item=steps[index];const localized=currentLang==='cs'?null:tourEn[item.title];title.textContent=localized?localized[0]:item.title;text.textContent=localized?localized[1]:item.text;}
  }
  function showSetup(){
    if(!setup) return;
    clearTarget();
    setup.hidden=false; setupOpen=true;
    if(card) card.hidden=true;
    const setupStep=document.getElementById('studioTourSetupStep');
    const setupTotal=document.getElementById('studioTourSetupTotal');
    if(setupStep) setupStep.textContent='1';
    if(setupTotal) setupTotal.textContent=String(tourTotal());
    syncSetup();
  }
  function hideSetup(){
    if(!setup) return;
    setup.hidden=true; setupOpen=false;
    if(card) card.hidden=false;
  }
  function clearTarget(){
    if(target){target.classList.remove('studio-tour-target');target=null;}
    tourBlockers.forEach(el=>el.remove());
    tourBlockers=[];
    if(spotlight){spotlight.style.width='0';spotlight.style.height='0';}
  }
  function finish(){
    markSeen();
    try{ localStorage.setItem('honza3d-onboarded','1'); }catch(_){}
    hideSetup();clearTarget();tour.hidden=true;tour.setAttribute('aria-hidden','true');document.body.classList.remove('studio-tour-active');
    if(window.H3D_QUOTA&&window.H3D_QUOTA.signedIn) setTimeout(showNews,120);
  }

  function positionCard(rect){
    const gap=18,margin=14;
    card.classList.remove('is-right','is-left','is-top','is-bottom');
    card.style.left='';card.style.right='';card.style.top='';card.style.bottom='';
    const cw=Math.min(390,window.innerWidth-margin*2),ch=card.offsetHeight;
    let placement='bottom',left,top;
    const spaces={right:window.innerWidth-rect.right-margin,left:rect.left-margin,bottom:window.innerHeight-rect.bottom-margin,top:rect.top-margin};
    if(rect.left<90 && spaces.right>=Math.min(cw,330)){
      placement='right'; left=rect.right+gap; top=rect.top+rect.height/2-ch/2;
    }else if(spaces.top>=ch+gap && rect.top>window.innerHeight*.38){
      placement='top'; left=rect.left+rect.width/2-cw/2; top=rect.top-ch-gap;
    }else{
      placement='bottom'; left=rect.left+rect.width/2-cw/2; top=rect.bottom+gap;
      if(top+ch>window.innerHeight-margin && spaces.left>=cw){placement='left';left=rect.left-cw-gap;top=rect.top+rect.height/2-ch/2;}
    }
    left=Math.max(margin,Math.min(left,window.innerWidth-cw-margin));
    top=Math.max(margin,Math.min(top,window.innerHeight-ch-margin));
    card.style.width=cw+'px';card.style.left=left+'px';card.style.top=top+'px';card.classList.add('is-'+placement);
  }
  function createTourBlockers(rect){
    tourBlockers.forEach(el=>el.remove());
    tourBlockers=[];
    const specs=[
      {left:0,top:0,width:window.innerWidth,height:Math.max(0,rect.top)},
      {left:0,top:rect.bottom,width:window.innerWidth,height:Math.max(0,window.innerHeight-rect.bottom)},
      {left:0,top:rect.top,width:Math.max(0,rect.left),height:Math.max(0,rect.height)},
      {left:rect.right,top:rect.top,width:Math.max(0,window.innerWidth-rect.right),height:Math.max(0,rect.height)}
    ];
    specs.forEach(spec=>{
      const blocker=document.createElement('div');
      blocker.className='studio-tour-blocker';
      blocker.style.left=spec.left+'px';
      blocker.style.top=spec.top+'px';
      blocker.style.width=spec.width+'px';
      blocker.style.height=spec.height+'px';
      tour.appendChild(blocker);
      tourBlockers.push(blocker);
    });
  }

  function blockTargetInteraction(e){
    if(tour.hidden||!target)return;
    if(e.target instanceof Node && target.contains(e.target)){
      e.preventDefault();
      e.stopPropagation();
      if(typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation();
    }
  }
  document.addEventListener('pointerdown',blockTargetInteraction,true);
  document.addEventListener('click',blockTargetInteraction,true);

  function show(i){
    clearTarget(); index=Math.max(0,Math.min(i,steps.length-1));
    if(setup) setup.hidden=true; setupOpen=false;
    if(card) card.hidden=false;
    const item=steps[index]; target=item.el; target.classList.add('studio-tour-target');
    let currentLang='en';
    try{ currentLang=localStorage.getItem('honza3d-lang')||'en'; }catch(_){ currentLang='en'; }
    const localized=currentLang==='cs'?null:tourEn[item.title];
    title.textContent=localized?localized[0]:item.title;
    text.textContent=localized?localized[1]:item.text;
    stepEl.textContent=String(index+2);totalEl.textContent=String(tourTotal());
    prev.disabled=false;
    next.textContent=index===steps.length-1?(currentLang==='cs'?'Hotovo':'Done'):(currentLang==='cs'?'Další':'Next');
    syncSetup();
    requestAnimationFrame(()=>{const rect=target.getBoundingClientRect();positionCard(rect);if(spotlight){spotlight.style.left=(rect.left-3)+'px';spotlight.style.top=(rect.top-3)+'px';spotlight.style.width=(rect.width+6)+'px';spotlight.style.height=(rect.height+6)+'px';}createTourBlockers(rect);});
  }
  function start(){
    if(getCookie(TOUR_COOKIE))return;
    buildSteps(); if(!steps.length)return;
    tour.hidden=false;tour.setAttribute('aria-hidden','false');document.body.classList.add('studio-tour-active');
    if(card) card.hidden=true;
    showSetup();
  }

  function continueTour(){
    if(setupOpen){ hideSetup(); show(0); return; }
    if(index<steps.length-1) show(index+1); else finish();
  }
  next.addEventListener('click',continueTour);
  prev.addEventListener('click',()=>{if(index===0){showSetup();return;} show(index-1);});
  skip.addEventListener('click',finish);
  if(setupContinue) setupContinue.addEventListener('click',()=>{hideSetup();show(0);});
  document.querySelectorAll('#tourSetupLanguage button').forEach(b=>b.addEventListener('click',()=>{if(typeof setLang==='function')setLang(b.dataset.lang);syncSetup();}));
  document.querySelectorAll('#tourSetupUnits button').forEach(b=>b.addEventListener('click',()=>{const sel=document.getElementById('unitSelect');if(sel&&sel.value!==b.dataset.unit){sel.value=b.dataset.unit;sel.dispatchEvent(new Event('change'));}syncSetup();}));window.addEventListener('resize',()=>{if(!tour.hidden&&target){const rect=target.getBoundingClientRect();positionCard(rect);if(spotlight){spotlight.style.left=(rect.left-3)+'px';spotlight.style.top=(rect.top-3)+'px';spotlight.style.width=(rect.width+6)+'px';spotlight.style.height=(rect.height+6)+'px';}createTourBlockers(rect);}});
  document.addEventListener('keydown',e=>{if(tour.hidden)return;if(setupOpen){if(e.key==='Escape'||e.key==='ArrowRight'||e.key==='ArrowLeft'){e.preventDefault();}return;}if(e.key==='Escape'){e.preventDefault();finish();}else if(e.key==='ArrowRight'){e.preventDefault();continueTour();}else if(e.key==='ArrowLeft'&&!prev.disabled){e.preventDefault();prev.click();}});
  window.addEventListener('load',function waitForOnboarding(){
    if(getCookie(TOUR_COOKIE))return;
    if(document.body.classList.contains('onboarding')){setTimeout(waitForOnboarding,400);return;}
    setTimeout(start,650);
  });
})();
</script>
<script>window.H3D_CSRF=<?= json_encode($studioCsrf) ?>;
window.H3D_SIGNIN=<?= json_encode(isset($_GET['signin'])) ?>;
window.H3D_SIGNIN_NEXT=<?= json_encode((string) ($_GET['next'] ?? '')) ?>;
window.H3D_VERIFIED=<?= json_encode((bool)($me && !empty($me['verified_at']))) ?>;
window.H3D_AUTHENTICATED=<?= json_encode((bool)$me) ?>;</script>
<?= Consent::render() ?>

<script>
(function(){
  if(!window.H3D_AUTHENTICATED) return;
  const panel=document.querySelector('.h3d-support-panel');
  if(!panel) return;

  const body=document.getElementById('supportChatBody');
  const log=document.getElementById('supportMessageLog');
  const form=document.getElementById('supportChatForm');
  const input=document.getElementById('supportChatInput');
  const quick=document.getElementById('supportQuick');
  const feedback=document.getElementById('supportFeedback');
  const archivePanel=document.getElementById('supportArchivePanel');
  const archiveList=document.getElementById('supportArchiveList');
  const archiveDetail=document.getElementById('supportArchiveDetail');
  const endBtn=document.getElementById('supportEndChat');
  const liveTitle=document.getElementById('supportLiveTitle');
  const limitLabel=document.getElementById('supportMessageLimit');
  let consecutiveUserMessages=0;
  const MAX_USER_MESSAGES=5;
  const csrf=window.H3D_CSRF||'';
  let waitingForAdmin=false;
  let liveMode=false;
  let adminMessageId=null;
  let lastAdminPostAt=0;
  let pollTimer=null;
  const usedQuickActions=new Set();
  const availableQuickActions=new Map();
  let quickProcessing=false;

  function updateComposerState(){
    const canWrite=liveMode && !waitingForAdmin && consecutiveUserMessages < MAX_USER_MESSAGES;
    if(form) form.hidden=!liveMode;
    if(input){
      input.hidden=!liveMode;
      input.disabled=!canWrite;
      input.placeholder=waitingForAdmin ? 'Čeká se na odpověď živého kolegy…' : 'Napiš zprávu živému kolegovi…';
    }
    const send=form?.querySelector('.support-send');
    if(send){ send.hidden=!liveMode; send.disabled=!canWrite; }
    if(endBtn){ endBtn.hidden=!liveMode; endBtn.disabled=!liveMode; }
    if(liveTitle) liveTitle.hidden=!liveMode;
    if(limitLabel){
      limitLabel.hidden=!liveMode;
      limitLabel.textContent=waitingForAdmin ? 'Čeká se na odpověď živého kolegy' : `Zprávy ${consecutiveUserMessages}/${MAX_USER_MESSAGES}`;
    }
  }

  function enableLiveComposer(message='Napiš zprávu živému kolegovi…'){
    liveMode=true;
    if(input) input.placeholder=message;
    updateComposerState();
    if(input && !input.disabled) input.focus();
  }

  function scrollBottom(){
    if(body) body.scrollTop=body.scrollHeight;
  }

  function resetToNewChat(){
    stopPolling();
    waitingForAdmin=false;
    liveMode=false;
    adminMessageId=null;
    lastAdminPostAt=0;
    consecutiveUserMessages=0;
    window.__h3dPendingArchiveTranscript=null;
    window.__h3dArchivedMessageId=0;
    usedQuickActions.clear();
    availableQuickActions.clear();
    quickProcessing=false;

    if(log){
      log.innerHTML=`<div class="support-message support-message-helper">
        <div class="support-avatar"><span class="support-avatar-glyph"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.2"/><path d="M5.5 20c.7-4.1 2.9-6.2 6.5-6.2s5.8 2.1 6.5 6.2"/></svg></span></div>
        <div class="support-message-content"><div class="support-message-name">H3D Pomocník</div>
        <div class="support-bubble">Ahoj! 👋 Vítám tě v H3D Studiu. Jsem tvůj virtuální asistent a rád ti okamžitě pomůžu s dotazy ohledně účtu, kreditů nebo exportů. Co tě zajímá?</div></div>
      </div>`;
    }
    if(feedback) feedback.hidden=true;
    if(form){
      form.hidden=true;
      form.classList.remove('is-ended','is-waiting');
    }
    if(input){
      input.value='';
      input.disabled=false;
      input.hidden=true;
    }
    const send=form?.querySelector('.support-send');
    if(send){ send.hidden=true; send.disabled=false; }
    if(endBtn){ endBtn.hidden=true; endBtn.disabled=false; }
    if(liveTitle) liveTitle.hidden=true;
    if(limitLabel) limitLabel.hidden=true;
    const info=document.getElementById('supportInfoPanel');
    if(info) info.hidden=true;
    renderQuick(['Moje balíčky','Moje aktivní předplatné','Moje objednávky','Moje kredity','Moje exporty','Nabídka kreditů / časového plánu','Podmínky','Promo akce','Předat živému kolegovi']);
    scrollBottom();
  }

  function escapeHtml(v){
    return String(v).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[c]));
  }

  function addMessage(kind,name,html){
    const row=document.createElement('div');
    row.className='support-message '+(kind==='user'?'support-message-user':'support-message-helper');
    row.innerHTML='<div class="support-avatar support-avatar-small"><span class="support-avatar-glyph" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.2"/><path d="M5.5 20c.7-4.1 2.9-6.2 6.5-6.2s5.8 2.1 6.5 6.2"/></svg></span></div>'+
      '<div class="support-message-content"><div class="support-message-name"></div><div class="support-bubble"></div></div>';
    row.querySelector('.support-message-name').textContent=name;
    row.querySelector('.support-bubble').innerHTML=html;
    log.appendChild(row);
    scrollBottom();
    return row;
  }

  function addSystemLine(title,text){
    const line=document.createElement('div');
    line.className='support-system-line';
    line.innerHTML='<span class="support-system-line-title"></span><span class="support-system-line-text"></span>';
    line.querySelector('.support-system-line-title').textContent=title;
    line.querySelector('.support-system-line-text').textContent=text;
    log.appendChild(line);
    scrollBottom();
    return line;
  }

  function expandLegacyTranscript(){
    const legacy=document.querySelector('.support-legacy-transcript');
    if(!legacy) return;
    const bubble=legacy.querySelector('.support-bubble');
    const text=bubble?.textContent||'';
    if(!text.includes(':')) return;
    const wrap=legacy.parentElement;
    if(!wrap) return;
    const parts=text.split(/\n\s*\n/).map(x=>x.trim()).filter(Boolean);
    if(parts.length<2) return;
    const container=document.createElement('div');
    container.className='support-thread-messages support-expanded-transcript';
    parts.forEach(part=>{
      const m=part.match(/^([^:]+):\s*([\s\S]*)$/);
      const name=m?m[1].trim():'H3D Pomocník';
      const body=m?m[2].trim():part;
      const isUser=/^(Já|Ty|Uživatel)$/i.test(name);
      const row=document.createElement('div');
      row.className='support-message '+(isUser?'support-message-user':'support-message-helper');
      row.innerHTML='<div class="support-avatar support-avatar-small"><span class="support-avatar-glyph"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.2"/><path d="M5.5 20c.7-4.1 2.9-6.2 6.5-6.2s5.8 2.1 6.5 6.2"/></svg></span></div><div class="support-message-content"><div class="support-message-name"></div><div class="support-bubble"></div></div>';
      row.querySelector('.support-message-name').textContent=name;
      row.querySelector('.support-bubble').textContent=body;
      container.appendChild(row);
    });
    legacy.replaceWith(container);
  }

  function addTyping(){
    const row=addMessage('helper','H3D Pomocník','<span class="support-dots" aria-label="Pomocník odpovídá"><span></span></span>');
    row.classList.add('support-typing');
    return row;
  }

  function setWaiting(on,reveal=true){
    waitingForAdmin=on;
    if(on) consecutiveUserMessages=MAX_USER_MESSAGES;
    else consecutiveUserMessages=0;
    if(reveal && liveMode) updateComposerState();
    if(form) form.classList.toggle('is-waiting',on);
    if(quick) quick.hidden=true;
    if(on){
      startPolling();
    }else{
      if(liveMode) startPolling(); else stopPolling();
      updateComposerState();
    }
  }

  const SUPPORT_RESPONSE_DELAY_MS=3000;

  function waitMs(ms){ return new Promise(resolve=>setTimeout(resolve,ms)); }

  async function fetchSupportWithDelay(url, options, dramatic=false){
    if(!dramatic) return fetch(url,options);
    const started=Date.now();
    const controller=new AbortController();
    // 3 s is the minimum dramatic delay, not a request timeout.
    // A slow but valid server response must still be allowed to finish.
    const timeout=setTimeout(()=>controller.abort(),15000);
    try{
      const response=await fetch(url,{...options,signal:controller.signal});
      const elapsed=Date.now()-started;
      if(elapsed<SUPPORT_RESPONSE_DELAY_MS) await waitMs(SUPPORT_RESPONSE_DELAY_MS-elapsed);
      return response;
    }catch(err){
      const elapsed=Date.now()-started;
      if(elapsed<SUPPORT_RESPONSE_DELAY_MS) await waitMs(SUPPORT_RESPONSE_DELAY_MS-elapsed);
      throw err;
    }finally{
      clearTimeout(timeout);
    }
  }

  async function ask(text,dramatic=false){
    const clean=text.trim();
    if(!clean || waitingForAdmin || liveMode) return;

    addMessage('user','Já',escapeHtml(clean));
    input.value='';
    const typing=addTyping();

    try{
      const fd=new FormData();
      fd.append('action','assistant');
      fd.append('csrf',csrf);
      fd.append('body',clean);

      const r=await fetchSupportWithDelay('api/user-message.php',{method:'POST',body:fd,credentials:'same-origin'},dramatic);
      const data=await r.json();
      typing.remove();

      if(!r.ok||!data.ok) throw new Error(data.error||'Pomocník není dostupný.');

      addMessage('helper','H3D Pomocník',data.reply);
      quickProcessing=false;
      renderQuick(Array.isArray(data.quick) ? data.quick : []);
    }catch(err){
      typing.remove();
      addMessage('helper','H3D Pomocník','Nepodařilo se mi najít relevantní odpověď. omlouvám se.');
      quickProcessing=false;
      renderQuick(['Předat živému kolegovi']);
    }
  }

  function clearQuickActions(){
    availableQuickActions.clear();
    if(!quick) return;
    quick.innerHTML='';
    quick.hidden=true;
  }

  function quickKey(label){
    const raw=String(label||'').trim().toLocaleLowerCase('cs-CZ')
      .normalize('NFD').replace(/[\u0300-\u036f]/g,'');
    // Several server responses intentionally use a more natural variant of
    // the same action (for example "Moje objednavka" after a credit reply).
    // Treat these variants as one action so an already answered topic can
    // never come back as a new quick-action chip.
    if(/^moje\s+objednavk(y|a)$/.test(raw)) return 'moje objednavky';
    if(/^kolik\s+mam\s+kreditu\??$/.test(raw) || raw==='muj zustatek kreditu') return 'moje kredity';
    if(/^jake\s+nabizite\s+balicky\??$/.test(raw)) return 'nabidka kreditu / casoveho planu';
    if(/^nabidka\s+kreditu\s*\/\s*casoveho\s*planu$/.test(raw)) return 'nabidka kreditu / casoveho planu';
    if(/^moje\s+balicky$/.test(raw)) return 'moje balicky';
    return raw;
  }

  function isAdminQuick(label){
    return /předat.*(admin|živému kolegovi|kolegu)|admin.*(kontakt|kolega)/i.test(String(label||''));
  }

  function rememberAnsweredQuickActions(){
    if(!log) return;

    // Anything the user has already clicked in this conversation is considered
    // answered and must never come back as a quick-action suggestion. This also
    // covers quick actions returned again by the API after a reply.
    log.querySelectorAll('.support-message-user .support-bubble').forEach(node=>{
      const text=(node.textContent||'').trim();
      if(text) usedQuickActions.add(quickKey(text));
    });
  }

  function renderQuick(items, replace=false){
    if(!quick) return;

    rememberAnsweredQuickActions();
    if(replace) availableQuickActions.clear();

    // Remove previously suggested actions that have since been answered.
    for(const key of [...availableQuickActions.keys()]){
      if(usedQuickActions.has(key)) availableQuickActions.delete(key);
    }

    const seen=new Set(availableQuickActions.keys());
    (Array.isArray(items)?items:[]).forEach(label=>{
      const text=String(label||'').trim();
      const key=quickKey(text);
      if(text && !usedQuickActions.has(key) && !seen.has(key)){
        availableQuickActions.set(key,text);
        seen.add(key);
      }
    });

    quick.innerHTML='';
    for(const [key,text] of availableQuickActions){
      if(usedQuickActions.has(key)) continue;
      const b=document.createElement('button');
      b.type='button';
      b.className='support-chip';
      if(isAdminQuick(text)) b.classList.add('support-chip-admin');
      b.textContent=text;
      b.dataset.supportText=text;
      b.disabled=quickProcessing;
      b.setAttribute('aria-disabled',quickProcessing?'true':'false');
      quick.appendChild(b);
    }
    quick.hidden=(availableQuickActions.size===0) || waitingForAdmin || liveMode;
  }

  function removeQuickAction(label){
    const key=quickKey(label);
    usedQuickActions.add(key);
    availableQuickActions.delete(key);
    renderQuick([]);
  }

  function syncInitialQuickActions(){
    if(!quick) return;
    const labels=[...quick.querySelectorAll('[data-support-text]')].map(b=>b.dataset.supportText||b.textContent);
    availableQuickActions.clear();
    labels.forEach(label=>{
      const text=String(label||'').trim();
      const key=quickKey(text);
      if(text && !usedQuickActions.has(key)) availableQuickActions.set(key,text);
    });
    renderQuick([]);
  }

  async function showPurchasedPackages(){
    if(waitingForAdmin || liveMode) return;
    // A quick action is a real chat turn visually: show the user's selection
    // before starting the assistant request, exactly like a typed message.
    const userTurn=addMessage('user','Já','Moje balíčky');
    userTurn.classList.add('support-message-quick');
    const typing=addTyping();
    try{
      const fd=new FormData();
      fd.append('action','assistant_packages');
      fd.append('csrf',csrf);
      const r=await fetchSupportWithDelay('api/user-message.php',{method:'POST',body:fd,credentials:'same-origin'},true);
      const data=await r.json();
      typing.remove();
      if(!r.ok || !data.ok) throw new Error(data.error||'Zakoupené balíčky se nepodařilo načíst.');

      if(!Array.isArray(data.packages) || !data.packages.length){
        addMessage('helper','H3D Pomocník',data.reply || 'Na účtu nemáš žádný zakoupený balíček k aktivaci.');
      }else{
        const wrap=document.createElement('div');
        wrap.className='support-purchased-packages';
        const title=document.createElement('div');
        title.className='support-orders-title';
        title.textContent='Zakoupené balíčky k aktivaci';
        wrap.appendChild(title);
        data.packages.forEach(pkg=>{
          const a=document.createElement('a');
          a.className='support-package-purchased-row';
          a.href=pkg.href;
          a.target='_blank';
          a.rel='noopener';
          const strong=document.createElement('strong');
          strong.textContent=pkg.reference || '';
          const span=document.createElement('span');
          span.textContent=pkg.duration || '';
          const em=document.createElement('em');
          em.textContent='neaktivovaný';
          a.append(strong,span,em);
          wrap.appendChild(a);
        });
        log.appendChild(wrap);
        scrollBottom();
      }
      quickProcessing=false;
      renderQuick(Array.isArray(data.quick) ? data.quick : []);
    }catch(err){
      typing.remove();
      addMessage('helper','H3D Pomocník','Zakoupené balíčky se nepodařilo načíst. Zkus to prosím ještě jednou.');
      quickProcessing=false;
      renderQuick(['Předat živému kolegovi']);
    }
  }

  async function showCatalog(){
    if(waitingForAdmin || liveMode) return;
    addMessage('user','Já','Nabídka kreditů / časového plánu');
    const typing=addTyping();
    try{
      const fd=new FormData();
      fd.append('action','assistant_catalog');
      fd.append('csrf',csrf);
      const r=await fetchSupportWithDelay('api/user-message.php',{method:'POST',body:fd,credentials:'same-origin'},true);
      const data=await r.json();
      typing.remove();
      if(!r.ok||!data.ok) throw new Error(data.error||'Nabídku se nepodařilo načíst.');

      const wrap=document.createElement('div');
      wrap.className='support-catalog';

      if(data.promo?.active){
        const promo=document.createElement('div');
        promo.className='support-promo-card';
        promo.innerHTML='<strong></strong><span></span>';
        promo.querySelector('strong').textContent=data.promo.label || 'Promo akce';
        promo.querySelector('span').textContent=`Sleva ${data.promo.percent}%${data.promo.until ? ' · do '+data.promo.until : ''}`;
        wrap.appendChild(promo);
      }

      const groups=[
        {key:'promo',title:'Promo akce',items:(data.packages||[]).filter(p=>p.free)},
        {key:'credits',title:'Kreditové balíčky',items:(data.packages||[]).filter(p=>!p.free && p.sub_days===0)},
        {key:'time',title:'Časové plány',items:(data.packages||[]).filter(p=>!p.free && p.sub_days>0)}
      ];
      let renderedAny=false;

      groups.forEach(group=>{
        if(!group.items.length) return;
        renderedAny=true;
        const section=document.createElement('section');
        section.className='support-catalog-group';
        const h=document.createElement('div');
        h.className='support-catalog-group-title';
        h.textContent=group.title;
        section.appendChild(h);

        const cards=document.createElement('div');
        cards.className='support-package-list';
        group.items.forEach(p=>{
          const card=document.createElement('div');
          card.className='support-package-card';
          const duration=p.sub_days>0
            ? (p.sub_days===1?'1 den':p.sub_days===7?'1 týden':p.sub_days===30?'1 měsíc':p.sub_days===365?'1 rok':p.sub_days+' dní')
            : (p.credits+' kreditů');
          const sale=p.discount?.on && p.discount.final_cents<p.price_cents;
          const basePrice=p.free?'ZDARMA':((p.price_cents/100).toFixed(2).replace('.',',')+' '+(p.currency||'EUR'));
          const finalPrice=p.free?'ZDARMA':((p.discount?.final_cents/100).toFixed(2).replace('.',',')+' '+(p.currency||'EUR'));
          card.innerHTML='<strong></strong><span class="support-package-value"></span><span class="support-package-price"></span>'+
            (sale?'<span class="support-package-old-price"></span><span class="support-package-sale"></span>':'')+
            '<a href="account.php#packages" class="support-package-link">Zobrazit balíček</a>';
          card.querySelector('strong').textContent=p.name;
          card.querySelector('.support-package-value').textContent=duration;
          card.querySelector('.support-package-price').textContent=sale?finalPrice:basePrice;
          if(sale){
            card.querySelector('.support-package-old-price').textContent=basePrice;
            card.querySelector('.support-package-sale').textContent=`Promo ${p.discount.percent}%`;
          }
          cards.appendChild(card);
        });
        section.appendChild(cards);
        wrap.appendChild(section);
      });

      if(!renderedAny){
        const empty=document.createElement('div');
        empty.className='support-catalog-empty';
        empty.textContent='Momentálně nemáme aktivní žádné balíčky.';
        wrap.appendChild(empty);
      }

      log.appendChild(wrap);
      scrollBottom();
      quickProcessing=false;
      renderQuick(['Moje kredity','Moje objednávky','Nabídka kreditů / časového plánu','Předat živému kolegovi']);
    }catch(err){
      typing.remove();
      addMessage('helper','H3D Pomocník','Nepodařilo se mi najít relevantní odpověď. omlouvám se.');
      quickProcessing=false;
      renderQuick(['Předat živému kolegovi']);
    }
  }

  async function handoff(){
    if(liveMode || waitingForAdmin) return;

    try{
      const fd=new FormData();
      fd.append('action','assistant_handoff');
      fd.append('csrf',csrf);
      fd.append('subject','Chat s živým kolegou');
      fd.append('transcript','');

      const r=await fetch('api/user-message.php',{method:'POST',body:fd,credentials:'same-origin'});
      const data=await r.json();
      if(!r.ok||!data.ok) throw new Error(data.error||'Předání se nepodařilo.');

      adminMessageId=Number(data.id)||null;
      liveMode=true;
      consecutiveUserMessages=0;
      clearQuickActions();
      document.getElementById('supportInfoPanel')?.setAttribute('hidden','');
      addSystemLine('PŘEDÁNO ŽIVÉMU KOLEGOVI','Teď můžeš poslat až 5 zpráv po sobě. Potom bude potřeba počkat na odpověď živého kolegy.');
      addMessage('helper','H3D Pomocník','Živý kolega je připraven. Napiš mu, co potřebuješ vyřešit.');
      enableLiveComposer('Napiš zprávu živému kolegovi…');
      startPolling();
    }catch(err){
      addMessage('helper','H3D Pomocník','Předání živému kolegovi se nepodařilo dokončit. Zkus to prosím ještě jednou.');
    }
  }

  async function checkAdmin(){
    if(!liveMode || !adminMessageId) return;
    try{
      const fd=new FormData();
      fd.append('action','assistant_status');
      fd.append('csrf',csrf);
      const r=await fetch('api/user-message.php',{method:'POST',body:fd,credentials:'same-origin'});
      const data=await r.json();
      if(!r.ok||!data.ok||!data.active) return;

      if(Number(data.message_id)!==Number(adminMessageId)) return;

      const ts=Number(data.last_created||0);
      if(data.last_author==='staff' && ts>lastAdminPostAt){
        lastAdminPostAt=ts;
        addSystemLine('ODPOVĚĎ ŽIVÉHO KOLEGY','Živý kolega odpověděl. Můžeš znovu poslat až 5 zpráv.');
        addMessage('helper','Živý kolega',escapeHtml(data.last_body||''));
        setWaiting(false);
      }
    }catch(e){}
  }

  function startPolling(){
    stopPolling();
    pollTimer=setInterval(checkAdmin,5000);
    checkAdmin();
  }
  function stopPolling(){
    if(pollTimer){clearInterval(pollTimer);pollTimer=null;}
  }

  form?.addEventListener('submit',async e=>{
    e.preventDefault();
    const clean=(input?.value||'').trim();
    if(!clean || waitingForAdmin || !liveMode || !adminMessageId || consecutiveUserMessages >= MAX_USER_MESSAGES) return;

    if(liveMode && adminMessageId){
      const optimistic=addMessage('user','Já',escapeHtml(clean));
      input.value='';
      const fd=new FormData();
      fd.append('action','reply_chat');
      fd.append('csrf',csrf);
      fd.append('message_id',String(adminMessageId));
      fd.append('body',clean);
      try{
        const r=await fetch('api/user-message.php',{method:'POST',body:fd,credentials:'same-origin'});
        const data=await r.json().catch(()=>({}));
        if(!r.ok||!data.ok) throw new Error(data.error||'Zprávu se nepodařilo odeslat.');
        if(data.message_id) adminMessageId=Number(data.message_id)||adminMessageId;
        consecutiveUserMessages++;
        if(consecutiveUserMessages >= MAX_USER_MESSAGES){
          addSystemLine('LIMIT 5 ZPRÁV','Teď je potřeba počkat na odpověď živého kolegy.');
          setWaiting(true);
        }else{
          updateComposerState();
        }
      }catch(err){
        optimistic?.remove();
        addMessage('helper','H3D Pomocník',escapeHtml(err.message||'Zprávu se nepodařilo odeslat.'));
      }
      return;
    }
    ask(clean);
  });

  quick?.addEventListener('click',e=>{
    const b=e.target.closest('button[data-support-text]');
    if(!b || !quick.contains(b)) return;

    // Do not let the generic flyout/navigation click handlers see this click.
    // Otherwise a quick answer can be interpreted as an outside click and the
    // whole H3D Pomocník flyout closes before the request is rendered.
    e.preventDefault();
    e.stopPropagation();

    if(waitingForAdmin || liveMode) return;

    const t=(b.dataset.supportText||'').trim();
    if(!t) return;

    // Permanently hide this option for the rest of the current conversation.
    usedQuickActions.add(quickKey(t));

    if(isAdminQuick(t)){
      clearQuickActions();
      void handoff();
      return;
    }

    // Lock every remaining choice while H3D Pomocník is processing the selected one.
    quickProcessing=true;
    removeQuickAction(t);

    if(/^moje\s+bal[ií]čky$/i.test(t) || /^moje\s+balicky$/i.test(t)){
      void showPurchasedPackages();
      return;
    }

    if(/jak[eé] nabíz[ií]te\s+bal[ií]čky/i.test(t)
      || /^nab[ií]dka\s+bal[ií]čků$/i.test(t)
      || /^nab[ií]dka\s+kreditů?\s*\/\s*[čc]asov[eé]ho\s+pl[aá]nu$/i.test(t)
      || /^promo\s+akce$/i.test(t)){
      void showCatalog();
      return;
    }

    void ask(t,true);
  });

  function ratingLabel(v){ return ({good:'Pomohl',partial:'Částečně',bad:'Nepomohl'})[v]||'—'; }
  async function loadArchive(){
    if(!archiveList) return;
    archiveList.innerHTML='<div class="support-archive-empty">Načítám archiv…</div>';
    try{
      const fd=new FormData(); fd.append('action','assistant_archive_list'); fd.append('csrf',csrf);
      const r=await fetch('api/user-message.php',{method:'POST',body:fd,credentials:'same-origin'});
      const data=await r.json();
      if(!r.ok||!data.ok) throw new Error(data.error||'Archiv se nepodařilo načíst.');
      archiveList.innerHTML='';
      if(!data.items?.length){ archiveList.innerHTML='<div class="support-archive-empty">Zatím nemáš žádnou ukončenou konverzaci.</div>'; return; }
      data.items.forEach(item=>{
        const b=document.createElement('button'); b.type='button'; b.className='support-archive-row';
        b.innerHTML='<span class="support-archive-subject"></span><span class="support-archive-rating"></span>';
        b.querySelector('.support-archive-subject').textContent=item.subject;
        b.querySelector('.support-archive-rating').textContent=ratingLabel(item.rating);
        b.addEventListener('click',()=>showArchiveDetail(item));
        archiveList.appendChild(b);
      });
    }catch(e){ archiveList.innerHTML='<div class="support-archive-empty">Archiv se nepodařilo načíst.</div>'; }
  }
  function showArchiveDetail(item){
    if(!archiveDetail) return;
    archiveDetail.hidden=false;
    const msgs=(item.transcript||'').split(/\n\n+/).filter(Boolean);
    let html='<div class="support-archive-detail-head"><div class="support-archive-detail-actions"><button type="button" id="supportArchiveBack">← Archiv</button><button type="button" id="supportArchiveChat">Zpět do chatu</button></div><strong></strong><span></span></div><div class="support-archive-transcript"></div>';
    archiveDetail.innerHTML=html;
    archiveDetail.querySelector('strong').textContent=item.subject;
    archiveDetail.querySelector('span').textContent='Hodnocení: '+ratingLabel(item.rating);
    const wrap=archiveDetail.querySelector('.support-archive-transcript');
    msgs.forEach(block=>{
      const m=block.match(/^([^:]+):\s*([\s\S]*)$/);
      const name=m?m[1]:'H3D Pomocník'; const text=m?m[2]:block;
      const row=document.createElement('div'); row.className='support-archive-msg '+(/^(Já|Ty|Uživatel)$/i.test(name)?'is-user':'is-helper');
      row.innerHTML='<div class="support-avatar support-avatar-small"><span class="support-avatar-glyph"><svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="8" r="3.2"/><path d="M5.5 20c.7-4.1 2.9-6.2 6.5-6.2s5.8 2.1 6.5 6.2"/></svg></span></div><div><small></small><div class="support-bubble"></div></div>';
      row.querySelector('small').textContent=name; row.querySelector('.support-bubble').textContent=text; wrap.appendChild(row);
    });
    archiveDetail.querySelector('#supportArchiveBack')?.addEventListener('click',()=>{
      archiveDetail.hidden=true;
    });
    archiveDetail.querySelector('#supportArchiveChat')?.addEventListener('click',()=>{
      archiveDetail.hidden=true;
      if(archivePanel) archivePanel.hidden=true;
      if(typeof window.scrollTo==='function') window.scrollTo({top:0,behavior:'smooth'});
    });
  }
  document.getElementById('supportArchiveBtn')?.addEventListener('click',()=>{
    if(archivePanel){ archivePanel.hidden=false; archiveDetail && (archiveDetail.hidden=true); loadArchive(); }
  });
  document.getElementById('supportArchiveClose')?.addEventListener('click',()=>{if(archivePanel) archivePanel.hidden=true;});
  document.getElementById('supportChatBtn')?.addEventListener('click',(ev)=>{
    ev.preventDefault();
    ev.stopPropagation();

    if(archiveDetail) archiveDetail.hidden=true;
    if(archivePanel) archivePanel.hidden=true;

    // This button is a tab inside the popup, not the popup toggle.
    // If the flyout is closed, open it. If it is already open, keep it open.
    const supportFlyout=panel.closest('.flyout');
    const isOpen=!!(supportFlyout && !supportFlyout.hidden);
    const navChat=document.querySelector('.nav-item[data-flyout="messageAdmin"]');
    if(!isOpen && typeof window.openFlyout==='function'){
      window.openFlyout('messageAdmin',navChat||null);
    }else if(isOpen){
      panel.hidden=false;
      panel.removeAttribute('aria-hidden');
    }

    if(!liveMode && !adminMessageId){
      resetToNewChat();
      syncInitialQuickActions();
    }
    if(typeof window.scrollTo==='function') window.scrollTo({top:0,behavior:'smooth'});
  });

  document.getElementById('supportInfoBtn')?.addEventListener('click',()=>{
    const p=document.getElementById('supportInfoPanel');
    if(p) p.hidden=!p.hidden;
  });
  document.getElementById('supportInfoClose')?.addEventListener('click',()=>{
    const p=document.getElementById('supportInfoPanel');
    if(p) p.hidden=true;
  });

  function closeFinishedChat(){
    // Once the live chat has been ended, the top-right X has the exact same
    // finalizing/reset behavior as the "Zavřít chat" button below feedback.
    window.location.reload();
  }

  document.querySelector('.support-chat-close')?.addEventListener('click',(ev)=>{
    ev.preventDefault();
    ev.stopPropagation();
    document.getElementById('supportInfoPanel')?.setAttribute('hidden','');

    if(window.__h3dArchivedMessageId && feedback && !feedback.hidden){
      closeFinishedChat();
      return;
    }

    // With H3D Pomocník, closing the window is enough to finish that temporary
    // helper session. A live administrator conversation must survive closing
    // and reopen exactly where it was left.
    if(!liveMode){
      resetToNewChat();
    }

    if(typeof window.closeFlyout==='function'){
      window.closeFlyout();
    }
    panel.hidden=true;
    panel.setAttribute('aria-hidden','true');
  });

  endBtn?.addEventListener('click',async()=>{
    if(endBtn.disabled) return;
    stopPolling();
    waitingForAdmin=false;
    if(form) form.classList.remove('is-waiting');

    const msgs=[...log.querySelectorAll('.support-message:not(.support-typing)')];
    const transcript=msgs.map(r=>{
      const name=r.querySelector('.support-message-name')?.textContent||'';
      const text=r.querySelector('.support-bubble')?.textContent||'';
      return name+': '+text;
    }).join('\n\n');

    const status=document.getElementById('supportFeedbackStatus');
    try{
      // Close the live conversation immediately. The conversation belongs in
      // the archive regardless of whether the user decides to leave feedback.
      const afd=new FormData();
      afd.append('action','assistant_archive');
      afd.append('csrf',csrf);
      afd.append('subject','Chat s živým kolegou');
      afd.append('transcript',transcript||'Konverzace s živým kolegou byla ukončena.');
      const ar=await fetch('api/user-message.php',{method:'POST',body:afd,credentials:'same-origin'});
      const ad=await ar.json().catch(()=>({}));
      if(!ar.ok||!ad.ok) throw new Error(ad.error||'Archivaci se nepodařilo dokončit.');
      window.__h3dArchivedMessageId=Number(ad.id||0);
      window.__h3dPendingArchiveTranscript=transcript;
    }catch(err){
      if(status){ status.textContent=err.message||'Chat se nepodařilo uzavřít.'; status.hidden=false; }
      return;
    }

    if(feedback){
      feedback.hidden=false;
      feedback.classList.remove('is-rated');
    }
    if(form){
      form.classList.add('is-ended');
      input.disabled=true;
      endBtn.disabled=true;
      const send=form.querySelector('.support-send');
      if(send) send.hidden=true;
    }
    if(quick) quick.hidden=true;
    const info=document.getElementById('supportInfoPanel');
    if(info) info.hidden=true;

    addSystemLine('CHAT UKONČEN','Konverzace byla přesunuta do archivu. Můžeš ji ohodnotit, nebo pokračovat bez hodnocení.');
    feedback?.scrollIntoView({behavior:'smooth',block:'nearest'});
  });

  document.querySelectorAll('.support-rating button').forEach(b=>{
    b.addEventListener('click',()=>{
      document.querySelectorAll('.support-rating button').forEach(x=>x.classList.remove('is-selected'));
      b.classList.add('is-selected');
    });
  });

  document.getElementById('supportFeedbackSend')?.addEventListener('click',async()=>{
    const selected=document.querySelector('.support-rating button.is-selected');
    const status=document.getElementById('supportFeedbackStatus');
    if(!selected){status.textContent='Vyber prosím hodnocení.';status.hidden=false;return;}
    const messageId=Number(window.__h3dArchivedMessageId||0);
    if(!messageId){status.textContent='Konverzace už není dostupná pro hodnocení.';status.hidden=false;return;}
    try{
      const fd=new FormData();
      fd.append('action','assistant_feedback');
      fd.append('csrf',csrf);
      fd.append('message_id',String(messageId));
      fd.append('rating',selected.dataset.rating||'partial');
      fd.append('comment',document.getElementById('supportFeedbackComment')?.value||'');
      const r=await fetch('api/user-message.php',{method:'POST',body:fd,credentials:'same-origin'});
      const data=await r.json().catch(()=>({}));
      if(!r.ok||!data.ok) throw new Error(data.error||'Hodnocení se nepodařilo uložit.');

      document.getElementById('supportFeedbackSend').disabled=true;
      document.getElementById('supportFeedbackSkip')?.setAttribute('hidden','');
      document.getElementById('supportFeedbackClose')?.removeAttribute('hidden');
      status.textContent='Děkujeme za hodnocení.';
      status.hidden=false;
    }catch(err){
      status.textContent=err.message||'Hodnocení se nepodařilo uložit.';
      status.hidden=false;
    }
  });

  document.getElementById('supportFeedbackSkip')?.addEventListener('click',()=>{
    // The chat has already been archived when the user clicked "Ukončit chat".
    // No feedback row is created; simply reload into a clean helper session.
    closeFinishedChat();
  });

  document.getElementById('supportFeedbackClose')?.addEventListener('click',()=>{
    closeFinishedChat();
  });

  document.getElementById('supportOpenHuman')?.addEventListener('click',()=>{
    adminMessageId=<?= $activeMessage ? (int)$activeMessage['id'] : 0 ?>;
    liveMode=true;
    if(quick) quick.hidden=true;
    document.getElementById('supportInfoPanel')?.setAttribute('hidden','');
    enableLiveComposer('Předám živému kolegovi, popište prosím svůj problém…');
  });

  // If an active live conversation already exists, restore its message limit.
  <?php if ($activeMessage): ?>
    adminMessageId=<?= (int)$activeMessage['id'] ?>;
    liveMode=true;
    if(quick) quick.hidden=true;
    document.getElementById('supportInfoPanel')?.setAttribute('hidden','');
    <?php
      $lastActivePost = $activePosts ? $activePosts[count($activePosts)-1] : null;
      $lastActiveKind = $lastActivePost ? (string)$lastActivePost['author_kind'] : null;
      $lastActiveAt = 0;
      for ($j = count($activePosts)-1; $j >= 0; $j--) {
        if ((string)$activePosts[$j]['author_kind'] === 'staff') { $lastActiveAt = (int)$activePosts[$j]['created_at']; break; }
      }
      $activeUserStreak = 0;
      for ($i = count($activePosts)-1; $i >= 0; $i--) {
        if ((string)$activePosts[$i]['author_kind'] !== 'user') break;
        $activeUserStreak++;
      }
    ?>
    lastAdminPostAt=<?= $lastActiveAt ?>;
    consecutiveUserMessages=<?= $activeUserStreak ?>;
    enableLiveComposer('Napiš zprávu živému kolegovi…');
    if(<?= json_encode($lastActiveKind === 'user' || $lastActiveKind === null) ?> && consecutiveUserMessages>=MAX_USER_MESSAGES){
      setWaiting(true);
    }else{
      setWaiting(false);
    }
    startPolling();
  <?php endif; ?>

  // An active conversation is state, not a command to open the popup.
  // Visibility is controlled exclusively by the flyout click lifecycle.
  const initialSupportFlyout=panel.closest('.flyout');
  if(initialSupportFlyout){
    initialSupportFlyout.hidden=true;
    panel.hidden=true;
    panel.setAttribute('aria-hidden','true');
  }

  syncInitialQuickActions();

  window.addEventListener('h3dSupportOpened',()=>{
    if(!liveMode && !adminMessageId){
      resetToNewChat();
      syncInitialQuickActions();
    }
  });

  scrollBottom();
})();
</script>


<?php if (is_array($verificationNotice) && ($verificationNotice['type'] ?? '') === 'created'): ?>
<div class="verification-modal-back" id="registrationVerificationModal" aria-hidden="false">
  <section class="verification-modal registration-verification-modal" role="dialog" aria-modal="true" aria-labelledby="registrationVerificationTitle">
    <button type="button" class="verification-modal-close" id="registrationVerificationClose"
            aria-label="<?= e($lang === 'cs' ? 'Zavřít' : 'Close') ?>">×</button>
    <div class="verification-mail-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="5" width="18" height="14" rx="2"/>
        <path d="m4 7 8 6 8-6"/>
      </svg>
    </div>
    <h2 id="registrationVerificationTitle"><?= e($lang === 'cs' ? 'Účet byl vytvořen' : 'Account created') ?></h2>
    <p class="verification-lede">
      <?= e($lang === 'cs'
        ? 'Účet byl úspěšně vytvořen. Před přihlášením si prosím ověř svůj e-mail pomocí odkazu, který jsme ti poslali.'
        : 'Your account was created successfully. Before signing in, please verify your email using the link we sent you.') ?>
    </p>
    <div class="verification-checklist">
      <div><span class="verification-check-dot"></span><span><?= e($lang === 'cs' ? 'Zkontroluj doručenou poštu.' : 'Check your inbox.') ?></span></div>
      <div><span class="verification-check-dot"></span><span><?= e($lang === 'cs' ? 'Pokud e-mail nevidíš, zkontroluj také spam nebo hromadnou poštu.' : 'If you do not see it, check your spam or junk folder.') ?></span></div>
      <div><span class="verification-check-dot"></span><span><?= e($lang === 'cs' ? 'Pokud jsi při registraci zadal špatný e-mail, zaregistruj se znovu se správnou adresou.' : 'If you entered the wrong email during registration, register again with the correct address.') ?></span></div>
    </div>
    <button type="button" class="btn primary registration-verification-resend" id="registrationVerificationResend">
      <?= e($lang === 'cs' ? 'Nepřišel mi e-mail' : 'I did not receive the email') ?>
    </button>
    <p class="verification-account-email">
      <?= e($lang === 'cs' ? 'Registrovaný e-mail: ' : 'Registered email: ') ?><strong><?= e((string)($verificationNotice['email'] ?? '')) ?></strong>
    </p>
  </section>
</div>
<?php endif; ?>

<?php if (!$me): ?>
<div class="verification-modal-back" id="verificationMailModal" hidden aria-hidden="true">
  <section class="verification-modal" role="dialog" aria-modal="true" aria-labelledby="verificationMailTitle">
    <button type="button" class="verification-modal-close" id="verificationModalClose" aria-label="<?= e($lang === 'cs' ? 'Zavřít' : 'Close') ?>">×</button>
    <div class="verification-mail-icon" aria-hidden="true">
      <svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="5" width="18" height="14" rx="2"/>
        <path d="m4 7 8 6 8-6"/>
      </svg>
    </div>
    <h2 id="verificationMailTitle"><?= e($lang === 'cs' ? 'Ověření e-mailu' : 'Email verification') ?></h2>
    <p class="verification-lede">
      <?= e($lang === 'cs'
        ? 'Nepřišel ti ověřovací e-mail? Zadej svou e-mailovou adresu a pokud k ní existuje neaktivní účet, pošleme ti nový ověřovací odkaz.'
        : 'Didn’t receive your verification email? Enter your email address and, if an inactive account exists for it, we will send you a new verification link.') ?>
    </p>
    <form id="verificationResendForm">
      <input type="hidden" name="csrf" value="<?= e($studioCsrf) ?>">
      <label for="verificationEmail"><?= e($lang === 'cs' ? 'E-mailová adresa' : 'Email address') ?></label>
      <input id="verificationEmail" name="email" type="email" autocomplete="email" required
             placeholder="<?= e($lang === 'cs' ? 'tvuj@email.cz' : 'you@example.com') ?>">
      <?= Captcha::field() ?>
      <input type="hidden" name="language" value="<?= e($lang) ?>">
      <div class="verify-mail-actions">
        <button type="submit" class="btn primary" id="verificationResendSubmit">
          <?= e($lang === 'cs' ? 'Odeslat ověřovací e-mail' : 'Send verification email') ?>
        </button>
      </div>
      <div class="verification-resend-status" id="verificationStatus" hidden aria-live="polite"></div>
    </form>
  </section>
</div>
<?php endif; ?>

<script src="assets/captcha.js?v=<?= $assetVersion ?>"></script>
<script>
(function(){
  const registrationModal=document.getElementById('registrationVerificationModal');
  const registrationClose=document.getElementById('registrationVerificationClose');
  const registrationResend=document.getElementById('registrationVerificationResend');
  const modal=document.getElementById('verificationMailModal');
  const btn=document.getElementById('verificationMailBtn');
  const close=document.getElementById('verificationModalClose');
  const form=document.getElementById('verificationResendForm');
  const status=document.getElementById('verificationStatus');
  const email=document.getElementById('verificationEmail');
  if(!modal||!form)return;

  function closeRegistration(){
    if(registrationModal){
      registrationModal.hidden=true;
      registrationModal.setAttribute('aria-hidden','true');
    }
  }
  registrationClose?.addEventListener('click',closeRegistration);
  registrationModal?.addEventListener('click',e=>{if(e.target===registrationModal)closeRegistration();});
  registrationResend?.addEventListener('click',()=>{
    closeRegistration();
    open();
  });

  function open(){
    modal.hidden=false; modal.setAttribute('aria-hidden','false');
    setTimeout(()=>email?.focus(),30);
  }
  function hide(){
    modal.hidden=true; modal.setAttribute('aria-hidden','true');
  }
  btn?.addEventListener('click',open);
  close?.addEventListener('click',hide);
  modal.addEventListener('click',e=>{if(e.target===modal)hide();});
  document.addEventListener('keydown',e=>{if(e.key==='Escape'&&!modal.hidden)hide();});

  form.addEventListener('submit',async e=>{
    if(!form.checkValidity()){
      e.preventDefault();
      form.reportValidity();
      return;
    }
    e.preventDefault();
    status.hidden=true;
    const submit=document.getElementById('verificationResendSubmit');
    if(submit)submit.disabled=true;
    try{
      const r=await fetch('api/resend-verification.php',{
        method:'POST',
        credentials:'same-origin',
        headers:{'Content-Type':'application/x-www-form-urlencoded','X-Requested-With':'XMLHttpRequest'},
        body:new URLSearchParams(new FormData(form))
      });
      const raw=await r.text();
      let d={};
      try{ d=JSON.parse(raw); }catch(parseErr){
        d={ok:false,error:<?= json_encode($lang==='cs' ? 'Server vrátil neočekávanou odpověď. Zkus stránku obnovit.' : 'The server returned an unexpected response. Reload the page and try again.') ?>};
      }
      if(d.csrf_refresh){
        const token=form.querySelector('input[name="csrf"]');
        if(token)token.value=d.csrf_refresh;
      }
      const stateText=d.debug_state ? '\n\nPUBLIC VERIFY STATE\n'+JSON.stringify(d.debug_state,null,2) : '';
      const diagnostic=(d.error_code ? ' ['+d.error_code+']' : '') + (d.debug_id ? '\nDiagnostika ID: '+d.debug_id : '') + (d.diagnostic ? '\n\nSMTP / MAILER TRACE\n'+d.diagnostic : '') + stateText;
      status.textContent=(d.message||d.error||(
        r.ok
          ? <?= json_encode($lang==='cs' ? 'Požadavek byl zpracován.' : 'The request was processed.') ?>
          : <?= json_encode($lang==='cs' ? 'Požadavek se nepodařilo zpracovat.' : 'The request could not be processed.') ?>
      )) + diagnostic;
      status.className='verification-status '+(d.ok?'ok':'err');
      status.hidden=false;
    }catch(err){
      status.textContent=<?= json_encode($lang==='cs' ? 'Odeslání se nepodařilo. Zkus to prosím znovu.' : 'The request could not be sent. Please try again.') ?>;
      status.className='verification-status err';
      status.hidden=false;
    }finally{
      if(submit)submit.disabled=false;
    }
  });

})();
</script>

<!-- H3D_PUBLIC_VERIFY_DEBUG_BUILD: 20260816-hardcore-public-v1 -->
</body>
</html>
