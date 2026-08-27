<?php
/**
 * Administration tab: promotions
 *
 * Lifted out of admin/index.php, which had all twenty of these in one
 * 6000-line file. Included from there with the surrounding scope intact,
 * so $tab, $ok, $error, the helpers and everything else the page sets up
 * are available exactly as before.
 */
if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }

    // One-time migration: fold a leftover single discount into the manager,
    // so opening this screen never loses a campaign somebody set the old way.
    if (!Promotions::configured() && Settings::int('discount_percent') > 0) {
        Promotions::saveAll([[
            'label'   => Settings::get('discount_label'),
            'percent' => Settings::int('discount_percent'),
            'from'    => Settings::get('discount_from'),
            'until'   => Settings::get('discount_until'),
        ]]);
        Settings::set(['discount_percent' => '0', 'discount_label' => '',
                       'discount_from' => '', 'discount_until' => '']);
    }
    $promos      = Promotions::all();
    $activePromo = Promotions::active();
?>
  <div class="card">
    <h2>Akce a slevy</h2>
    <p class="hint" style="margin-top:0">
      Naplánuj slevu na určité období dopředu. Akce se nesmí časově překrývat,
      takže vždycky běží nanejvýš jedna a platí na všechny balíčky.
      Prázdné „Od" = hned, prázdné „Do" = bez konce; poslední den se počítá celý.
    </p>

    <?php if ($activePromo): ?>
      <div class="msg ok">
        Právě běží <strong><?= e($activePromo['label'] !== '' ? $activePromo['label'] : 'akce') ?></strong>
        - všechny balíčky mají -<?= e(Cred::fmtPercent($activePromo['percent'])) ?>&nbsp;%.
      </div>
    <?php else: ?>
      <div class="msg info">Teď neběží žádná akce, balíčky jsou za plnou cenu.</div>
    <?php endif; ?>

    <?php if ($promos): ?>
      <div class="table-scroll">
        <table class="promo-table">
          <thead><tr><th>Název</th><th class="num">Sleva %</th><th>Od</th><th>Do</th><th>Stav</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($promos as $i => $pr): ?>
            <?php
              $state      = Promotions::state($pr);
              $stateLabel = ['running' => 'běží', 'planned' => 'naplánováno', 'ended' => 'skončilo'][$state];
              $pfid       = 'promo-' . (int) $i;
            ?>
            <tr class="promo-row is-<?= $state ?>">
              <td><input form="<?= $pfid ?>" name="label" type="text" value="<?= e($pr['label']) ?>" placeholder="Bez názvu"></td>
              <td class="num"><input form="<?= $pfid ?>" name="percent" type="number" min="0.1" max="95" step="0.1" style="width:80px" value="<?= e(Cred::fmtPercent($pr['percent'])) ?>"></td>
              <td><input form="<?= $pfid ?>" name="from" type="date" value="<?= e($pr['from']) ?>"></td>
              <td><input form="<?= $pfid ?>" name="until" type="date" value="<?= e($pr['until']) ?>"></td>
              <td><span class="promo-state is-<?= $state ?>"><?= e($stateLabel) ?></span></td>
              <td class="row" style="gap:6px;justify-content:flex-end">
                <button class="btn small" form="<?= $pfid ?>" type="submit">Uložit</button>
                <button class="btn small danger" form="<?= $pfid ?>-del" type="submit"
                        data-confirm="Smazat akci <?= e($pr['label'] !== '' ? $pr['label'] : 'bez názvu') ?>?"
                        data-confirm-ok="Smazat akci">Smazat</button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php foreach ($promos as $i => $pr): ?>
        <?php $pfid = 'promo-' . (int) $i; ?>
        <form method="post" id="<?= $pfid ?>" hidden>
          <?= Auth::csrfField() ?>
          <input type="hidden" name="action" value="save_promotion">
          <input type="hidden" name="index" value="<?= (int) $i ?>">
        </form>
        <form method="post" id="<?= $pfid ?>-del" hidden>
          <?= Auth::csrfField() ?>
          <input type="hidden" name="action" value="delete_promotion">
          <input type="hidden" name="index" value="<?= (int) $i ?>">
        </form>
      <?php endforeach; ?>
    <?php endif; ?>

    <form method="post" class="promo-add">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="save_promotion">
      <input type="hidden" name="index" value="-1">
      <div class="grid4">
        <div><label for="pmlabel">Název akce</label><input id="pmlabel" name="label" type="text" placeholder="Vánoce"></div>
        <div><label for="pmpct">Sleva (%)</label><input id="pmpct" name="percent" type="number" min="0.1" max="95" step="0.1" placeholder="15"></div>
        <div><label for="pmfrom">Od</label><input id="pmfrom" name="from" type="date"></div>
        <div><label for="pmuntil">Do</label><input id="pmuntil" name="until" type="date"></div>
      </div>
      <button class="btn primary" type="submit">Naplánovat akci</button>
    </form>

    <div class="hint" style="margin-top:12px">
      U každého zlevněného balíčku se návštěvníkovi ukáže i nejnižší cena za
      posledních 30 dní. Historie se zapisuje při každé změně ceny - dozadu
      ji dopočítat nejde, takže začíná dnem, kdy jsi cenu upravil.
    </div>
  </div>
