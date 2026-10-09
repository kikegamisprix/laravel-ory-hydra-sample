<?php
require __DIR__ . '/../oidc.php';
startSession();
$oidc = oidcClient();
// ?reauth=1 なら prompt=login を付け、SSO 済みでもパスワード入力を求める
if (($_GET['reauth'] ?? '') === '1') {
    $oidc->addAuthParam(['prompt' => 'login']);
}
// 未認証なら state / nonce / PKCE を生成して Hydra の認可エンドポイントへリダイレクトする
$oidc->authenticate();
