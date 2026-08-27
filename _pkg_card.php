<?php
/**
 * One package card in the shop.
 *
 * Included by the account page for every package, in both the credit and the
 * time-based group, so the two groups render from exactly the same markup and
 * cannot drift apart. Expects $p, $user and $cur in scope.
 */
if (!defined('H3D_APP')) {
    http_response_code(403);
    exit('Forbidden');
}

// Record before reading, so a price set a moment ago is already part of the
// window rather than appearing next time.
Pricing::record((int) $p['id'], Pricing::of($p)['final']);
$pr = Pricing::of($p);
[$canOrder, $whyNot] = Pricing::mayOrder($p, (int) $user['id']);

$shownBase  = Money::convert($pr['base'],  $cur);
$shownFinal = Money::convert($pr['final'], $cur);

$days = (int) ($p['sub_days'] ?? 0);
$fav  = (int) ($p['featured'] ?? 0) === 1;
$csm  = Lang::current() === 'cs';
$promoMode = !empty($promoMode) && (int) ($p['price_cents'] ?? 0) === 0;
?>
<form method="post" class="card pkg<?= $pr['on'] ? ' pkg-sale' : '' ?><?= $fav ? ' pkg-fav' : '' ?><?= $promoMode ? ' pkg-promo' : '' ?>"
      style="margin:0" data-busy="<?= e(__('busy.order')) ?>">
  <?= Auth::csrfField() ?>
  <input type="hidden" name="action" value="order">
  <input type="hidden" name="package_id" value="<?= (int) $p['id'] ?>">

  <?php if ($promoMode): ?>
    <span class="pkg-fav-badge">PROMO AKCE</span>
  <?php elseif ($fav): ?>
    <span class="pkg-fav-badge"><?= e($csm ? '★ Oblíbený' : '★ Favourite') ?></span>
  <?php endif; ?>
  <?php if ($pr['on']): ?>
    <span class="pkg-badge">-<?= e($pr['percent']) ?>%</span>
  <?php endif; ?>

  <div class="pkg-name"><?= e($p['name']) ?></div>

  <?php if ($days > 0): ?>
    <div class="pkg-credits"><?= e(Orders::durationLabel($days, $csm ? 'cs' : 'en')) ?>
      <span><?= e($csm ? 'neomezeně' : 'unlimited') ?></span>
    </div>
    <?php if ((int) $p['credits'] > 0): ?>
      <div class="hint">+ <?= e(Cred::fmt((int) $p['credits'])) ?> <?= e($csm ? 'kreditů' : 'credits') ?></div>
    <?php endif; ?>

    <!-- No "starts when" choice any more: the package waits on the account
         and the customer starts it whenever it suits them. -->
    <div class="hint pkg-start-note"><?= e($csm
        ? 'Po zaplacení počká na účtu, spustíš ho, až budeš chtít.'
        : 'Once paid it waits on your account; you start it when you want.') ?></div>
  <?php else: ?>
    <div class="pkg-credits"><?= e(Cred::fmt((int) $p['credits'])) ?>
      <span><?= e((int) $p['credits'] === Cred::SCALE ? __('acc.credit') : __('acc.credits.plural')) ?></span>
    </div>
  <?php endif; ?>

  <div class="pkg-price">
    <?php if ($promoMode): ?>
      <strong><?= e($csm ? 'ZDARMA' : 'FREE') ?></strong>
    <?php elseif ($pr['on']): ?>
      <s><?= e(money($shownBase, $cur)) ?></s>
      <strong><?= e(money($shownFinal, $cur)) ?></strong>
    <?php elseif ($shownFinal === 0): ?>
      <strong><?= e(__('acc.free')) ?></strong>
    <?php else: ?>
      <strong><?= e(money($shownFinal, $cur)) ?></strong>
    <?php endif; ?>
  </div>

  <?php if (!$promoMode && $pr['final'] > 0): ?>
    <div class="pkg-lowest">
      <?php if ($pr['lowest30'] !== null && $pr['lowest30'] < $pr['final']): ?>
        <?= e(__('acc.lowest30')) ?>
        <?= e(money(Money::convert($pr['lowest30'], $cur), $cur)) ?>
      <?php else: ?>
        <?= e(__('acc.islowest')) ?>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <?php if ($canOrder): ?>
    <button class="btn primary" type="submit" style="width:100%"
            data-confirm="<?= e($promoMode
                ? ($csm ? 'Aktivovat promo balíček „' . $p['name'] . '“ zdarma?' : 'Activate the free promo package “' . $p['name'] . '”?')
                : (($csm ? 'Objednat ' : 'Order ') . $p['name'] . ($csm ? ' za ' : ' for ') . money($shownFinal, $cur) . '?')) ?>"
            data-confirm-ok="<?= e($promoMode ? ($csm ? 'Aktivovat' : 'Activate') : __('acc.order')) ?>"><?= e($promoMode ? ($csm ? 'Aktivovat' : 'Activate') : __('acc.order')) ?></button>
  <?php else: ?>
    <button class="btn" type="button" disabled style="width:100%"><?= e(__('acc.unavailable')) ?></button>
    <div class="hint"><?= e($whyNot) ?></div>
  <?php endif; ?>
</form>
