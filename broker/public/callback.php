<?php
// Hydra からの戻り。トークン交換と ID トークン検証はここで完結させ、
// レガシー側には短寿命・一回限りの引換コードだけを渡す
require __DIR__ . '/../oidc.php';
require __DIR__ . '/../store.php';
startSession();

$returnTo = $_SESSION['return_to'] ?? '';
if ($returnTo === '') {
    http_response_code(400);
    echo 'セッションに戻り先がありません';
    exit;
}

$oidc = oidcClient();
try {
    $oidc->authenticate();
} catch (Throwable $e) {
    http_response_code(400);
    echo 'ログインに失敗しました: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES);
    exit;
}
$claims = $oidc->getVerifiedClaims();
$code = issueExchangeCode([
    'sub' => $claims->sub ?? null,
    'email' => $claims->email ?? null,
    'name' => $claims->name ?? null,
]);
unset($_SESSION['return_to']);

$sep = str_contains($returnTo, '?') ? '&' : '?';
header('Location: ' . $returnTo . $sep . 'code=' . rawurlencode($code));
