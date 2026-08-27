<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Notification content.
 *
 * Every message is built twice: a plain-text part and an HTML part, sent
 * together as multipart/alternative. Text-only looks dated; HTML-only breaks
 * in clients that refuse it and scores worse with spam filters.
 *
 * All outgoing mail is English regardless of the interface language — one
 * wording to keep correct, and an address has no language attached to it.
 *
 * Each method returns ['subject' => , 'text' => , 'html' => ].
 */
final class Notify
{
    /** Brand colours, inlined because mail clients strip <style> blocks. */
    private const NAVY   = '#0b131e';
    private const ACCENT = '#1976d2';
    private const INK    = '#20242a';
    private const MUTED  = '#66707d';
    private const LINE   = '#dde3ea';
    private const PAPER  = '#f3f5f8';

    /**
     * Language the next message is built in. Set to the recipient's language
     * (Auth::userLang) right before calling a message method; it defaults to
     * English so anything that forgets still produces a valid mail.
     */
    public static string $lang = 'en';

    /** Pick the wording for the current language. */
    private static function tr(string $en, string $cs): string
    {
        return self::$lang === 'cs' ? $cs : $en;
    }

    private static function site(): string
    {
        return Settings::get('site_name');
    }

    private static function brand(): string
    {
        $b = trim(Settings::get('brand_label'));
        return $b !== '' ? $b : self::site();
    }

    // ------------------------------------------------------------------
    // Messages
    // ------------------------------------------------------------------

    /**
     * The confirmation link.
     *
     * $bonus and $days are what THIS account was promised when it was
     * created, not what the panel offers today - see Verify::promisedBonus.
     * The message has to name both halves: an account whose welcome gift was
     * a week of unlimited exports was told it would get credits, and one
     * whose gift was days only was told nothing at all.
     */
    public static function verification(string $link, int $bonus, int $days = 0): array
    {
        $intro = self::tr(
            'Please confirm this address to finish setting up your account.',
            'Potvrď prosím tuhle adresu a dokonči nastavení účtu.'
        );

        $spell    = Orders::durationLabel($days, self::$lang === 'cs' ? 'cs' : 'en');
        $bonusMsg = '';
        if ($bonus > 0 && $days > 0) {
            $bonusMsg = self::tr(
                'Confirming adds ' . Cred::amountEn($bonus) . ' to your account, plus '
                    . $spell . ' of unlimited exports.',
                'Potvrzením ti připíšeme ' . Cred::amountCs($bonus) . ' na účet a k tomu '
                    . $spell . ' neomezených exportů.'
            );
        } elseif ($bonus > 0) {
            $bonusMsg = self::tr(
                'Confirming adds ' . Cred::amountEn($bonus) . ' to your account.',
                'Potvrzením ti připíšeme ' . Cred::amountCs($bonus) . ' na účet.'
            );
        } elseif ($days > 0) {
            $bonusMsg = self::tr(
                'Confirming starts ' . $spell . ' of unlimited exports on your account.',
                'Potvrzením ti na účtu spustíme ' . $spell . ' neomezených exportů.'
            );
        }

        $keep = $bonusMsg === ''
            ? self::tr(
                'Your account already works without confirming - confirming just proves the address is yours.',
                'Účet funguje i bez potvrzení - potvrzením jen doložíš, že adresa je tvoje.'
            )
            : self::tr(
                'Your account already works without confirming - you simply will not receive the bonus until you do.',
                'Účet funguje i bez potvrzení - jen dokud ho nepotvrdíš, nedostaneš bonus.'
            );
        $ignore = self::tr(
            'If you did not create this account, ignore this message.',
            'Pokud sis účet nezakládal, tuhle zprávu ignoruj.'
        );

        $text = self::tr('Welcome to ', 'Vítej v ') . self::site() . ".\n\n$intro\n\n$link\n\n";
        if ($bonusMsg !== '') {
            $text .= $bonusMsg . "\n\n";
        }
        $text .= "$keep\n\n$ignore\n";

        $html = self::paragraph($intro)
              . self::button($link, self::tr('Confirm my email address', 'Potvrdit e-mail'))
              . ($bonusMsg !== '' ? self::callout($bonusMsg) : '')
              . self::paragraph($keep)
              . self::paragraph($ignore, true);

        return self::wrap(
            self::tr('Confirm your email address', 'Potvrď svůj e-mail'),
            self::tr('Welcome', 'Vítej'),
            $text, $html
        );
    }

