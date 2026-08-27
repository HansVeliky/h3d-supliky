<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once __DIR__ . '/lib/View.php';

$user = Auth::requireUser('login.php?next=account.php');
$isVerified = Verify::isVerified($user);
$accountPrefs = json_decode((string) ($user['prefs'] ?? ''), true);
$accountPrefs = is_array($accountPrefs) ? $accountPrefs : [];
$accountLang = (($user['lang'] ?? '') === 'cs') ? 'cs' : 'en';
$accountUnit = (($accountPrefs['unit'] ?? 'mm') === 'in') ? 'in' : 'mm';
$ucs = $accountLang === 'cs';

// The customer sees the consequence of an expiry here, so it is swept here
// too rather than only when an administrator happens to look.
Orders::expireStale();

// A message left behind by the redirect that followed the last POST.
[$ok, $error] = take_flash();

// Shown right after registering, and after any action that sends mail.
$mailNotice = isset($_GET['welcome']) && Mailer::enabled() && !Verify::isVerified($user);

// Set by an action that sent mail, and carried through the redirect.
$mailNotice = $mailNotice || isset($_GET['sent']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::checkCsrf();
    $action = (string) ($_POST['action'] ?? '');

    // Unverified accounts may only use the small set of account actions
    // needed to finish verification. Everything else stays server-side
    // locked as well as hidden in the UI.
    if (!$isVerified && !in_array($action, ['set_lang', 'set_unit', 'resend_verify', 'change_unverified_email', 'password'], true)) {
        $action = 'noop';
        $error = Lang::current() === 'cs'
            ? 'Nejdřív ověř e-mailovou adresu. Do té doby je účet omezený pouze na nastavení účtu.'
            : 'Please verify your email address first. Until then, only account settings are available.';
    }

    // Any mail from here goes to this account, so build it in their language.
    Notify::$lang = Auth::userLang($user);

    if ($action === 'resend_verify') {
        [$sent, $msg] = Verify::sendLink($user);
        if ($sent) {
            $ok = Lang::current() === 'cs'
                ? 'Ověřovací e-mail byl znovu odeslán.'
                : 'The verification email was sent again.';
        } else {
            $error = $msg;
        }
    } elseif ($action === 'noop') {
        // The message was prepared by the verification gate above.
    } elseif ($action === 'redeem') {
        [$done, $msg, $granted, $gdays] = Codes::redeem((int) $user['id'], (string) ($_POST['code'] ?? ''));
        if ($done) {
            $csr   = Lang::current() === 'cs';
            $parts = [];
            if ($granted !== 0) {
                $parts[] = Cred::fmt($granted) . ($csr ? ' kreditů' : ' credits');
            }
            if ($gdays > 0) {
                $parts[] = Orders::durationLabel($gdays, $csr ? 'cs' : 'en')
                         . ($csr ? ' neomezeně' : ' unlimited');
            }
            $ok = ($csr ? 'Kód uplatněn. Připsáno: ' : 'Code accepted. Added: ')
                . implode($csr ? ' a ' : ' and ', $parts) . '.';
        } else {
            $error = $msg;
        }
    } elseif ($action === 'set_lang') {
        $l = ($_POST['lang'] ?? '') === 'cs' ? 'cs' : 'en';
        Db::pdo()->prepare('UPDATE users SET lang = ? WHERE id = ?')
                 ->execute([$l, (int) $user['id']]);
        $user['lang'] = $l;
        // Mirror the choice into the studio cookie so the whole interface
        // switches at once and keeps the language even after signing out.
        setcookie('h3d_lang', $l, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
        $_COOKIE['h3d_lang'] = $l;
        Lang::set($l);
        $ok = $l === 'cs' ? 'Jazyk uložen.' : 'Language saved.';
    } elseif ($action === 'set_unit') {
        $u = ($_POST['unit'] ?? '') === 'in' ? 'in' : 'mm';
        $prefs = json_decode((string) ($user['prefs'] ?? ''), true);
        $prefs = is_array($prefs) ? $prefs : [];
        $prefs['unit'] = $u;
        Db::pdo()->prepare('UPDATE users SET prefs = ? WHERE id = ?')
                 ->execute([json_encode($prefs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), (int) $user['id']]);
        setcookie('h3d_unit', $u, ['expires' => time() + 31536000, 'path' => '/', 'samesite' => 'Lax']);
        $_COOKIE['h3d_unit'] = $u;
        $ok = $u === 'in' ? 'Units saved.' : 'Jednotky uloženy.';
    } elseif ($action === 'prefs') {
        Db::pdo()->prepare('UPDATE users SET confirm_spend = ? WHERE id = ?')
                 ->execute([isset($_POST['confirm_spend']) ? 1 : 0, (int) $user['id']]);
        $ok = __('acc.saved');
    } elseif ($action === 'currency') {
        Money::choose((string) ($_POST['code'] ?? ''));
        $ok = 'Currency changed to ' . e(Money::current()) . '.';
    } elseif ($action === 'order') {
        $pid = (int) ($_POST['package_id'] ?? 0);
        $st = Db::pdo()->prepare('SELECT * FROM packages WHERE id = ? AND active = 1');
        $st->execute([$pid]);
        $pkg = $st->fetch();

        // Zero-price packages are PROMO AKCE offers. They remain claimable
        // while paid package purchases are switched off.
        $isFreePackage = $pkg && (int) ($pkg['price_cents'] ?? 0) === 0;
        if (!$pkg) {
            $error = 'That package is not available.';
        } elseif (!$isFreePackage && !Settings::bool('purchase_enabled')) {
            $error = $ucs ? 'Nákup balíčků je momentálně vypnutý.' : 'Package purchases are currently disabled.';
        } elseif (!Pricing::mayOrder($pkg, (int) $user['id'])[0]) {
            $error = Pricing::mayOrder($pkg, (int) $user['id'])[1];
        } else {
            // The price is taken from the server's own calculation, never
            // from the form: a discount the customer edited in devtools is
            // not a discount.
            $price = Pricing::of($pkg);

            // The price is converted once, here, and stored with the order
            // together with the currency it was converted into. A later rate
            // change must not move an amount somebody already agreed to.
            $cur      = Money::current();
            $payCents = Money::convert($price['final'], $cur);
            $reference = 'H3D-' . strtoupper(bin2hex(random_bytes(3)));

            // Nothing to choose any more: a time package waits on the
            // account and its owner starts it whenever they like. The column
            // stays for the orders placed while the choice existed.
            $subStart = '';

            // Through Db::write: the price and the package were read a moment
            // ago, which leaves this connection on an old snapshot, and under
            // load SQLite refuses the write outright rather than waiting.
            $orderId = Db::write(static function (PDO $pdo) use ($user, $pkg, $subStart, $payCents, $cur, $reference): int {
                $pdo->prepare(
                    'INSERT INTO orders (user_id, package_id, credits, sub_days, sub_start, price_cents, currency, status,
                                         reference, access_token, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, "pending", ?, ?, ?)'
                )->execute([
                    (int) $user['id'], (int) $pkg['id'], (int) $pkg['credits'], (int) ($pkg['sub_days'] ?? 0),
                    $subStart, $payCents, $cur, $reference, bin2hex(random_bytes(16)), time(),
                ]);
                $id = (int) $pdo->lastInsertId();

                // The variable symbol is the order id, zero padded: unique,
                // numeric, and short enough to type off a screen.
                $pdo->prepare('UPDATE orders SET vs = ? WHERE id = ?')
                    ->execute([sprintf('%06d', $id), $id]);

                return $id;
            });

            $st = Db::pdo()->prepare('SELECT * FROM orders WHERE id = ?');
            $st->execute([$orderId]);
            $order = $st->fetch();

            $free = (int) $order['price_cents'] === 0;

            // Nothing to pay means nothing to check, so a free package is
            // settled immediately instead of sitting in the queue waiting for
            // a payment that will never arrive.
            if ($free) {
                // PROMO AKCE is fully automatic: settle the order immediately.
                Db::pdo()->prepare(
                    'UPDATE orders SET status = "paid", paid_at = ?, admin_note = ? WHERE id = ?'
                )->execute([time(), 'Zdarma - aktivováno automaticky.', $orderId]);

                $st->execute([$orderId]);
                $order = $st->fetch();

                // Credit promo packages are granted immediately. A free
                // time package is different: the order is completed
                // automatically, but the package remains in "Moje balíčky"
                // until the customer activates it manually.
                Orders::fulfil($order);
            }

            if (Mailer::enabled()) {
                Mailer::notify(
                    (string) $user['email'],
                    $free
                        ? ((int)($order['sub_days'] ?? 0) > 0
                            ? Notify::orderTimePaid($order, orderStatusUrl($order), invoiceUrl($order))
                            : Notify::orderPaid($order, Credits::balance((int) $user['id']), orderStatusUrl($order), invoiceUrl($order)))
                        : Notify::orderCreated(
                            $order,
                            Payments::active() ? Payments::forOrder(Payments::active()[0], $order)['url'] : '',
                            Lang::instructions(),
                            orderStatusUrl($order)
                          ),
                    $free ? 'order_paid' : 'order_created'
                );
            }

            $placedRef = (string) $order['reference'];

            if (!$free) {
                $ok = "Objednávka přijata. Variabilní symbol: $reference";
            } elseif ((int) ($order['sub_days'] ?? 0) > 0) {
                $ok = 'Hotovo. Promo časový balíček čeká v „Moje balíčky“ na tvoji aktivaci.';
            } else {
                $ok = 'Hotovo. ' . Cred::fmtCs((int) $order['credits']) . ' kreditů je na tvém účtu.';
            }

            $sentMail = Mailer::enabled();
        }
    } elseif ($action === 'cancel_order') {
        $oid = (int) ($_POST['order_id'] ?? 0);
        $st = Db::pdo()->prepare(
            'SELECT * FROM orders WHERE id = ? AND user_id = ?'
        );
        $st->execute([$oid, (int) $user['id']]);
        $order = $st->fetch();

        if (!$order) {
            $error = Lang::current() === 'cs'
                ? 'Tahle objednávka k tvému účtu nepatří.'
                : 'This order does not belong to your account.';
        } else {
            Notify::$lang = Auth::userLang($user);
            [$done, $msg] = Orders::cancelPending($order, $user);
            if ($done) {
                $ok = $msg;
            } else {
                $error = $msg;
            }
        }
    } elseif ($action === 'activate_pass') {
        // Starting a paid time package. Nothing about it ran until now, so
        // this is the moment its days begin and any credits arrive.
        $oid = (int) ($_POST['order_id'] ?? 0);
        $st  = Db::pdo()->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
        $st->execute([$oid, (int) $user['id']]);
        $order = $st->fetch();

        if (!$order) {
            $error = 'Tahle objednávka k tvému účtu nepatří.';
        } else {
            [$done, $why] = Orders::activate($order);
            if (!$done) {
                $error = $why;
            } else {
                $until = (int) (Auth::byId((int) $user['id'])['subscription_until'] ?? 0);
                $ok = 'Balíček je aktivní'
                    . ($until > 0 ? ' do ' . date('d.m.Y H:i', $until) : '') . '.';
            }
        }
    } elseif ($action === 'request_refund') {
        /*
         * The customer's own withdrawal. What they bought is frozen the
         * moment they ask - the credits leave the balance, an activated plan
         * loses its days - so nothing can be used up while the money is on
         * its way back. If the refund is called off in the panel, all of it
         * comes back (refund_undo).
         */
        $oid = (int) ($_POST['order_id'] ?? 0);
        $st  = Db::pdo()->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
        $st->execute([$oid, (int) $user['id']]);
        $order = $st->fetch();

        if (!$order) {
            $error = 'Tahle objednávka k tvému účtu nepatří.';
        } elseif (($why = Orders::refundBlocker($order, $user)) !== '') {
            $error = 'Vrácení peněz už nejde: ' . $why;
        } else {
            $bits = Orders::reclaimGrant($order, $user);

            Db::pdo()->prepare(
                'UPDATE orders SET status = ?, refund_cents = ?, admin_note = ? WHERE id = ?'
            )->execute([
                Orders::REFUND,
                (int) $order['price_cents'],
                trim((string) $order['admin_note']) . ' · zákazník požádal o vrácení peněz '
                    . date('d.m.Y H:i') . ($bits ? ' (' . implode(', ', $bits) . ')' : ''),
                $oid,
            ]);

            $ok = 'Žádost o vrácení peněz přijata. Do vyřízení je objednávka pozastavená; '
                . 'peníze pošleme zpět na účet, ze kterého přišly.';

            if (Mailer::enabled()) {
                // notify_admin copies this to the operator, so the request
                // does not sit in the panel unnoticed.
                Notify::$lang = Auth::userLang($user);
                Mailer::notify(
                    (string) $user['email'],
                    Notify::refundRequested(
                        $order,
                        money((int) $order['price_cents'], $order['currency'] ?? null),
                        orderStatusUrl($order)
                    ),
                    'refund_requested'
                );
                $sentMail = true;
            }
        }
    } elseif ($action === 'password') {
        $cur = (string) ($_POST['current'] ?? '');
        $new = (string) ($_POST['new'] ?? '');
        if (!password_verify($cur, $user['pass_hash'])) {
            $error = $ucs ? 'Současné heslo není správné.' : 'The current password is not correct.';
        } elseif (($weak = Auth::passwordProblem($new)) !== null) {
            // Same rules as the sign-up form, from the same place.
            $error = __($weak);
        } else {
            Db::pdo()->prepare('UPDATE users SET pass_hash = ? WHERE id = ?')
                     ->execute([password_hash($new, PASSWORD_DEFAULT), (int) $user['id']]);
            $ok = $ucs ? 'Heslo bylo změněno.' : 'Password changed.';
        }
    } elseif ($action === 'nickname') {
        [$nok, $nmsg] = Auth::changeNickname((int) $user['id'], (string) ($_POST['nickname'] ?? ''));
        // The settings tab saves this in the background; a JSON answer keeps
        // the flash message from popping up on some later page load.
        if (!empty($_POST['ajax'])) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => $nok, 'error' => $nok ? '' : $nmsg]);
            exit;
        }
        if ($nok) { $ok = 'Nickname updated.'; } else { $error = $nmsg; }
    } elseif ($action === 'billing') {
        // Billing details for the invoice. Stored inside the account's prefs
        // JSON, readable and editable only by the signed-in owner (this page
        // requires a session and every POST carries the CSRF token); the
        // invoice pages themselves only open for the owner or with the
        // order's secret link token.
        $billing = [];
        foreach (['name', 'street', 'city', 'zip', 'country', 'cin', 'vatid'] as $bk) {
            $billing[$bk] = mb_substr(trim((string) ($_POST['b_' . $bk] ?? '')), 0, 120);
        }
        $prefs = json_decode((string) ($user['prefs'] ?? ''), true);
        $prefs = is_array($prefs) ? $prefs : [];
        if (implode('', $billing) === '') {
            unset($prefs['billing']);
        } else {
            $prefs['billing'] = $billing;
        }
        Db::pdo()->prepare('UPDATE users SET prefs = ? WHERE id = ?')
                 ->execute([json_encode($prefs, JSON_UNESCAPED_UNICODE), (int) $user['id']]);
        $ok = 'Billing details saved.';
    } elseif ($action === 'email') {
        if (!Mailer::enabled()) {
            $error = 'E-mail sending is not configured, so the address cannot be verified.';
        } else {
            [$eok, $etok] = Auth::beginEmailChange((int) $user['id'], (string) ($_POST['email'] ?? ''));
            if (!$eok) {
                $error = $etok;
            } else {
                Notify::$lang = Auth::userLang($user);
                Mailer::notify(
                    Auth::normaliseEmail((string) ($_POST['email'] ?? '')),
                    Notify::emailChange(originUrl() . '/confirm-email.php?token=' . urlencode($etok), 24),
                    'email_change'
                );
                $ok = 'A confirmation link was sent to the new address. The change takes effect once you open it.';
            }
        }
    }

    // Redirecting turns the POST into a GET, so a refresh repeats the view
    // rather than the action. Everything above has already happened; only
    // the message needs carrying across.
    $query = [];
    if (!empty($sentMail))  { $query['sent'] = '1'; }
    if (!empty($placedRef)) { $query['ordered'] = $placedRef; }

    // Land back on the tab the action was fired from. Without it, redeeming
    // a code answers on the shop tab and the message reads as if it belonged
    // to something else entirely.
    $backTab = match ($action) {
        'redeem'            => 'kod',
        'order', 'currency' => 'koupit',
        'cancel_order'      => 'historie',
        default             => 'nastaveni',
    };

    redirect_after_post(
        'account.php' . ($query ? '?' . http_build_query($query) : '') . '#' . $backTab,
        $ok,
        $error
    );
}

