<?php
/**
 * Administration tab: messages
 *
 * Lifted out of admin/index.php, which had all twenty of these in one
 * 6000-line file. Included from there with the surrounding scope intact,
 * so $tab, $ok, $error, the helpers and everything else the page sets up
 * are available exactly as before.
 */
if (!defined('H3D_APP')) { http_response_code(403); exit('Forbidden'); }

    $mq = trim((string) ($_GET['q'] ?? ''));
    $messageState = (string) ($_GET['state'] ?? 'all');
    $messagePage = max(1, (int) ($_GET['message_page'] ?? 1));
    $messagesPerPage = 50;
    $allowedStates = ['all', 'new', 'waiting', 'answered', 'resolved'];
    if (!in_array($messageState, $allowedStates, true)) { $messageState = 'all'; }
    $filters = []; $args = [];
    if ($mq !== '') {
        $filters[] = '(m.subject LIKE ? OR m.body LIKE ? OR u.email LIKE ? OR u.nickname LIKE ?)';
        $like = '%' . $mq . '%'; $args = [$like, $like, $like, $like];
    }
    $filters[] = match ($messageState) {
        'new'      => 'm.closed_at IS NULL AND m.read_at IS NULL',
        'waiting'  => "m.closed_at IS NULL AND m.read_at IS NOT NULL AND COALESCE(m.admin_reply, '') = ''",
        'answered' => "m.closed_at IS NULL AND COALESCE(m.admin_reply, '') <> ''",
        'resolved' => 'm.closed_at IS NOT NULL',
        default    => '1 = 1',
    };
    $where = ' WHERE ' . implode(' AND ', $filters);
    $st = $pdo->prepare('SELECT COUNT(*) FROM user_messages m JOIN users u ON u.id = m.user_id' . $where);
    $st->execute($args);
    $messagesTotal = (int) $st->fetchColumn();
    $messagePages = max(1, (int) ceil($messagesTotal / $messagesPerPage));
    $messagePage = min($messagePage, $messagePages);
    $st = $pdo->prepare('SELECT m.*, u.email, u.nickname FROM user_messages m JOIN users u ON u.id = m.user_id' . $where . ' ORDER BY CASE WHEN m.closed_at IS NULL THEN 0 ELSE 1 END, m.created_at DESC LIMIT ? OFFSET ?');
    foreach ($args as $i => $arg) { $st->bindValue($i + 1, $arg, PDO::PARAM_STR); }
    $st->bindValue(count($args) + 1, $messagesPerPage, PDO::PARAM_INT);
    $st->bindValue(count($args) + 2, ($messagePage - 1) * $messagesPerPage, PDO::PARAM_INT);
    $st->execute(); $messages = $st->fetchAll();
    $unread = UserMessages::unreadCount();
    $feedbackRows = $pdo->query(
        "SELECT f.*, u.email, u.nickname
         FROM support_feedback f
         JOIN users u ON u.id = f.user_id
         ORDER BY f.created_at DESC, f.id DESC
         LIMIT 50"
    )->fetchAll();

    // Rating overview: percentages are calculated from all submitted ratings,
    // not just the 50 most recent rows shown below.
    $feedbackStats = ['good' => 0, 'partial' => 0, 'bad' => 0];
    $feedbackTotal = (int) $pdo->query('SELECT COUNT(*) FROM support_feedback')->fetchColumn();
    $feedbackCountRows = $pdo->query(
        "SELECT rating, COUNT(*) AS cnt
         FROM support_feedback
         GROUP BY rating"
    )->fetchAll();
    foreach ($feedbackCountRows as $fr) {
        $key = (string) $fr['rating'];
        if (isset($feedbackStats[$key])) {
            $feedbackStats[$key] = (int) $fr['cnt'];
        }
    }
    $manageId = (int) ($_GET['manage'] ?? 0);
    $managed = null;
    if ($manageId) {
        $ms = $pdo->prepare('SELECT m.*, u.email, u.nickname, u.lang, a.email AS reply_admin FROM user_messages m JOIN users u ON u.id = m.user_id LEFT JOIN users a ON a.id = m.replied_by WHERE m.id = ?');
        $ms->execute([$manageId]); $managed = $ms->fetch() ?: null;
    }
    $managedPosts = $managed ? UserMessages::postsFor((int) $managed['id']) : [];
    $managedHasOtherActive = false;
    if ($managed && $managed['closed_at'] !== null) {
        $chk = $pdo->prepare('SELECT 1 FROM user_messages WHERE user_id = ? AND closed_at IS NULL AND id <> ? LIMIT 1');
        $chk->execute([(int)$managed['user_id'], (int)$managed['id']]);
        $managedHasOtherActive = (bool)$chk->fetchColumn();
    }
    $returnQuery = '?tab=messages' . ($mq !== '' ? '&amp;q=' . urlencode($mq) : '') . ($messageState !== 'all' ? '&amp;state=' . urlencode($messageState) : '') . ($messagePage > 1 ? '&amp;message_page=' . $messagePage : '');
