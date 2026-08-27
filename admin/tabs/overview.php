<?php
/**
 * Administration tab: overview
 *
 * Lifted out of admin/index.php, which had all twenty of these in one
 * 6000-line file. Included from there with the surrounding scope intact,
 * so $tab, $ok, $error, the helpers and everything else the page sets up
 * are available exactly as before.
 */
if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }

    // Three passes over three tables instead of six separate counts: every
    // figure a table can answer is asked for in one go. It reads the same,
    // but the panel stops walking the ledger and the export log twice each.
    $day = time() - 86400;

    // "Utracené" means credits a customer actually used up, which is exports
    // and nothing else. Credits clawed back when an order is refunded or an
    // admin undoes a grant are negative movements too, but counting them as
    // spending made a cancelled order look like turnover twice over: once as
    // issued, once as spent.
    $led = $pdo->query(
        "SELECT COALESCE(SUM(CASE WHEN delta > 0 THEN delta END), 0) AS gained,
                COALESCE(SUM(CASE WHEN delta < 0 AND reason = 'export' THEN -delta END), 0) AS spent,
                COALESCE(SUM(CASE WHEN delta < 0 AND reason <> 'export' THEN -delta END), 0) AS taken
         FROM ledger"
    )->fetch(PDO::FETCH_ASSOC) ?: ['gained' => 0, 'spent' => 0, 'taken' => 0];
    $exp = $pdo->query(
        'SELECT COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN created_at > ' . $day . ' THEN 1 END), 0) AS today
         FROM exports'
    )->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'today' => 0];

    /*
     * The overview is where the panel opens, so it is built to be left
     * again: every number is a link to the page that explains it, and
     * anything actually waiting for a human is at the top.
     */
    $waiting = [];
    if (Settings::bool('maintenance_mode')) {
        $waiting[] = ['reset', 'Web je pozastaven kvůli údržbě', 'Návštěvníci vidí údržbovou stránku a registrace je zavřená.', true];
    }
    if ($adminOpenOrders > 0) {
        $waiting[] = ['orders', $adminOpenOrders . ' ' . ($adminOpenOrders === 1 ? 'objednávka čeká' : ($adminOpenOrders < 5 ? 'objednávky čekají' : 'objednávek čeká')),
                      'Přijmout, nebo počkat na zaplacení.', false];
    }
    if ($adminUnreadMessages > 0) {
        $waiting[] = ['messages', $adminUnreadMessages . ' ' . ($adminUnreadMessages === 1 ? 'konverzace čeká' : ($adminUnreadMessages < 5 ? 'konverzace čekají' : 'konverzací čeká')),
                      'Poslední slovo má zákazník.', false];
    }
    $mismatch = Credits::audit();
    if ($mismatch) {
        $waiting[] = ['overview', count($mismatch) . ' účtů nesedí v účetní knize',
                      'Rozdíl mezi zůstatkem a součtem pohybů. Podrobnosti níž.', true];
    }

    /*
     * Numbers in blocks by what they are about, each block leading to the
     * page it comes from. One flat grid of eight figures said everything and
     * explained nothing: turnover sat next to the number of active codes.
     */
    $usersTotal  = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    $usersNew    = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE created_at > ' . ($day - 6 * 86400))->fetchColumn();
    $usersUnver  = (int) $pdo->query('SELECT COUNT(*) FROM users WHERE verified_at IS NULL')->fetchColumn();
    $codesActive = (int) $pdo->query('SELECT COUNT(*) FROM codes WHERE active = 1')->fetchColumn();

    $blocks = [
        ['Lidé', 'users', [
            ['Účtů celkem',        (string) $usersTotal],
            ['Nových za 7 dní',    (string) $usersNew],
            ['Neověřených adres',  (string) $usersUnver],
        ]],
        ['Exporty', 'exports', [
            ['Exportů celkem',     (string) (int) $exp['total']],
            ['Za posledních 24 h', (string) (int) $exp['today']],
            ['Utracené kredity',   Cred::fmtCs((int) $led['spent'])],
        ]],
        ['Kredity', 'exports', [
            ['Vydané',             Cred::fmtCs((int) $led['gained'])],
            ['Vrácené a opravené', Cred::fmtCs((int) $led['taken'])],
            ['V oběhu',            Cred::fmtCs((int) $led['gained'] - (int) $led['spent'] - (int) $led['taken'])],
        ]],
        ['Prodej', 'orders', [
            ['Objednávek čeká',    (string) $adminOpenOrders],
            ['Aktivní kódy',       (string) $codesActive],
            ['Balíčky',            'Nastavit'],
        ]],
    ];

    // The exposure check makes real HTTP requests, so it is opt-in rather
    // than something that runs on every page load.
    $checks = isset($_GET['scan']) ? SelfCheck::run(originUrl()) : null;
