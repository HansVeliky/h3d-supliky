<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/bootstrap.php';

/**
 * Printable payment receipt for a paid order.
 *
 * A standalone page - not the usual chrome - so it prints (or "saves as PDF")
 * clean. Reached from the confirmation email with the order token, or by the
 * owner and an admin. Only a paid order has a receipt.
 */
$ref   = trim((string) ($_GET['ref'] ?? ''));
$token = trim((string) ($_GET['t'] ?? ''));
$user  = Auth::user();

// Two documents can exist per order: the original receipt (REF) which stays
// findable forever, and - once a refund is completed - a refund receipt
// under its own number REF_refund.
$isRefundDoc = str_ends_with($ref, '_refund');
$baseRef     = $isRefundDoc ? substr($ref, 0, -7) : $ref;

$order = null;
if ($baseRef !== '') {
    $st = Db::pdo()->prepare(
        'SELECT o.*, u.email, u.prefs AS buyer_prefs FROM orders o JOIN users u ON u.id = o.user_id WHERE o.reference = ?'
    );
    $st->execute([$baseRef]);
    $order = $st->fetch() ?: null;
}

$viaToken = $order
    && $token !== ''
    && (string) $order['access_token'] !== ''
    && hash_equals((string) $order['access_token'], $token);
$isOwner = $order && $user && (int) $order['user_id'] === (int) $user['id'];
$isAdmin = $user && (int) $user['is_admin'] === 1;

// The original receipt survives a refund - the purchase happened and must
// stay findable. The refund document only exists once the money went back.
$okStatus = static function (?array $o) use ($isRefundDoc): bool {
    if ($o === null) {
        return false;
    }
    if ($isRefundDoc) {
        // Older refunds predate the refunded_at column - the receipt still
        // exists for them, just with less detail filled in.
        return (string) $o['status'] === 'refunded';
    }
    return in_array((string) $o['status'], ['paid', 'refund', 'refunded'], true);
};