?>
<section class="card support-inbox">
  <div class="card-head support-inbox-head"><div><h2>Centrum zpráv</h2><p class="hint">Jedna fronta, jasný stav a plná konverzace bez duplicitního panelu.</p></div><span class="support-unread"><?= (int) $unread ?> <?= $unread === 1 ? 'nová zpráva' : 'nových zpráv' ?></span></div>
  <form method="get" class="support-search"><input type="hidden" name="tab" value="messages"><input type="search" name="q" value="<?= e($mq) ?>" placeholder="Hledat uživatele, e-mail nebo obsah"><input type="hidden" name="state" value="<?= e($messageState) ?>"><button class="btn" type="submit">Hledat</button></form>
  <nav class="support-filters" aria-label="Filtr zpráv">
    <?php foreach (['all' => 'Vše', 'new' => 'Nové', 'waiting' => 'Čekají na odpověď', 'answered' => 'Odpovězené', 'resolved' => 'Archiv'] as $key => $label): ?>
      <a class="<?= $messageState === $key ? 'is-on' : '' ?>" href="?tab=messages&amp;state=<?= e($key) ?><?= $mq !== '' ? '&amp;q=' . urlencode($mq) : '' ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php if (!$messages): ?><p class="hint support-empty">V této části nejsou žádné zprávy.</p><?php else: ?>
    <div class="support-rows">
    <?php foreach ($messages as $m): ?>
      <?php
        if ($m['closed_at'] !== null) { $stateText = 'Vyřešeno'; $stateClass = 'done'; }
        elseif (empty($m['read_at'])) { $stateText = 'Nová'; $stateClass = 'new'; }
        elseif (trim((string) ($m['admin_reply'] ?? '')) === '') { $stateText = 'Čeká'; $stateClass = 'waiting'; }
        else { $stateText = 'Odpovězeno'; $stateClass = 'answered'; }
      ?>
      <article class="support-row <?= $stateClass === 'new' ? 'is-new' : '' ?>">
        <span class="support-row-state is-<?= e($stateClass) ?>"><?= e($stateText) ?></span>
        <div class="support-row-user"><strong><?= e((string) ($m['nickname'] ?: $m['email'])) ?></strong><span><?= e((string) $m['email']) ?></span></div>
        <?php
          $rowIsLive = stripos((string)$m['subject'], 'živým kolegou') !== false
              || stripos((string)$m['subject'], 'live colleague') !== false
              || stripos((string)$m['subject'], 'podpora:') === 0;
          $rowTitle = trim((string)($m['admin_title'] ?? ''));
          if ($rowTitle === '') {
              $rowTitle = $rowIsLive ? 'Nová zpráva' : (string)$m['subject'];
          }
        ?>
        <div class="support-row-copy"><strong><?= e($rowTitle) ?></strong></div>
        <time datetime="<?= e(date(DATE_ATOM, (int) $m['created_at'])) ?>"><?= e(when((int) $m['created_at'])) ?></time>
        <div class="support-row-actions"><a class="btn small" href="?tab=users&amp;focus=<?= (int) $m['user_id'] ?>&amp;reopen=manage-<?= (int) $m['user_id'] ?>&amp;user_pane=zpravy">Historie uživatele</a><form method="post"><?= Auth::csrfField() ?><input type="hidden" name="action" value="open_user_message"><input type="hidden" name="message_id" value="<?= (int) $m['id'] ?>"><input type="hidden" name="reopen" value="support-<?= (int) $m['id'] ?>"><button class="btn small primary" type="submit">Spravovat</button></form></div>
      </article>
    <?php endforeach; ?>
    </div>
  <?php endif; ?>
  <?php if ($messagePages > 1): ?>
    <nav class="pagination" aria-label="Stránkování zpráv">
      <?php if ($messagePage > 1): ?><a class="btn small" href="?tab=messages&amp;message_page=<?= $messagePage - 1 ?><?= $mq !== '' ? '&amp;q=' . urlencode($mq) : '' ?><?= $messageState !== 'all' ? '&amp;state=' . urlencode($messageState) : '' ?>">Předchozí</a><?php endif; ?>
      <span class="hint">Strana <?= $messagePage ?> z <?= $messagePages ?> · <?= $messagesTotal ?> konverzací</span>
      <?php if ($messagePage < $messagePages): ?><a class="btn small" href="?tab=messages&amp;message_page=<?= $messagePage + 1 ?><?= $mq !== '' ? '&amp;q=' . urlencode($mq) : '' ?><?= $messageState !== 'all' ? '&amp;state=' . urlencode($messageState) : '' ?>">Další</a><?php endif; ?>
    </nav>
  <?php endif; ?>
