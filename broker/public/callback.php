<?php
// Hydra からの戻り。トークン交換と ID トークン検証はここで完結させ、
// レガシー側には短寿命・一回限りの引換コードと、預かった state だけを返す
require __DIR__ . '/../oidc.php';
require __DIR__ . '/../store.php';
startSession();

$pending = $_SESSION['pending'] ?? null;
unset($_SESSION['pending']);
if (!is_array($pending)) {
    http_response_code(400);
    echo 'セッションに開始情報がありません';
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
$code = issueExchangeCode($pending['app_id'], [
    'sub' => $claims->sub ?? null,
    'email' => $claims->email ?? null,
    'name' => $claims->name ?? null,
]);

$sep = str_contains($pending['return_to'], '?') ? '&' : '?';
header('Location: ' . $pending['return_to'] . $sep . http_build_query([
    'code' => $code,
    'state' => $pending['state'],
]));
