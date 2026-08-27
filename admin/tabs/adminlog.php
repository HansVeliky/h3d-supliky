<?php
/**
 * Administration tab: adminlog
 *
 * Lifted out of admin/index.php, which had all twenty of these in one
 * 6000-line file. Included from there with the surrounding scope intact,
 * so $tab, $ok, $error, the helpers and everything else the page sets up
 * are available exactly as before.
 */
if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }

    // Who did what in the panel. Paged, because this table only grows.
    $lgQ    = trim((string) ($_GET['q'] ?? ''));
    $lgPage = max(1, (int) ($_GET['page'] ?? 1));
    $lgPer  = 50;

    $where = '';
    $args  = [];
    if ($lgQ !== '') {
        $where = ' WHERE admin_name LIKE ? OR action LIKE ? OR detail LIKE ? OR target LIKE ?';
        $args  = array_fill(0, 4, '%' . $lgQ . '%');
    }

    $st = $pdo->prepare('SELECT COUNT(*) FROM admin_log' . $where);
    $st->execute($args);
    $lgTotal = (int) $st->fetchColumn();
    $lgPages = max(1, (int) ceil($lgTotal / $lgPer));
    $lgPage  = min($lgPage, $lgPages);

    $st = $pdo->prepare(
        'SELECT * FROM admin_log' . $where . ' ORDER BY id DESC LIMIT ' . $lgPer
        . ' OFFSET ' . (($lgPage - 1) * $lgPer)
    );
    $st->execute($args);
    $lgRows = $st->fetchAll();
?>
  <div class="card">
    <h2>Protokol zásahů</h2>
    <p class="hint" style="margin-top:0">
      Každá akce provedená v administraci, i ta odmítnutá. Historie
      u uživatele říká, co se stalo; tohle říká, kdo to udělal.
    </p>

    <form method="get" class="row" style="margin-bottom:14px">
      <input type="hidden" name="tab" value="adminlog">
      <input type="search" name="q" value="<?= e($lgQ) ?>" placeholder="Správce, akce nebo text" style="max-width:280px">
      <button class="btn" type="submit">Hledat</button>
      <?php if ($lgQ !== ''): ?>
        <a class="btn" href="?tab=adminlog">Zrušit filtr</a>
      <?php endif; ?>
      <span class="hint" style="margin-left:auto"><?= $lgTotal ?> záznamů</span>
    </form>

    <?php if (!$lgRows): ?>
      <p class="hint"><?= $lgQ !== '' ? 'Filtru neodpovídá žádný záznam.' : 'Zatím tu nic není.' ?></p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead><tr><th>Kdy</th><th>Správce</th><th>Akce</th><th>Čeho se týkalo</th><th>Výsledek</th></tr></thead>
          <tbody>
          <?php foreach ($lgRows as $r): ?>
            <tr<?= (int) $r['ok'] === 0 ? ' class="is-revoked"' : '' ?>>
              <td><?= e(when((int) $r['created_at'])) ?></td>
              <td><?= e((string) $r['admin_name']) ?></td>
              <td class="mono"><?= e((string) $r['action']) ?></td>
              <td class="mono"><?= e((string) $r['target']) ?></td>
              <td>
                <?php if ((int) $r['ok'] === 0): ?><span class="tag bad">odmítnuto</span> <?php endif; ?>
                <span class="hint"><?= e((string) $r['detail']) ?></span>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php if ($lgPages > 1): ?>
        <div class="pager">
          <?php if ($lgPage > 1): ?>
            <a class="btn small" href="?tab=adminlog&amp;page=<?= $lgPage - 1 ?><?= $lgQ !== '' ? '&amp;q=' . urlencode($lgQ) : '' ?>">Předchozí</a>
          <?php endif; ?>
          <span class="hint">Strana <?= $lgPage ?> z <?= $lgPages ?></span>
          <?php if ($lgPage < $lgPages): ?>
            <a class="btn small" href="?tab=adminlog&amp;page=<?= $lgPage + 1 ?><?= $lgQ !== '' ? '&amp;q=' . urlencode($lgQ) : '' ?>">Další</a>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