    /** $days: free days released along with the credits, if the bonus had any. */
    public static function verified(int $granted, int $balance, int $days = 0): array
    {
        $confirmed = self::tr('Your email address is confirmed.', 'Tvůj e-mail je potvrzený.');
        $done      = self::tr('Thanks - you are all set.', 'Hotovo - máš vše nastavené.');
        $spell     = Orders::durationLabel($days, self::$lang === 'cs' ? 'cs' : 'en');

        $text = "$confirmed\n\n";
        if ($granted > 0) {
            $text .= self::tr(
                Cred::amountEn($granted) . ' added.' . "\n" . 'Your balance is now ' . Cred::fmt($balance) . '.',
                'Připsáno ' . Cred::amountCs($granted) . '.' . "\n" . 'Nový zůstatek je ' . Cred::fmtCs($balance) . '.'
            ) . "\n\n";
        }
        if ($days > 0) {
            $text .= self::tr(
                'Unlimited exports are running for ' . $spell . '.',
                'Neomezené exporty ti běží ' . $spell . '.'
            ) . "\n\n";
        }
        $text .= "$done\n";

        $rows = [];
        if ($granted > 0) {
            $rows[self::tr('Credits added', 'Připsáno kreditů')] = Cred::fmt($granted);
            $rows[self::tr('New balance', 'Nový zůstatek')]      = Cred::fmt($balance);
        }
        if ($days > 0) {
            $rows[self::tr('Unlimited exports', 'Neomezené exporty')] = $spell;
        }

        $html = self::paragraph($confirmed)
              . ($rows ? self::rows($rows) : '')
              . self::paragraph($done);

        return self::wrap(
            self::tr('Email confirmed', 'E-mail potvrzen'),
            self::tr('Email confirmed', 'E-mail potvrzen'),
            $text, $html
        );
    }

    public static function orderCreated(
        array $order, string $payLink, string $instructions, string $statusLink = ''
    ): array {
        $rows = self::orderRows($order);
        $thanks  = self::tr('Thank you for your order.', 'Děkujeme za objednávku.');
        $byHand  = self::tr(
            'Payments are checked by hand, so the credits appear once the payment has been confirmed. You will get another message then.',
            'Platby kontrolujeme ručně, takže se odměna připíše až po potvrzení platby. Pak ti přijde další zpráva.'
        );

        // How long that check may take, in the recipient's language.
        $wait = Orders::processingNote(self::$lang);
        if ($wait !== '') {
            $byHand .= ' ' . $wait;
        }

        $text = "$thanks\n\n" . self::rowsText($rows) . "\n";

        // Every active payment method, so the customer does not have to open
        // the site to find out how to pay.
        $ways = [];
        foreach (Payments::active($order['currency'] ?? null) as $pm) {
            $r = Payments::forOrder($pm, $order);
            $line = $r['label'] . ': ' . ($r['url'] !== '' ? $r['url'] : $r['text']);
            if (trim($r['note']) !== '') {
                $line .= ' (' . trim($r['note']) . ')';
            }
            if ($r['url'] !== '' || $r['text'] !== '') {
                $ways[] = $line;
            }
        }
        if ($ways) {
            $text .= self::tr('How to pay:', 'Jak zaplatit:') . "\n" . implode("\n", $ways) . "\n\n";
        } elseif ($payLink !== '') {
            $text .= self::tr('Pay here:', 'Zaplatit:') . "\n$payLink\n\n";
        }
        if ($statusLink !== '')         { $text .= self::tr('Track this order:', 'Sledovat objednávku:') . "\n$statusLink\n\n"; }
        if (trim($instructions) !== '') { $text .= trim($instructions) . "\n\n"; }
        $text .= $byHand . "\n";

        $html = self::paragraph($thanks)
              . self::rows($rows)
              . self::payRows($order, $payLink)
              . (trim($instructions) !== '' ? self::callout(nl2br(e(trim($instructions)))) : '')
              . self::paragraph($byHand)
              . ($statusLink !== '' ? self::link($statusLink, self::tr('Check the status of this order', 'Zkontrolovat stav objednávky')) : '');

        return self::wrap(
            self::tr('Order ' . $order['reference'] . ' received', 'Objednávka ' . $order['reference'] . ' přijata'),
            self::tr('Order received', 'Objednávka přijata'),
            $text, $html
        );
    }