// The POST handler above always redirects, so nothing below writes to the
// session. Letting go of its lock here keeps a slow account page from
// holding up every other request the same browser makes.
Auth::releaseSession();

// Until the address is confirmed this is deliberately the only account view.
// Keeping it server-side (not merely hiding tabs with CSS) prevents direct URLs
// and POST requests from reaching the rest of the customer area.
if (!$isVerified) {
    $ucs = Lang::current() === 'cs';
    $verifyUntil = (int) ($user['verify_expires_at'] ?? (time() + 86400));
    $verifyLeft = max(0, $verifyUntil - time());
    $hoursLeft = (int) ceil($verifyLeft / 3600);
    page_head($ucs ? 'Ověření e-mailu' : 'Verify your email');
    ?>
    <div class="wrap narrow">
      <div class="verify-required-banner" role="alert">
        <div class="verify-required-icon" aria-hidden="true">!</div>
        <div>
          <strong><?= e($ucs ? 'E-mailová adresa není ověřená' : 'Your email address is not verified') ?></strong>
          <p>
            <?= e($ucs
              ? 'Ověř prosím svou e-mailovou adresu pomocí odkazu, který jsme poslali na ' . $user['email'] . '. Dokud účet neověříš, je dostupné pouze nastavení účtu.'
              : 'Please verify your email address using the link we sent to ' . $user['email'] . '. Until the account is verified, only account settings are available.') ?>
          </p>
          <p class="hint" style="margin-bottom:0">
            <?= e($ucs
              ? 'Ověření je potřeba dokončit do 24 hodin. Potom bude účet deaktivován a po dalších 24 hodinách bude smazán.'
              : 'Verification must be completed within 24 hours. After that the account is deactivated and deleted 24 hours later.') ?>
            <?php if ($verifyLeft > 0): ?>
              <span> <?= e($ucs ? 'Zbývá přibližně ' . $hoursLeft . ' h.' : 'About ' . $hoursLeft . ' h remaining.') ?></span>
            <?php endif; ?>
          </p>
        </div>
      </div>

      <?php flash('ok', $ok); flash('err', $error); ?>

      <div class="card verify-account-card">
        <h1 style="margin-top:0"><?= e($ucs ? 'Nastavení účtu' : 'Account settings') ?></h1>
        <p class="lede"><?= e($user['email']) ?></p>

        <div class="verify-mail-actions">
          <form method="post">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="resend_verify">
            <button class="btn primary" type="submit">
              <?= e($ucs ? 'Poslat ověřovací e-mail znovu' : 'Send verification email again') ?>
            </button>
          </form>
        </div>
        <p class="hint">
          <?= e($ucs
            ? 'Pokud e-mail nevidíš, zkontroluj Spam nebo Hromadnou poštu. Opětovné odeslání neposouvá 24hodinový termín.'
            : 'If you do not see the email, check Spam or Junk. Resending does not extend the 24-hour deadline.') ?>
        </p>

        <div class="verify-language-box">
          <strong><?= e($ucs ? 'Jazyk účtu' : 'Account language') ?></strong>
          <form method="post" class="row-actions" style="margin-top:10px">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="set_lang">
            <select name="lang" onchange="this.form.submit()" aria-label="<?= e($ucs ? 'Jazyk' : 'Language') ?>">
              <option value="cs"<?= Lang::current() === 'cs' ? ' selected' : '' ?>>Čeština</option>
              <option value="en"<?= Lang::current() === 'en' ? ' selected' : '' ?>>English</option>
            </select>
          </form>
        </div>
      </div>

      <div class="account-studio-return" style="margin-top:12px">
        <a class="btn" href="<?= e('index.php?lang=' . rawurlencode(Lang::current())) ?>">
          <?= e($ucs ? '← Zpět do Studia' : '← Back to Studio') ?>
        </a>
      </div>
    </div>
    <?php page_foot(); ?>
    <?php exit;
}

