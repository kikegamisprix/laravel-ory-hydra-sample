<?php
// ログイン開始。state を生成してセッションに置き、ブローカーに渡す。
// 戻ってきた state と突き合わせることで、他人が始めたログインの引換コードを
// 踏まされてその人としてログインしてしまう事故（ログイン CSRF）を防ぐ
session_name('rp_legacy_session');
session_start();

$state = bin2hex(openssl_random_pseudo_bytes(16));
$_SESSION['sso_state'] = $state;

$url = getenv('BROKER_START_URL')
    . '?return_to=' . rawurlencode(getenv('SELF_CALLBACK_URL'))
    . '&state=' . rawurlencode($state);
header('Location: ' . $url);
