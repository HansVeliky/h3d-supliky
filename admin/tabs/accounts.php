<?php
/**
 * Administration tab: accounts
 *
 * Lifted out of admin/index.php, which had all twenty of these in one
 * 6000-line file. Included from there with the surrounding scope intact,
 * so $tab, $ok, $error, the helpers and everything else the page sets up
 * are available exactly as before.
 */
if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }
?>
  <form method="post" class="card">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="settings">
    <input type="hidden" name="_flags" value="registration_open">
    <input type="hidden" name="_texts" value="reserved_names">

<fieldset>
      <legend>Účty</legend>
      <label class="check">
        <input type="checkbox" name="registration_open" value="1" <?= Settings::bool('registration_open') ? 'checked' : '' ?>>
        Povolit registraci nových účtů
      </label>
      <div class="hint">
        Úplně první účet se zaregistruje vždycky a stane se správcem, jinak by
        se do čerstvé instalace nedalo dostat. Při zapnuté údržbě je registrace
        zavřená bez ohledu na tohle nastavení.
      </div>
    </fieldset>

    <fieldset style="margin-top:18px">
      <legend>Zabraná jména</legend>
      <div class="hint" style="margin-top:0">
        Jména, která si nikdo nemůže dát jako zobrazované jméno. Nejde
        o oprávnění - ta se řídí rolí účtu, ne jménem. Jde o podporu, kde by
        zpráva podepsaná „Správce" vypadala, že je od nás. Porovnává se bez
        ohledu na velikost písmen a diakritiku, takže <code>Spravce</code>
        i <code>SPRÁVCE</code> jsou totéž slovo.
      </div>
      <div class="chips-editor" data-chips="reserved_names">
        <div class="chips-row">
          <input type="text" class="chips-input" id="reservedInput"
                 placeholder="Napiš jméno a stiskni Enter" autocomplete="off">
          <button class="btn" type="button" data-chips-add>Přidat</button>
        </div>
        <div class="chips-list" id="reservedChips" aria-live="polite"></div>
        <!-- The real value. Without JavaScript this stays an ordinary
             comma-separated field, which is still perfectly editable. -->
        <input type="text" name="reserved_names" id="reservedNames"
               value="<?= e(Settings::get('reserved_names')) ?>">
      </div>
    </fieldset>
  </form>