$decision = Quota::check($user);

$packages = Db::pdo()->query('SELECT * FROM packages WHERE active = 1 ORDER BY sort, credits')->fetchAll();

$orders = Db::pdo()->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 10');
$orders->execute([(int) $user['id']]);
$orders = $orders->fetchAll();

// Orders that still need attention. A freshly purchased package stays here
// until payment/processing is finished; paid, completed, cancelled and
// refunded orders belong only to History.
$openOrders = array_values(array_filter($orders, static function (array $o): bool {
    return in_array((string) ($o['status'] ?? ''), ['pending', 'accepted', 'refund'], true);
}));

$history = Credits::history((int) $user['id'], 25);

page_head('Account');
?>
<div class="wrap">
  <h1><?= e(__('acc.title')) ?></h1>
  <p class="lede"><?= e($user['email']) ?></p>

  <?php flash('ok', $ok); flash('err', $error); ?>

  <?php if ($mailNotice): ?>
    <div class="msg info">
      A confirmation has been sent to <strong><?= e($user['email']) ?></strong>.
      If it is not there within a few minutes, check your spam or junk folder -
      automated messages often land there the first time. Marking it as
      &ldquo;not spam&rdquo; keeps later ones out of it.
    </div>
  <?php endif; ?>

  <?php
    // Which tabs this account actually has. A tab that would open on an empty
    // screen is not offered at all: fewer doors, none of them locked.
    // Free packages are promotional offers, not part of the paid shop.
    // They stay available even when paid purchasing is disabled.
    $freePackages = array_values(array_filter($packages, static fn($p) =>
        (int) ($p['price_cents'] ?? 0) === 0));
    $paidPackages = array_values(array_filter($packages, static fn($p) =>
        (int) ($p['price_cents'] ?? 0) > 0));

    $promoOn = !empty($freePackages);
    $shopOn  = Settings::bool('purchase_enabled') && !empty($paidPackages);
    $refOn   = Referral::enabled();
    $subUntil = Auth::subscribedUntil($user);

    // Paid time packages that have not been started yet. Not $waiting: the
    // quota text below owns that name for something else entirely.
    $waitingPasses = Orders::waitingFor((int) $user['id']);
  ?>

  <!--
    The state of the account stays above the tabs, always visible: how much
    is left and what an export costs right now is the reason people open this
    page, and it must not be something you have to go looking for.
  -->
  <div class="acct-hero">
    <div class="acct-hero-balance">
      <?php if (Auth::isUnlimited($user)): ?>
        <?php // Unlimited - a role or a running subscription - never spends
              // credits, so the running total would just be a confusing number.
              // Show the infinity, and the subscription's end date beneath it. ?>
        <b title="<?= e(__('acc.unlimitedrole')) ?>">&infin;</b>
        <span><?= e(__('acc.credits')) ?></span>
        <?php if ($subUntil !== null): ?>
          <span class="balance-sub">
            <?= e(($ucs ? 'Předplatné do ' : 'Subscription until ')
                  . date('d.m.Y H:i', $subUntil)) ?>
          </span>
        <?php endif; ?>
      <?php else: ?>
        <b><?= e(Cred::fmt((int) $user['credits'])) ?></b>
        <span><?= e(__('acc.credits')) ?></span>
      <?php endif; ?>
    </div>

    <div class="acct-hero-now">
      <div class="acct-hero-label"><?= e(__('acc.allowance')) ?></div>
    <?php if (!empty($decision['unlimited'])): ?>
      <p><?= e(__('acc.unlimitedrole')) ?></p>
    <?php elseif ($decision['cooldown'] === 0): ?>
      <p><?= e(__('acc.unlimited')) ?></p>

    <?php elseif ($decision['mode'] === Quota::FREE): ?>
      <p>
        <?php if ((int) $decision['limit'] > 0): ?>
          <?= e(__('acc.freecount',
                   (string) (int) $decision['free_left'],
                   (string) (int) $decision['limit'])) ?>
        <?php else: ?>
          <?= e(__('acc.freenow')) ?>
        <?php endif; ?>

        <?php if ((int) $decision['window_reset'] > 0
                  && (int) $decision['free_left'] < (int) $decision['limit']): ?>
          <?= e(__('acc.resetin', Quota::humanDuration((int) $decision['window_reset']))) ?>
        <?php endif; ?>
      </p>

    <?php elseif ($decision['mode'] === Quota::CREDIT): ?>
      <p>
        <?php if (empty($decision['free_paid'])): ?>
          <?= e(__('acc.freecount', '0', (string) (int) $decision['limit'])) ?>
          <?= e(__('acc.resetall', Quota::humanDuration((int) $decision['retry_after']))) ?>
        <?php endif; ?>
        <?= e(__('acc.costsbefore')) ?>
        <strong><?= e(Cred::fmt((int) $decision['cost'])) ?></strong>
        <?= e((int) $decision['cost'] === Cred::SCALE ? __('acc.credit') : __('acc.credits.plural')) ?>.
      </p>

    <?php else: ?>
      <p>
        <?php if (empty($decision['free_paid'])): ?>
          <?= e(__('acc.freecount', '0', (string) (int) $decision['limit'])) ?>
          <?= e(__('acc.resetall', Quota::humanDuration((int) $decision['retry_after']))) ?>
        <?php else: ?>
          <?= e(__('acc.needcredits')) ?>
        <?php endif; ?>
      </p>
    <?php endif; ?>

    <?php
      // "Credits let you skip the wait" only means anything when there is a
      // wait. With the allowance untouched it sits under "5 of 5" saying
      // nothing, so it is left out.
      $waiting = (int) $decision['free_left'] < (int) $decision['limit']
                 || $decision['mode'] !== Quota::FREE;
    ?>
    <?php if (!empty($decision['free_paid'])): ?>
      <p class="hint"><?= e(__('acc.nofree')) ?></p>
    <?php elseif ($waiting && Settings::bool('credits_enabled')): ?>
      <p class="hint"><?= e(__('acc.windowrule')) ?></p>
    <?php endif; ?>

      <?php if ($shopOn && !Auth::isUnlimited($user)): ?>
        <button class="btn primary acct-hero-btn" type="button" data-atab-go="koupit">
          <?= e($ucs ? 'Dobít kredity' : 'Top up credits') ?>
        </button>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($waitingPasses): ?>
    <!--
      Paid time packages that have not been started yet. Above the tabs, not
      inside the shop: it is the first thing to deal with, and it has to be
      reachable even if selling gets switched off after somebody bought one.
    -->
    <div class="card">
      <h2><?= e($ucs ? 'Moje balíčky' : 'My packages') ?></h2>
      <p class="hint" style="margin-top:0"><?= e($ucs
          ? 'Připravené balíčky. Odpočítávat se začne až po ruční aktivaci, takže si vyber, kdy se ti to hodí.'
          : 'Ready packages. The clock starts only after you activate one, so pick a moment that suits you.') ?></p>

      <div class="adm-cards">
        <?php foreach ($waitingPasses as $w): ?>
          <?php $wRefund = Orders::refundBlocker($w, $user); ?>
          <div class="adm-card">
            <div class="adm-card-main">
              <div class="adm-card-title">
                <?= e(Orders::durationLabel((int) $w['sub_days'], Lang::current())) ?>
                <?= e($ucs ? 'neomezeně' : 'unlimited') ?>
                <span class="tag warn"><?= e($ucs ? 'čeká na aktivaci' : 'waiting') ?></span>
              </div>
              <div class="adm-card-sub hint">
                <span class="mono"><?= e($w['reference']) ?></span>
                · <?= e(money((int) $w['price_cents'], $w['currency'] ?? null)) ?>
                <?php if ((int) $w['credits'] > 0): ?>
                  · + <?= e(Cred::fmt((int) $w['credits'])) ?> <?= e($ucs ? 'kreditů' : 'credits') ?>
                <?php endif; ?>
              </div>
            </div>
            <div class="row-actions">
              <form method="post" data-busy="<?= e($ucs ? 'Aktivuji…' : 'Activating…') ?>">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="activate_pass">
                <input type="hidden" name="order_id" value="<?= (int) $w['id'] ?>">
                <button class="btn primary small" type="submit"
                        data-confirm="<?= e($ucs
                            ? 'Spustit balíček teď? Od téhle chvíle běží ' . Orders::durationLabel((int) $w['sub_days'], 'cs') . ' a nárok na vrácení peněz zaniká, jakmile ho začneš používat.'
                            : 'Start the package now? It runs for ' . Orders::durationLabel((int) $w['sub_days'], 'en') . ' from this moment, and the right to a refund ends as soon as you start using it.') ?>"
                        data-confirm-ok="<?= e($ucs ? 'Aktivovat' : 'Activate') ?>"><?= e($ucs ? 'Aktivovat' : 'Activate') ?></button>
              </form>
              <?php if ((int) ($w['price_cents'] ?? 0) > 0 && $wRefund === ''): ?>
                <form method="post" data-busy="<?= e($ucs ? 'Odesílám žádost…' : 'Sending…') ?>">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="action" value="request_refund">
                  <input type="hidden" name="order_id" value="<?= (int) $w['id'] ?>">
                  <button class="btn small danger" type="submit"
                          data-confirm="<?= e($ucs
                              ? 'Požádat o vrácení peněz za tenhle balíček? Balíček se tím pozastaví a nepůjde aktivovat.'
                              : 'Ask for a refund of this package? It will be put on hold and cannot be activated.') ?>"
                          data-confirm-ok="<?= e($ucs ? 'Požádat o vrácení' : 'Request refund') ?>"><?= e($ucs ? 'Vrátit peníze' : 'Refund') ?></button>
                </form>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <p class="hint" style="margin-bottom:0"><?= e($ucs
          ? 'Vrátit peníze jde do ' . Orders::REFUND_DAYS . ' dní od zaplacení, dokud je balíček nedotčený. Po aktivaci nárok zaniká ve chvíli, kdy začneš službu využívat.'
          : 'A refund is possible within ' . Orders::REFUND_DAYS . ' days of payment while the package is untouched. After activation the right ends as soon as you start using the service.') ?></p>
    </div>
  <?php endif; ?>

  <?php
    // One row of tabs, one thing behind each. The page used to be a single
    // column where the shop, the code box, the invitation and three tables
    // all scrolled past each other and nothing looked more important than
    // anything else.
    $tabs = [];
    // Account settings is a permanent part of the account, independent of
    // whether the shop is enabled. Keep it first so enabling paid packages
    // cannot make the settings screen appear to disappear by changing the
    // default tab to the shop.
    $tabs['nastaveni'] = $ucs ? 'Nastavení účtu' : 'Account settings';

    // PROMO AKCE is its own account area. It must never share a pane with the
    // paid shop because purchase_enabled is deliberately allowed to disable
    // paid sales without affecting free promotional packages.
    if ($promoOn) {
        $tabs['promo'] = $ucs ? 'PROMO AKCE' : 'PROMO';
    }

    /*
     * Buying is offered only while there is something to buy.
     *
     * The portal is free now: credits arrive with a promo package, a code or
     * an invitation, and a tab called "Buy credits" that leads to an empty
     * shop is a promise the site does not keep. It comes back on its own the
     * moment paid packages are switched on again - and it also stays for
     * somebody who still has an unpaid order from before, because that order
     * has to remain payable.
     */
    if ($shopOn || $openOrders) {
        $tabs['koupit'] = $ucs ? 'Koupit kredity' : 'Buy credits';
    }
    $tabs['kod'] = $ucs ? 'Uplatnit kód' : 'Redeem a code';
    if ($refOn)  { $tabs['pozvi'] = $ucs ? 'Pozvi kamaráda' : 'Invite a friend'; }
    $tabs['historie']  = $ucs ? 'Historie' : 'History';

    $requestedTab = trim((string) ($_GET['tab'] ?? ''));
    $defaultTab = isset($tabs[$requestedTab]) ? $requestedTab : 'nastaveni';
  ?>
  <div class="acct-tabs" role="tablist">
    <?php foreach ($tabs as $key => $label): ?>
      <button type="button" class="acct-tab-btn<?= $key === $defaultTab ? ' active' : '' ?>"
              data-atab="<?= e($key) ?>" role="tab"><?= e($label) ?></button>
    <?php endforeach; ?>
  </div>

  <div class="acct-tab-pane<?= $defaultTab === 'kod' ? ' active' : '' ?>" data-apane="kod">
    <div class="card">
      <h2><?= e(__('acc.redeem')) ?></h2>
      <form method="post" data-busy="<?= e(__('busy.redeem')) ?>">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="redeem">
        <label for="code"><?= e(__('acc.code')) ?></label>
        <input id="code" name="code" type="text" class="mono" placeholder="ABCD-EFGH-JKLM"
               autocomplete="off" required>
        <div class="hint"><?= e(__('acc.codehint')) ?></div>
        <button class="btn primary" type="submit" style="margin-top:14px"><?= e(__('acc.redeem.btn')) ?></button>
      </form>
      <p class="hint" style="margin-bottom:0"><?= e($ucs
          ? 'Uplatněné kódy najdeš v Historii.'
          : 'Codes you have already used are listed under History.') ?></p>
    </div>
  </div>

  <div class="acct-tab-pane" data-apane="pozvi">
    <?php if ($refOn): ?>
      <?php
        $refCode  = Referral::codeFor($user);
        $refLink  = originUrl() . '/login.php?tab=register&ref=' . urlencode($refCode);
        $refStats = Referral::statsFor((int) $user['id']);
        $refBonus = Cred::fmt(Settings::int('referral_bonus'));
        $refCap   = (int) $refStats['cap'];
        $refUsed  = $refCap > 0 ? min((int) $refStats['invited'], $refCap) : (int) $refStats['invited'];
      ?>
      <div class="card">
        <div class="ref-head">
          <h2 style="margin:0;border:0;padding:0"><?= e($ucs ? 'Pozvi kamaráda' : 'Invite a friend') ?></h2>
          <span class="ref-counter<?= $refCap > 0 && $refUsed >= $refCap ? ' full' : '' ?>"
                title="<?= e($ucs ? 'Získané odměny / maximum' : 'Rewards earned / maximum') ?>">
            <?= e($ucs ? 'Odměny' : 'Rewards') ?> <b><?= $refUsed ?> / <?= $refCap > 0 ? $refCap : '∞' ?></b>
          </span>
        </div>
        <p class="hint" style="margin-top:8px"><?= e($ucs
            ? "Kamarád se zaregistruje s tvým kódem, ověří e-mail a oba dostanete {$refBonus} kreditů."
            : "Your friend registers with your code, verifies their e-mail and you both get {$refBonus} credits.") ?></p>
        <div class="ref-row">
          <span class="ref-label"><?= e($ucs ? 'Tvůj kód' : 'Your code') ?></span>
          <code class="mono ref-code" id="refCode"><?= e($refCode) ?></code>
          <button class="btn small" type="button" data-copy="<?= e($refCode) ?>"><?= e($ucs ? 'Kopírovat' : 'Copy') ?></button>
        </div>
        <div class="ref-row">
          <span class="ref-label"><?= e($ucs ? 'Odkaz' : 'Link') ?></span>
          <code class="mono ref-link" id="refLink"><?= e($refLink) ?></code>
          <button class="btn small" type="button" data-copy="<?= e($refLink) ?>"><?= e($ucs ? 'Kopírovat' : 'Copy') ?></button>
        </div>
        <p class="hint" style="margin-bottom:0"><?= e($ucs
            ? 'Pozváno a ověřeno: ' . $refStats['invited'] . ' · Získáno: ' . Cred::fmt($refStats['earned']) . ' kreditů'
            : 'Invited & verified: ' . $refStats['invited'] . ' · Earned: ' . Cred::fmt($refStats['earned']) . ' credits') ?></p>

        <?php $refPeople = Referral::invitedBy((int) $user['id']); ?>
        <?php if ($refPeople): ?>
          <div class="ref-people">
            <div class="ref-people-head"><?= e($ucs ? 'Tví pozvaní' : 'Your invitees') ?></div>
            <?php foreach ($refPeople as $rp): ?>
              <div class="ref-person">
                <span class="mono"><?= e($rp['who']) ?></span>
                <span class="hint"><?= e(date('j.n.Y', $rp['at'])) ?></span>
                <?php if ($rp['verified']): ?>
                  <span class="tag on"><?= e($ucs ? 'Ověřeno ✓' : 'Verified ✓') ?></span>
                <?php else: ?>
                  <span class="tag warn"><?= e($ucs ? 'Čeká na ověření' : 'Awaiting verification') ?></span>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div><!-- /pane pozvi -->

  <?php
    // The identity and appearance cards are rendered here but printed into
    // their own tab further down, so this file keeps reading top to bottom.
    $ulang = Auth::userLang($user);
  ?>
  <?php ob_start(); /* settings-tab cards are rendered now, printed later */ ?>
  <div class="grid2">
    <?php if (!Auth::isPrivileged($user) && Settings::bool('credits_enabled')): ?>
      <div class="card">
        <h2><?= e(__('acc.prefs')) ?></h2>
        <form method="post" data-busy="<?= e(__('acc.save')) ?>">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="action" value="prefs">
          <label class="check">
            <input type="checkbox" name="confirm_spend" value="1"
                   <?= Quota::confirmSpend($user) ? 'checked' : '' ?>>
            <span>
              <strong><?= e(__('acc.confirmspend')) ?></strong>
              <div class="hint"><?= e(__('acc.confirmhint')) ?></div>
            </span>
          </label>
          <button class="btn" type="submit" style="margin-top:12px"><?= e(__('acc.save')) ?></button>
        </form>
      </div>
    <?php endif; ?>

    <div class="card">
      <h2><?= e($ucs ? 'Nastavení zobrazení' : 'Display settings') ?></h2>
      <p class="hint" style="margin-top:0"><?= e($ucs
          ? 'Jazyk a jednotky. Projeví se hned; ve zvoleném jazyce ti přijdou i e-maily.'
          : 'Language and units. Takes effect immediately; e-mails arrive in the chosen language too.') ?></p>
      <form method="post" class="account-setting-row">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="set_lang">
        <label for="prefLang"><?= e($ucs ? 'Jazyk' : 'Language') ?></label>
        <select id="prefLang" name="lang" onchange="this.form.submit()" style="width:auto">
          <option value="en"<?= $accountLang === 'en' ? ' selected' : '' ?>>English</option>
          <option value="cs"<?= $accountLang === 'cs' ? ' selected' : '' ?>>Čeština</option>
        </select>
      </form>
      <form method="post" class="account-setting-row">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="set_unit">
        <label for="prefUnit"><?= e($ucs ? 'Jednotky' : 'Units') ?></label>
        <select id="prefUnit" name="unit" onchange="this.form.submit()" style="width:auto">
          <option value="mm"<?= $accountUnit === 'mm' ? ' selected' : '' ?>><?= e($ucs ? 'Milimetry (mm)' : 'Millimeters (mm)') ?></option>
          <option value="in"<?= $accountUnit === 'in' ? ' selected' : '' ?>><?= e($ucs ? 'Palce (in)' : 'Inches (in)') ?></option>
        </select>
      </form></div>

    <div class="card">
      <h2><?= e(__('acc.password')) ?></h2>
      <form method="post" data-busy="<?= e(__('busy.password')) ?>">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="password">
        <label for="current"><?= e(__('acc.pass.current')) ?></label>
        <input id="current" name="current" type="password" autocomplete="current-password" required>
        <label for="new"><?= e(__('acc.pass.new')) ?></label>
        <input id="new" name="new" type="password" autocomplete="new-password" minlength="<?= Auth::PASSWORD_MIN ?>" required>
        <!-- Say the rules before the form is sent, not after it is refused. -->
        <div class="hint"><?= e(__('reset.rule')) ?></div>
        <button class="btn" type="submit" style="margin-top:14px"><?= e($ucs ? 'Změnit heslo' : 'Change password') ?></button>
      </form>
    </div>

    <div class="card">
      <h2><?= e($ucs ? 'Zobrazované jméno' : 'Display name') ?></h2>
      <form method="post" data-busy="<?= e($ucs ? 'Ukládám…' : 'Saving…') ?>">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="nickname">
        <label for="nickname"><?= e($ucs ? 'Jméno, které uvidí podpora' : 'The name support sees') ?></label>
        <input id="nickname" name="nickname" type="text" minlength="3" maxlength="20"
               value="<?= e((string) ($user['nickname'] ?? '')) ?>">
        <div class="hint"><?= e($ucs
          ? 'Přihlašuješ se e-mailem; tohle je jen jméno u zpráv. Prázdné = ukáže se e-mail. 3 až 20 znaků.'
          : 'You sign in with your e-mail; this is only the name on your messages. Empty means the address is shown. 3 to 20 characters.') ?></div>
        <button class="btn" type="submit" style="margin-top:14px"><?= e($ucs ? 'Uložit' : 'Save') ?></button>
      </form>
    </div>

    <div class="card">
      <h2><?= e($ucs ? 'Změna e-mailu' : 'Change email') ?></h2>
      <form method="post" data-busy="<?= e($ucs ? 'Odesílám…' : 'Sending…') ?>">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="email">
        <label for="newemail"><?= e($ucs ? 'Nový e-mail' : 'New email') ?></label>
        <input id="newemail" name="email" type="email" autocomplete="email" required>
        <p class="hint"><?= e($ucs ? 'Na novou adresu pošleme ověřovací odkaz - změna proběhne až po jeho otevření.' : 'We send a confirmation link to the new address; the change happens once you open it.') ?></p>
        <button class="btn" type="submit" style="margin-top:14px"><?= e($ucs ? 'Poslat ověřovací odkaz' : 'Send confirmation link') ?></button>
      </form>
    </div>

    <?php
      $billingPrefs = json_decode((string) ($user['prefs'] ?? ''), true);
      $billing = is_array($billingPrefs['billing'] ?? null) ? $billingPrefs['billing'] : [];
      $bv = static fn (string $k): string => (string) ($billing[$k] ?? '');
    ?>
    <div class="card">
      <h2><?= e($ucs ? 'Fakturační údaje' : 'Billing details') ?></h2>
      <p class="hint"><?= e($ucs
          ? 'Nepovinné. Když je vyplníš, objeví se na faktuře. Vidíš je jen ty po přihlášení.'
          : 'Optional. When filled in, they appear on your invoice. Only you can see them, signed in.') ?></p>
      <form method="post" data-busy="<?= e($ucs ? 'Ukládám…' : 'Saving…') ?>">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="billing">
        <label for="b_name"><?= e($ucs ? 'Jméno / firma' : 'Name / company') ?></label>
        <input id="b_name" name="b_name" type="text" maxlength="120" autocomplete="name" value="<?= e($bv('name')) ?>">
        <label for="b_street" style="margin-top:10px"><?= e($ucs ? 'Ulice a číslo' : 'Street and number') ?></label>
        <input id="b_street" name="b_street" type="text" maxlength="120" autocomplete="street-address" value="<?= e($bv('street')) ?>">
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:10px;margin-top:10px">
          <div>
            <label for="b_city"><?= e($ucs ? 'Město' : 'City') ?></label>
            <input id="b_city" name="b_city" type="text" maxlength="120" autocomplete="address-level2" value="<?= e($bv('city')) ?>">
          </div>
          <div>
            <label for="b_zip"><?= e($ucs ? 'PSČ' : 'ZIP') ?></label>
            <input id="b_zip" name="b_zip" type="text" maxlength="16" autocomplete="postal-code" value="<?= e($bv('zip')) ?>">
          </div>
        </div>
        <label for="b_country" style="margin-top:10px"><?= e($ucs ? 'Země' : 'Country') ?></label>
        <input id="b_country" name="b_country" type="text" maxlength="60" autocomplete="country-name" value="<?= e($bv('country')) ?>">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px">
          <div>
            <label for="b_cin"><?= e($ucs ? 'IČO' : 'Company ID') ?></label>
            <input id="b_cin" name="b_cin" type="text" maxlength="20" value="<?= e($bv('cin')) ?>">
          </div>
          <div>
            <label for="b_vatid"><?= e($ucs ? 'DIČ' : 'VAT ID') ?></label>
            <input id="b_vatid" name="b_vatid" type="text" maxlength="20" value="<?= e($bv('vatid')) ?>">
          </div>
        </div>
        <button class="btn" type="submit" style="margin-top:14px"><?= e($ucs ? 'Uložit' : 'Save') ?></button>
      </form>
    </div>
  </div>
  <?php $settingsCards = ob_get_clean(); ?>

  <?php if ($promoOn): ?>
  <div class="acct-tab-pane<?= $defaultTab === 'promo' ? ' active' : '' ?>" data-apane="promo">
    <div class="card promo-card">
      <div class="card-head">
        <div>
          <h2 style="border:0;padding:0;margin:0">PROMO AKCE</h2>
          <p class="hint" style="margin:5px 0 0">
            <?= e($ucs
              ? 'Bezplatné balíčky, které můžeš aktivovat přímo na svém účtu.'
              : 'Free packages you can activate directly on your account.') ?>
          </p>
        </div>
        <span class="tag on"><?= e($ucs ? 'ZDARMA' : 'FREE') ?></span>
      </div>
      <div class="pkg-grid">
        <?php
          $cur = Money::current();
          foreach ($freePackages as $p):
            $promoMode = true;
            require __DIR__ . '/_pkg_card.php';
          endforeach;
          unset($promoMode);
        ?>
      </div>
    </div>
  </div><!-- /pane promo -->
  <?php endif; ?>

  <?php if ($shopOn || $openOrders): ?>
  <div class="acct-tab-pane<?= $defaultTab === 'koupit' ? ' active' : '' ?>" data-apane="koupit">

    <?php $shopCs = $ucs; ?>
    <?php if ($openOrders): ?>
      <?php $openOrdersCs = Lang::current() === 'cs'; ?>
      <div class="card open-orders-card">
        <div class="card-head">
          <div>
            <h2 style="border:0;padding:0;margin:0"><?= e($openOrdersCs ? 'Otevřené objednávky' : 'Open orders') ?></h2>
            <p class="hint" style="margin:5px 0 0"><?= e($openOrdersCs
              ? 'Objednávky, které ještě čekají na dokončení nebo platbu. V detailu najdeš všechny údaje, platbu i možnosti zrušení.'
              : 'Orders that still need payment or processing. Open the details for payment, full information and cancellation options.') ?></p>
          </div>
          <span class="tag warn"><?= e((string) count($openOrders)) ?></span>
        </div>

        <div class="open-orders-list">
          <?php foreach ($openOrders as $o): ?>

            <div class="open-order-row">
              <div class="open-order-main">
                <strong><?= e((string) $o['reference']) ?></strong>
                <span>
                  <?php if ((int) ($o['sub_days'] ?? 0) > 0): ?>
                    <?= e(Orders::durationLabel((int) $o['sub_days'], Lang::current())) ?>
                  <?php else: ?>
                    <?= e(Cred::fmt((int) ($o['credits'] ?? 0)) . ($shopCs ? ' kreditů' : ' credits')) ?>
                  <?php endif; ?>
                </span>
                <small><?= e(when((int) ($o['created_at'] ?? 0))) ?></small>
              </div>
              <div class="open-order-right">
                <span class="tag <?= $o['status'] === 'pending' ? 'warn' : '' ?>">
                  <?= e(Lang::status((string) $o['status'])) ?>
                </span>
                <strong><?= e(money((int) ($o['price_cents'] ?? 0), $o['currency'] ?? null)) ?></strong>
                <a class="btn small primary" href="order.php?ref=<?= e(urlencode((string) $o['reference'])) ?>">
                  <?= e('Info') ?>
                </a>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endif; ?>


    <?php
      // Split the shelf: one-off credit packs and time passes are different
      // purchases and read more clearly under their own heading than mixed
      // into one grid.
      $creditPkgs = array_values(array_filter($paidPackages, static fn($p) => (int) ($p['sub_days'] ?? 0) === 0));
      $timePkgs   = array_values(array_filter($paidPackages, static fn($p) => (int) ($p['sub_days'] ?? 0) > 0));
      $cur        = Money::current();
      $others     = Money::active();
      $manyCur    = count($others) > 1 || ($others && $others[0]['code'] !== Money::base());
    ?>

    <div class="card">
      <?php if ($shopOn): ?>
      <div class="card-head">
        <h2 style="border:0;padding:0;margin:0"><?= e(__('acc.buy')) ?></h2>
        <?php if ($manyCur): ?>
          <form method="post" class="currency-picker" data-busy="<?= e(__('busy.currency')) ?>">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="currency">
            <label for="curcode" style="margin:0"><?= e(__('acc.payin')) ?></label>
            <select id="curcode" name="code" onchange="this.form.submit()">
              <?php
                $codes = [];
                foreach ($others as $c) { $codes[$c['code']] = $c; }
                if (!isset($codes[Money::base()])) { $codes[Money::base()] = ['code' => Money::base()]; }
              ?>
              <?php foreach ($codes as $code => $c): ?>
                <option value="<?= e($code) ?>" <?= $cur === $code ? 'selected' : '' ?>><?= e($code) ?></option>
              <?php endforeach; ?>
            </select>
            <noscript><button class="btn small" type="submit"><?= e(__('acc.change')) ?></button></noscript>
          </form>
        <?php endif; ?>
      </div>

      <?php if ($manyCur && $cur !== Money::base()): ?>
        <p class="hint" style="margin-top:0"><?= e(__('acc.convertedfrom', Money::base())) ?></p>
      <?php endif; ?>

      <?php if (Pricing::discountActive()): ?>
        <div class="sale-banner">
          <strong><?= e(Pricing::label()) ?></strong>
          <span><?= e(Pricing::percent()) ?>% <?= e(__('acc.offperpack')) ?></span>
          <?php if (Pricing::until() !== ''): ?>
            <span class="hint"><?= e(__('acc.until')) ?> <?= e(Pricing::until()) ?></span>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <?php if ($creditPkgs): ?>
        <?php if ($timePkgs): ?>
          <h3 class="shop-group"><?= e($shopCs ? 'Kredity' : 'Credits') ?></h3>
          <p class="hint" style="margin:0 0 12px"><?= e($shopCs
              ? 'Jednorázový nákup, kredity nevyprší.'
              : 'A one-off purchase; credits do not expire.') ?></p>
        <?php endif; ?>
        <div class="pkg-grid">
          <?php foreach ($creditPkgs as $p): require __DIR__ . '/_pkg_card.php'; endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if ($timePkgs): ?>
        <h3 class="shop-group"><?= e($shopCs ? 'Časové balíčky' : 'Time passes') ?></h3>
        <p class="hint" style="margin:0 0 12px"><?= e($shopCs
            ? 'Neomezené exporty po celou dobu platnosti, kredity se přitom neodečítají.'
            : 'Unlimited exports for the whole period; no credits are spent meanwhile.') ?></p>
        <div class="pkg-grid">
          <?php foreach ($timePkgs as $p): require __DIR__ . '/_pkg_card.php'; endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!$creditPkgs && !$timePkgs): ?>
        <div class="msg info" style="margin-bottom:0">
          <?= e($ucs ? 'Momentálně nejsou v prodeji žádné balíčky.' : 'There are currently no packages for sale.') ?>
        </div>
      <?php endif; ?>
      <?php else: ?>
        <div class="msg info" style="margin-bottom:0">
          <?= e($ucs ? 'Prodej balíčků je momentálně vypnutý. Nejsou v prodeji žádné balíčky.' : 'Package sales are currently disabled. There are no packages for sale.') ?>
        </div>
      <?php endif; ?>
      <div class="msg info" style="margin-bottom:0"><?= e(Lang::instructions()) ?></div>
    </div>
  </div><!-- /pane koupit -->
  <?php endif; ?>

  <div class="acct-tab-pane<?= $defaultTab === 'historie' ? ' active' : '' ?>" data-apane="historie">

  <?php if ($orders): ?>
    <div class="card">
      <h2><?= e(__('acc.orders')) ?></h2>
      <div class="table-scroll">
        <table>
          <thead><tr><th><?= e(__('th.when')) ?></th><th><?= e(__('th.reference')) ?></th><th class="num"><?= e(__('th.credits')) ?></th><th class="num"><?= e(__('th.amount')) ?></th><th><?= e(__('th.status')) ?></th><th></th></tr></thead>
          <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td><?= e(when((int) $o['created_at'])) ?></td>
              <td class="mono"><?= e($o['reference']) ?></td>
              <td class="num">
                <?php if ((int) ($o['sub_days'] ?? 0) > 0): ?>
                  <?= e(Orders::durationLabel((int) $o['sub_days'], Lang::current())) ?>
                  <?php if ((int) $o['credits'] > 0): ?><div class="hint">+ <?= e(Cred::fmt((int) $o['credits'])) ?></div><?php endif; ?>
                <?php else: ?>
                  <?= e(Cred::fmt((int) $o['credits'])) ?>
                <?php endif; ?>
              </td>
              <td class="num"><?= e(money((int) $o['price_cents'], $o['currency'] ?? null)) ?></td>
              <td>
                <span class="tag <?= $o['status'] === 'paid' ? 'on' : ($o['status'] === 'cancelled' ? 'bad' : 'warn') ?>">
                  <?= e(Lang::status((string) $o['status'])) ?>
                </span>
              </td>
              <td>
                <!-- One flex row, so a button carrying an icon cannot sit a
                     few pixels off the ones next to it. -->
                <div class="row-actions">
                  <?php if ($o['status'] === 'pending'): ?>
                    <?php foreach (Payments::active($o['currency'] ?? null) as $pm): ?>
                      <?php $r = Payments::forOrder($pm, $o); ?>
                      <?php if ($r['url'] !== ''): ?>
                        <a class="btn small pay-btn" href="<?= e($r['url']) ?>" target="_blank" rel="noopener"
                           title="<?= e($r['label']) ?>"><?= Payments::icon($r['kind'], 16) ?><?= e($r['label']) ?></a>
                      <?php endif; ?>
                    <?php endforeach; ?>
                    <a class="btn small" href="order.php?ref=<?= e(urlencode($o['reference'])) ?>"><?= e(__('acc.detail')) ?></a>
                    <form method="post" data-busy="<?= e(__('busy.cancel')) ?>">
                      <?= Auth::csrfField() ?>
                      <input type="hidden" name="action" value="cancel_order">
                      <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                      <button class="btn small danger" type="submit"
                              data-confirm="<?= e(__('acc.cancelask')) ?>"
                              data-confirm-ok="<?= e(__('acc.cancel')) ?>"><?= e(__('acc.cancel')) ?></button>
                    </form>
                  <?php endif; ?>
                  <?php if ($o['status'] === 'paid'): ?>
                    <a class="btn small" target="_blank" rel="noopener"
                       href="invoice.php?ref=<?= e(urlencode((string) $o['reference'])) ?>&amp;t=<?= e(urlencode((string) ($o['access_token'] ?? ''))) ?>">
                      <?= e(Lang::current() === 'cs' ? 'Faktura' : 'Receipt') ?>
                    </a>
                    <?php if (Orders::refundBlocker($o, $user) === ''): ?>
                      <!-- Within the withdrawal window and nothing used yet. -->
                      <form method="post" data-busy="<?= e($ucs ? 'Odesílám žádost…' : 'Sending…') ?>">
                        <?= Auth::csrfField() ?>
                        <input type="hidden" name="action" value="request_refund">
                        <input type="hidden" name="order_id" value="<?= (int) $o['id'] ?>">
                        <button class="btn small danger" type="submit"
                                data-confirm="<?= e($ucs
                                    ? 'Požádat o vrácení peněz? Co ti objednávka připsala, se hned pozastaví a nepůjde utratit, dokud se žádost nevyřídí.'
                                    : 'Ask for a refund? Whatever the order granted is put on hold straight away and cannot be spent until the request is settled.') ?>"
                                data-confirm-ok="<?= e($ucs ? 'Požádat o vrácení' : 'Request refund') ?>"><?= e($ucs ? 'Vrátit peníze' : 'Refund') ?></button>
                      </form>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
                <?php if ($o['status'] === 'cancelled' && trim((string) $o['admin_note']) !== ''): ?>
                  <div class="hint"><?= e($o['admin_note']) ?></div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <?php
    $csx = Lang::current() === 'cs';
    $myCodes = Db::pdo()->prepare(
        'SELECT cu.created_at, c.label, c.code, c.credits, c.sub_days
         FROM code_uses cu JOIN codes c ON c.id = cu.code_id
         WHERE cu.user_id = ? ORDER BY cu.created_at DESC'
    );
    $myCodes->execute([(int) $user['id']]);
    $myCodes = $myCodes->fetchAll();
  ?>
  <?php if ($myCodes): ?>
    <div class="card">
      <h2><?= e($csx ? 'Uplatněné kódy' : 'Redeemed codes') ?></h2>
      <div class="table-scroll">
        <table>
          <thead><tr>
            <th><?= e(__('th.when')) ?></th>
            <th><?= e($csx ? 'Kód' : 'Code') ?></th>
            <th><?= e($csx ? 'Získáno' : 'Received') ?></th>
          </tr></thead>
          <tbody>
          <?php foreach ($myCodes as $mc): ?>
            <?php
              $mcBits = [];
              if ((int) $mc['credits'] !== 0) {
                  $mcBits[] = Cred::fmtCs((int) $mc['credits']) . ($csx ? ' kreditů' : ' credits');
              }
              if ((int) ($mc['sub_days'] ?? 0) > 0) {
                  $mcBits[] = Orders::durationLabel((int) $mc['sub_days'], $csx ? 'cs' : 'en')
                            . ($csx ? ' neomezeně' : ' unlimited');
              }
            ?>
            <tr>
              <td><?= e(when((int) $mc['created_at'])) ?></td>
              <td class="mono"><?= e(trim((string) ($mc['label'] ?? '')) !== '' ? $mc['label'] : $mc['code']) ?></td>
              <td><?= e($mcBits ? implode(' + ', $mcBits) : '-') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2><?= e(__('acc.history')) ?></h2>
    <?php if (!$history): ?>
      <p class="hint"><?= e(__('acc.nohistory')) ?></p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead><tr><th><?= e(__('th.when')) ?></th><th><?= e(__('th.reason')) ?></th><th><?= e(__('th.reference')) ?></th><th class="num"><?= e(__('th.change')) ?></th><th class="num"><?= e(__('th.balance')) ?></th></tr></thead>
          <tbody>
          <?php foreach ($history as $h): ?>
            <tr>
              <td><?= e(when((int) $h['created_at'])) ?></td>
              <?php $reasonLabel = Lang::reason((string) $h['reason']); ?>
              <td><?= e($reasonLabel) ?></td>
              <td class="mono"><?= e(ref_label($h['ref'] ?? null)) ?></td>
              <?php $hd = (int) $h['delta']; ?>
              <td class="num" style="color:<?= $hd < 0 ? 'var(--bad)' : ($hd > 0 ? 'var(--good)' : 'var(--muted,#68707a)') ?>">
                <?= $hd === 0 ? '·' : ($hd > 0 ? '+' : '-') . e(Cred::fmt(abs($hd))) ?>
              </td>
              <td class="num"><?= e(Cred::fmt((int) $h['balance_after'])) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <?php if (!$orders && !$myCodes && !$history): ?>
    <p class="hint"><?= e($ucs
        ? 'Až něco koupíš nebo uplatníš kód, najdeš to tady.'
        : 'Once you buy something or redeem a code, it shows up here.') ?></p>
  <?php endif; ?>

  </div><!-- /pane historie -->

  <div class="acct-tab-pane<?= $defaultTab === 'nastaveni' ? ' active' : '' ?>" data-apane="nastaveni">
    <?= $settingsCards ?>
  </div>

