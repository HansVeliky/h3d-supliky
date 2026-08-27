<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-H3D-Verify-Debug: 20260816-hardcore-public-v1');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok'=>false,'error'=>'Method not allowed.']);
    exit;
}
if (!Security::sameOriginRequest()) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Cross-origin request denied.'], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!Auth::csrfIsValid()) {
    http_response_code(419);
    echo json_encode(['ok'=>false,'csrf_refresh'=>Auth::csrfToken(),'error'=>'CSRF token refreshed.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$email = Auth::normaliseEmail((string)($_POST['email'] ?? ''));
$lang = (($_POST['language'] ?? '') === 'cs') ? 'cs' : 'en';

/**
 * Convert the mailer failure into a stable diagnostic code that can be
 * shown to the user/developer without exposing SMTP credentials or secrets.
 */
function verificationMailErrorCode(string $message): string
{
    $m = strtolower($message);
    if (str_contains($m, 'not configured')) {
        return 'SMTP_NOT_CONFIGURED';
    }
    if (str_contains($m, 'cannot connect') || str_contains($m, 'connection')) {
        return 'SMTP_CONNECTION_FAILED';
    }
    if (str_contains($m, 'starttls') || str_contains($m, 'tls')) {
        return 'SMTP_TLS_FAILED';
    }
    if (str_contains($m, 'authentication') || str_contains($m, 'auth ')) {
        return 'SMTP_AUTH_FAILED';
    }
    if (str_contains($m, 'recipient') || str_contains($m, 'rcpt')) {
        return 'SMTP_RECIPIENT_REJECTED';
    }
    if (str_contains($m, 'sender') || str_contains($m, 'mail from')) {
        return 'SMTP_SENDER_REJECTED';
    }
    if (str_contains($m, 'timeout') || str_contains($m, 'stopped responding')) {
        return 'SMTP_TIMEOUT';
    }
    if (str_contains($m, 'smtp [rcpt to]')) {
        return 'SMTP_RECIPIENT_REJECTED';
    }
    if (str_contains($m, 'smtp [mail from]')) {
        return 'SMTP_SENDER_REJECTED';
    }
    if (str_contains($m, 'smtp [data') || str_contains($m, 'smtp [message body]')) {
        return 'SMTP_MESSAGE_REJECTED';
    }
    if (str_contains($m, 'smtp [auth]')) {
        return 'SMTP_AUTH_FAILED';
    }
    if (str_contains($m, 'smtp error:')) {
        return 'SMTP_SERVER_REJECTED';
    }
    return 'SMTP_SEND_FAILED';
}


/*
 * Validate the address before CAPTCHA so the user immediately gets useful
 * feedback for an empty/invalid field. CAPTCHA is still mandatory before any
 * account lookup or mail send.
 */
if ($email === '') {
    http_response_code(422);
    echo json_encode([
        'ok'=>false,
        'field'=>'email',
        'error'=>($lang === 'cs' ? 'Zadej e-mailovou adresu.' : 'Enter your email address.')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    http_response_code(422);
    echo json_encode([
        'ok'=>false,
        'field'=>'email',
        'error'=>($lang === 'cs' ? 'Zadej platnou e-mailovou adresu.' : 'Enter a valid email address.')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

[$capOk, $capMsg] = Captcha::verify($_POST);
if (!$capOk) {
    http_response_code(400);
    echo json_encode(['ok'=>false,'error'=>$capMsg], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
$now=time();
$ipHash=Auth::ipHash();
$emailHash=hash('sha256','h3d-verify-email|'.$email);

$pdo=Db::pdo();
$st=$pdo->prepare('SELECT COUNT(*) FROM verification_requests WHERE (ip_hash = ? OR email_hash = ?) AND created_at > ?');
$st->execute([$ipHash,$emailHash,$now-300]);
if ((int)$st->fetchColumn() > 0) {
    http_response_code(429);
    echo json_encode([
        'ok'=>false,
        'cooldown'=>true,
        'message'=>($lang === 'cs' ? 'Počkej prosím 5 minut před dalším požadavkem.' : 'Please wait 5 minutes before requesting another verification email.')
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

$user=Auth::byEmail($email);
if ($user && empty($user['verified_at'])) {
    // Verify::sendLink has its own 5-minute per-account cooldown. The
    // verification_requests table above additionally protects by IP/email.
    [$sent, $sendMessage] = Verify::sendLink($user, false);

    if (!$sent) {
        // Do not expose whether the address exists. A cooldown is safe to
        // explain because it applies to this request, not account existence.
        if (stripos($sendMessage, 'wait') !== false || stripos($sendMessage, 'sek') !== false) {
            http_response_code(429);
            echo json_encode([
                'ok'=>false,
                'cooldown'=>true,
                'message'=>($lang === 'cs')
                    ? 'Ověřovací e-mail už byl nedávno odeslán. Počkej prosím 5 minut a zkus to znovu.'
                    : 'A verification email was sent recently. Please wait 5 minutes and try again.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        // HARD DEBUG: return a sanitized SMTP report for this public
        // verification request. Never include passwords, auth data or tokens.
        http_response_code(502);
        $errorCode = verificationMailErrorCode((string)$sendMessage);
        $debugId = Mailer::lastDebugId();
        error_log('[h3d] PUBLIC VERIFY DEBUG id=' . $debugId . ' code=' . $errorCode . ' user=' . (int)$user['id'] . ' msg=' . $sendMessage);
        $state = [
            'user_id' => (int)$user['id'],
            'verified' => !empty($user['verified_at']),
            'verify_sent_at' => (int)($user['verify_sent_at'] ?? 0),
            'seconds_since_last_send' => !empty($user['verify_sent_at']) ? max(0, time()-(int)$user['verify_sent_at']) : null,
            'mailer_enabled' => Mailer::enabled(),
            'mailer_debug_id' => $debugId,
            'send_error' => (string)$sendMessage,
        ];
        echo json_encode([
            'ok'=>false,
            'error_code'=>$errorCode,
            'debug_id'=>$debugId,
            'diagnostic'=>Mailer::lastDebugReport(),
            'debug_state'=>$state,
            'message'=>($lang === 'cs')
                ? 'PUBLIC VERIFY DEBUG: odeslání selhalo. Zobrazena je přesná diagnostika.'
                : 'PUBLIC VERIFY DEBUG: sending failed. Detailed diagnostics are shown.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Record the public request only after SMTP processing. A failed SMTP
// connection must not consume the user's 5-minute resend slot.
$pdo->prepare('INSERT INTO verification_requests (ip_hash,email_hash,created_at) VALUES (?,?,?)')
    ->execute([$ipHash,$emailHash,time()]);

/*
 * Deliberately return the same public response whether the account exists,
 * is already verified, or the address was mistyped. This avoids turning this
 * form into an account-enumeration oracle.
 */
echo json_encode([
    'ok'=>true,
    'message'=>($lang === 'cs')
        ? 'Pokud k této adrese existuje neaktivní účet, byl odeslán nový ověřovací e-mail. Pokud nic nepřijde, zkontroluj spam nebo se zaregistruj znovu se správnou adresou.'
        : 'If the address belongs to an inactive account, a new verification email has been sent. If nothing arrives, check spam or register again with the correct address.'
], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    $errorCode = verificationMailErrorCode($e->getMessage());
    $exceptionId = strtoupper(bin2hex(random_bytes(4)));
    $debugId = Mailer::lastDebugId();
    $diagnostic = Mailer::lastDebugReport();
    $exceptionMessage = preg_replace('/(?:password|smtp_pass|auth(?:entication)?).*?(?=\s|$)/i', '[REDACTED]', $e->getMessage()) ?? $e->getMessage();
    error_log('[h3d] resend verification failed id=' . $exceptionId . ' [' . $errorCode . '] class=' . get_class($e) . ' msg=' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok'=>false,
        'error_code'=>$errorCode,
        'debug_id'=>$debugId,
        'exception_id'=>$exceptionId,
        'exception_class'=>get_class($e),
        'exception_message'=>$exceptionMessage,
        'diagnostic'=>$diagnostic,
        'debug_state'=>[
            'request_uri'=>(string)($_SERVER['REQUEST_URI'] ?? ''),
            'method'=>(string)($_SERVER['REQUEST_METHOD'] ?? ''),
            'mailer_debug_id'=>$debugId,
            'mailer_enabled'=>Mailer::enabled(),
            'smtp_debug_started'=>($debugId !== ''),
            'db_path'=>basename(Db::path()),
        ],
        'message'=>($lang === 'cs')
            ? 'PUBLIC VERIFY DEBUG: serverová výjimka. ID výjimky: ' . $exceptionId
            : 'PUBLIC VERIFY DEBUG: server exception. Exception ID: ' . $exceptionId
    ], JSON_UNESCAPED_UNICODE);
}
