<?php
// ブローカーを利用するアプリの一覧。BROKER_APPS（JSON）で渡す
// [{"id":"rp-legacy","secret":"...","return_to":"http://localhost:8084/callback.php"}]
function apps(): array
{
    static $apps = null;
    if ($apps === null) {
        $apps = json_decode((string) getenv('BROKER_APPS'), true) ?: [];
    }
    return $apps;
}

// 戻り先は完全一致。前方一致にするとレガシー側のオープンリダイレクト経由でコードが漏れる
function appForReturnTo(string $returnTo): ?array
{
    foreach (apps() as $app) {
        if (($app['return_to'] ?? '') === $returnTo) {
            return $app;
        }
    }
    return null;
}

function appById(string $id): ?array
{
    foreach (apps() as $app) {
        if (($app['id'] ?? '') === $id) {
            return $app;
        }
    }
    return null;
}