</section>


<section class="card support-feedback-admin">
  <div class="card-head">
    <div>
      <h2>Hodnocení H3D Pomocníka</h2>
      <p class="hint">Hodnocení odeslaná po ukončení chatu. Archiv obsahuje všechny ukončené konverzace.</p>
    </div>
  </div>
  <?php if ($feedbackTotal === 0): ?>
    <p class="hint">Zatím nebylo odesláno žádné hodnocení.</p>
  <?php else: ?>
    <?php
      $feedbackMeta = [
        'good' => ['emoji' => '🙂', 'label' => 'Pomohl'],
        'partial' => ['emoji' => '😐', 'label' => 'Částečně'],
        'bad' => ['emoji' => '🙁', 'label' => 'Nepomohl'],
      ];
    ?>
    <div class="support-feedback-summary" aria-label="Souhrn hodnocení">
      <?php foreach ($feedbackMeta as $key => $meta): ?>
        <?php
          $count = $feedbackStats[$key];
          $percent = $feedbackTotal > 0 ? round(($count / $feedbackTotal) * 100, 1) : 0;
        ?>
        <div class="support-feedback-stat is-<?= e($key) ?>">
          <div class="support-feedback-stat-top">
            <span class="support-feedback-emoji" aria-hidden="true"><?= $meta['emoji'] ?></span>
            <strong><?= e($meta['label']) ?></strong>
            <b><?= e((string)$percent) ?> %</b>
          </div>
          <div class="support-feedback-stat-bar" aria-hidden="true">
            <span style="width:<?= e((string)$percent) ?>%"></span>
          </div>
          <small><?= e((string)$count) ?> <?= $count === 1 ? 'hodnocení' : 'hodnocení' ?></small>
        </div>
      <?php endforeach; ?>
      <div class="support-feedback-total">
        Celkem hodnocení <strong><?= e((string)$feedbackTotal) ?></strong>
      </div>
    </div>
  <?php endif; ?>

  <?php if ($feedbackRows): ?>
    <div class="support-feedback-admin-list">
      <?php foreach ($feedbackRows as $f): ?>
        <?php
          $labels = ['good'=>'Pomohl','partial'=>'Částečně','bad'=>'Nepomohl'];
          $ratingLabel = $labels[(string)$f['rating']] ?? (string)$f['rating'];
        ?>
        <article class="support-feedback-admin-row">
          <div>
            <strong><?= e((string)($f['nickname'] ?: $f['email'])) ?></strong>
            <span><?= e((string)$f['email']) ?></span>
          </div>
          <b class="support-feedback-admin-rating is-<?= e((string)$f['rating']) ?>"><?= e($ratingLabel) ?></b>
          <time><?= e(when((int)$f['created_at'])) ?></time>
          <?php if (trim((string)$f['comment']) !== ''): ?>
            <p><?= nl2br(e((string)$f['comment'])) ?></p>
          <?php endif; ?>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($managed): ?>
  <?php
    if ($managed['closed_at'] !== null) { $managedState = 'VYŘEŠENO'; $managedClass = 'done'; }
    elseif (trim((string) ($managed['admin_reply'] ?? '')) !== '') { $managedState = 'ODPOVĚZENO'; $managedClass = 'answered'; }
    else { $managedState = 'ČEKÁ NA ODPOVĚĎ'; $managedClass = 'waiting'; }
  ?>
  <div class="support-modal-back">
    <section class="support-workspace" role="dialog" aria-modal="true" aria-labelledby="supportTicketTitle">
      <header class="support-workspace-head"><div class="support-title-wrap"><span>SUPPORT / TICKET #<?= (int) $managed['id'] ?></span><div class="support-admin-title"><h3 id="supportTicketTitle"><?= e(trim((string)($managed['admin_title'] ?? '')) !== '' ? (string)$managed['admin_title'] : (stripos((string)$managed['subject'], 'podpora:') === 0 ? 'Nová zpráva' : (string)$managed['subject'])) ?></h3><button type="button" class="support-title-edit" data-support-title-edit>Upravit nadpis</button></div><form method="post" class="support-title-form" data-support-title-form hidden><input type="hidden" name="action" value="update_support_title"><?= Auth::csrfField() ?><input type="hidden" name="message_id" value="<?= (int) $managed['id'] ?>"><input type="hidden" name="reopen" value="support-<?= (int) $managed['id'] ?>"><input type="text" name="admin_title" value="<?= e(trim((string)($managed['admin_title'] ?? '')) !== '' ? (string)$managed['admin_title'] : (stripos((string)$managed['subject'], 'podpora:') === 0 ? 'Nová zpráva' : (string)$managed['subject'])) ?>" maxlength="160" aria-label="Interní nadpis konverzace"><button class="btn small primary" type="submit">Uložit</button><button class="btn small" type="button" data-support-title-cancel>Zrušit</button></form></div><div class="support-head-actions"><b class="support-state is-<?= e($managedClass) ?>"><?= e($managedState) ?></b><a class="support-close danger" href="<?= $returnQuery ?>" aria-label="Zavřít konverzaci">×</a></div></header>
      <div class="support-workspace-body">
        <main class="support-thread">
          <?php
            $managedIsLive = (string)$managed['subject'] === 'Nová zpráva'
                || stripos((string)$managed['subject'], 'živým kolegou') !== false
                || stripos((string)$managed['subject'], 'live colleague') !== false
                || stripos((string)$managed['subject'], 'podpora:') === 0;
            if (!$managedIsLive && trim((string)$managed['body']) !== ''):
          ?>
            <div class="support-bubble customer">
              <small>UŽIVATEL · <?= e(when((int) $managed['created_at'])) ?></small>
              <div><?= nl2br(e((string) $managed['body'])) ?></div>
            </div>
          <?php endif; ?>
          <?php foreach ($managedPosts as $post):
            $kind = (string)$post['author_kind'];
            $isUser = $kind === 'user';
            $isStaff = $kind === 'staff';
            $bubbleClass = $isStaff ? 'operator' : ($isUser ? 'customer' : 'assistant');
            $label = $isStaff ? 'ŽIVÝ KOLEGA' : ($isUser ? 'UŽIVATEL' : 'H3D POMOCNÍK');
          ?>
            <div class="support-bubble <?= e($bubbleClass) ?>">
              <small><?= e($label) ?><?= $isStaff && trim((string)$post['author_name']) !== '' ? ' · ' . e((string)$post['author_name']) : '' ?> · <?= e(when((int) $post['created_at'])) ?></small>
              <div><?= nl2br(e((string)$post['body'])) ?></div>
            </div>
          <?php endforeach; ?>
          <?php if ($managed['closed_at'] !== null): ?><p class="support-resolution">Konverzace byla uzavřena <?= e(when((int) $managed['closed_at'])) ?>.</p><?php endif; ?>
        </main>
        <aside class="support-context"><div class="support-person"><span>UŽIVATEL</span><strong><?= e((string) ($managed['nickname'] ?: $managed['email'])) ?></strong><a href="?tab=users&amp;focus=<?= (int) $managed['user_id'] ?>&amp;reopen=manage-<?= (int) $managed['user_id'] ?>&amp;user_pane=zpravy"><?= e((string) $managed['email']) ?></a></div><dl><div><dt>IP adresa</dt><dd><?= e((string) ($managed['ip_address'] ?: 'nezaznamenána')) ?></dd></div><div><dt>Jazyk účtu</dt><dd><?= e(strtoupper((string) ($managed['lang'] ?: 'cs'))) ?></dd></div><div><dt>Vytvořeno</dt><dd><?= e(when((int) $managed['created_at'])) ?></dd></div></dl></aside>
      </div>
      <footer class="support-composer">
      <?php if ($managed['closed_at'] === null): ?>
        <form method="post" id="supportReplyForm"><input type="hidden" name="action" value="reply_user_message"><?= Auth::csrfField() ?><input type="hidden" name="message_id" value="<?= (int) $managed['id'] ?>"><input type="hidden" name="reopen" value="support-<?= (int) $managed['id'] ?>"><label for="message_reply">Odpověď uživateli</label><textarea id="message_reply" name="reply" rows="5" maxlength="10000" required placeholder="Napiš jasnou a konkrétní odpověď…"></textarea></form>
        <div class="support-compose-actions">
          <span>Každé odeslání je nový, neměnný příspěvek chatu.</span>
          <div class="support-compose-buttons">
            <button class="btn primary" type="submit" form="supportReplyForm">Odeslat odpověď</button>
            <form method="post" class="support-close-ticket"><?= Auth::csrfField() ?><input type="hidden" name="action" value="close_user_message"><input type="hidden" name="message_id" value="<?= (int) $managed['id'] ?>"><input type="hidden" name="reopen" value="support-<?= (int) $managed['id'] ?>"><button class="btn danger" type="submit" data-confirm="Označit tuto konverzaci jako vyřešenou? Uživatel už ji nebude moci znovu otevřít." data-confirm-ok="Ano, vyřešit">Označit jako vyřešené</button></form>
          </div>
        </div>
      <?php else: ?>
        <form method="post" class="support-reopen-ticket"><?= Auth::csrfField() ?><input type="hidden" name="action" value="reopen_user_message"><input type="hidden" name="message_id" value="<?= (int) $managed['id'] ?>"><input type="hidden" name="reopen" value="support-<?= (int) $managed['id'] ?>">
          <?php if ($managedHasOtherActive): ?>
            <p>Konverzace je uzavřená, ale tento uživatel už má aktivní chat s podporou. Nejprve je potřeba dokončit aktivní chat.</p>
            <button class="btn primary" type="button" disabled>Znovu otevřít konverzaci</button>
          <?php else: ?>
            <p>Konverzace je uzavřená. Znovu ji otevři jen pokud je potřeba pokračovat.</p>
            <button class="btn primary" type="submit">Znovu otevřít konverzaci</button>
          <?php endif; ?>
        </form>
      <?php endif; ?>
      </footer>
    </section>
  </div>
<?php endif; ?>
