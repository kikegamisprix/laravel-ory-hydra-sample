<?php
// レガシーアプリから呼ばれる入口。戻り先からアプリを特定し、
// アプリが生成した state を預かって OIDC の認可要求を開始する
require __DIR__ . '/../oidc.php';
require __DIR__ . '/../apps.php';
startSession();

$returnTo = (string) ($_GET['return_to'] ?? '');
$state = (string) ($_GET['state'] ?? '');
$app = $returnTo !== '' ? appForReturnTo($returnTo) : null;

if ($app === null) {
    http_response_code(400);
    echo 'return_to が許可されていません';
    exit;
}
if ($state === '' || strlen($state) > 128) {
    http_response_code(400);
    echo 'state がありません';
    exit;
}

$_SESSION['pending'] = ['app_id' => $app['id'], 'return_to' => $returnTo, 'state' => $state];
oidcClient()->authenticate();
