<?php
/**
 * Administration tab: invoices
 *
 * Lifted out of admin/index.php, which had all twenty of these in one
 * 6000-line file. Included from there with the surrounding scope intact,
 * so $tab, $ok, $error, the helpers and everything else the page sets up
 * are available exactly as before.
 */
if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }

    $invoices = $pdo->query(
        'SELECT o.*, u.email FROM orders o JOIN users u ON u.id = o.user_id
         WHERE o.status = "paid"
         ORDER BY COALESCE(o.paid_at, o.created_at) DESC, o.id DESC
         LIMIT 300'
    )->fetchAll();
    $paidTotal = (int) $pdo->query('SELECT COALESCE(SUM(price_cents),0) FROM orders WHERE status = "paid"')->fetchColumn();
?>
  <div class="card">
    <h2>Faktury / doklady</h2>
    <p class="hint" style="margin-top:0">
      Ke každé zaplacené objednávce je doklad o platbě. Otevři ho a vytiskni,
      nebo ulož do PDF (tlačítko Tisk na dokladu). Zákazníkovi odešel odkazem
      v potvrzovacím e-mailu.
    </p>

    <div class="order-summary">
      <span><strong><?= count($invoices) ?></strong> zaplacených objednávek</span>
      <span>Celkem: <strong><?= e(money($paidTotal)) ?></strong></span>
    </div>

    <?php if (!$invoices): ?>
      <p class="hint">Zatím žádná zaplacená objednávka - jakmile označíš platbu (nebo si někdo vezme balíček zdarma), doklad se objeví tady.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead><tr><th>Datum úhrady</th><th>Zákazník</th><th>Doklad</th><th>Za co</th><th class="num">Částka</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($invoices as $o): ?>
            <tr>
              <td><?= e(when($o['paid_at'] !== null ? (int) $o['paid_at'] : (int) $o['created_at'])) ?></td>
              <td><?= e($o['email']) ?></td>
              <td class="mono"><?= e($o['reference']) ?></td>
              <td>
                <?php if ((int) ($o['sub_days'] ?? 0) > 0): ?>
                  <?= e(Orders::durationLabel((int) $o['sub_days'], 'cs')) ?> neomezeně<?php if ((int) $o['credits'] > 0): ?> + <?= e(Cred::fmtCs((int) $o['credits'])) ?> kr.<?php endif; ?>
                <?php else: ?>
                  <?= e(Cred::fmtCs((int) $o['credits'])) ?> kreditů
                <?php endif; ?>
              </td>
              <td class="num"><?= e(money((int) $o['price_cents'], $o['currency'] ?? null)) ?></td>
              <td class="num">
                <div class="row" style="gap:6px;justify-content:flex-end;flex-wrap:nowrap">
                  <a class="btn small icon-btn" target="_blank" rel="noopener" title="Zobrazit / tisk dokladu"
                     href="../invoice.php?ref=<?= e(urlencode((string) $o['reference'])) ?>&amp;t=<?= e(urlencode((string) ($o['access_token'] ?? ''))) ?>">
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V3h12v6"/><path d="M6 18H4a2 2 0 0 1-2-2v-4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="7" rx="1"/></svg>
                    <span>Tisk</span>
                  </a>
                  <form method="post" style="display:inline">
                    <?= Auth::csrfField() ?>
                    <input type="hidden" name="action" value="resend_invoice">
                    <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                    <button class="btn small" type="submit"
                            data-confirm="Odeslat doklad k objednávce <?= e($o['reference']) ?> znovu na <?= e($o['email']) ?>?"
                            data-confirm-ok="Odeslat">Odeslat znovu</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>
