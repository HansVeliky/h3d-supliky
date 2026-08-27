<?php
declare(strict_types=1);

/**
 * Single include for every entry point. Keeps the require lists in
 * index.php, the account pages and the admin panel from drifting apart.
 */
if (!defined('H3D_APP')) {
    define('H3D_APP', true);
}

/**
 * The version of the site, shown in both footers and in the admin panel.
 *
 * Bump it by hand with every batch that goes up. It is the answer to "is
 * what I am looking at what I uploaded?" - without it, a stale file or a
 * cached page on the host is indistinguishable from a change that did not
 * work, and both were guessed at more than once.
 */
if (!defined('H3D_VERSION')) {
    define('H3D_VERSION', '2.0.0.12beta');
}

require_once __DIR__ . '/Db.php';
require_once __DIR__ . '/Security.php';
require_once __DIR__ . '/SmtpPresets.php';
require_once __DIR__ . '/Cred.php';
require_once __DIR__ . '/Settings.php';
require_once __DIR__ . '/Money.php';
require_once __DIR__ . '/Lang.php';
require_once __DIR__ . '/Reset.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/Credits.php';
require_once __DIR__ . '/DailyCredits.php';
require_once __DIR__ . '/Codes.php';
require_once __DIR__ . '/Audit.php';
require_once __DIR__ . '/Paypal.php';
require_once __DIR__ . '/Qr.php';
require_once __DIR__ . '/Payments.php';
require_once __DIR__ . '/Orders.php';
require_once __DIR__ . '/Links.php';
require_once __DIR__ . '/Captcha.php';
require_once __DIR__ . '/Promotions.php';
require_once __DIR__ . '/Pricing.php';
require_once __DIR__ . '/Mailer.php';
require_once __DIR__ . '/Notify.php';
require_once __DIR__ . '/Verify.php';
require_once __DIR__ . '/Referral.php';
require_once __DIR__ . '/Quota.php';
require_once __DIR__ . '/SubExpiry.php';
require_once __DIR__ . '/Consent.php';
require_once __DIR__ . '/UserMessages.php';
// Layout carries the parameter ranges, which the studio page and the admin
// panel now render from - not just the export endpoint that validates
// against them. Geometry stays out: it is only ever needed by the export.
require_once __DIR__ . '/Layout.php';

Security::headers();
// Expire/delete unverified accounts before restoring any persistent login.
Auth::sweepUnverifiedAccounts();
Auth::start();

// Until the e-mail address is verified, the account page is the only
// application page available. The studio and every other customer page are
// intentionally inaccessible; API calls get JSON rather than an HTML redirect.
if (PHP_SAPI !== 'cli') {
    $pendingUser = Auth::user();
    if ($pendingUser && !Auth::canManageOrders($pendingUser) && empty($pendingUser['verified_at'])) {
        $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $path = str_replace('\\', '/', (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? ''));
        // An unverified account may use the Studio just like a visitor.
        // Feature locks and export limits are applied separately; the
        // account page remains available for verification management.
        $allowed = ['index.php', 'account.php', 'logout.php', 'verify.php', 'confirm-email.php', 'login.php', 'forgot.php', 'change-password.php', 'cookies.php'];
        // A pending account is allowed to acknowledge the current news card.
        // api/news.php only advances news_seen_version; blocking it here makes
        // the same news reappear forever for an unverified signed-in user.
        $newsApiAllowed = $script === 'news.php' && str_contains($path, '/api/news.php');
        if (!in_array($script, $allowed, true) && !$newsApiAllowed) {
            if (str_contains($path, '/api/')) {
                http_response_code(403);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'error' => 'Please verify your email address first.'], JSON_UNESCAPED_UNICODE);
            } else {
                header('Location: account.php');
            }
            exit;
        }
    }
}

/*
 * Planned work must stop the public application consistently, including
 * direct API calls. Panel users keep access so the switch can always be
 * reverted without touching files on the server.
 */
if (PHP_SAPI !== 'cli' && Settings::bool('maintenance_mode')) {
    $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    // Also accept /admin (without a trailing slash). Some hosts run the
    // directory redirect only after PHP has started; treating that URL as
    // public would lock the operator out exactly when maintenance is on.
    $isPanelPath = preg_match('#(?:^|/)admin(?:/|$)#', ltrim(str_replace('\\', '/', $path), '/')) === 1;
    $isEntryPath = in_array($script, ['login.php', 'logout.php', 'install.php', 'error.php'], true);
    $maintenanceUser = Auth::user();
    $isOperator = Auth::canManageOrders($maintenanceUser);
    // A signed-in customer reaches the studio solely to receive its permanent
    // maintenance warning. The application API remains closed, so no work
    // can be mistaken for safely saved work while maintenance is in progress.
    $isSignedStudio = $maintenanceUser !== null && $script === 'index.php';
    if (!$isPanelPath && !$isEntryPath && !$isOperator && !$isSignedStudio) {
        http_response_code(503);
        header('Retry-After: 900');
        if (str_contains(str_replace('\\', '/', $path), '/api/')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Služba je dočasně pozastavena kvůli údržbě.']);
        } else {
            header('Content-Type: text/html; charset=utf-8');
            // The page itself lives in lib/ (denied to the web server), so
            // it cannot be opened on its own and mistaken for a real outage.
            require __DIR__ . '/maintenance-page.php';
        }
        exit;
    }
}

