<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Ory Hydra Admin API の login / consent 操作だけを薄く包む。
 * パスは spec/api.json（ory/hydra）の getOAuth2LoginRequest 等に対応する。
 */
class HydraAdmin
{
    private function client(): PendingRequest
    {
        return Http::baseUrl(config('hydra.admin_url'))->acceptJson()->timeout(5)->throw();
    }

    public function getLoginRequest(string $challenge): array
    {
        return $this->client()->get('/admin/oauth2/auth/requests/login', ['login_challenge' => $challenge])->json();
    }

    public function acceptLoginRequest(string $challenge, array $body): string
    {
        return $this->client()
            ->withQueryParameters(['login_challenge' => $challenge])
            ->put('/admin/oauth2/auth/requests/login/accept', $body)
            ->json('redirect_to');
    }

    public function rejectLoginRequest(string $challenge, string $error, string $description): string
    {
        return $this->client()
            ->withQueryParameters(['login_challenge' => $challenge])
            ->put('/admin/oauth2/auth/requests/login/reject', ['error' => $error, 'error_description' => $description])
            ->json('redirect_to');
    }

    public function getConsentRequest(string $challenge): array
    {
        return $this->client()->get('/admin/oauth2/auth/requests/consent', ['consent_challenge' => $challenge])->json();
    }

    public function acceptConsentRequest(string $challenge, array $body): string
    {
        return $this->client()
            ->withQueryParameters(['consent_challenge' => $challenge])
            ->put('/admin/oauth2/auth/requests/consent/accept', $body)
            ->json('redirect_to');
    }
}
