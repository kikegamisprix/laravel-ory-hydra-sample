<?php
require __DIR__ . '/../oidc.php';
startSession();
// 未認証なら state / nonce / PKCE を生成して Hydra の認可エンドポイントへリダイレクトする
oidcClient()->authenticate();
