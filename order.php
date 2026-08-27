<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/View.php';

$cs = Lang::current() === 'cs';

// The reference can arrive in the link or be typed into the lookup form.
$ref   = trim((string) ($_GET['ref'] ?? $_POST['ref'] ?? ''));
$token = trim((string) ($_GET['t'] ?? ''));
$user  = Auth::user();

$order = null;
if ($ref !== '') {
    $st = Db::pdo()->prepare('SELECT * FROM orders WHERE reference = ?');
    $st->execute([$ref]);
    $order = $st->fetch() ?: null;
}

// Ways in, in the order they are trusted. The token from the email is the
// verified route and needs nothing else; the owner and an admin are known by
// their session. hash_equals keeps the token comparison constant-time so it
// cannot be recovered one character at a time.
$viaToken = $order
    && $token !== ''
    && (string) $order['access_token'] !== ''
    && hash_equals((string) $order['access_token'], $token);

$isOwner = $order && $user && (int) $order['user_id'] === (int) $user['id'];
$isAdmin = $user && (int) $user['is_admin'] === 1;

$actionOk = '';
$actionError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && (string) ($_POST['action'] ?? '') === 'cancel_order') {
    Auth::checkCsrf();

    if (!$isOwner) {
        $actionError = $cs
            ? 'Objednávku může zrušit jen její vlastník po přihlášení.'
            : 'Only the signed-in owner can cancel this order.';
    } else {
        Notify::$lang = Auth::userLang($user);
        [$done, $msg] = Orders::cancelPending($order, $user);
        if ($done) {
            $actionOk = $msg;
            // Refresh the row so the detail immediately shows the new state.
            $st = Db::pdo()->prepare('SELECT * FROM orders WHERE id = ?');
            $st->execute([(int) $order['id']]);
            $order = $st->fetch() ?: $order;
        } else {
            $actionError = $msg;
        }
    }
}

// Anyone else has to prove they know the address the order belongs to. This
// is what stops a guessed or forwarded reference from exposing someone else's
// order: the reference alone reveals nothing, not even whether it exists.
$emailTried = false;
$viaEmail   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$viaToken && !$isOwner && !$isAdmin) {
    Auth::checkCsrf();
    $emailTried = true;

    $entered = Auth::normaliseEmail((string) ($_POST['email'] ?? ''));
    if ($order && $entered !== '') {
        $owner = Auth::byId((int) $order['user_id']);
        // Compared even when the order is missing would still be safe, but
        // there is nothing to compare against, so a wrong reference and a
        // wrong email fail the same way - no enumeration either direction.
        if ($owner && hash_equals(Auth::normaliseEmail((string) $owner['email']), $entered)) {
            $viaEmail = true;
        }
    }
}

$authorized = $viaToken || $isOwner || $isAdmin || $viaEmail;
$show       = $order && $authorized;

// Paying is an account action: the credits have to land on the account, and
// the person following a forwarded link is not necessarily its holder.
$mayPay = $show && ($isOwner || $isAdmin);

