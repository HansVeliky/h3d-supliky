<?php
/**
 * Administration tab: features
 *
 * Lifted out of admin/index.php, which had all twenty of these in one
 * 6000-line file. Included from there with the surrounding scope intact,
 * so $tab, $ok, $error, the helpers and everything else the page sets up
 * are available exactly as before.
 */
if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }
?>
  <form method="post" data-busy="Ukládám…">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="settings">
    <input type="hidden" name="_flags" value="purchase_enabled,credits_enabled,bugs_enabled">
    <input type="hidden" name="_texts" value="analytics_id">

    <div class="card">
      <h2>Co je na webu zapnuté</h2>
      <p class="hint" style="margin-top:0">
        Vypnutá část zmizí zákazníkům z očí, ale nic se nemaže - data
        zůstanou a po zapnutí se vrátí, jak byla. Podrobné nastavení každé
        z nich je ve své vlastní záložce, tady je jen vypínač.
      </p>

      <?php
        toggle('purchase_enabled', 'Prodej balíčků a kreditů',
               'Vypnuté = na účtu zmizí záložka Koupit kredity a nejdou zakládat nové objednávky. Balíčky se nastavují v Prodej a peníze → Balíčky.');
        toggle('credits_enabled', 'Kredity',
               'Vypnuté = export se neplatí kredity, jede jen na volných exportech. Ceny a bonusy jsou v Exporty a kredity.');
        toggle('bugs_enabled', 'Hlášení chyb a nápadů',
               'Veřejná sekce pro přihlášené: nahlásí chybu nebo pošlou nápad, ostatní hlasují. Správa je v záložce Hlášení.');
      ?>

      <label for="order_process_days" style="margin-top:16px">Zpracování objednávky trvá až (dny)</label>
      <input id="order_process_days" name="order_process_days" type="number" min="0" max="30"
             value="<?= Settings::int('order_process_days') ?>" style="max-width:160px">
      <div class="hint">
        Tohle číslo se říká zákazníkovi u nezaplacené objednávky a v e-mailu:
        platby páruješ ručně, takže slibovat rychlejší potvrzení, než stíháš,
        se nevyplácí. 0 = neříkat nic.
      </div>
    </div>

    <div class="card">
      <h2>Měření návštěvnosti</h2>
      <label for="analytics_id">Google Analytics - měřicí ID</label>
      <input id="analytics_id" name="analytics_id" type="text" class="mono"
             placeholder="G-XXXXXXXXXX" style="max-width:280px"
             value="<?= e(Settings::get('analytics_id')) ?>">
      <div class="hint">
        Prázdné = neměří se nic a nikomu se odsud nic neposílá. Vyplněné =
        načte se Google Analytics, ale <strong>až potom, co návštěvník klikne
        na Přijmout</strong> v cookie liště. Bez souhlasu se skript vůbec
        nestáhne. Reklamní a personalizační funkce jsou vypnuté, IP zkrácená.
      </div>
      <div class="hint">
        Cookie lišta se objeví jen tehdy, když je tu ID - bez měření není na
        co se ptát a ptát se zbytečně jen učí lidi odklikávat souhlas bez
        čtení. Co přesně web ukládá, je popsané na stránce
        <a href="../cookies.php" target="_blank" rel="noopener">cookies.php</a>;
        odkaz na ni je v patičce každé stránky.
      </div>
      <?php if (Consent::analyticsId() === '' && trim(Settings::get('analytics_id')) !== ''): ?>
        <div class="msg warn" style="margin-top:12px">
          Tohle ID nevypadá jako platné (čeká se <code>G-…</code> nebo
          <code>UA-…-…</code>), takže se měření nespustí.
        </div>
      <?php endif; ?>
    </div>
  </form>
