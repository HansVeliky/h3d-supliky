<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * A small SMTP client.
 *
 * Written by hand rather than pulling in a library, because the whole point
 * of this build is that it stays a folder you copy onto the NAS with no
 * Composer step. It covers what a normal mail provider needs: implicit TLS,
 * STARTTLS, AUTH LOGIN and AUTH PLAIN, and UTF-8 headers.
 *
 * Every send is recorded in mail_log with its outcome, because mail that
 * quietly fails is worse than mail that never existed — you find out weeks
 * later when a customer says they never got the link.
 */
final class Mailer
{
    /** Current SMTP command, used to make failures actionable. */
    private static string $stage = 'connect';
    private static array $trace = [];
    private static array $debugContext = [];
    private static string $debugId = '';
    public static function enabled(): bool
    {
        return Settings::bool('smtp_enabled')
            && trim(Settings::get('smtp_host')) !== ''
            && trim(Settings::get('smtp_from')) !== '';
    }

    /**
     * Sends one message.
     *
     * @return array{0:bool,1:string} success and error text
     */
    public static function send(
        string $to, string $subject, string $textBody, string $kind = 'other', ?string $htmlBody = null
    ): array {
        if (!self::enabled()) {
            self::log($to, $subject, $kind, false, 'SMTP is not configured.');
            return [false, 'SMTP is not configured.'];
        }

        try {
            self::startDebug($to, $subject, $kind);
            self::deliver($to, $subject, $textBody, $htmlBody);
            self::finishDebug(true, '');
            self::log($to, $subject, $kind, true, null);
            return [true, ''];
        } catch (Throwable $e) {
            self::finishDebug(false, $e->getMessage());
            self::log($to, $subject, $kind, false, $e->getMessage());
            error_log('[h3d] mail failed: ' . $e->getMessage());
            return [false, $e->getMessage()];
        }
    }

    /**
     * Sends a prepared notification and, when configured, copies it to the
     * operator so problems show up in your own inbox rather than only in a
     * log nobody opens.
     *
     * @param array{subject:string,text:string,html:string} $msg
     * @return array{0:bool,1:string}
     */
    public static function notify(string $to, array $msg, string $kind = 'other'): array
    {
        [$ok, $err] = self::send($to, $msg['subject'], $msg['text'], $kind, $msg['html'] ?? null);

        $copy = trim(Settings::get('admin_email'));
        if ($copy !== '' && Settings::bool('notify_admin')
            && strcasecmp($copy, $to) !== 0 && self::enabled()) {

            // Sent as a separate message rather than Bcc so the copy can be
            // labelled and so a failure to reach you never affects the
            // customer's delivery.
            self::send(
                $copy,
                '[' . $to . '] ' . $msg['subject'],
                "Copy of a message sent to $to.\n\n" . str_repeat('=', 58) . "\n\n" . $msg['text'],
                $kind . '_copy',
                $msg['html'] ?? null
            );
        }

        return [$ok, $err];
    }

    /** Sends without touching the log — used by the admin test button. */
    public static function trySend(string $to, string $subject, string $body, ?string $html = null): array
    {
        try {
            self::startDebug($to, $subject, 'test');
            self::deliver($to, $subject, $body, $html);
            self::finishDebug(true, '');
            self::log($to, $subject, 'test', true, null);
            return [true, ''];
        } catch (Throwable $e) {
            self::finishDebug(false, $e->getMessage());
            self::log($to, $subject, 'test', false, $e->getMessage());
            return [false, $e->getMessage()];
        }
    }

    public static function lastDebugReport(): string
    {
        return self::buildDebugReport();
    }

    public static function lastDebugId(): string
    {
        return self::$debugId;
    }

