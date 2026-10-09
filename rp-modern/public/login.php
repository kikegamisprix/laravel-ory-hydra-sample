<?php
require __DIR__ . '/../oidc.php';
startSession();
$oidc = oidcClient();
// ?reauth=1 なら prompt=login を付け、SSO 済みでもパスワード入力を求める
if (($_GET['reauth'] ?? '') === '1') {
    $oidc->addAuthParam(['prompt' => 'login']);
}
// ?max_age=N なら N 秒より古い認証を認めない
if (ctype_digit((string) ($_GET['max_age'] ?? ''))) {
    $oidc->addAuthParam(['max_age' => (string) $_GET['max_age']]);
}
// 未認証なら state / nonce / PKCE を生成して Hydra の認可エンドポイントへリダイレクトする
$oidc->authenticate();
