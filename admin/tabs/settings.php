<?php
/**
 * Administration tab: settings
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
    <input type="hidden" name="_flags" value="robots">
    <input type="hidden" name="_texts" value="custom_links">

<fieldset>
      <legend>Identita</legend>
      <label for="site_name">Název webu</label>
      <input id="site_name" name="site_name" type="text" value="<?= e(Settings::get('site_name')) ?>">
      <div class="hint">Používá se v titulku stránek a v e-mailech.</div>

      <div class="grid2">
        <div>
          <label for="brand_label">Zobrazené jméno v hlavičce</label>
          <input id="brand_label" name="brand_label" type="text" placeholder="Honza3D"
                 value="<?= e(Settings::get('brand_label')) ?>">
          <div class="hint">Prázdné = použije se název webu.</div>
        </div>
        <div>
          <label for="brand_url">Odkaz z hlavičky</label>
          <input id="brand_url" name="brand_url" type="url"
                 placeholder="https://hans.jecool.net/d2/index.php"
                 value="<?= e(Settings::get('brand_url')) ?>">
          <div class="hint">Prázdné = jméno nebude odkaz.</div>
        </div>
      </div>
      <div class="grid2">
        <div>
          <label for="share_base_url">Základ sdíleného odkazu</label>
          <input id="share_base_url" name="share_base_url" type="url"
                 placeholder="https://example.com/d_beta"
                 value="<?= e(Settings::get('share_base_url')) ?>">
          <div class="hint">Určuje adresu, ze které se skládají odkazy na sdílené návrhy. Prázdné = použije se automaticky aktuální adresa webu.</div>
        </div>
        <div>
          <label for="default_lang">Výchozí jazyk</label>
          <select id="default_lang" name="default_lang">
            <?php foreach (['en' => 'Angličtina', 'cs' => 'Čeština'] as $k => $v): ?>
              <option value="<?= $k ?>" <?= Settings::get('default_lang') === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
          <div class="hint">Týká se veřejné části, ne administrace.</div>
        </div></div>
      <div class="grid2">
        <div>
          <label for="timezone">Časové pásmo</label>
          <input id="timezone" name="timezone" type="text" value="<?= e(Settings::get('timezone')) ?>">
          <div class="hint">Rozhoduje, kdy je půlnoc pro denní kredity. Např. <code>Europe/Prague</code>.</div>
        </div>
      </div>
      <label class="check">
        <input type="checkbox" name="robots" value="1" <?= Settings::bool('robots') ? 'checked' : '' ?>>
        Povolit indexování vyhledávači
      </label>

      <label for="custom_links">Odkazy na tvoje další služby</label>
      <textarea id="custom_links" name="custom_links" rows="5"
                placeholder="Honza3D | https://honza3d.cz&#10;Filament Manager | https://hans.jecool.net/manager/&#10;https://hans.jecool.net/teren"><?= e(Settings::get('custom_links')) ?></textarea>
      <div class="hint">
        Jeden odkaz na řádek ve tvaru <code>Popisek | https://adresa</code>.
        Samotná adresa bez popisku funguje taky - použije se doména. Řádek
        začínající <code>#</code> se přeskočí. Zobrazí se v patičce účtu
        i administrace. Povolené je jen <code>http</code> a <code>https</code>.
        <?php $lp = Links::all(); ?>
        <?php if ($lp): ?>
          <br>Aktuálně: <?= count($lp) ?> odkazů -
          <?= e(implode(', ', array_column($lp, 'label'))) ?>
        <?php endif; ?>
      </div>
    </fieldset>
  </form>
