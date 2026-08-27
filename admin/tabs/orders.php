<?php
/**
 * Administration tab: orders
 *
 * Lifted out of admin/index.php, which had all twenty of these in one
 * 6000-line file. Included from there with the surrounding scope intact,
 * so $tab, $ok, $error, the helpers and everything else the page sets up
 * are available exactly as before.
 */
if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }

    $q       = trim((string) ($_GET['q'] ?? ''));
    $group   = (string) ($_GET['group'] ?? 'all');
    if (!isset(Orders::GROUPS[$group])) {
        $group = 'all';
    }
    $status  = (string) ($_GET['status'] ?? '');
    $from    = trim((string) ($_GET['from'] ?? ''));
    $to      = trim((string) ($_GET['to'] ?? ''));
    $page    = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 50;

    $where  = [];
    $params = [];

    if ($q !== '') {
        // One box for both, because in practice you either have the
        // reference from a bank statement or the address from an email.
        $where[]  = '(u.email LIKE ? OR o.reference LIKE ?)';
        $params[] = '%' . $q . '%';
        $params[] = '%' . $q . '%';
    }
    // The tab decides the statuses; the old status parameter still works so
    // links kept from before do not break.
    $statuses = $status !== '' ? [$status] : Orders::statusesFor($group);
    $statuses = array_values(array_filter($statuses, static fn($x) =>
        in_array($x, [Orders::PENDING, Orders::ACCEPTED, Orders::PAID, Orders::CANCELLED, Orders::REFUND, Orders::REFUNDED], true)));

    if ($statuses) {
        $where[]  = 'o.status IN (' . implode(',', array_fill(0, count($statuses), '?')) . ')';
        $params   = array_merge($params, $statuses);
    }
    if ($from !== '' && ($ts = strtotime($from)) !== false) {
        $where[]  = 'o.created_at >= ?';
        $params[] = $ts;
    }
    if ($to !== '' && ($ts = strtotime($to . ' 23:59:59')) !== false) {
        $where[]  = 'o.created_at <= ?';
        $params[] = $ts;
    }

    $sql = 'FROM orders o JOIN users u ON u.id = o.user_id'
         . ($where ? ' WHERE ' . implode(' AND ', $where) : '');

    $st = $pdo->prepare('SELECT COUNT(*) ' . $sql);
    $st->execute($params);
    $total = (int) $st->fetchColumn();

    // Totals for the current filter, not just the page being shown.
    $st = $pdo->prepare(
        'SELECT
            COALESCE(SUM(CASE WHEN o.status = "paid" THEN o.price_cents ELSE 0 END), 0) AS paid_cents,
            COALESCE(SUM(CASE WHEN o.status = "paid" THEN o.credits ELSE 0 END), 0)     AS paid_credits,
            COALESCE(SUM(CASE WHEN o.status IN ("pending","accepted") THEN o.price_cents ELSE 0 END), 0) AS pending_cents,
            SUM(CASE WHEN o.status IN ("pending","accepted") THEN 1 ELSE 0 END) AS pending_n
         ' . $sql
    );
    $st->execute($params);
    $sum = $st->fetch();

    $pages  = max(1, (int) ceil($total / $perPage));
    $page   = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    // u.lang comes along for the ride: which language to write to somebody
    // in is the first thing you need before answering them, and looking it
    // up per order was a query per row.
    $st = $pdo->prepare('SELECT o.*, u.email, u.lang, u.nickname ' . $sql . ' ORDER BY o.id DESC LIMIT ? OFFSET ?');
    foreach ($params as $i => $v) {
        $st->bindValue($i + 1, $v);
    }
    $st->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
    $st->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
    $st->execute();
    $orders = $st->fetchAll();

    // Keeps the filter attached to paging links.
    $qs = static function (array $extra = []) use ($q, $status, $group, $from, $to, $page): string {
        $base = array_filter([
            'tab' => 'orders', 'group' => $group, 'q' => $q, 'status' => $status,
            'from' => $from, 'to' => $to, 'page' => $page,
        ], static fn($v) => $v !== '' && $v !== null);
        return '?' . http_build_query(array_merge($base, $extra));
    };
