<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/bootstrap.php';
require_once __DIR__ . '/../lib/View.php';
require_once __DIR__ . '/../lib/SelfCheck.php';

/*
 * A manager is let in, but only as far as the orders they are here to
 * process. The tab they asked for does not decide whether they get in - it
 * decides where they land, so arriving at /admin/ takes them to the orders
 * rather than to a refusal.
 */
$admin      = Auth::requirePanel(Auth::canManageOrders(Auth::user()));
$ordersOnly = !Auth::isAdmin($admin);

/*
 * The three helpers below are guarded, like toggle() further down: the markup
 * test renders this page many times in one process, and a bare declaration
 * makes the second render die with "cannot redeclare".
 */
if (!function_exists('admin_log_write')):
/**
 * Records one administrator action.
 *
 * Called from a single place at the end of the POST handler, so an action
 * added later cannot forget to log itself. Nothing here may throw: a failed
 * log entry must never take down the action it was recording.
 */
function admin_log_write(array $admin, string $action, array $post, string $ok, string $error): void
{
    if ($action === '') {
        return;
    }
    try {
        // Whatever the action was aimed at, in the order the fields tend to
        // appear. Values are ids, so they are safe to store as they are.
        $target = '';
        foreach (['user_id', 'order_id', 'id', 'code_id', 'use_id', 'ledger_id', 'package_id'] as $k) {
            if (isset($post[$k]) && (string) $post[$k] !== '') {
                $target = $k . '=' . (int) $post[$k];
                break;
            }
        }

        $detail = trim($ok !== '' ? $ok : $error);
        if (mb_strlen($detail) > 400) {
            $detail = mb_substr($detail, 0, 400) . '…';
        }

        Db::pdo()->prepare(
            'INSERT INTO admin_log (admin_id, admin_name, action, target, detail, ok, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            (int) ($admin['id'] ?? 0),
            (string) ($admin['nickname'] ?? $admin['email'] ?? ''),
            $action,
            $target,
            $detail,
            $error === '' ? 1 : 0,
            time(),
        ]);
    } catch (Throwable $e) {
        // Deliberately silent - see the note above.
    }
}
endif;

if (!function_exists('order_status_cs')):
/** Czech status label for an order row. */
function order_status_cs(string $status): string
{
    return match ($status) {
        Orders::PENDING   => 'čeká',
        Orders::ACCEPTED  => 'zpracovává se',
        Orders::PAID      => 'zaplaceno',
        Orders::CANCELLED => 'zrušeno',
        Orders::REFUND    => 'refund - vrátit peníze',
        Orders::REFUNDED  => 'refundováno',
        default           => $status,
    };
}
endif;

if (!function_exists('order_info_btn')):
/**
 * The ⓘ button next to an order: its whole story (dates, amounts, what a
 * refund returned and where) lives in a popup instead of a wall of text
 * squeezed into the table row. admin.js renders the dialog from the JSON.
 */
function order_info_btn(array $o): string
{
    $rows   = [];
    $rows[] = ['Stav', order_status_cs((string) $o['status'])];
    $rows[] = ['Vytvořeno', when((int) $o['created_at'])];
    $rows[] = ['Obsah', (int) ($o['sub_days'] ?? 0) > 0
        ? Orders::durationLabel((int) $o['sub_days'], 'cs') . ' neomezeně'
            . ((int) $o['credits'] > 0 ? ' + ' . Cred::fmtCs((int) $o['credits']) . ' kreditů' : '')
        : Cred::fmtCs((int) $o['credits']) . ' kreditů'];
    $rows[] = ['Cena', money((int) $o['price_cents'], $o['currency'] ?? null)];

    if (!empty($o['paid_at'])) {
        $rows[] = ['Zaplaceno', when((int) $o['paid_at'])];
    }
    // A time package starts when the customer says so, so "paid" and
    // "running" are two different moments and both are worth seeing.
    if ((int) ($o['sub_days'] ?? 0) > 0 && (string) $o['status'] === Orders::PAID) {
        $rows[] = ['Aktivace', empty($o['activated_at'])
            ? 'čeká na zákazníka'
            : when((int) $o['activated_at'])];
    }
    if (!empty($o['cancelled_at'])) {
        $rows[] = ['Zrušeno', when((int) $o['cancelled_at'])];
        $cancelReason = trim((string) ($o['admin_note'] ?? ''));
        if ($cancelReason !== '') {
            $rows[] = ['Důvod zrušení', $cancelReason];
        } else {
            $rows[] = ['Důvod zrušení', 'Důvod nebyl při zrušení zadán.'];
        }
    }
    if (($o['refund_cents'] ?? null) !== null
        && in_array($o['status'], [Orders::REFUND, Orders::REFUNDED], true)) {
        $rows[] = [
            $o['status'] === Orders::REFUNDED ? 'Vráceno' : 'K vrácení',
            money((int) $o['refund_cents'], $o['currency'] ?? null),
        ];
    }
    if (!empty($o['refunded_at'])) {
        $rows[] = ['Refundováno', when((int) $o['refunded_at'])];
    }
    if (trim((string) ($o['refund_dest'] ?? '')) !== '') {
        $rows[] = ['Vráceno na', (string) $o['refund_dest']];
    }
    if (trim((string) ($o['admin_note'] ?? '')) !== '') {
        $rows[] = ['Průběh', (string) $o['admin_note']];
    }

    $data = ['title' => 'Objednávka ' . (string) $o['reference'], 'rows' => $rows];

    return '<button type="button" class="oinfo-btn" title="Detail objednávky" aria-label="Detail objednávky"'
         . ' data-order-info="' . e(json_encode($data, JSON_UNESCAPED_UNICODE) ?: '{}') . '">i</button>';
}
endif;

if (!function_exists('order_history')):
/**
 * Everything that ever happened to one order, newest last.
 *
 * Two sources woven together: the order's own timestamps (created, accepted,
 * paid…) and the administrator log, which knows WHO did it. Neither alone
 * answers "why is this order in this state", which is the question somebody
 * opening it three weeks later actually has.
 *
 * @return array<int, array{at:int, what:string, who:string, detail:string}>
 */
function order_history(array $o): array
{
    $out = [];

    $add = static function (?int $at, string $what, string $who = '', string $detail = '') use (&$out): void {
        if ($at) {
            $out[] = ['at' => $at, 'what' => $what, 'who' => $who, 'detail' => $detail];
        }
    };

    $add((int) $o['created_at'], 'Objednávka vytvořena', 'zákazník',
        money((int) $o['price_cents'], $o['currency'] ?? null));
    $add(isset($o['accepted_at']) ? (int) $o['accepted_at'] : null, 'Přijato ke zpracování');
    $add(isset($o['paid_at']) ? (int) $o['paid_at'] : null, 'Označeno jako zaplacené');
    $add(isset($o['activated_at']) ? (int) $o['activated_at'] : null, 'Balíček aktivován', 'zákazník');
    $cancelReason = trim((string) ($o['admin_note'] ?? ''));
    $add(
        isset($o['cancelled_at']) ? (int) $o['cancelled_at'] : null,
        'Zrušeno',
        '',
        $cancelReason !== '' ? 'Důvod: ' . $cancelReason : 'Důvod nebyl zadán.'
    );
    $add(isset($o['refunded_at']) ? (int) $o['refunded_at'] : null, 'Refundováno',
        '', (string) ($o['refund_dest'] ?? ''));

    try {
        $st = Db::pdo()->prepare(
            'SELECT admin_name, action, detail, ok, created_at FROM admin_log
             WHERE target = ? ORDER BY id'
        );
        $st->execute(['order_id=' . (int) $o['id']]);
        foreach ($st->fetchAll() as $r) {
            $out[] = [
                'at'     => (int) $r['created_at'],
                'what'   => (string) $r['action'],
                'who'    => (string) $r['admin_name'],
                'detail' => ((int) $r['ok'] === 1 ? '' : 'neúspěch: ') . (string) $r['detail'],
            ];
        }
    } catch (Throwable $e) {
        // The log is a convenience; the dialog opens without it.
    }

    usort($out, static fn($a, $b) => $a['at'] <=> $b['at']);

    return $out;
}
endif;

/** Ready-made reasons for the note field, so the usual answer is one click. */
const ORDER_REPLIES = [
    'Platba dorazila, díky!',
    'Platbu jsem zatím nenašel - zkontroluj prosím variabilní symbol.',
    'Přišla jiná částka, než je na objednávce. Ozvi se mi prosím.',
    'Objednávka byla zrušena na žádost zákazníka.',
    'Zrušeno kvůli nezaplacení ve lhůtě.',
    'Duplicitní objednávka, ponechána jen jedna.',
    'Peníze vráceny zpět na účet, ze kterého platba přišla.',
];

$wantedTab = (string) ($_GET['tab'] ?? ($ordersOnly ? 'orders' : 'overview'));

if ($ordersOnly && $wantedTab !== 'orders') {
    header('Location: ?tab=orders');
    exit;
}
$pdo   = Db::pdo();

// Orders are the heart of the panel, so an admin lands there rather than on
// the overview - it is the screen actually worked from all day.
/**
 * Every tab there is, in the order the menu shows them.
 *
 * The single list the router, the menu and the check script all read, so
 * adding a tab is adding a file plus one line here - and a value from the
 * query string can never name a file that is not on it.
 */
const ADMIN_TABS = ['overview','settings','studio','accounts','seller','look','exports',
                    'payments','codes','users','billing','promotions','invoices','orders',
                    'mail','messages','adminlog','reset','features','activity'];

$tab = (string) ($_GET['tab'] ?? 'overview');
if (!in_array($tab, ADMIN_TABS, true)) { $tab = 'overview'; }