    public static function orderPaid(array $order, int $balance, string $statusLink = '', string $invoiceLink = ''): array
    {
        $rows = [
            self::tr('Reference', 'Objednávka')     => (string) $order['reference'],
            self::tr('Credits', 'Kredity')          => Cred::fmt((int) $order['credits']),
            self::tr('New balance', 'Nový zůstatek') => Cred::fmt($balance),
        ];

        $opening = self::tr(
            'Your payment has been confirmed and the order is complete.',
            'Platbu jsme potvrdili a objednávka je vyřízená.'
        );
        $ready = self::tr(
            'The credits are on your account and ready to use.',
            'Odměna je na tvém účtu a připravená k použití.'
        );

        $text = "$opening\n\n" . self::rowsText($rows) . "\n$ready\n";
        if ($invoiceLink !== '') { $text .= "\n" . self::tr('Receipt:', 'Doklad:') . "\n$invoiceLink\n"; }
        if ($statusLink !== '')  { $text .= "\n" . self::tr('Order details:', 'Detail objednávky:') . "\n$statusLink\n"; }

        $html = self::paragraph($opening)
              . self::rows($rows)
              . self::callout($ready)
              . ($invoiceLink !== '' ? self::button($invoiceLink, self::tr('View / print receipt', 'Zobrazit / vytisknout doklad')) : '')
              . ($statusLink !== '' ? self::link($statusLink, self::tr('View this order', 'Zobrazit objednávku')) : '');

        return self::wrap(
            self::tr('Credits added - order ' . $order['reference'], 'Objednávka ' . $order['reference'] . ' vyřízena'),
            self::tr('Order complete', 'Objednávka vyřízena'),
            $text, $html
        );
    }

    /** Confirmation for time packages: no credit balance message; the package waits for manual activation. */
    public static function orderTimePaid(array $order, string $statusLink = '', string $invoiceLink = ''): array
    {
        $days=(int)($order['sub_days']??0);
        $duration=Orders::durationLabel($days, self::$lang==='cs'?'cs':'en');
        $rows=[
            self::tr('Reference','Objednávka') => (string)$order['reference'],
            self::tr('Package','Balíček') => $duration,
        ];
        $opening=self::tr(
            'Your time package has been added to your account. It is waiting for activation.',
            'Časový balíček byl přidán na tvůj účet a čeká na aktivaci.'
        );
        $ready=self::tr(
            'Open your account and activate the package when you want its time to start.',
            'V účtu najdeš balíček v části Moje balíčky. Aktivuj ho ve chvíli, kdy chceš začít čerpat jeho čas.'
        );
        $text="$opening\n\n".self::rowsText($rows)."\n$ready\n";
        if($invoiceLink!=='') $text.="\n".self::tr('Receipt:','Doklad:')."\n$invoiceLink\n";
        if($statusLink!=='') $text.="\n".self::tr('Order details:','Detail objednávky:')."\n$statusLink\n";
        $html=self::paragraph($opening).self::rows($rows).self::callout($ready)
            .($invoiceLink!==''?self::button($invoiceLink,self::tr('View / print receipt','Zobrazit / vytisknout doklad')):'')
            .($statusLink!==''?self::link($statusLink,self::tr('View this order','Zobrazit objednávku')):'');
        return self::wrap(
            self::tr('Time package added - order '.$order['reference'],'Časový balíček přidán – objednávka '.$order['reference']),
            self::tr('Time package ready','Časový balíček čeká na aktivaci'),
            $text,$html
        );
    }