?>
  <div class="card">
    <h2>Objednávky</h2>

    <?php $counts = Orders::counts(); ?>
    <?php
      $orderGroupInfo = [
        'all'       => ['Vše',           'Všechny objednávky bez omezení stavu.', 'all'],
        'open'      => ['Otevřené',      'Nové objednávky čekající na zpracování.', 'open'],
        'accepted'  => ['Zpracovává se', 'Objednávky, které už byly převzaty ke zpracování.', 'accepted'],
        'paid'      => ['Zpracované',    'Dokončené a zaplacené objednávky.', 'paid'],
        'refund'    => ['Refundy',       'Objednávky čekající na vrácení nebo již refundované.', 'refund'],
        'cancelled' => ['Zrušené',       'Objednávky, které byly zrušeny.', 'cancelled'],
      ];
    ?>
    <nav class="order-groups" role="tablist" aria-label="Stav objednávek">
      <?php foreach ($orderGroupInfo as $key => [$label, $description, $icon]): ?>
        <a class="order-group order-group-card order-<?= e($icon) ?><?= $group === $key ? ' is-on' : '' ?>"
           href="?tab=orders&group=<?= e($key) ?>"
           role="tab" aria-selected="<?= $group === $key ? 'true' : 'false' ?>">
          <span class="order-group-icon" aria-hidden="true"></span>
          <span class="order-group-copy">
            <strong><?= e($label) ?></strong>
            <small><?= e($description) ?></small>
          </span>
          <span class="order-count"><?= (int) ($counts[$key] ?? 0) ?></span>
        </a>
      <?php endforeach; ?>
    </nav>

    <form method="get" class="order-filter">
      <input type="hidden" name="tab" value="orders">
      <input type="hidden" name="group" value="<?= e($group) ?>">
      <input type="search" name="q" value="<?= e($q) ?>"
             placeholder="E-mail nebo variabilní symbol" style="min-width:230px">
      <input type="date" name="from" value="<?= e($from) ?>" aria-label="Od" style="width:auto">
      <input type="date" name="to" value="<?= e($to) ?>" aria-label="Do" style="width:auto">
      <button class="btn" type="submit">Hledat</button>
      <?php if ($q !== '' || $status !== '' || $from !== '' || $to !== ''): ?>
        <a class="btn" href="?tab=orders">Zrušit filtr</a>
      <?php endif; ?>
    </form>

    <div class="order-summary">
      <span><strong><?= $total ?></strong> objednávek</span>
      <span>Zaplaceno: <strong><?= e(money((int) $sum['paid_cents'])) ?></strong>
        (<?= e(Cred::fmtCs((int) $sum['paid_credits'])) ?> kreditů)</span>
      <?php if ((int) $sum['pending_n'] > 0): ?>
        <span class="pending-note">Čeká na platbu: <strong><?= (int) $sum['pending_n'] ?></strong>
          za <?= e(money((int) $sum['pending_cents'])) ?></span>
      <?php endif; ?>
    </div>
    <?php if (!$orders): ?>
      <p class="hint">
        <?= ($q !== '' || $status !== '' || $from !== '' || $to !== '')
            ? 'Filtru neodpovídá žádná objednávka.'
            : 'Zatím žádné objednávky.' ?>
      </p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead><tr><th>Vytvořeno</th><th>Uživatel</th><th>Variabilní symbol</th><th class="num">Kredity</th><th class="num">Cena</th><th>Stav</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($orders as $o): ?>
            <tr>
              <td><?= e(when((int) $o['created_at'])) ?></td>
              <td><?= e($o['email']) ?></td>
              <td class="mono"><?= e($o['reference']) ?>
                <?php $vs = Payments::vs($o); ?>
                <?php if ($vs !== ''): ?><div class="hint">VS <?= e($vs) ?></div><?php endif; ?>
              </td>
              <td class="num">
                <?php if ((int) ($o['sub_days'] ?? 0) > 0): ?>
                  <?= e(Orders::durationLabel((int) $o['sub_days'], 'cs')) ?>
                  <?php if ((int) $o['credits'] > 0): ?><div class="hint">+ <?= e(Cred::fmtCs((int) $o['credits'])) ?> kr.</div><?php endif; ?>
                <?php else: ?>
                  <?= e(Cred::fmtCs((int) $o['credits'])) ?>
                <?php endif; ?>
              </td>
              <td class="num"><?= e(money((int) $o['price_cents'], $o['currency'] ?? null)) ?></td>
              <td>
                <span class="order-status status-<?= e((string) $o['status']) ?>"><?= e(order_status_cs((string) $o['status'])) ?></span>
                <?php $left = Orders::expiresIn($o); ?>
                <?php if ($left !== null): ?>
                  <div class="hint">zbývá <?= e(Quota::humanDuration($left)) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php $isFinished = !in_array($o['status'], [Orders::PENDING, Orders::ACCEPTED], true); ?>
                <button class="btn small primary" type="button"
                        data-edit-open="order-<?= (int) $o['id'] ?>"><?= $isFinished ? 'Detail' : 'Zpracovat' ?></button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- One dialog per order: everything that can be done with it, the
           customer it belongs to, and what has already happened. -->
      <?php foreach ($orders as $o): ?>
        <?php
          $oid   = (int) $o['id'];
          $ref   = (string) $o['reference'];
          $oCur  = $o['currency'] ?? null;
          $oDays = (int) ($o['sub_days'] ?? 0);
          $oLang = ($o['lang'] ?? '') === 'cs' ? 'cs' : 'en';
          $reward = $oDays > 0
              ? Orders::durationLabel($oDays, 'cs') . ' neomezeně'
                  . ((int) $o['credits'] > 0 ? ' + ' . Cred::fmtCs((int) $o['credits']) . ' kreditů' : '')
              : Cred::fmtCs((int) $o['credits']) . ' kreditů';
          $open  = in_array($o['status'], [Orders::PENDING, Orders::ACCEPTED], true);
        ?>
        <div class="modal-back edit-modal" id="order-<?= $oid ?>" hidden>
          <div class="modal modal-wide order-modal" role="dialog" aria-modal="true">
            <button type="button" class="modal-x" data-edit-close aria-label="Zavřít">&times;</button>
            <h3>Objednávka <?= e($ref) ?></h3>

            <table class="ud-kv">
              <tr><th>Zákazník</th><td>
                <a href="?tab=users&amp;q=<?= e(urlencode((string) $o['email'])) ?>"><?= e((string) $o['email']) ?></a>
                <?php if (trim((string) ($o['nickname'] ?? '')) !== ''): ?>
                  <span class="hint">(<?= e((string) $o['nickname']) ?>)</span>
                <?php endif; ?>
              </td></tr>
              <tr><th>Jazyk komunikace</th><td>
                <strong><?= $oLang === 'cs' ? 'Česky' : 'Anglicky' ?></strong>
                <span class="hint">e-maily z aplikace mu chodí v tomhle jazyce, odpovídej stejně</span>
              </td></tr>
              <tr><th>Stav</th><td>
                <span class="order-status status-<?= e((string) $o['status']) ?>"><?= e(order_status_cs((string) $o['status'])) ?></span>
                <?php $left = Orders::expiresIn($o); ?>
                <?php if ($left !== null): ?><span class="hint">zbývá <?= e(Quota::humanDuration($left)) ?></span><?php endif; ?>
              </td></tr>
              <tr><th>Obsah</th><td><?= e($reward) ?></td></tr>
              <tr><th>Částka</th><td><strong><?= e(money((int) $o['price_cents'], $oCur)) ?></strong></td></tr>
              <tr><th>Variabilní symbol</th><td class="mono"><?= e(Payments::vs($o)) ?></td></tr>
              <?php if ($oDays > 0 && (string) $o['status'] === Orders::PAID): ?>
                <tr><th>Aktivace</th><td>
                  <?= empty($o['activated_at'])
                      ? '<span class="tag warn">čeká na zákazníka</span>'
                      : e(when((int) $o['activated_at'])) ?>
                </td></tr>
              <?php endif; ?>
            </table>

            <?php if ($open): ?>
              <form method="post" data-busy="Zpracovávám objednávku…">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="order_status">
                <input type="hidden" name="id" value="<?= $oid ?>">

                <label for="note-<?= $oid ?>" style="margin-top:16px">Poznámka / důvod</label>
                <select class="reply-picker" data-reply-for="note-<?= $oid ?>">
                  <option value="">Vlastní text…</option>
                  <?php foreach (ORDER_REPLIES as $rep): ?>
                    <option value="<?= e($rep) ?>"><?= e($rep) ?></option>
                  <?php endforeach; ?>
                </select>
                <input id="note-<?= $oid ?>" name="admin_note" type="text"
                       value="<?= e((string) $o['admin_note']) ?>"
                       placeholder="Napiš vlastní, nebo vyber nahoře">
                <div class="hint">
                  Uloží se k objednávce a zákazník ji uvidí u zrušené objednávky
                  ve svém účtu. Odešle se s tou akcí, kterou stiskneš.
                </div>

                <div class="credit-dialog-actions">
                  <button class="btn primary" type="submit" name="status" value="paid"
                          data-confirm="Označit objednávku <?= e($ref) ?> jako zaplacenou? Připíše se <?= e($reward) ?> a zákazníkovi odejde e-mail."
                          data-confirm-ok="Označit jako zaplacenou">Označit jako zaplacenou</button>
                  <?php if ($o['status'] === Orders::PENDING): ?>
                    <?php $days = Settings::int('accept_expiry_days'); ?>
                    <button class="btn" type="submit" name="status" value="accepted"
                            data-confirm="Přijmout objednávku <?= e($ref) ?> ke zpracování? Zákazník ji pak už nemůže zrušit<?= $days > 0 ? ' a bez zaplacení se sama zruší za ' . $days . ' dní' : '' ?>."
                            data-confirm-ok="Přijmout">Přijmout ke zpracování</button>
                  <?php endif; ?>
                  <button class="btn danger" type="submit" name="status" value="cancelled"
                          data-confirm="Zrušit objednávku <?= e($ref) ?>? Záznam zůstane, kredity se nepřipíší."
                          data-confirm-ok="Zrušit objednávku">Zrušit</button>
                </div>
              </form>

              <?php if ($o['status'] === Orders::PENDING): ?>
                <form method="post" style="margin-top:12px" data-busy="Odesílám e-mail…">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="action" value="resend_order_mail">
                  <input type="hidden" name="id" value="<?= $oid ?>">
                  <button class="btn small" type="submit"
                          data-confirm="Poslat zákazníkovi znovu e-mail k objednávce <?= e($ref) ?>?"
                          data-confirm-ok="Poslat znovu">Poslat e-mail znovu</button>
                </form>
              <?php endif; ?>

            <?php elseif ($o['status'] === Orders::PAID): ?>
              <div class="row" style="gap:8px;margin-top:16px;flex-wrap:wrap">
                <a class="btn small" target="_blank" rel="noopener"
                   href="../invoice.php?ref=<?= e(urlencode($ref)) ?>&amp;t=<?= e(urlencode((string) ($o['access_token'] ?? ''))) ?>">Faktura</a>
                <form method="post" data-busy="Zpracovávám refund…">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="action" value="refund_order">
                  <input type="hidden" name="order_id" value="<?= $oid ?>">
                  <button class="btn small danger" type="submit"
                          data-confirm="Refundovat objednávku <?= e($ref) ?> (<?= e(money((int) $o['price_cents'], $oCur)) ?>)? Připsané kredity i čas se odeberou hned a objednávka půjde do fronty refundů - peníze pak vrať ručně."
                          data-confirm-ok="Refundovat">Refundovat</button>
                </form>
              </div>
              <?php $why = Orders::refundBlocker($o, Auth::byId((int) $o['user_id'])); ?>
              <?php if ($why !== ''): ?>
                <div class="msg warn" style="margin-top:12px">Refund by neprošel: <?= e($why) ?></div>
              <?php endif; ?>

            <?php elseif ($o['status'] === Orders::REFUND): ?>
              <form method="post" style="margin-top:16px" data-busy="Uzavírám refund…">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="refund_done">
                <input type="hidden" name="order_id" value="<?= $oid ?>">
                <label for="dest-<?= $oid ?>">Kam byly peníze vráceny</label>
                <input id="dest-<?= $oid ?>" name="refund_dest" type="text" required
                       placeholder="číslo účtu, PayPal…">
                <div class="hint">Povinné - vytiskne se to na doklad o refundaci.</div>
                <div class="credit-dialog-actions" style="margin-top:14px">
                  <button class="btn primary" type="submit"
                          data-confirm="Potvrdit, že peníze (<?= e(money((int) ($o['refund_cents'] ?? $o['price_cents']), $oCur)) ?>) za <?= e($ref) ?> byly vráceny?"
                          data-confirm-ok="Peníze vráceny">Peníze vráceny</button>
                </div>
              </form>
              <form method="post" style="margin-top:12px" data-busy="Obnovuji objednávku…">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="refund_undo">
                <input type="hidden" name="order_id" value="<?= $oid ?>">
                <button class="btn small" type="submit"
                        data-confirm="Zrušit refund a obnovit objednávku <?= e($ref) ?>? Odebrané kredity i dny se zákazníkovi vrátí a objednávka bude zase zaplacená."
                        data-confirm-ok="Obnovit objednávku">Zamítnout refund a obnovit</button>
              </form>

            <?php elseif ($o['status'] === Orders::REFUNDED): ?>
              <div class="row" style="gap:8px;margin-top:16px;flex-wrap:wrap">
                <a class="btn small" target="_blank" rel="noopener"
                   href="../invoice.php?ref=<?= e(urlencode($ref)) ?>&amp;t=<?= e(urlencode((string) ($o['access_token'] ?? ''))) ?>">Faktura</a>
                <a class="btn small" target="_blank" rel="noopener"
                   href="../invoice.php?ref=<?= e(urlencode($ref . '_refund')) ?>&amp;t=<?= e(urlencode((string) ($o['access_token'] ?? ''))) ?>">Doklad o refundaci</a>
              </div>
            <?php endif; ?>

            <?php $hist = order_history($o); ?>
            <div class="order-hist">
              <div class="order-hist-head">Historie objednávky</div>
              <?php if (!$hist): ?>
                <p class="hint" style="margin:0">Zatím jen vytvoření.</p>
              <?php else: ?>
                <ul class="audit">
                  <?php foreach ($hist as $h): ?>
                    <li>
                      <span class="audit-when"><?= e(when($h['at'])) ?></span>
                      <span class="audit-what">
                        <?= e($h['what']) ?>
                        <?php if ($h['who'] !== '' || $h['detail'] !== ''): ?>
                          <span class="hint">
                            <?= e(trim($h['who'] . ($h['who'] !== '' && $h['detail'] !== '' ? ' · ' : '') . $h['detail'])) ?>
                          </span>
                        <?php endif; ?>
                      </span>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <?php if ($pages > 1): ?>
        <div class="pager">
          <?php if ($page > 1): ?>
            <a class="btn small" href="<?= e($qs(['page' => $page - 1])) ?>">← Novější</a>
          <?php endif; ?>
          <span class="hint">Strana <?= $page ?> z <?= $pages ?></span>
          <?php if ($page < $pages): ?>
            <a class="btn small" href="<?= e($qs(['page' => $page + 1])) ?>">Starší →</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