// Message carried over from the redirect that followed the last POST.
[$ok, $error] = take_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::checkCsrf();
    $action = (string) ($_POST['action'] ?? '');

    // A manager can only act on orders. Everything else is refused here
    // rather than relying on the tab being hidden, since a hidden form is
    // not a closed door.
    if ($ordersOnly && !in_array($action, ['order_status', 'resend_order_mail'], true)) {
        http_response_code(403);
        exit('Forbidden');
    }

    try {
        switch ($action) {

            case 'settings':
                $baseBefore = Money::base();
                $values = [];

                // Each settings form only posts its own fields, so a
                // checkbox absent from THIS form must not be reset to 0.
                // Only flags actually present on the submitted form count.
                $formFlags = array_filter(
                    array_map('trim', explode(',', (string) ($_POST['_flags'] ?? '')))
                );

                // Textareas the form owns. An emptied textarea posts an
                // empty string, which is indistinguishable from "not on this
                // form" — so the form has to say which ones are its own.
                // Without this, saving the mail tab wiped the payment
                // instructions.
                $formTexts = array_filter(
                    array_map('trim', explode(',', (string) ($_POST['_texts'] ?? '')))
                );

                foreach (Settings::DEFAULTS as $key => $_) {
                    if (in_array($key, $formTexts, true)) {
                        $values[$key] = (string) ($_POST[$key] ?? '');
                    } elseif (isset($_POST[$key])) {
                        $values[$key] = (string) $_POST[$key];
                    }
                }
                // Unchecked checkboxes are simply absent from the POST body,
                // so they have to be written back explicitly as "0".
                foreach (['registration_open','require_login','guest_export_enabled',
                          'credits_enabled','purchase_enabled','robots',
                          'confirm_credit_spend','smtp_enabled','require_verify','referral_enabled',
                          'captcha_enabled','free_enabled_user','free_enabled_guest',
                          'bugs_enabled','news_enabled'] as $flag) {
                    // A form declares which flags it owns via a hidden marker,
                    // so saving the mail tab cannot silently clear the flags
                    // that only exist on the settings tab.
                    if (in_array($flag, $formFlags, true)) {
                        $values[$flag] = isset($_POST[$flag]) ? '1' : '0';
                    }
                }
                foreach (['cooldown_guest','cooldown_user'] as $n) {
                    if (isset($values[$n])) {
                        $values[$n] = (string) (max(0, (int) $values[$n]) * 60);
                    }
                }
                foreach (['free_per_window_guest',
                          'free_per_window_user','max_boxes','max_cells',
                          'page_margin_top','page_margin_bottom',
                          'accept_expiry_days','signup_bonus_days','referral_max',
                          'order_process_days'] as $n) {
                    if (isset($values[$n])) {
                        $values[$n] = (string) max(0, (int) $values[$n]);
                    }
                }

                /*
                 * Studio ranges are millimetres with decimals, kept as
                 * plain numbers.
                 *
                 * A maximum below its minimum would leave a field that
                 * refuses every value, so the pairs are put back in order
                 * here rather than trusting the form: the browser only
                 * knows about one input at a time.
                 */
                // Palette colors are stored only as safe #RRGGBB tokens.
                $paletteKeys = ['palette_bg','palette_panel','palette_panel2','palette_line',
                    'palette_text','palette_muted','palette_accent','palette_accent_dark',
                    'palette_accent_text','palette_button','palette_button_text','palette_button_hover',
                    'palette_good','palette_good_text','palette_good_hover','palette_bad',
                    'palette_canvas','palette_grid'];
                foreach ($paletteKeys as $pk) {
                    if (isset($values[$pk])) {
                        $v = strtoupper(trim((string) $values[$pk]));
                        $values[$pk] = preg_match('/^#[0-9A-F]{6}$/', $v) ? $v : Settings::get($pk);
                    }
                }
                foreach (['studio_dw_max','studio_dd_max','studio_dh_max',
                          'studio_wall_min','studio_wall_max',
                          'studio_bottom_min','studio_bottom_max',
                          'studio_radius_max','studio_gap_max',
                          'studio_outer_max','studio_print_max'] as $n) {
                    if (isset($values[$n])) {
                        $values[$n] = (string) max(0, min(5000, (float) str_replace(',', '.', $values[$n])));
                    }
                }
                foreach ([['studio_wall_min', 'studio_wall_max'],
                          ['studio_bottom_min', 'studio_bottom_max']] as [$lo, $hi]) {
                    if (isset($values[$lo], $values[$hi]) && (float) $values[$lo] > (float) $values[$hi]) {
                        [$values[$lo], $values[$hi]] = [$values[$hi], $values[$lo]];
                    }
                }

                // Credit amounts and the discount are entered with one
                // decimal and stored as tenths.
                foreach (['credit_cost','signup_bonus','daily_credits','daily_credits_cap','referral_bonus'] as $n) {
                    if (isset($values[$n])) {
                        $values[$n] = (string) max(0, Cred::parse($values[$n]));
                    }
                }
                if (isset($values['discount_percent'])) {
                    $values['discount_percent'] = (string) Cred::parsePercent($values['discount_percent']);
                }

                // An invalid timezone would break the daily grant silently,
                // so it is checked here rather than at midnight.
                if (isset($values['timezone'])) {
                    try {
                        new DateTimeZone($values['timezone']);
                    } catch (Throwable) {
                        unset($values['timezone']);
                        $error = 'Neznámé časové pásmo, ponechal jsem původní.';
                    }
                }
                if (!empty($_POST['_news_form'])) {
                    $values['news_version'] = (string) (Settings::int('news_version') + 1);
                }
                Settings::set($values);
                // Every other rate was measured against the old base, so
                // changing it silently invalidates all of them unless they
                // are re-expressed.
                if (isset($values['currency']) && Money::base() !== $baseBefore) {
                    Money::rebase($baseBefore, Money::base());
                    $ok = 'Nastavení uloženo. Kurzy byly přepočítány na novou základní měnu '
                        . Money::base() . ' - zkontroluj je.';
                } else {
                    $ok = 'Nastavení uloženo.';
                }

                Pricing::recordAll();
                break;

            case 'create_code':
                $expiresDays = (int) ($_POST['expires_days'] ?? 0);
                $code = Codes::create(
                    Cred::parse($_POST['credits'] ?? 0),
                    (int) ($_POST['max_uses'] ?? 1),
                    $expiresDays > 0 ? time() + $expiresDays * 86400 : null,
                    trim((string) ($_POST['note'] ?? '')),
                    trim((string) ($_POST['code'] ?? '')),
                    (int) ($_POST['uses_per_account'] ?? 1),
                    max(0, (int) ($_POST['sub_days'] ?? 0))
                );
                $ok = "Kód vytvořen: $code";
                break;

            case 'toggle_code':
                $pdo->prepare('UPDATE codes SET active = 1 - active WHERE id = ?')
                    ->execute([(int) $_POST['id']]);
                $ok = 'Code updated.';
                break;

            case 'delete_code':
                $pdo->prepare('DELETE FROM codes WHERE id = ?')->execute([(int) $_POST['id']]);
                $ok = 'Code deleted.';
                break;

            case 'edit_code':
                $cid     = (int) ($_POST['id'] ?? 0);
                $credits = Cred::parse($_POST['credits'] ?? 0);
                $subDays = max(0, (int) ($_POST['sub_days'] ?? 0));
                $maxUses = max(0, (int) ($_POST['max_uses'] ?? 0));
                $perAcc  = max(0, (int) ($_POST['uses_per_account'] ?? 0));
                $note    = trim((string) ($_POST['note'] ?? ''));
                $active  = isset($_POST['active']) ? 1 : 0;
                $expRaw  = trim((string) ($_POST['expires'] ?? ''));
                $expires = ($expRaw !== '' && ($t = strtotime($expRaw . ' 23:59:59')) !== false) ? $t : null;

                if ($credits === 0 && $subDays <= 0) {
                    $error = 'Kód musí dávat kredity nebo dny.';
                    break;
                }
                $pdo->prepare(
                    'UPDATE codes SET credits=?, sub_days=?, max_uses=?, uses_per_account=?,
                            note=?, expires_at=?, active=? WHERE id=?'
                )->execute([$credits, $subDays, $maxUses, $perAcc, $note, $expires, $active, $cid]);
                $ok = 'Kód upraven.';
                break;

            case 'adjust_credits':
                $uid    = (int) $_POST['user_id'];
                $amount = Cred::parse($_POST['amount'] ?? 0);
                $mode   = (string) ($_POST['mode'] ?? 'add');
                $reason = trim((string) ($_POST['reason'] ?? ''));

                $target = Auth::byId($uid);
                if (!$target) {
                    $error = 'Uživatel neexistuje.';
                    break;
                }

                // Privileged accounts do not spend credits, but the balance is
                // still kept and adjustable: it is what they would fall back to
                // if their role were ever lowered, so being able to see and set
                // it here is the whole point.

                // "Set" is the one that needs working out: the ledger only
                // records changes, so a target balance becomes the difference
                // between where the account is and where it should end up.
                if ($mode === 'raw') {
                    // A leading sign means "change by this much"; a bare
                    // number means "make the balance this". Both are things
                    // people write, and guessing wrong empties an account.
                    $raw    = trim((string) ($_POST['amount'] ?? ''));
                    $signed = $raw !== '' && ($raw[0] === '+' || $raw[0] === '-');

                    $delta = $signed
                        ? $amount
                        : $amount - (int) $target['credits'];
                } else {
                    $delta = match ($mode) {
                        'sub'   => -abs($amount),
                        'set'   => $amount - (int) $target['credits'],
                        default => abs($amount),
                    };
                }

                if ($delta === 0) {
                    $error = $mode === 'set'
                        ? 'Zůstatek už je na téhle hodnotě.'
                        : 'Zadej nenulový počet kreditů.';
                    break;
                }

                try {
                    $balance = Credits::apply(
                        $uid, $delta, 'admin',
                        ($reason !== '' ? $reason . ' - ' : '') . 'Admin'
                    );
                } catch (Throwable $e) {
                    $error = 'Nelze provést: ' . $e->getMessage();
                    break;
                }

                $ok = 'Kredity upraveny (' . ($delta > 0 ? '+' : '-') . Cred::fmtCs(abs($delta)) . ').';

                if (Mailer::enabled()) {
                    Notify::$lang = Auth::userLang($target);
                    [$sent, $mErr] = Mailer::notify(
                        (string) $target['email'],
                        Notify::creditsChanged($delta, $balance, $reason),
                        'credits_admin'
                    );
                    $ok .= $sent ? ' Zákazník informován.' : ' E-mail se nepodařilo odeslat: ' . $mErr;
                }
                break;

            case 'bulk_credits':
                $amount = Cred::parse($_POST['amount'] ?? 0);
                $reason = trim((string) ($_POST['reason'] ?? ''));
                $scope  = (string) ($_POST['scope'] ?? 'active');
                $bDays  = max(0, (int) ($_POST['days'] ?? 0));

                if ($amount === 0 && $bDays === 0) {
                    $error = 'Zadej počet kreditů nebo dnů, které se mají rozdat.';
                    break;
                }

                // Blocked and anonymised accounts are skipped: giving credits
                // to an account nobody can sign into just distorts the books.
                $sql = "SELECT * FROM users WHERE is_blocked = 0 AND role NOT IN ('admin','super')";
                if ($scope === 'verified') {
                    $sql .= ' AND verified_at IS NOT NULL';
                }
                $targets = $pdo->query($sql)->fetchAll();

                $done = 0; $mailed = 0; $failed = 0; $daysGiven = 0;
                foreach ($targets as $t) {
                    $balance = (int) $t['credits'];
                    if ($amount !== 0) {
                        try {
                            $balance = Credits::apply(
                                (int) $t['id'], $amount, 'admin_bulk',
                                ($reason !== '' ? $reason . ' - ' : '') . 'Admin'
                            );
                            $done++;
                        } catch (Throwable $e) {
                            // A negative bulk change can overdraw someone;
                            // skip them rather than abandoning the whole run.
                            $failed++;
                            continue;
                        }
                    }

                    // Time can be handed out the same way - it stacks onto a
                    // running plan, so nobody loses days they already paid for.
                    if ($bDays > 0) {
                        $until = Orders::grantSubscription((int) $t['id'], $bDays);
                        if ($until !== null) {
                            Credits::apply((int) $t['id'], 0, 'sub_set',
                                '+' . $bDays . ' dní hromadně'
                                . ($reason !== '' ? ' (' . $reason . ')' : '')
                                . ', do ' . date('d.m.Y H:i', $until));
                            $daysGiven++;
                        }
                    }

                    if ($amount !== 0 && Mailer::enabled()) {
                        Notify::$lang = Auth::userLang($t);
                        [$sent] = Mailer::notify(
                            (string) $t['email'],
                            Notify::creditsChanged($amount, $balance, $reason),
                            'credits_bulk'
                        );
                        if ($sent) { $mailed++; }
                    }
                }

                $bits = [];
                if ($amount !== 0) { $bits[] = "kredity u $done účtů"; }
                if ($daysGiven)    { $bits[] = "$bDays dní u $daysGiven účtů"; }
                $ok = 'Hotovo: ' . ($bits ? implode(' a ', $bits) : 'nic k úpravě')
                    . ($mailed ? ", odesláno $mailed e-mailů" : '') . '.';
                if ($failed) {
                    $ok .= " $failed účtů přeskočeno (nedostatek kreditů).";
                }
                break;

            case 'anonymise_user':
                // Keeps every accounting record and simply detaches the
                // person from it. This is the option to reach for when a
                // customer asks to be removed but their orders still have to
                // add up.
                $uid = (int) $_POST['user_id'];
                if ($uid === (int) $admin['id']) {
                    $error = 'Vlastní účet takhle upravit nemůžeš.';
                    break;
                }
                $victim = Auth::byId($uid);
                if (!$victim) {
                    $error = 'Uživatel neexistuje.';
                    break;
                }

                Db::transact(function (PDO $pdo) use ($uid, $victim) {
                    // Zero the balance through the ledger so the audit in
                    // the overview keeps agreeing with itself.
                    $bal = (int) $victim['credits'];
                    if ($bal !== 0) {
                        $pdo->prepare(
                            'INSERT INTO ledger (user_id, delta, balance_after, reason, ref, created_at)
                             VALUES (?, ?, 0, ?, ?, ?)'
                        )->execute([$uid, -$bal, 'anonymised', null, time()]);
                    }

                    $pdo->prepare(
                        'UPDATE users
                         SET email = ?, pass_hash = ?, credits = 0, is_blocked = 1,
                             is_admin = 0, verify_token = NULL
                         WHERE id = ?'
                    )->execute([
                        'smazany-' . $uid . '@invalid.local',
                        password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT),
                        $uid,
                    ]);
                });

                $ok = 'Účet anonymizován. Objednávky a historie zůstaly zachovány.';
                break;

            case 'delete_user':
                $uid = (int) $_POST['user_id'];
                if ($uid === (int) $admin['id']) {
                    $error = 'Vlastní účet smazat nemůžeš.';
                    break;
                }
                $victim = Auth::byId($uid);
                if (!$victim) {
                    $error = 'Uživatel neexistuje.';
                    break;
                }
                if ((int) $victim['is_admin'] === 1
                    && (int) $pdo->query('SELECT COUNT(*) FROM users WHERE is_admin = 1')->fetchColumn() <= 1) {
                    $error = 'Tohle je poslední správce. Nejdřív udělej správcem někoho jiného.';
                    break;
                }

                Db::transact(function (PDO $pdo) use ($uid) {
                    // The foreign keys cascade, so the ledger, orders and
                    // code redemptions go with the account. The per-code use
                    // counter is a separate column and would be left
                    // overstating reality, so it is corrected here.
                    $st = $pdo->prepare('SELECT code_id FROM code_uses WHERE user_id = ?');
                    $st->execute([$uid]);
                    foreach ($st->fetchAll(PDO::FETCH_COLUMN) as $codeId) {
                        $pdo->prepare('UPDATE codes SET uses = MAX(0, uses - 1) WHERE id = ?')
                            ->execute([(int) $codeId]);
                    }

                    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$uid]);
                });

                $ok = 'Účet i jeho záznamy smazány.';
                break;

            case 'toggle_block':
                $uid = (int) $_POST['user_id'];
                if ($uid === (int) $admin['id']) {
                    $error = 'You cannot block your own account.';
                } else {
                    $pdo->prepare('UPDATE users SET is_blocked = 1 - is_blocked WHERE id = ?')->execute([$uid]);
                    $ok = 'User updated.';
                }
                break;

            case 'set_subscription':
                // Adjust or clear a user's time-based access by hand. An empty
                // date clears it; a date sets cover to the end of that day.
                $uid    = (int) ($_POST['user_id'] ?? 0);
                $date   = trim((string) ($_POST['until'] ?? ''));
                $target = Auth::byId($uid);

                if (!$target) {
                    $error = 'Uživatel neexistuje.';
                    break;
                }

                // What was there before, so the history can say what changed.
                $prevUntil = (int) ($target['subscription_until'] ?? 0);
                $prevLeft  = $prevUntil > time() ? (int) ceil(($prevUntil - time()) / 86400) : 0;

                if ($date === '') {
                    $pdo->prepare('UPDATE users SET subscription_until = NULL WHERE id = ?')->execute([$uid]);
                    // A zero-credit ledger line: the balance is untouched, but
                    // the change shows up in the user's history and the audit.
                    Credits::apply($uid, 0, 'sub_cancel',
                        $prevLeft > 0 ? 'do ' . date('j.n.Y', $prevUntil) . ' (' . $prevLeft . ' dní)' : null);
                    $ok = 'Předplatné zrušeno.';
                } elseif (($ts = strtotime($date . ' 23:59:59')) === false) {
                    $error = 'Neplatné datum.';
                } else {
                    $pdo->prepare('UPDATE users SET subscription_until = ? WHERE id = ?')->execute([$ts, $uid]);
                    // How many days this manual set actually ADDED on top of
                    // whatever ran before - recorded so the grant can later be
                    // revoked one-for-one like a package.
                    $added = (int) ceil(($ts - max(time(), $prevUntil)) / 86400);
                    Credits::apply($uid, 0, 'sub_set',
                        'do ' . date('j.n.Y', $ts) . ($added > 0 ? ' (+' . $added . ' dní)' : ''));
                    $ok = 'Předplatné nastaveno do ' . date('j.n.Y', $ts) . '.';
                }
                break;

            case 'cancel_subscription':
                // One-click revoke: the unlimited access ends now. Any money
                // refund is handled outside the app; this just takes the
                // access back so it is not being used while that happens.
                $uid    = (int) ($_POST['user_id'] ?? 0);
                $target = Auth::byId($uid);

                if (!$target) {
                    $error = 'Uživatel neexistuje.';
                    break;
                }

                $prevUntil = (int) ($target['subscription_until'] ?? 0);
                $prevLeft  = $prevUntil > time() ? (int) ceil(($prevUntil - time()) / 86400) : 0;

                // Worked out before the plan is taken away, while there is
                // still a plan to measure: what the unused days cost, and
                // which orders they came from.
                $due = Orders::planRefundDue($target);

                $pdo->prepare('UPDATE users SET subscription_until = NULL WHERE id = ?')->execute([$uid]);

                $dueText = $due['cents'] > 0
                    ? ' · k vrácení ' . money($due['cents'], $due['currency'])
                        . ' (' . implode(', ', array_map(
                            static fn($p) => $p['ref'] . ': ' . money($p['cents'], $due['currency']),
                            $due['parts'])) . ')'
                    : '';

                // Zero-credit ledger line so the revoke is visible in the
                // user's history and the admin audit, with what was left and
                // what it is worth - otherwise the figure lives only in
                // whoever's head clicked the button.
                Credits::apply($uid, 0, 'sub_cancel',
                    $prevLeft > 0
                        ? 'do ' . date('j.n.Y', $prevUntil) . ' (' . $prevLeft . ' dní)' . $dueText
                        : null);

                $ok = 'Předplatné zrušeno, neomezený přístup okamžitě skončil.';
                if ($due['cents'] > 0) {
                    $ok .= ' K vrácení zbývá ' . money($due['cents'], $due['currency'])
                         . ' za ' . $due['days'] . ' nevyužitých dní'
                         . ' (' . implode(', ', array_map(
                             static fn($p) => $p['ref'] . ' ' . money($p['cents'], $due['currency']),
                             $due['parts'])) . ').'
                         . ' Peníze pošli ručně - záznam je v historii účtu.';
                }

                if (Mailer::enabled()) {
                    Notify::$lang = Auth::userLang($target);
                    [$sent] = Mailer::notify(
                        (string) $target['email'],
                        Notify::subscriptionCancelled(),
                        'subscription_cancelled'
                    );
                    if ($sent) { $ok .= ' Zákazník informován.'; }
                }
                break;

            case 'revoke_time':
                // Takes back ONE time grant (order, code or manual) by
                // subtracting its days from the running subscription. A
                // machine token [g:…] rides along in the ledger note, so the
                // exact grant is marked closed - matching by wording broke
                // the moment the date format changed.
                $uid    = (int) ($_POST['user_id'] ?? 0);
                $days   = max(0, (int) ($_POST['days'] ?? 0));
                $srcTxt = trim((string) ($_POST['source'] ?? ''));
                $tok    = trim((string) ($_POST['grant'] ?? ''));
                $target = Auth::byId($uid);

                if (!$target || $days === 0 || !preg_match('/^[ol]:[A-Za-z0-9._-]+$/', $tok)) {
                    $error = 'Neplatný požadavek.';
                    break;
                }
                // One-shot: the token in the ledger says this grant is closed.
                $st = $pdo->prepare("SELECT 1 FROM ledger WHERE user_id = ? AND ref LIKE ?");
                $st->execute([$uid, '%[g:' . $tok . ']%']);
                if ($st->fetchColumn()) {
                    $error = 'Tohle přidělení už bylo odebráno.';
                    break;
                }
                $until = (int) ($target['subscription_until'] ?? 0);
                if ($until <= time()) {
                    $error = 'Účet nemá aktivní časový plán.';
                    break;
                }

                // A deferred start ("od zítřka") waited some hours before the
                // paid days began - take that gap back too, or the plan hangs
                // around "active" until midnight after being revoked.
                $pad = 0;
                if (str_starts_with($tok, 'o:')) {
                    $stO = $pdo->prepare('SELECT sub_padding, sub_start FROM orders WHERE reference = ?');
                    $stO->execute([substr($tok, 2)]);
                    if ($oRow = $stO->fetch()) {
                        $pad = (int) ($oRow['sub_padding'] ?? 0);
                        if ($pad === 0 && (string) ($oRow['sub_start'] ?? '') === 'tomorrow') {
                            $pad = 86400; // legacy order from before padding was recorded
                        }
                    }
                }

                $newUntil = $until - $days * 86400 - $pad;
                $pdo->prepare('UPDATE users SET subscription_until = ? WHERE id = ?')
                    ->execute([$newUntil > time() ? $newUntil : null, $uid]);

                Credits::apply($uid, 0, 'sub_cancel',
                    '-' . $days . ' dní' . ($srcTxt !== '' ? ' (' . $srcTxt . ')' : '')
                    . ($newUntil > time() ? ', nově do ' . date('d.m.Y H:i', $newUntil) : ', plán ukončen')
                    . ' [g:' . $tok . ']');

                $ok = 'Odebráno ' . $days . ' dní.'
                    . ($newUntil > time() ? ' Plán nyní končí ' . date('d.m.Y H:i', $newUntil) . '.' : ' Plán tím skončil.');
                break;

            case 'revoke_credits':
                // Takes back ONE admin credit grant. The reversal references
                // the original ledger row, which is also how a grant already
                // stornoed is recognised and never taken twice.
                $uid = (int) ($_POST['user_id'] ?? 0);
                $lid = (int) ($_POST['ledger_id'] ?? 0);

                $st = $pdo->prepare(
                    "SELECT * FROM ledger WHERE id = ? AND user_id = ? AND delta > 0
                     AND reason IN ('admin','admin_bulk')"
                );
                $st->execute([$lid, $uid]);
                $grant = $st->fetch();

                if (!$grant) {
                    $error = 'Tenhle přídavek nejde odebrat.';
                    break;
                }
                $st = $pdo->prepare("SELECT 1 FROM ledger WHERE user_id = ? AND ref = ?");
                $st->execute([$uid, 'storno #' . $lid]);
                if ($st->fetchColumn()) {
                    $error = 'Tenhle přídavek už byl odebrán.';
                    break;
                }

                // Written even at zero, so the grant is marked as stornoed
                // and the button does not keep offering an empty reversal.
                $take = min((int) $grant['delta'], Credits::balance($uid));
                Credits::apply($uid, -$take, 'admin', 'storno #' . $lid);
                $ok = 'Odebráno ' . Cred::fmtCs($take) . ' kreditů'
                    . ($take < (int) $grant['delta']
                        ? ' (z ' . Cred::fmtCs((int) $grant['delta']) . ' - zbytek už byl utracen).'
                        : '.');
                break;

            case 'mark_message_read':
                $mid = (int) ($_POST['message_id'] ?? 0);
                $st = $pdo->prepare('UPDATE user_messages SET read_at = ?, read_by = ? WHERE id = ?');
                $st->execute([time(), (int) $admin['id'], $mid]);
                $ok = 'Zpráva byla označena jako přečtená.';
                break;

            case 'mark_message_unread':
                $mid = (int) ($_POST['message_id'] ?? 0);
                $st = $pdo->prepare('UPDATE user_messages SET read_at = NULL, read_by = NULL WHERE id = ?');
                $st->execute([$mid]);
                $ok = 'Zpráva byla označena jako nepřečtená.';
                break;

            case 'update_support_title':
                $mid = (int) ($_POST['message_id'] ?? 0);
                $title = trim((string) ($_POST['admin_title'] ?? ''));
                if ($title === '') {
                    $error = 'Nadpis nemůže být prázdný.';
                    break;
                }
                if (mb_strlen($title) > 160) {
                    $error = 'Nadpis může mít nejvýše 160 znaků.';
                    break;
                }
                if (!UserMessages::updateAdminTitle($mid, $title)) {
                    $error = 'Nadpis se nepodařilo změnit.';
                } else {
                    $ok = 'Nadpis konverzace byl změněn.';
                }
                break;

            case 'reply_user_message':
                $mid   = (int) ($_POST['message_id'] ?? 0);
                $reply = trim((string) ($_POST['reply'] ?? ''));
                if ($reply === '' || mb_strlen($reply) > 10000) {
                    $error = 'Odpověď je povinná a může mít nejvýše 10 000 znaků.';
                    break;
                }
                if (!UserMessages::addStaffReply($mid, (int) $admin['id'], $reply)) {
                    $error = 'Konverzace už neexistuje nebo je uzavřená.';
                } else {
                    $ok = 'Odpověď byla odeslána do konverzace uživatele.';
                }
                break;

            case 'open_user_message':
                $mid = (int) ($_POST['message_id'] ?? 0);
                $st = $pdo->prepare('UPDATE user_messages SET read_at = ?, read_by = ? WHERE id = ? AND read_at IS NULL');
                $st->execute([time(), (int) $admin['id'], $mid]);
                $ok = 'Konverzace otevřena.';
                break;

            case 'close_user_message':
            case 'reopen_user_message':
                $mid = (int) ($_POST['message_id'] ?? 0);
                $close = $action === 'close_user_message';
                if (!$close) {
                    $ownerSt = $pdo->prepare('SELECT user_id, closed_at FROM user_messages WHERE id = ? LIMIT 1');
                    $ownerSt->execute([$mid]);
                    $owner = $ownerSt->fetch();
                    if (!$owner) {
                        $error = 'Konverzace už neexistuje.';
                        break;
                    }
                    $otherSt = $pdo->prepare('SELECT 1 FROM user_messages WHERE user_id = ? AND closed_at IS NULL AND id <> ? LIMIT 1');
                    $otherSt->execute([(int)$owner['user_id'], $mid]);
                    if ($otherSt->fetchColumn()) {
                        $error = 'Tento uživatel už má aktivní chat s podporou. Uzavřenou konverzaci nelze znovu otevřít, dokud aktivní chat neskončí.';
                        break;
                    }
                }
                $st = $pdo->prepare($close
                    ? 'UPDATE user_messages SET closed_at = ?, closed_by = ? WHERE id = ?'
                    : 'UPDATE user_messages SET closed_at = NULL, closed_by = NULL WHERE id = ?');
                $st->execute($close ? [time(), (int) $admin['id'], $mid] : [$mid]);
                if ($st->rowCount() === 0) {
                    $error = 'Konverzace už neexistuje nebo se její stav mezitím změnil.';
                } else {
                    $ok = $close ? 'Chat byl označen jako vyřešený.' : 'Chat byl znovu otevřený.';
                }
                break;

            case 'reconcile_ledger':
                $uid = (int) ($_POST['user_id'] ?? 0);
                $delta = Credits::reconcile($uid);
                $ok = $delta === 0
                    ? 'Účetní kniha už byla srovnaná; pokud zůstává historická chyba, je uvedená v kontrole a v protokolu zásahu.'
                    : 'Účetní kniha byla dorovnána o ' . ($delta > 0 ? '+' : '-')
                        . Cred::fmtCs(abs($delta)) . ' kreditů. Zůstatek účtu se nezměnil. Zásah byl zapsán do protokolu účtu.';
                break;

            case 'send_user_mail':
                // A hand-written message to one account - typically "where
                // should the refund go". Logged like every other mail.
                $uid     = (int) ($_POST['user_id'] ?? 0);
                $subject = trim((string) ($_POST['subject'] ?? ''));
                $mbody   = trim((string) ($_POST['body'] ?? ''));
                $target  = Auth::byId($uid);

                if (!$target) {
                    $error = 'Uživatel neexistuje.';
                } elseif ($subject === '' || $mbody === '') {
                    $error = 'Vyplň předmět i text zprávy.';
                } elseif (!Mailer::enabled()) {
                    $error = 'Odesílání e-mailů není nastavené.';
                } else {
                    Notify::$lang = Auth::userLang($target);
                    [$sent, $mErr] = Mailer::notify(
                        (string) $target['email'],
                        Notify::custom($subject, $mbody),
                        'admin_message'
                    );
                    if ($sent) { $ok = 'E-mail odeslán na ' . $target['email'] . '.'; }
                    else       { $error = 'Odeslání selhalo: ' . $mErr; }
                }
                break;

            case 'revoke_code':
                // Takes back what a redeemed code granted - credits down to
                // what the balance still covers, days off the running plan.
                // The storno marker on the ledger keeps it one-shot.
                $uid   = (int) ($_POST['user_id'] ?? 0);
                $useId = (int) ($_POST['use_id'] ?? 0);

                $st = $pdo->prepare(
                    'SELECT cu.id, cu.created_at, c.code, c.label, c.credits, c.sub_days
                     FROM code_uses cu JOIN codes c ON c.id = cu.code_id
                     WHERE cu.id = ? AND cu.user_id = ?'
                );
                $st->execute([$useId, $uid]);
                $use = $st->fetch();

                if (!$use) {
                    $error = 'Uplatnění kódu nebylo nalezeno.';
                    break;
                }
                $st = $pdo->prepare('SELECT 1 FROM ledger WHERE user_id = ? AND ref LIKE ?');
                $st->execute([$uid, 'storno kódu #' . $useId . '%']);
                if ($st->fetchColumn()) {
                    $error = 'Tenhle kód už byl odebrán.';
                    break;
                }

                $codeName = trim((string) ($use['label'] ?? '')) !== '' ? $use['label'] : $use['code'];
                $bits     = [];

                $take = min(max(0, (int) $use['credits']), Credits::balance($uid));
                Credits::apply($uid, -$take, 'admin', 'storno kódu #' . $useId . ' (' . $codeName . ')');
                if ((int) $use['credits'] > 0) {
                    $bits[] = 'kredity -' . Cred::fmtCs($take)
                            . ($take < (int) $use['credits'] ? ' (z ' . Cred::fmtCs((int) $use['credits']) . ')' : '');
                }

                $cDays = (int) ($use['sub_days'] ?? 0);
                if ($cDays > 0) {
                    $target = Auth::byId($uid);
                    $until  = (int) ($target['subscription_until'] ?? 0);
                    if ($until > time()) {
                        $newUntil = $until - $cDays * 86400;
                        $pdo->prepare('UPDATE users SET subscription_until = ? WHERE id = ?')
                            ->execute([$newUntil > time() ? $newUntil : null, $uid]);
                        Credits::apply($uid, 0, 'sub_cancel',
                            '-' . $cDays . ' dní (storno kódu #' . $useId . ' ' . $codeName . ')');
                        $bits[] = 'čas -' . $cDays . ' dní';
                    }
                }

                $ok = 'Kód ' . $codeName . ' odebrán' . ($bits ? ' (' . implode(', ', $bits) . ')' : '') . '.';
                break;

            case 'refund_order':
                // Starts a refund: the grant (credits and/or days) is taken
                // back right away, the order moves to the Refundy queue and
                // waits there until the money is actually returned.
                $oid = (int) ($_POST['order_id'] ?? 0);
                $st  = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND status = "paid"');
                $st->execute([$oid]);
                $order = $st->fetch();

                if (!$order) {
                    $error = 'Objednávka neexistuje nebo není zaplacená.';
                    break;
                }
                $uid    = (int) $order['user_id'];
                $target = Auth::byId($uid);
                $refundCents = (int) $order['price_cents'];

                // The policy itself lives in Orders, so the panel and the
                // customer's own "vrátit peníze" button cannot drift apart.
                $why = Orders::refundBlocker($order, $target);
                if ($why !== '') {
                    $error = 'Refund nejde: ' . $why;
                    break;
                }

                // The grant is frozen right away: what is being refunded must
                // not be spendable while the money is on its way back.
                $bits = $target ? Orders::reclaimGrant($order, $target) : [];

                $pdo->prepare(
                    'UPDATE orders SET status = ?, refund_cents = ?, admin_note = ? WHERE id = ?'
                )->execute([
                    Orders::REFUND,
                    $refundCents,
                    'K refundaci ' . money($refundCents, $order['currency'] ?? null)
                        . ($refundCents !== (int) $order['price_cents']
                            ? ' (z ' . money((int) $order['price_cents'], $order['currency'] ?? null) . ')' : '')
                        . ($bits ? ' · ' . implode(', ', $bits) : '')
                        . ' · ' . date('d.m.Y H:i'),
                    $oid,
                ]);

                $ok = 'Objednávka ' . $order['reference'] . ' je ve frontě refundů. '
                    . 'Vrátit: ' . money($refundCents, $order['currency'] ?? null)
                    . ($bits ? ' (' . implode(', ', $bits) . ')' : '');
                break;

            case 'refund_undo':
                // The refund was a mistake: put the order back to "paid" and
                // hand back exactly what its refund took - the credits that
                // were reclaimed and the days that were cut.
                $oid = (int) ($_POST['order_id'] ?? 0);
                $st  = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND status = "refund"');
                $st->execute([$oid]);
                $order = $st->fetch();

                if (!$order) {
                    $error = 'Objednávka není ve frontě refundů.';
                    break;
                }
                $uid  = (int) $order['user_id'];
                $ref  = (string) $order['reference'];
                $bits = [];

                // Credits the refund reclaimed (the most recent negative
                // 'refund' row for this reference).
                $st = $pdo->prepare(
                    "SELECT delta FROM ledger WHERE user_id = ? AND reason = 'refund'
                     AND ref = ? AND delta < 0 ORDER BY id DESC LIMIT 1"
                );
                $st->execute([$uid, $ref]);
                $took = (int) ($st->fetchColumn() ?: 0);
                if ($took < 0) {
                    Credits::apply($uid, -$took, 'refund', 'obnova ' . $ref);
                    $bits[] = 'kredity +' . Cred::fmtCs(-$took);
                }

                // Days the refund cut (parsed from its own ledger note).
                $st = $pdo->prepare(
                    "SELECT ref FROM ledger WHERE user_id = ? AND reason = 'sub_cancel'
                     AND ref LIKE ? ORDER BY id DESC LIMIT 1"
                );
                $st->execute([$uid, '-%dní (refund ' . $ref . ')%']);
                $cutRef = (string) ($st->fetchColumn() ?: '');
                if (preg_match('/^-(\d+) dní/u', $cutRef, $m)) {
                    $back = (int) $m[1];
                    Orders::grantSubscription($uid, $back);
                    // The restore marker [g~:…] lifts the grant's "revoked"
                    // flag, so its row comes back to life in the detail.
                    Credits::apply($uid, 0, 'sub_set',
                        'obnova refundu ' . $ref . ' (+' . $back . ' dní)'
                        . ' [g~:o:' . preg_replace('/[^A-Za-z0-9._-]/', '', $ref) . ']');
                    $bits[] = 'čas +' . $back . ' dní';
                }

                $pdo->prepare(
                    'UPDATE orders SET status = ?, refund_cents = NULL, refund_dest = \'\', refunded_at = NULL,
                                       admin_note = ? WHERE id = ?'
                )->execute([
                    Orders::PAID,
                    trim((string) $order['admin_note']) . ' · refund zrušen ' . date('d.m.Y H:i'),
                    $oid,
                ]);

                $ok = 'Objednávka ' . $ref . ' obnovena'
                    . ($bits ? ' (vráceno: ' . implode(', ', $bits) . ')' : '') . '.';
                break;

            case 'refund_done':
                // The money left the bank - close the refund. WHERE it went
                // is mandatory: the refund receipt is built from it.
                $oid  = (int) ($_POST['order_id'] ?? 0);
                $dest = trim((string) ($_POST['refund_dest'] ?? ''));
                $st   = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND status = "refund"');
                $st->execute([$oid]);
                $order = $st->fetch();

                if (!$order) {
                    $error = 'Objednávka není ve frontě refundů.';
                    break;
                }
                if ($dest === '') {
                    $error = 'Vyplň, kam byly peníze vráceny (účet, PayPal…) - bez toho refund nejde uzavřít.';
                    break;
                }
                $pdo->prepare(
                    'UPDATE orders SET status = ?, refund_dest = ?, refunded_at = ?, admin_note = ? WHERE id = ?'
                )->execute([
                    Orders::REFUNDED,
                    $dest,
                    time(),
                    trim((string) $order['admin_note']) . ' · refundováno ' . date('d.m.Y H:i') . ' na: ' . $dest,
                    $oid,
                ]);
                $ok = 'Refund objednávky ' . $order['reference'] . ' uzavřen (vráceno na: ' . $dest . ').';

                // The customer gets the refund receipt by mail right away.
                if (Mailer::enabled()) {
                    $buyer = Auth::byId((int) $order['user_id']);
                    if ($buyer) {
                        Notify::$lang = Auth::userLang($buyer);
                        $rcAmount = money((int) ($order['refund_cents'] ?? $order['price_cents']), $order['currency'] ?? null);
                        $rcUrl    = originUrl() . '/invoice.php?ref=' . urlencode($order['reference'] . '_refund')
                                  . '&t=' . urlencode((string) ($order['access_token'] ?? ''));
                        [$sent] = Mailer::notify(
                            (string) $buyer['email'],
                            Notify::refundReceipt((string) $order['reference'], $rcAmount, $dest, $rcUrl),
                            'refund_receipt'
                        );
                        if ($sent) { $ok .= ' Doklad odeslán e-mailem.'; }
                    }
                }
                break;

            case 'reset_quotas':
                // The allowance is freed by moving the counting start to now,
                // not by deleting the export log. Deleting it would take the
                // statistics and the "who exported what" history with it; this
                // keeps every record and simply stops the older exports from
                // counting against anyone's window.
                Settings::set(['quota_reset_at' => (string) time()]);
                $ok = 'Kvóty vynulovány. Historie exportů i statistiky zůstávají, '
                    . 'jen se každému znovu uvolnil plný počet volných exportů.';
                break;

            case 'set_maintenance':
                $enabled = (string) ($_POST['maintenance_mode'] ?? '0') === '1';
                Settings::set(['maintenance_mode' => $enabled ? '1' : '0']);
                $ok = $enabled
                    ? 'Veřejný provoz je pozastaven. Správci mohou dál pracovat v administraci.'
                    : 'Veřejný provoz je znovu zapnutý.';
                break;

            case 'create_user':
                // Hand-made account. The panel needed this the moment a
                // fresh install had no way in except the very first admin -
                // and it is also how a second administrator gets made.
                $nEmail = Auth::normaliseEmail((string) ($_POST['new_email'] ?? ''));
                $nNick  = trim((string) ($_POST['new_nickname'] ?? ''));
                $nPass  = (string) ($_POST['new_password'] ?? '');
                $nRole  = (string) ($_POST['new_role'] ?? 'user');

                if (!filter_var($nEmail, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Zadej platný e-mail.';
                    break;
                }
                if (strlen($nPass) < 8) {
                    $error = 'Heslo musí mít aspoň 8 znaků.';
                    break;
                }
                if (!isset(Auth::ROLES[$nRole])) {
                    $error = 'Neznámá role.';
                    break;
                }

                $st = $pdo->prepare('SELECT 1 FROM users WHERE email = ? OR (nickname <> "" AND nickname = ?)');
                $st->execute([$nEmail, $nNick]);
                if ($st->fetchColumn()) {
                    $error = 'Takový e-mail nebo přezdívka už existuje.';
                    break;
                }

                $pdo->prepare(
                    'INSERT INTO users (email, nickname, pass_hash, role, is_admin, credits, created_at, verified_at, lang)
                     VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?)'
                )->execute([
                    $nEmail,
                    $nNick,
                    password_hash($nPass, PASSWORD_DEFAULT),
                    $nRole,
                    $nRole === 'admin' ? 1 : 0,
                    time(),
                    time(),   // made by hand, so it counts as verified
                    'cs',
                ]);
                $ok = 'Účet ' . $nEmail . ' vytvořen (' . Auth::ROLES[$nRole] . ').';
                break;

            case 'reset_password':
                // Same route as "forgot password": a one-time link to a page
                // where the account holder picks their own password. No
                // temporary password is minted or mailed around - the account's
                // current password simply keeps working until they follow the
                // link, so nobody is locked out if the mail is missed.
                $uid    = (int) ($_POST['user_id'] ?? 0);
                $target = Auth::byId($uid);

                if (!$target) {
                    $error = 'Uživatel neexistuje.';
                    break;
                }
                if (!Mailer::enabled()) {
                    $error = 'Odesílání e-mailů není nastavené, takže odkaz na reset by se neměl jak doručit.';
                    break;
                }

                $token = Auth::beginReset((string) $target['email']);
                if ($token === null) {
                    $error = 'Reset se nepodařilo spustit.';
                    break;
                }

                Notify::$lang = Auth::userLang($target);
                [$sent, $mErr] = Mailer::notify(
                    (string) $target['email'],
                    Notify::passwordReset(originUrl() . '/change-password.php?token=' . urlencode($token), 2),
                    'password_reset'
                );

                $ok = $sent
                    ? 'Odkaz na obnovu hesla odeslán na ' . $target['email']
                      . '. Platí 2 hodiny a použije se jen jednou.'
                    : 'Odkaz se nepodařilo odeslat: ' . $mErr;
                break;

            case 'set_role':
                $uid  = (int) ($_POST['user_id'] ?? 0);
                $role = (string) ($_POST['role'] ?? 'user');

                $target = Auth::byId($uid);
                if (!$target) {
                    $error = 'Uživatel neexistuje.';
                    break;
                }

                // Removing the last administrator would lock everybody out of
                // the panel, and there is no way back in from the outside.
                $lastAdmin = Auth::isAdmin($target)
                    && $role !== 'admin'
                    && (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn() <= 1;

                if ($lastAdmin) {
                    $error = 'Tohle je poslední správce. Nejdřív udělej správcem někoho jiného.';
                    break;
                }

                try {
                    Auth::setRole($uid, $role);
                    $ok = 'Role změněna na ' . (Auth::ROLES[$role] ?? $role) . '.';
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
                break;

            case 'toggle_admin':
                $uid = (int) $_POST['user_id'];
                if ($uid === (int) $admin['id']) {
                    $error = 'You cannot remove your own admin rights.';
                } else {
                    $pdo->prepare('UPDATE users SET is_admin = 1 - is_admin WHERE id = ?')->execute([$uid]);
                    $ok = 'User updated.';
                }
                break;

            /*
             * The list of ready-made SMTP servers. Its own actions rather
             * than part of the settings form: adding a server must not also
             * save half-typed mail credentials.
             */
            case 'smtp_preset_add':
                $added = SmtpPresets::add([
                    'label'    => (string) ($_POST['preset_label'] ?? ''),
                    'host'     => (string) ($_POST['preset_host'] ?? ''),
                    'port'     => (int) ($_POST['preset_port'] ?? 587),
                    'security' => (string) ($_POST['preset_security'] ?? 'tls'),
                    'note'     => (string) ($_POST['preset_note'] ?? ''),
                ]);
                if ($added) {
                    $ok = 'Předvolba přidána.';
                } else {
                    $error = 'Předvolba potřebuje název a adresu serveru.';
                }
                break;

            case 'smtp_preset_remove':
                SmtpPresets::remove((string) ($_POST['preset_id'] ?? ''));
                $ok = 'Předvolba odebrána.';
                break;

            case 'smtp_preset_reset':
                SmtpPresets::reset();
                $ok = 'Seznam předvoleb je zpátky ve výchozím stavu.';
                break;
            case 'save_package':
                $id = (int) ($_POST['id'] ?? 0);
                $name = trim((string) ($_POST['name'] ?? ''));
                // A subscription package may legitimately grant no credits at
                // all - its value is the days of unlimited access - so the
                // usual "at least one credit" floor is lifted when days are set.
                $subDays = max(0, (int) ($_POST['sub_days'] ?? 0));
                $credits = $subDays > 0
                    ? max(0, Cred::parse($_POST['credits'] ?? 0))
                    : max(1, Cred::parse($_POST['credits'] ?? 1));
                $price = max(0, (int) round(((float) str_replace(',', '.', (string) ($_POST['price'] ?? '0'))) * 100));
                $sort = (int) ($_POST['sort'] ?? 0);
                $active = isset($_POST['active']) ? 1 : 0;
                $featured = isset($_POST['featured']) ? 1 : 0;
                $maxUses = max(0, (int) ($_POST['max_uses'] ?? 0));
                $perAcc  = max(0, (int) ($_POST['uses_per_account'] ?? 0));

                if ($name === '') {
                    $error = 'Balíček potřebuje název.';
                } elseif ($id > 0) {
                    // sort is deliberately left out: order is owned by the
                    // drag-and-drop handler, so a field edit here must not
                    // reset the position to whatever a hidden input happened
                    // to carry (or to zero when there is none).
                    $pdo->prepare('UPDATE packages SET name=?, credits=?, price_cents=?,
                                   active=?, max_uses=?, uses_per_account=?, sub_days=?, featured=? WHERE id=?')
                        ->execute([$name, $credits, $price, $active, $maxUses, $perAcc, $subDays, $featured, $id]);
                    $ok = 'Balíček uložen.';
                } else {
                    // A new package always lands at the end with the next free
                    // number, so two packages never share a position and the
                    // admin never has to pick one by hand.
                    $sort = 1 + (int) $pdo->query('SELECT COALESCE(MAX(sort), 0) FROM packages')->fetchColumn();
                    $pdo->prepare('INSERT INTO packages (name, credits, price_cents, sort, active,
                                   max_uses, uses_per_account, sub_days, featured) VALUES (?,?,?,?,?,?,?,?,?)')
                        ->execute([$name, $credits, $price, $sort, $active, $maxUses, $perAcc, $subDays, $featured]);
                    $id = (int) $pdo->lastInsertId();
                    $ok = 'Balíček přidán.';
                }

                // Record the price that is now on offer, so the 30-day
                // history stays complete even if nobody visits the shop.
                if (!$error && $id > 0) {
                    $st = $pdo->prepare('SELECT * FROM packages WHERE id = ?');
                    $st->execute([$id]);
                    if ($pkgRow = $st->fetch()) {
                        Pricing::record($id, Pricing::of($pkgRow)['final']);
                    }
                }
                break;

            case 'delete_package':
                $pdo->prepare('DELETE FROM packages WHERE id = ?')->execute([(int) $_POST['id']]);
                $ok = 'Package deleted.';
                break;

            case 'reorder_packages':
                // The drag-and-drop table posts the ids in their new visual
                // order; sort simply becomes the position, so what the admin
                // arranges is exactly what the shop shows.
                $ids = $_POST['order'] ?? [];
                if (is_array($ids) && $ids) {
                    $st  = $pdo->prepare('UPDATE packages SET sort = ? WHERE id = ?');
                    $pos = 1;
                    foreach ($ids as $pid) {
                        $st->execute([$pos++, (int) $pid]);
                    }
                    $ok = 'Pořadí uloženo.';
                } else {
                    $error = 'Nic k seřazení.';
                }
                break;

            case 'save_promotion':
                $index  = (int) ($_POST['index'] ?? -1);
                $label  = trim((string) ($_POST['label'] ?? ''));
                $tenths = Cred::parsePercent((string) ($_POST['percent'] ?? '0'));
                $from   = trim((string) ($_POST['from'] ?? ''));
                $until  = trim((string) ($_POST['until'] ?? ''));

                $candidate = ['label' => $label, 'percent' => $tenths, 'from' => $from, 'until' => $until];

                $list    = Promotions::all();
                $editing = $index >= 0 && $index < count($list);

                // "until" before "from" is the classic mistake here, so it is
                // caught up front rather than becoming a promotion that can
                // never run.
                [$cs, $ce] = Promotions::window($candidate);

                if ($tenths <= 0) {
                    $error = 'Sleva musí být větší než 0 %.';
                    break;
                }
                if ($cs !== null && $ce !== null && $cs > $ce) {
                    $error = 'Datum „do" je před datem „od".';
                    break;
                }

                $clash = Promotions::firstClash($candidate, $editing ? $index : -1);
                if ($clash !== null) {
                    $c = Promotions::all()[$clash];
                    $error = 'Akce se překrývá s „' . ($c['label'] !== '' ? $c['label'] : 'bez názvu')
                           . '". Uprav data tak, aby na sebe nenavazovaly.';
                    break;
                }

                if ($editing) {
                    $list[$index] = $candidate;
                    $ok = 'Akce upravena.';
                } else {
                    $list[] = $candidate;
                    $ok = 'Akce naplánována.';
                }
                Promotions::saveAll($list);

                // The offered price may have just changed, so keep the 30-day
                // history in step exactly as saving a package does.
                Pricing::recordAll();
                break;

            case 'delete_promotion':
                $index = (int) ($_POST['index'] ?? -1);
                $list  = Promotions::all();
                if (isset($list[$index])) {
                    unset($list[$index]);
                    Promotions::saveAll($list);
                    Pricing::recordAll();
                    $ok = 'Akce smazána.';
                } else {
                    $error = 'Akce neexistuje.';
                }
                break;

            case 'order_status':
                $oid    = (int) $_POST['id'];
                $status = (string) $_POST['status'];
                $note   = trim((string) ($_POST['admin_note'] ?? ''));

                $st = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
                $st->execute([$oid]);
                $order = $st->fetch();

                if (!$order) {
                    $error = 'Neznámá objednávka.';
                } elseif ($order['status'] === 'paid') {
                    $error = 'Zaplacenou objednávku už nelze měnit. Kredity uprav ručně u uživatele.';
                } elseif ($order['status'] === 'cancelled') {
                    // A cancelled order is final. Allowing it back to paid
                    // would credit an order the customer was told was dead.
                    $error = 'Zrušenou objednávku už nelze označit jako zaplacenou. '
                           . 'Ať zákazník vytvoří novou, nebo mu kredity přidej ručně u uživatele.';
                } elseif ($status === 'accepted') {
                    if ($order['status'] === 'accepted') {
                        $error = 'Objednávka už je přijatá.';
                        break;
                    }

                    $pdo->prepare('UPDATE orders SET status = "accepted", accepted_at = ?, admin_note = ? WHERE id = ?')
                        ->execute([time(), $note, $oid]);

                    $days = Settings::int('accept_expiry_days');
                    $ok = 'Objednávka přijata ke zpracování.'
                        . ($days > 0 ? " Bez zaplacení se zruší za $days dní." : '');

                    $buyer = Auth::byId((int) $order['user_id']);
                    if ($buyer && Mailer::enabled()) {
                        Notify::$lang = Auth::userLang($buyer);
                        $order['accepted_at'] = time();
                        [$sent] = Mailer::notify(
                            (string) $buyer['email'],
                            Notify::orderAccepted($order, $days, orderStatusUrl($order)),
                            'order_accepted'
                        );
                        if ($sent) { $ok .= ' Zákazník informován.'; }
                    }
                } elseif ($status === 'paid') {
                    // Marking an order paid is what grants its rewards -
                    // credits and/or subscription months - and it only ever
                    // happens once per order.
                    Orders::fulfil($order);
                    $pdo->prepare('UPDATE orders SET status = "paid", paid_at = ?, admin_note = ? WHERE id = ?')
                        ->execute([time(), $note, $oid]);

                    $buyer = Auth::byId((int) $order['user_id']);
                    if ($buyer && Mailer::enabled()) {
                        Notify::$lang = Auth::userLang($buyer);
                        [$sent, $mErr] = Mailer::notify(
                            (string) $buyer['email'],
                            ((int)($order['sub_days'] ?? 0) > 0
                                ? Notify::orderTimePaid($order, orderStatusUrl($order), invoiceUrl($order))
                                : Notify::orderPaid($order, Credits::balance((int) $buyer['id']),
                                              orderStatusUrl($order), invoiceUrl($order))),
                            'order_paid'
                        );
                        $ok = $sent
                            ? ((int)($order['sub_days'] ?? 0) > 0
                                ? 'Objednávka zaplacena, časový balíček čeká na aktivaci a zákazník byl informován.'
                                : 'Objednávka zaplacena, kredity připsány a zákazník informován.')
                            : ((int)($order['sub_days'] ?? 0) > 0
                                ? 'Objednávka zaplacena, časový balíček čeká na aktivaci, ale e-mail se nepodařilo odeslat: ' . $mErr
                                : 'Objednávka zaplacena a kredity připsány, ale e-mail se nepodařilo odeslat: ' . $mErr);
                    } else {
                        $ok = (int)($order['sub_days'] ?? 0) > 0 ? 'Objednávka zaplacena, časový balíček čeká na aktivaci.' : 'Objednávka zaplacena a kredity připsány.';
                    }
                } elseif ($status === 'cancelled') {
                    // The row is kept and marked, never deleted, so the
                    // history stays auditable. The reason is optional.
                    $pdo->prepare('UPDATE orders SET status = "cancelled", cancelled_at = ?, admin_note = ? WHERE id = ?')
                        ->execute([time(), $note, $oid]);

                    $buyer = Auth::byId((int) $order['user_id']);
                    if ($buyer && Mailer::enabled()) {
                        Notify::$lang = Auth::userLang($buyer);
                        [$sent, $mErr] = Mailer::notify(
                            (string) $buyer['email'],
                            Notify::orderCancelled($order, $note, false, orderStatusUrl($order)),
                            'order_cancelled'
                        );
                        $ok = $sent
                            ? 'Objednávka zrušena a zákazník informován.'
                            : 'Objednávka zrušena, ale e-mail se nepodařilo odeslat: ' . $mErr;
                    } else {
                        $ok = 'Objednávka zrušena.';
                    }
                } elseif ($status === 'pending') {
                    // Only the note can change while it stays pending.
                    $pdo->prepare('UPDATE orders SET admin_note = ? WHERE id = ?')
                        ->execute([$note, $oid]);
                    $ok = 'Poznámka uložena.';
                } else {
                    $error = 'Neznámý stav objednávky.';
                }
                break;

            case 'resend_invoice':
                // Re-sends the paid-order confirmation, which carries the
                // receipt link, to the account it belongs to.
                $st = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
                $st->execute([(int) ($_POST['id'] ?? 0)]);
                $order = $st->fetch();
                $buyer = $order ? Auth::byId((int) $order['user_id']) : null;

                if (!$order || $order['status'] !== 'paid') {
                    $error = 'Doklad lze poslat jen k zaplacené objednávce.';
                } elseif (!$buyer) {
                    $error = 'Zákazník k objednávce neexistuje.';
                } elseif (!Mailer::enabled()) {
                    $error = 'Odesílání e-mailů není nastavené.';
                } else {
                    Notify::$lang = Auth::userLang($buyer);
                    [$sent, $mErr] = Mailer::notify(
                        (string) $buyer['email'],
                        ((int)($order['sub_days'] ?? 0) > 0
                            ? Notify::orderTimePaid($order, orderStatusUrl($order), invoiceUrl($order))
                            : Notify::orderPaid($order, Credits::balance((int) $buyer['id']),
                                          orderStatusUrl($order), invoiceUrl($order))),
                        'invoice_resend'
                    );
                    $ok    = $sent ? 'Doklad znovu odeslán na ' . $buyer['email'] . '.' : '';
                    $error = $sent ? '' : 'Odeslání selhalo: ' . $mErr;
                }
                break;

            case 'resend_order_mail':
                $st = $pdo->prepare('SELECT * FROM orders WHERE id = ?');
                $st->execute([(int) ($_POST['id'] ?? 0)]);
                $order = $st->fetch();
                $buyer = $order ? Auth::byId((int) $order['user_id']) : null;

                if (!$order || !$buyer) {
                    $error = 'Objednávka nebo zákazník neexistuje.';
                } elseif ($order['status'] !== 'pending') {
                    // Vyřízená i zrušená objednávka je uzavřená; opakovaná
                    // zpráva o ní by zákazníka jen mátla.
                    $error = 'Znovu odeslat lze jen u čekající objednávky.';
                } elseif (!Mailer::enabled()) {
                    $error = 'Odesílání e-mailů není nastavené.';
                } else {
                    Notify::$lang = Auth::userLang($buyer);
                    $link = orderStatusUrl($order);
                    $msg = match ($order['status']) {
                        'paid'      => Notify::orderPaid($order, Credits::balance((int) $buyer['id']), $link, invoiceUrl($order)),
                        'cancelled' => Notify::orderCancelled($order, (string) $order['admin_note'], false, $link),
                        default     => Notify::orderCreated($order, Paypal::linkFor($order),
                                            Lang::instructions(), $link),
                    };
                    [$sent, $mErr] = Mailer::notify((string) $buyer['email'], $msg, 'order_resend');
                    if ($sent) { $ok = 'Zpráva znovu odeslána na ' . $buyer['email'] . '.'; }
                    else       { $error = 'Odeslání selhalo: ' . $mErr; }
                }
                break;

            case 'backup':
                // Streamed before any reset, because "I meant the other
                // scope" is the most likely thing to go wrong here.
                $path = Db::path();
                if (!is_file($path)) {
                    $error = 'Databáze nenalezena.';
                    break;
                }
                // A checkpoint folds the write-ahead log into the main file,
                // so the copy is complete on its own.
                try { $pdo->exec('PRAGMA wal_checkpoint(TRUNCATE)'); } catch (Throwable $e) {}

                header('Content-Type: application/octet-stream');
                // Named after the site, so several installs do not produce a
                // pile of identically named files in one downloads folder.
                $slug = preg_replace('/[^a-z0-9]+/i', '-', Settings::get('site_name')) ?? 'h3d';
                $slug = trim(strtolower($slug), '-') ?: 'h3d';

                header('Content-Disposition: attachment; filename="' . $slug . '-'
                    . date('Ymd-His') . '.sqlite"');
                header('Content-Length: ' . filesize($path));
                header('Cache-Control: no-store');
                readfile($path);
                exit;

            case 'restore':
                // Overwriting the live database from an upload is the one door
                // the app otherwise keeps shut, so it is walled in: an admin
                // password, a file that is provably our own SQLite database,
                // and a copy of what is being replaced taken first. Any doubt
                // and the current site is left exactly as it was.
                $pass = (string) ($_POST['password'] ?? '');
                if (!password_verify($pass, (string) $admin['pass_hash'])) {
                    $error = 'Heslo nesouhlasí.';
                    break;
                }
                if (trim((string) ($_POST['confirm'] ?? '')) !== 'OBNOVIT') {
                    $error = 'Do potvrzovacího pole napiš OBNOVIT.';
                    break;
                }

                $up = $_FILES['dbfile'] ?? null;
                if (!$up || ($up['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                    $error = 'Nevybral jsi žádný soubor se zálohou.';
                    break;
                }
                if (($up['error'] ?? 1) !== UPLOAD_ERR_OK) {
                    // The most common cause is a file larger than the server's
                    // upload limit, which fails before this code even runs.
                    $error = 'Nahrání selhalo (kód ' . (int) $up['error']
                           . '). Nejspíš je soubor větší, než server pro upload dovolí.';
                    break;
                }

                $tmp  = (string) $up['tmp_name'];
                $size = (int) $up['size'];

                if (!is_uploaded_file($tmp)) {
                    $error = 'Neplatný upload.';
                    break;
                }
                if ($size <= 0 || $size > 128 * 1024 * 1024) {
                    $error = 'Soubor je prázdný nebo přes 128 MB.';
                    break;
                }

                // Right magic bytes, our core tables, and a clean integrity
                // check - all read from a throwaway handle before the live
                // file is touched, so a wrong or corrupt file cannot brick it.
                $magic = (string) file_get_contents($tmp, false, null, 0, 16);
                if (strncmp($magic, "SQLite format 3\0", 16) !== 0) {
                    $error = 'Tohle není databáze SQLite.';
                    break;
                }
                try {
                    $probe = new PDO('sqlite:' . $tmp);
                    $probe->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                    $core = $probe->query(
                        "SELECT COUNT(*) FROM sqlite_master
                         WHERE type='table' AND name IN ('users','settings')"
                    )->fetchColumn();
                    if ((int) $core < 2) {
                        throw new RuntimeException('chybí základní tabulky');
                    }
                    $integrity = (string) $probe->query('PRAGMA integrity_check')->fetchColumn();
                    $probe = null;
                    if (strtolower($integrity) !== 'ok') {
                        throw new RuntimeException('integrity_check: ' . $integrity);
                    }
                } catch (Throwable $e) {
                    $error = 'Záloha není použitelná (' . $e->getMessage()
                           . '). Web zůstal beze změny.';
                    break;
                }

                $dbPath = Db::path();
                Db::close();

                // Keep what is being replaced, in the web-inaccessible data
                // folder, so even a valid-but-wrong backup is recoverable.
                if (is_file($dbPath)) {
                    @copy($dbPath, dirname($dbPath) . '/before-restore-' . date('Ymd-His') . '.sqlite');
                }
                // Drop the write-ahead sidecars so they are not replayed onto
                // the freshly installed file.
                foreach (['-wal', '-shm'] as $sfx) {
                    if (is_file($dbPath . $sfx)) {
                        @unlink($dbPath . $sfx);
                    }
                }

                if (!@move_uploaded_file($tmp, $dbPath) && !@copy($tmp, $dbPath)) {
                    $error = 'Zápis nové databáze selhal. Web běží na původní.';
                    break;
                }

                // The restored database has its own accounts, so this session
                // may not exist in it. Drop it and sign in against the new data.
                Auth::logout();
                header('Location: ../login.php');
                exit;

            case 'reset':
                $phrase = trim((string) ($_POST['confirm'] ?? ''));
                $pass   = (string) ($_POST['password'] ?? '');
                $scopes = array_values(array_intersect(
                    (array) ($_POST['scopes'] ?? []),
                    array_keys(Reset::SCOPES)
                ));
                $everything = !empty($_POST['everything']);

                if ($phrase !== 'RESET') {
                    $error = 'Do potvrzovacího pole napiš RESET.';
                } elseif (!password_verify($pass, (string) $admin['pass_hash'])) {
                    $error = 'Heslo nesouhlasí.';
                } elseif (!$everything && !$scopes) {
                    $error = 'Nevybral jsi nic ke smazání.';
                } elseif ($everything) {
                    Reset::wipeEverything();
                    Auth::logout();
                    header('Location: ../register.php');
                    exit;
                } else {
                    $done = Reset::run($scopes, (int) $admin['id']);
                    $parts = [];
                    foreach ($done as $k => $v) {
                        $parts[] = Reset::SCOPES[$k][0] . ': ' . $v;
                    }
                    $ok = 'Hotovo. ' . implode('; ', $parts) . '.';
                }
                break;

            case 'save_currency':
                try {
                    $code = (string) ($_POST['code'] ?? '');
                    // Position belongs to the drag-and-drop list, so editing a
                    // field keeps whatever place the row already has; a new
                    // currency lands at the end.
                    $known = Money::get($code);
                    $csort = $known
                        ? (int) $known['sort']
                        : 1 + (int) $pdo->query('SELECT COALESCE(MAX(sort), 0) FROM currencies')->fetchColumn();

                    Money::save(
                        $code,
                        (string) ($_POST['symbol'] ?? ''),
                        (string) ($_POST['rate'] ?? '1'),
                        (int) ($_POST['decimals'] ?? 2),
                        !empty($_POST['active']),
                        $csort
                    );
                    $ok = 'Měna uložena.';
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
                break;

            case 'delete_currency':
                try {
                    Money::delete((string) ($_POST['code'] ?? ''));
                    $ok = 'Měna smazána.';
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                }
                break;

            case 'reorder_currencies':
                // The card list posts the codes in their new visual order.
                $codes = $_POST['order'] ?? [];
                if (is_array($codes) && $codes) {
                    $st  = $pdo->prepare('UPDATE currencies SET sort = ? WHERE code = ?');
                    $pos = 1;
                    foreach ($codes as $code) {
                        $code = strtoupper(trim((string) $code));
                        if (Money::isCode($code)) {
                            $st->execute([$pos++, $code]);
                        }
                    }
                    $ok = 'Pořadí uloženo.';
                } else {
                    $error = 'Nic k seřazení.';
                }
                break;

            case 'save_payment':
                // The dialog ticks the currencies it allows; empty means all.
                $curs = $_POST['currencies'] ?? '';
                if (is_array($curs)) {
                    $curs = implode(',', array_map('strval', $curs));
                }

                // Same rule as packages and currencies: the order is the list's
                // to decide, so saving a field never moves the row.
                $payId = (int) ($_POST['id'] ?? 0);
                $known = $payId > 0 ? Payments::find($payId) : null;
                $psort = $known
                    ? (int) $known['sort']
                    : 1 + (int) $pdo->query('SELECT COALESCE(MAX(sort), 0) FROM payment_methods')->fetchColumn();

                Payments::save(
                    $payId,
                    (string) ($_POST['kind'] ?? 'other'),
                    (string) ($_POST['label'] ?? ''),
                    (string) ($_POST['target'] ?? ''),
                    (string) ($_POST['note'] ?? ''),
                    !empty($_POST['active']),
                    $psort,
                    (string) $curs
                );
                $ok = 'Platební možnost uložena.';
                break;

            case 'delete_payment':
                Payments::delete((int) ($_POST['id'] ?? 0));
                $ok = 'Platební možnost smazána.';
                break;

            case 'reorder_payments':
                $ids = $_POST['order'] ?? [];
                if (is_array($ids) && $ids) {
                    $st  = $pdo->prepare('UPDATE payment_methods SET sort = ? WHERE id = ?');
                    $pos = 1;
                    foreach ($ids as $pid) {
                        $st->execute([$pos++, (int) $pid]);
                    }
                    $ok = 'Pořadí uloženo.';
                } else {
                    $error = 'Nic k seřazení.';
                }
                break;

            case 'test_mail':
                $to = trim((string) ($_POST['test_to'] ?? ''));
                if ($to === '') {
                    $error = 'Zadej adresu, na kterou se má zkušební zpráva poslat.';
                } else {
                    [$sent, $mErr] = Mailer::trySend(
                        $to,
                        'SMTP test - ' . Settings::get('site_name'),
                        "This is a test message.\n\nIf it arrived, SMTP is configured correctly.\n"
                    );
                    $ok    = $sent ? 'Zkušební zpráva odeslána na ' . $to . '.' : '';
                    $error = $sent ? '' : 'Odeslání selhalo: ' . $mErr;
                }
                break;

            case 'hardcore_verify_test':
                $to = Auth::normaliseEmail((string) ($_POST['hardcore_email'] ?? ''));
                if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
                    $error = 'Zadej platný e-mail účtu, který už v databázi existuje.';
                } else {
                    $u = Auth::byEmail($to);
                    if (!$u) {
                        $error = 'Účet s tímto e-mailem nebyl nalezen. Hardcore test záměrně používá přesně stejnou Verify::sendLink() cestu jako veřejné ověření.';
                    } else {
                        [$sent, $mErr] = Verify::sendLink($u, true);
                        $report = Mailer::lastDebugReport();
                        $ok = $sent
                            ? 'HARDCORE VERIFY TEST: e-mail byl odeslán. Debug ID: ' . Mailer::lastDebugId()
                            : 'HARDCORE VERIFY TEST SELHAL. Debug ID: ' . Mailer::lastDebugId();
                        $error = $sent ? '' : $mErr;
                    }
                }
                break;

            case 'resend_verify':
                $u = Auth::byId((int) ($_POST['uid'] ?? 0));
                [$sent, $msg] = Verify::sendLink($u, true);
                if ($sent) { $ok = 'Ověřovací e-mail odeslán na ' . $u['email'] . '.'; }
                else       { $error = $msg; }
                break;

            case 'mark_verified':
                // Manual override for the case where mail simply will not
                // reach someone; the bonus is released as if they clicked.
                $u = Auth::byId((int) ($_POST['uid'] ?? 0));
                if ($u && empty($u['verified_at'])) {
                    $pdo->prepare('UPDATE users SET verified_at = ?, verify_token = NULL WHERE id = ?')
                        ->execute([time(), (int) $u['id']]);
                    $givenDays = 0;
                    $given = Verify::grantBonus(Auth::byId((int) $u['id']), $givenDays);
                    // Manual verification counts like the real thing - the
                    // referral reward for both sides is released too.
                    $refGiven = Referral::rewardOnVerify(Auth::byId((int) $u['id']));
                    $ok = 'Účet označen jako ověřený'
                        . ($given > 0 ? ', připsáno ' . Cred::fmtCs($given) . ' kreditů' : '')
                        . ($givenDays > 0 ? ', spuštěno ' . Orders::durationLabel($givenDays, 'cs') . ' neomezených exportů' : '')
                        . ($refGiven > 0 ? ', + referral bonus ' . Cred::fmtCs($refGiven) . ' oběma stranám' : '')
                        . '.';
                } else {
                    $error = 'Účet je už ověřený nebo neexistuje.';
                }
                break;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }

    // Live-save requests (the package rows) want an answer, not a redirect:
    // the page stays put and only the little status marker changes. Only our
    // own fetch sends ajax=1, so a plain JSON reply is safe here.
    if (($_POST['ajax'] ?? '') === '1') {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode([
            'ok'      => $error === '',
            'message' => $error !== '' ? $error : $ok,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Turn the POST into a GET so refreshing repeats the view, not the
    // action. Without it, F5 after marking an order paid credited the
    // customer twice. Anything that already sent its own response - the
    // backup download, the reset redirect - has exited before this point.
    //
    // The tab is kept so you land back where you were working. Filters on
    // the orders tab are kept for the same reason.
    $returnTab = (string) ($_POST['tab'] ?? $tab);
    $allowedReturnTabs = [
        'orders','overview','settings','studio','accounts','seller','look','exports',
        'payments','codes','users','billing','promotions','invoices','mail','features',
        'activity','adminlog','messages','reset'
    ];
    if (!in_array($returnTab, $allowedReturnTabs, true)) {
        $returnTab = $tab;
    }
    $back = '?tab=' . urlencode($returnTab);
    foreach (['q', 'status', 'state', 'from', 'to', 'page', 'user_page', 'focus', 'kind'] as $keep) {
        if (($_GET[$keep] ?? '') !== '') {
            $back .= '&' . $keep . '=' . urlencode((string) $_GET[$keep]);
        }
    }
    // An action fired from inside a user dialog says so, and the dialog is
    // reopened on the other side of the redirect instead of vanishing.
    $reopen = (string) ($_POST['reopen'] ?? '');
    if (preg_match('/^manage-\d+$/', $reopen)) {
        $back .= '&reopen=' . urlencode($reopen);
    } elseif (preg_match('/^support-(\d+)$/', $reopen, $match)) {
        $back .= '&manage=' . (int) $match[1];
    }

    // One place records every action, so nothing can be added later and
    // quietly skip the log. What was touched is taken from whichever id the
    // action carried; the outcome message says how it went.
    admin_log_write($admin, $action, $_POST, $ok, $error);

    redirect_after_post($back, $ok, $error);
}

/*
 * Everything that writes to the session is behind us - the POST handler
 * redirects, so nothing below this line changes it. Handing the lock back
 * now means a slow screen only makes itself slow: with the lock held, the
 * panel blocked every other request from the same browser, so clicking to
 * the next tab looked like the whole site had frozen.
 */
Auth::releaseSession();

// No scheduler here, but expiry is relevant only while working with orders.
// Running it for every System/Users/Messages tab could additionally send
// e-mails before the page was rendered, making unrelated navigation slow.
if ($tab === 'orders') {
    Orders::expireStale();
}

/** Renders a checkbox that also submits a 0 when unticked. */
if (!function_exists('toggle')):
function toggle(string $key, string $label, string $hint = ''): void
{
    $on = Settings::bool($key);
    echo '<div class="switch"><input type="checkbox" id="' . e($key) . '" name="' . e($key) . '" value="1"'
        . ($on ? ' checked' : '') . '><label for="' . e($key) . '" style="margin:0"><span>' . e($label) . '</span></label></div>';
    if ($hint !== '') {
        echo '<div class="hint" style="margin:-4px 0 10px 26px">' . e($hint) . '</div>';
    }
}
endif;

/*
 * User detail fragment: the full-screen "Spravovat" dialog loads its data
 * tabs from here on open, so the users list stays light no matter how much
 * history an account has. Returns bare HTML panes, no page chrome.
 */
if (!$ordersOnly && ($_GET['tab'] ?? '') === 'users'
    && isset($_GET['detail'], $_GET['fragment'])) {

    $du = Auth::byId((int) $_GET['detail']);
    if (!$du) {
        http_response_code(404);
        exit('<p class="hint">Uživatel neexistuje.</p>');
    }
    $duId = (int) $du['id'];

    // Shared data for several panes, loaded once.
    $st = $pdo->prepare('SELECT * FROM ledger WHERE user_id = ? ORDER BY id DESC LIMIT 150');
    $st->execute([$duId]);
    $led = $st->fetchAll();

    $st = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC');
    $st->execute([$duId]);
    $ords = $st->fetchAll();

    $st = $pdo->prepare(
        'SELECT cu.id AS use_id, cu.created_at, c.label, c.code, c.credits, c.sub_days
         FROM code_uses cu JOIN codes c ON c.id = cu.code_id
         WHERE cu.user_id = ? ORDER BY cu.created_at DESC'
    );
    $st->execute([$duId]);
    $uses = $st->fetchAll();

    // Admin grants already reversed - their button must not show again.
    $stornoed = [];
    foreach ($led as $l) {
        if (preg_match('/^storno #(\d+)$/', (string) ($l['ref'] ?? ''), $m)) {
            $stornoed[(int) $m[1]] = true;
        }
    }

    /* ---- Přehled ---- */
    echo '<div class="ud-pane" data-udpane="prehled" hidden>';
    $subU  = Auth::subscribedUntil($du);
    $stats = Referral::statsFor($duId);
    $refBy = null;
    if (!empty($du['referred_by'])) {
        $rb    = Auth::byId((int) $du['referred_by']);
        $refBy = $rb ? (string) $rb['email'] : 'smazaný účet';
    }
    $rows = [
        'ID účtu'             => (string) $duId,
        'E-mail'              => (string) $du['email'],
        'Čeká na nový e-mail' => trim((string) ($du['pending_email'] ?? '')) !== '' ? (string) $du['pending_email'] : null,
        'Přezdívka'           => trim((string) ($du['nickname'] ?? '')) !== '' ? (string) $du['nickname'] : '-',
        'Role'                => Auth::ROLES[Auth::role($du)] ?? Auth::role($du),
        'Registrace'          => when((int) $du['created_at']),
        'E-mail ověřen'       => $du['verified_at'] !== null ? when((int) $du['verified_at']) : 'NE - čeká na ověření',
        'Poslední přihlášení' => when($du['last_login_at'] !== null ? (int) $du['last_login_at'] : null),
        'Jazyk e-mailů'       => strtoupper(Auth::userLang($du)),
        'Kredity'             => Cred::fmtCs((int) $du['credits']),
        'Časový plán'         => $subU !== null ? 'aktivní do ' . date('d.m.Y H:i', $subU) : 'žádný',
        'Blokace'             => (int) $du['is_blocked'] === 1 ? 'ZABLOKOVÁN' : 'ne',
        'Referral kód'        => trim((string) ($du['ref_code'] ?? '')) !== '' ? (string) $du['ref_code'] : '(zatím nevygenerován)',
        'Získáno doporučením' => Cred::fmtCs((int) $stats['earned']) . ' kreditů',
    ];
    echo '<table class="ud-kv">';
    foreach ($rows as $k => $v) {
        if ($v === null) { continue; }
        echo '<tr><th>' . e($k) . '</th><td>' . e($v) . '</td></tr>';
    }
    echo '</table>';

    // -- referraly: kdo ho pozval a koho pozval on, s proklikem na účty --
    echo '<div class="ud-card" style="margin-top:14px"><h4 class="ud-h">Referraly</h4>';
    if (!empty($du['referred_by'])) {
        $rb = Auth::byId((int) $du['referred_by']);
        echo '<p style="margin:0 0 10px">Pozval ho: '
           . ($rb
              ? '<a class="ud-userlink" href="?tab=users&q=' . urlencode((string) $rb['email']) . '&reopen=manage-' . (int) $rb['id'] . '">' . e($rb['email']) . '</a>'
              : '<span class="hint">smazaný účet</span>')
           . '</p>';
    }
    $st = $pdo->prepare(
        'SELECT id, email, nickname, created_at, verified_at, ref_rewarded
         FROM users WHERE referred_by = ? ORDER BY id DESC LIMIT 50'
    );
    $st->execute([$duId]);
    $invitees = $st->fetchAll();
    if (!$invitees) {
        echo '<p class="hint" style="margin:0">Zatím nikoho nepozval.</p>';
    } else {
        echo '<div class="table-scroll"><table class="ud-table"><thead><tr><th>Registrace</th><th>Uživatel</th><th>Přezdívka</th><th>Stav</th></tr></thead><tbody>';
        foreach ($invitees as $iv) {
            echo '<tr><td>' . e(when((int) $iv['created_at'])) . '</td>'
               . '<td><a class="ud-userlink" href="?tab=users&q=' . urlencode((string) $iv['email']) . '&reopen=manage-' . (int) $iv['id'] . '">' . e($iv['email']) . '</a></td>'
               . '<td>' . e(trim((string) ($iv['nickname'] ?? '')) !== '' ? $iv['nickname'] : '-') . '</td>'
               . '<td>' . ($iv['verified_at'] !== null || !empty($iv['ref_rewarded'])
                    ? '<span class="tag on">ověřen' . (!empty($iv['ref_rewarded']) ? ' · odměna vyplacena' : '') . '</span>'
                    : '<span class="tag warn">čeká na ověření</span>') . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';

    echo '</div>';

    /* ---- Čas / Kredity / Kódy - tři karty na jedné záložce.
            Zrušené položky zůstávají v tabulce se svým původním datem,
            jen zešednou a dostanou štítek "zrušeno". ---- */
    echo '<div class="ud-pane" data-udpane="kredity" hidden>';

    // Which grants were already taken back. New revocations carry a machine
    // token [g:…]; the wording-based fallback only covers old records.
    $timeRevoked = [];
    $tokRevoked  = [];
    $codeStorno  = [];
    // Oldest first, so a later restore marker [g~:…] overrides the
    // revocation [g:…] it undoes.
    foreach (array_reverse($led) as $l) {
        $ref = (string) ($l['ref'] ?? '');
        if (preg_match_all('/\[g:([A-Za-z0-9._:-]+)\]/', $ref, $mAll)) {
            foreach ($mAll[1] as $tk) { $tokRevoked[$tk] = true; }
        }
        if (preg_match_all('/\[g~:([A-Za-z0-9._:-]+)\]/', $ref, $mAll)) {
            foreach ($mAll[1] as $tk) { unset($tokRevoked[$tk]); }
        }
        if (preg_match('/^storno kódu #(\d+)/u', $ref, $m)) { $codeStorno[(int) $m[1]] = true; }
        if ((string) $l['reason'] === 'sub_cancel' && preg_match('/\(([^()]+)\)/u', $ref, $m)) {
            $key = $m[1];
            if (preg_match('/^refund (.+)$/u', $key, $mm)) {
                $timeRevoked['Objednávka ' . $mm[1]] = true;
            } elseif (preg_match('/^storno kódu #(\d+)/u', $key, $mm)) {
                $codeStorno[(int) $mm[1]] = true;
            } else {
                $timeRevoked[$key] = true;
            }
        }
    }

    /* --- karta: Časové plány --- */
    echo '<div class="ud-card"><h4 class="ud-h">Časové plány</h4>';
    $subU = Auth::subscribedUntil($du);
    if ($subU !== null) {
        // What cancelling would owe back, said out loud before the click
        // rather than left for somebody to work out from the order list.
        $due     = Orders::planRefundDue($du);
        $dueLine = $due['cents'] > 0
            ? 'Při zrušení je k vrácení ' . money($due['cents'], $due['currency'])
                . ' za ' . $due['days'] . ' nevyužitých dní ('
                . implode(', ', array_map(
                    static fn($p) => e($p['ref']) . ' ' . money($p['cents'], $due['currency']),
                    $due['parts'])) . ').'
            : 'Za zbývající dny není co vracet - nestojí za nimi zaplacená objednávka.';

        echo '<div class="ud-cas-head"><p style="margin:0">Plán je teď aktivní do <strong>' . e(date('d.m.Y H:i', $subU)) . '</strong>.</p>'
           . '<form method="post" style="margin:0">'
           . Auth::csrfField()
           . '<input type="hidden" name="action" value="cancel_subscription">'
           . '<input type="hidden" name="user_id" value="' . $duId . '">'
           . '<input type="hidden" name="reopen" value="manage-' . $duId . '">'
           . '<button class="btn small danger" type="submit"'
           . ' data-confirm="Zrušit celý časový plán? Neomezené exporty okamžitě skončí. '
           . e($dueLine) . '"'
           . ' data-confirm-ok="Zrušit plán">Zrušit celý plán</button></form></div>'
           . '<p class="hint" style="margin:6px 0 0">' . $dueLine . '</p>';
    } else {
        echo '<p class="hint">Účet teď nemá aktivní časový plán.</p>';
    }

    $grants = [];
    foreach ($ords as $o) {
        if ((int) ($o['sub_days'] ?? 0) > 0 && in_array($o['status'], [Orders::PAID, Orders::REFUND, Orders::REFUNDED], true)) {
            $grants[] = [
                'at'    => (int) ($o['paid_at'] ?? $o['created_at']),
                'what'  => 'Objednávka ' . $o['reference'],
                'key'   => 'Objednávka ' . $o['reference'],
                'tok'   => 'o:' . preg_replace('/[^A-Za-z0-9._-]/', '', (string) $o['reference']),
                'oid'   => (int) $o['id'],
                'use'   => null,
                'days'  => (int) $o['sub_days'],
                'price' => money((int) $o['price_cents'], $o['currency'] ?? null),
                'gone'  => $o['status'] !== Orders::PAID ? 'refundováno' : '',
            ];
        }
    }
    foreach ($uses as $cu) {
        if ((int) ($cu['sub_days'] ?? 0) > 0) {
            $cn = trim((string) ($cu['label'] ?? '')) !== '' ? $cu['label'] : $cu['code'];
            $grants[] = [
                'at'    => (int) $cu['created_at'],
                'what'  => 'Kód ' . $cn,
                'key'   => '',
                'tok'   => '',
                'use'   => (int) $cu['use_id'],
                'days'  => (int) $cu['sub_days'],
                'price' => 'zdarma (kód)',
                'gone'  => '',
            ];
        }
    }
    foreach ($led as $l) {
        if ((string) $l['reason'] === 'sub_set') {
            $ref  = (string) ($l['ref'] ?? '');
            // Restore bookkeeping rides the ledger but is not a grant of its
            // own - the revived order row already shows those days.
            if (str_starts_with($ref, 'obnova refundu')) { continue; }
            $days = preg_match('/\(\+(\d+) dní\)/u', $ref, $m) ? (int) $m[1] : 0;
            $key  = 'Ručně adminem z ' . when((int) $l['created_at']);
            $grants[] = [
                'at'    => (int) $l['created_at'],
                'what'  => 'Ručně adminem (' . $ref . ')',
                'key'   => $key,
                'tok'   => 'l:' . (int) $l['id'],
                'use'   => null,
                'days'  => $days,
                'price' => '-',
                'gone'  => '',
            ];
        }
    }
    usort($grants, static fn($a, $b) => $b['at'] <=> $a['at']);

    if (!$grants) { echo '<p class="hint">Žádné časové balíčky.</p>'; }
    else {
        echo '<div class="table-scroll"><table class="ud-table"><thead><tr><th>Aplikováno</th><th>Zdroj</th><th class="num">Dní</th><th class="num">Cena</th><th>Stav</th></tr></thead><tbody>';
        foreach ($grants as $g) {
            // A grant with a machine token trusts ONLY the token trail (so a
            // restore really restores); the wording match covers old records.
            $revoked = $g['gone'] !== ''
                || ($g['tok'] !== ''
                    ? isset($tokRevoked[$g['tok']])
                    : ($g['key'] !== '' && isset($timeRevoked[$g['key']])))
                || ($g['use'] !== null && isset($codeStorno[$g['use']]));
            echo '<tr' . ($revoked ? ' class="is-revoked"' : '') . '>'
               . '<td>' . e(when($g['at'])) . '</td><td>' . e($g['what']) . '</td>'
               . '<td class="num">' . ($g['days'] > 0 ? (int) $g['days'] : '-') . '</td>'
               . '<td class="num">' . e($g['price']) . '</td><td>';
            if ($revoked) {
                echo '<span class="tag off">' . ($g['gone'] !== '' ? 'refundováno' : 'zrušeno') . '</span>';
            } elseif ($g['use'] !== null) {
                echo '<span class="hint">ruší se v kartě Kódy</span>';
            } elseif (isset($g['oid'])) {
                // Bought time comes back only through a refund, so the order
                // is closed at the same moment the days are taken.
                echo '<form method="post" onsubmit="return confirm(\'Refundovat ' . e($g['what'])
                   . '? Nevyužité dny se odeberou, objednávka se zařadí do fronty refundů a peníze pak vrať ručně.\')">'
                   . Auth::csrfField()
                   . '<input type="hidden" name="action" value="refund_order">'
                   . '<input type="hidden" name="reopen" value="manage-' . $duId . '">'
                   . '<input type="hidden" name="order_id" value="' . (int) $g['oid'] . '">'
                   . '<button class="btn small danger" type="submit">Refundovat</button></form>';
            } elseif ($g['days'] > 0 && $subU !== null) {
                echo '<form method="post" onsubmit="return confirm(\'Odebrat ' . (int) $g['days'] . ' dní ('
                   . e($g['what']) . ')? Plán se o tolik zkrátí.\')">'
                   . Auth::csrfField()
                   . '<input type="hidden" name="action" value="revoke_time">'
                   . '<input type="hidden" name="reopen" value="manage-' . $duId . '">'
                   . '<input type="hidden" name="user_id" value="' . $duId . '">'
                   . '<input type="hidden" name="days" value="' . (int) $g['days'] . '">'
                   . '<input type="hidden" name="source" value="' . e($g['key']) . '">'
                   . '<input type="hidden" name="grant" value="' . e($g['tok']) . '">'
                   . '<button class="btn small danger" type="submit">Odebrat ' . (int) $g['days'] . ' dní</button></form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';

    /* --- karta: Kredity --- */
    echo '<div class="ud-card"><h4 class="ud-h">Kredity · zůstatek ' . e(Cred::fmtCs((int) $du['credits'])) . '</h4>';
    // Time bookkeeping rows live in the card above; here only real credits.
    $ledCredits = array_values(array_filter($led, static fn($l) =>
        !in_array((string) $l['reason'], ['sub_set', 'sub_cancel'], true)));
    if (!$ledCredits) { echo '<p class="hint">Žádné pohyby kreditů.</p>'; }
    else {
        echo '<div class="table-scroll"><table class="ud-table"><thead><tr><th>Kdy</th><th>Důvod</th><th>Pozn.</th><th class="num">Změna</th><th class="num">Zůstatek</th><th>Stav</th></tr></thead><tbody>';
        foreach ($ledCredits as $l) {
            $d    = (int) $l['delta'];
            $gone = isset($stornoed[(int) $l['id']]);
            echo '<tr' . ($gone ? ' class="is-revoked"' : '') . '>'
               . '<td>' . e(when((int) $l['created_at'])) . '</td><td>' . e(Lang::reason((string) $l['reason']))
               . '</td><td class="hint">' . e(ref_label($l['ref'] ?? null)) . '</td>'
               . '<td class="num" style="color:' . ($d < 0 ? 'var(--bad)' : ($d > 0 ? 'var(--good)' : '#68707a')) . '">'
               . ($d === 0 ? '·' : ($d > 0 ? '+' : '-') . e(Cred::fmtCs(abs($d)))) . '</td>'
               . '<td class="num">' . e(Cred::fmtCs((int) $l['balance_after'])) . '</td><td>';
            if ($gone) {
                echo '<span class="tag off">zrušeno</span>';
            } elseif ($d > 0 && in_array((string) $l['reason'], ['admin', 'admin_bulk'], true)) {
                echo '<form method="post" onsubmit="return confirm(\'Odebrat přídavek ' . e(Cred::fmtCs($d)) . ' kreditů? Odečte se, co zůstatek ještě kryje.\')">'
                   . Auth::csrfField()
                   . '<input type="hidden" name="action" value="revoke_credits">'
                   . '<input type="hidden" name="reopen" value="manage-' . $duId . '">'
                   . '<input type="hidden" name="user_id" value="' . $duId . '">'
                   . '<input type="hidden" name="ledger_id" value="' . (int) $l['id'] . '">'
                   . '<button class="btn small danger" type="submit">Odebrat</button></form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';

    /* --- karta: Historie denních kreditů --- */
    $dailyHistory = DailyCredits::historyForUser($duId);
    echo '<div class="ud-card daily-credit-history"><h4 class="ud-h">Historie denních kreditů</h4>';
    echo '<p class="hint">Každý kalendářní den je veden samostatně. Při pozdějším přihlášení se doplní všechny chybějící dny a pro každý den se použije sazba, která byla platná na začátku daného dne. Řádek s 0 kredity znamená, že den byl zpracován, ale narazil na strop účtu.</p>';
    if (!$dailyHistory) {
        echo '<p class="hint">Zatím nebyly přiděleny žádné denní kredity.</p>';
    } else {
        echo '<div class="table-scroll"><table class="ud-table"><thead><tr>'
           . '<th>Den</th><th class="num">Sazba</th><th class="num">Připsáno</th>'
           . '<th class="num">Zůstatek</th><th>Přiděleno</th>'
           . '</tr></thead><tbody>';
        foreach ($dailyHistory as $dh) {
            $amount = (int) $dh['amount'];
            $rate   = (int) $dh['rate'];
            echo '<tr>'
               . '<td>' . e($dh['date']) . '</td>'
               . '<td class="num">' . e(Cred::fmtCs($rate)) . '</td>'
               . '<td class="num" style="color:' . ($amount > 0 ? 'var(--good)' : 'var(--muted)') . '">'
               . ($amount > 0 ? '+' : '') . e(Cred::fmtCs($amount)) . '</td>'
               . '<td class="num">' . e(Cred::fmtCs((int) $dh['balance'])) . '</td>'
               . '<td>';
            if ($dh['backfilled']) {
                echo '<span class="tag warn">doplněno zpětně</span>'
                   . '<div class="hint">zpracováno ' . e(when((int) $dh['created_at'])) . '</div>';
            } else {
                echo '<span class="hint">v daný den</span>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';

    /* --- karta: Uplatněné kódy --- */
    echo '<div class="ud-card"><h4 class="ud-h">Uplatněné kódy</h4>';
    if (!$uses) { echo '<p class="hint">Žádné uplatněné kódy.</p>'; }
    else {
        echo '<div class="table-scroll"><table class="ud-table"><thead><tr><th>Uplatněno</th><th>Kód</th><th>Získal</th><th>Stav</th></tr></thead><tbody>';
        foreach ($uses as $cu) {
            $cn   = trim((string) ($cu['label'] ?? '')) !== '' ? $cu['label'] : $cu['code'];
            $bits = [];
            if ((int) $cu['credits'] !== 0) { $bits[] = Cred::fmtCs((int) $cu['credits']) . ' kreditů'; }
            if ((int) ($cu['sub_days'] ?? 0) > 0) { $bits[] = Orders::durationLabel((int) $cu['sub_days'], 'cs') . ' neomezeně'; }
            $gone = isset($codeStorno[(int) $cu['use_id']]);
            echo '<tr' . ($gone ? ' class="is-revoked"' : '') . '>'
               . '<td>' . e(when((int) $cu['created_at'])) . '</td>'
               . '<td class="mono">' . e($cn) . '</td>'
               . '<td>' . e($bits ? implode(' + ', $bits) : '-') . '</td><td>';
            if ($gone) {
                echo '<span class="tag off">zrušeno</span>';
            } else {
                echo '<form method="post" onsubmit="return confirm(\'Odebrat, co dal kód ' . e($cn) . '? Kredity se odečtou (co zůstatek kryje) a případné dny se stáhnou z plánu.\')">'
                   . Auth::csrfField()
                   . '<input type="hidden" name="action" value="revoke_code">'
                   . '<input type="hidden" name="reopen" value="manage-' . $duId . '">'
                   . '<input type="hidden" name="user_id" value="' . $duId . '">'
                   . '<input type="hidden" name="use_id" value="' . (int) $cu['use_id'] . '">'
                   . '<button class="btn small danger" type="submit">Odebrat</button></form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '<p class="hint">Zrušení čehokoli tady se zapisuje do historie účtu i auditu; řádek zůstává se svým datem, jen zešedne.</p>';
    echo '</div>';

    echo '</div>';

    /* ---- Objednávky (s cenami + refund) ---- */
    echo '<div class="ud-pane" data-udpane="objednavky" hidden>';
    if (!$ords) { echo '<p class="hint">Žádné objednávky.</p>'; }
    else {
        echo '<div class="table-scroll"><table class="ud-table"><thead><tr><th>Kdy</th><th>Reference</th><th>Obsah</th><th class="num">Cena</th><th>Stav</th><th></th></tr></thead><tbody>';
        foreach ($ords as $o) {
            $what = (int) ($o['sub_days'] ?? 0) > 0
                ? Orders::durationLabel((int) $o['sub_days'], 'cs') . ' neomezeně'
                    . ((int) $o['credits'] > 0 ? ' + ' . Cred::fmtCs((int) $o['credits']) . ' kr.' : '')
                : Cred::fmtCs((int) $o['credits']) . ' kreditů';
            echo '<tr><td>' . e(when((int) $o['created_at'])) . '</td><td class="mono">' . e((string) $o['reference']) . '</td>'
               . '<td>' . e($what) . '</td>'
               . '<td class="num"><b>' . e(money((int) $o['price_cents'], $o['currency'] ?? null)) . '</b></td>'
               . '<td><span class="order-status status-' . e((string) $o['status']) . '">' . e(order_status_cs((string) $o['status'])) . '</span> '
               . order_info_btn($o)
               . '</td><td>';
            if ($o['status'] === Orders::PAID) {
                echo '<form method="post" onsubmit="return confirm(\'Refundovat ' . e((string) $o['reference'])
                   . ' (' . e(money((int) $o['price_cents'], $o['currency'] ?? null)) . ')? Kredity/čas se odeberou hned, peníze vrať ručně.\')">'
                   . Auth::csrfField()
                   . '<input type="hidden" name="action" value="refund_order">'
                   . '<input type="hidden" name="reopen" value="manage-' . $duId . '">'
                   . '<input type="hidden" name="order_id" value="' . (int) $o['id'] . '">'
                   . '<button class="btn small danger" type="submit">Refund</button></form>';
            } elseif ($o['status'] === Orders::REFUND) {
                echo '<form method="post" style="display:inline" onsubmit="return confirm(\'Peníze vráceny?\')">'
                   . Auth::csrfField()
                   . '<input type="hidden" name="action" value="refund_done">'
                   . '<input type="hidden" name="reopen" value="manage-' . $duId . '">'
                   . '<input type="hidden" name="order_id" value="' . (int) $o['id'] . '">'
                   . '<input type="text" name="refund_dest" required placeholder="Kam vráceno (povinné)" style="width:170px;padding:6px 9px;font-size:12px;margin-right:4px">'
                   . '<button class="btn small primary" type="submit">Peníze vráceny</button></form> '
                   . '<form method="post" style="display:inline" onsubmit="return confirm(\'Zrušit refund a obnovit objednávku ' . e((string) $o['reference']) . '? Odebrané kredity i dny se vrátí.\')">'
                   . Auth::csrfField()
                   . '<input type="hidden" name="action" value="refund_undo">'
                   . '<input type="hidden" name="reopen" value="manage-' . $duId . '">'
                   . '<input type="hidden" name="order_id" value="' . (int) $o['id'] . '">'
                   . '<button class="btn small" type="submit">Obnovit objednávku</button></form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';

    /* ---- E-maily ---- */
    echo '<div class="ud-pane" data-udpane="maily" hidden>';

    // Compose box first: contacting the customer (refund details and co.)
    // happens right where their mail history lives.
    echo '<div class="ud-card"><h4 class="ud-h">Napsat uživateli</h4>'
       . '<form method="post" class="ud-mail-form">'
       . Auth::csrfField()
       . '<input type="hidden" name="action" value="send_user_mail">'
       . '<input type="hidden" name="reopen" value="manage-' . $duId . '">'
       . '<input type="hidden" name="user_id" value="' . $duId . '">'
       . '<label>Předmět</label>'
       . '<input type="text" name="subject" maxlength="150" placeholder="Např. K refundaci objednávky - kam poslat peníze?">'
       . '<label>Zpráva</label>'
       . '<textarea name="body" rows="5" placeholder="Text e-mailu…"></textarea>'
       . '<button class="btn primary" type="submit" style="margin-top:12px">Odeslat e-mail</button>'
       . '</form></div>';

    echo '<h4 class="ud-h" style="margin-top:16px">Odeslané e-maily</h4>';
    try {
        $st = $pdo->prepare('SELECT * FROM mail_log WHERE recipient = ? ORDER BY id DESC LIMIT 60');
        $st->execute([(string) $du['email']]);
        $mails = $st->fetchAll();
    } catch (Throwable) { $mails = []; }
    if (!$mails) { echo '<p class="hint">Žádné odeslané e-maily.</p>'; }
    else {
        echo '<div class="table-scroll"><table class="ud-table"><thead><tr><th>Kdy</th><th>Předmět</th><th>Typ</th><th>Stav</th></tr></thead><tbody>';
        foreach ($mails as $m) {
            echo '<tr><td>' . e(when((int) $m['created_at'])) . '</td><td>' . e((string) $m['subject']) . '</td>'
               . '<td><span class="tag">' . e((string) $m['kind']) . '</span></td>'
               . '<td>' . ((int) $m['ok'] === 1 ? '<span class="tag on">odesláno</span>'
                    : '<span class="tag bad" title="' . e((string) ($m['error'] ?? '')) . '">chyba</span>') . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';

    /* ---- Zprávy podpory ---- */
    echo '<div class="ud-pane" data-udpane="zpravy" hidden>';
    $st = $pdo->prepare('SELECT m.*, a.email AS reply_admin FROM user_messages m LEFT JOIN users a ON a.id = m.replied_by WHERE m.user_id = ? ORDER BY m.created_at DESC');
    $st->execute([$duId]);
    $supportMessages = $st->fetchAll();
    $supportPosts = UserMessages::postsForMessages(array_map(static fn (array $m): int => (int) $m['id'], $supportMessages));
    if (!$supportMessages) { echo '<p class="hint">Uživatel zatím žádnou zprávu neposlal.</p>'; }
    else {
        foreach ($supportMessages as $sm) {
            $closed = $sm['closed_at'] !== null;
            $answered = trim((string) ($sm['admin_reply'] ?? '')) !== '';
            $state = $closed ? 'vyřešeno' : ($answered ? 'odpovězeno' : 'čeká na odpověď');
            $stateClass = $closed ? 'done' : ($answered ? 'answered' : 'waiting');
            echo '<article class="user-conversation"><header class="user-conversation-head"><div><span>TICKET #' . (int) $sm['id'] . ' · ' . e(when((int) $sm['created_at'])) . '</span>'
               . '<strong>' . e((string) $sm['subject']) . '</strong></div><b class="user-conversation-state is-' . $stateClass . '">' . e($state) . '</b></header>'
               . '<div class="user-conversation-body"><div class="user-conversation-message customer"><small>UŽIVATEL</small><div>' . nl2br(e((string) $sm['body'])) . '</div></div>';
            $posts = $supportPosts[(int) $sm['id']] ?? [];
            if ($posts) {
                foreach ($posts as $post) {
                    echo '<div class="user-conversation-message operator"><small>ADMINISTRACE · ' . e(when((int) $post['created_at'])) . '</small><div>' . nl2br(e((string) $post['body'])) . '</div></div>';
                }
            } else {
                echo '<p class="user-conversation-wait">Čeká na odpověď administrace.</p>';
            }
            echo '<div class="user-conversation-actions"><a class="btn small primary" href="?tab=messages&amp;manage=' . (int) $sm['id'] . '">Spravovat</a></div></div></div>';
        }
    }
    echo '</div>';

    /* ---- Exporty ---- */
    echo '<div class="ud-pane" data-udpane="exporty" hidden>';
    $st = $pdo->prepare('SELECT * FROM exports WHERE user_id = ? ORDER BY id DESC LIMIT 60');
    $st->execute([$duId]);
    $exps = $st->fetchAll();
    if (!$exps) { echo '<p class="hint">Žádné exporty.</p>'; }
    else {
        echo '<div class="table-scroll"><table class="ud-table"><thead><tr><th>Kdy</th><th>Formát</th><th class="num">Boxů</th><th class="num">Utraceno kreditů</th></tr></thead><tbody>';
        foreach ($exps as $x) {
            echo '<tr><td>' . e(when((int) $x['created_at'])) . '</td><td class="mono">' . e(strtoupper((string) $x['format'])) . '</td>'
               . '<td class="num">' . (int) $x['boxes'] . '</td>'
               . '<td class="num">' . ((int) $x['credits_spent'] > 0 ? e(Cred::fmtCs((int) $x['credits_spent'])) : 'zdarma') . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }
    echo '</div>';

    /* ---- Aktivita (kompletní časová osa) ---- */
    echo '<div class="ud-pane" data-udpane="aktivita" hidden>';
    $events = Audit::forUser($duId);
    if (!$events) { echo '<p class="hint">Zatím žádná aktivita.</p>'; }
    else {
        echo '<ul class="audit">';
        foreach ($events as $ev) {
            echo '<li class="audit-' . e($ev['kind']) . '"><span class="audit-when">' . e(when((int) $ev['at'])) . '</span>'
               . '<span class="audit-what">' . e($ev['text'])
               . ($ev['detail'] !== '' ? '<span class="hint">' . e($ev['detail']) . '</span>' : '')
               . '</span></li>';
        }
        echo '</ul>';
    }
    echo '</div>';

    exit;
}

$tabs = $ordersOnly ? ['orders' => 'Objednávky'] : [
    'orders'   => 'Objednávky',
    'overview' => 'Přehled',
    'settings' => 'Web',
    'studio'   => 'Studio',
    'accounts' => 'Účty a registrace',
    'seller'   => 'Fakturační údaje',
    'look'     => 'Vzhled',
    'exports'  => 'Exporty a kredity',
    'payments' => 'Platby',
    'codes'    => 'Kódy',
    'users'    => 'Uživatelé',
    'billing'  => 'Balíčky',
    'promotions' => 'Akce',
    'invoices' => 'Faktury',
    'mail'     => 'E-maily',
    'features' => 'Funkce',
    'activity' => 'Aktivita',
    'adminlog' => 'Protokol zásahů',
    'messages' => 'Zprávy uživatelů',
    'reset'    => 'Údržba',
];

// Fewer top-level buttons: the tabs are gathered into a handful of groups,
// each opening a second-level bar. The content each tab renders is untouched -
// only how they are reached changes. group key => [label, [tab keys]].
$groups = $ordersOnly ? [
    'orders' => ['Objednávky', ['orders']],
] : [
    /*
     * Přehled first: it is the page the panel opens on and the one that
     * sends you everywhere else.
     *
     * Nastavení and Systém used to be two buttons whose contents nobody
     * could tell apart - "Vzhled" in one, "Protokol zásahů" in the other,
     * both of them settings of the installation. They are one group now,
     * ordered from what gets changed often to what gets changed once.
     */
    'overview' => ['Přehled',           ['overview']],
    'orders'   => ['Objednávky',        ['orders']],
    /*
     * Every tab stays in the menu whether anything is for sale or not.
     *
     * Hiding them while selling was off looked tidy and was wrong: promo
     * offers are packages, and packages are edited under Balíčky - so
     * switching sales off took away the page where the free packages are
     * made. What is for sale is decided by the price of a package, not by
     * which tabs an administrator can reach.
     */
    'money'    => ['Prodej a peníze',   ['billing', 'promotions', 'payments', 'codes', 'invoices']],
    'exports'  => ['Exporty a kredity', ['exports']],
    'users'    => ['Uživatelé',         ['users']],
    'messages' => ['Zprávy',            ['messages']],
    'settings' => ['Nastavení',         ['settings', 'studio', 'look', 'accounts', 'seller', 'mail',
                                        'features', 'activity', 'adminlog', 'reset']],
];

// The tabs of whichever group holds the current tab, for the sub-nav.
$curGroupTabs = [];
foreach ($groups as $g) {
    if (in_array($tab, $g[1], true)) {
        $curGroupTabs = $g[1];
        break;
    }
}

page_head('Administrace', '../');
$adminPaletteCss = [
  'bg'=>Settings::get('palette_bg'),'panel'=>Settings::get('palette_panel'),'panel2'=>Settings::get('palette_panel2'),
  'line'=>Settings::get('palette_line'),'text'=>Settings::get('palette_text'),'muted'=>Settings::get('palette_muted'),
  'accent'=>Settings::get('palette_accent'),'accentv'=>Settings::get('palette_accent'),'accentv2'=>Settings::get('palette_accent_dark'),
  'good'=>Settings::get('palette_good'),'bad'=>Settings::get('palette_bad'),'button'=>Settings::get('palette_button'),
  'buttonText'=>Settings::get('palette_button_text'),'buttonHover'=>Settings::get('palette_button_hover'),
];
?>
<style id="h3-admin-palette">
:root{
  --bg:<?=e($adminPaletteCss['bg'])?>;
  --panel:<?=e($adminPaletteCss['panel'])?>;
  --panel2:<?=e($adminPaletteCss['panel2'])?>;
  --line:<?=e($adminPaletteCss['line'])?>;
  --text:<?=e($adminPaletteCss['text'])?>;
  --muted:<?=e($adminPaletteCss['muted'])?>;
  --accent:<?=e($adminPaletteCss['accent'])?>;
  --accentv:<?=e($adminPaletteCss['accentv'])?>;
  --accentv2:<?=e($adminPaletteCss['accentv2'])?>;
  --accentText:<?=e(Settings::get('palette_accent_text'))?>;
  --good:<?=e($adminPaletteCss['good'])?>;
  --goodText:<?=e(Settings::get('palette_good_text'))?>;
  --goodHover:<?=e(Settings::get('palette_good_hover'))?>;
  --bad:<?=e($adminPaletteCss['bad'])?>;
  --button:<?=e($adminPaletteCss['button'])?>;
  --buttonText:<?=e($adminPaletteCss['buttonText'])?>;
  --buttonHover:<?=e($adminPaletteCss['buttonHover'])?>;
}
</style>
<?php
/*
 * Badges, the way a phone does them: how many things are waiting, not how
 * many rows exist. Conversations where the customer wrote last, and orders
 * that still need somebody to do something.
 */
$adminUnreadMessages = $ordersOnly ? 0 : UserMessages::waitingCount();
$adminOpenOrders     = Orders::openCount();
$maintenanceActive = Settings::bool('maintenance_mode');
?>
<div class="wrap admin">
  <div class="admin-head">
    <div>
      <h1>Administrace</h1>
      <p class="lede" style="margin:0">Přihlášen jako <?= e($admin['email']) ?></p>
    </div>
    <a class="btn" href="../index.php">← Zpět na web</a>
  </div>

  <?php if ($maintenanceActive): ?>
    <aside class="admin-maintenance-alert" role="status" aria-live="polite">
      <div><span>VEŘEJNÝ WEB JE POZASTAVEN</span><strong>Probíhá údržba — přihlášení uživatelé vidí ochranné upozornění.</strong></div>
      <?php if (!$ordersOnly): ?><a class="btn small" href="?tab=reset">Otevřít údržbu</a><?php endif; ?>
    </aside>
  <?php endif; ?>

  <nav class="admin-tabs">
    <?php foreach ($groups as $groupKey => $g):
      // One badge rule for every group, so adding another is one line.
      $badge = match ($groupKey) {
          'messages' => $adminUnreadMessages,
          'orders'   => $adminOpenOrders,
          default    => 0,
      };
    ?>
      <a class="admin-tab<?= in_array($tab, $g[1], true) ? ' is-on' : '' ?>"
         href="?tab=<?= e($g[1][0]) ?>"><?= e($g[0]) ?><?php if ($badge > 0): ?><span class="admin-badge" title="<?= $groupKey === 'messages' ? 'Konverzací čeká na odpověď' : 'Objednávek čeká na vyřízení' ?>"><?= $badge > 99 ? '99+' : $badge ?></span><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if (count($curGroupTabs) > 1): ?>
    <nav class="subtabs">
      <?php foreach ($curGroupTabs as $t): ?>
        <a class="subtab <?= $tab === $t ? 'is-on' : '' ?>" href="?tab=<?= e($t) ?>"><?= e($tabs[$t]) ?></a>
      <?php endforeach; ?>
    </nav>
  <?php endif; ?>

  <?php flash('ok', $ok); flash('err', $error); ?>

<?php
/*
 * One tab, one file, in admin/tabs/.
 *
 * The list is a whitelist, not a convenience: $tab arrives from the query
 * string, and the moment it decides a filename an unchecked value is a way
 * out of this directory. Anything unknown falls back to the first tab
 * rather than showing an empty page.
 */
$tabFile = __DIR__ . '/tabs/' . $tab . '.php';
if (!in_array($tab, ADMIN_TABS, true) || !is_file($tabFile)) {
    $tabFile = __DIR__ . '/tabs/' . ADMIN_TABS[0] . '.php';
}
require $tabFile;
?>
</div>
<script>
document.querySelectorAll('form input[name="_news_form"]').forEach(function(marker){
  var form=marker.form;
  if(!form)return;
  form.addEventListener('submit',function(e){
    if(!window.confirm('Uložit novinky a vytvořit novou verzi? Po potvrzení se karta „Co je nového“ zobrazí uživatelům, kteří tuto verzi ještě nečetli.'))e.preventDefault();
  });
});
</script>
<?php page_foot('../');
