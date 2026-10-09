<?php
// レガシーアプリからサーバー間で呼ばれる。共有シークレットで呼び出し元を認証し、
// 引換コードをユーザー情報に変換する
require __DIR__ . '/../store.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    exit;
}
$secret = getenv('BROKER_EXCHANGE_SECRET');
if (!hash_equals($secret, (string)($_POST['secret'] ?? ''))) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}
$claims = consumeExchangeCode((string)($_POST['code'] ?? ''));
if ($claims === null) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid_or_expired_code']);
    exit;
}
echo json_encode(['user' => $claims], JSON_UNESCAPED_UNICODE);
