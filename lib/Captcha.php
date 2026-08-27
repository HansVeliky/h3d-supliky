<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Small browser proof-of-work challenge.
 *
 * The challenge is intentionally stateless. Earlier versions stored the
 * challenge in PHP session data; that made the public resend form fail with
 * "expired" whenever the session changed between rendering and POST. The
 * signed token below contains all server-verifiable challenge data, so the
 * POST does not depend on a session containing the same CAPTCHA anymore.
 */
final class Captcha
{
    private const RANGE = 120000;
    private const TTL = 900;

    private static function secret(): string
    {
        // Keep the signing secret independent of the site version. Changing
        // CSS/HTML/PHP versions must never invalidate a challenge that was
        // rendered moments earlier in another request.
        require_once __DIR__ . '/captcha-secret.php';
        return H3D_CAPTCHA_SECRET;
    }

    private static function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function unb64(string $value): string|false
    {
        $value = strtr($value, '-_', '+/');
        $pad = strlen($value) % 4;
        if ($pad) $value .= str_repeat('=', 4 - $pad);
        return base64_decode($value, true);
    }

    public static function enabled(): bool
    {
        return Settings::bool('captcha_enabled');
    }

    public static function issue(): array
    {
        $salt = bin2hex(random_bytes(12));
        $answer = random_int(0, self::RANGE - 1);
        $issued = time();
        $target = hash('sha256', $salt . $answer);
        $id = bin2hex(random_bytes(16));

        $payload = [
            'id' => $id,
            'salt' => $salt,
            'target' => $target,
            'range' => self::RANGE,
            'issued' => $issued,
        ];
        $raw = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $sig = hash_hmac('sha256', (string)$raw, self::secret());
        $token = self::b64((string)$raw) . '.' . $sig;

        return [
            'id' => $id,
            'salt' => $salt,
            'target' => $target,
            'range' => self::RANGE,
            'issued' => $issued,
            'token' => $token,
        ];
    }

    /** @return array{0:bool,1:string} */
    public static function verify(array $post): array
    {
        if (!self::enabled()) return [true, ''];

        if (trim((string)($post['website'] ?? '')) !== '') {
            return [false, 'Verification failed. Please try again.'];
        }

        $token = trim((string)($post['captcha_token'] ?? ''));
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return [false, 'The verification expired. Please reload and try again.'];
        }

        $raw = self::unb64($parts[0]);
        $sig = $parts[1];
        if ($raw === false || !preg_match('/^[a-f0-9]{64}$/', $sig)) {
            return [false, 'The verification expired. Please reload and try again.'];
        }

        $expectedSig = hash_hmac('sha256', $raw, self::secret());
        if (!hash_equals($expectedSig, $sig)) {
            return [false, 'The verification expired. Please reload and try again.'];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)
            || !isset($data['id'], $data['salt'], $data['target'], $data['range'], $data['issued'])
            || !is_string($data['id'])
            || !is_string($data['salt'])
            || !is_string($data['target'])
            || !is_int($data['range'])
            || !is_int($data['issued'])
            || !preg_match('/^[a-f0-9]{32}$/', $data['id'])
            || !preg_match('/^[a-f0-9]{24}$/', $data['salt'])
            || !preg_match('/^[a-f0-9]{64}$/', $data['target'])
            || $data['range'] !== self::RANGE
        ) {
            return [false, 'The verification expired. Please reload and try again.'];
        }

        if ($data['issued'] <= 0 || time() - $data['issued'] > self::TTL || $data['issued'] > time() + 30) {
            return [false, 'The verification expired. Please reload and try again.'];
        }

        $given = (string)($post['captcha_answer'] ?? '');
        if ($given === '' || !ctype_digit($given)) {
            return [false, 'Please wait for browser verification to finish.'];
        }

        $answer = (int)$given;
        if ($answer < 0 || $answer >= self::RANGE) {
            return [false, 'Verification failed. Please try again.'];
        }

        $actual = hash('sha256', $data['salt'] . $answer);
        if (!hash_equals($data['target'], $actual)) {
            return [false, 'Verification failed. Please try again.'];
        }

        return [true, ''];
    }

    public static function field(): string
    {
        if (!self::enabled()) return '';

        $c = self::issue();

        return '
<div class="captcha" data-captcha
     data-captcha-id="' . e($c['id']) . '"
     data-salt="' . e($c['salt']) . '"
     data-target="' . e($c['target']) . '"
     data-range="' . (int)$c['range'] . '"
     data-captcha-token="' . e($c['token']) . '">
  <span class="captcha-spinner" aria-hidden="true"></span>
  <span class="captcha-text">Checking your browser…</span>
  <input type="hidden" name="captcha_token" value="' . e($c['token']) . '">
  <input type="hidden" name="captcha_answer" value="">
</div>

<div class="hp-field" aria-hidden="true">
  <label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label>
</div>';
    }
}