    public static function orderCancelled(
        array $order, string $reason, bool $byUser, string $statusLink = ''
    ): array {
        $opening = $byUser
            ? self::tr('Your order has been cancelled as requested.', 'Objednávka byla na tvou žádost zrušena.')
            : self::tr('Your order has been cancelled.', 'Tvoje objednávka byla zrušena.');
        $nothing = self::tr(
            'No credits were added and nothing is owed. You can place a new order at any time.',
            'Nic se nepřipsalo a nic nedlužíš. Novou objednávku můžeš vytvořit kdykoli.'
        );

        $rows = self::orderRows($order);
        if (trim($reason) !== '') {
            $rows[self::tr('Reason', 'Důvod')] = trim($reason);
        }

        $text = "$opening\n\n" . self::rowsText($rows) . "\n$nothing\n";
        if ($statusLink !== '') { $text .= "\n" . self::tr('Order details:', 'Detail objednávky:') . "\n$statusLink\n"; }

        $html = self::paragraph($opening)
              . self::rows($rows)
              . self::paragraph($nothing)
              . ($statusLink !== '' ? self::link($statusLink, self::tr('View this order', 'Zobrazit objednávku')) : '');

        return self::wrap(
            self::tr('Order ' . $order['reference'] . ' cancelled', 'Objednávka ' . $order['reference'] . ' zrušena'),
            self::tr('Order cancelled', 'Objednávka zrušena'),
            $text, $html
        );
    }

    /** A link to choose a new password. */
    public static function passwordReset(string $url, int $hours): array
    {
        $opening = self::tr(
            'Somebody asked to reset the password for this account.',
            'Někdo požádal o obnovu hesla k tomuto účtu.'
        );
        $body = self::tr(
            "The link below works for $hours hours and once only. If it was not you, nothing has changed and you can ignore this.",
            "Odkaz níže platí $hours hodin a jen jednou. Pokud jsi to nebyl ty, nic se nezměnilo a můžeš to ignorovat."
        );

        $text = "$opening\n$body\n\n" . self::tr('Set a new password:', 'Nastav nové heslo:') . "\n$url\n";

        $html = self::paragraph($opening)
              . self::paragraph($body, true)
              . self::button($url, self::tr('Set a new password', 'Nastavit nové heslo'));

        return self::wrap(
            self::tr('Reset your password', 'Obnova hesla'),
            self::tr('Password reset', 'Obnova hesla'),
            $text, $html
        );
    }

    /**
     * A free-form message written by the admin - "where should the refund
     * go", "your order is on hold", whatever needs saying. The subject and
     * body arrive verbatim; only the mail chrome is added around them.
     */
    public static function custom(string $subject, string $body): array
    {
        $text = $body . "\n";

        // Paragraph per blank line, line breaks kept, content escaped -
        // the admin writes plain text, not markup.
        $html = '';
        foreach (preg_split('/\n{2,}/', trim($body)) ?: [] as $para) {
            $para = trim($para);
            if ($para !== '') {
                $html .= self::paragraph(nl2br(e($para)));
            }
        }
        if ($html === '') {
            $html = self::paragraph(e($body));
        }

        return self::wrap($subject, $subject, $text, $html);
    }

    /** The refund is done - money sent back, receipt attached by link. */
    public static function refundReceipt(string $reference, string $amount, string $dest, string $url): array
    {
        $opening = self::tr(
            "Your order $reference has been refunded.",
            "Tvoje objednávka $reference byla refundována."
        );
        $body = self::tr(
            "We have sent $amount back to: $dest. The refund receipt is available at the link below.",
            "Částku $amount jsme vrátili na: $dest. Doklad o refundaci najdeš na odkazu níže."
        );

        $text = "$opening\n$body\n\n$url\n";

        $html = self::paragraph($opening)
              . self::paragraph($body, true)
              . self::button($url, self::tr('Refund receipt', 'Doklad o refundaci'));

        return self::wrap(
            self::tr("Refund of order $reference", "Refundace objednávky $reference"),
            self::tr('Order refunded', 'Objednávka refundována'),
            $text, $html
        );
    }

