<?php
/**
 * Administration tab: seller
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
    <input type="hidden" name="_flags" value="">
    <input type="hidden" name="_texts" value="invoice_info">

<fieldset>
      <legend>Fakturační údaje</legend>
      <label for="invoice_seller">Dodavatel (jméno / firma)</label>
      <input id="invoice_seller" name="invoice_seller" type="text"
             placeholder="<?= e(Settings::get('site_name')) ?>"
             value="<?= e(Settings::get('invoice_seller')) ?>">
      <div class="hint">Prázdné = použije se název webu.</div>

      <label for="invoice_info">Údaje na doklad</label>
      <textarea id="invoice_info" name="invoice_info" rows="4"
                placeholder="Jméno Příjmení&#10;Ulice 1, 100 00 Praha&#10;IČO: 12345678&#10;Neplátce DPH"><?= e(Settings::get('invoice_info')) ?></textarea>
      <div class="hint">
        Zobrazí se na potvrzení o platbě (fakturce), přesně jak to napíšeš -
        adresa, IČO/DIČ, „neplátce DPH", kontakt.
      </div>
    </fieldset>
  </form>
