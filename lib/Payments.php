<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Payment methods offered on an order.
 *
 * Each row is one way to pay. The kind decides two things: which icon is
 * drawn, and how the target is treated - some kinds build a link with the
 * amount in it, others are just an account number to copy.
 *
 * Icons are inline SVG rather than image files. They have to work in the
 * admin, on the order page and in an email, and a brand logo fetched from a
 * CDN would break on a network that blocks foreign hosts - which is exactly
 * the network this runs on.
 */
final class Payments
{
    /**
     * kind => [label, whether the target is a link, hint for the admin]
     *
     * The label is also what a method is called when the admin leaves the
     * name blank, so it ends up in front of the customer - hence Czech.
     */
    public const KINDS = [
        'paypal'   => ['PayPal',           false, 'Uživatelské jméno z paypal.me, například honza3d'],
        'revolut'  => ['Revolut',          false, 'Uživatelské jméno z revolut.me'],
        'bank'     => ['Bankovní převod',  false, 'Číslo účtu, zobrazí se k opsání'],
        'qr'       => ['QR platba',        false, 'IBAN nebo číslo účtu; QR kód se vygeneruje s částkou a VS'],
        'card'     => ['Platební karta',   true,  'Odkaz na platební stránku'],
        'crypto'   => ['Kryptoměna',       false, 'Adresa peněženky, zobrazí se k opsání'],
        'cash'     => ['Osobně',           false, 'Co je potřeba říct k platbě na místě'],
        'link'     => ['Jiný odkaz',       true,  'Libovolná adresa, použije se přesně tak, jak je zapsaná'],
        'other'    => ['Jiné',             false, 'Jen pokyny, žádný odkaz'],
    ];

    /** @return array<int, array> */
    public static function all(): array
    {
        return Db::pdo()->query(
            'SELECT * FROM payment_methods ORDER BY sort, id'
        )->fetchAll();
    }

    /**
     * Active methods, optionally only those that accept a given currency.
     * A method with no restriction accepts everything.
     */
    public static function active(?string $currency = null): array
    {
        $rows = Db::pdo()->query(
            'SELECT * FROM payment_methods WHERE active = 1 ORDER BY sort, id'
        )->fetchAll();

        if ($currency === null) {
            return $rows;
        }

        $currency = Money::code($currency);

        return array_values(array_filter($rows, static function (array $r) use ($currency): bool {
            $list = trim((string) ($r['currencies'] ?? ''));
            if ($list === '') {
                return true;
            }
            $codes = array_map('trim', explode(',', strtoupper($list)));
            return in_array($currency, $codes, true);
        }));
    }

    public static function find(int $id): ?array
    {
        $st = Db::pdo()->prepare('SELECT * FROM payment_methods WHERE id = ?');
        $st->execute([$id]);
        return $st->fetch() ?: null;
    }