?>
  <?php if ($waiting): ?>
    <div class="ov-waiting">
      <?php foreach ($waiting as [$target, $title, $why, $urgent]): ?>
        <a class="ov-card ov-card-act<?= $urgent ? ' is-urgent' : '' ?>" href="?tab=<?= e($target) ?>">
          <strong><?= e($title) ?></strong>
          <span><?= e($why) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php else: ?>
    <div class="msg ok" style="margin-bottom:16px">Nic nečeká na vyřízení.</div>
  <?php endif; ?>

  <div class="ov-blocks">
    <?php foreach ($blocks as [$title, $target, $rows]): ?>
      <a class="ov-card" href="?tab=<?= e($target) ?>">
        <h3><?= e($title) ?></h3>
        <?php foreach ($rows as [$label, $value]): ?>
          <div class="ov-row"><span><?= e($label) ?></span><b><?= e($value) ?></b></div>
        <?php endforeach; ?>
      </a>
    <?php endforeach; ?>
  </div>

  <p class="hint" style="margin:10px 2px 0">
    Utracené jsou jen kredity odečtené za export. Kredity stažené zpět při
    zrušené nebo refundované objednávce a opravy od správce jsou vedle,
    ve „vrácených a opravených" - jinak by jedna zrušená objednávka
    vypadala jako obrat dvakrát. Kredity v oběhu jsou to, co mají účty
    právě teď na zůstatku.
  </p>
  <div class="card ledger-audit-card" style="margin-top:16px">
    <h2>Kontrola účetní knihy</h2>
    <?php if (!$mismatch): ?>
      <div class="msg ok">Účetní zůstatky i posloupnost pohybů jsou v pořádku.</div>
    <?php else: ?>
      <div class="msg err">
        <?= count($mismatch) ?> účtů obsahuje nesoulad zůstatku nebo chybu v historické posloupnosti pohybů.
      </div>
      <p class="hint">Kontrola porovnává aktuální zůstatek se součtem účetní knihy a současně ověřuje, že každý zapsaný <code>balance_after</code> odpovídá předchozímu zůstatku plus pohybu. Opravný záznam nemění zůstatek účtu ani nemaže původní historii.</p>
      <table>
        <thead><tr><th>Uživatel</th><th class="num">Zůstatek</th><th class="num">Součet pohybů</th><th class="num">Rozdíl</th><th>Kontrola historie</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($mismatch as $m): ?>
          <tr>
            <td><?= e($m['email']) ?></td>
            <td class="num"><?= e(Cred::fmtCs((int) $m['credits'])) ?></td>
            <td class="num"><?= e(Cred::fmtCs((int) $m['ledger_sum'])) ?></td>
            <td class="num"><?= e(($m['mismatch'] >= 0 ? '+' : '-') . Cred::fmtCs(abs((int) $m['mismatch']))) ?></td>
            <td>
              <?php if ((int) $m['chain_errors'] > 0): ?>
                <strong><?= (int) $m['chain_errors'] ?> historických chyb</strong>
                <?php $ce = $m['first_chain_error']; ?>
                <?php if ($ce): ?>
                  <small class="hint ledger-audit-detail">Řádek #<?= (int) $ce['id'] ?>: pohyb <?= e(Cred::fmtCs((int) $ce['delta'])) ?>; očekáváno <?= e(Cred::fmtCs((int) $ce['expected'])) ?>, zapsáno <?= e(Cred::fmtCs((int) $ce['actual'])) ?>.</small>
                <?php endif; ?>
              <?php else: ?>
                <span class="hint">Posloupnost v pořádku</span>
              <?php endif; ?>
            </td>
            <td class="num">
              <?php if ((int) $m['mismatch'] !== 0): ?>
                <form method="post"><?= Auth::csrfField() ?><input type="hidden" name="action" value="reconcile_ledger"><input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>"><button class="btn small" type="submit" data-confirm="Dorovnat účetní knihu podle aktuálního zůstatku? Původní historie zůstane zachovaná a přibude dohledatelný opravný záznam s důvodem zásahu." data-confirm-ok="Dorovnat knihu">Dorovnat</button></form>
              <?php else: ?>
                <span class="hint">Bez dorovnání</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="card" style="margin-top:16px">
    <h2>Kontrola dostupnosti souborů</h2>
    <p class="hint">
      Pravidla v <code>.htaccess</code> platí jen na Apachi. Na nginxu nebo při
      vypnutém AllowOverride se bez varování ignorují. Tohle se zeptá tvého
      vlastního serveru na každý chráněný soubor a ukáže, co se opravdu vrátí.
    </p>

    <?php if ($checks === null): ?>
      <a class="btn primary" href="?tab=overview&amp;scan=1">Spustit kontrolu</a>
    <?php else: ?>
      <?php
        $bad      = array_filter($checks, fn($c) => $c['exposed']);
        $untested = array_filter($checks, fn($c) => !$c['tested']);
      ?>
      <?php if ($bad): ?>
        <div class="msg err">
          <?= count($bad) ?> file(s) can be downloaded by anyone. Fix the server
          configuration before putting this online.
        </div>
      <?php elseif ($untested): ?>
        <div class="msg warn">
          <?= count($untested) ?> file(s) could not be tested, so this proves
          nothing. The loopback request needs a server that can answer a second
          request while handling the first - PHP's built-in development server
          cannot, and some firewalls block a host from calling itself. Check
          those paths by hand in a private browser window.
        </div>
      <?php else: ?>
        <div class="msg ok">Nic chráněného není z webu dostupné.</div>
      <?php endif; ?>
      <table>
        <thead><tr><th>Cesta</th><th>Obsahuje</th><th>Výsledek</th></tr></thead>
        <tbody>
        <?php foreach ($checks as $c): ?>
          <tr>
            <td><code><?= e($c['path']) ?></code></td>
            <td><?= e($c['what']) ?></td>
            <td<?= $c['exposed'] ? ' style="color:var(--bad);font-weight:700"' : '' ?>><?= e($c['status']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="hint"><a href="?tab=overview&amp;scan=1">Spustit znovu</a></p>
    <?php endif; ?>

    <p class="hint">
      Databáze: <code><?= e(Db::path()) ?></code>
      (<?= is_file(Db::path()) ? number_format(filesize(Db::path()) / 1024, 0, ',', ' ') . ' kB' : 'chybí' ?>)
    </p>
  </div>
