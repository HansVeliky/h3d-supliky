<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';
require_once __DIR__ . '/lib/View.php';

/**
 * Terms of use.
 *
 * Written to be read, not to be survived: short paragraphs, plain sentences,
 * and no clause that says something the site does not actually do. The part
 * about selling prints is deliberately a request rather than a ban - it is
 * what the author actually wants, and dressing a wish up as a prohibition
 * would be a lie in a document whose whole job is to be trusted.
 */

$cs = Lang::current() === 'cs';

page_head($cs ? 'Podmínky použití' : 'Terms of use');
?>
<div class="wrap">
  <h1><?= $cs ? 'Podmínky použití' : 'Terms of use' ?></h1>
  <p class="lede">
    <?= $cs
      ? 'Krátce a bez právničiny: navrhni si organizér, stáhni si model, vytiskni. Model je tvůj. Prosba místo zákazu je níž.'
      : 'Short, and without the legalese: design an organiser, download the model, print it. The model is yours. The request instead of a ban is below.' ?>
  </p>

  <div class="card">
    <h2><?= $cs ? 'Co ti web dává' : 'What the site gives you' ?></h2>
    <p style="margin-top:0">
      <?= $cs
        ? 'Studio ti spočítá a vygeneruje 3D model podle rozměrů, které zadáš, a nabídne ho ke stažení jako 3MF nebo STL. Vytisknutý díl je tvůj: můžeš si ho vytisknout kolikrát chceš, upravit ho, rozdat ho.'
        : 'The studio builds a 3D model from the measurements you enter and hands it to you as a 3MF or an STL. The printed part is yours: print it as many times as you like, change it, give it away.' ?>
    </p>
    <p>
      <?= $cs
        ? 'Model vzniká z tvého zadání. Neukládám si tvoje návrhy nikam jinam než do tvého vlastního profilu, abys je našel, až se vrátíš.'
        : 'The model comes from what you typed. Your designs are not stored anywhere but in your own profile, so that they are there when you come back.' ?>
    </p>
  </div>

  <div class="card">
    <h2><?= $cs ? 'Prosba, ne zákaz' : 'A request, not a ban' ?></h2>
    <p style="margin-top:0">
      <?= $cs
        ? 'Kdybys chtěl výtisky prodávat nebo generátor použít komerčně: nebráním ti v tom a nebudu to hlídat. Poprosím tě ale o dvě věci - napiš u toho, odkud model je (Honza3D Studio), a když ti to bude vydělávat, dej mi vědět. Udělá mi to radost a možná z toho vznikne něco dalšího.'
        : 'If you want to sell your prints, or use the generator commercially: I am not stopping you and I will not be policing it. I would ask two things - say where the model came from (Honza3D Studio), and if it starts making money, drop me a line. It would make my day, and something more may come of it.' ?>
    </p>
    <p class="hint" style="margin-bottom:0">
      <?= $cs
        ? 'Co naopak nechci: vydávat generátor za svůj, prodávat přístup k němu nebo ho kopírovat i s kódem.'
        : 'What I would rather you did not: pass the generator off as your own, sell access to it, or copy it code and all.' ?>
    </p>
  </div>

  <div class="card">
    <h2><?= $cs ? 'Účet, kredity a exporty' : 'Account, credits and exports' ?></h2>
    <p style="margin-top:0">
      <?= $cs
        ? 'Bez přihlášení máš omezený počet volných exportů. Účet ti dá víc a odemkne generátor, tvarované boxy, příčky a ukládání návrhu do profilu. Kredity se dají koupit nebo uplatnit kódem; co za kredit dostaneš, je vždy napsané u exportu předem.'
        : 'Without an account you get a limited number of free exports. An account gives you more, and unlocks the generator, dividers and keeping your design in your profile. Credits can be bought or redeemed with a code; what a credit buys is always stated before you spend it.' ?>
    </p>
    <p>
      <?= $cs
        ? 'Nevyužité kredity nepropadají. Když s účtem skončíš, napiš mi a smažu ho i s tím, co je v něm uložené.'
        : 'Unused credits do not expire. When you are done with the account, write to me and it goes, along with everything stored in it.' ?>
    </p>
  </div>

  <div class="card">
    <h2><?= $cs ? 'Za co neručím' : 'What I cannot promise' ?></h2>
    <p style="margin-top:0">
      <?= $cs
        ? 'Model se staví ve skutečných milimetrech, ale tiskárna, materiál i smrštění jsou na tvé straně. Změř si šuplík a udělej si zkušební tisk jednoho malého boxu, než pustíš celou sadu - ušetří to plast i čas. Za nepovedený tisk ani za rozměr, který nesedl, ručit nemůžu.'
        : 'The model is built in real millimetres, but the printer, the material and the shrinkage are on your side of the cable. Measure the drawer and print one small box as a test before you commit to a whole set - it saves plastic and time. I cannot take responsibility for a failed print or a size that did not fit.' ?>
    </p>
    <p>
      <?= $cs
        ? 'Web běží, jak nejlíp umím, ale je to jednočlenný projekt: výpadek nebo chyba se stát můžou. Když na něco narazíš, napiš mi - spravím to.'
        : 'The site runs as well as I can make it, but this is a one-person project: an outage or a bug can happen. If you hit one, tell me and I will fix it.' ?>
    </p>
  </div>

  <div class="card">
    <h2><?= $cs ? 'Změny' : 'Changes' ?></h2>
    <p style="margin-top:0">
      <?= $cs
        ? 'Když se tyhle podmínky změní, platí pro to, co uděláš potom. Co ti bylo slíbeno při registraci nebo při nákupu, platí tak, jak to bylo slíbeno tehdy.'
        : 'If these terms change, the new ones apply to what you do afterwards. Whatever was promised to you when you registered or bought something stands as it was promised then.' ?>
    </p>
    <p class="hint" style="margin-bottom:0">
      <?= $cs ? 'Naposledy upraveno: ' : 'Last updated: ' ?><?= date('j. n. Y', filemtime(__FILE__) ?: time()) ?>
      &middot; <?= $cs ? 'verze webu' : 'site version' ?> <?= e(H3D_VERSION) ?>
    </p>
  </div>

  <div class="card">
    <h2><?= $cs ? 'Kontakt' : 'Contact' ?></h2>
    <p style="margin-top:0">
      <?= $cs
        ? 'Cokoli k podmínkám, k účtu nebo k tomu, co ti nesedí: napiš na adresu v patičce webu.'
        : 'Anything about the terms, your account, or something that does not add up: write to the address in the site footer.' ?>
    </p>
    <a class="btn" href="index.php"><?= $cs ? 'Zpět do studia' : 'Back to the studio' ?></a>
  </div>
</div>
<?php page_foot();
