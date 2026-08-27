<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/View.php';

/**
 * What this site stores in your browser, and what leaves it.
 *
 * The "more information" behind the banner. Every cookie is named with what
 * it is for and how long it lives, because "we use cookies to improve your
 * experience" tells nobody anything. The current answer is shown here too,
 * with a way to change it - the choice has to be as easy to take back as it
 * was to give.
 */

$cs = Lang::current() === 'cs';
$ga = Consent::analyticsId();

page_head($cs ? 'Cookies' : 'Cookies');
?>
<div class="wrap">
  <h1><?= $cs ? 'Cookies a soukromí' : 'Cookies and privacy' ?></h1>
  <p class="lede">
    <?= $cs
      ? 'Krátce: nic o tobě nikam neposílám. Jedinou výjimkou je měření návštěvnosti, a to jen když ho povolíš.'
      : 'In short: nothing about you is sent anywhere. The one exception is traffic measurement, and only if you allow it.' ?>
  </p>

  <div class="card">
    <h2><?= $cs ? 'Tvoje volba' : 'Your choice' ?></h2>
    <?php if (!Consent::needed()): ?>
      <div class="msg ok" style="margin-top:0">
        <?= $cs
          ? 'Web teď nepoužívá žádné měření ani nic dalšího, co by potřebovalo souhlas. Není se tedy na co ptát a žádný pruh se nezobrazuje.'
          : 'The site currently uses no analytics and nothing else that would need consent, so there is nothing to ask about and no banner is shown.' ?>
      </div>
    <?php else: ?>
      <?php $choice = Consent::choice(); ?>
      <p>
        <?php if ($choice === 'all'): ?>
          <span class="tag on"><?= $cs ? 'Měření povoleno' : 'Analytics allowed' ?></span>
        <?php elseif ($choice === 'none'): ?>
          <span class="tag off"><?= $cs ? 'Měření odmítnuto' : 'Analytics refused' ?></span>
        <?php else: ?>
          <span class="tag warn"><?= $cs ? 'Zatím ses nerozhodl' : 'Not answered yet' ?></span>
        <?php endif; ?>
      </p>
      <p class="hint" style="margin-top:0">
        <?= $cs
          ? 'Volbu můžeš kdykoli změnit, klidně oběma směry. Uloží se do cookie h3d_consent a nic víc se s ní neděje.'
          : 'You can change this at any time, either way. It is stored in the h3d_consent cookie and nothing else happens with it.' ?>
      </p>
      <!-- The same two buttons as in the banner, same weight, either way. -->
      <div class="row" style="gap:8px;flex-wrap:wrap">
        <button class="btn" type="button" data-consent="none" data-consent-reload="1">
          <?= $cs ? 'Odmítnout měření' : 'Refuse analytics' ?>
        </button>
        <button class="btn" type="button" data-consent="all" data-consent-reload="1">
          <?= $cs ? 'Povolit měření' : 'Allow analytics' ?>
        </button>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2><?= $cs ? 'Nutné cookies' : 'Necessary cookies' ?></h2>
    <p class="hint" style="margin-top:0">
      <?= $cs
        ? 'Bez nich web nefunguje, takže se na ně neptám - odmítnout je znamená web nepoužívat. Zůstávají v tvém prohlížeči a na server chodí jen zpátky sem.'
        : 'The site does not work without these, so they are not up for consent - refusing them means not using the site. They stay in your browser and are only ever sent back here.' ?>
    </p>
    <div class="table-scroll">
      <table>
        <thead><tr>
          <th><?= $cs ? 'Název' : 'Name' ?></th>
          <th><?= $cs ? 'K čemu' : 'What for' ?></th>
          <th><?= $cs ? 'Jak dlouho' : 'How long' ?></th>
        </tr></thead>
        <tbody>
          <tr>
            <td class="mono">PHPSESSID</td>
            <td><?= $cs ? 'Drží běžné přihlášení a rozdělanou objednávku.' : 'Keeps the normal sign-in session and an order in progress.' ?></td>
            <td><?= $cs ? 'do zavření prohlížeče' : 'until you close the browser' ?></td>
          </tr>
          <tr>
            <td class="mono">h3d_remember</td>
            <td><?= $cs ? 'Pokud při přihlášení zaškrtneš „Zůstat přihlášen 30 dní“, tento zabezpečený cookie umožní automatické přihlášení i po zavření prohlížeče.' : 'If you tick “Stay signed in for 30 days” when signing in, this secure cookie keeps you signed in automatically even after closing the browser.' ?></td>
            <td><?= $cs ? '30 dní' : '30 days' ?></td>
          </tr>
          <tr>
            <td class="mono">h3d_consent</td>
            <td><?= $cs ? 'Tvoje odpověď na otázku o měření - aby se neptala pořád dokola.' : 'Your answer about analytics, so the question is not asked again.' ?></td>
            <td><?= $cs ? '1 rok' : '1 year' ?></td>
          </tr>
          <tr>
            <td class="mono">h3d_lang, h3d_unit</td>
            <td><?= $cs ? 'Jazyk a jednotky (mm/palce), aby web vypadal stejně i příště.' : 'Language and units, so the site looks the same next time.' ?></td>
            <td><?= $cs ? '1 rok' : '1 year' ?></td>
          </tr>
        </tbody>
      </table>
    </div>
    <p class="hint" style="margin-bottom:0">
      <?= $cs
        ? 'Při přihlášení si můžeš zvolit „Zůstat přihlášen 30 dní“. V takovém případě se uloží zabezpečený cookie h3d_remember s platností 30 dní. Bez zaškrtnutí této volby se toto trvalé přihlášení nevytvoří.'
        : 'When signing in, you can choose “Stay signed in for 30 days”. In that case, a secure h3d_remember cookie is stored for 30 days. If you do not tick this option, no persistent sign-in is created.' ?>
    </p>
  </div>

  <div class="card">
    <h2><?= $cs ? 'Měření návštěvnosti' : 'Traffic measurement' ?></h2>
    <?php if ($ga === ''): ?>
      <p><?= $cs ? 'Teď se nic neměří - žádné analytické cookies se nenastavují.' : 'Nothing is measured at the moment; no analytics cookies are set.' ?></p>
    <?php else: ?>
      <p>
        <?= $cs
          ? 'Používám Google Analytics, a je to jediná služba, které se odsud něco posílá: jaká stránka se otevřela, odkud jsi přišel, přibližná poloha podle IP (zkrácené) a typ zařízení. Neposílám tam e-mail, jméno, obsah tvého návrhu ani nic z objednávek.'
          : 'I use Google Analytics, and it is the only service anything is sent to: which page was opened, where you came from, a rough location from a shortened IP, and the kind of device. No e-mail, no name, nothing from your design or your orders.' ?>
      </p>
      <p class="hint">
        <?= $cs
          ? 'Reklamní ani personalizační funkce jsou vypnuté (consent mode: ad_storage a personalizace zamítnuty), IP se zkracuje. Bez tvého souhlasu se skript vůbec nenačte - není to tak, že by běžel a jen se tvářil, že neměří.'
          : 'Advertising and personalisation are switched off (consent mode: ad_storage and personalisation denied) and the IP is truncated. Without your consent the script is not loaded at all - it is not running quietly in the background.' ?>
      </p>
      <div class="table-scroll">
        <table>
          <thead><tr>
            <th><?= $cs ? 'Název' : 'Name' ?></th>
            <th><?= $cs ? 'K čemu' : 'What for' ?></th>
            <th><?= $cs ? 'Jak dlouho' : 'How long' ?></th>
          </tr></thead>
          <tbody>
            <tr>
              <td class="mono">_ga, _ga_*</td>
              <td><?= $cs ? 'Google Analytics: rozliší opakovanou návštěvu od nové.' : 'Google Analytics: tells a returning visit from a new one.' ?></td>
              <td><?= $cs ? '2 roky' : '2 years' ?></td>
            </tr>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </div>

  <div class="card">
    <h2><?= $cs ? 'Co zůstává jen u tebe v prohlížeči' : 'What stays in your browser only' ?></h2>
    <p class="hint" style="margin-top:0">
      <?= $cs
        ? 'Tohle nejsou cookies a na server se to nikdy neposílá - je to úložiště prohlížeče, ze kterého čte jen studio na tomhle počítači.'
        : 'These are not cookies and are never sent to the server - they are browser storage that only the studio on this computer reads.' ?>
    </p>
    <ul class="hint" style="margin:0;padding-left:18px">
      <li><span class="mono">h3d_studio</span> - <?= $cs ? 'rozpracovaný návrh šuplíku, ať o něj nepřijdeš při zavření záložky' : 'the drawer you are working on, so closing the tab does not lose it' ?></li>
      <li><span class="mono">honza3d-lang, -unit</span> - <?= $cs ? 'stejná nastavení jako v cookies, aby studio nabíhalo bez bliknutí' : 'the same settings as the cookies, so the studio starts without a flash' ?></li>
      <li><span class="mono">honza3d-onboarded</span> - <?= $cs ? 'že jsi už viděl uvítací obrazovku' : 'that you have already seen the welcome screen' ?></li>
    </ul>
  </div>

  <div class="card">
    <h2><?= $cs ? 'Když si to rozmyslíš' : 'If you change your mind' ?></h2>
    <p style="margin-top:0">
      <?= $cs
        ? 'Vrať se sem a klikni na druhé tlačítko. Cookies můžeš taky kdykoli smazat přímo v prohlížeči - web bude fungovat dál, jen se zase zeptá a zapomene zvolený jazyk a jednotky.'
        : 'Come back here and click the other button. You can also delete the cookies in your browser at any time - the site keeps working, it will just ask again and forget your language and units.' ?>
    </p>
    <a class="btn" href="index.php"><?= $cs ? 'Zpět do studia' : 'Back to the studio' ?></a>
  </div>
</div>
<?php page_foot();
