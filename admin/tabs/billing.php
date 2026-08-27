<?php
/**
 * Administration tab: billing
 *
 * Lifted out of admin/index.php, which had all twenty of these in one
 * 6000-line file. Included from there with the surrounding scope intact,
 * so $tab, $ok, $error, the helpers and everything else the page sets up
 * are available exactly as before.
 */
if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }

    $packages = $pdo->query('SELECT * FROM packages ORDER BY sort, credits')->fetchAll();

    // Two shelves under one tab: plain credit packs and time passes. The
    // switch is a link so it survives a reload after saving a package.
    $kindRaw = (string) ($_GET['kind'] ?? 'credit');
    $kind = in_array($kindRaw, ['credit', 'time', 'free'], true) ? $kindRaw : 'credit';

    $shown = array_values(array_filter($packages, static function ($p) use ($kind): bool {
        $free = (int) ($p['price_cents'] ?? 0) === 0;
        $time = (int) ($p['sub_days'] ?? 0) > 0;
        if ($kind === 'free') return $free;
        if ($kind === 'time') return !$free && $time;
        return !$free && !$time;
    }));

    $creditCount = count(array_filter($packages, static fn($p) =>
        (int) ($p['price_cents'] ?? 0) > 0 && (int) ($p['sub_days'] ?? 0) === 0));
    $timeCount = count(array_filter($packages, static fn($p) =>
        (int) ($p['price_cents'] ?? 0) > 0 && (int) ($p['sub_days'] ?? 0) > 0));
    $freeCount = count(array_filter($packages, static fn($p) =>
        (int) ($p['price_cents'] ?? 0) === 0));
