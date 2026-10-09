<?php
// ブローカーから POST で引換コードを受け取り、サーバー間通信でユーザー情報に変換してセッションを作る
require __DIR__ . '/../session.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo 'POST のみ';
    exit;
}
$code = isset($_POST['code']) ? $_POST['code'] : '';
$state = isset($_POST['state']) ? $_POST['state'] : '';
$expected = isset($_SESSION['sso_state']) ? $_SESSION['sso_state'] : '';
unset($_SESSION['sso_state']);

if ($code === '') {
    http_response_code(400);
    echo 'code がありません';
    exit;
}
$stateOk = ($state !== '' && $expected !== '' && hash_equals($expected, $state));

// state が合わなくてもブローカーに送る。ブローカーは引き換え要求を受けた時点で
// コードを失効させるので、未使用のコードが残らない
$ch = curl_init(getenv('BROKER_EXCHANGE_URL'));
curl_setopt_array($ch, array(
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(array(
        'app_id' => getenv('BROKER_APP_ID'),
        'secret' => getenv('BROKER_EXCHANGE_SECRET'),
        'code' => $code,
        'state' => $expected,   // URL やフォームの値ではなくセッションの値を送る
    )),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5,
));
$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$json = json_decode($body, true);
if (!$stateOk || $status !== 200 || !isset($json['user']['sub'])) {
    http_response_code(400);
    echo 'ログインをやり直してください';
    exit;
}

session_regenerate_id(true);
$_SESSION['user'] = $json['user'];
header('Location: /');
