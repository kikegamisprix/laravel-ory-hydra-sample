<?php
// ブローカーから引換コードを受け取り、サーバー間通信でユーザー情報に変換してセッションを作る
session_name('rp_legacy_session');
session_start();

$code = isset($_GET['code']) ? $_GET['code'] : '';
$state = isset($_GET['state']) ? $_GET['state'] : '';
$expected = isset($_SESSION['sso_state']) ? $_SESSION['sso_state'] : '';
unset($_SESSION['sso_state']);

if ($code === '' || $state === '' || $expected === '' || !hash_equals($expected, $state)) {
    http_response_code(400);
    echo 'state が一致しません。ログインをやり直してください';
    exit;
}

$ch = curl_init(getenv('BROKER_EXCHANGE_URL'));
curl_setopt_array($ch, array(
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(array(
        'app_id' => getenv('BROKER_APP_ID'),
        'secret' => getenv('BROKER_EXCHANGE_SECRET'),
        'code' => $code,
    )),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5,
));
$body = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$json = json_decode($body, true);
if ($status !== 200 || !isset($json['user']['sub'])) {
    http_response_code(400);
    echo '引換に失敗しました: ' . htmlspecialchars((string)$body, ENT_QUOTES);
    exit;
}

session_regenerate_id(true);
$_SESSION['user'] = $json['user'];
header('Location: /');
