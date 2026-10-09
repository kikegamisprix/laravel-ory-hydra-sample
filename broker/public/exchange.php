<?php
// レガシーアプリからサーバー間で呼ばれる。アプリごとのシークレットで呼び出し元を認証し、
// そのアプリ向けに発行した引換コードだけをユーザー情報に変換する
require __DIR__ . '/../store.php';
require __DIR__ . '/../apps.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}
$app = appById((string) ($_POST['app_id'] ?? ''));
if ($app === null || !hash_equals((string) $app['secret'], (string) ($_POST['secret'] ?? ''))) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}
$claims = consumeExchangeCode($app['id'], (string) ($_POST['code'] ?? ''));
if ($claims === null) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_or_expired_code']);
    exit;
}
echo json_encode(['user' => $claims], JSON_UNESCAPED_UNICODE);
