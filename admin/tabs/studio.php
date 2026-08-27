<?php
/**
 * Administration tab: studio
 *
 * Lifted out of admin/index.php, which had all twenty of these in one
 * 6000-line file. Included from there with the surrounding scope intact,
 * so $tab, $ok, $error, the helpers and everything else the page sets up
 * are available exactly as before.
 */
if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }
?>
  <?php
    /*
     * Everything the drawing studio is allowed to do.
     *
     * These numbers are not decoration for the interface: the same array
     * renders the fields and sliders in the studio (Layout::limits() is
     * handed to the page) and refuses an export that arrives outside them.
     * Raising a maximum here really does raise what the server accepts.
     */
    $sl = Layout::limits();
  ?>
  <form method="post" class="card">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="settings">
    <h2>Limity modelu</h2>
    <p class="hint" style="margin-top:0">
      Proti těmto číslům kontroluje server každý export.
    </p>
    <div class="grid2">
      <div>
        <label for="max_boxes">Max. boxů v jednom exportu</label>
        <input id="max_boxes" name="max_boxes" type="number" min="1" max="2000"
               value="<?= Settings::int('max_boxes') ?>">
        <div class="hint">Víc boxů = větší soubor a delší výpočet.</div>
      </div>
      <div>
        <label for="max_cells">Max. buněk mřížky na stranu</label>
        <input id="max_cells" name="max_cells" type="number" min="1" max="40"
               value="<?= Settings::int('max_cells') ?>">
        <div class="hint">
          Kam až dojede posuvník sloupců a řádků ve studiu. Aktuálně
          <?= Settings::int('max_cells') ?> × <?= Settings::int('max_cells') ?>.
        </div>
      </div>
    </div>
  </form>

  <form method="post" class="card">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="settings">
    <input type="hidden" name="_flags" value="news_enabled">
    <input type="hidden" name="_texts" value="news_body,news_body_en">
    <input type="hidden" name="_news_form" value="1">
    <h2>Co je nového</h2>
    <p class="hint" style="margin-top:0">
      Karta, která se ve studiu ukáže jednou tomu, kdo tu už byl - místo
      uvítacího nastavení. Kdo přijde poprvé, dostane pořád uvítání.
    </p>
    <label class="check">
      <input type="checkbox" name="news_enabled" value="1" <?= Settings::bool('news_enabled') ? 'checked' : '' ?>>
      Zobrazovat kartu ve studiu
    </label>
    <div class="grid2" style="margin-top:12px">
      <div>
        <label>Verze novinek</label>
        <input type="text" value="<?= e(H3D_VERSION . ' · #' . Settings::int('news_version')) ?>" readonly>
        <div class="hint">
          Každé potvrzené uložení této karty zvýší číslo o 1. Uživatelé uvidí
          kartu znovu jen tehdy, když je nová verze vyšší než ta, kterou už četli.
        </div>
      </div>
      <div>
        <label for="news_title">Nadpis (CZ)</label>
        <input id="news_title" name="news_title" type="text" maxlength="120"
               value="<?= e(Settings::get('news_title')) ?>">
        <label for="news_title_en" style="margin-top:10px">Nadpis (EN)</label>
        <input id="news_title_en" name="news_title_en" type="text" maxlength="120"
               value="<?= e(Settings::get('news_title_en')) ?>">
      </div>
    </div>
    <label for="news_body" style="margin-top:12px">Body (CZ)</label>
    <textarea id="news_body" name="news_body" rows="8"><?= e(Settings::get('news_body')) ?></textarea>
    <div class="hint">
      Každý řádek je jedna odrážka. Prázdný řádek dělá mezeru, řádek
      začínající <code>#</code> je mezinadpis. Prázdné pole = nic se
      nezobrazí.
    </div>
    <label for="news_body_en" style="margin-top:12px">Body (EN)</label>
    <textarea id="news_body_en" name="news_body_en" rows="8"><?= e(Settings::get('news_body_en')) ?></textarea>
    <div class="hint">Prázdné = i anglickému návštěvníkovi se ukáže česká verze.</div>
  </form>

  <form method="post" class="card">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="settings">
    <h2>Rozsahy parametrů</h2>
    <p class="hint" style="margin-top:0">
      Meze polí a posuvníků ve studiu, v milimetrech. Co je mimo, studio
      nenabídne a server nepřijme. Hodnota, kterou má někdo uloženou
      z dřívějška a je teď nad novým stropem, se při načtení srovná dolů.
    </p>

    <h3 style="margin-bottom:6px">Rozměry šuplíku</h3>
    <div class="grid3">
      <div>
        <label for="studio_dw_max">Max. šířka</label>
        <input id="studio_dw_max" name="studio_dw_max" type="number" min="1" max="5000" step="1"
               value="<?= e(rtrim(rtrim(number_format($sl['dw'][1], 2, '.', ''), '0'), '.')) ?>">
      </div>
      <div>
        <label for="studio_dd_max">Max. hloubka</label>
        <input id="studio_dd_max" name="studio_dd_max" type="number" min="1" max="5000" step="1"
               value="<?= e(rtrim(rtrim(number_format($sl['dd'][1], 2, '.', ''), '0'), '.')) ?>">
      </div>
      <div>
        <label for="studio_dh_max">Max. výška</label>
        <input id="studio_dh_max" name="studio_dh_max" type="number" min="1" max="5000" step="1"
               value="<?= e(rtrim(rtrim(number_format($sl['dh'][1], 2, '.', ''), '0'), '.')) ?>">
      </div>
    </div>

    <h3 style="margin:18px 0 6px">Konstrukce</h3>
    <div class="grid2">
      <div>
        <label for="studio_wall_min">Stěna - min.</label>
        <input id="studio_wall_min" name="studio_wall_min" type="number" min="0.1" max="50" step="0.1"
               value="<?= e(rtrim(rtrim(number_format($sl['wall'][0], 2, '.', ''), '0'), '.')) ?>">
        <div class="hint">Pod tímhle už tisk nedrží pohromadě.</div>
      </div>
      <div>
        <label for="studio_wall_max">Stěna - max.</label>
        <input id="studio_wall_max" name="studio_wall_max" type="number" min="0.1" max="50" step="0.1"
               value="<?= e(rtrim(rtrim(number_format($sl['wall'][1], 2, '.', ''), '0'), '.')) ?>">
      </div>
      <div>
        <label for="studio_bottom_min">Dno - min.</label>
        <input id="studio_bottom_min" name="studio_bottom_min" type="number" min="0.1" max="50" step="0.1"
               value="<?= e(rtrim(rtrim(number_format($sl['bottom'][0], 2, '.', ''), '0'), '.')) ?>">
      </div>
      <div>
        <label for="studio_bottom_max">Dno - max.</label>
        <input id="studio_bottom_max" name="studio_bottom_max" type="number" min="0.1" max="50" step="0.1"
               value="<?= e(rtrim(rtrim(number_format($sl['bottom'][1], 2, '.', ''), '0'), '.')) ?>">
      </div>
    </div>

    <div class="grid3" style="margin-top:14px">
      <div>
        <label for="studio_radius_max">Max. zaoblení rohů</label>
        <input id="studio_radius_max" name="studio_radius_max" type="number" min="0" max="100" step="0.1"
               value="<?= e(rtrim(rtrim(number_format($sl['radius'][1], 2, '.', ''), '0'), '.')) ?>">
      </div>
      <div>
        <label for="studio_gap_max">Max. mezera mezi boxy</label>
        <input id="studio_gap_max" name="studio_gap_max" type="number" min="0" max="50" step="0.1"
               value="<?= e(rtrim(rtrim(number_format($sl['gap'][1], 2, '.', ''), '0'), '.')) ?>">
        <div class="hint">Mezera mezi sousedy; každý box z ní ubere polovinu.</div>
      </div>
      <div>
        <label for="studio_outer_max">Max. venkovní odsazení</label>
        <input id="studio_outer_max" name="studio_outer_max" type="number" min="0" max="100" step="0.1"
               value="<?= e(rtrim(rtrim(number_format($sl['outer'][1], 2, '.', ''), '0'), '.')) ?>">
        <div class="hint">Volný okraj dokola celého layoutu.</div>
      </div>
    </div>

    <h3 style="margin:18px 0 6px">Tisková plocha</h3>
    <div class="grid2">
      <div>
        <label for="studio_print_max">Max. nastavitelná plocha</label>
        <?php /* Whole millimetres, and any of them: a step of 10 refused
                 501 with a browser error nobody asked for. */ ?>
        <input id="studio_print_max" name="studio_print_max" type="number" min="1" max="5000" step="1"
               value="<?= e(rtrim(rtrim(number_format($sl['print'][1], 2, '.', ''), '0'), '.')) ?>">
        <div class="hint">
          Kam až jde nastavit plocha tiskárny ve studiu. Samotná kontrola
          „vejde se box na podložku" se řídí tím, co si uživatel zadá.
        </div>
      </div>
    </div>
  </form>
