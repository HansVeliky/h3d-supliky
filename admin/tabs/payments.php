<?php
/**
 * Administration tab: payments
 *
 * Lifted out of admin/index.php, which had all twenty of these in one
 * 6000-line file. Included from there with the surrounding scope intact,
 * so $tab, $ok, $error, the helpers and everything else the page sets up
 * are available exactly as before.
 */
if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }

    $methods    = Payments::all();
    $currencies = Money::all();
    $base       = Money::base();

    /** One line describing what the customer ends up looking at. */
    $payPreview = static function (array $m) use ($base): string {
        $p = Payments::forOrder($m, ['price_cents' => 24900, 'currency' => $base, 'vs' => '000123']);
        return $p['url'] !== '' ? $p['url'] : $p['text'];
    };

    /** "2 desetinná místa" - the number decides the ending. */
    $decimalsCs = static function (int $n): string {
        return match ($n) {
            0       => 'bez desetinných míst',
            1       => '1 desetinné místo',
            default => $n . ' desetinná místa',
        };
    };
?>
  <form method="post" data-busy="Ukládám…">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="settings">
    <input type="hidden" name="_flags" value="purchase_enabled">
    <input type="hidden" name="_texts" value="purchase_instructions">

    <div class="card">
      <h2>Prodej kreditů</h2>
      <label class="check">
        <input type="checkbox" name="purchase_enabled" value="1" <?= Settings::bool('purchase_enabled') ? 'checked' : '' ?>>
        Zobrazovat balíčky kreditů
      </label>

      <div class="grid2" style="margin-top:14px">
        <div>
          <label for="currency">Základní měna</label>
          <select id="currency" name="currency">
            <?php foreach ($currencies as $c): ?>
              <option value="<?= e($c['code']) ?>" <?= $base === $c['code'] ? 'selected' : '' ?>>
                <?= e($c['code']) ?> <?= e($c['symbol']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="hint">
            Ceny balíčků se zadávají v ní, ostatní měny se přepočítají kurzem.
            Volný text tu byl dřív a nadělal škodu: „€" se po očištění smrsklo
            na prázdno a tiše se použily koruny.
          </div>
        </div>
        <div>
          <label for="accept_expiry_days">Splatnost přijaté objednávky (dny)</label>
          <input id="accept_expiry_days" name="accept_expiry_days" type="number" min="0"
                 value="<?= Settings::int('accept_expiry_days') ?>">
          <div class="hint">
            Po přijetí objednávky ke zpracování má zákazník tolik dní na
            zaplacení. Pak se objednávka sama zruší a přijde mu o tom e-mail.
            0 = nikdy neruší. Kontrola běží při otevření administrace nebo
            účtu - na tomhle serveru není plánovač, který by ji spouštěl sám.
          </div>
        </div>
      </div>

      <label for="purchase_instructions" style="margin-top:14px">Pokyny k platbě</label>
      <textarea id="purchase_instructions" name="purchase_instructions" rows="3"><?= e(Settings::get('purchase_instructions')) ?></textarea>
      <div class="hint">
        Prázdné = použije se vestavěný text v jazyce čtenáře. Cokoli sem
        napíšeš se ukáže přesně tak, jak to je, všem bez ohledu na jazyk -
        pole je jen jedno.
      </div>
      <div class="hint">
        Hromadná sleva na balíčky se nastavuje v záložce
        <a href="?tab=billing">Balíčky</a>, u balíčků samotných.
      </div>

      <div class="msg warn" style="margin-top:14px">
        <strong>Platební brána tu není.</strong> Odkaz jenom otevře PayPal -
        nic v aplikaci se nedozví, že platba dorazila. Objednávku musíš
        označit jako zaplacenou ručně v záložce Objednávky.
      </div>
    </div>
  </form>

  <div class="card">
    <div class="card-head">
      <h2>Měny</h2>
      <button class="btn primary small" type="button" data-edit-open="cur-new">Přidat měnu</button>
    </div>
    <p class="hint" style="margin-top:0">
      Ceny se zadávají v základní měně (<?= e($base) ?>), ostatní se přepočítají
      kurzem: kolik <?= e($base) ?> stojí jedna jednotka té měny. Kurz je ruční -
      žádný automatický zdroj tu není, takže si ho hlídej sám. Ukázka na kartě
      je cena 249 <?= e($base) ?> přepočtená do dané měny.
    </p>

    <?php if (!$currencies): ?>
      <p class="hint" style="margin:0">Zatím žádná měna. Přidej aspoň tu základní.</p>
    <?php else: ?>
      <div class="adm-cards" data-sortable data-sort-action="reorder_currencies">
        <?php foreach ($currencies as $c): ?>
          <?php
            $isBase = $c['code'] === $base;
            $con    = (int) $c['active'] === 1;
            $cid    = 'cur-' . preg_replace('/[^A-Za-z0-9]/', '', (string) $c['code']);
          ?>
          <div class="adm-card<?= $con || $isBase ? '' : ' is-off' ?>" data-sort-id="<?= e($c['code']) ?>">
            <span class="drag-handle" draggable="true" title="Táhni pro pořadí" aria-label="Přesunout">⠿</span>
            <div class="adm-card-main">
              <div class="adm-card-title">
                <span class="mono"><?= e($c['code']) ?></span>
                <span class="hint"><?= e($c['symbol']) ?></span>
                <?php if ($isBase): ?><span class="tag on">základní</span><?php endif; ?>
                <?php if (!$con && !$isBase): ?><span class="tag off">neaktivní</span><?php endif; ?>
              </div>
              <div class="adm-card-sub hint">
                <?php if ($isBase): ?>
                  Měřítko pro ostatní měny
                <?php else: ?>
                  1 <?= e($c['code']) ?> = <?= e(Money::rateOf($c)) ?> <?= e($base) ?>
                <?php endif; ?>
                · <?= e($decimalsCs((int) $c['decimals'])) ?>
              </div>
            </div>
            <div class="adm-card-side">
              <?= e(money(Money::convert(24900, (string) $c['code']), (string) $c['code'])) ?>
            </div>
            <button class="btn small" type="button" data-edit-open="<?= $cid ?>">Upravit</button>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- One dialog per currency, opened from its card. -->
  <?php foreach ($currencies as $c): ?>
    <?php
      $isBase = $c['code'] === $base;
      $con    = (int) $c['active'] === 1;
      $cid    = 'cur-' . preg_replace('/[^A-Za-z0-9]/', '', (string) $c['code']);
    ?>
    <div class="modal-back edit-modal" id="<?= $cid ?>" hidden>
      <div class="modal modal-wide" role="dialog" aria-modal="true">
        <button type="button" class="modal-x" data-edit-close aria-label="Zavřít">&times;</button>
        <h3><?= e($c['code']) ?><?= $isBase ? ' - základní měna' : '' ?></h3>
        <form method="post" id="<?= $cid ?>-form">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="action" value="save_currency">
          <input type="hidden" name="code" value="<?= e($c['code']) ?>">
          <div class="grid2">
            <div>
              <label for="<?= $cid ?>-sym">Symbol</label>
              <input id="<?= $cid ?>-sym" name="symbol" type="text" value="<?= e($c['symbol']) ?>">
              <div class="hint">Píše se za částku, například <code>Kč</code>.</div>
            </div>
            <div>
              <label for="<?= $cid ?>-dec">Desetinná místa</label>
              <input id="<?= $cid ?>-dec" name="decimals" type="number" min="0" max="2" value="<?= (int) $c['decimals'] ?>">
              <div class="hint">Forinty se nezaokrouhlují na haléře - tam dej 0.</div>
            </div>
          </div>

          <?php if ($isBase): ?>
            <input type="hidden" name="rate" value="1">
            <input type="hidden" name="active" value="1">
            <div class="hint">
              Základní měna má kurz 1 a nedá se vypnout ani smazat. Změnit ji
              můžeš v Prodeji kreditů nahoře - ostatní kurzy se přepočítají.
            </div>
          <?php else: ?>
            <label for="<?= $cid ?>-rate">Kurz: 1 <?= e($c['code']) ?> = ? <?= e($base) ?></label>
            <input id="<?= $cid ?>-rate" name="rate" type="text" value="<?= e(Money::rateOf($c)) ?>">
            <div class="hint">Při 1 <?= e($c['code']) ?> = 24,50 <?= e($base) ?> zadáš <code>24.50</code>.</div>
            <label class="check" style="margin-top:12px">
              <input type="checkbox" name="active" value="1" <?= $con ? 'checked' : '' ?>>
              Aktivní (zákazník si ji může vybrat)
            </label>
          <?php endif; ?>

          <div class="credit-dialog-actions">
            <button class="btn primary" type="submit">Uložit</button>
            <button class="btn" type="button" data-edit-close>Zrušit</button>
          </div>
        </form>
        <?php if (!$isBase): ?>
          <form method="post" id="<?= $cid ?>-del" style="margin-top:14px;border-top:1px solid var(--line);padding-top:12px">
            <?= Auth::csrfField() ?>
            <input type="hidden" name="action" value="delete_currency">
            <input type="hidden" name="code" value="<?= e($c['code']) ?>">
            <button class="btn small danger" type="submit"
                    data-confirm="Smazat měnu <?= e($c['code']) ?>? Objednávky v ní zůstanou a budou se dál zobrazovat správně."
                    data-confirm-ok="Smazat měnu">Smazat měnu</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="modal-back edit-modal" id="cur-new" hidden>
    <div class="modal modal-wide" role="dialog" aria-modal="true">
      <button type="button" class="modal-x" data-edit-close aria-label="Zavřít">&times;</button>
      <h3>Přidat měnu</h3>
      <form method="post">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="save_currency">
        <div class="grid2">
          <div>
            <label for="ncode">Kód</label>
            <input id="ncode" name="code" type="text" maxlength="3" placeholder="EUR" class="mono" required>
            <div class="hint">Tři písmena podle ISO, například EUR nebo PLN.</div>
          </div>
          <div>
            <label for="nsym">Symbol</label>
            <input id="nsym" name="symbol" type="text" placeholder="€">
            <div class="hint">Prázdné = doplní se známý symbol, jinak kód.</div>
          </div>
        </div>
        <div class="grid2">
          <div>
            <label for="nrate">Kurz: 1 jednotka = ? <?= e($base) ?></label>
            <input id="nrate" name="rate" type="text" placeholder="24.50" required>
            <div class="hint">Při 1 EUR = 24,50 <?= e($base) ?> zadáš <code>24.50</code>.</div>
          </div>
          <div>
            <label for="ndec">Desetinná místa</label>
            <input id="ndec" name="decimals" type="number" min="0" max="2" value="2">
          </div>
        </div>
        <label class="check"><input type="checkbox" name="active" value="1" checked> Aktivní</label>
        <div class="credit-dialog-actions">
          <button class="btn primary" type="submit">Přidat měnu</button>
          <button class="btn" type="button" data-edit-close>Zrušit</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <h2>Způsoby platby</h2>
      <button class="btn primary small" type="button" data-edit-open="pay-new">Přidat způsob platby</button>
    </div>
    <p class="hint" style="margin-top:0">
      Zákazník je uvidí u nezaplacené objednávky, v pořadí, v jakém je tu
      seřadíš. U PayPalu a Revolutu stačí uživatelské jméno, odkaz i částka se
      poskládají samy; u převodu nebo krypta se text jen zobrazí k opsání.
    </p>

    <?php if (!$methods): ?>
      <p class="hint" style="margin:0">
        Zatím žádný způsob platby. Bez něj zákazník uvidí jen pokyny k platbě.
      </p>
    <?php else: ?>
      <div class="adm-cards" data-sortable data-sort-action="reorder_payments">
        <?php foreach ($methods as $m): ?>
          <?php
            $mon   = (int) $m['active'] === 1;
            $kind  = (string) $m['kind'];
            $kname = Payments::KINDS[$kind][0] ?? $kind;
            $only  = trim((string) ($m['currencies'] ?? ''));
            $prev  = $payPreview($m);
          ?>
          <div class="adm-card<?= $mon ? '' : ' is-off' ?>" data-sort-id="<?= (int) $m['id'] ?>">
            <span class="drag-handle" draggable="true" title="Táhni pro pořadí" aria-label="Přesunout">⠿</span>
            <span class="adm-card-icon"><?= Payments::icon($kind) ?></span>
            <div class="adm-card-main">
              <div class="adm-card-title">
                <?= e($m['label']) ?>
                <?php if (!$mon): ?><span class="tag off">neaktivní</span><?php endif; ?>
                <?php if ($only !== ''): ?><span class="tag warn">jen <?= e($only) ?></span><?php endif; ?>
              </div>
              <div class="adm-card-sub hint">
                <?= e($kname) ?>
                <?php if (trim((string) $m['target']) === ''): ?>
                  · <strong>cíl není vyplněný</strong>
                <?php elseif ($prev !== ''): ?>
                  · <code><?= e($prev) ?></code>
                <?php endif; ?>
              </div>
            </div>
            <button class="btn small" type="button" data-edit-open="pay-<?= (int) $m['id'] ?>">Upravit</button>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="hint">Náhled je u objednávky za 249 <?= e($base) ?>.</p>
    <?php endif; ?>
  </div>

  <!-- One dialog per method, opened from its card, plus the one for a new one. -->
  <?php foreach ($methods as $m): ?>
    <?php $mid = 'pay-' . (int) $m['id']; $mon = (int) $m['active'] === 1; ?>
    <div class="modal-back edit-modal" id="<?= $mid ?>" hidden>
      <div class="modal modal-wide" role="dialog" aria-modal="true">
        <button type="button" class="modal-x" data-edit-close aria-label="Zavřít">&times;</button>
        <h3><?= e($m['label']) ?></h3>
        <form method="post" id="<?= $mid ?>-form">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="action" value="save_payment">
          <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
          <div class="grid2">
            <div>
              <label for="<?= $mid ?>-kind">Typ</label>
              <select id="<?= $mid ?>-kind" name="kind" data-kind-hint="<?= $mid ?>-hint">
                <?php foreach (Payments::KINDS as $k => $info): ?>
                  <option value="<?= e($k) ?>" data-hint="<?= e($info[2]) ?>"
                          <?= $m['kind'] === $k ? 'selected' : '' ?>><?= e($info[0]) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="<?= $mid ?>-label">Název</label>
              <input id="<?= $mid ?>-label" name="label" type="text" value="<?= e($m['label']) ?>">
              <div class="hint">Prázdné = název typu.</div>
            </div>
          </div>
          <label for="<?= $mid ?>-target">Cíl</label>
          <input id="<?= $mid ?>-target" name="target" type="text" value="<?= e($m['target']) ?>">
          <div class="hint" id="<?= $mid ?>-hint"></div>

          <label for="<?= $mid ?>-note" style="margin-top:12px">Poznámka pro zákazníka</label>
          <input id="<?= $mid ?>-note" name="note" type="text" value="<?= e($m['note']) ?>">

          <?php $only = array_filter(array_map('trim', explode(',', strtoupper((string) ($m['currencies'] ?? ''))))); ?>
          <label style="margin-top:12px">Jen pro měny</label>
          <div class="row" style="gap:14px;flex-wrap:wrap">
            <?php foreach ($currencies as $c): ?>
              <label class="check" style="margin:0">
                <input type="checkbox" name="currencies[]" value="<?= e($c['code']) ?>"
                       <?= in_array((string) $c['code'], $only, true) ? 'checked' : '' ?>>
                <?= e($c['code']) ?>
              </label>
            <?php endforeach; ?>
          </div>
          <div class="hint">
            Nic zaškrtnutého = nabídne se u všech měn. Účet vedený v korunách
            nemá smysl nabízet u objednávky v eurech.
          </div>

          <label class="check" style="margin-top:12px">
            <input type="checkbox" name="active" value="1" <?= $mon ? 'checked' : '' ?>> Aktivní
          </label>

          <div class="credit-dialog-actions">
            <button class="btn primary" type="submit">Uložit</button>
            <button class="btn" type="button" data-edit-close>Zrušit</button>
          </div>
        </form>
        <form method="post" id="<?= $mid ?>-del" style="margin-top:14px;border-top:1px solid var(--line);padding-top:12px">
          <?= Auth::csrfField() ?>
          <input type="hidden" name="action" value="delete_payment">
          <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
          <button class="btn small danger" type="submit"
                  data-confirm="Smazat platební možnost <?= e($m['label']) ?>? Dřívější objednávky to neovlivní."
                  data-confirm-ok="Smazat">Smazat způsob platby</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="modal-back edit-modal" id="pay-new" hidden>
    <div class="modal modal-wide" role="dialog" aria-modal="true">
      <button type="button" class="modal-x" data-edit-close aria-label="Zavřít">&times;</button>
      <h3>Přidat způsob platby</h3>
      <form method="post">
        <?= Auth::csrfField() ?>
        <input type="hidden" name="action" value="save_payment">
        <div class="grid2">
          <div>
            <label for="nkind">Typ</label>
            <select id="nkind" name="kind" data-kind-hint="nkind-hint">
              <?php foreach (Payments::KINDS as $k => $info): ?>
                <option value="<?= e($k) ?>" data-hint="<?= e($info[2]) ?>"><?= e($info[0]) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div>
            <label for="nlabel">Název</label>
            <input id="nlabel" name="label" type="text" placeholder="Nechej prázdné pro výchozí">
          </div>
        </div>
        <label for="ntarget">Cíl</label>
        <input id="ntarget" name="target" type="text" placeholder="Uživatelské jméno, číslo účtu nebo odkaz">
        <div class="hint" id="nkind-hint"></div>

        <label for="nnote" style="margin-top:12px">Poznámka pro zákazníka</label>
        <input id="nnote" name="note" type="text" placeholder="Do zprávy uveď variabilní symbol.">

        <label style="margin-top:12px">Jen pro měny</label>
        <div class="row" style="gap:14px;flex-wrap:wrap">
          <?php foreach ($currencies as $c): ?>
            <label class="check" style="margin:0">
              <input type="checkbox" name="currencies[]" value="<?= e($c['code']) ?>">
              <?= e($c['code']) ?>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="hint">Nic zaškrtnutého = nabídne se u všech měn.</div>

        <label class="check" style="margin-top:12px">
          <input type="checkbox" name="active" value="1" checked> Aktivní
        </label>
        <div class="credit-dialog-actions">
          <button class="btn primary" type="submit">Přidat</button>
          <button class="btn" type="button" data-edit-close>Zrušit</button>
        </div>
      </form>
    </div>
  </div>