?>
  <div class="subtabs">
    <a class="subtab <?= $kind === 'credit' ? 'is-on' : '' ?>" href="?tab=billing&amp;kind=credit">Kreditové balíčky (<?= $creditCount ?>)</a>
    <a class="subtab <?= $kind === 'time' ? 'is-on' : '' ?>" href="?tab=billing&amp;kind=time">Časové balíčky (<?= $timeCount ?>)</a>
    <a class="subtab <?= $kind === 'free' ? 'is-on' : '' ?>" href="?tab=billing&amp;kind=free">Zdarma (<?= $freeCount ?>)</a>
  </div>

  <?php if ($kind === 'free'): ?>
    <div class="msg info">
      Aktivní balíček s cenou <strong>0</strong> se uživateli zobrazí jako
      <strong>PROMO AKCE</strong>. Lze ho aktivovat i tehdy, když je prodej
      placených balíčků vypnutý. Po aktivaci se objednávka automaticky označí
      jako <strong>zpracovaná</strong>; u časového promo balíčku se čas začne
      počítat okamžitě. Pro nabídky zdarma doporučujeme
      <strong>Na jeden účet = 1</strong>.
    </div>
  <?php else: ?>
  <div class="msg warn">
    Platební brána tu není. Objednávka je jen záznam o úmyslu: zákazník dostane
    variabilní symbol a kredity se připíšou, až objednávku označíš jako
    zaplacenou.
  </div>
  <?php endif; ?>

  <div class="card">
    <div class="card-head">
      <h2><?= $kind === 'free' ? 'Balíčky zdarma · PROMO AKCE' : ($kind === 'time' ? 'Časové balíčky' : 'Kreditové balíčky') ?></h2>
      <button class="btn primary small" type="button" data-edit-open="pkg-new">Nový balíček</button>
    </div>
    <?php if (!$shown): ?>
      <p class="hint" style="margin:0">
        Zatím žádné <?= $kind === 'free' ? 'balíčky zdarma' : ($kind === 'time' ? 'časové balíčky' : 'kreditové balíčky') ?> -
        přidej je tlačítkem nahoře.
      </p>
    <?php else: ?>
      <p class="hint" style="margin-top:0">
        Táhni kartu za úchyt (⠿) pro pořadí - v tom se ukáže zákazníkovi.
        Uprav kliknutím na Upravit.
      </p>
      <div class="adm-cards" data-sortable data-sort-action="reorder_packages">
        <?php foreach ($shown as $p): ?>
          <?php
            $pr    = Pricing::of($p);
            $pon   = (int) $p['active'] === 1;
            $pfeat = (int) ($p['featured'] ?? 0) === 1;
            $pdays = (int) ($p['sub_days'] ?? 0);
          ?>
          <div class="adm-card<?= $pon ? '' : ' is-off' ?>" data-sort-id="<?= (int) $p['id'] ?>">
            <span class="drag-handle" draggable="true" title="Táhni pro pořadí" aria-label="Přesunout">⠿</span>
            <div class="adm-card-main">
              <div class="adm-card-title">
                <?= e($p['name']) ?>
                <?php if ($pfeat): ?><span class="tag on">★ oblíbený</span><?php endif; ?>
                <?php if (!$pon): ?><span class="tag bad">neaktivní</span><?php endif; ?>
              </div>
              <div class="adm-card-sub hint">
                <?php if ($pdays > 0): ?>
                  <?= e(Orders::durationLabel($pdays, 'cs')) ?> neomezeně<?php if ((int) $p['credits'] > 0): ?> + <?= e(Cred::fmtCs((int) $p['credits'])) ?> kr.<?php endif; ?>
                <?php else: ?>
                  <?= e(Cred::fmtCs((int) $p['credits'])) ?> kreditů
                <?php endif; ?>
              </div>
            </div>
            <div class="adm-card-side">
              <?php if ($pr['on']): ?><s><?= e(money($pr['base'])) ?></s> <?php endif; ?>
              <strong><?= (int) $p['price_cents'] === 0 ? 'zdarma' : e(money($pr['final'])) ?></strong>
            </div>
            <button class="btn small" type="button" data-edit-open="edit-<?= (int) $p['id'] ?>">Upravit</button>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- One edit dialog per package, opened from its card. -->
  <?php foreach ($shown as $p): ?>
    <?php $pon = (int) $p['active'] === 1; $pfeat = (int) ($p['featured'] ?? 0) === 1; ?>
    <div class="modal-back edit-modal" id="edit-<?= (int) $p['id'] ?>" hidden>
      <div class="modal modal-wide" role="dialog" aria-modal="true">
        <button type="button" class="modal-x" data-edit-close aria-label="Zavřít">&times;</button>
        <h3><?= e($p['name']) ?></h3>
        <form method="post" id="pkg-<?= (int) $p['id'] ?>-form">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="action" value="save_package">
          <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <div class="grid2">
            <div><label>Název</label><input name="name" type="text" value="<?= e($p['name']) ?>" required></div>
            <div><label>Cena (<?= e(Settings::get('currency')) ?>)</label><input name="price" type="text" value="<?= number_format($p['price_cents'] / 100, 2, '.', '') ?>"></div>
          </div>
          <div class="grid2">
            <div>
              <label>Kredity</label>
              <input name="credits" type="number" min="0" step="0.1" value="<?= e(Cred::input((int) $p['credits'])) ?>">
              <div class="hint">U časového balíčku můžeš dát 0.</div>
            </div>
            <div>
              <label>Časový balíček (dny)</label>
              <input name="sub_days" type="number" min="0" value="<?= (int) ($p['sub_days'] ?? 0) ?>">
              <div class="hint">0 = kreditový. 7 = týden, 30 = měsíc, 365 = rok.</div>
            </div>
          </div>
          <div class="grid2">
            <div><label>Max. objednávek</label><input name="max_uses" type="number" min="0" value="<?= (int) $p['max_uses'] ?>"><div class="hint">0 = neomezeně.</div></div>
            <div><label>Na jeden účet</label><input name="uses_per_account" type="number" min="0" value="<?= (int) $p['uses_per_account'] ?>"><div class="hint">0 = neomezeně.</div></div>
          </div>
          <label class="check"><input type="checkbox" name="active" value="1" <?= $pon ? 'checked' : '' ?>> Aktivní</label>
          <label class="check"><input type="checkbox" name="featured" value="1" <?= $pfeat ? 'checked' : '' ?>> Oblíbený (zvýrazní se v nabídce)</label>
          <div class="credit-dialog-actions">
            <button class="btn primary" type="submit">Uložit</button>
            <button class="btn" type="button" data-edit-close>Zrušit</button>
          </div>
        </form>
        <form method="post" id="pkg-<?= (int) $p['id'] ?>-del" style="margin-top:14px;border-top:1px solid var(--line);padding-top:12px">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="action" value="delete_package">
          <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
          <button class="btn small danger" type="submit"
                  data-confirm="Smazat balíček <?= e($p['name']) ?>? Dřívější objednávky zůstanou."
                  data-confirm-ok="Smazat balíček">Smazat balíček</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>

  <!-- New package: the same dialog as editing one, opened from the heading. -->
  <div class="modal-back edit-modal" id="pkg-new" hidden>
    <div class="modal modal-wide" role="dialog" aria-modal="true">
      <button type="button" class="modal-x" data-edit-close aria-label="Zavřít">&times;</button>
      <h3>Nový balíček</h3>
      <form method="post">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="save_package">
        <div class="grid2">
          <div><label for="pname">Název</label><input id="pname" name="name" type="text" placeholder="Starter" required></div>
          <div><label for="pprice">Cena (<?= e(Settings::get('currency')) ?>)</label><input id="pprice" name="price" type="text" value="<?= $kind === 'free' ? '0' : '99' ?>"></div>
        </div>
        <div class="grid2">
          <div>
            <label for="pcred">Kredity</label>
            <input id="pcred" name="credits" type="number" min="0" step="0.1"
                   value="<?= $kind === 'time' ? '0' : '10' ?>">
            <div class="hint">U časového balíčku můžeš dát 0.</div>
          </div>
          <div>
            <label for="psub">Časový balíček (dny)</label>
            <input id="psub" name="sub_days" type="number" min="0" value="<?= $kind === 'time' ? '30' : '0' ?>">
            <div class="hint">0 = kreditový. 7 = týden, 30 = měsíc, 365 = rok.</div>
          </div>
        </div>
        <div class="grid2">
          <div>
            <label for="pmax">Max. objednávek celkem</label>
            <input id="pmax" name="max_uses" type="number" min="0" value="0">
            <div class="hint">0 = neomezeně.</div>
          </div>
          <div>
            <label for="pacc">Na jeden účet</label>
            <input id="pacc" name="uses_per_account" type="number" min="0" value="<?= $kind === 'free' ? '1' : '0' ?>">
            <div class="hint">0 = neomezeně. U balíčku zdarma dej 1.</div>
          </div>
        </div>
        <label class="check"><input type="checkbox" name="active" value="1" checked> Aktivní</label>
        <label class="check"><input type="checkbox" name="featured" value="1"> Oblíbený (zvýrazní se v nabídce)</label>
        <div class="hint" style="margin-top:10px">
          Balíček se zařadí na konec, pořadí pak přeskládáš tažením karet.
        </div>
        <div class="credit-dialog-actions">
          <button class="btn primary" type="submit">Přidat balíček</button>
          <button class="btn" type="button" data-edit-close>Zrušit</button>
        </div>
      </form>
    </div>
  </div>
