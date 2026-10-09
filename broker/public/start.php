<?php
// レガシーアプリから呼ばれる入口。戻り先を検証して保存し、OIDC の認可要求を開始する
require __DIR__ . '/../oidc.php';
startSession();

$returnTo = $_GET['return_to'] ?? '';
$allowed = getenv('ALLOWED_RETURN_PREFIX');
if ($returnTo === '' || strncmp($returnTo, $allowed, strlen($allowed)) !== 0) {
    http_response_code(400);
    echo 'return_to が許可されていません';
    exit;
}
$_SESSION['return_to'] = $returnTo;
oidcClient()->authenticate();