    /**
     * The customer asked for their money back.
     *
     * Goes to the customer as a receipt of the request; with "copy me in"
     * switched on the operator gets it too, which is how a request reaches
     * anybody at all - there is no queue that beeps.
     */
    public static function refundRequested(array $order, string $amount, string $url = ''): array
    {
        $ref     = (string) $order['reference'];
        $opening = self::tr(
            "We have your request to refund order $ref.",
            "Máme tvoji žádost o vrácení peněz za objednávku $ref."
        );
        $body = self::tr(
            "The order is on hold from now on: what it granted has been put aside, so nothing "
            . "of it can be used while the refund is dealt with. We will send $amount back to "
            . "the account the payment came from and write to you once it is done.",
            "Objednávka je od teď pozastavená: co z ní bylo připsané, je odložené stranou, "
            . "takže se to během vyřizování nedá utratit. Částku $amount pošleme zpět na účet, "
            . "ze kterého platba přišla, a napíšeme, jakmile to bude hotové."
        );

        $text = "$opening\n$body\n" . ($url !== '' ? "\n$url\n" : '');

        $html = self::paragraph($opening)
              . self::paragraph($body, true)
              . ($url !== '' ? self::button($url, self::tr('Order status', 'Stav objednávky')) : '');

        return self::wrap(
            self::tr("Refund requested for order $ref", "Žádost o vrácení peněz - objednávka $ref"),
            self::tr('Refund requested', 'Žádost o vrácení peněz'),
            $text, $html
        );
    }

    /** The invited friend verified - the inviter just earned the bonus. */
    public static function referralReward(string $bonus, string $balance): array
    {
        $opening = self::tr(
            "Someone you invited just joined and verified their account.",
            'Někdo, koho jsi pozval, se právě přidal a ověřil svůj účet.'
        );
        $body = self::tr(
            "You have received $bonus credits for the referral. Your balance is now $balance credits. Thanks for spreading the word!",
            "Za doporučení jsi získal $bonus kreditů. Aktuální zůstatek je $balance kreditů. Díky, že o nás dáváš vědět!"
        );

        $text = "$opening\n$body\n";

        $html = self::paragraph($opening)
              . self::paragraph($body, true);

        return self::wrap(
            self::tr('Your referral just paid off', 'Tvoje doporučení se vyplatilo'),
            self::tr('Referral reward', 'Odměna za doporučení'),
            $text, $html
        );
    }

    public static function emailChange(string $url, int $hours): array
    {
        $opening = self::tr(
            'A request was made to use this address for a Honza3D account.',
            'Byla podána žádost použít tuto adresu pro účet Honza3D.'
        );
        $body = self::tr(
            "Confirm it with the link below to finish the change. It works for $hours hours and once only. If it was not you, you can ignore this.",
            "Potvrď ji odkazem níže a změna se dokončí. Platí $hours hodin a jen jednou. Pokud jsi to nebyl ty, můžeš to ignorovat."
        );

        $text = "$opening\n$body\n\n" . self::tr('Confirm this address:', 'Potvrdit adresu:') . "\n$url\n";

        $html = self::paragraph($opening)
              . self::paragraph($body, true)
              . self::button($url, self::tr('Confirm this address', 'Potvrdit adresu'));

        return self::wrap(
            self::tr('Confirm your new e-mail', 'Potvrzení nového e-mailu'),
            self::tr('Confirm your e-mail', 'Potvrzení e-mailu'),
            $text, $html
        );
    }

    /** A time-based subscription revoked by an administrator. */
    public static function subscriptionCancelled(): array
    {
        $opening = self::tr('Your subscription has been cancelled.', 'Tvoje předplatné bylo zrušeno.');
        $body    = self::tr(
            'Unlimited exports have ended. Your account and any credits on it are unchanged, and you can subscribe again at any time.',
            'Neomezené exporty skončily. Účet ani kredity na něm se nemění a předplatné si můžeš kdykoli pořídit znovu.'
        );

        $text = "$opening\n$body\n";
        $html = self::paragraph($opening) . self::paragraph($body, true);

        return self::wrap(
            self::tr('Subscription cancelled', 'Předplatné zrušeno'),
            self::tr('Subscription', 'Předplatné'),
            $text, $html
        );
    }

    /** A time-based subscription that simply ran out. */
    public static function subscriptionExpired(int $endedAt, string $shopUrl): array
    {
        $opening = self::tr(
            'Your subscription expired on ' . date('d.m.Y H:i', $endedAt) . '.',
            'Tvoje předplatné vypršelo ' . date('d.m.Y H:i', $endedAt) . '.'
        );
        $body = self::tr(
            'Unlimited exports have ended. Your account and any credits on it are unchanged - you can renew the subscription any time here: ' . $shopUrl,
            'Neomezené exporty skončily. Účet ani kredity na něm se nemění - předplatné si můžeš kdykoli obnovit tady: ' . $shopUrl
        );

        $text = "$opening\n$body\n";
        $html = self::paragraph($opening) . self::paragraph($body, true);

        return self::wrap(
            self::tr('Subscription expired', 'Předplatné vypršelo'),
            self::tr('Subscription', 'Předplatné'),
            $text, $html
        );
    }

