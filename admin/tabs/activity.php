<?php
/**
 * Administration tab: activity
 *
 * Lifted out of admin/index.php, which had all twenty of these in one
 * 6000-line file. Included from there with the surrounding scope intact,
 * so $tab, $ok, $error, the helpers and everything else the page sets up
 * are available exactly as before.
 */
if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }

    $exports = $pdo->query(
        'SELECT e.*, u.email FROM exports e LEFT JOIN users u ON u.id = e.user_id
         ORDER BY e.id DESC LIMIT 100'
    )->fetchAll();
?>
  <div class="card">
    <h2>Poslední exporty</h2>
    <?php if (!$exports): ?>
      <p class="hint">Zatím nic.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead><tr><th>Kdy</th><th>Who</th><th>Formát</th><th class="num">Boxů</th><th class="num">Kredity</th></tr></thead>
          <tbody>
          <?php foreach ($exports as $x): ?>
            <tr>
              <td><?= e(when((int) $x['created_at'])) ?></td>
              <td><?= $x['email'] ? e($x['email']) : '<span class="hint">host ' . e(substr($x['ip_hash'], 0, 8)) . '</span>' ?></td>
              <td><?= e(strtoupper($x['format'])) ?></td>
              <td class="num"><?= (int) $x['boxes'] ?></td>
              <td class="num"><?= (int) $x['credits_spent'] ? e(Cred::fmtCs((int) $x['credits_spent'])) : '-' ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="hint">Adresy hostů se ukládají jen jako otisk, nikdy čitelně.</p>
    <?php endif; ?>
  </div>
