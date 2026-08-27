<?php
/**
 * Administration tab: mail
 *
 * Lifted out of admin/index.php, which had all twenty of these in one
 * 6000-line file. Included from there with the surrounding scope intact,
 * so $tab, $ok, $error, the helpers and everything else the page sets up
 * are available exactly as before.
 */
if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }

    $log = $pdo->query('SELECT * FROM mail_log ORDER BY id DESC LIMIT 40')->fetchAll();
    $unverified = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE verified_at IS NULL')->fetchColumn();
?>
  <form method="post" class="card">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="settings">
    <input type="hidden" name="_flags" value="smtp_enabled,require_verify,notify_admin,captcha_enabled">

    <fieldset>
      <legend>SMTP</legend>
      <label class="check">
        <input type="checkbox" name="smtp_enabled" value="1" <?= Settings::bool('smtp_enabled') ? 'checked' : '' ?>>
        Odesílat e-maily
      </label>
      <div class="hint" style="margin-bottom:12px">
        Když je odesílání vypnuté nebo nenastavené, aplikace funguje dál -
        jen se nic neodešle a účty se musí ověřit ručně v Uživatelích.
        Všechny zprávy zákazníkům jsou anglicky.
      </div>

      <?php $presets = SmtpPresets::all(); ?>
      <?php if ($presets): ?>
      <div class="smtp-preset">
        <label for="smtpPreset">Předvolba serveru</label>
        <select id="smtpPreset">
          <option value="">Vlastní nastavení</option>
          <?php foreach ($presets as $ps): ?>
            <option value="<?= e($ps['id']) ?>"
                    data-host="<?= e($ps['host']) ?>"
                    data-port="<?= (int) $ps['port'] ?>"
                    data-security="<?= e($ps['security']) ?>"
                    data-note="<?= e($ps['note']) ?>"
                    <?= Settings::get('smtp_host') === $ps['host'] ? 'selected' : '' ?>><?= e($ps['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <div class="hint" id="smtpPresetNote">
          Vyplní jen server, port a zabezpečení. Jméno a heslo patří k účtu, ne k serveru.
        </div>
      </div>
      <?php endif; ?>

      <div class="grid2">
        <div>
          <label for="smtp_host">Server</label>
          <input id="smtp_host" name="smtp_host" type="text" placeholder="smtp.seznam.cz"
                 value="<?= e(Settings::get('smtp_host')) ?>">
        </div>
        <div>
          <label for="smtp_port">Port</label>
          <input id="smtp_port" name="smtp_port" type="number" min="1" max="65535"
                 value="<?= Settings::int('smtp_port') ?>">
          <div class="hint">587 pro STARTTLS, 465 pro SSL, 25 bez šifrování.</div>
        </div>
      </div>

      <div class="grid2">
        <div>
          <label for="smtp_security">Zabezpečení</label>
          <select id="smtp_security" name="smtp_security">
            <?php foreach (['tls' => 'STARTTLS (port 587)', 'ssl' => 'SSL/TLS (port 465)', 'none' => 'Žádné'] as $k => $v): ?>
              <option value="<?= $k ?>" <?= Settings::get('smtp_security') === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label for="smtp_timeout">Timeout (sekundy)</label>
          <input id="smtp_timeout" name="smtp_timeout" type="number" min="3" max="60"
                 value="<?= Settings::int('smtp_timeout') ?>">
        </div>
      </div>

      <div class="grid2">
        <div>
          <label for="smtp_user">Uživatel</label>
          <input id="smtp_user" name="smtp_user" type="text" autocomplete="off"
                 value="<?= e(Settings::get('smtp_user')) ?>">
          <div class="hint">Prázdné = bez přihlášení.</div>
        </div>
        <div>
          <label for="smtp_pass">Heslo</label>
          <input id="smtp_pass" name="smtp_pass" type="password" autocomplete="new-password"
                 value="<?= e(Settings::get('smtp_pass')) ?>">
        </div>
      </div>

      <div class="grid2">
        <div>
          <label for="smtp_from">Odesílatel (adresa)</label>
          <input id="smtp_from" name="smtp_from" type="email" placeholder="noreply@honza3d.cz"
                 value="<?= e(Settings::get('smtp_from')) ?>">
        </div>
        <div>
          <label for="smtp_from_name">Odesílatel (jméno)</label>
          <input id="smtp_from_name" name="smtp_from_name" type="text"
                 value="<?= e(Settings::get('smtp_from_name')) ?>">
        </div>
      </div>
      <div class="hint">
        Adresa odesílatele by měla patřit ke stejné doméně jako SMTP účet,
        jinak zprávy skončí ve spamu nebo je server odmítne.
      </div>
    </fieldset>

    <fieldset>
      <legend>Kopie pro tebe</legend>
      <div class="grid2">
        <div>
          <label for="admin_email">Tvoje adresa</label>
          <input id="admin_email" name="admin_email" type="email" placeholder="jan@honza3d.cz"
                 value="<?= e(Settings::get('admin_email')) ?>">
        </div>
        <div style="align-self:end">
          <label class="check">
            <input type="checkbox" name="notify_admin" value="1" <?= Settings::bool('notify_admin') ? 'checked' : '' ?>>
            Posílat kopii každé zprávy zákazníkovi
          </label>
        </div>
      </div>
      <div class="hint">
        Kopie chodí jako samostatná zpráva s adresou zákazníka v předmětu, ne
        jako skrytá kopie. Když selže doručení kopie, zákazníkova zpráva to
        neovlivní.
      </div>
    </fieldset>

    <fieldset>
      <legend>Ověřování účtů</legend>
      <label class="check">
        <input type="checkbox" name="require_verify" value="1" <?= Settings::bool('require_verify') ? 'checked' : '' ?>>
        Vyžadovat ověření e-mailu
      </label>
      <label class="check">
        <input type="checkbox" name="captcha_enabled" value="1" <?= Settings::bool('captcha_enabled') ? 'checked' : '' ?>>
        Ochrana registrace proti robotům
      </label>
      <div class="hint">
        Prohlížeč musí vyřešit početní úlohu (proof of work). Žádné obrázky
        ani cizí služba - počítá se u návštěvníka a ověřuje se tady. Trvá to
        zlomek sekundy, ale robota to stojí stejný čas při každém pokusu.
        Doplňuje to skrytá past ve formuláři a minimální doba vyplňování.
      </div>
      <div class="hint">
        Účet funguje hned po registraci, ale bonus za registraci
        (<?= e(Cred::fmtCs(Settings::int('signup_bonus'))) ?> kreditů<?php
          $sbd = Settings::int('signup_bonus_days');
          echo $sbd > 0 ? ' + ' . e(Orders::durationLabel($sbd, 'cs')) . ' neomezených exportů' : '';
        ?>) se připíše až po kliknutí na odkaz v e-mailu. Výše se mění v Nastavení.
        <br>Změna se týká jen účtů založených potom: každý účet si při registraci
        uloží, co mu bylo slíbeno, a dostane přesně to.
        <?php if ($unverified > 0): ?>
          <br><strong>Neověřených účtů: <?= $unverified ?></strong>
        <?php endif; ?>
      </div>
    </fieldset>

  </form>

  <div class="card">
    <h2>Zkušební odeslání</h2>
    <?php if (!Mailer::enabled()): ?>
      <div class="msg warn">
        Odesílání je vypnuté nebo chybí server a adresa odesílatele. Nastavení
        nejdřív ulož, teprve pak má smysl testovat.
      </div>
    <?php endif; ?>
    <form method="post" class="inline-form">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="test_mail">
      <input type="email" name="test_to" placeholder="tvuj@email.cz" required
             value="<?= e((string) $admin['email']) ?>">
      <button class="btn primary" type="submit">Odeslat zkušební zprávu</button>
    </form>
    <div class="hint">
      Skutečně se připojí k serveru a odešle. Chyba se vypíše celá, včetně
      odpovědi serveru - to je obvykle to, co problém prozradí.
    </div>
  </div>

  <div class="card" style="border:2px solid #d97706">
    <h2>🔥 HARDCORE diagnostika ověřovacího e-mailu</h2>
    <p class="hint">Toto není obyčejný SMTP test. Spustí <strong>přesně Verify::sendLink()</strong>, tedy stejnou cestu jako tlačítko „Odeslat ověřovací e-mail“ na veřejné stránce. Zapisuje kompletní SMTP průběh do <code>data/mail-debug.log</code>. Heslo SMTP se do logu nikdy nezapisuje.</p>
    <form method="post" class="inline-form">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="hardcore_verify_test">
      <input type="email" name="hardcore_email" placeholder="email existujícího účtu" required>
      <button class="btn primary" type="submit">Spustit HARDCORE VERIFY TEST</button>
    </form>
    <?php if (isset($report) && $report !== ''): ?>
      <pre style="margin-top:14px;max-height:520px;overflow:auto;white-space:pre-wrap;background:#111827;color:#e5e7eb;padding:14px;border-radius:10px;font:12px/1.5 ui-monospace,SFMono-Regular,Consolas,monospace"><?= e($report) ?></pre>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2>Poslední SMTP debug log</h2>
    <p class="hint">Poslední diagnostické běhy. Pokud veřejné ověření selže, jeho průběh bude tady.</p>
    <pre style="max-height:520px;overflow:auto;white-space:pre-wrap;background:#111827;color:#e5e7eb;padding:14px;border-radius:10px;font:12px/1.5 ui-monospace,SFMono-Regular,Consolas,monospace"><?= e(Mailer::debugLogTail()) ?></pre>
  </div>

  <div class="card">
    <h2>Odeslané zprávy</h2>
    <?php if (!$log): ?>
      <p class="hint">Zatím nic.</p>
    <?php else: ?>
      <div class="table-scroll">
        <table>
          <thead><tr><th>Kdy</th><th>Příjemce</th><th>Typ</th><th>Předmět</th><th>Výsledek</th></tr></thead>
          <tbody>
          <?php foreach ($log as $m): ?>
            <tr>
              <td><?= e(when((int) $m['created_at'])) ?></td>
              <td><?= e($m['recipient']) ?></td>
              <td><span class="tag"><?= e($m['kind']) ?></span></td>
              <td><?= e($m['subject']) ?></td>
              <td>
                <?php if ((int) $m['ok'] === 1): ?>
                  <span class="tag on">odesláno</span>
                <?php else: ?>
                  <span class="tag bad">chyba</span>
                  <div class="hint"><?= e((string) $m['error']) ?></div>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

<!--
  The list of ready-made servers.

  Kept out of the settings form above on purpose: that form saves the mail
  configuration, this one edits a list. Sharing one submit button would mean
  adding a server also saves half-typed credentials.
-->
<div class="card">
  <h2>Předvolby SMTP serverů</h2>
  <p class="hint" style="margin-top:0">
    Vyplní se z nich server, port a zabezpečení. Přihlašovací údaje ne - ty
    patří k účtu, ne k serveru. Seznam je tvůj: co nepoužiješ, smaž; co ti
    chybí, přidej.
  </p>

  <div class="table-scroll">
    <table>
      <thead><tr><th>Název</th><th>Server</th><th class="num">Port</th><th>Zabezpečení</th><th>Poznámka</th><th></th></tr></thead>
      <tbody>
      <?php foreach (SmtpPresets::all() as $ps): ?>
        <tr>
          <td><strong><?= e($ps['label']) ?></strong></td>
          <td><code><?= e($ps['host']) ?></code></td>
          <td class="num"><?= (int) $ps['port'] ?></td>
          <td><?= e(['tls' => 'STARTTLS', 'ssl' => 'SSL/TLS', 'none' => 'žádné'][$ps['security']] ?? $ps['security']) ?></td>
          <td class="hint"><?= e($ps['note']) ?></td>
          <td class="num">
            <form method="post" style="margin:0">
              <?= Auth::csrfField() ?>
              <input type="hidden" name="action" value="smtp_preset_remove">
              <input type="hidden" name="preset_id" value="<?= e($ps['id']) ?>">
              <button class="btn small" type="submit"
                      data-confirm="Odebrat předvolbu <?= e($ps['label']) ?>? Nastavení pošty se tím nemění."
                      data-confirm-ok="Odebrat">Odebrat</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <form method="post" style="margin-top:16px">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="smtp_preset_add">
    <h3 style="margin-bottom:6px">Přidat server</h3>
    <div class="grid2">
      <div>
        <label for="preset_label">Název</label>
        <input id="preset_label" name="preset_label" type="text" maxlength="60" placeholder="Můj hosting" required>
      </div>
      <div>
        <label for="preset_host">Server</label>
        <input id="preset_host" name="preset_host" type="text" maxlength="120" placeholder="smtp.mojedomena.cz" required>
      </div>
      <div>
        <label for="preset_port">Port</label>
        <input id="preset_port" name="preset_port" type="number" min="1" max="65535" value="587">
      </div>
      <div>
        <label for="preset_security">Zabezpečení</label>
        <select id="preset_security" name="preset_security">
          <option value="tls">STARTTLS (obvykle 587)</option>
          <option value="ssl">SSL/TLS (obvykle 465)</option>
          <option value="none">Žádné (25)</option>
        </select>
      </div>
    </div>
    <label for="preset_note">Poznámka</label>
    <input id="preset_note" name="preset_note" type="text" maxlength="200"
           placeholder="Třeba: jméno je celá adresa, heslo z administrace hostingu">
    <div class="row" style="gap:8px;margin-top:12px;flex-wrap:wrap">
      <button class="btn primary" type="submit">Přidat</button>
    </div>
  </form>

  <form method="post" style="margin-top:10px">
    <?= Auth::csrfField() ?>
    <input type="hidden" name="action" value="smtp_preset_reset">
    <button class="btn" type="submit"
            data-confirm="Vrátit seznam do stavu, ve kterém je dodáván? Tvoje vlastní předvolby se ztratí."
            data-confirm-ok="Vrátit seznam">Vrátit výchozí seznam</button>
    <div class="hint" style="margin-top:6px">
      Vrátí i ty předvolby, které jsi odebral.
    </div>
  </form>
</div>