    /** A password an administrator generated, to be replaced on arrival. */
    public static function temporaryPassword(string $password, string $loginUrl): array
    {
        $opening = 'Your password has been reset by an administrator.';
        $body    = 'Sign in with the password below. You will be asked to choose '
                 . 'a new one straight away - this one stops working then.';

        $text = "$opening\n$body\n\n"
              . self::rowsText(['Temporary password' => $password])
              . "\nSign in:\n$loginUrl\n";

        $html = self::paragraph($opening)
              . self::paragraph($body, true)
              . self::rows(['Temporary password' => '<code style="font-size:15px;letter-spacing:1px">'
                                                  . e($password) . '</code>'])
              . self::button($loginUrl, 'Sign in');

        return self::wrap('Your new password', 'Password reset', $text, $html);
    }

    /** An order taken in hand, with the deadline if there is one. */
    public static function orderAccepted(array $order, int $days, string $statusUrl): array
    {
        $rows = self::orderRows($order);

        $opening = self::tr(
            'Your order has been accepted and is waiting for payment.',
            'Objednávka byla přijata ke zpracování a čeká na platbu.'
        );
        $deadline = $days > 0
            ? self::tr(
                "Please pay within $days days, or the order is cancelled automatically.",
                "Zaplať prosím do $days dní, jinak se objednávka sama zruší."
            )
            : '';

        $text = "$opening\n"
              . ($deadline !== '' ? "$deadline\n" : '')
              . "\n" . self::rowsText($rows) . "\n"
              . self::tr('Order status:', 'Stav objednávky:') . "\n$statusUrl\n";

        $html = self::paragraph($opening)
              . ($deadline !== '' ? self::callout($deadline) : '')
              . self::rows($rows)
              . self::payRows($order, '')
              . self::button($statusUrl, self::tr('View the order', 'Zobrazit objednávku'));

        return self::wrap(
            self::tr('Order accepted - ' . $order['reference'], 'Objednávka ' . $order['reference'] . ' přijata'),
            self::tr('Order accepted', 'Objednávka přijata'),
            $text, $html
        );
    }

    /** Credits added or removed by hand, with the reason given. */
    public static function creditsChanged(int $delta, int $balance, string $reason): array
    {
        $added = $delta > 0;
        $n     = Cred::fmt(abs($delta));

        $opening = $added
            ? self::tr("$n credits have been added to your account.", "Na tvůj účet bylo připsáno $n kreditů.")
            : self::tr("$n credits have been removed from your account.", "Z tvého účtu bylo odečteno $n kreditů.");

        $rows = [($added ? self::tr('Credits added', 'Připsáno kreditů') : self::tr('Credits removed', 'Odečteno kreditů')) => $n,
                 self::tr('New balance', 'Nový zůstatek') => Cred::fmt($balance)];
        if (trim($reason) !== '') {
            $rows[self::tr('Reason', 'Důvod')] = trim($reason);
        }

        $text = "$opening\n\n" . self::rowsText($rows) . "\n";

        $html = self::paragraph($opening) . self::rows($rows);

        return self::wrap(
            $added ? self::tr('Credits added to your account', 'Kredity byly připsány') : self::tr('Your credit balance changed', 'Změna zůstatku kreditů'),
            $added ? self::tr('Credits added', 'Kredity připsány') : self::tr('Balance changed', 'Změna zůstatku'),
            $text, $html
        );
    }

    // ------------------------------------------------------------------
    // Building blocks
    // ------------------------------------------------------------------

    /** @return array<string,string> */
    private static function orderRows(array $order): array
    {
        $rows = [self::tr('Reference', 'Objednávka') => (string) ($order['reference'] ?? '')];

        $vs = Payments::vs($order);
        if ($vs !== '') {
            $rows[self::tr('Variable symbol', 'Variabilní symbol')] = $vs;
        }

        $rows[self::tr('Credits', 'Kredity')] = Cred::fmt((int) ($order['credits'] ?? 0));
        $rows[self::tr('Amount', 'Částka')]   = self::money((int) ($order['price_cents'] ?? 0), $order['currency'] ?? null);

        return $rows;
    }

