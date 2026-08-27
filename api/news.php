<?php
declare(strict_types=1);
require __DIR__ . '/../lib/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$user=Auth::user();
if(!$user){http_response_code(401);echo json_encode(['ok'=>false]);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['ok'=>false]);exit;}
if (!Auth::csrfIsValid()) {
    http_response_code(419);
    echo json_encode([
        'ok'=>false,
        'csrf_refresh'=>Auth::csrfToken(),
        'error'=>'Invalid CSRF token.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
$version=max(0,(int)($_POST['version']??0));
$current=Settings::int('news_version');
if($version<1 || $version>$current){$version=$current;}
$seen = (int) Db::write(static function (PDO $pdo) use ($version, $user): int {
    $st = $pdo->prepare(
        'UPDATE users SET news_seen_version = CASE
            WHEN COALESCE(news_seen_version,0) < ? THEN ?
            ELSE COALESCE(news_seen_version,0)
         END
         WHERE id = ?'
    );
    $st->execute([$version, $version, (int)$user['id']]);
    $st = $pdo->prepare('SELECT news_seen_version FROM users WHERE id = ?');
    $st->execute([(int)$user['id']]);
    return (int)($st->fetchColumn() ?: 0);
});
echo json_encode(['ok'=>true,'version'=>$seen]);