    public static function save(
        int $id, string $kind, string $label, string $target,
        string $note, bool $active, int $sort, string $currencies = ''
    ): void {
        if (!isset(self::KINDS[$kind])) {
            $kind = 'other';
        }
        $label = trim($label) !== '' ? trim($label) : self::KINDS[$kind][0];

        if ($id > 0) {
            Db::pdo()->prepare(
                'UPDATE payment_methods SET kind=?, label=?, target=?, note=?, active=?, sort=?, currencies=? WHERE id=?'
            )->execute([$kind, $label, trim($target), trim($note), $active ? 1 : 0, $sort,
                        self::cleanCurrencies($currencies), $id]);
        } else {
            Db::pdo()->prepare(
                'INSERT INTO payment_methods (kind, label, target, note, active, sort, currencies)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([$kind, $label, trim($target), trim($note), $active ? 1 : 0, $sort,
                        self::cleanCurrencies($currencies)]);
        }
    }

    public static function delete(int $id): void
    {
        Db::pdo()->prepare('DELETE FROM payment_methods WHERE id = ?')->execute([$id]);
    }

    /**
     * Resolves one method against an order.
     *
     * @return array{label:string, kind:string, url:string, text:string, note:string}
     */
    public static function forOrder(array $method, array $order): array
    {
        $kind   = (string) $method['kind'];
        $target = trim((string) $method['target']);
        $url    = '';
        $text   = '';

        // The order's own currency, not today's shop setting: switching the
        // shop to euros must not relabel an invoice that was paid in crowns.
        $currency = Money::code($order['currency'] ?? null);
        $amount   = number_format(((int) ($order['price_cents'] ?? 0)) / 100, 2, '.', '');

        if ($target !== '') {
            switch ($kind) {
                case 'paypal':
                    // paypal.me takes the amount in the path, so the payer
                    // does not have to type it and cannot mistype it.
                    $url = 'https://www.paypal.me/' . rawurlencode(self::handle($target, 'paypal.me'))
                         . '/' . rawurlencode($amount) . rawurlencode($currency);
                    break;

                case 'revolut':
                    $url = 'https://revolut.me/' . rawurlencode(self::handle($target, 'revolut.me'));
                    break;

                case 'card':
                case 'link':
                    $url = self::url($target);
                    break;

                default:
                    // bank, crypto, cash, other: something to read, not click.
                    $text = $target;
            }
        }

        return [
            'label'  => (string) $method['label'],
            'kind'   => $kind,
            'url'    => $url,
            'text'   => $text,
            'note'   => (string) $method['note'],
            'spayd'  => $kind === 'qr' && $target !== '' ? self::spayd($target, $order) : '',
            'detail' => $kind === 'qr' && $target !== '' ? [
                'Účet'               => self::prettyAccount($target),
                'Částka'             => money((int) ($order['price_cents'] ?? 0), $currency),
                'Variabilní symbol'  => self::vs($order),
            ] : [],
        ];
    }

    /**
     * Czech payment string for a QR code (SPAYD, the format every Czech
     * banking app reads).
     *
     * The amount comes from the order and the variable symbol from its
     * reference, so the customer cannot mistype either - which is the whole
     * point of the QR code and the main reason payments go unmatched.
     */
    public static function spayd(string $account, array $order): string
    {
        $amount   = number_format(((int) ($order['price_cents'] ?? 0)) / 100, 2, '.', '');
        $currency = Money::code($order['currency'] ?? null);
        $ref      = (string) ($order['reference'] ?? '');

        $parts = [
            'SPD',
            '1.0',
            'ACC:' . self::iban($account),
            'AM:' . $amount,
            'CC:' . $currency,
        ];

        $vs = self::vs($order);
        if ($vs !== '') {
            $parts[] = 'X-VS:' . $vs;
        }
        if ($ref !== '') {
            // The message keeps the human-readable reference, since the
            // variable symbol can only hold digits.
            $parts[] = 'MSG:' . self::spaydSafe($ref);
        }

        return implode('*', $parts);
    }

    /**
     * The variable symbol: digits only, at most ten.
     *
     * Stored on the order rather than derived from the reference, because
     * the digits inside "H3D-A1B2C3" are "3123" - a number that matches
     * nothing and would send payments to the wrong place, or nowhere.
     */
    public static function vs(array $order): string
    {
        $vs = preg_replace('/\D+/', '', (string) ($order['vs'] ?? '')) ?? '';
        if ($vs !== '') {
            return substr($vs, -10);
        }
        // Older orders, before the column existed.
        return isset($order['id']) ? sprintf('%06d', (int) $order['id']) : '';
    }

    /** SPAYD is asterisk-delimited, so the payload must not contain one. */
    private static function spaydSafe(string $v): string
    {
        $v = str_replace('*', '-', $v);
        return substr(preg_replace('/[^\x20-\x7E]/', '', $v) ?? '', 0, 60);
    }

    /** Accepts an IBAN or a local account number, normalised for SPAYD. */
    private static function iban(string $account): string
    {
        return strtoupper(preg_replace('/\s+/', '', $account) ?? $account);
    }

    private static function prettyAccount(string $account): string
    {
        $a = trim($account);
        // An IBAN reads far better in groups of four.
        if (preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]+$/i', str_replace(' ', '', $a))) {
            return trim(chunk_split(strtoupper(str_replace(' ', '', $a)), 4, ' '));
        }
        return $a;
    }

    /** Keeps only well-formed codes from a comma separated list. */
    private static function cleanCurrencies(string $list): string
    {
        $out = [];
        foreach (explode(',', strtoupper($list)) as $c) {
            $c = trim($c);
            if (Money::isCode($c)) {
                $out[$c] = true;
            }
        }
        return implode(',', array_keys($out));
    }

    /** Strips a pasted full address back to the bare user name. */
    private static function handle(string $v, string $host): string
    {
        $v = ltrim(trim($v), '@');
        $v = preg_replace('~^https?://(www\.)?' . preg_quote($host, '~') . '/~i', '', $v) ?? $v;
        return trim((string) $v, '/');
    }

    private static function url(string $v): string
    {
        $v = trim($v);
        if (!preg_match('~^https?://~i', $v)) {
            $v = 'https://' . $v;
        }
        return filter_var($v, FILTER_VALIDATE_URL) ? $v : '';
    }

    /**
     * Inline SVG badge for a kind, sized to the current font.
     *
     * Drawn rather than fetched: a logo from a CDN would fail on a network
     * that blocks foreign hosts, and would put a third party in the page.
     */
    public static function icon(string $kind, int $size = 22): string
    {
        $paths = [
            // Stylised "P" on a rounded tile.
            'paypal'  => ['#003087', '<path d="M9 17.5 10.6 6.5h4.2c2.3 0 3.6 1.2 3.3 3.2-.3 2.2-2 3.5-4.4 3.5h-1.7l-.6 4.3H9Zm3.4-6.1h1.4c1.1 0 1.9-.5 2-1.5.1-.8-.4-1.3-1.4-1.3h-1.4l-.6 2.8Z" fill="#fff"/>'],
            'revolut' => ['#0075EB', '<path d="M8.5 6.5h4.7c2.2 0 3.6 1.3 3.6 3.3 0 1.7-1 2.9-2.6 3.2l3 4.5h-2.7l-2.7-4.2h-1v4.2H8.5V6.5Zm2.3 1.9v2.9h2.2c1 0 1.6-.5 1.6-1.5 0-.9-.6-1.4-1.6-1.4h-2.2Z" fill="#fff"/>'],
            'bank'    => ['#2f6f4e', '<path d="M12 5.5 19 9v1.4H5V9l7-3.5ZM7 11.6h1.8v5.2H7v-5.2Zm4.1 0h1.8v5.2h-1.8v-5.2Zm4.1 0H17v5.2h-1.8v-5.2ZM5 17.8h14V19.2H5v-1.4Z" fill="#fff"/>'],
            'qr'      => ['#20242a', '<path d="M6 6h4.2v4.2H6V6Zm1.5 1.5v1.2h1.2V7.5H7.5ZM13.8 6H18v4.2h-4.2V6Zm1.5 1.5v1.2h1.2V7.5h-1.2ZM6 13.8h4.2V18H6v-4.2Zm1.5 1.5v1.2h1.2v-1.2H7.5Zm6.3-1.5h1.6v1.6h-1.6v-1.6Zm3.1 0H18v1.6h-1.1v-1.6Zm-3.1 3.1h1.6V18h-1.6v-1.1Zm3.1 0H18V18h-1.1v-1.1Z" fill="#fff"/>'],
            'card'    => ['#5a3fa0', '<path d="M4.6 7.5h14.8c.6 0 1.1.5 1.1 1.1v6.8c0 .6-.5 1.1-1.1 1.1H4.6c-.6 0-1.1-.5-1.1-1.1V8.6c0-.6.5-1.1 1.1-1.1Zm-.1 3.1h15v1.6h-15v-1.6Zm1.6 3.3h3.6v1.2H6.1v-1.2Z" fill="#fff"/>'],
            'crypto'  => ['#b8860b', '<path d="M12 4.8 19.2 12 12 19.2 4.8 12 12 4.8Zm0 3-1.9 4.2h3.8L12 7.8Zm-2.3 5.5L12 16.9l2.3-3.6h-4.6Z" fill="#fff"/>'],
            'cash'    => ['#1c7a4a', '<path d="M4.5 7.8h15v8.4h-15V7.8Zm7.5 1.7a2.5 2.5 0 1 0 0 5 2.5 2.5 0 0 0 0-5ZM6.3 9.4h1.5v1.5H6.3V9.4Zm9.9 3.7h1.5v1.5h-1.5v-1.5Z" fill="#fff"/>'],
            'link'    => ['#1976d2', '<path d="M10.4 13.6a3.4 3.4 0 0 0 5 .3l2-2a3.4 3.4 0 0 0-4.8-4.8l-1.1 1.1 1.2 1.2 1.1-1.1a1.7 1.7 0 1 1 2.4 2.4l-2 2a1.7 1.7 0 0 1-2.5-.1l-1.3 1ZM13.6 10.4a3.4 3.4 0 0 0-5-.3l-2 2a3.4 3.4 0 0 0 4.8 4.8l1.1-1.1-1.2-1.2-1.1 1.1a1.7 1.7 0 1 1-2.4-2.4l2-2a1.7 1.7 0 0 1 2.5.1l1.3-1Z" fill="#fff"/>'],
            'other'   => ['#66707d', '<path d="M12 5.6a6.4 6.4 0 1 0 0 12.8 6.4 6.4 0 0 0 0-12.8Zm.9 10.2h-1.8V14h1.8v1.8Zm1.3-4.6c-.3.4-.8.8-1.2 1.1-.3.3-.4.5-.4.9v.4h-1.4v-.6c0-.7.3-1.2.9-1.7.5-.4.9-.7.9-1.2 0-.6-.4-1-1.1-1-.6 0-1.1.4-1.2 1.1H9.3c.1-1.4 1.2-2.4 2.7-2.4 1.5 0 2.6.9 2.6 2.2 0 .5-.1.9-.4 1.2Z" fill="#fff"/>'],
        ];

        [$bg, $glyph] = $paths[$kind] ?? $paths['other'];

        return '<svg class="pay-icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24"'
             . ' role="img" aria-hidden="true" focusable="false">'
             . '<rect width="24" height="24" rx="6" fill="' . $bg . '"/>'
             . $glyph
             . '</svg>';
    }
}