    private static function rowsText(array $rows): string
    {
        $out = '';
        $pad = max(array_map('strlen', array_keys($rows))) + 2;
        foreach ($rows as $k => $v) {
            $out .= str_pad($k . ':', $pad) . $v . "\n";
        }
        return $out;
    }

    /** Payment options as a table, falling back to a single button. */
    private static function payRows(array $order, string $payLink): string
    {
        $rows = [];
        foreach (Payments::active($order['currency'] ?? null) as $pm) {
            $r = Payments::forOrder($pm, $order);
            if ($r['url'] !== '') {
                $rows[$r['label']] = '<a href="' . e($r['url']) . '" style="color:' . self::ACCENT . '">'
                                   . e($r['url']) . '</a>';
            } elseif ($r['text'] !== '') {
                $rows[$r['label']] = e($r['text']);
            }
            if (isset($rows[$r['label']]) && trim($r['note']) !== '') {
                $rows[$r['label']] .= '<br><span style="font-size:12px;color:' . self::MUTED . '">'
                                    . e(trim($r['note'])) . '</span>';
            }
        }

        if (!$rows) {
            return $payLink !== '' ? self::button($payLink, self::tr('Pay now', 'Zaplatit')) : '';
        }

        $out = '<p style="margin:0 0 8px;font-size:13px;font-weight:700;color:' . self::INK . '">'
             . e(self::tr('How to pay', 'Jak zaplatit')) . '</p>'
             . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"'
             . ' style="margin:0 0 20px;border:1px solid ' . self::LINE . ';border-radius:10px;'
             . 'border-collapse:separate;overflow:hidden">';
        $i = 0;
        foreach ($rows as $k => $v) {
            $bg = $i % 2 === 0 ? '#ffffff' : self::PAPER;
            $out .= '<tr><td style="padding:11px 16px;background:' . $bg . ';font-size:13px;color:'
                  . self::MUTED . ';white-space:nowrap;vertical-align:top">' . e($k) . '</td>'
                  . '<td style="padding:11px 16px;background:' . $bg . ';font-size:13px;color:'
                  . self::INK . ';word-break:break-all">' . $v . '</td></tr>';
            $i++;
        }
        return $out . '</table>';
    }

    private static function paragraph(string $html, bool $muted = false): string
    {
        $colour = $muted ? self::MUTED : self::INK;
        return '<p style="margin:0 0 16px;font-size:15px;line-height:1.6;color:' . $colour . '">'
             . $html . '</p>';
    }

    private static function rows(array $rows): string
    {
        $out = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"'
             . ' style="margin:0 0 20px;border:1px solid ' . self::LINE . ';border-radius:10px;'
             . 'border-collapse:separate;overflow:hidden">';

        $i = 0;
        foreach ($rows as $k => $v) {
            $bg = $i % 2 === 0 ? '#ffffff' : self::PAPER;
            $out .= '<tr>'
                  . '<td style="padding:11px 16px;background:' . $bg . ';font-size:13px;color:'
                  . self::MUTED . ';white-space:nowrap">' . e($k) . '</td>'
                  . '<td style="padding:11px 16px;background:' . $bg . ';font-size:14px;color:'
                  . self::INK . ';font-weight:600;text-align:right">' . e($v) . '</td>'
                  . '</tr>';
            $i++;
        }

        return $out . '</table>';
    }

    private static function button(string $url, string $label): string
    {
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0"'
             . ' style="margin:0 0 14px"><tr><td style="border-radius:10px;background:'
             . self::ACCENT . '">'
             . '<a href="' . e($url) . '" style="display:inline-block;padding:13px 26px;'
             . 'font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:10px">'
             . e($label) . '</a></td></tr></table>'
             // Some clients strip buttons or rewrite links; the bare URL
             // underneath means the message is never a dead end.
             . '<p style="margin:0 0 20px;font-size:12px;line-height:1.5;color:' . self::MUTED . '">'
             . e(self::tr('If the button does not work, copy this address into your browser:',
                          'Pokud tlačítko nefunguje, zkopíruj tuhle adresu do prohlížeče:')) . '<br>'
             . '<span style="color:' . self::ACCENT . '">' . e($url) . '</span></p>';
    }

