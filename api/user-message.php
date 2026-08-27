<?php
declare(strict_types=1);
define('H3D_APP', true);
require_once dirname(__DIR__) . '/lib/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(['ok'=>false,'error'=>'Metoda není povolena.']); exit; }
$u=Auth::user();
if (!$u) { http_response_code(401); echo json_encode(['ok'=>false,'error'=>'Pro odeslání zprávy se musíš přihlásit.']); exit; }
if (!Auth::isPrivileged($u) && empty($u['verified_at'])) {
    http_response_code(403);
    echo json_encode(['ok'=>false,'error'=>'Chat je dostupný až po ověření e-mailu.'], JSON_UNESCAPED_UNICODE);
    exit;
}
Auth::start();
$sent=(string)($_POST['csrf']??'');
$token=Auth::csrfToken();
if (!Security::sameOriginRequest() || $sent === '' || !hash_equals($token,$sent)) { http_response_code(403); echo json_encode(['ok'=>false,'error'=>'Platnost formuláře vypršela. Obnov stránku.'], JSON_UNESCAPED_UNICODE); exit; }
// All identity and CSRF work is complete; message writes must not block a
// parallel studio save or a click in another browser tab.
@session_write_close();


if (($_POST['action'] ?? '') === 'assistant') {
    try {
        $body = trim((string)($_POST['body'] ?? ''));
        if ($body === '' || mb_strlen($body) > 2000) {
            throw new InvalidArgumentException('Zpráva je povinná a může mít nejvýše 2 000 znaků.');
        }

        $pdo = Db::pdo();
        $uid = (int)$u['id'];
        $credits = (int)$pdo->prepare('SELECT credits FROM users WHERE id = ?');
        $st = $pdo->prepare('SELECT credits FROM users WHERE id = ?');
        $st->execute([$uid]);
        $credits = (int)$st->fetchColumn();

        $st = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 1');
        $st->execute([$uid]);
        $order = $st->fetch() ?: null;

        $q = trim(mb_strtolower($body));
        $reply = '';
        $quick = [];

        // Quick-action labels are deterministic commands, not free-form text.
        if (in_array($q, ['moje aktivní předplatné','moje aktivni predplatne'], true)) {
            $st=$pdo->prepare('SELECT role, is_admin, subscription_until, credits FROM users WHERE id=?');
            $st->execute([$uid]);
            $account=$st->fetch() ?: [];
            $balance=(int)($account['credits'] ?? 0);

            if (Auth::isPrivileged($account)) {
                $reply='Máš aktivní časový balíček na neurčito. Exporty můžeš používat bez časového omezení a kredity se za ně neodečítají.';
            } elseif (($until=Auth::subscribedUntil($account)) !== null) {
                $reply='Máš aktivní časový balíček do '.date('d.m.Y H:i',$until).'. Po dobu jeho platnosti máš exporty bez omezení podle pravidel časového balíčku.';
            } else {
                $parts=['Nemáš žádný <strong>aktivní časový balíček</strong>.','Na účtu máš <strong>'.number_format($balance,0,',',' ').' kreditů</strong>.'];
                try { $quota=Quota::check($account); } catch (Throwable $e) { $quota=[]; }
                $cost=(int)($quota['cost'] ?? Settings::int('credit_cost'));
                $limit=(int)($quota['limit'] ?? Settings::int('free_per_window_user'));
                $freeLeft=(int)($quota['free_left'] ?? 0);
                $cooldown=(int)($quota['cooldown'] ?? Settings::int('cooldown_user'));
                if($cost>0) $parts[]='Jeden export stojí <strong>'.$cost.' kredit'.($cost===1?'':'ů').'</strong>.';
                if(Settings::bool('free_enabled_user') && $limit>0) {
                    $parts[]='Zdarma máš <strong>'.$limit.' export'.($limit===1?'':'y').($cooldown>0?' každých '.max(1,(int)ceil($cooldown/60)).' minut':'').'</strong>. Aktuálně zbývá <strong>'.$freeLeft.'</strong>.';
                }
                $reply=implode('<br>',$parts);
            }
            $quick=['Moje balíčky','Moje objednávky','Moje kredity'];
        } elseif (preg_match('/moje\s+bal[ií]čky|zakoupen[ée]\s+bal[ií]čky|moje\s+balicky/', $q)) {
            // Only paid time packages that are still waiting for activation.
            $rows = Orders::waitingFor($uid);
            $parts=[];
            foreach($rows as $r){
                $value=Orders::durationLabel((int)$r['sub_days'], Lang::current()==='cs'?'cs':'en');
                $ref=htmlspecialchars((string)$r['reference'],ENT_QUOTES,'UTF-8');
                $href='order.php?ref='.rawurlencode((string)$r['reference']);
                $parts[]='<a class="support-package-purchased-row" href="'.$href.'" target="_blank" rel="noopener">'
                    .'<strong>'.$ref.'</strong><span>'.$value.'</span><em>neaktivovaný</em></a>';
            }

            if (!$parts) {
                $reply='Na účtu nemáš žádný zakoupený balíček k aktivaci.';
            } else {
                $reply='<div class="support-purchased-packages">'
                    .'<div class="support-orders-title">Zakoupené balíčky k aktivaci</div>'
                    .implode('',$parts)
                    .'</div>';
            }
            $quick=['Moje aktivní předplatné','Moje objednávky','Moje kredity'];
        } elseif (preg_match('/aktivn[ií]\s+p[rř]edplatn|p[rř]edplatn[eé]ní|subscription/', $q)) {
            // Give the user one complete account/export status instead of only
            // saying whether subscription_until is set.
            $st=$pdo->prepare('SELECT role, is_admin, subscription_until, credits FROM users WHERE id=?');
            $st->execute([$uid]);
            $account=$st->fetch() ?: [];

            $isPrivileged = Auth::isPrivileged($account);
            $until = Auth::subscribedUntil($account);
            $balance=(int)($account['credits'] ?? 0);

            $fmtCredits=number_format($balance,0,',',' ');
            $replyParts=[];

            if($isPrivileged){
                $replyParts[]='Máš aktivní časový balíček na neurčito. Exporty můžeš používat bez časového omezení a kredity se za ně neodečítají.';
            } elseif($until !== null){
                $replyParts[]='Máš aktivní časový balíček do '.date('d.m.Y H:i',$until).'. Po dobu jeho platnosti máš exporty bez omezení podle pravidel časového balíčku.';
            }else{
                $replyParts[]='Nemáš žádný <strong>aktivní časový balíček</strong>.';
                $replyParts[]='Na účtu máš <strong>'.$fmtCredits.' kreditů</strong>.';

                // Build the export limits directly here. Do not let an
                // unrelated quota/storage exception turn this account-status
                // answer into the generic "message failed" response.
                $quota = [];
                try {
                    $quota = Quota::check($account);
                } catch (Throwable $quotaError) {
                    $quota = [];
                }
                $cost=(int)($quota['cost'] ?? Settings::int('credit_cost'));
                $limit=(int)($quota['limit'] ?? Settings::int('free_per_window_user'));
                $freeLeft=(int)($quota['free_left'] ?? 0);
                $cooldown=(int)($quota['cooldown'] ?? Settings::int('cooldown_user'));
                $freeEnabled=Settings::bool('free_enabled_user');

                if($cost>0){
                    $replyParts[]='Jeden export stojí <strong>'.$cost.' kredit'.($cost===1?'':'ů').'</strong>.';
                } elseif(Settings::bool('credits_enabled')){
                    $replyParts[]='Jeden export se nyní neúčtuje kredity.';
                }

                if($freeEnabled && $limit>0 && $cooldown>0){
                    $minutes=max(1,(int)ceil($cooldown/60));
                    $replyParts[]='Zdarma máš <strong>'.$limit.' export'.($limit===1?'':'y').' každých '.$minutes.' minut</strong>.'
                        .' Aktuálně zbývá <strong>'.$freeLeft.'</strong>.';
                } elseif($freeEnabled && $limit>0){
                    $replyParts[]='Zdarma máš <strong>'.$limit.' export'.($limit===1?'':'y').'</strong>.';
                } else {
                    $replyParts[]='Bez aktivního časového balíčku nemáš nastavený bezplatný export.';
                }
            }

            $reply=implode('<br>',$replyParts);
            $quick=['Moje balíčky','Moje objednávky','Moje kredity'];
        } elseif (preg_match('/historie\s+objednáv|historie\s+objednav|všechny\s+objednáv|vsechny\s+objednav/', $q)) {
            $st=$pdo->prepare(
                'SELECT reference,status,credits,sub_days,price_cents,currency,created_at
                 FROM orders WHERE user_id=? ORDER BY id DESC'
            );
            $st->execute([$uid]);
            $rows=$st->fetchAll();
            if(!$rows){
                $reply='Na účtu zatím nemáš žádné objednávky.';
            }else{
                $labels=['pending'=>'čeká na platbu','accepted'=>'zpracovává se','paid'=>'zaplacená','cancelled'=>'zrušená','refund'=>'čeká na refundaci','refunded'=>'refundovaná'];
                $parts=[];
                foreach($rows as $r){
                    $status=$labels[(string)$r['status']]??(string)$r['status'];
                    $refRaw=(string)$r['reference'];
                    $ref=htmlspecialchars($refRaw,ENT_QUOTES,'UTF-8');
                    $href='order.php?ref='.rawurlencode($refRaw);
                    $parts[]='<a class="support-order-row" href="'.$href.'" target="_blank" rel="noopener">'
                        .'<strong>'.$ref.'</strong><span class="support-order-status">'.htmlspecialchars($status,ENT_QUOTES,'UTF-8').'</span>'
                        .'</a>';
                }
                $reply='<div class="support-orders"><div class="support-orders-title">Historie objednávek</div><div class="support-orders-grid">'.implode('',$parts).'</div></div>';
            }
            $quick=['Moje objednávky','Moje kredity','Nabídka kreditů / časového plánu'];
        } elseif (preg_match('/moje\s+objednáv|objedn[aá]vky/', $q)) {
            $st=$pdo->prepare(
                "SELECT reference,status,credits,sub_days,price_cents,currency,created_at
                 FROM orders WHERE user_id=? AND status IN ('pending','accepted','refund') ORDER BY id DESC"
            );
            $st->execute([$uid]);
            $rows=$st->fetchAll();
            if(!$rows){
                $reply='Momentálně nemáš žádnou otevřenou objednávku.';
            }else{
                $labels=['pending'=>'čeká na platbu','accepted'=>'zpracovává se','refund'=>'čeká na refundaci'];
                $parts=[];
                foreach($rows as $r){
                    $status=$labels[(string)$r['status']]??(string)$r['status'];
                    $refRaw=(string)$r['reference'];
                    $ref=htmlspecialchars($refRaw,ENT_QUOTES,'UTF-8');
                    $href='order.php?ref='.rawurlencode($refRaw);
                    $parts[]='<a class="support-order-row" href="'.$href.'" target="_blank" rel="noopener">'
                        .'<strong>'.$ref.'</strong><span class="support-order-status">'.htmlspecialchars($status,ENT_QUOTES,'UTF-8').'</span>'
                        .'</a>';
                }
                $reply='<div class="support-orders"><div class="support-orders-title">Otevřené objednávky</div><div class="support-orders-grid">'.implode('',$parts).'</div></div>';
            }
            $quick=['Historie objednávek','Moje balíčky','Moje kredity'];
        } elseif (preg_match('/moje\s+export|exporty|historie\s+export/', $q)) {
            $st=$pdo->prepare('SELECT format,boxes,credits_spent,created_at FROM exports WHERE user_id=? ORDER BY id DESC LIMIT 8');
            $st->execute([$uid]);
            $rows=$st->fetchAll();
            if(!$rows){
                $reply='Zatím tu nevidím žádný export.';
            }else{
                $parts=[];
                foreach($rows as $r){
                    $parts[]='<strong>'.htmlspecialchars(strtoupper((string)$r['format']),ENT_QUOTES,'UTF-8').'</strong> – '
                        .(int)$r['boxes'].' boxů – '.(int)$r['credits_spent'].' kreditů – '
                        .date('d.m.Y H:i',(int)$r['created_at']);
                }
                $reply='Poslední exporty:<br>• '.implode('<br>• ',$parts);
            }
            $quick=['Moje kredity','Moje objednávky','Moje balíčky'];
        } elseif (preg_match('/objednáv|objednav|zakáz|zakaz|order/', $q)) {
            if ($order) {
                $labels = [
                    'pending' => 'čeká na zpracování',
                    'accepted' => 'zpracovává se',
                    'paid' => 'zaplacená / dokončená',
                    'cancelled' => 'zrušená',
                    'refund' => 'čeká na vrácení',
                    'refunded' => 'vrácená',
                ];
                $status = $labels[(string)$order['status']] ?? (string)$order['status'];
                $reply = 'Podívám se na poslední objednávku. <strong>' .
                    htmlspecialchars((string)$order['reference'], ENT_QUOTES, 'UTF-8') .
                    '</strong> je aktuálně <strong>' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</strong>.';
                $quick[] = 'Kde jsou moje kredity?';
                $quick[] = 'Předat živému kolegovi';
            } else {
                $reply = 'Na účtu zatím nevidím žádnou objednávku. Pokud řešíš jiný problém, napiš mi ho a zkusím poradit.';
                $quick[] = 'Předat živému kolegovi';
            }
        } elseif (preg_match('/kredit|credits|zůstatek|zustatek/', $q)) {
            $reply = 'Aktuální zůstatek účtu je <strong>' . number_format($credits, 0, ',', ' ') . ' kreditů</strong>.';
            $quick[] = 'Moje objednávky';
        } elseif (preg_match('/platb|zaplat|payment/', $q)) {
            if ($order) {
                $labels = ['pending'=>'čeká na platbu','accepted'=>'zpracovává se','paid'=>'zaplaceno','cancelled'=>'zrušeno','refund'=>'čeká na refundaci','refunded'=>'refundováno'];
                $status = $labels[(string)$order['status']] ?? (string)$order['status'];
                $reply = 'U poslední objednávky <strong>' .
                    htmlspecialchars((string)$order['reference'], ENT_QUOTES, 'UTF-8') .
                    '</strong> vidím stav <strong>' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</strong>.';
            } else {
                $reply = 'Na účtu zatím nemám objednávku, podle které bych mohl platbu ověřit.';
            }
            $quick[] = 'Předat živému kolegovi';
        } elseif (preg_match('/admin|člověk|clovek|správ|sprav|pomoc/', $q)) {
            $reply = 'Jasně. Tuhle konverzaci můžu předat živému kolegovi. Potom mu můžeš napsat přímo.';
            $quick[] = 'Předat živému kolegovi';
        } elseif (in_array($q, ['promo akce','promo','akce'], true)) {
            // Promo akce means only currently active FREE packages.
            $stPromo = $pdo->query(
                'SELECT name, credits, sub_days, price_cents
                   FROM packages
                  WHERE active = 1 AND price_cents = 0
                  ORDER BY sort, credits, sub_days, id'
            );
            $rows = $stPromo->fetchAll();

            if (!$rows) {
                $reply = 'Aktuálně žádná akce neprobíhá.';
            } else {
                $parts = [];
                foreach ($rows as $p) {
                    $value = (int)$p['sub_days'] > 0
                        ? Orders::durationLabel((int)$p['sub_days'], Lang::current() === 'cs' ? 'cs' : 'en')
                        : Cred::fmt((int)$p['credits']) . ' kreditů';
                    $parts[] = '<strong>'.htmlspecialchars((string)$p['name'],ENT_QUOTES,'UTF-8').'</strong> – '
                        .htmlspecialchars($value,ENT_QUOTES,'UTF-8').' – <strong>zdarma</strong>';
                }
                $reply = 'Aktuální promo akce:<br>• '.implode('<br>• ',$parts)
                    .'<br><br>Podrobnosti a aktivaci najdeš v účtu v části balíčky.';
            }
            $quick[] = 'Moje kredity';
            $quick[] = 'Nabídka kreditů / časového plánu';
        } elseif (preg_match('/balíč|balick|nabíz|nabiz|cena|koupit/', $q)) {
            $rows = $pdo->query('SELECT name, credits, sub_days, price_cents FROM packages WHERE active = 1 ORDER BY sort, credits, sub_days, id')->fetchAll();
            if (!$rows) {
                $reply = 'Momentálně nemáme aktivní žádný balíček.';
            } else {
                $parts = [];
                foreach ($rows as $p) {
                    $value = (int)$p['sub_days'] > 0
                        ? Orders::durationLabel((int)$p['sub_days'], Lang::current() === 'cs' ? 'cs' : 'en')
                        : Cred::fmt((int)$p['credits']) . ' kreditů';
                    $price = (int)$p['price_cents'] === 0
                        ? 'zdarma'
                        : Money::fmt((int)$p['price_cents'], Settings::get('currency'));
                    $parts[] = '<strong>'.htmlspecialchars((string)$p['name'],ENT_QUOTES,'UTF-8').'</strong> – '
                        .htmlspecialchars($value,ENT_QUOTES,'UTF-8').' – '.htmlspecialchars($price,ENT_QUOTES,'UTF-8');
                }
                $reply = 'Aktuálně nabízíme:<br>• '.implode('<br>• ',$parts)
                    .'<br><br>Podrobnosti a aktivaci najdeš v účtu v části balíčky.';
            }
            $quick[] = 'Moje kredity';
            $quick[] = 'Nabídka kreditů / časového plánu';
        } elseif (preg_match('/podmín|podmin|terms|podmínky použití/', $q)) {
            $reply = 'Podmínky použití najdeš na stránce <a href="terms.php">Podmínky použití</a>. Pomohu ti také s objednávkou, kredity nebo exportem.';
            $quick[] = 'Jaké nabízíte balíčky?';
        } elseif (preg_match('/stl|3mf|export|slicer|stažen|stazen/', $q)) {
            $free = Settings::bool('free_enabled_user');
            $cost = Settings::int('credit_cost');
            $reply = 'Export podporuje 3MF a STL. Před tiskem doporučuji model projít ve sliceru. '
                . ($free ? 'Pro přihlášené uživatele jsou zapnuté volné exporty.' : 'Volné exporty jsou vypnuté.')
                . ($cost > 0 ? ' Po vyčerpání volných exportů je cena jednoho exportu '.$cost.' kreditů.' : '');
            $quick[] = 'Jaké nabízíte balíčky?';
            $quick[] = 'Předat živému kolegovi';
        } else {
            $reply = 'Nepodařilo se mi najít relevantní odpověď. omlouvám se.';
            $quick = ['Moje objednávky','Kolik mám kreditů?','Předat živému kolegovi'];
        }

        echo json_encode(['ok'=>true,'reply'=>$reply,'quick'=>$quick], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}



if (($_POST['action'] ?? '') === 'assistant_knowledge') {
    try {
        $lang = Lang::current();
        $cs = $lang === 'cs';
        $rows = Db::pdo()->query(
            'SELECT id, name, credits, sub_days, price_cents
             FROM packages WHERE active = 1
             ORDER BY sort, credits, sub_days, id'
        )->fetchAll();

        $packages = [];
        foreach ($rows as $p) {
            $packages[] = [
                'name'=>(string)$p['name'],
                'credits'=>(int)$p['credits'],
                'days'=>(int)$p['sub_days'],
                'price'=>(int)$p['price_cents'],
                'free'=>(int)$p['price_cents']===0,
            ];
        }

        $knowledge = [
            'site_name'=>(string)Settings::get('site_name'),
            'purchase_enabled'=>Settings::bool('purchase_enabled'),
            'purchase_instructions'=>(string)Settings::get('purchase_instructions'),
            'export_enabled'=>Settings::bool('credits_enabled') || Settings::bool('free_enabled_user'),
            'credit_cost'=>Settings::int('credit_cost'),
            'free_exports'=>Settings::bool('free_enabled_user'),
            'require_login'=>Settings::bool('require_login'),
            'terms_url'=>'terms.php',
            'packages'=>$packages,
            'news_title'=>(string)Settings::get($cs ? 'news_title' : 'news_title_en'),
            'news_body'=>(string)Settings::get($cs ? 'news_body' : 'news_body_en'),
            'custom_links'=>(string)Settings::get('custom_links'),
        ];
        echo json_encode(['ok'=>true,'knowledge'=>$knowledge], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>'Informace se nepodařilo načíst.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if (($_POST['action'] ?? '') === 'assistant_packages') {
    try {
        $uid = (int)$u['id'];
        $rows = Orders::waitingFor($uid);
        $items = [];
        foreach ($rows as $r) {
            $refRaw = (string)$r['reference'];
            $items[] = [
                'reference' => $refRaw,
                'href' => 'order.php?ref=' . rawurlencode($refRaw),
                'duration' => Orders::durationLabel((int)$r['sub_days'], Lang::current() === 'cs' ? 'cs' : 'en'),
                'paid_at' => !empty($r['paid_at']) ? date('d.m.Y H:i', (int)$r['paid_at']) : '',
            ];
        }
        echo json_encode([
            'ok' => true,
            'packages' => $items,
            'reply' => $items ? null : 'Na účtu nemáš žádný zakoupený balíček k aktivaci.',
            'quick' => ['Moje aktivní předplatné','Moje objednávky','Moje kredity'],
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>'Zakoupené balíčky se nepodařilo načíst.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if (($_POST['action'] ?? '') === 'assistant_catalog') {
    try {
        $rows = Db::pdo()->query(
            'SELECT id, name, credits, sub_days, price_cents
             FROM packages
             WHERE active = 1
             ORDER BY sort, credits, sub_days, id'
        )->fetchAll();

        $cur = Settings::get('currency') ?: 'EUR';
        $items = [];
        foreach ($rows as $p) {
            $pricing = Pricing::of($p);
            $items[] = [
                'id' => (int)$p['id'],
                'name' => (string)$p['name'],
                'credits' => (int)$p['credits'],
                'sub_days' => (int)$p['sub_days'],
                'price_cents' => (int)$p['price_cents'],
                'free' => (int)$p['price_cents'] === 0,
                'currency' => $cur,
                'discount' => [
                    'on' => (bool)$pricing['on'],
                    'percent' => (string)$pricing['percent'],
                    'final_cents' => (int)$pricing['final'],
                ],
            ];
        }
        $promo = Pricing::activePromo();
        echo json_encode([
            'ok'=>true,
            'packages'=>$items,
            'promo'=>$promo ? [
                'active'=>true,
                'label'=>(string)$promo['label'],
                'percent'=>Cred::fmtPercent((int)$promo['percent']),
                'until'=>(string)$promo['until'],
            ] : ['active'=>false],
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>'Nabídku balíčků se nepodařilo načíst.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if (($_POST['action'] ?? '') === 'assistant_status') {
    try {
        $active = UserMessages::activeForUser((int)$u['id']);
        if (!$active) {
            echo json_encode(['ok'=>true,'active'=>false], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $posts = UserMessages::postsFor((int)$active['id']);
        if (count($posts) > 20) $posts = array_slice($posts, -20);
        $last = $posts ? $posts[count($posts)-1] : null;
        $thread = array_map(static function(array $post): array {
            return [
                'author_kind'=>(string)$post['author_kind'],
                'body'=>(string)$post['body'],
                'created_at'=>(int)$post['created_at'],
            ];
        }, $posts);
        echo json_encode([
            'ok'=>true,
            'active'=>true,
            'message_id'=>(int)$active['id'],
            'last_author'=>$last ? (string)$last['author_kind'] : null,
            'last_body'=>$last ? (string)$last['body'] : null,
            'last_created'=>$last ? (int)$last['created_at'] : null,
            'posts'=>$thread,
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>'Stav konverzace se nepodařilo načíst.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}


if (($_POST['action'] ?? '') === 'assistant_archive_list') {
    try {
        $st = Db::pdo()->prepare(
            "SELECT m.id, m.subject, m.body, m.created_at, m.closed_at,
                    f.rating, f.comment AS rating_comment
             FROM user_messages m
             LEFT JOIN support_feedback f ON f.message_id = m.id
             WHERE m.user_id = ? AND m.closed_at IS NOT NULL
             ORDER BY m.closed_at DESC, m.id DESC"
        );
        $st->execute([(int)$u['id']]);
        $items=[];
        foreach($st->fetchAll() as $m){
            $posts=UserMessages::postsFor((int)$m['id']);
            $parts=[];
            $isLive = stripos((string)$m['subject'], 'živým kolegou') !== false
                || stripos((string)$m['subject'], 'live colleague') !== false;
            $body=(string)$m['body'];
            if($body!=='' && !$isLive) $parts[]=$body;
            foreach($posts as $post){
                $name=$post['author_kind']==='staff'?'Živý kolega':($post['author_kind']==='assistant'?'H3D Pomocník':'Já');
                $parts[]=$name.': '.(string)$post['body'];
            }
            $items[]=[
                'id'=>(int)$m['id'],
                'subject'=>(string)$m['subject'],
                'rating'=>(string)($m['rating']??''),
                'rating_comment'=>(string)($m['rating_comment']??''),
                'transcript'=>implode("\n\n",$parts),
                'closed_at'=>(int)$m['closed_at'],
            ];
        }
        echo json_encode(['ok'=>true,'items'=>$items],JSON_UNESCAPED_UNICODE);
    } catch(Throwable $e){
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>'Archiv se nepodařilo načíst.'],JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if (($_POST['action'] ?? '') === 'assistant_archive') {
    try {
        $body=trim((string)($_POST['transcript']??''));
        if ($body==='') $body='Konverzace s H3D Pomocníkem byla ukončena.';
        $subject=trim((string)($_POST['subject']??'H3D Pomocník'));
        if (mb_strlen($subject)>160) $subject=mb_substr($subject,0,157).'…';
        $active = UserMessages::activeForUser((int)$u['id']);
        if ($active) {
            $id=(int)$active['id'];
            // Keep the original live conversation and close it; do not create
            // a second ticket just because the assistant transcript was archived.
            UserMessages::closeByUser((int)$u['id'],$id);
        } else {
            $id=UserMessages::create((int)$u['id'],$subject,$body);
            UserMessages::closeByUser((int)$u['id'],$id);
        }
        echo json_encode(['ok'=>true,'id'=>$id], JSON_UNESCAPED_UNICODE);
    } catch(Throwable $e) {
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>'Archivaci se nepodařilo dokončit.'], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if (($_POST['action'] ?? '') === 'assistant_handoff') {
    try {
        $body = 'Uživatel požádal o předání z H3D Pomocníka. Zprávy uživatele budou do této konverzace zapisovány až po předání.';
        // Live-support conversations start with a neutral internal title.
        // The administrator can rename it later; the renamed title is shown
        // in the support inbox via admin_title.
        $subject = 'Nová zpráva';
        $id = UserMessages::create((int)$u['id'], $subject, $body);
        echo json_encode(['ok'=>true,'id'=>$id], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if (($_POST['action'] ?? '') === 'assistant_feedback') {
    try {
        $rating = (string)($_POST['rating'] ?? '');
        if (!in_array($rating, ['good','partial','bad'], true)) {
            throw new InvalidArgumentException('Neplatné hodnocení.');
        }
        $comment = trim((string)($_POST['comment'] ?? ''));
        if (mb_strlen($comment) > 2000) { $comment = mb_substr($comment, 0, 2000); }
        $messageId=(int)($_POST['message_id']??0);
        $st = Db::pdo()->prepare('INSERT INTO support_feedback (user_id, message_id, rating, comment, created_at) VALUES (?, ?, ?, ?, ?)');
        $st->execute([(int)$u['id'], $messageId>0?$messageId:null, $rating, $comment, time()]);
        echo json_encode(['ok'=>true], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

if (($_POST['action'] ?? '') === 'read_replies') {
    UserMessages::markRepliesRead((int) $u['id']);
    echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    exit;
}
if (($_POST['action'] ?? '') === 'reply_chat') {
    try {
        $userId=(int)$u['id'];
        $messageId=(int)($_POST['message_id'] ?? 0);
        $body=trim((string)($_POST['body'] ?? ''));
        if ($body==='' || mb_strlen($body)>2000) {
            throw new InvalidArgumentException('Zpráva je povinná a může mít nejvýše 2 000 znaků.');
        }
        $active=UserMessages::activeForUser($userId);
        if (!$active || (int)$active['id'] !== $messageId) {
            throw new InvalidArgumentException('Aktivní konverzace už není otevřená.');
        }
        $posts=UserMessages::postsFor($messageId);
        $streak=0;
        for($i=count($posts)-1;$i>=0;$i--){
            if((string)$posts[$i]['author_kind']!=='user') break;
            $streak++;
        }
        if($streak>=5){
            http_response_code(409);
            echo json_encode(['ok'=>false,'error'=>'Odeslal jsi 5 zpráv po sobě. Teď je potřeba počkat na odpověď živého kolegy.'],JSON_UNESCAPED_UNICODE);
            exit;
        }
        $ok = UserMessages::addUserReply($userId,$messageId,$body);
        if (!$ok) {
            http_response_code(409);
            echo json_encode([
                'ok' => false,
                'error' => 'Aktivní konverzace už není otevřená. Otevři Zprávy znovu a zkus to prosím ještě jednou.'
            ], JSON_UNESCAPED_UNICODE);
        } else {
            $active = UserMessages::activeForUser((int)$u['id']);
            echo json_encode([
                'ok' => true,
                'message_id' => $active ? (int)$active['id'] : 0
            ], JSON_UNESCAPED_UNICODE);
        }
    } catch (Throwable $e) {
        error_log('[H3D] reply_chat failed: '.$e->getMessage());
        http_response_code(422);
        echo json_encode([
            'ok' => false,
            'error' => 'Zprávu se nepodařilo odeslat. Zkus to prosím ještě jednou.'
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}
if (($_POST['action'] ?? '') === 'close_chat') {
    $ok = UserMessages::closeByUser((int) $u['id'], (int) ($_POST['message_id'] ?? 0));
    if (!$ok) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Aktivní konverzace už nebyla nalezena.'], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

try {
    $subject=(string)($_POST['subject']??'');
    $body=(string)($_POST['body']??'');
    $id=UserMessages::create((int)$u['id'],$subject,$body);
    echo json_encode(['ok'=>true,'id'=>$id], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['ok'=>false,'error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
}
