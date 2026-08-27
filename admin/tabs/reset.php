<?php
/**
 * Administration tab: reset
 *
 * Lifted out of admin/index.php, which had all twenty of these in one
 * 6000-line file. Included from there with the surrounding scope intact,
 * so $tab, $ok, $error, the helpers and everything else the page sets up
 * are available exactly as before.
 */
if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }

    $counts = Reset::counts();
?>
  <div class="card">
    <h2>Veřejný provoz</h2>
    <?php $maintenance = Settings::bool('maintenance_mode'); ?>
    <div class="msg <?= $maintenance ? 'warn' : 'info' ?>" style="margin-top:0">
      <?= $maintenance
        ? 'Web je pro veřejnost pozastavený. Administrace zůstává dostupná, aby šel provoz bezpečně obnovit.'
        : 'Běžný provoz je zapnutý. Při nasazování změn nebo opravě databáze ho lze dočasně pozastavit.' ?>
    </div>
    <form method="post">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="set_maintenance">
      <input type="hidden" name="maintenance_mode" value="<?= $maintenance ? '0' : '1' ?>">
      <button class="btn <?= $maintenance ? 'primary' : 'danger' ?>" type="submit"
              data-confirm="<?= $maintenance ? 'Znovu zpřístupnit web návštěvníkům?' : 'Pozastavit veřejný web? Přihlášení uživatelé a roboty uvidí stránku s údržbou.' ?>"
              data-confirm-ok="<?= $maintenance ? 'Zapnout web' : 'Pozastavit web' ?>"><?= $maintenance ? 'Zapnout web' : 'Vypnout web kvůli údržbě' ?></button>
    </form>
  </div>

  <div class="card">
    <h2>Kvóty na exporty</h2>
    <p class="hint" style="margin-top:0">
      Uvolní počítadlo volných exportů všem najednou. Historie exportů,
      statistiky ani záznam o tom, kdo co kdy exportoval, se nemažou -
      okno se jen začne počítat od teď, takže každý má zase plný počet
      volných exportů. Kredity, objednávky ani účty se nemění.
      <?php $qr = Settings::int('quota_reset_at'); ?>
      <?php if ($qr > 0): ?>
        <br>Naposledy uvolněno <strong><?= e(when($qr)) ?></strong>.
      <?php endif; ?>
    </p>
    <form method="post">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="reset_quotas">
      <button class="btn" type="submit"
              data-confirm="Uvolnit kvóty všem uživatelům? Každý bude mít znovu plný počet volných exportů. Historie exportů ani statistiky se nemažou."
              data-confirm-ok="Uvolnit kvóty">Vynulovat kvóty</button>
    </form>
  </div>

  <div class="card">
    <h2>Záloha</h2>
    <p class="hint" style="margin-top:0">
      Stáhne celou databázi jako jeden soubor pojmenovaný podle webu.
    </p>
    <div class="msg info">
      Soubor si dobře ulož - je to celá databáze. Obnovit ji můžeš rovnou
      níže v panelu, nebo ručně: nakopírovat do <code>data/</code> pod přesným
      názvem <code><?= e(basename(Db::path())) ?></code>. Ten náhodný kus je
      z <code>data/instance.php</code>, takže při ručním kopírování zálohuj
      i ten.
    </div>
    <form method="post">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="backup">
      <button class="btn primary" type="submit">Stáhnout zálohu</button>
    </form>
  </div>

  <div class="card">
    <h2>Obnova ze zálohy</h2>
    <div class="msg warn" style="margin-top:0">
      Přepíše <strong>celou databázi</strong> nahraným souborem - účty, kredity,
      objednávky i nastavení. Aktuální databáze se předtím bezpečně uloží do
      <code>data/</code> jako <code>before-restore-…sqlite</code>, takže když
      nahraješ omylem špatnou zálohu, jde to vrátit. Po obnově tě to odhlásí
      a přihlašuješ se už proti obnoveným datům.
    </div>
    <form id="restoreForm" method="post" enctype="multipart/form-data" data-busy="Obnovuji databázi…">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="restore">

      <label for="dbfile">Soubor zálohy (<code>.sqlite</code>)</label>
      <input id="dbfile" name="dbfile" type="file"
             accept=".sqlite,application/x-sqlite3,application/octet-stream" required>
      <div class="hint" style="margin-bottom:12px">
        Musí to být <code>.sqlite</code> záloha z tohohle webu. Cizí nebo
        poškozený soubor se ověří a odmítne dřív, než se čehokoli dotkne.
      </div>

      <div class="grid2">
        <div>
          <label for="restore_confirm">Napiš OBNOVIT</label>
          <input id="restore_confirm" name="confirm" type="text" autocomplete="off" placeholder="OBNOVIT">
        </div>
        <div>
          <label for="restore_pass">Tvoje heslo</label>
          <input id="restore_pass" name="password" type="password" autocomplete="current-password">
        </div>
      </div>

      <button id="restoreSubmit" class="btn danger" type="submit" style="margin-top:14px" disabled
              data-confirm="Opravdu přepsat celou databázi nahraným souborem? Aktuální data se předtím zazálohují do data/, ale i tak je to velký zásah."
              data-confirm-ok="Obnovit ze zálohy">Obnovit ze zálohy</button>
    </form>
    <script>(function(){var f=document.getElementById('restoreForm'),file=document.getElementById('dbfile'),b=document.getElementById('restoreSubmit');if(!f||!file||!b)return;function ready(){b.disabled=!file.files.length;}file.addEventListener('change',ready);ready();})();</script>
  </div>

  <div class="card">
    <h2>Co je teď v databázi</h2>
    <div class="grid3">
      <?php foreach ($counts as $table => $n): ?>
        <div class="stat"><span><?= e($table) ?></span><b><?= (int) $n ?></b></div>
      <?php endforeach; ?>
    </div>
  </div>

  <form method="post" class="card">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="reset">

    <h2>Vyčistit data</h2>
    <div class="msg warn">
      Nic z toho nejde vrátit zpět. Nejdřív si stáhni zálohu.
      <br><strong>Chaty a konverzace jsou samostatné volby:</strong> můžeš smazat jen aktivní chaty a ponechat archiv, nebo odstranit kompletní historii podpory.
    </div>

    <?php foreach (Reset::SCOPES as $key => [$label, $desc]): ?>
      <label class="check" style="align-items:flex-start">
        <input type="checkbox" name="scopes[]" value="<?= e($key) ?>">
        <span>
          <strong><?= e($label) ?></strong>
          <div class="hint"><?= e($desc) ?></div>
        </span>
      </label>
    <?php endforeach; ?>

    <fieldset style="margin-top:16px;border-color:var(--bad)">
      <legend style="color:var(--bad)">Úplně od nuly</legend>
      <label class="check" style="align-items:flex-start">
        <input type="checkbox" name="everything" value="1">
        <span>
          <strong>Smazat celou databázi</strong>
          <div class="hint">
            Smaže soubor databáze i token instance. Web se vrátí do stavu čerstvé
            instalace - první registrace zase vytvoří správce. Tvůj účet zmizí taky.
          </div>
        </span>
      </label>
    </fieldset>

    <div class="grid2" style="margin-top:16px">
      <div>
        <label for="confirm">Napiš RESET</label>
        <input id="confirm" name="confirm" type="text" autocomplete="off" placeholder="RESET">
      </div>
      <div>
        <label for="rpass">Tvoje heslo</label>
        <input id="rpass" name="password" type="password" autocomplete="current-password">
      </div>
    </div>

    <button class="btn danger" type="submit"
            data-confirm="Opravdu smazat vybraná data? Tohle nejde vzít zpět a bez zálohy je to nenávratné."
            data-confirm-ok="Smazat">Provést reset</button>
  </form>

  <div class="card">
    <h2>Z příkazové řádky</h2>
    <p class="hint" style="margin-top:0">
      Když se do panelu nedostaneš, po SSH ve složce aplikace:
    </p>
    <pre class="mono" style="white-space:pre-wrap"><code>php reset.php --list
php reset.php --scope=activity,commerce
php reset.php --everything</code></pre>
  </div>