/**
 * A portal with no administrator cannot be used or configured, so every page
 * sends you to the installer until one exists. The check is one indexed
 * count and is skipped the moment setup is done, so it costs nothing in
 * normal running.
 */
if (!defined('H3D_NO_INSTALL_REDIRECT')
    && basename($_SERVER['SCRIPT_NAME'] ?? '') !== 'install.php'
    && PHP_SAPI !== 'cli') {
    try {
        $adminCount = (int) Db::pdo()->query(
            "SELECT COUNT(*) FROM users WHERE role = 'admin' OR is_admin = 1"
        )->fetchColumn();
    } catch (Throwable $e) {
        $adminCount = 0;   // no database yet - definitely not set up
    }
    if ($adminCount === 0) {
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        if (str_ends_with($dir, '/admin') || str_ends_with($dir, '/api')) {
            $dir = substr($dir, 0, (int) strrpos($dir, '/'));
        }
        header('Location: ' . $dir . '/install.php');
        exit;
    }
}

// Notice freshly expired subscriptions and mail their owners about it.
SubExpiry::tick();

// PHP's built-in dev server (php -S) on Windows intermittently resets
// kept-alive connections mid-response - a submitted form then sits behind
// its "working…" overlay forever even though the server did the work.
// Closing the connection per request costs nothing locally and production
// (Apache/nginx SAPI) never enters this branch.
if (PHP_SAPI === 'cli-server' && !headers_sent()) {
    header('Connection: close');
}

/** Escape for HTML output. */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/** Formats an amount that is already expressed in the given currency. */
function money(int $cents, ?string $currency = null): string
{
    return Money::fmt($cents, $currency);
}

function when(?int $ts): string
{
    return $ts ? date('d.m.Y H:i', $ts) : '-';
}

/**
 * Ledger notes carry machine markers ("[g:o:REF]", "storno #12") that mean
 * nothing to a person. This strips and translates them for display; the raw
 * value stays in the database untouched.
 */
function ref_label(?string $ref): string
{
    $r = trim((string) $ref);
    if ($r === '') {
        return '-';
    }
    $r = preg_replace('/\s*\[g~?:[^\]]+\]/', '', $r);
    // The storno markers also appear mid-sentence ("-2 dní (storno kódu #1
    // Dárkový)"), so they are translated wherever they sit, not just alone.
    $r = preg_replace('/storno kódu #\d+\s*/u', 'odebrání kódu ', $r);
    $r = preg_replace('/storno #\d+/u', 'odebrání dřívějšího přídavku', $r);
    $r = preg_replace('/^obnova (H3D-\S+)$/u', 'obnova objednávky $1', $r);
    $r = trim(preg_replace('/\s{2,}/', ' ', $r) ?? $r);

    return trim($r) === '' ? '-' : trim($r);
}

/** Absolute link to an order's status page, used in emails. */
/**
 * Finishes a POST by redirecting to a plain GET of the same page.
 *
 * Without this, refreshing after submitting repeats the request: an order was
 * placed again, credits were spent again, and the browser only warned about
 * it if it felt like it. The message survives in the session so the page can
 * still say what happened.
 *
 * 303 rather than 302 because it tells every client to follow with GET,
 * which is exactly the point.
 */
function redirect_after_post(string $url, string $ok = '', string $error = ''): void
{
    Auth::start();

    if ($ok !== '' || $error !== '') {
        $_SESSION['flash'] = ['ok' => $ok, 'error' => $error];
    }

    // A test harness renders these pages in-process, where exiting would take
    // the whole run with it. The seam is deliberately narrow: production
    // never defines the constant, so the behaviour there is unchanged.
    if (defined('H3D_NO_EXIT')) {
        throw new RedirectSignal($url);
    }

    header('Location: ' . $url, true, 303);
    exit;
}

/** Stands in for the exit when a test is driving the page. */
final class RedirectSignal extends RuntimeException
{
    public function __construct(public readonly string $url)
    {
        parent::__construct('redirect: ' . $url);
    }
}

/**
 * Reads and clears the message left by the redirect.
 *
 * @return array{0:string,1:string} ok, error
 */
function take_flash(): array
{
    Auth::start();

    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return [(string) ($flash['ok'] ?? ''), (string) ($flash['error'] ?? '')];
}

function orderStatusUrl(array $order): string
{
    // The token is what makes the link work without a login. Without it the
    // page still opens, but only for someone signed in as the owner.
    return originUrl() . '/order.php?ref=' . urlencode((string) $order['reference'])
         . '&t=' . urlencode((string) ($order['access_token'] ?? ''));
}

/** Printable receipt / invoice for a paid order, same token as the status page. */
function invoiceUrl(array $order): string
{
    return originUrl() . '/invoice.php?ref=' . urlencode((string) $order['reference'])
         . '&t=' . urlencode((string) ($order['access_token'] ?? ''));
}

/** Base URL of the installation, used for absolute links. */
function originUrl(): string
{
    $scheme = Auth::isHttps() ? 'https' : 'http';
    $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');

    // Mail sent FROM the admin panel must not link into /admin/ - the
    // receipt and order pages live in the installation root. This was why
    // invoice links in admin-sent mails 404ed.
    if (str_ends_with($dir, '/admin')) {
        $dir = substr($dir, 0, -6);
    }

    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
}