    public static function debugLogTail(int $maxBytes = 24000): string
    {
        try {
            $file = dirname(Db::path()) . '/mail-debug.log';
            if (!is_file($file)) return 'Žádný SMTP debug log zatím neexistuje.';
            $size = filesize($file);
            $fp = fopen($file, 'rb');
            if (!$fp) return 'Debug log nelze otevřít.';
            if ($size > $maxBytes) fseek($fp, -$maxBytes, SEEK_END);
            $data = stream_get_contents($fp);
            fclose($fp);
            return trim((string)$data);
        } catch (Throwable $e) {
            return 'Debug log nelze načíst: ' . $e->getMessage();
        }
    }

    private static function startDebug(string $to, string $subject, string $kind): void
    {
        self::$stage = 'connect';
        self::$trace = [];
        self::$debugId = strtoupper(bin2hex(random_bytes(4)));
        self::$debugContext = [
            'id' => self::$debugId,
            'time' => date('c'), 'recipient' => $to, 'subject' => $subject, 'kind' => $kind,
            'php' => PHP_VERSION,
            'openssl' => defined('OPENSSL_VERSION_TEXT') ? OPENSSL_VERSION_TEXT : 'n/a',
            'host' => trim(Settings::get('smtp_host')), 'port' => Settings::int('smtp_port'),
            'security' => strtolower(Settings::get('smtp_security')),
            'user' => self::maskUser(Settings::get('smtp_user')),
            'from' => self::maskEmail(Settings::get('smtp_from')),
            'timeout' => Settings::int('smtp_timeout'),
        ];
        self::trace('START', 'SMTP diagnostic started');
    }

    private static function finishDebug(bool $ok, string $error): void
    {
        self::$debugContext['ok'] = $ok;
        self::$debugContext['error'] = $error;
        self::$debugContext['stage'] = self::$stage;
        self::$debugContext['trace'] = self::$trace;
        $report = self::buildDebugReport();
        try {
            $dir = dirname(Db::path());
            if (!is_dir($dir)) @mkdir($dir, 0770, true);
            $file = $dir . '/mail-debug.log';
            file_put_contents($file, "\n===== H3D SMTP DIAGNOSTIC " . date('c') . " =====\n" . $report . "\n", FILE_APPEND | LOCK_EX);
            @chmod($file, 0640);
        } catch (Throwable $e) {
            error_log('[h3d] mail debug log failed: ' . $e->getMessage());
        }
    }

    private static function trace(string $direction, string $value): void
    {
        $v = trim($value);
        if (self::$stage === 'AUTH' && !preg_match('/^AUTH\s+LOGIN$/i', $v)) $v = '[AUTH DATA REDACTED]';
        if (strlen($v) > 1200) $v = substr($v, 0, 1200) . '…';
        self::$trace[] = date('H:i:s') . ' ' . $direction . ' ' . $v;
    }

    private static function maskEmail(string $v): string
    {
        $v = trim($v);
        if (!filter_var($v, FILTER_VALIDATE_EMAIL)) return $v === '' ? '(empty)' : '(invalid)';
        [$a, $d] = explode('@', $v, 2);
        return (strlen($a) <= 2 ? substr($a, 0, 1) : substr($a, 0, 2) . '***') . '@' . $d;
    }

    private static function maskUser(string $v): string
    {
        $v = trim($v);
        return $v === '' ? '(empty)' : (strlen($v) <= 2 ? '**' : substr($v, 0, 2) . '***');
    }

    private static function buildDebugReport(): string
    {
        if (!self::$debugContext) return 'No SMTP diagnostic has been run yet.';
        $c = self::$debugContext;
        $out = [
            'RESULT: ' . (!empty($c['ok']) ? 'SUCCESS' : 'FAILED'),
            'STAGE: ' . ($c['stage'] ?? self::$stage),
            'ERROR: ' . (($c['error'] ?? '') !== '' ? $c['error'] : '(none)'),
            'TIME: ' . ($c['time'] ?? ''),
            'PHP: ' . ($c['php'] ?? ''),
            'OpenSSL: ' . ($c['openssl'] ?? ''),
            'SMTP: ' . ($c['host'] ?? '') . ':' . ($c['port'] ?? '') . ' / ' . ($c['security'] ?? ''),
            'SMTP user: ' . ($c['user'] ?? ''),
            'From: ' . ($c['from'] ?? ''),
            'Recipient: ' . self::maskEmail((string)($c['recipient'] ?? '')),
            'Kind: ' . ($c['kind'] ?? ''),
            '--- SMTP TRACE ---'
        ];
        foreach (($c['trace'] ?? []) as $line) $out[] = $line;
        return implode("\n", $out);
    }

