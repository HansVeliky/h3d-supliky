# Plán 39 bodů - stav

Průběžný přehled. Aktualizuje se s každou dávkou, ať je po vyčištění
kontextu jasné, co je hotové a co ne.

**Před každým nahráním:**

```bash
php tests/check.php
```

Zkontroluje syntaxi všech PHP souborů, sestaví a zparsuje klientský balík,
porovná české a anglické řetězce klíč po klíči, prožene generátor geometrie,
vykreslí všech 20 záložek administrace a projede pravidla účtů - všechno
proti odložené databázi. Skončí buď
„VŠE V POŘÁDKU", nebo vypíše, co je rozbité.

## Poznámka k `REFACTOR_REPORT.md`

Ten soubor popisuje opravy, které v kódu **nebyly**. Ověřeno: install.php
takeover neopravený, `isHttps` dál věřil hlavičce, `client.js.php.bak` pořád
v `lib/`. Naopak CSRF a limity přihlašování v pořádku byly (report je hlásil
jako chybějící) a `admin/index.refactored.php` s `modules/router.php` byla
prázdná kulisa, kterou nic nevolalo. Smazáno. Ten report neber jako popis
skutečnosti.

---

## Hotovo

| # | Věc | Poznámka |
|---|-----|----------|
| 0 | **Záchranná síť** `tests/check.php` | 5 sekcí, běží proti odložené databázi |
| 1a | **install.php takeover** | `install_needed()` teď vyžaduje prázdnou tabulku uživatelů, ne jen chybějícího admina. Web, který ztratil admina, se obnovuje přes `php reset.php --list` |
| 1b | **`isHttps` a hlavička proxy** | `X-Forwarded-Proto` se věří jen při `H3D_TRUST_PROXY=1` (stejný přepínač, jaký už hlídá `X-Forwarded-For`). Dvě kopie funkce sloučeny, `Auth::isHttps()` deleguje na `Security` |
| 1c | **Kontrola stejného původu** | Porovnává **host**, ne schéma. Když se odhad http/https netrefil za proxy, vracelo to 403 na každý POST |
| 1d | **Úklid** | Smazán `lib/client.js.php.bak` (243 kB celého klienta) a mrtvá kulisa po předchozím refaktoru |
| 31 | **Údržbový režim** | Brána v `bootstrap.php` už existovala (admin projde, API se zavře, 503 + Retry-After). Doplněno: **registrace se při údržbě zavře** i na serveru, ne jen v šabloně |
| 34 | **Animace údržby** | `lib/maintenance-page.php` podle přiloženého `animace.html`. Žádný externí soubor - stránka musí fungovat, i když je zbytek webu schválně vypnutý. Respektuje `prefers-reduced-motion`, v neaktivní záložce se zastaví |
| 39 | **Přihlášení jen e-mailem** | Dotaz v `Auth::login()` už přezdívku nezná. Přezdívka zůstala jako **zobrazované jméno** u zpráv - smazat ji úplně by znamenalo ukazovat v podpoře plné e-maily |
| 2 | **Zabraná jména** | `reserved_names` v nastavení, editor s bublinami v Účtech. Porovnává se bez diakritiky a velikosti písmen (Spravce = SPRÁVCE). Jméno **není unikátní** - unikátní muselo být, dokud se jím dalo přihlásit |
| 23a | **Registrace zjednodušena** | Formulář se ptá na adresu a heslo, nic víc. Smazán `api/nickname.php` (komukoli bez přihlášení odpovídal, jestli jméno existuje) |
| - | **`tests/accounts.php`** | 21 kontrol chování, napojeno do `check.php` jako sekce 6 |
| 6a | **Administrace rozdělena na moduly** | `admin/index.php` 6051 na 2639 řádků, dvacet záložek v `admin/tabs/{tab}.php`. Ověřeno porovnáním vykresleného HTML před a po: **20/20 shodných** (liší se jen prázdné řádky a cesta k dočasné databázi) |
| 6b | **`$tab` má whitelist** | `ADMIN_TABS` je jediný seznam pro router, menu i kontrolu. Dřív šel `$tab` z URL bez kontroly - dokud vybíral větev, neškodil; jakmile vybírá soubor, je to průchod ven z adresáře |
| 12 | **Nastavení + Systém spojeny** | Jedna skupina, seřazená od toho, co se mění často, k tomu, co se nastaví jednou. Vzhled a Protokol zásahů byly v různých skupinách, obojí přitom nastavení instalace |
| 5 | **Odznak zpráv** | `UserMessages::waitingCount()` počítá **konverzace, kde má poslední slovo zákazník**, ne nepřečtené řádky. Ukecaný zákazník byl dřív pětka |
| 13 | **Odznak objednávek** | `Orders::openCount()` = čekající plus rozpracované. Zaplacené a zrušené se nepočítají, jinak by odznak svítil navždy |
| 14, 15 | **Přehled jako rozcestník** | Panel se otevírá na Přehledu. Nahoře karty co čeká (údržba, objednávky, konverzace, nesoulad v knize) s důvodem a odkazem, pod tím čísla ve čtyřech blocích (Lidé, Exporty, Kredity, Prodej), každý blok je odkaz na svou stránku. Šířka se přizpůsobuje oknu |
| - | **`tests/panel.php`** | 12 kontrol: sémantika obou odznaků, whitelist routeru, soubor ke každé záložce. Sekce 7 v `check.php` |
| 4 | **Hodnocení pomocníka bylo rozbité** | Tři nespárované značky v jedné záložce: `<article>` hodnocení se zavíral jako `</div>`, to samé u řádku konverzace, a `<div class="support-rows">` se nezavíral vůbec - kryl ho právě ten přebytečný `</div>`. Proto se blok roztahoval přes obrazovku |
| - | **`tests/markup.php`** | Počítá párování značek ve všech dvaceti záložkách. **První verze chybu nechytila** - prázdná databáze blok hodnocení vůbec nevykreslí - takže harness teď zakládá zákazníka, konverzaci, tři hodnocení a objednávku, a hlídá i to, že se stránka vůbec vykreslila (fatal nemá nespárované značky, protože nemá žádné). Sekce 8 |
| 36 | **FREE MODE v generátoru** | Přepínač v panelu generátoru. Po rozvržení sloučí sousední boxy do L a T. Sloučení projde, jen když výsledek **není** obdélník, drží se v mezích, je souvislý a nemá díru. Dvě políčka dají obdélník, takže umí pobrat i dva sousedy - jinak by z mřížky jedniček nikdy L nevzniklo. Každý tvar se tvaruje jednou a má strop, jinak z 25 boxů zbyly dva obří kusy |
| 37a | **Animace mřížky** | Klik na +/- rozjede FLIP: boxy dojedou ze starých obdélníků do nových, nová řada nebo sloupec se vfadují. Respektuje systémové vypnutí animací |
| 35 | **Přejmenováno na FREE MODE** | Uživatelské texty v obou jazycích; „Tvary L/T", „Volný režim" a „free-shape" byly tři názvy pro jednu věc |
| - | **Chybové hlášky měly klíč z drátu** | „dh must be between 1 and 500 mm" i v češtině: hláška se skládá z názvu klíče a čísel, takže ji překladová tabulka v `api/export.php` neznala. Pole mají teď lidská jména v obou jazycích a `Layout::parse()` staví hlášku rovnou v jazyce studia |
| - | **Minimální výška = dno + 1 mm** | Server i studio; studio to řekne hned při psaní, ne až po utraceném exportu. Hláška obsahuje obě čísla, protože „musí být menší než výška" nechávalo hádat, co se má změnit |
| - | **Dno si nese minimum výšky s sebou** | `enforceMinHeight()` posouvá spodní mez výšky na dno + 1 mm - pole, posuvník i popisek u jeho levého konce - a výšku, která se pod ni dostane, zvedne. Tažením dna se tedy do neplatného stavu dostat nedá. Načtený projekt se nepřepisuje: limit se u něj jen ukáže, a když je uložená výška menší, nechá se posuvníku dolní mez na nule, jinak by ho prohlížeč utnul a rukojeť by ukazovala výšku, kterou model nemá. **Opravit** teď tuhle chybu umí a nabízí se i u prázdného šuplíku - všechno ostatní, co spravuje, je vlastnost boxů, ale výška je nastavení a je špatně i bez jediného boxu. Ověřeno v prohlížeči: dno na 10 zvedne výšku z 5 na 11; ručně podstrčená výška 4 dá NEPLATNÉ, červenou ikonu a Opravit, po kliku 11 mm a READY |
| - | **Tisková plocha se ukládá** | Ověřeno celou cestou (whitelist v `api/prefs.php`, uložení, načtení, desetiny přežijí). Zafixováno v `tests/panel.php` |
| - | **FREE MODE: stěny mimo tvar** | `midWalls` (střed boxu) a `halfWalls` (jemná 2:1) se počítají z **opsaného obdélníku**, a ten u L pokrývá i políčka, která box nemá - proto po sloučení vystřelily do prázdna. Sloučení je teď zahodí, kresba je u tvarů nekreslí vůbec a `Layout` je u tvaru nepřijme, takže export odpovídá návrhu |
| 4b | **Wall mode vs. generátor** | Přepínač FREE MODE se v režimu příček schová a generátor v něm tvary nedělá. Režim příček stejně tvary převádí zpět na obdélníky, takže by se generovalo něco, co první přepnutí zahodí |
| - | **Denní kredity v nápovědě** | Číslo i strop se berou z nastavení (`H3D_LIMITS`), řádek se ukáže jen když je příděl zapnutý - „denně 0 kreditů" je horší než nic |
| 16d | **Menu administrace vráceno** | Schování Balíčků při vypnutém prodeji byla chyba: promo akce **jsou** balíčky a zakládají se právě tam, takže vypnutí prodeje vzalo stránku, kde se ty balíčky zdarma dělají. Co je na prodej, rozhoduje cena balíčku, ne to, kam se admin dostane |
| 16c | **PROMO zpět, nákup pryč** | Záložka „Koupit kredity" se ukáže, jen když je co koupit - jinak vede do prázdného obchodu. Zůstane i tomu, kdo má nezaplacenou objednávku z dřívějška, aby ji mohl doplatit. **PROMO AKCE je na `purchase_enabled` nezávislá**, takže si uživatel dál sám aktivuje balíčky za 0 (v databázi máš FREE DAY a FREE WEEK). Ověřeno vykreslením účtu v obou stavech |
| 16b | **Free verze** | `purchase_enabled` je vypnuté a je to i nová výchozí hodnota. **Zůstávají promo balíčky za 0, kódy i referral** - ty na `purchase_enabled` schválně navázané nejsou. Placené balíčky server odmítá, v administraci se skupina jmenuje Akce a kódy; Platby, Faktury a Balíčky zůstávají dostupné přes URL, aby staré objednávky nezmizely, a záložka Objednávky se v menu drží, dokud nějaká objednávka existuje |

