<?php
declare(strict_types=1);

if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

/**
 * Every tunable lives here with a default, so a fresh install works before
 * anyone opens the admin panel and an unknown key can never silently
 * become an empty string.
 */
final class Settings
{
    private static ?array $cache = null;

    public const DEFAULTS = [
        // Identity
        'site_name'              => 'Honza3D Drawer Organizer Studio',
        // Where the brand in the header points, and what it says. Separate
        // from site_name so the visible label can be short while the site
        // keeps its full title in page titles and emails.
        'brand_label'            => 'Honza3D',
        'brand_url'              => '',
        // Base URL used for generated share links. Empty = detect the current app URL automatically.
        'share_base_url'         => '',
        'default_lang'           => 'en',
        // Global H3D Light palette. The color keys are centralized so new UI
        // can consume the same tokens.
        'palette_bg'             => '#eef1f5',
        'palette_panel'          => '#ffffff',
        'palette_panel2'         => '#f5f7fa',
        'palette_line'           => '#d5dce5',
        'palette_text'           => '#20242a',
        'palette_muted'          => '#68707a',
        'palette_accent'         => '#1769d1',
        'palette_accent_dark'    => '#0f57b4',
        'palette_accent_text'    => '#ffffff',
        'palette_button'         => '#e8eef5',
        'palette_button_text'    => '#20242a',
        'palette_button_hover'   => '#dbe5ef',
        'palette_good'           => '#168b57',
        'palette_good_text'      => '#ffffff',
        'palette_good_hover'     => '#117247',
        'palette_bad'            => '#d64555',
        'palette_canvas'         => '#eef1f5',
        'palette_grid'           => '#b5c1cf',

        // Accounts
        'registration_open'      => '1',

        /*
         * Display names nobody may take.
         *
         * Not a security boundary - the panel is behind a password and a
         * role, and no permission has ever been decided by a name. This is
         * about the support chat, where a message signed "Správce" is read
         * as coming from the shop. Compared case-insensitively and with
         * accents folded, so Spravce and SPRÁVCE are the same word.
         */
        'reserved_names'         => 'admin,administrator,administrátor,správce,spravce,moderator,moderátor,support,podpora,helpdesk,guest,host,anonym,system,systém,root,operator,operátor,h3d,honza3d,info,kontakt,noreply',
        // Bonus released when the email address is confirmed, not at
        // registration: an unconfirmed address should not be able to mint it
        // by signing up repeatedly. Either credits or days of unlimited
        // exports (a "first week free") - set whichever fits, or both.
        'signup_bonus'           => '30',
        'signup_bonus_days'      => '0',
        // Referral program: both sides get the bonus once the invited account
        // verifies its e-mail. Stored in tenths like every credit amount.
        // The cap limits how many rewards one account can collect.
        'referral_enabled'       => '1',
        'referral_bonus'         => '100',
        'referral_max'           => '20',
        'require_verify'         => '1',
        // Proof-of-work check on the registration form.
        'captcha_enabled'        => '1',
        'require_login'          => '0',
        // Public traffic can be paused during a deploy or database repair;
        // administrators keep access to the panel to turn it back on.
        'maintenance_mode'       => '0',

        // Export throttling. A window length in seconds plus how many free
        // exports fit inside it, so "3 per 30 minutes" is as expressible as
        // "1 per hour". A window of 0 disables throttling entirely.
        'cooldown_guest'         => '3600',
        'cooldown_user'          => '3600',
        'free_per_window_guest'  => '1',
        'free_per_window_user'   => '1',
        'guest_export_enabled'   => '1',

        // Free hosting parks a fixed advertising strip over the page. These
        // reserve room for it so nothing of ours ends up underneath, in
        // pixels, top and bottom independently.
        'page_margin_top'        => '0',
        'page_margin_bottom'     => '0',

        // Quota reset marker. "Reset kvót" in the panel does not delete the
        // export log any more - that would take the statistics and the
        // "who exported what" history with it. Instead it stamps the current
        // time here, and the window only counts exports made after it, so
        // everyone's free allowance is handed back while the records stay.
        'quota_reset_at'         => '0',

        // Credits let a signed-in user skip the wait.
        // Free exports can be switched off entirely, so every export costs
        // credits. Separate from the window: a window of 0 means unlimited
        // free, which is the opposite intent and used to be the only way to
        // express either.
        'free_enabled_user'      => '1',
        'free_enabled_guest'     => '1',

        'credit_cost'            => '10',
        'credits_enabled'        => '1',

        // Ask before a credit is spent, so the free allowance running out is
        // never a silent surprise.
        'confirm_credit_spend'   => '1',

        // Daily top-up, applied on first visit after local midnight rather
        // than by a cron job. The cap stops an idle account accumulating
        // credits forever; 0 means no cap.
        'daily_credits'          => '0',
        'daily_credits_cap'      => '100',
        'timezone'               => 'Europe/Prague',

        // Model limits, enforced server side on every request.
        'max_boxes'              => '200',
        'max_cells'              => '15',

        /*
         * The range of every studio parameter, in millimetres.
         *
         * One place, three consumers: the fields and sliders are rendered
         * from it, the client clamps against it, and Layout::parse() refuses
         * anything outside it. Before this the same numbers were written
         * into the HTML, into Layout::LIMITS and into the client, and moving
         * one of them changed what the studio offered without changing what
         * the server accepted (or the other way round).
         */
        'studio_dw_max'          => '1000',
        'studio_dd_max'          => '1000',
        'studio_dh_max'          => '500',
        'studio_wall_min'        => '2',
        'studio_wall_max'        => '10',
        'studio_bottom_min'      => '2',
        'studio_bottom_max'      => '10',
        'studio_radius_max'      => '20',
        'studio_gap_max'         => '2',
        'studio_outer_max'       => '20',
        'studio_print_max'       => '500',

        /*
         * "What's new", shown once in the studio to somebody who has been
         * here before.
         *
         * The tag is what decides "once": it is stored in the visitor's
         * browser when they close the card, so changing the tag is how the
         * same person is shown the news again. Leaving the text empty shows
         * nothing at all.
         */
        'news_enabled'           => '1',
        'news_version'           => '1',
        'news_tag'               => '2.0.0.5beta',
        'news_title'             => 'Co je nového',
        'news_title_en'          => 'What is new',
        // Written here rather than left empty, so a site that has never been
        // near this admin page still tells people what changed.
        'news_body'              => "# Příčky v boxu\n"
            . "Nové tlačítko se stěnou v liště pod kresbou: klikáním na čáru mezi buňkami rozdělíš box na přihrádky.\n"
            . "Příčka je jedna stěna v nastavené tloušťce a tiskne se jako součást boxu - dělený box je jeden kus, ne čtyři.\n"
            . "Příčka navazuje na vnější stěnu bez schodu.\n"
            . "Příčky mohou být použity i při zaoblených vnějších rozích; vnitřní rohy příček zůstávají ostré.\n"
            . "Příčka, která by končila v prázdnu, se ohlásí v Přehledu a tlačítko Opravit ji odebere.\n"
            . "\n"
            . "# Kresba\n"
            . "Box je nakreslený i se svou stěnou, v tloušťce, ve které se vytiskne.\n"
            . "Každý box má vlastní barvu a dva sousední nikdy stejnou; vybraný box je barevný, ostatní ustoupí do šeda.\n"
            . "Kliknutí vedle boxů výběr zruší, stejně jako křížek v bublině u vybraného boxu.\n"
            . "\n"
            . "# Ostatní\n"
            . "Do profilu se ukládají i příčky a stav nástroje stěn, takže se vrátíš přesně tam, kde jsi skončil.\n"
            . "Před stažením 3MF nebo STL vyskočí připomínka, ať si model projdeš ve sliceru. Jde vypnout jedním zaškrtnutím.\n"
            . "Nepřihlášený začíná s prázdnou mřížkou místo šestnácti boxů, které musel nejdřív smazat.\n"
            . "V patičce přibyly Podmínky použití.\n",
        'news_body_en'           => "# Dividers inside a box\n"
            . "A new wall button in the strip under the drawing: click the line between two cells to split a box into compartments.\n"
            . "A divider is one wall thick, exactly the thickness you set, and it is printed as part of the box - a divided box is one piece, not four.\n"
            . "The divider meets the outer wall without a step.\n"
            . "Dividers use square internal corners while the outer corners can remain rounded.\n"
            . "A divider that would stop in mid-air is reported in the Overview, and Fix removes it.\n"
            . "\n"
            . "# The drawing\n"
            . "A box is drawn with its wall, at the thickness it will be printed at.\n"
            . "Every box has its own colour and no two neighbours share one; the selected box keeps its colour while the rest step back into grey.\n"
            . "Clicking beside the boxes clears the selection, as does the cross in the bubble by the selected box.\n"
            . "\n"
            . "# Everything else\n"
            . "Your profile now keeps the dividers and the state of the wall tool, so you come back to exactly where you left off.\n"
            . "Downloading a 3MF or an STL first shows a reminder to check the model in your slicer. One tick turns it off.\n"
            . "Without an account the studio opens on an empty grid instead of sixteen boxes you had to delete first.\n"
            . "Terms of use have been added to the footer.\n",

        // Purchases
        /*
         * The portal is free. Paid packages are off; the machinery stays in
         * place so switching it back on is one checkbox, and so the orders
         * anybody already made remain readable rather than vanishing.
         *
         * Zero-price packages (promo offers), redeem codes and referral
         * rewards are deliberately NOT tied to this: they cost nobody
         * anything and are the whole point of the free version.
         */
        'purchase_enabled'       => '0',
        'currency'               => 'CZK',

        // How long the customer is told to expect before an order is
        // confirmed. Payments are matched by hand here, so promising
        // anything faster than the operator actually manages is worse than
        // saying nothing.
        'order_process_days'     => '3',

        // Bug reports and ideas from signed-in customers, with voting. The
        // whole section - the public page and the panel tab - hangs off this
        // switch, so it can be put away without deleting what people wrote.
        'bugs_enabled'           => '1',

        // Google Analytics measurement id ("G-XXXXXXX"), or empty for no
        // analytics at all. Nothing loads without it, and with it nothing
        // loads until the visitor agrees - see lib/Consent.php. It is the
        // only thing on this site that sends anything to anybody.
        'analytics_id'           => '',

        // Seller block printed on the payment receipt / invoice. An empty
        // seller falls back to the site name; invoice_info is free text
        // (address, IČO/DIČ, "neplátce DPH", contact) shown as written.
        'invoice_seller'         => '',
        'invoice_info'           => '',

        // PayPal. Normally just the paypal.me user name; the full link,
        // the amount and the currency are assembled from it. paypal_link
        // stays as an override for anyone using a hosted button or another
        // gateway entirely.
        'paypal_user'            => '',
        'paypal_link'            => '',
        'paypal_note'            => '',

        // Site-wide discount on every package. Kept as the legacy single
        // discount; the scheduled promotions below supersede it once any
        // exist, and a one-time migration folds a leftover discount into the
        // first promotion.
        'discount_percent'       => '0',
        'discount_label'         => '',
        'discount_from'          => '',
        'discount_until'         => '',

        // Scheduled promotions, as a JSON list of
        // {label, percent (tenths), from, until}. They may not overlap in
        // time, so at most one is ever active, and the active one discounts
        // every package. Empty means "fall back to the legacy discount above".
        'promotions_json'        => '',

        // Free-form links shown in the footer and on the account page.
        // One per line: "Label | https://example.com".
        'custom_links'           => '',

        // Outgoing mail. Everything sent to users is in English.
        'smtp_enabled'           => '0',
        'smtp_host'              => '',
        'smtp_port'              => '587',
        'smtp_security'          => 'tls',   // none | tls | ssl
        'smtp_user'              => '',
        'smtp_pass'              => '',
        'smtp_from'              => '',
        'smtp_from_name'         => 'Honza3D',
        'smtp_timeout'           => '12',
        // Editable list of ready-made servers; empty means "use the ones
        // this version ships with" (see SmtpPresets).
        'smtp_presets'           => '',

        // Copy of every customer notification, so problems land in your own
        // inbox instead of only in a log.
        'admin_email'            => '',
        'notify_admin'           => '1',
        // Empty on purpose: an empty value falls back to wording the reader
        // gets in their own language. Anything typed here is shown as
        // written, to everyone, because there is only one box for it.
        'purchase_instructions'  => '',

        // Days an accepted order may wait for payment before it cancels
        // itself. 0 switches the expiry off.
        'accept_expiry_days'     => '5',
    ];

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $rows = Db::pdo()->query('SELECT key, value FROM settings')->fetchAll();
        $stored = [];
        foreach ($rows as $r) {
            $stored[$r['key']] = $r['value'];
        }

