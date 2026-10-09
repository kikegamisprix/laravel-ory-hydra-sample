<?php
require __DIR__ . '/../oidc.php';
startSession();
$oidc = oidcClient();
try {
    // code を token に交換し、ID トークンの署名・iss・aud・nonce を検証する
    $oidc->authenticate();
} catch (Throwable $e) {
    http_response_code(400);
    echo 'ログインに失敗しました: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES);
    exit;
}
session_regenerate_id(true);
$claims = $oidc->getVerifiedClaims();
$_SESSION['user'] = [
    'sub' => $claims->sub ?? null,
    'email' => $claims->email ?? null,
    'name' => $claims->name ?? null,
    'iss' => $claims->iss ?? null,
    'aud' => $claims->aud ?? null,
];
header('Location: /');