| - | **Export příček byl rozbitý u obdélníků** | `Geometry.php` si protiřečil: u tvarů staví jeden svařený kus a v komentáři varuje, ať se příčky **nepřidávají jako samostatné hranoly**, u obdélníku přesně to dělalo (`smoothRectDividedMesh` + `appendMesh` bez svaření). Výsledek byl box plus volné hranoly, které se jen o 0,04 mm překrývaly: tiskne se to jako celek, ale „split to parts" příčky rozsype a opravné nástroje ten překryv hlásí jako chybu. Hranatý box jde teď přes svařenou cestu; zaoblený si smooth skořepinu drží dál, protože rastr by z oblouku udělal schody |
| - | **Stěna 0,96 mm místo 2 mm u tvarů** | Ta vada z řezu. `traceLoops` četl **startovní bod smyčky zpátky z klíče** `sprintf('%.5f')`, takže jeden bod každého obrysu byl zaokrouhlený na pět desetinných míst. U šuplíku 250/7 = 35,714285... to bod posunulo o 3 µm mimo rovnou hranu, na které leží - a sousední bod tím přestal být „na přímce". Zůstal jako roh, roh dostane zaoblení a to se vyfrézovalo doprostřed rovné stěny poloměrem R+W. Se sloupci 5 vycházejí souřadnice na pět míst přesně, proto to nikdy nechytily testy s `rows=5`. Startovní bod se teď bere z uložených přesných souřadnic a „leží na přímce?" se posuzuje **vzdáleností v mm**, ne holým vektorovým součinem (ten roste s délkou hran, takže pevná mez 1e-9 na 50mm hraně odpovídá 4e-13 mm) |
| - | **Mitr v `offsetInward` platil jen pro pravý úhel** | `(n1+n2)·d` je správně jen tam, kde jsou normály kolmé. U bodu **na rovné hraně** jsou obě stejné a bod se posune dvakrát dál, než je stěna tlustá - 0,2 mm ven na povrchu, 2,2 mm v dutině, s klínem sbíhajícím k oběma skutečným rohům. Doplněn dělitel `(1 + n1·n2)`, čímž je odsazení přesné pod libovolným úhlem a bod, který proklouzne filtrem, už nic nestojí |
| - | **Zaoblený box + příčky přetekl paměť** | Rastrová mřížka drží čáru pro **každý bod každého obrysu**, a oblouk má bod po pár stupních. U zaobleného obdélníku z toho byly schody, u zaobleného tvaru s několika oblouky se mřížka znásobila, až síť nevlezla do paměti. Zaoblený box - obdélník i tvar - si teď drží hladkou skořepinu a příčky bere jako samostatné objemy; ořezává je `scanSpan` podle **skutečné dutiny** (průsečík přímky příčky s obrysem), takže to funguje i pro L a kříž, kde žádný opsaný obdélník nedává smysl. Hranatý box jde dál svařenou cestou a je to jeden kus |
| - | **`tests/shapes.php`** | 1748 kontrol, 342 tvarů. K L, T, S, U a obdélníku přibyly kříž, schody, vidlička, Z a **osmice z hlášení**, každý s příčkami i bez, přes `Layout::parse()` jako skutečný export. Klíčové je, že se to prohání i po **mřížkách, které nedělí beze zbytku** (5×7, 3×7, 7×7, 4×6) a se zapnutým zaoblením - přesně tam se vada schovávala. Hlídá uzavřenost, žádný přesah mimo buňky, plnou tloušťku stěny a u hranatého boxu **jeden kus**; u zaobleného místo toho, že skořepina má plnou stěnu a příčka nevyleze blíž k vnějšímu líci než na tloušťku stěny. Navrch celý **šuplík z hlášení** buňku po buňce: každý box uzavřený, plná stěna a sousedé drží mezeru 0,400 mm. Tolerance 5 µm kryje tětivu 32úhelníku místo oblouku (klesá šestnáctkrát na každé zdvojení počtu úseků - podpis tětivy, ne tenké stěny). Sekce 9 |
| - | **Předvolby SMTP** | `lib/SmtpPresets.php` a správa v Poště: 28 hotových serverů (Seznam, Centrum, Forpsi, Wedos, Gmail, M365, Outlook, Yahoo, iCloud, Zoho, Fastmail, GMX, WEB.DE, OVH, Proton Bridge, Brevo, SendGrid, Mailgun, Postmark, Mailjet, SES, SMTP2GO, Resend, MailerSend, Elastic, SparkPost, Mailtrap, localhost). Výběr vyplní server, port a zabezpečení; přihlašovací údaje ne, ty patří k účtu. Seznam jde doplňovat i mazat, s návratem k výchozímu |