    private static function deliver(
        string $to, string $subject, string $textBody, ?string $htmlBody = null
    ): void
    {
        $host     = trim(Settings::get('smtp_host'));
        $port     = max(1, Settings::int('smtp_port'));
        $security = strtolower(Settings::get('smtp_security'));
        $user     = Settings::get('smtp_user');
        $pass     = Settings::get('smtp_pass');
        $from     = trim(Settings::get('smtp_from'));
        $fromName = Settings::get('smtp_from_name');
        $timeout  = max(3, Settings::int('smtp_timeout'));

        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Invalid recipient address.');
        }
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('The sender address in the settings is not valid.');
        }

        $transport = $security === 'ssl' ? 'ssl://' : '';
        $ctx = stream_context_create(['ssl' => [
            'verify_peer'       => true,
            'verify_peer_name'  => true,
            'SNI_enabled'       => true,
        ]]);

        $sock = @stream_socket_client(
            $transport . $host . ':' . $port,
            $errno, $errstr, $timeout,
            STREAM_CLIENT_CONNECT, $ctx
        );

        if (!$sock) {
            throw new RuntimeException("Cannot connect to $host:$port ($errstr)");
        }

        stream_set_timeout($sock, $timeout);

        try {
            self::$stage = 'greeting';
            self::expect($sock, 220);

            $ehloName = self::heloName();
            self::$stage = 'EHLO';
            self::cmd($sock, 'EHLO ' . $ehloName, 250);

            if ($security === 'tls') {
                self::$stage = 'STARTTLS';
                self::cmd($sock, 'STARTTLS', 220);
                if (!@stream_socket_enable_crypto(
                    $sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT
                )) {
                    throw new RuntimeException('STARTTLS negotiation failed.');
                }
                // The capability list must be re-read over the encrypted
                // channel; the pre-TLS one cannot be trusted.
                self::$stage = 'EHLO after TLS';
                self::cmd($sock, 'EHLO ' . $ehloName, 250);
            }

            if ($user !== '') {
                self::$stage = 'AUTH';
                self::authenticate($sock, $user, $pass);
            }

            self::$stage = 'MAIL FROM';
            self::cmd($sock, 'MAIL FROM:<' . $from . '>', 250);
            self::$stage = 'RCPT TO';
            self::cmd($sock, 'RCPT TO:<' . $to . '>', [250, 251]);
            self::$stage = 'DATA';
            self::cmd($sock, 'DATA', 354);

            self::$stage = 'message body';
            fwrite($sock, self::message($to, $from, $fromName, $subject, $textBody, $htmlBody));
            fwrite($sock, "\r\n.\r\n");
            self::$stage = 'DATA completion';
            self::expect($sock, 250);

            self::$stage = 'QUIT';
            self::cmd($sock, 'QUIT', [221, 250]);
        } finally {
            @fclose($sock);
        }
    }

    private static function authenticate($sock, string $user, string $pass): void
    {
        // AUTH LOGIN is the most widely accepted; PLAIN is the fallback.
        try {
            self::cmd($sock, 'AUTH LOGIN', 334);
            self::cmd($sock, base64_encode($user), 334);
            self::cmd($sock, base64_encode($pass), 235);
        } catch (RuntimeException $e) {
            $plain = base64_encode("\0" . $user . "\0" . $pass);
            self::cmd($sock, 'AUTH PLAIN ' . $plain, 235);
        }
    }

    private static function message(
        string $to, string $from, string $fromName, string $subject,
        string $body, ?string $html = null
    ): string {
        $headers = [
            'Date: ' . date('r'),
            'From: ' . self::addr($fromName, $from),
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . self::heloName() . '>',
            'MIME-Version: 1.0',
            // These tell well-behaved clients and autoresponders that the
            // message is machine-generated, which stops out-of-office replies
            // bouncing back at an address nobody reads.
            'Auto-Submitted: auto-generated',
            'X-Auto-Response-Suppress: All',
        ];

        // Use base64 for message bodies rather than raw 8-bit data. Some
        // SMTP servers do not advertise 8BITMIME and will reject a DATA block
        // containing UTF-8 Czech characters even though the SMTP connection,
        // authentication and recipient are otherwise valid. The admin SMTP
        // test is ASCII-only, which can hide this problem.
        if ($html === null || trim($html) === '') {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: base64';
            $payload = rtrim(chunk_split(base64_encode(self::stuff($body)), 76, "\r\n"));
        } else {
            // multipart/alternative: the client picks whichever part it can
            // render, and the text part keeps the message readable where HTML
            // is blocked. Both parts are base64 encoded for maximum SMTP
            // compatibility.
            $boundary = 'h3d_' . bin2hex(random_bytes(10));
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';

            $textEncoded = rtrim(chunk_split(base64_encode(self::stuff($body)), 76, "\r\n"));
            $htmlEncoded = rtrim(chunk_split(base64_encode(self::stuff($html)), 76, "\r\n"));

            $payload =
                  "--$boundary\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . $textEncoded . "\r\n"
                . "--$boundary\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . $htmlEncoded . "\r\n"
                . "--$boundary--";
        }

        return implode("\r\n", $headers) . "\r\n\r\n" . $payload;
    }

    /**
     * Normalises line endings and escapes a leading dot, which would
     * otherwise terminate the DATA block and truncate the message.
     */
    private static function stuff(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = preg_replace('/^\./m', '..', $body) ?? $body;
        return str_replace("\n", "\r\n", $body);
    }

    private static function addr(string $name, string $email): string
    {
        $name = trim($name);
        return $name === '' ? '<' . $email . '>'
            : self::encodeHeader($name) . ' <' . $email . '>';
    }

    private static function encodeHeader(string $v): string
    {
        return preg_match('/[\x80-\xFF]/', $v)
            ? '=?UTF-8?B?' . base64_encode($v) . '?='
            : $v;
    }

    private static function heloName(): string
    {
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $host = preg_replace('/:.*$/', '', $host);
        return ($host !== '' && preg_match('/^[a-z0-9.-]+$/i', $host)) ? $host : 'localhost';
    }

    /** @param int|int[] $expected */
    private static function cmd($sock, string $line, int|array $expected): string
    {
        self::trace('C>', $line);
        fwrite($sock, $line . "\r\n");
        return self::expect($sock, $expected);
    }

    /** @param int|int[] $expected */
    private static function expect($sock, int|array $expected): string
    {
        $expected = (array) $expected;
        $response = '';

        // A multi-line reply keeps a dash after the code until the last line.
        while (!feof($sock)) {
            $line = fgets($sock, 1024);
            if ($line === false) {
                break;
            }
            $response .= $line;
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        $meta = stream_get_meta_data($sock);
        if (!empty($meta['timed_out'])) {
            throw new RuntimeException('The server stopped responding.');
        }

        self::trace('S<', $response);
        $code = (int) substr(trim($response), 0, 3);
        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('SMTP [' . self::$stage . '] error: ' . trim($response));
        }

        return $response;
    }

    private static function log(string $to, string $subject, string $kind, bool $ok, ?string $err): void
    {
        try {
            Db::pdo()->prepare(
                'INSERT INTO mail_log (recipient, subject, kind, ok, error, created_at)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$to, $subject, $kind, $ok ? 1 : 0, $err, time()]);
        } catch (Throwable $e) {
            error_log('[h3d] mail log failed: ' . $e->getMessage());
        }
    }
}
