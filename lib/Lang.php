<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Translations for the pages rendered by PHP.
 *
 * The studio translates itself in the browser, which works because it is one
 * long-lived page. These pages are plain server-rendered forms, so the text
 * has to be chosen before it is sent. The language comes from the same
 * `h3d_lang` cookie the studio sets, so switching it there carries across.
 *
 * The administration is deliberately left out: it is Czech throughout and
 * translating it would double the wording with no reader to benefit.
 */
final class Lang
{
    private static ?string $lang = null;

    private const STRINGS = [
        // --- top bar and shared chrome ---
        'nav.studio'        => ['Studio', 'Studio'],
        'nav.account'       => ['Account', 'Účet'],
        'nav.admin'         => ['Admin', 'Administrace'],
        'nav.signout'       => ['Sign out', 'Odhlásit se'],
        'nav.signin'        => ['Sign in', 'Přihlásit se'],
        'nav.credits'       => ['cr', 'kr'],

        // --- account page ---
        'acc.title'         => ['Account', 'Účet'],
        'acc.credits'       => ['credits available', 'kreditů k dispozici'],
        'acc.allowance'     => ['Export allowance', 'Kvóta na exporty'],
        'acc.unlimited'     => ['Exports are currently unlimited.', 'Exporty jsou teď bez omezení.'],
        'acc.freenow'       => ['A free export is available right now.', 'Volný export je právě k dispozici.'],
        'acc.nextfree'      => ['The next free export is in', 'Další volný export bude za'],
        'acc.costsbefore'   => ['Exporting before then costs', 'Dřívější export stojí'],
        'acc.credit'        => ['credit', 'kredit'],
        'acc.credits.plural'=> ['credits', 'kreditů'],
        // "3 of 5" says what is left and what the ceiling is in one line;
        // "one every 30 min" left people counting backwards from a rule.
        'acc.freecount'     => ['You have %1$s of %2$s free exports left.',
                                'Máš k dispozici %1$s z %2$s volných exportů.'],
        'acc.resetin'       => ['One comes back in %s.', 'Jeden se vrátí za %s.'],
        'acc.resetall'      => ['The allowance resets in %s.', 'Kvóta se obnoví za %s.'],
        'acc.windowrule'    => ['Credits let you skip the wait.',
                                'S kredity nemusíš čekat.'],
        'acc.nofree'        => ['Every export uses credits.', 'Každý export stojí kredity.'],
        'acc.needcredits'   => ['You have no credits left.', 'Nemáš žádné kredity.'],

        'acc.redeem'        => ['Redeem a code', 'Uplatnit kód'],
        'acc.code'          => ['Code', 'Kód'],
        'acc.codehint'      => ['Case and dashes do not matter.', 'Na velikosti písmen ani pomlčkách nezáleží.'],
        'acc.redeem.btn'    => ['Redeem', 'Uplatnit'],

        'acc.prefs'         => ['Preferences', 'Předvolby'],
        'acc.confirmspend'  => ['Ask before spending a credit',
                                'Zeptat se před odečtením kreditu'],
        'acc.confirmhint'   => ['A short dialog before an export that costs credits.',
                                'Krátké potvrzení před exportem, který stojí kredity.'],
        'acc.save'          => ['Save', 'Uložit'],
        'acc.saved'         => ['Saved.', 'Uloženo.'],
        'acc.unlimitedrole' => ['Your account exports without limits.',
                                'Tvůj účet exportuje bez omezení.'],

        'acc.password'      => ['Change password', 'Změna hesla'],
        'acc.pass.current'  => ['Current password', 'Současné heslo'],
        'acc.pass.new'      => ['New password', 'Nové heslo'],
        'acc.pass.btn'      => ['Change', 'Změnit'],

        'acc.buy'           => ['Buy credits', 'Koupit kredity'],
        'acc.payin'         => ['Pay in', 'Platit v'],
        'acc.change'        => ['Change', 'Změnit'],
        'acc.convertedfrom' => ['Converted from %s at the rate set by the shop.',
                                'Přepočteno z %s kurzem, který nastavil obchod.'],
        'acc.free'          => ['Free', 'Zdarma'],
        'acc.order'         => ['Order', 'Objednat'],
        'acc.unavailable'   => ['Unavailable', 'Nedostupné'],
        'acc.lowest30'      => ['Lowest price in the last 30 days:', 'Nejnižší cena za posledních 30 dní:'],
        'acc.islowest'      => ['This is the lowest price of the last 30 days.',
                                'Tohle je nejnižší cena za posledních 30 dní.'],
        'acc.offperpack'    => ['off every package', 'na všechny balíčky'],
        'acc.until'         => ['until', 'do'],

        'acc.orders'        => ['Orders', 'Objednávky'],
        'acc.history'       => ['Account history', 'Historie účtu'],
        'acc.noorders'      => ['No orders yet.', 'Zatím žádné objednávky.'],
        'acc.nohistory'     => ['Nothing yet.', 'Zatím nic.'],
        'acc.detail'        => ['Detail', 'Detail'],
        'acc.cancel'        => ['Cancel', 'Zrušit'],
        'acc.cancelask'     => ['Cancel this order?', 'Opravdu zrušit tuto objednávku?'],

        // --- table headings ---
        'th.when'           => ['When', 'Kdy'],
        'th.reference'      => ['Reference', 'Číslo objednávky'],
        'th.credits'        => ['Credits', 'Kredity'],
        'th.amount'         => ['Amount', 'Částka'],
        'th.status'         => ['Status', 'Stav'],
        'th.change'         => ['Change', 'Změna'],
        'th.balance'        => ['Balance', 'Zůstatek'],
        'th.reason'         => ['Reason', 'Důvod'],

        // --- order states ---
        'state.pending'     => ['Awaiting payment', 'Čeká na platbu'],
        'state.paid'        => ['Paid', 'Zaplaceno'],
        'state.cancelled'   => ['Cancelled', 'Zrušeno'],
        'state.complete'    => ['Complete', 'Vyřízeno'],
        'state.refund'      => ['Refund in progress', 'Probíhá refundace'],
        'state.refunded'    => ['Refunded', 'Refundováno'],
        'state.accepted'    => ['Being processed', 'Zpracovává se'],
        'state.pending.body'=> ['The order is recorded. Credits are added once the payment has been confirmed.',
                                'Objednávka je zaznamenaná. Kredity se připíšou, jakmile platba dorazí.'],
        'state.paid.body'   => ['The payment has been confirmed and the credits are on your account.',
                                'Platba je potvrzená a kredity máš na účtu.'],
        'state.cancel.body' => ['This order was cancelled. Nothing was charged and no credits were added.',
                                'Tahle objednávka byla zrušena. Nic se neúčtovalo a žádné kredity nepřibyly.'],

        // --- ledger reasons ---
        'reason.signup'     => ['Welcome bonus', 'Bonus za registraci'],
        'reason.daily'      => ['Daily credits', 'Denní kredity'],
        'reason.code'       => ['Code redeemed', 'Uplatněný kód'],
        'reason.order'      => ['Purchase', 'Nákup'],
        'reason.export'     => ['Export', 'Export'],
        'reason.admin'      => ['Admin', 'Admin'],
        'reason.anonymised' => ['Account closed', 'Zrušený účet'],
        'reason.sub_set'    => ['Subscription set', 'Předplatné nastaveno'],
        'reason.sub_cancel' => ['Subscription cancelled', 'Předplatné zrušeno'],
        'reason.referral'   => ['Friend referral', 'Doporučení kamaráda'],
        'reason.refund'     => ['Order refund', 'Refundace objednávky'],
        'reason.ledger_repair' => ['Ledger correction', 'Dorovnání účetní knihy'],

        // --- sign in / register ---
        'auth.signin'       => ['Sign in', 'Přihlášení'],
        'auth.signin.lede'  => ['Credits and redeemed codes are tied to your account.',
                                'Kredity a uplatněné kódy patří k tvému účtu.'],
        'auth.email'        => ['Email', 'E-mail'],
        'auth.password'     => ['Password', 'Heslo'],
        'auth.password2'    => ['Repeat password', 'Heslo znovu'],
        'auth.signin.btn'   => ['Sign in', 'Přihlásit se'],
        'auth.create'       => ['Create an account', 'Založit účet'],
        'auth.createadmin'  => ['Create the administrator', 'Vytvořit správce'],
        'auth.register'     => ['Register', 'Registrace'],
        'auth.closed'       => ['Registration closed', 'Registrace je uzavřená'],
        'auth.closedbody'   => ['New accounts are not being accepted at the moment.',
                                'Nové účty se teď nepřijímají.'],
        'auth.back'         => ['Back to the studio', 'Zpět do studia'],
        'auth.haveaccount'  => ['Already have an account?', 'Už máš účet?'],
        'auth.forgot'       => ['Forgotten password?', 'Zapomenuté heslo?'],

        'reset.title'       => ['Forgotten password', 'Zapomenuté heslo'],
        'reset.lede'        => ['Enter the address you signed up with and a link to set a new password will be sent to it.',
                                'Zadej adresu, se kterou ses registroval, a přijde na ni odkaz pro nastavení nového hesla.'],
        'reset.send'        => ['Send the link', 'Poslat odkaz'],
        'reset.sent'        => ['If an account exists for that address, a link is on its way.',
                                'Pokud k té adrese existuje účet, odkaz je na cestě.'],
        'reset.senthint'    => ['Check the spam folder if it does not arrive within a few minutes. The link works for two hours and once only.',
                                'Když do pár minut nedorazí, mrkni do spamu. Odkaz platí dvě hodiny a jen jednou.'],
        'reset.needemail'   => ['Fill in your address.', 'Vyplň svoji adresu.'],
        'reset.nomail'      => ['This site cannot send mail at the moment, so a reset link cannot be sent. Ask the administrator.',
                                'Web teď neumí odesílat e-maily, odkaz proto nelze poslat. Napiš správci.'],
        'reset.badlink'     => ['That link no longer works', 'Odkaz už neplatí'],
        'reset.badlinkbody' => ['Reset links last two hours and can be used once. Ask for a new one.',
                                'Odkaz na obnovu platí dvě hodiny a jde použít jednou. Nech si poslat nový.'],
        'reset.again'       => ['Send a new link', 'Poslat nový odkaz'],
        'reset.settitle'    => ['Set a new password', 'Nastavení nového hesla'],
        'reset.newpass'     => ['New password', 'Nové heslo'],
        'reset.repeat'      => ['Repeat the password', 'Heslo znovu'],
        'reset.rule'        => ['At least eight characters, with an upper-case letter, a lower-case letter and a digit.',
                                'Nejméně osm znaků, velké písmeno, malé písmeno a číslice.'],
        'reset.save'        => ['Save the password', 'Uložit heslo'],
        'reset.tooshort'    => ['The password must be at least eight characters.',
                                'Heslo musí mít nejméně osm znaků.'],
        /*
         * The password rules, one message per rule. Auth::passwordProblem()
         * decides which one applies, and every form that sets a password -
         * sign-up, reset, the account page, the installer - shows the answer
         * it gives, so the rules cannot drift apart between them again.
         */
        'pw.short'          => ['The password must be at least eight characters.',
                                'Heslo musí mít nejméně osm znaků.'],
        'pw.nolower'        => ['The password needs a lower-case letter.',
                                'Heslo musí obsahovat malé písmeno.'],
        'pw.noupper'        => ['The password needs an upper-case letter.',
                                'Heslo musí obsahovat velké písmeno.'],
        'pw.nodigit'        => ['The password needs a digit.',
                                'Heslo musí obsahovat číslici.'],
        'reset.mismatch'    => ['The two passwords do not match.', 'Hesla se neshodují.'],
        'reset.samepass'    => ['Choose a different password from the one you were sent.',
                                'Zvol jiné heslo, než jaké ti přišlo.'],
        'reset.done'        => ['Password changed.', 'Heslo změněno.'],
        'reset.forced'      => ['This password was issued by an administrator. Choose your own before carrying on.',
                                'Tohle heslo ti přidělil správce. Než budeš pokračovat, zvol si vlastní.'],
        'busy.reset'        => ['Working…', 'Pracuji…'],
        'auth.firstadmin'   => ['This installation has no administrator yet. The first account created becomes one.',
                                'Tahle instalace zatím nemá správce. Prvním vytvořeným účtem se stane.'],
        'ord.cancelledon'   => ['Cancelled', 'Zrušeno'],

        // --- order status page ---
        'ord.title'         => ['Order', 'Objednávka'],
        'ord.notfound'      => ['This order could not be found. Check the link from your email, or sign in to see the orders on your account.',
                                'Tuhle objednávku se nepodařilo najít. Zkontroluj odkaz z e-mailu, nebo se přihlas a podívej se na objednávky ve svém účtu.'],
        'ord.signintopay'   => ['Sign in to this account to pay for the order.',
                                'Pro zaplacení se přihlas k tomuto účtu.'],
        'ord.back'          => ['Back to your account', 'Zpět na účet'],
        'ord.vs'            => ['Variable symbol', 'Variabilní symbol'],
        'ord.howtopay'      => ['How to pay', 'Jak zaplatit'],
        'ord.instructions'  => ['Orders are confirmed by hand. Send the amount with the variable symbol shown on the order and the credits are added once the payment arrives.',
                                'Objednávky potvrzuji ručně. Pošli částku s variabilním symbolem uvedeným u objednávky a kredity se připíšou, jakmile platba dorazí.'],
        'ord.created'       => ['Order created', 'Objednávka vytvořena'],
        'ord.created.lede'  => ['Nothing has been charged yet. Pay whenever suits you - the order waits.',
                                'Zatím se nic neúčtovalo. Zaplatit můžeš, kdy se ti hodí, objednávka počká.'],
        'ord.close'         => ['Close', 'Zavřít'],
        'ord.emailed'       => ['The same details are in your email.', 'Stejné údaje máš i v e-mailu.'],

        // Shown on the dimmed overlay while the server works.
        'busy.order'        => ['Placing your order…', 'Zpracovávám objednávku…'],
        'busy.redeem'       => ['Checking the code…', 'Ověřuji kód…'],
        'busy.password'     => ['Changing your password…', 'Měním heslo…'],
        'busy.cancel'       => ['Cancelling the order…', 'Ruším objednávku…'],
        'busy.currency'     => ['Switching currency…', 'Přepínám měnu…'],
        'busy.signin'       => ['Signing you in…', 'Přihlašuji…'],
        'busy.register'     => ['Creating your account…', 'Zakládám účet…'],
        'ord.pay'           => ['Pay', 'Zaplatit'],

        // --- verification ---
        'ver.title'         => ['Email confirmed', 'E-mail potvrzen'],
        'ver.failed'        => ['Confirmation failed', 'Potvrzení se nezdařilo'],
        'ver.balance'       => ['Your balance is %s credits.', 'Tvůj zůstatek je %s kreditů.'],
        'ver.continue'      => ['Continue to the studio', 'Pokračovat do studia'],
    ];

