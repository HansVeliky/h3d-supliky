<?php
/**
 * Administration tab: users
 *
 * Lifted out of admin/index.php, which had all twenty of these in one
 * 6000-line file. Included from there with the surrounding scope intact,
 * so $tab, $ok, $error, the helpers and everything else the page sets up
 * are available exactly as before.
 */
if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }

    // The codes tab links here with an address, so the filter has to exist
    // for that link to lead anywhere useful.
    $q = trim((string) ($_GET['q'] ?? ''));
    $focusId = max(0, (int) ($_GET['focus'] ?? 0));
    $userPage = max(1, (int) ($_GET['user_page'] ?? 1));
    $usersPerPage = 50;
    $usersRole = (string) ($_GET['users_role'] ?? 'all');
    if (!in_array($usersRole, ['all','user','super','manager','admin'], true)) {
        $usersRole = 'all';
    }

    // Role counts are global (not affected by search/pagination). The legacy
    // is_admin flag wins, exactly like Auth::role().
    $userRoleCounts = [
        'all'     => (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(),
        'user'    => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 0 AND role = 'user'")->fetchColumn(),
        'super'   => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 0 AND role = 'super'")->fetchColumn(),
        'manager' => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 0 AND role = 'manager'")->fetchColumn(),
        'admin'   => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE is_admin = 1 OR role = 'admin'")->fetchColumn(),
    ];

    // Rendering one full dialog per account is intentionally avoided here:
    // each dialog contains forms and a lazy history endpoint, so a large
    // customer list used to make the Users tab look frozen before any click.
    if ($focusId > 0) {
        $st = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $st->execute([$focusId]);
        $users = $st->fetchAll();
        $usersTotal = count($users);
        $userPages = 1;
        $userPage = 1;
    } else {
        $conditions = [];
        $params = [];

        if ($usersRole === 'admin') {
            $conditions[] = "(is_admin = 1 OR role = 'admin')";
        } elseif ($usersRole !== 'all') {
            $conditions[] = "is_admin = 0 AND role = ?";
            $params[] = $usersRole;
        }

        if ($q !== '') {
            $conditions[] = '(email LIKE ? OR nickname LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $where = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $st = $pdo->prepare('SELECT COUNT(*) FROM users' . $where);
        $st->execute($params);
        $usersTotal = (int) $st->fetchColumn();
        $userPages = max(1, (int) ceil($usersTotal / $usersPerPage));
        $userPage = min($userPage, $userPages);
        $st = $pdo->prepare('SELECT * FROM users' . $where . ' ORDER BY id DESC LIMIT ? OFFSET ?');
        foreach ($params as $i => $value) { $st->bindValue($i + 1, $value, PDO::PARAM_STR); }
        $st->bindValue(count($params) + 1, $usersPerPage, PDO::PARAM_INT);
        $st->bindValue(count($params) + 2, ($userPage - 1) * $usersPerPage, PDO::PARAM_INT);
        $st->execute();
        $users = $st->fetchAll();
    }

    // A single account's timeline, shown when its "Historie" link is followed.
    $auditId   = (int) ($_GET['audit'] ?? 0);
    $auditUser = $auditId > 0 ? Auth::byId($auditId) : null;
?>
  <?php if ($auditUser): ?>
    <div class="card" id="audit">
      <div class="row" style="justify-content:space-between">
        <h2 style="border:0;margin:0;padding:0">Protokol zásahů a historie účtu <?= e($auditUser['email']) ?></h2>
        <a class="btn small" href="?tab=users">Zavřít historii</a>
      </div>
      <?php $events = Audit::forUser($auditId); ?>
      <?php if (!$events): ?>
        <p class="hint">Zatím žádná aktivita.</p>
      <?php else: ?>
        <ul class="audit">
          <?php foreach ($events as $ev): ?>
            <li class="audit-<?= e($ev['kind']) ?>">
              <span class="audit-when"><?= e(when((int) $ev['at'])) ?></span>
              <span class="audit-what">
                <?= e($ev['text']) ?>
                <?php if ($ev['detail'] !== ''): ?><span class="hint"><?= e($ev['detail']) ?></span><?php endif; ?>
              </span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="card">
    <h2>Uživatelé</h2>
    <div class="users-role-tabs" role="tablist" aria-label="Role uživatelů">
      <?php
        $roleTabs = [
          'all'     => 'Všichni',
          'user'    => 'Uživatelé',
          'super'   => 'Super user',
          'manager' => 'Správce',
          'admin'   => 'Administrátoři',
        ];
      ?>
      <?php foreach ($roleTabs as $rk => $rl): ?>
        <a class="users-role-tab role-<?= e($rk) ?><?= $usersRole === $rk ? ' is-on' : '' ?>"
           href="?tab=users&amp;users_role=<?= e($rk) ?>"
           role="tab" aria-selected="<?= $usersRole === $rk ? 'true' : 'false' ?>">
          <span class="users-role-icon" aria-hidden="true"></span>
          <span class="users-role-copy">
            <strong><?= e($rl) ?></strong>
            <small><?= (int) $userRoleCounts[$rk] ?> uživatelů</small>
          </span>
          <span class="users-role-count"><?= (int) $userRoleCounts[$rk] ?></span>
        </a>
      <?php endforeach; ?>
    </div>

    <form method="get" class="inline-form" style="margin-bottom:16px">
      <input type="hidden" name="tab" value="users">
      <input type="hidden" name="users_role" value="<?= e($usersRole) ?>">
      <input type="search" name="q" placeholder="Hledat e-mail nebo přezdívku" value="<?= e($q) ?>">
      <button class="btn" type="submit">Hledat</button>
      <?php if ($q !== ''): ?>
        <a class="btn" href="?tab=users">Zrušit filtr</a>
        <span class="hint">nalezeno: <?= $usersTotal ?></span>
      <?php endif; ?>
    </form>

    <?php if (!$users): ?>
      <p class="hint">Nikdo neodpovídá filtru.</p>
    <?php endif; ?>

    <?php if ($users): ?>
    <div class="table-scroll">
      <table class="user-table">
        <thead>
          <tr><th>Jméno</th><th>Přezdívka</th><th>Role</th><th class="num">Kredity</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <?php
            $role   = Auth::role($u);
            $isSelf = (int) $u['id'] === (int) $admin['id'];
            $priv   = Auth::isPrivileged($u);
            $mid    = 'manage-' . (int) $u['id'];
          ?>
          <tr class="user-tr<?= (int) $u['is_blocked'] === 1 ? ' is-blocked' : '' ?>">
            <td class="user-td-name">
              <?php $uSub = Auth::subscribedUntil($u); ?>
              <span class="user-mail"><?= e($u['email']) ?></span>
              <?php if ($uSub !== null): ?>
                <span class="sub-icon" title="Aktivní časový balíček do <?= e(date('d.m.Y H:i', $uSub)) ?>" aria-label="Aktivní časový balíček">
                  <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M12 7v5l3 2"></path></svg>
                </span>
              <?php endif; ?>
              <span class="user-tags">
                <?php if ((int) $u['is_blocked'] === 1): ?><span class="tag bad">zablokován</span><?php endif; ?>
                <?php if ($u['verified_at'] === null): ?><span class="tag warn">neověřen</span><?php endif; ?>
                <?php if ($isSelf): ?><span class="tag">to jsi ty</span><?php endif; ?>
              </span>
            </td>
            <td class="user-td-nickname"><?= e(trim((string) ($u['nickname'] ?? '')) !== '' ? (string) $u['nickname'] : '—') ?></td>
            <td class="user-td-role"><?= e(Auth::ROLES[$role] ?? $role) ?></td>
            <td class="num">
              <?php if ($priv): ?>
                <b class="user-inf" title="Neomezené - export je vždy zdarma">&infin;</b>
              <?php else: ?>
                <?= e(Cred::fmtCs((int) $u['credits'])) ?>
              <?php endif; ?>
            </td>
            <td class="num">
              <button class="btn small primary" type="button" data-user-open="<?= e($mid) ?>">Spravovat</button>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>

    <?php if ($focusId === 0 && $userPages > 1): ?>
      <nav class="pagination" aria-label="Stránkování uživatelů">
        <?php if ($userPage > 1): ?><a class="btn small" href="?tab=users&amp;users_role=<?= e($usersRole) ?>&amp;user_page=<?= $userPage - 1 ?><?= $q !== '' ? '&amp;q=' . urlencode($q) : '' ?>">Předchozí</a><?php endif; ?>
        <span class="hint">Strana <?= $userPage ?> z <?= $userPages ?> · <?= $usersTotal ?> uživatelů</span>
        <?php if ($userPage < $userPages): ?><a class="btn small" href="?tab=users&amp;users_role=<?= e($usersRole) ?>&amp;user_page=<?= $userPage + 1 ?><?= $q !== '' ? '&amp;q=' . urlencode($q) : '' ?>">Další</a><?php endif; ?>
      </nav>
    <?php endif; ?>

    <?php foreach ($users as $u): ?>
      <?php
        $role   = Auth::role($u);
        $isSelf = (int) $u['id'] === (int) $admin['id'];
        $priv   = Auth::isPrivileged($u);
        $mid    = 'manage-' . (int) $u['id'];
      ?>

      <!-- One dialog per account. The row is only an identity and a way in;
           everything you can do to the account - credits, role, blocking,
           password, deletion - lives together in here instead of spread
           across a wide row where the minus sign in a balance goes missing. -->
      <div class="modal-back user-modal" id="<?= e($mid) ?>"<?= (string) ($_GET['reopen'] ?? '') === $mid ? ' data-auto-open="1"' : '' ?><?= (string) ($_GET['user_pane'] ?? '') === 'zpravy' ? ' data-auto-pane="zpravy"' : '' ?> hidden>
        <div class="modal modal-full" role="dialog" aria-modal="true"
             aria-labelledby="<?= e($mid) ?>-title">
          <button type="button" class="modal-x" data-user-close aria-label="Zavřít">&times;</button>

          <h3 id="<?= e($mid) ?>-title"><?= e($u['email']) ?></h3>
          <div class="user-modal-tags">
            <?php if ($role === 'admin'): ?><span class="tag on">správce</span><?php endif; ?>
            <?php if ($role === 'super'): ?><span class="tag super">super user</span><?php endif; ?>
            <?php if ((int) $u['is_blocked'] === 1): ?><span class="tag bad">zablokován</span><?php endif; ?>
            <?php if ($u['verified_at'] === null): ?><span class="tag warn">neověřen</span><?php endif; ?>
            <?php if ($isSelf): ?><span class="tag">to jsi ty</span><?php endif; ?>
          </div>

          <!-- The whole account on tabs: actions first, then every record
               the system holds about it, loaded on open. -->
          <nav class="ud-tabs">
            <button type="button" class="ud-tab is-on" data-udtab="sprava">Správa</button>
            <button type="button" class="ud-tab" data-udtab="prehled">Přehled</button>
            <button type="button" class="ud-tab" data-udtab="kredity">Čas · Kredity · Kódy</button>
            <button type="button" class="ud-tab" data-udtab="objednavky">Objednávky</button>
            <button type="button" class="ud-tab" data-udtab="maily">E-maily</button>
            <button type="button" class="ud-tab" data-udtab="zpravy">Konverzace</button>
            <button type="button" class="ud-tab" data-udtab="exporty">Exporty</button>
            <button type="button" class="ud-tab" data-udtab="aktivita">Aktivita</button>
          </nav>

          <div class="ud-body">
          <div class="ud-pane" data-udpane="sprava">
          <div class="user-meta">
            <span>Registrace <strong><?= e(when((int) $u['created_at'])) ?></strong></span>
            <span>Poslední přihlášení <strong><?= e(when($u['last_login_at'] !== null ? (int) $u['last_login_at'] : null)) ?></strong></span>
          </div>

          <div class="user-modal-section">
            <h4>Kredity</h4>
            <div class="user-modal-balance"><b><?= e(Cred::fmtCs((int) $u['credits'])) ?></b> kreditů</div>
            <?php if ($priv): ?>
              <p class="hint" style="margin:0 0 10px">
                Neomezená role teď kredity nečerpá (export je vždy zdarma), ale
                zůstatek se drží dál - kdyby se účet vrátil na běžnou roli,
                bude platit tenhle. Proto ho tu jde vidět i upravit.
              </p>
            <?php endif; ?>
            <button class="btn small" type="button"
                    data-credit-open
                    data-user="<?= (int) $u['id'] ?>"
                    data-email="<?= e($u['email']) ?>"
                    data-current="<?= e(Cred::input((int) $u['credits'])) ?>">
              Upravit kredity
            </button>
          </div>

          <div class="user-modal-section">
            <h4>Předplatné</h4>
            <?php $subUntil = Auth::subscribedUntil($u); ?>
            <?php if ($subUntil !== null): ?>
              <p style="margin:0 0 10px">
                Aktivní do <strong><?= e(date('d.m.Y H:i', $subUntil)) ?></strong> - neomezené exporty.
              </p>
            <?php else: ?>
              <p class="hint" style="margin:0 0 10px">Žádné aktivní předplatné.</p>
            <?php endif; ?>
            <div class="user-buttons">
              <form method="post" class="inline-form" style="margin:0">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="set_subscription">
                <input type="hidden" name="reopen" value="manage-<?= (int) $u['id'] ?>">
                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                <input type="date" name="until"
                       value="<?= $subUntil !== null ? e(date('Y-m-d', $subUntil)) : '' ?>"
                       aria-label="Předplatné do" style="width:auto">
                <button class="btn small" type="submit">Nastavit datum</button>
              </form>
              <?php if ($subUntil !== null): ?>
                <form method="post" style="display:inline">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="action" value="cancel_subscription">
                  <input type="hidden" name="reopen" value="manage-<?= (int) $u['id'] ?>">
                  <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                  <button class="btn small danger" type="submit"
                          data-confirm="Zrušit předplatné účtu <?= e($u['email']) ?>? Neomezené exporty mu okamžitě skončí. Případné vrácení peněz řeš zvlášť."
                          data-confirm-ok="Zrušit předplatné">Zrušit (refund)</button>
                </form>
              <?php endif; ?>
            </div>
            <div class="hint" style="margin-top:8px">
              Prázdné datum + Nastavit = zrušit. Datum se počítá do konce dne.
            </div>
          </div>

          <div class="user-modal-section user-actions-section">
            <h4>Akce s uživatelem</h4>
            <div class="user-action-list">
              <form method="post" class="role-form">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="set_role">
                <input type="hidden" name="reopen" value="manage-<?= (int) $u['id'] ?>">
                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                <select name="role" aria-label="Role">
                  <?php foreach (Auth::ROLES as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= $role === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
                <button class="btn small" type="submit"
                        data-confirm="Opravdu změnit roli účtu <?= e($u['email']) ?>? Nová role bude použita okamžitě."
                        data-confirm-ok="Změnit roli">
                  Uložit roli
                </button>
              </form>

              <span class="user-action-sep">|</span>

              <form method="post">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="toggle_block">
                <input type="hidden" name="reopen" value="manage-<?= (int) $u['id'] ?>">
                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                <button class="btn small" type="submit"
                        data-confirm="<?= (int) $u['is_blocked'] === 1
                          ? 'Opravdu odblokovat účet '.e($u['email']).'?'
                          : 'Opravdu zablokovat účet '.e($u['email']).'?' ?>"
                        data-confirm-ok="<?= (int) $u['is_blocked'] === 1 ? 'Odblokovat' : 'Zablokovat' ?>">
                  <?= (int) $u['is_blocked'] === 1 ? 'Odblokovat' : 'Zablokovat' ?>
                </button>
              </form>

              <span class="user-action-sep">|</span>

              <form method="post">
                <?= Auth::csrfField() ?>
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="reopen" value="manage-<?= (int) $u['id'] ?>">
                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                <button class="btn small" type="submit"
                        data-confirm="Poslat účtu <?= e($u['email']) ?> odkaz na obnovu hesla? Dostane e-mail s jednorázovým odkazem (platí 2 h)."
                        data-confirm-ok="Poslat odkaz">Reset hesla</button>
              </form>

              <?php if ($u['verified_at'] === null): ?>
                <span class="user-action-sep">|</span>
                <form method="post">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="action" value="mark_verified">
                  <input type="hidden" name="reopen" value="manage-<?= (int) $u['id'] ?>">
                  <input type="hidden" name="uid" value="<?= (int) $u['id'] ?>">
                  <button class="btn small" type="submit"
                          data-confirm="Opravdu ručně ověřit účet <?= e($u['email']) ?>? Tím se účtu odemknou funkce pro ověřené uživatele."
                          data-confirm-ok="Ověřit účet">Ověřit ručně</button>
                </form>
              <?php endif; ?>

              <?php if (!$isSelf): ?>
                <span class="user-action-sep">|</span>
                <form method="post">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="action" value="anonymise_user">
                  <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                  <button class="btn small" type="submit"
                          data-confirm="Anonymizovat účet <?= e($u['email']) ?>? E-mail a heslo se přepíšou, zůstatek se vynuluje a účet se zablokuje. Objednávky a historie kreditů zůstanou zachovány."
                          data-confirm-ok="Anonymizovat">Anonymizovat</button>
                </form>

                <span class="user-action-sep">|</span>

                <form method="post">
                  <?= Auth::csrfField() ?>
                  <input type="hidden" name="action" value="delete_user">
                  <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                  <button class="btn small danger" type="submit"
                          data-confirm="Nenávratně smazat účet <?= e($u['email']) ?>? Zmizí i jeho objednávky, historie kreditů a záznamy o uplatněných kódech. Pokud potřebuješ zachovat účetní stopu, použij radši Anonymizovat."
                          data-confirm-ok="Smazat natrvalo">Smazat</button>
                </form>
              <?php endif; ?>
            </div>
          </div>
</div><!-- /ud-pane sprava -->

          <!-- Data tabs arrive from the fragment endpoint when the dialog opens. -->
          <div class="ud-remote" data-detail-url="?tab=users&amp;detail=<?= (int) $u['id'] ?>&amp;fragment=1">
            <p class="hint" style="display:none">Načítám…</p>
          </div>
          </div><!-- /ud-body -->
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- One dialog serves every row; the row only carries the numbers. Editing
       a balance in a table cell meant reading a figure in one column and
       typing into another, which is how a minus sign goes missing. -->
  <div class="credit-backdrop" id="creditDialog" hidden>
    <form class="credit-dialog" method="post" role="dialog" aria-modal="true"
          aria-labelledby="creditDialogTitle">
      <?= Auth::csrfField() ?>
      <input type="hidden" name="action" value="adjust_credits">
      <input type="hidden" name="reopen" id="creditReopen" value="">
      <input type="hidden" name="user_id" id="creditUserId">
      <input type="hidden" name="mode" value="raw">

      <h2 id="creditDialogTitle">Úprava kreditů</h2>
      <p class="credit-dialog-who" id="creditEmail"></p>

      <div class="credit-fields">
        <div>
          <label for="creditCurrent">Aktuální stav</label>
          <input id="creditCurrent" type="text" readonly tabindex="-1">
        </div>
        <div>
          <label for="creditChange">Úprava</label>
          <input id="creditChange" name="amount" type="text" inputmode="decimal"
                 autocomplete="off" placeholder="-50">
        </div>
        <div>
          <label for="creditResult">Nový stav</label>
          <input id="creditResult" type="text" readonly tabindex="-1">
        </div>
      </div>

      <p class="hint" id="creditHint">
        Se znaménkem se hodnota přičte nebo odečte (<code>-50</code>,
        <code>+10</code>). Bez znaménka je to nový stav konta.
      </p>

      <label for="creditReason">Důvod (nepovinné)</label>
      <input id="creditReason" name="reason" type="text"
             placeholder="Objeví se zákazníkovi v e-mailu i v historii">

      <div class="credit-dialog-actions">
        <button class="btn primary" type="submit" id="creditSubmit">Uložit</button>
        <button class="btn" type="button" data-credit-close>Zrušit</button>
      </div>
    </form>
  </div>
