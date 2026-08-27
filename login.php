<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/lib/View.php';

// Signing in normally opens the studio. The sole exception is the protected
// admin entrance: during maintenance the studio is deliberately unavailable,
// so an operator needs this safe, fixed return address to reach the panel.
$requestedNext = (string) ($_GET['next'] ?? $_POST['redirect'] ?? '');
$next = 'index.php';
if ($requestedNext === 'admin/index.php') {
    $next = 'admin/index.php';
} elseif (preg_match('/^index\.php\?share=[A-Za-z0-9_-]{16}$/', $requestedNext)) {
    // A share key is safe to return to after login. Keep it as a query URL so
    // this works even when the hosting provider has no rewrite rule.
    $next = $requestedNext;
} elseif (preg_match('/^[A-Za-z0-9_-]{16}$/', $requestedNext)) {
    // Backward compatibility for older share links that used the rewrite form.
    $next = 'index.php?share=' . rawurlencode($requestedNext);
}
if (Auth::user()) { header('Location: ' . $next); exit; }

$firstRun = !Auth::anyAdminExists();
/*
 * Maintenance closes the door to new accounts as well. Signing in stays
 * open - the operator has to get to the panel to switch maintenance off,
 * and an existing customer should still be able to read the notice - but
 * an account created while the site is half rebuilt would land in a state
 * nobody has checked: no welcome mail, no credits granted, no support.
 */
$maintenance = Settings::bool('maintenance_mode');
$regOpen  = ($firstRun || Settings::bool('registration_open')) && !$maintenance;