    /**
     * 'cs' or 'en'. A signed-in reader's own account language wins, so the
     * whole interface follows their choice - not only their e-mails. Guests
     * (and accounts with no choice) fall back to the studio cookie, then the
     * site default.
     */
    public static function current(): string
    {
        if (self::$lang !== null) {
            return self::$lang;
        }

        $u = Auth::user();
        $own = $u['lang'] ?? null;
        if ($own === 'cs' || $own === 'en') {
            return self::$lang = $own;
        }

        $raw = (string) ($_COOKIE['h3d_lang'] ?? Settings::get('default_lang'));

        return self::$lang = $raw === 'cs' ? 'cs' : 'en';
    }

    /** Forces a language, for tests. */
    public static function set(string $lang): void
    {
        self::$lang = $lang === 'cs' ? 'cs' : 'en';
    }

    /**
     * Looks up a key. Extra arguments are substituted with sprintf, so a
     * sentence can put a number where the language needs it rather than
     * where English happens to.
     */
    public static function get(string $key, ...$args): string
    {
        $row = self::STRINGS[$key] ?? null;

        if ($row === null) {
            // A missing key is a bug, but showing the key beats showing
            // nothing: it is obvious on screen and searchable in the source.
            return $key;
        }

        $text = self::current() === 'cs' ? $row[1] : $row[0];

        return $args ? vsprintf($text, $args) : $text;
    }

    /**
     * Payment instructions: whatever the admin typed, or the built-in
     * wording in the reader's language when they left it empty.
     */
    public static function instructions(): string
    {
        $own = trim(Settings::get('purchase_instructions'));

        return $own !== '' ? $own : self::get('ord.instructions');
    }

    /** Order status in the reader's language. */
    public static function status(string $status): string
    {
        return self::get('state.' . $status);
    }

    /** Ledger reason in the reader's language, falling back to the raw code. */
    public static function reason(string $reason): string
    {
        $key = 'reason.' . str_replace('_bulk', '', $reason);

        return isset(self::STRINGS[$key]) ? self::get($key) : $reason;
    }
}

/** Shorthand, since these templates are mostly text. */
function __(string $key, ...$args): string
{
    return Lang::get($key, ...$args);
}