</div>

<?php
  $profilePrefs = json_decode((string) ($user['prefs'] ?? ''), true);
  $profilePrefs = is_array($profilePrefs) ? $profilePrefs : [];
  $profileAppearance = [];
  foreach (['lang', 'unit'] as $prefKey) {
      if (isset($profilePrefs[$prefKey])) {
          $profileAppearance[$prefKey] = $profilePrefs[$prefKey];
      }
  }
?>
<script>
window.H3D_PROFILE_PREFS = <?= json_encode($profileAppearance, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
(function(){
  // Tab switching, remembered in the hash so a reload - and the redirect that
  // follows every form here - stays where you were.
  var btns=document.querySelectorAll('.acct-tab-btn');
  var panes=document.querySelectorAll('.acct-tab-pane');
  function has(name){
    return Array.prototype.some.call(btns,function(b){ return b.dataset.atab===name; });
  }
  function show(name){
    if(!has(name)) return;
    btns.forEach(function(b){ b.classList.toggle('active', b.dataset.atab===name); });
    panes.forEach(function(p){ p.classList.toggle('active', p.dataset.apane===name); });
    try{ history.replaceState(null,'','#'+name); }catch(e){}
  }
  btns.forEach(function(b){ b.addEventListener('click',function(){ show(b.dataset.atab); }); });

  // Links and buttons elsewhere on the page can open a tab: the "top up"
  // button in the status strip is one.
  document.querySelectorAll('[data-atab-go]').forEach(function(el){
    el.addEventListener('click',function(){
      show(el.dataset.atabGo);
      window.scrollTo({top:0,behavior:'smooth'});
    });
  });

  // "#kredity" and "#settings" are what the two old tabs used.
  var fromHash=(location.hash||'').replace('#','');
  if(fromHash==='settings') fromHash='nastaveni';
  if(fromHash==='credits'||fromHash==='kredity') fromHash='';
  if(fromHash) show(fromHash);
  window.addEventListener('hashchange',function(){
    var h=(location.hash||'').replace('#','');
    if(h) show(h);
  });

  // Language and units are saved by native POST forms above.
  // This deliberately does not use fetch() so a CSRF/session error cannot
  // be swallowed by a silent redirect.


  // Copy buttons for the referral code and link.
  document.querySelectorAll('[data-copy]').forEach(function(btn){
    btn.addEventListener('click',function(){
      var t=btn.getAttribute('data-copy');
      function done(){ var old=btn.textContent; btn.textContent='✓'; setTimeout(function(){ btn.textContent=old; },1200); }
      if(navigator.clipboard&&navigator.clipboard.writeText){ navigator.clipboard.writeText(t).then(done).catch(function(){}); }
      else{ var ta=document.createElement('textarea'); ta.value=t; document.body.appendChild(ta); ta.select(); try{document.execCommand('copy');done();}catch(e){} ta.remove(); }
    });
  });

})();
</script>

<?php
/*
 * Shown once, right after an order is placed. The same shape as the email:
 * what was ordered, what to pay, and how - so somebody who never opens the
 * email still has everything in front of them.
 */
$justOrdered = null;
if (isset($_GET['ordered'])) {
    $st = Db::pdo()->prepare('SELECT * FROM orders WHERE reference = ? AND user_id = ?');
    $st->execute([(string) $_GET['ordered'], (int) $user['id']]);
    $justOrdered = $st->fetch() ?: null;
}
?>
<?php if ($justOrdered): ?>
  <div class="order-backdrop" id="orderDialog">
    <div class="order-dialog" role="dialog" aria-modal="true" aria-labelledby="orderDialogTitle">
      <div class="order-dialog-head">
        <span class="order-tick" aria-hidden="true">&#10003;</span>
        <h2 id="orderDialogTitle"><?= e(__('ord.created')) ?></h2>
      </div>

      <p class="order-lede">
        <?= e($justOrdered['status'] === 'paid' ? __('state.paid.body') : __('ord.created.lede')) ?>
      </p>

      <table class="order-summary-table">
        <tr><td><?= e(__('th.reference')) ?></td>
            <td class="mono"><?= e($justOrdered['reference']) ?></td></tr>
        <?php $vs = Payments::vs($justOrdered); ?>
        <?php if ($vs !== ''): ?>
          <tr><td><?= e(__('ord.vs')) ?></td><td class="mono"><?= e($vs) ?></td></tr>
        <?php endif; ?>
        <tr><td><?= e(__('th.credits')) ?></td>
            <td><?= e(Cred::fmt((int) $justOrdered['credits'])) ?></td></tr>
        <tr><td><?= e(__('th.amount')) ?></td>
            <td><strong><?= e(money((int) $justOrdered['price_cents'], $justOrdered['currency'] ?? null)) ?></strong></td></tr>
      </table>

      <?php if ($justOrdered['status'] === 'pending'): ?>
        <h3 class="order-dialog-sub"><?= e(__('ord.howtopay')) ?></h3>
        <?php
          $order  = $justOrdered;
          $mayPay = true;
          require __DIR__ . '/_payment_options.php';
        ?>
        <?php if (trim(Lang::instructions()) !== ''): ?>
          <p class="hint"><?= e(Lang::instructions()) ?></p>
        <?php endif; ?>
        <?php if (Orders::processingNote() !== ''): ?>
          <p class="hint"><?= e(Orders::processingNote()) ?></p>
        <?php endif; ?>
      <?php endif; ?>

      <div class="order-dialog-actions">
        <a class="btn primary" href="order.php?ref=<?= e(urlencode($justOrdered['reference'])) ?>">
          <?= e(__('acc.detail')) ?>
        </a>
        <button class="btn" type="button" data-close><?= e(__('ord.close')) ?></button>
      </div>

      <?php if (Mailer::enabled()): ?>
        <p class="order-dialog-foot"><?= e(__('ord.emailed')) ?></p>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?php page_foot();