$lang = $_COOKIE['h3d_lang'] ?? Settings::get('default_lang');
$lang = ($lang === 'cs') ? 'cs' : 'en';
$theme = 'light';$tab      = (($_GET['tab'] ?? '') === 'register' && $regOpen) ? 'register' : 'login';
$loginErr = '';
$regErr   = '';
$wantsJson = !empty($_POST['ajax']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // The login dialog can outlive the PHP session (idle timeout, another tab
    // logging out, or session renewal). Instead of making the user lose the
    // entered password to a stale CSRF token, refresh the token once and let
    // the same-origin client retry. A token is still mandatory; this is not a
    // CSRF bypass.
    $isAjaxLogin = (($_POST['ajax'] ?? '') === '1');
    if ($isAjaxLogin && !Security::sameOriginRequest()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'Cross-origin request denied.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($isAjaxLogin && !Auth::csrfIsValid()) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'csrf_refresh' => Auth::csrfToken(),
            'error' => 'CSRF token refreshed.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    Auth::checkCsrf();
    $form = (string) ($_POST['form'] ?? 'login');

    if ($form === 'register' && $regOpen) {
        $tab = 'register';
        [$capOk, $capMsg] = Captcha::verify($_POST);
        $email = (string) ($_POST['email'] ?? '');
        $pass  = (string) ($_POST['password'] ?? '');
        if (!$capOk) {
            $regErr = $capMsg;
        } elseif ($pass !== (string) ($_POST['password2'] ?? '')) {
            $regErr = ($lang === 'cs') ? 'Hesla se neshodují.' : 'The two passwords do not match.';
        } else {
            [$ok, $regErr] = Auth::register($email, $pass, $firstRun, '', (string) ($_POST['refcode'] ?? ''));
            if ($ok) {
                if ($firstRun) {
                    // The first operator account is created verified by policy.
                    Auth::login($email, $pass);
                    header('Location: admin/index.php');
                    exit;
                }

                // Ordinary accounts must verify before any login. Keep the
                // confirmation state in the anonymous session and show it in
                // the Studio instead of silently signing the user in.
                Auth::start();
                $_SESSION['verification_notice'] = [
                    'type' => 'created',
                    'email' => Auth::normaliseEmail($email),
                ];
                header('Location: index.php');
                exit;
            }
        }
    } else {
        $tab = 'login';
        [$ok, $loginErr] = Auth::login(
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            !empty($_POST['remember_me'])
        );
        if (!$ok && $loginErr === 'Please verify your email address before signing in.') {
            $loginErr = $lang === 'cs'
                ? 'Před přihlášením musíš nejdříve ověřit svůj e-mail.'
                : 'Please verify your email address before signing in.';
        }

        if ($wantsJson) {
            header('Content-Type: application/json');
            echo json_encode([
                'ok'    => $ok,
                'error' => $ok ? '' : $loginErr,
                'next'  => $ok
                    ? (Auth::mustChangePassword(Auth::user()) ? 'change-password.php' : $next)
                    : null,
            ]);
            exit;
        }
        if ($ok && Auth::mustChangePassword(Auth::user())) { header('Location: change-password.php'); exit; }
        if ($ok) {
            header('Location: ' . $next);
            exit;
        }
    }
}

// Registration asks for an address and a password, nothing else. The
// display name shown in the support chat is derived from the address in
// Auth::register() and can be changed later on the account page.
$registerEmail = (string) ($_POST['email'] ?? '');

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
<title><?= e(__('auth.signin')) ?> · <?= e(Settings::get('brand_label')) ?></title>
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
    <p class="auth-lede"><?= e(__('auth.signin.lede')) ?></p>

    <?php if ($regOpen): ?>
    <div class="auth-tabs" role="tablist">
      <button type="button" class="auth-tab<?= $tab === 'login' ? ' active' : '' ?>" data-tab="login"><?= e(__('auth.signin')) ?></button>
      <button type="button" class="auth-tab<?= $tab === 'register' ? ' active' : '' ?>" data-tab="register"><?= e(__('auth.create')) ?></button>
    </div>
    <?php endif; ?>

    <!-- Sign in -->
    <form method="post" class="auth-form<?= $tab === 'login' ? ' active' : '' ?>" data-form="login" data-busy="<?= e($lang === 'cs' ? 'Přihlašuji…' : 'Signing you in…') ?>">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="form" value="login">
      <input type="hidden" name="redirect" value="<?= e($next) ?>">
      <?php if ($loginErr !== ''): ?><div class="auth-err"><?= e($loginErr) ?></div><?php endif; ?>
      <label for="l-email"><?= e(__('auth.email')) ?></label>
      <input id="l-email" name="email" type="email" required autocomplete="email"
             value="<?= e($tab === 'login' ? (string) ($_POST['email'] ?? '') : '') ?>">
      <label for="l-pass"><?= e(__('auth.password')) ?></label>
      <input id="l-pass" name="password" type="password" required autocomplete="current-password">
      <label class="remember-row">
        <input type="checkbox" name="remember_me" value="1"<?= !empty($_POST['remember_me']) ? ' checked' : '' ?>>
        <span><?= e($lang === 'cs' ? 'Zůstat přihlášen 30 dní' : 'Stay signed in for 30 days') ?></span>
      </label>
      <button class="btn primary" type="submit"><?= e(__('auth.signin.btn')) ?></button>
      <div class="auth-links">
        <a href="forgot.php"><?= e(__('auth.forgot')) ?></a>
      </div>
    </form>

    <?php if ($regOpen): ?>
    <!-- Register -->
    <form method="post" class="auth-form<?= $tab === 'register' ? ' active' : '' ?>" data-form="register" data-busy="<?= e($lang === 'cs' ? 'Zakládám účet…' : 'Creating your account…') ?>">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="form" value="register">
      <?php if ($regErr !== ''): ?><div class="auth-err"><?= e($regErr) ?></div><?php endif; ?>
      <?php if ($firstRun): ?>
        <div class="hint" style="margin-bottom:6px"><?= e(__('auth.firstadmin')) ?></div>
      <?php endif; ?>
      <label for="r-email"><?= e(__('auth.email')) ?></label>
      <input id="r-email" name="email" type="email" required autocomplete="email"
             value="<?= e($tab === 'register' ? $registerEmail : '') ?>">
      <label for="r-pass"><?= e(__('auth.password')) ?></label>
      <input id="r-pass" name="password" type="password" required autocomplete="new-password" minlength="8">
      <!-- Live checklist: each rule turns green as the password meets it. -->
      <ul class="pw-rules" id="pwRules" aria-live="polite">
        <li data-rule="len"><?= e($lang === 'cs' ? 'Minimálně 8 znaků' : 'At least 8 characters') ?></li>
        <li data-rule="case"><?= e($lang === 'cs' ? 'Malé i velké písmeno' : 'Lower and upper-case letter') ?></li>
        <li data-rule="digit"><?= e($lang === 'cs' ? 'Alespoň jedna číslice' : 'At least one digit') ?></li>
      </ul>
      <label for="r-pass2"><?= e(__('auth.password2')) ?></label>
      <input id="r-pass2" name="password2" type="password" required autocomplete="new-password">
      <?php if (Referral::enabled() && !$firstRun): ?>
        <?php $refPrefill = strtoupper(trim((string) ($_POST['refcode'] ?? ($_GET['ref'] ?? '')))); ?>
        <label for="r-ref"><?= e($lang === 'cs' ? 'Kód od kamaráda (nepovinné)' : 'Friend\'s code (optional)') ?></label>
        <input id="r-ref" name="refcode" type="text" class="mono" maxlength="16" autocomplete="off"
               placeholder="<?= e($lang === 'cs' ? 'Např. AB2CD3EF' : 'e.g. AB2CD3EF') ?>"
               value="<?= e($refPrefill) ?>">
        <div class="hint"><?= e($lang === 'cs'
            ? 'Po ověření e-mailu dostanete odměnu oba.'
            : 'Once your e-mail is verified, you both get a reward.') ?></div>
      <?php endif; ?>
      <div style="margin-top:14px"><?= Captcha::field() ?></div>
      <button class="btn primary" type="submit"><?= e(__('auth.create')) ?></button>
    </form>
    <?php endif; ?>

    <a class="auth-back" href="index.php">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 6l-6 6 6 6"/></svg>
      <?= e(__('auth.back')) ?>
    </a>
  </div>
</div>

<script>
(function(){
  var tabs=document.querySelectorAll('.auth-tab');
  var forms=document.querySelectorAll('.auth-form');
  function show(name){
    tabs.forEach(function(t){ t.classList.toggle('active', t.dataset.tab===name); });
    forms.forEach(function(f){ f.classList.toggle('active', f.dataset.form===name); });
    var focusable=document.querySelector('.auth-form.active input');
    if(focusable) try{ focusable.focus(); }catch(e){}
  }
  tabs.forEach(function(t){ t.addEventListener('click',function(){ show(t.dataset.tab); }); });

  // Live password checks: the rules under the field flip green as they are
  // met, the fields themselves show green/red borders, and the repeat field
  // follows whether it matches.
  var p1=document.getElementById('r-pass'), p2=document.getElementById('r-pass2');
  var rules=document.querySelectorAll('#pwRules li');
  function checkRules(v){
    return {
      len: v.length>=8,
      'case': /[a-z]/.test(v) && /[A-Z]/.test(v),
      digit: /\d/.test(v)
    };
  }
  function paint(){
    if(!p1)return;
    var v=p1.value, st=checkRules(v), all=st.len&&st['case']&&st.digit;
    rules.forEach(function(li){
      var ok=st[li.dataset.rule];
      li.classList.toggle('ok',!!ok&&v!=='');
      li.classList.toggle('bad',!ok&&v!=='');
    });
    p1.classList.toggle('field-ok',v!==''&&all);
    p1.classList.toggle('field-bad',v!==''&&!all);
    if(p2){
      var match=p2.value!==''&&p2.value===v;
      p2.classList.toggle('field-ok',match);
      p2.classList.toggle('field-bad',p2.value!==''&&!match);
    }
  }
  if(p1){ p1.addEventListener('input',paint); }
  if(p2){ p2.addEventListener('input',paint); }

})();
</script>
<script src="assets/adspace.js?v=<?= $assetVersion ?>"></script>
<script src="assets/panel.js?v=<?= $assetVersion ?>"></script>
<?php if (Captcha::enabled()) { echo '<script src="assets/captcha.js"></script>'; } ?>
<?= Consent::render() ?>
</body>
</html>
