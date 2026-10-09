<?php
// Hydra からの戻り。トークン交換と ID トークン検証はここで完結させ、
// レガシー側には短寿命・一回限りの引換コードと、預かった state だけを返す。
// コードは URL に載せず、自動送信の POST フォームで渡す（アクセスログ・履歴・Referer に残さない）
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
$code = issueExchangeCode($pending['app_id'], $pending['state'], [
    'sub' => $claims->sub ?? null,
    'email' => $claims->email ?? null,
    'name' => $claims->name ?? null,
]);

header('Cache-Control: no-store');
$h = fn(string $v): string => htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
echo '<!doctype html><meta charset="utf-8"><title>redirecting</title>'
    . '<form id="f" method="post" action="' . $h($pending['return_to']) . '">'
    . '<input type="hidden" name="code" value="' . $h($code) . '">'
    . '<input type="hidden" name="state" value="' . $h($pending['state']) . '">'
    . '<button type="submit">続行</button>'
    . '</form><script>document.getElementById("f").submit();</script>';