    private static function link(string $url, string $label): string
    {
        return '<p style="margin:0 0 16px;font-size:14px">'
             . '<a href="' . e($url) . '" style="color:' . self::ACCENT . ';text-decoration:underline">'
             . e($label) . '</a></p>';
    }

    private static function callout(string $html): string
    {
        return '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"'
             . ' style="margin:0 0 20px"><tr><td style="padding:14px 16px;background:#eaf2fb;'
             . 'border-left:3px solid ' . self::ACCENT . ';border-radius:6px;font-size:14px;'
             . 'line-height:1.55;color:#14508f">' . $html . '</td></tr></table>';
    }

    /**
     * Wraps a body in the shared shell: header, content, footer.
     *
     * The footer carries the "generated automatically, do not reply" notice,
     * since these come from an address nobody reads.
     */
    private static function wrap(string $subject, string $heading, string $text, string $bodyHtml): array
    {
        $site  = self::site();
        $links = Links::all();

        // ---- plain-text footer ----
        $autoNote = self::tr(
            'This message was generated automatically. Please do not reply - replies to this address are not read.',
            'Tato zpráva byla vygenerována automaticky. Neodpovídej na ni - odpovědi na tuhle adresu nikdo nečte.'
        );

        $text .= "\n" . str_repeat('-', 58) . "\n" . wordwrap($autoNote, 70) . "\n";
        if ($links) {
            $text .= "\n";
            foreach ($links as $l) {
                $text .= $l['label'] . ': ' . $l['url'] . "\n";
            }
        }
        $text .= "\n$site\n";

        // ---- html footer ----
        $linksHtml = '';
        if ($links) {
            $parts = [];
            foreach ($links as $l) {
                $parts[] = '<a href="' . e($l['url']) . '" style="color:' . self::ACCENT
                         . ';text-decoration:none">' . e($l['label']) . '</a>';
            }
            $linksHtml = '<p style="margin:0 0 10px;font-size:12px;color:' . self::MUTED . '">'
                       . implode(' &nbsp;·&nbsp; ', $parts) . '</p>';
        }

        $html = '<!doctype html><html><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<title>' . e($subject) . '</title></head>'
            . '<body style="margin:0;padding:0;background:' . self::PAPER . '">'

            // Preview line shown in the inbox before the message is opened.
            . '<div style="display:none;max-height:0;overflow:hidden;opacity:0">'
            . e($heading) . ' · ' . e($site) . '</div>'

            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%"'
            . ' style="background:' . self::PAPER . '"><tr><td align="center" style="padding:28px 14px">'

            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="560"'
            . ' style="width:100%;max-width:560px;background:#ffffff;border:1px solid ' . self::LINE . ';'
            . 'border-radius:16px;overflow:hidden;font-family:-apple-system,BlinkMacSystemFont,'
            . "'Segoe UI',Roboto,Helvetica,Arial,sans-serif\">"

            . '<tr><td style="padding:22px 28px;background:' . self::NAVY . '">'
            . '<span style="font-size:17px;font-weight:800;color:#ffffff;letter-spacing:.3px">'
            . e(self::brand()) . '</span>'
            . '<span style="font-size:12px;color:#8e9aae;margin-left:10px">' . e($heading) . '</span>'
            . '</td></tr>'

            . '<tr><td style="padding:28px">' . $bodyHtml . '</td></tr>'

            . '<tr><td style="padding:18px 28px 24px;background:' . self::PAPER . ';'
            . 'border-top:1px solid ' . self::LINE . '">'
            . $linksHtml
            . '<p style="margin:0;font-size:12px;line-height:1.55;color:' . self::MUTED . '">'
            . e($autoNote) . '<br>' . e($site)
            . '</p></td></tr>'

            . '</table></td></tr></table></body></html>';

        return ['subject' => $subject, 'text' => $text, 'html' => $html];
    }

    private static function money(int $cents, ?string $code = null): string
    {
        return Money::fmt($cents, $code);
    }
}
