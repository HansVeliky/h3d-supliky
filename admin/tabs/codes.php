<?php
/**
 * Administration tab: codes
 *
 * Lifted out of admin/index.php, which had all twenty of these in one
 * 6000-line file. Included from there with the surrounding scope intact,
 * so $tab, $ok, $error, the helpers and everything else the page sets up
 * are available exactly as before.
 */
if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }
?>
  <div class="card">
    <h2>Nový kód</h2>
    <form method="post">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="create_code">
      <div class="grid3">
        <div>
          <label for="credits">Kredity</label>
          <input id="credits" name="credits" type="number" step="0.1" value="10">
          <div class="hint">Kolik kreditů kód připíše. Záporné = opravy. U časového kódu dej 0.</div>
        </div>
        <div>
          <label for="code_sub_days">Časový balíček (dny)</label>
          <input id="code_sub_days" name="sub_days" type="number" min="0" value="0">
          <div class="hint">Neomezené exporty na tolik dní (7 = týden). 0 = jen kredity.</div>
        </div>
        <div>
          <label for="max_uses">Max. použití celkem</label>
          <input id="max_uses" name="max_uses" type="number" min="0" value="1">
          <div class="hint">0 = neomezeně.</div>
        </div>
        <div>
          <label for="uses_per_account">Použití na jeden účet</label>
          <input id="uses_per_account" name="uses_per_account" type="number" min="0" value="1">
          <div class="hint">0 = bez omezení na účet.</div>
        </div>
        <div>
          <label for="expires_days">Platnost (dny)</label>
          <input id="expires_days" name="expires_days" type="number" min="0" value="0">
          <div class="hint">0 = bez expirace.</div>
        </div>
      </div>
      <div class="grid2">
        <div>
          <label for="code">Vlastní kód</label>
          <input id="code" name="code" type="text" class="mono" placeholder="nech prázdné pro vygenerování">
        </div>
        <div>
          <label for="note">Poznámka</label>
          <input id="note" name="note" type="text" placeholder="e.g. Facebook giveaway">
        </div>
      </div>
      <button class="btn primary" type="submit" style="margin-top:14px">Vytvořit kód</button>
    </form>
  </div>

  <div class="card">
    <h2>Kódy</h2>
    <?php
      $codes = Codes::all();
      $usage = Codes::usageMap();
    ?>
    <?php if (!$codes): ?>
      <p class="hint">Zatím žádné kódy.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead><tr>
            <th>Kód</th><th class="num">Kredity / čas</th><th class="num">Využito</th>
            <th>Platnost do</th><th>Poznámka</th><th>Stav</th><th></th>
          </tr></thead>
          <tbody>
          <?php foreach ($codes as $c): ?>
            <tr>
              <td class="mono"><?= e(Codes::label($c)) ?></td>
              <td class="num">
                <?php $cdays = (int) ($c['sub_days'] ?? 0); ?>
                <?php if ((int) $c['credits'] !== 0): ?><?= e(Cred::fmtCs((int) $c['credits'])) ?> kr.<?php endif; ?>
                <?php if ($cdays > 0): ?>
                  <?php if ((int) $c['credits'] !== 0): ?><br><?php endif; ?>
                  <span class="tag on"><?= e(Orders::durationLabel($cdays, 'cs')) ?></span>
                <?php endif; ?>
              </td>
              <td class="num">
                <?= (int) $c['uses'] ?> / <?= (int) $c['max_uses'] === 0 ? '∞' : (int) $c['max_uses'] ?>
                <div class="hint"><?= (int) ($c['uses_per_account'] ?? 1) === 0 ? '∞' : (int) $c['uses_per_account'] ?>× na účet</div>
              </td>
              <td><?= $c['expires_at'] ? e(when((int) $c['expires_at'])) : 'nikdy' ?></td>
              <td><?= e((string) $c['note']) ?></td>
              <td>
                <?php
                $expired = $c['expires_at'] !== null && (int) $c['expires_at'] < time();
                $spent = (int) $c['max_uses'] > 0 && (int) $c['uses'] >= (int) $c['max_uses'];
                if ((int) $c['active'] !== 1) { echo '<span class="tag off">vypnutý</span>'; }
                elseif ($expired) { echo '<span class="tag bad">expirovaný</span>'; }
                elseif ($spent) { echo '<span class="tag warn">vyčerpaný</span>'; }
                else { echo '<span class="tag on">aktivní</span>'; }
                ?>
              </td>
              <td class="num">
                <div class="row" style="gap:6px;justify-content:flex-end;flex-wrap:nowrap">
                  <?php $usedCount = count($usage[(int) $c['id']] ?? []); ?>
                  <button class="btn small" type="button" data-open-modal="code-info-<?= (int) $c['id'] ?>"
                          title="Kdo kód uplatnil">Info<?php if ($usedCount): ?> (<?= $usedCount ?>)<?php endif; ?></button>
                  <button class="btn small" type="button" data-open-modal="code-edit-<?= (int) $c['id'] ?>">Upravit</button>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Per-code dialogs: who used it, and editing its parameters. -->
      <?php foreach ($codes as $c): ?>
        <?php
          $used  = $usage[(int) $c['id']] ?? [];
          $cid   = (int) $c['id'];
          $cd    = (int) ($c['sub_days'] ?? 0);
          $cexp  = $c['expires_at'] !== null ? date('Y-m-d', (int) $c['expires_at']) : '';
        ?>
        <div class="modal-back js-modal" id="code-info-<?= $cid ?>" hidden>
          <div class="modal modal-wide" role="dialog" aria-modal="true">
            <button type="button" class="modal-x" data-close-modal aria-label="Zavřít">&times;</button>
            <h3>Kdo uplatnil <?= e(Codes::label($c)) ?></h3>
            <?php if (!$used): ?>
              <p class="hint">Zatím nikdo.</p>
            <?php else: ?>
              <ul class="used-by" style="font-size:13.5px">
                <?php foreach ($used as $x): ?>
                  <li>
                    <?php if ($x['email'] !== null): ?>
                      <a href="?tab=users&amp;q=<?= e(urlencode((string) $x['email'])) ?>"><?= e($x['email']) ?></a>
                    <?php else: ?>
                      <span class="hint">smazaný účet</span>
                    <?php endif; ?>
                    <span class="hint"><?= e(when((int) $x['created_at'])) ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>

        <div class="modal-back js-modal" id="code-edit-<?= $cid ?>" hidden>
          <div class="modal modal-wide" role="dialog" aria-modal="true">
            <button type="button" class="modal-x" data-close-modal aria-label="Zavřít">&times;</button>
            <h3>Upravit kód <?= e(Codes::label($c)) ?></h3>
            <form method="post">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="action" value="edit_code">
              <input type="hidden" name="id" value="<?= $cid ?>">
              <div class="grid2">
                <div>
                  <label>Kredity</label>
                  <input name="credits" type="number" step="0.1" value="<?= e(Cred::input((int) $c['credits'])) ?>">
                  <div class="hint">U časového kódu můžeš dát 0.</div>
                </div>
                <div>
                  <label>Časový balíček (dny)</label>
                  <input name="sub_days" type="number" min="0" value="<?= $cd ?>">
                  <div class="hint">Neomezené exporty na tolik dní. 0 = jen kredity.</div>
                </div>
              </div>
              <div class="grid2">
                <div><label>Max. použití celkem</label><input name="max_uses" type="number" min="0" value="<?= (int) $c['max_uses'] ?>"><div class="hint">0 = neomezeně.</div></div>
                <div><label>Použití na účet</label><input name="uses_per_account" type="number" min="0" value="<?= (int) ($c['uses_per_account'] ?? 1) ?>"><div class="hint">0 = bez omezení.</div></div>
              </div>
              <div class="grid2">
                <div><label>Platnost do</label><input name="expires" type="date" value="<?= e($cexp) ?>"><div class="hint">Prázdné = bez expirace.</div></div>
                <div><label>Poznámka</label><input name="note" type="text" value="<?= e((string) $c['note']) ?>"></div>
              </div>
              <label class="check"><input type="checkbox" name="active" value="1" <?= (int) $c['active'] === 1 ? 'checked' : '' ?>> Aktivní</label>
              <div class="credit-dialog-actions">
                <button class="btn primary" type="submit">Uložit změny</button>
                <button class="btn" type="button" data-close-modal>Zrušit</button>
              </div>
            </form>
            <form method="post" style="margin-top:14px;border-top:1px solid var(--line);padding-top:12px">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="action" value="delete_code">
              <input type="hidden" name="id" value="<?= $cid ?>">
              <button class="btn small danger" type="submit"
                      data-confirm="Smazat kód <?= e(Codes::label($c)) ?>? Už připsané kredity zůstávají, ale zmizí záznam o tom, kdo ho uplatnil."
                      data-confirm-ok="Smazat kód">Smazat kód</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