        self::$cache = array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));

        // Legacy installations may still contain the removed Ocean preset.
        // If all palette values exactly match that old preset, migrate them to
        // H3D Light. Custom palettes are intentionally left untouched.
        $legacyOcean = [
            'palette_bg'=>'#0B1220','palette_panel'=>'#111C2D','palette_panel2'=>'#16263A',
            'palette_line'=>'#2A4058','palette_text'=>'#E8F1F7','palette_muted'=>'#9FB3C5',
            'palette_accent'=>'#43C6C6','palette_accent_dark'=>'#239E9E',
            'palette_accent_text'=>'#062022','palette_button'=>'#1A2D42',
            'palette_button_text'=>'#E8F1F7','palette_button_hover'=>'#24425D',
            'palette_good'=>'#35C98A','palette_good_text'=>'#062016',
            'palette_good_hover'=>'#25A972','palette_bad'=>'#FF6574',
            'palette_canvas'=>'#0D1827','palette_grid'=>'#344B63'
        ];
        $isLegacyOcean = true;
        foreach ($legacyOcean as $k => $v) {
            if (strcasecmp((string) ($stored[$k] ?? ''), $v) !== 0) {
                $isLegacyOcean = false;
                break;
            }
        }
        if ($isLegacyOcean) {
            foreach (array_keys($legacyOcean) as $k) {
                self::$cache[$k] = self::DEFAULTS[$k];
            }
        }

        return self::$cache;
    }

    public static function get(string $key, ?string $fallback = null): string
    {
        $all = self::all();
        return $all[$key] ?? $fallback ?? (self::DEFAULTS[$key] ?? '');
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }

    public static function bool(string $key): bool
    {
        return self::get($key) === '1';
    }

    /** Drops the cache, e.g. after the settings table has been emptied. */
    public static function forget(): void
    {
        self::$cache = null;
    }

    /** Writes only keys that exist in DEFAULTS. */
    public static function set(array $values): void
    {
        $pdo = Db::pdo();

        // The daily allowance is time-sensitive. Keep its effective value in
        // a small history table so a later login can reconstruct missing days
        // using the rate that was actually configured then.
        $dailyHistoryReady = false;
        $oldDaily = null;
        if (array_key_exists('daily_credits', $values)) {
            try {
                $oldDaily = self::get('daily_credits');
                $dailyHistoryReady = (bool) $pdo->query(
                    "SELECT 1 FROM sqlite_master WHERE type='table' AND name='daily_credit_rates'"
                )->fetchColumn();
            } catch (Throwable $e) {
                $dailyHistoryReady = false;
            }
        }

        $st = $pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?)
                             ON CONFLICT(key) DO UPDATE SET value = excluded.value');
        foreach ($values as $k => $v) {
            if (!array_key_exists($k, self::DEFAULTS)) {
                continue;
            }
            $st->execute([$k, (string) $v]);
        }

        if ($dailyHistoryReady) {
            $newDaily = (string) $values['daily_credits'];
            if ((string) $oldDaily !== $newDaily) {
                $pdo->prepare(
                    'INSERT INTO daily_credit_rates (credits, effective_at) VALUES (?, ?)'
                )->execute([Cred::parse($newDaily), time()]);
            }
        }

        self::$cache = null;
    }
}