page_head(__('ord.title') . ' ' . $ref);
?>
<div class="wrap narrow">
  <h1><?= e(__('ord.title')) ?></h1>

  <?php if (!$show): ?>
    <?php if ($emailTried): ?>
      <div class="msg err">
        <?= e($cs
            ? 'Žádná objednávka pro tenhle variabilní symbol a e-mail. Zkontroluj obojí, nebo použij odkaz z potvrzovacího e-mailu.'
            : 'No order matches that reference and email. Check both, or use the link from your confirmation email.') ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <p class="hint">
        <?= e($cs
            ? 'Stav objednávky ukážeme jen po zadání e-mailu, na který objednávka patří - odkaz z potvrzovacího e-mailu funguje rovnou.'
            : 'We show an order only after the email it belongs to is entered - the link from your confirmation email works straight away.') ?>
      </p>
      <form method="post">
        <?= Auth::csrfField() ?>
        <label for="ref"><?= e(__('th.reference')) ?></label>
        <input id="ref" name="ref" type="text" value="<?= e($ref) ?>"
               placeholder="H3D-000000" autocomplete="off" required>
        <label for="email"><?= e($cs ? 'E-mail na objednávce' : 'Email on the order') ?></label>
        <input id="email" name="email" type="email" autocomplete="email" required>
        <button class="btn primary" type="submit" style="margin-top:12px">
          <?= e($cs ? 'Zobrazit stav' : 'Show status') ?>
        </button>
      </form>
    </div>

    <?php if (!$user): ?>
      <p class="hint" style="margin-top:12px">
        <?= e($cs ? 'Máš účet? ' : 'Have an account? ') ?>
        <a href="login.php?next=account.php"><?= e(__('auth.signin.btn')) ?></a>
      </p>
    <?php endif; ?>

  <?php else: ?>
    <?php
      $state = [
          'pending'   => ['warn', __('state.pending'),   __('state.pending.body')],
          'accepted'  => ['warn', __('state.accepted'),  $cs ? 'Objednávka se zpracovává. Po přijetí už ji nelze zákazníkem jednostranně zrušit.' : 'The order is being processed. Once accepted, it can no longer be cancelled by the customer.'],
          'paid'      => ['on',   __('state.complete'),  __('state.paid.body')],
          'cancelled' => ['bad',  __('state.cancelled'), __('state.cancel.body')],
          'refund'    => ['warn', __('state.refund'),    $cs ? 'Objednávka čeká na vyřízení vrácení peněz.' : 'The refund is being processed.'],
          'refunded'  => ['on',   __('state.refunded'),  $cs ? 'Peníze byly vráceny.' : 'The money has been returned.'],
      ][$order['status']] ?? ['warn', Lang::status((string) $order['status']), ''];

      $pkg = null;
      if (!empty($order['package_id'])) {
          $pst = Db::pdo()->prepare('SELECT * FROM packages WHERE id = ?');
          $pst->execute([(int) $order['package_id']]);
          $pkg = $pst->fetch() ?: null;
      }
      $packageName = $pkg
          ? (string) ($pkg['name'] ?? $pkg['title'] ?? '')
          : '';
      $canCancel = $show && $isOwner && (string) $order['status'] === Orders::PENDING;
      $refundWhy = $isOwner ? Orders::refundBlocker($order, $user) : '';
      $canRefund = $isOwner && (string) $order['status'] === Orders::PAID && $refundWhy === '';
    ?>

    <?php if ($actionOk !== ''): ?>
      <div class="msg ok" style="margin-bottom:14px"><?= e($actionOk) ?></div>
    <?php endif; ?>
    <?php if ($actionError !== ''): ?>
      <div class="msg err" style="margin-bottom:14px"><?= e($actionError) ?></div>
    <?php endif; ?>

    <div class="card order-detail-card">
      <p><span class="tag <?= e($state[0]) ?>" style="font-size:13px"><?= e($state[1]) ?></span></p>
      <p class="hint"><?= e($state[2]) ?></p>

      <table>
        <tbody>
          <?php if ($packageName !== ''): ?>
            <tr><td><?= e($cs ? 'Balíček' : 'Package') ?></td><td><?= e($packageName) ?></td></tr>
          <?php endif; ?>
          <tr><td><?= e(__('th.reference')) ?></td><td class="mono"><?= e($order['reference']) ?></td></tr>
          <?php $vs = Payments::vs($order); ?>
          <?php if ($vs !== ''): ?>
            <tr><td><?= e(__('ord.vs')) ?></td><td class="mono"><?= e($vs) ?></td></tr>
          <?php endif; ?>
          <tr><td><?= e(__('th.credits')) ?></td><td><?= e(Cred::fmt((int) $order['credits'])) ?></td></tr>
          <tr><td><?= e(__('th.amount')) ?></td><td><?= e(money((int) $order['price_cents'], $order['currency'] ?? null)) ?></td></tr>
          <tr><td><?= e(__('th.when')) ?></td><td><?= e(when((int) $order['created_at'])) ?></td></tr>
          <?php if ($order['paid_at']): ?>
            <tr><td><?= e($cs ? 'Zaplaceno' : 'Paid') ?></td><td><?= e(when((int) $order['paid_at'])) ?></td></tr>
          <?php endif; ?>
          <?php if (!empty($order['accepted_at'])): ?>
            <tr><td><?= e($cs ? 'Přijato ke zpracování' : 'Accepted for processing') ?></td><td><?= e(when((int) $order['accepted_at'])) ?></td></tr>
          <?php endif; ?>
          <?php if ($order['cancelled_at']): ?>
            <tr><td><?= e(__('ord.cancelledon')) ?></td><td><?= e(when((int) $order['cancelled_at'])) ?></td></tr>
          <?php endif; ?>
          <?php if (trim((string) $order['admin_note']) !== ''): ?>
            <tr><td><?= e(__('th.reason')) ?></td><td><?= e($order['admin_note']) ?></td></tr>
          <?php endif; ?>
        </tbody>
      </table>

      <?php if ($canCancel): ?>
        <div class="order-detail-actionbar">
          <form method="post" data-busy="<?= e($cs ? 'Ruším objednávku…' : 'Cancelling the order…') ?>">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="ref" value="<?= e($order['reference']) ?>">
            <input type="hidden" name="action" value="cancel_order">
            <button class="btn danger" type="submit"
                    data-confirm="<?= e($cs
                      ? 'Opravdu chceš tuto objednávku zrušit? Objednávka ještě nebyla zaplacena ani přijata ke zpracování.'
                      : 'Cancel this order? It has not been paid or accepted for processing.') ?>"
                    data-confirm-ok="<?= e($cs ? 'Zrušit objednávku' : 'Cancel order') ?>">
              <?= e($cs ? 'Zrušit objednávku' : 'Cancel order') ?>
            </button>
          </form>
        </div>
      <?php endif; ?>

      <?php if ($order['status'] === 'pending'): ?>
        <div class="order-detail-section">
          <h3><?= e($cs ? 'Platba' : 'Payment') ?></h3>
          <?php require __DIR__ . '/_payment_options.php'; ?>
          <?php if (trim(Lang::instructions()) !== ''): ?>
            <div class="msg info" style="margin-top:14px"><?= e(Lang::instructions()) ?></div>
          <?php endif; ?>
          <?php if (Orders::processingNote() !== ''): ?>
            <p class="hint"><?= e(Orders::processingNote()) ?></p>
          <?php endif; ?>
        </div>

        <div class="order-detail-section order-rules">
          <h3><?= e($cs ? 'Možnosti objednávky' : 'Order options') ?></h3>
          <p class="hint">
            <?= e($cs
              ? 'Objednávku můžeš bezplatně zrušit, dokud čeká na platbu. Po přijetí ke zpracování už ji zákazník nemůže jednostranně zrušit. U zaplacené objednávky se případné vrácení peněz řídí 14denní lhůtou a podmínkami pro nevyužitou službu.'
              : 'You can cancel the order free of charge while it is waiting for payment. Once accepted for processing, it can no longer be cancelled by the customer. Paid orders may qualify for a refund within 14 days, subject to the unused-service conditions.') ?>
          </p>

        </div>
      <?php elseif ($order['status'] === 'accepted'): ?>
        <div class="order-detail-section order-rules">
          <h3><?= e($cs ? 'Stav zpracování' : 'Processing') ?></h3>
          <p class="hint">
            <?= e($cs
              ? 'Objednávka už byla přijata ke zpracování. Z tohoto důvodu ji nelze z účtu jednostranně zrušit. Pokud je potřeba řešit změnu nebo storno, kontaktuj správce.'
              : 'The order has already been accepted for processing and cannot be cancelled from the account. Contact the administrator if you need to resolve a change or cancellation.') ?>
          </p>
        </div>
      <?php elseif ($order['status'] === 'paid'): ?>
        <div class="order-detail-section order-rules">
          <h3><?= e($cs ? 'Vrácení peněz' : 'Refund') ?></h3>
          <?php if ($canRefund): ?>
            <p class="hint">
              <?= e($cs
                ? 'Podle pravidel je možné požádat o vrácení peněz. Žádost odešleš v historii objednávek na účtu.'
                : 'This order currently qualifies for a refund. You can submit the request from the order history in your account.') ?>
            </p>
            <a class="btn danger" href="account.php"><?= e($cs ? 'Požádat o vrácení' : 'Request refund') ?></a>
          <?php else: ?>
            <p class="hint"><?= e($refundWhy !== '' ? $refundWhy : ($cs ? 'Vrácení peněz není u této objednávky aktuálně dostupné.' : 'A refund is not currently available for this order.')) ?></p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if ($user): ?>
      <a class="btn" href="account.php"><?= e(__('ord.back')) ?></a>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php page_foot();
