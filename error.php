<?php
declare(strict_types=1);

/**
 * Error page for every status the server hands off to us.
 *
 * Reached two ways: Apache's ErrorDocument for a URL that does not exist, and
 * a direct include from PHP when a page decides it cannot continue. The code
 * is taken from the query string only when Apache put it there via
 * REDIRECT_STATUS, so it cannot be spoofed into claiming an odd status.
 */
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/View.php';

$code = (int) ($_GET['code'] ?? ($_SERVER['REDIRECT_STATUS'] ?? 404));

// Protected implementation folders are never a useful destination for a
// visitor or a scanner. Send both back to the studio instead of advertising
// their presence with a dead-end error page.
$failedPath = (string) ($_SERVER['REDIRECT_URL'] ?? $_SERVER['REQUEST_URI'] ?? '');
if ($code === 403 && preg_match('#/(?:data|lib|api)(?:/|$)#i', $failedPath)) {
    header('Location: index.php', true, 302);
    exit;
}

$pages = [
    400 => [
        'title' => 'Bad request',
        'lead'  => 'Something in that request did not make sense to the server.',
        'body'  => 'This usually means a link was mangled somewhere along the way, or a
                    form was submitted twice. Going back and trying once more is
                    normally enough.',
    ],
    403 => [
        'title' => 'Not for you',
        'lead'  => 'Sorry - this page is not available to your account.',
        'body'  => 'Either it needs a sign-in, or it belongs to somebody else, or it is
                    part of the administration. If you think you should be able to see
                    it, sign in and try again.',
    ],
    404 => [
        'title' => 'Nothing here',
        'lead'  => 'Sorry - there is no page at this address.',
        'body'  => 'The link may be out of date, or a character may have gone missing
                    from it. Nothing is broken on your side.',
    ],
    405 => [
        'title' => 'Wrong method',
        'lead'  => 'That address does not accept this kind of request.',
        'body'  => 'Open it from the site rather than calling it directly.',
    ],
    413 => [
        'title' => 'Too large',
        'lead'  => 'That was more data than the server accepts in one go.',
        'body'  => 'If you were exporting a very large layout, try splitting it into
                    fewer boxes.',
    ],
    429 => [
        'title' => 'Slow down',
        'lead'  => 'That is more requests than the server wants to handle right now.',
        'body'  => 'Wait a moment and try again.',
    ],
    500 => [
        'title' => 'Something broke',
        'lead'  => 'Sorry - that went wrong on our side, not yours.',
        'body'  => 'The problem has been written to the server log. Trying again in a
                    minute is worth a shot; if it keeps happening, let me know what you
                    were doing when it did.',
    ],
    503 => [
        'title' => 'Back shortly',
        'lead'  => 'The site is temporarily unavailable.',
        'body'  => 'This is usually maintenance and does not last long.',
    ],
];

$page = $pages[$code] ?? [
    'title' => 'Something went wrong',
    'lead'  => 'Sorry - the server could not complete that request.',
    'body'  => 'No further detail is available. Trying again usually helps.',
];

// Only set the status when nothing has been sent yet; when this file is
// included by a page that already set one, leave it alone.
if (!headers_sent()) {
    http_response_code($code >= 400 && $code < 600 ? $code : 404);
}

$user = Auth::user();

page_head($page['title']);
?>
<div class="wrap narrow">
  <div class="errorbox">
    <div class="errorcode"><?= (int) $code ?></div>
    <h1><?= e($page['title']) ?></h1>
    <p class="errorlead"><?= e($page['lead']) ?></p>
    <p class="hint"><?= e(preg_replace('/\s+/', ' ', $page['body'])) ?></p>

    <div class="errorlinks">
      <a class="btn primary" href="index.php">Open the studio</a>
      <?php if ($user): ?>
        <a class="btn" href="account.php">Your account</a>
      <?php else: ?>
        <a class="btn" href="login.php">Sign in</a>
      <?php endif; ?>
      <?php $home = trim(Settings::get('brand_url')); ?>
      <?php if ($home !== ''): ?>
        <a class="btn" href="<?= e($home) ?>"><?= e(Settings::get('brand_label') ?: 'Home') ?></a>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php page_foot();