if (!$okStatus($order) || (!$viaToken && !$isOwner && !$isAdmin)) {
    // A broken or stale link is not a dead end: the order number together
    // with the e-mail it was bought under identifies the buyer well enough
    // to show the receipt anyway.
    $fRef  = trim((string) ($_POST['ref'] ?? $ref));
    $fMail = trim((string) ($_POST['email'] ?? ''));
    $fErr  = '';

    $verified = false;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $fRef !== '' && $fMail !== '') {
        $isRefundDoc = str_ends_with($fRef, '_refund');
        $lookupRef   = $isRefundDoc ? substr($fRef, 0, -7) : $fRef;
        $st = Db::pdo()->prepare(
            'SELECT o.*, u.email, u.prefs AS buyer_prefs FROM orders o JOIN users u ON u.id = o.user_id WHERE o.reference = ?'
        );
        $st->execute([$lookupRef]);
        $cand = $st->fetch() ?: null;

        if ($okStatus($cand)
            && strcasecmp((string) $cand['email'], $fMail) === 0) {
            $order    = $cand;
            $verified = true;
        } else {
            // One answer for every failure, so the form cannot be used to
            // probe which references or addresses exist.
            usleep(400000);
            $fErr = 'Objednávka s tímhle číslem a e-mailem nebyla nalezena, nebo ještě není zaplacená.';
        }
    }

    if (!$verified) {
        http_response_code(404);
        $csF = Lang::current() === 'cs';
        ?><!doctype html>
<html lang="<?= e(Lang::current()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($csF ? 'Doklad' : 'Receipt') ?></title>
<style>
  body{margin:0;background:#eef1f5;color:#1a2230;font:15px/1.5 -apple-system,Segoe UI,Roboto,Arial,sans-serif;
       display:flex;align-items:center;justify-content:center;min-height:100vh;padding:20px;box-sizing:border-box}
  .box{background:#fff;border:1px solid #e2e7ee;border-radius:14px;padding:30px 32px;max-width:430px;width:100%}
  h1{font-size:19px;margin:0 0 8px}
  p{color:#6b7688;font-size:13.5px;margin:0 0 18px}
  label{display:block;font-size:12px;color:#6b7688;font-weight:600;margin:12px 0 5px}
  input{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #d3d9e2;border-radius:9px;font:inherit}
  input:focus{outline:none;border-color:#1f6feb;box-shadow:0 0 0 3px rgba(31,111,235,.14)}
  button{margin-top:16px;width:100%;padding:11px;border:0;border-radius:10px;background:#1f6feb;color:#fff;
         font:inherit;font-weight:600;cursor:pointer}
  .err{background:#fdecee;border:1px solid #e8bcc2;color:#c2384a;padding:10px 12px;border-radius:9px;
       font-size:13px;margin-bottom:8px}
</style>
</head>
<body>
  <form class="box" method="post">
    <h1><?= e($csF ? 'Doklad se nepodařilo otevřít' : 'The receipt could not be opened') ?></h1>
    <p><?= e($csF
        ? 'Odkaz je neplatný nebo vypršel. Zadej číslo objednávky a e-mail, na který byla vystavena, a doklad se zobrazí.'
        : 'The link is invalid or expired. Enter the order number and the e-mail it was placed under to view the receipt.') ?></p>
    <?php if ($fErr !== ''): ?><div class="err"><?= e($fErr) ?></div><?php endif; ?>
    <label for="f-ref"><?= e($csF ? 'Číslo objednávky' : 'Order number') ?></label>
    <input id="f-ref" name="ref" type="text" placeholder="H3D-XXXXXX" value="<?= e($fRef) ?>" required>
    <label for="f-mail">E-mail</label>
    <input id="f-mail" name="email" type="email" value="<?= e($fMail) ?>" required>
    <button type="submit"><?= e($csF ? 'Zobrazit doklad' : 'Show the receipt') ?></button>
  </form>
</body>
</html><?php
        exit;
    }
}

// Language: an explicit ?lang wins (the on-page switch), otherwise the
// viewer's current language. So a customer opening the link sees their own
// language, and anyone can flip it before printing.
$reqLang = (string) ($_GET['lang'] ?? '');
$lang    = in_array($reqLang, ['cs', 'en'], true) ? $reqLang : Lang::current();
$cs      = $lang === 'cs';

// Document number: the original keeps the order reference, the refund
// receipt gets its own number derived from it.
$docNo = $isRefundDoc ? $order['reference'] . '_refund' : (string) $order['reference'];

// Kept on the print/switch links so they carry the token AND stay on the
// same document.
$linkBase = 'invoice.php?ref=' . urlencode($docNo)
          . '&t=' . urlencode((string) ($order['access_token'] ?? ''));

$seller = trim(Settings::get('invoice_seller')) !== '' ? Settings::get('invoice_seller') : Settings::get('site_name');
$info   = trim(Settings::get('invoice_info'));

// Item wording from the order snapshot, so a later package change or deletion
// cannot rewrite what was actually bought.
$days = (int) ($order['sub_days'] ?? 0);
$item = '';
if ($days > 0) {
    $item = Orders::durationLabel($days, $cs ? 'cs' : 'en') . ' ' . ($cs ? 'neomezené exporty' : 'unlimited exports');
    if ((int) $order['credits'] > 0) {
        $item .= ($cs ? ' + ' : ' + ') . Cred::fmt((int) $order['credits']) . ($cs ? ' kreditů' : ' credits');
    }
} else {
    $item = Cred::fmt((int) $order['credits']) . ($cs ? ' kreditů' : ' credits');
}

// A package name if the package still exists, purely as a label.
$pkgName = '';
if (!empty($order['package_id'])) {
    $ps = Db::pdo()->prepare('SELECT name FROM packages WHERE id = ?');
    $ps->execute([(int) $order['package_id']]);
    $pkgName = (string) ($ps->fetchColumn() ?: '');
}

$amount   = money((int) $order['price_cents'], $order['currency'] ?? null);
$paidAt   = $order['paid_at'] !== null ? (int) $order['paid_at'] : (int) $order['created_at'];
$vs       = Payments::vs($order);

$refundCents = (int) ($order['refund_cents'] ?? 0);
if ($refundCents <= 0) {
    $refundCents = (int) $order['price_cents'];
}
$refundAmount = money($refundCents, $order['currency'] ?? null);
$refundedAt   = !empty($order['refunded_at']) ? (int) $order['refunded_at'] : null;
$refundDest   = trim((string) ($order['refund_dest'] ?? ''));

$title = $isRefundDoc
    ? (($cs ? 'Doklad o refundaci ' : 'Refund receipt ') . $docNo)
    : (($cs ? 'Potvrzení o platbě ' : 'Receipt ') . $docNo);
?><!doctype html>
<html lang="<?= e(Lang::current()) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($title) ?></title>
<style>
  :root{--ink:#1a2230;--muted:#6b7688;--line:#e2e7ee;--accent:#1f6feb}
  *{box-sizing:border-box}
  body{margin:0;background:#eef1f5;color:var(--ink);
       font:15px/1.5 -apple-system,Segoe UI,Roboto,Arial,sans-serif}
  .sheet{max-width:720px;margin:26px auto;background:#fff;border:1px solid var(--line);
         border-radius:14px;padding:38px 40px}
  .top{display:flex;justify-content:space-between;align-items:flex-start;gap:24px;flex-wrap:wrap}
  h1{font-size:22px;margin:0 0 4px}
  .doc-no{color:var(--muted);font-size:13px}
  .paid-badge{display:inline-block;background:#e6f4ec;color:#1a7f4b;border:1px solid #b6dcc6;
              font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;
              padding:5px 11px;border-radius:999px}
  .parties{display:flex;gap:30px;flex-wrap:wrap;margin:26px 0 6px}
  .party{flex:1;min-width:220px}
  .party h2{font-size:11px;text-transform:uppercase;letter-spacing:.6px;color:var(--muted);margin:0 0 6px}
  .party .name{font-weight:700}
  .party .info{white-space:pre-line;color:var(--muted);font-size:13.5px;margin-top:2px}
  table{width:100%;border-collapse:collapse;margin:22px 0}
  th,td{text-align:left;padding:11px 8px;border-bottom:1px solid var(--line)}
  th{font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--muted)}
  td.num,th.num{text-align:right;font-variant-numeric:tabular-nums}
  .total-row td{border-bottom:0;border-top:2px solid var(--ink);font-weight:800;font-size:18px;padding-top:14px}
  .meta{display:flex;gap:26px;flex-wrap:wrap;color:var(--muted);font-size:13.5px;margin-top:6px}
  .meta b{color:var(--ink);font-weight:600}
  .foot{margin-top:26px;color:var(--muted);font-size:12.5px;border-top:1px solid var(--line);padding-top:14px}
  /* Room above the print button so it is not glued to whatever the hosting
     paints across the top of the page. */
  .actions{max-width:720px;margin:40px auto 20px;display:flex;justify-content:space-between;align-items:center;gap:12px}
  .lang-switch{display:inline-flex;gap:2px;background:#e2e7ee;padding:3px;border-radius:10px}
  .lang-switch a{color:var(--muted);text-decoration:none;font-size:13px;padding:5px 11px;border-radius:8px}
  .lang-switch a.on{background:#fff;color:var(--ink);font-weight:600;box-shadow:0 1px 2px rgba(0,0,0,.12)}
  .btn{display:inline-block;border:1px solid var(--accent);background:var(--accent);color:#fff;
       font-size:14px;font-weight:600;padding:9px 18px;border-radius:10px;cursor:pointer;text-decoration:none}
  /* Zero page margins so the browser stops drawing its own header/footer bars
     (URL, date, page number); the sheet gets its own print padding instead. */
  @page{size:auto;margin:0}
  @media print{
    html,body{background:#fff}
    .actions{display:none}
    .sheet{border:0;border-radius:0;margin:0;max-width:none;padding:16mm 18mm}

    /*
     * Only the document itself goes on the paper.
     *
     * Free hosting injects its advertising strip into the page (today it is
     * called endora-panel), and a print rule naming that one element would
     * stop working the day the host changes - or the day this moves to a
     * paid server with a different banner. So instead of naming the intruder,
     * everything that is a direct child of <body> is hidden and only the
     * sheet is brought back. Anything injected anywhere else is caught too.
     */
    body > *{display:none !important}
    body > .sheet{display:block !important}
  }
</style>
</head>
<body>
  <div class="actions">
    <span class="lang-switch">
      <a href="<?= e($linkBase) ?>&amp;lang=cs"<?= $cs ? ' class="on"' : '' ?>>Česky</a>
      <a href="<?= e($linkBase) ?>&amp;lang=en"<?= $cs ? '' : ' class="on"' ?>>English</a>
    </span>
    <button class="btn" type="button" onclick="window.print()"><?= e($cs ? 'Tisk / uložit PDF' : 'Print / save PDF') ?></button>
  </div>
  <div class="sheet">
    <div class="top">
      <div>
        <h1><?= e($isRefundDoc
            ? ($cs ? 'Doklad o refundaci' : 'Refund receipt')
            : ($cs ? 'Potvrzení o platbě' : 'Payment receipt')) ?></h1>
        <div class="doc-no"><?= e($cs ? 'Číslo dokladu: ' : 'Document no.: ') ?><strong><?= e($docNo) ?></strong></div>
        <?php if ($isRefundDoc): ?>
          <div class="doc-no"><?= e($cs ? 'K původnímu dokladu: ' : 'Refers to receipt: ') ?><strong><?= e((string) $order['reference']) ?></strong></div>
        <?php endif; ?>
      </div>
      <?php if ($isRefundDoc): ?>
        <span class="paid-badge" style="background:#fdecee;color:#c2384a;border-color:#e8bcc2"><?= e($cs ? 'Refundováno' : 'Refunded') ?></span>
      <?php else: ?>
        <span class="paid-badge"><?= e($cs ? 'Zaplaceno' : 'Paid') ?></span>
      <?php endif; ?>
    </div>

    <div class="parties">
      <div class="party">
        <h2><?= e($cs ? 'Dodavatel' : 'Seller') ?></h2>
        <div class="name"><?= e($seller) ?></div>
        <?php if ($info !== ''): ?><div class="info"><?= e($info) ?></div><?php endif; ?>
      </div>
      <div class="party">
        <h2><?= e($cs ? 'Odběratel' : 'Customer') ?></h2>
        <?php
          // Billing details the customer chose to fill in on their account.
          $bp = json_decode((string) ($order['buyer_prefs'] ?? ''), true);
          $bb = is_array($bp['billing'] ?? null) ? $bp['billing'] : [];
          $bg = static fn (string $k): string => trim((string) ($bb[$k] ?? ''));
        ?>
        <?php if ($bg('name') !== ''): ?>
          <div class="name"><?= e($bg('name')) ?></div>
          <div class="info"><?= e((string) $order['email']) ?></div>
        <?php else: ?>
          <div class="name"><?= e((string) $order['email']) ?></div>
        <?php endif; ?>
        <?php
          $addr = array_filter([
              $bg('street'),
              trim($bg('zip') . ' ' . $bg('city')),
              $bg('country'),
          ], static fn ($v) => $v !== '');
        ?>
        <?php if ($addr): ?><div class="info"><?= implode('<br>', array_map('e', $addr)) ?></div><?php endif; ?>
        <?php if ($bg('cin') !== ''): ?><div class="info"><?= e(($cs ? 'IČO: ' : 'Company ID: ') . $bg('cin')) ?></div><?php endif; ?>
        <?php if ($bg('vatid') !== ''): ?><div class="info"><?= e(($cs ? 'DIČ: ' : 'VAT ID: ') . $bg('vatid')) ?></div><?php endif; ?>
      </div>
    </div>

    <table>
      <thead>
        <tr>
          <th><?= e($cs ? 'Položka' : 'Item') ?></th>
          <th class="num"><?= e($cs ? 'Cena' : 'Amount') ?></th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            <?php if ($pkgName !== ''): ?><strong><?= e($pkgName) ?></strong><br><?php endif; ?>
            <?= e($item) ?>
            <?php if ($isRefundDoc): ?>
              <br><span style="color:var(--muted);font-size:13px"><?= e($cs
                  ? 'zakoupeno ' . when((int) $order['created_at']) . ', uhrazeno ' . when($paidAt)
                  : 'purchased ' . when((int) $order['created_at']) . ', paid ' . when($paidAt)) ?></span>
            <?php endif; ?>
          </td>
          <td class="num"><?= e($isRefundDoc ? $amount : $amount) ?></td>
        </tr>
        <?php if ($isRefundDoc): ?>
          <tr>
            <td><?= e($cs ? 'Refundace' : 'Refund') ?><?php if ($refundCents !== (int) $order['price_cents']): ?>
              <span style="color:var(--muted);font-size:13px"> (<?= e($cs ? 'poměrná část za nevyužité dny' : 'pro-rated for unused days') ?>)</span>
            <?php endif; ?></td>
            <td class="num">-<?= e($refundAmount) ?></td>
          </tr>
          <tr class="total-row">
            <td><?= e($cs ? 'Vráceno celkem' : 'Refunded total') ?></td>
            <td class="num">-<?= e($refundAmount) ?></td>
          </tr>
        <?php else: ?>
          <tr class="total-row">
            <td><?= e($cs ? 'Celkem' : 'Total') ?></td>
            <td class="num"><?= e($amount) ?></td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>

    <div class="meta">
      <span><?= e($cs ? 'Datum úhrady:' : 'Paid on:') ?> <b><?= e(when($paidAt)) ?></b></span>
      <?php if ($vs !== ''): ?><span><?= e($cs ? 'Variabilní symbol:' : 'Reference no.:') ?> <b><?= e($vs) ?></b></span><?php endif; ?>
      <span><?= e($cs ? 'Vytvořeno:' : 'Ordered:') ?> <b><?= e(when((int) $order['created_at'])) ?></b></span>
      <?php if ($isRefundDoc): ?>
        <span><?= e($cs ? 'Refundováno:' : 'Refunded on:') ?> <b><?= e(when($refundedAt)) ?></b></span>
        <?php if ($refundDest !== ''): ?>
          <span><?= e($cs ? 'Vráceno na:' : 'Refunded to:') ?> <b><?= e($refundDest) ?></b></span>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <div class="foot">
      <?php if ($isRefundDoc): ?>
        <?= e($cs
            ? 'Doklad o vrácení platby k dokladu ' . $order['reference'] . '. Vygenerováno automaticky, platné bez podpisu a razítka.'
            : 'Proof of refund for receipt ' . $order['reference'] . '. Generated automatically, valid without signature or stamp.') ?>
      <?php else: ?>
        <?= e($cs
            ? 'Doklad o přijetí platby. Vygenerováno automaticky, platné bez podpisu a razítka.'
            : 'Proof of payment. Generated automatically, valid without signature or stamp.') ?>
        <?php if ((string) $order['status'] === 'refunded'): ?>
          <br><?= e($cs ? 'K objednávce existuje doklad o refundaci č. ' : 'A refund receipt exists for this order: ') ?>
          <a href="invoice.php?ref=<?= e(urlencode($order['reference'] . '_refund')) ?>&amp;t=<?= e(urlencode((string) ($order['access_token'] ?? ''))) ?>"><?= e($order['reference'] . '_refund') ?></a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
