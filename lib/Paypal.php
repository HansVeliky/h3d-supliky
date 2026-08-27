<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Builds the PayPal link shown on an unpaid order.
 *
 * Two shapes are supported because they behave differently:
 *
 *  - A paypal.me address takes the amount and currency in the path, so the
 *    payer sees the right number without typing it.
 *  - Anything else (a hosted button, a payment-request URL, an invoice) is
 *    used exactly as configured, because guessing at another provider's
 *    query parameters is how you end up sending people to a broken page.
 *
 * Either way this only *opens* PayPal. Nothing here can confirm that money
 * arrived — the order still has to be marked paid in the admin panel. A
 * link is not a gateway, and treating it as one would be the difference
 * between an honest manual flow and one that silently hands out credits.
 */
final class Paypal
{
    public static function configured(): bool
    {
        return self::base() !== '';
    }

    /**
     * The configured target, from either the user name or the full link.
     *
     * The user name is the normal case and is what the settings page asks
     * for; the raw link stays available for a hosted button or a different
     * provider, and wins when both are filled in.
     */
    private static function base(): string
    {
        $link = trim(Settings::get('paypal_link'));
        if ($link !== '') {
            return $link;
        }

        $user = trim(Settings::get('paypal_user'));
        if ($user === '') {
            return '';
        }

        // Accept "honza3d", "@honza3d" or a pasted paypal.me address.
        $user = ltrim($user, '@');
        $user = preg_replace('~^https?://(www\.)?paypal\.me/~i', '', $user) ?? $user;
        $user = trim((string) $user, '/');

        return $user === '' ? '' : 'https://www.paypal.me/' . rawurlencode($user);
    }

    /** @return string empty when no link is configured */
    public static function linkFor(array $order): string
    {
        $base = self::base();
        if ($base === '') {
            return '';
        }

        // Accept "paypal.me/name", "@name" or a full URL.
        if (str_starts_with($base, '@')) {
            $base = 'https://www.paypal.me/' . ltrim($base, '@');
        } elseif (!preg_match('~^https?://~i', $base)) {
            $base = 'https://' . $base;
        }

        $host = strtolower((string) parse_url($base, PHP_URL_HOST));

        if ($host === 'paypal.me' || $host === 'www.paypal.me') {
            $amount   = number_format(((int) $order['price_cents']) / 100, 2, '.', '');
            $currency = preg_replace('/[^A-Z]/', '', strtoupper(Settings::get('currency'))) ?: 'CZK';

            return rtrim($base, '/') . '/' . rawurlencode($amount) . rawurlencode($currency);
        }

        return $base;
    }
}
