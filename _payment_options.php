<?php
/**
 * Payment options for one unpaid order.
 *
 * Included by both the account page and the order status page, so the two
 * cannot drift apart. Expects $order and $mayPay in scope.
 */
// Only methods that accept the currency this order was placed in.
$methods = Payments::active($order['currency'] ?? null);
?>
<?php if ($methods && ($mayPay ?? true)): ?>
  <div class="pay-methods">
    <?php foreach ($methods as $pm): ?>
      <?php $r = Payments::forOrder($pm, $order); ?>
      <div class="pay-method">
        <?= Payments::icon($r['kind'], 26) ?>
        <div class="pay-body">
          <div class="pay-label"><?= e($r['label']) ?></div>
          <?php if ($r['text'] !== ''): ?>
            <div class="pay-target mono"><?= e($r['text']) ?></div>
          <?php endif; ?>
          <?php if ($r['detail']): ?>
            <dl class="pay-detail">
              <?php foreach ($r['detail'] as $k => $v): ?>
                <dt><?= e($k) ?></dt><dd class="mono"><?= e($v) ?></dd>
              <?php endforeach; ?>
            </dl>
          <?php endif; ?>
          <?php if ($r['note'] !== ''): ?>
            <div class="hint"><?= e($r['note']) ?></div>
          <?php endif; ?>
        </div>
        <?php if ($r['spayd'] !== ''): ?>
          <div class="pay-qr"><?= Qr::svg($r['spayd'], 150) ?></div>
        <?php endif; ?>
        <?php if ($r['url'] !== ''): ?>
          <a class="btn small primary" href="<?= e($r['url']) ?>" target="_blank" rel="noopener"><?= e(__('ord.pay')) ?></a>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php elseif (!($mayPay ?? true)): ?>
  <div class="msg info"><?= e(__('ord.signintopay')) ?></div>
  <a class="btn primary" href="login.php?next=<?= e(urlencode('order.php?ref=' . ($order['reference'] ?? ''))) ?>"><?= e(__('auth.signin.btn')) ?></a>
<?php endif; ?>
