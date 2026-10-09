<?php
// Hydra の issuer はブラウザ向け URL（localhost:4444）、バックチャネルはコンテナ名（hydra:4444）。
// 両者が異なるため Discovery を使わず、エンドポイントを個別に指定する。
require __DIR__ . '/vendor/autoload.php';

use Jumbojett\OpenIDConnectClient;

function oidcClient(): OpenIDConnectClient
{
    $issuer = getenv('OIDC_ISSUER');
    $oidc = new OpenIDConnectClient($issuer, getenv('OIDC_CLIENT_ID'), getenv('OIDC_CLIENT_SECRET'), $issuer);
    $oidc->providerConfigParam([
        'issuer' => $issuer,
        'authorization_endpoint' => getenv('OIDC_AUTHORIZATION_ENDPOINT'),
        'token_endpoint' => getenv('OIDC_TOKEN_ENDPOINT'),
        'jwks_uri' => getenv('OIDC_JWKS_URI'),
        'userinfo_endpoint' => getenv('OIDC_USERINFO_ENDPOINT'),
        'token_endpoint_auth_methods_supported' => ['client_secret_basic'],
        'code_challenge_methods_supported' => ['S256'],
    ]);
    $oidc->setRedirectURL(getenv('OIDC_REDIRECT_URI'));
    $oidc->addScope(['openid', 'email', 'profile']);
    $oidc->setCodeChallengeMethod('S256');
    return $oidc;
}

function startSession(): void
{
    // 同じ localhost 上で複数の PHP アプリが動くので Cookie 名を分ける
    session_name('broker_session');
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}
