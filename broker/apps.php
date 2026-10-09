<?php
// ブローカーを利用するアプリの一覧。BROKER_APPS（JSON）で渡す
// [{"id":"rp-legacy","secret":"...","return_prefix":"http://localhost:8084/"}]
function apps(): array
{
    static $apps = null;
    if ($apps === null) {
        $apps = json_decode((string) getenv('BROKER_APPS'), true) ?: [];
    }
    return $apps;
}

// return_to の前方一致でアプリを特定する。該当なしなら null
function appForReturnTo(string $returnTo): ?array
{
    foreach (apps() as $app) {
        $prefix = $app['return_prefix'] ?? '';
        if ($prefix !== '' && str_starts_with($returnTo, $prefix)) {
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