| - | **Otevřu web a nejsem přihlášený** | Dvě nezávislé příčiny. **1.** `Auth::user()` po dvou hodinách nečinnosti session zahodilo a vrátilo `null`, ale na trvalé přihlášení se nezeptalo - to dělá jen `Auth::start()`, když v session nikoho nenajde. Takže **druhý** požadavek přihlášený byl a první, ten, na který se člověk zrovna díval, ne. Odtud „kliknu na přihlášení a naskočí login" bez zadávání hesla. Trvalá cookie se teď načte hned v tom požadavku, který session zahodil; token zablokovaného, neověřeného nebo prošlého účtu odmítá `restoreRememberedLogin()` dál. **2.** `session.gc_maxlifetime` nebyl nastaven nikde, takže platila výchozí hodnota PHP - **1440 s, tedy 24 minut**, zatímco kód slibuje dvě hodiny. Kdo si nezaškrtl trvalé přihlášení, vypadl po necelé půlhodině, a náhodně, protože úklid je pravděpodobnostní. Nastaveno na `IDLE_TIMEOUT`. Na hostingu se sdíleným adresářem sessions může kratší nastavení souseda smazat soubory i tak - to je otázka vlastního `session.save_path`, ne kódu |
| 38 | **Sdílený odkaz se nejdřív ukáže** | Otevření cizího odkazu dřív spustilo `applyProject()` rovnou a přepsalo, co bylo na ploše. Teď se návrh natáhne, ale nepoužije: otevře se okno, kde jsou **obě plochy vedle sebe** - vlevo šuplík, jak vypadá teď, šipka, vpravo to, čím by se stal - a pod nimi rozměry, počet boxů, mřížka a věta o tom, co převzetí stojí. Kresba se dělá z projektu samotného (šuplík si drží proporce, box se vybarví po buňkách a obrys se táhne jen po hranách bez souseda téhož boxu, takže L vypadá jako jeden kus). **Zavřít** nechá plochu být, **Převzít návrh** ho teprve nasadí a vyhodí `?share=` z adresy - jinak by obnovení stránky hodilo tentýž návrh znovu a smazalo, co na něm mezitím vzniklo. Levá kresba i varování se překreslují z `draw()`, protože uložené rozvržení přihlášeného se dolévá až po otevření okna. Ověřeno: 10 boxů → 64,7 % vybarvení, po smazání na dva → 7,5 %, pravá pořád 64,7 % |
| - | **Tlačítko Kopírovat pod odkazem** | A hláška, která říká pravdu. Okno tvrdilo „odkaz byl zkopírován do schránky" vždycky, i když ji prohlížeč odmítl - na nezabezpečeném původu, při zamítnutém oprávnění nebo ve vestavěném prohlížeči. Kopírování je v `copyShareLink()` (moderní API, fallback přes skryté textarea) a vrací, jestli se povedlo; podle toho okno napíše potvrzení, nebo pokyn zkopírovat tlačítkem. Ikona a nadpis dostaly v obou oknech společný řádek |
| - | **Pravidla na heslo na jednom místě** | `Auth::passwordProblem()`: osm znaků, velké písmeno, malé písmeno, číslice. Do teď je vynucovala jen registrace - reset odkazem, změna hesla na účtu i instalátor chtěly osm znaků a nic víc, takže z resetu mohl vzejít účet slabší, než jaký šlo vůbec založit. Hláška říká **jednu věc k nápravě** („Heslo musí obsahovat číslici.") místo výčtu všech čtyř, a je přeložená; registrační hláška byla anglicky i na české stránce. `tests/accounts.php` hlídá i strukturálně, že se do žádného z těch souborů nevrátí vlastní kontrola délky |

## Zbývá

**Vlna 1 - bezpečnost a účty:** 19 (kontrola instalace), 20 (ochrana
databáze - reálně: šifrovat citlivé sloupce a zálohy, ne celou SQLite),
21 (odinstalace), 22 (instalátor s kompletní identitou webu), 23b (vzhled
přihlašovací a registrační stránky).

**Vlna 2 - administrace:** 3 (účetní kniha - proč se to stalo a jak to
spravit), 7 (stránka uživatele), 16 (Prodej a peníze na jednu stránku),
17 (kontrola denních kreditů plus tlačítko Opravit), 18 (styl Exportů
a kreditů). Zbytek skupiny hotov.

**Vlna 3 - studio a UX:** 10 (průvodce na všech rozlišeních), 11 (texty
pomocníka CZ/EN), 26 (tour), 27 (ochrana menu při zvětšení), 28 (telefon
a tablet), 32 (mizející popupy), 37b (animace přesunu boxu po gridu),
38 (sdílený odkaz v preview módu s převzetím návrhu).

**Vlna 4 - platforma:** 8 (moduly jako pluginy: INSPEKTOR, GENERATOR,
WALL MODE, FREE MODE, BALÍČKY, platební brána), 9 (přepínače vzhledu +
tmavé H3D Ocean, vzhled na účet), 24 (automatické maily), 25 (marketing,
referral při registraci), 29 (CZ/EN všude), 30 (jazyky do samostatných
souborů), 33 (SQL - teorie).

## Rozhodnutí, která stojí za připomenutí

- **24, mazání účtů:** nikdy nemazat účet s kredity nebo nákupem. Varovat po
  12 měsících, mazat po 14, účty se zůstatkem jen anonymizovat na vyžádání.
- **20, ukradená databáze:** šifrovat celou SQLite v PHP nemá smysl, klíč by
  ležel vedle ní. Smysl dává: hesla hashovaná (máme), karty neukládat (máme),
  citlivé sloupce klíčem mimo web root, a hlavně šifrované zálohy.
- **33, Postgres:** bezpečnost nepřidá. SQLite s WAL na tenhle provoz stačí;
  příprava = držet dotazy za `Db::`.
