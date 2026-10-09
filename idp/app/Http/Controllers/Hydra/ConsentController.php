<?php

namespace App\Http\Controllers\Hydra;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\HydraAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Hydra の同意プロバイダ。
 * クライアントに skip_consent が付いていれば consent request の skip が true で来るので、
 * 画面を出さずに受理する。ID トークンに入れる claim もここで決める。
 */
class ConsentController extends Controller
{
    public function __construct(private readonly HydraAdmin $hydra)
    {
    }

    public function show(Request $request): View|RedirectResponse
    {
        $challenge = (string) $request->query('consent_challenge', '');
        abort_if($challenge === '', 400, 'consent_challenge がありません');

        $consentRequest = $this->hydra->getConsentRequest($challenge);

        if (($consentRequest['skip'] ?? false) || ($consentRequest['client']['skip_consent'] ?? false)) {
            return redirect()->away($this->accept($challenge, $consentRequest, $consentRequest['requested_scope'] ?? []));
        }

        return view('hydra.consent', [
            'challenge' => $challenge,
            'clientName' => $consentRequest['client']['client_name'] ?? $consentRequest['client']['client_id'] ?? '',
            'scopes' => $consentRequest['requested_scope'] ?? [],
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'consent_challenge' => ['required', 'string'],
            'scopes' => ['array'],
            'scopes.*' => ['string'],
        ]);
        $consentRequest = $this->hydra->getConsentRequest($data['consent_challenge']);

        return redirect()->away($this->accept($data['consent_challenge'], $consentRequest, $data['scopes'] ?? []));
    }

    private function accept(string $challenge, array $consentRequest, array $grantScope): string
    {
        $user = User::find($consentRequest['subject'] ?? null);
        $idToken = [];
        if ($user !== null) {
            if (in_array('email', $grantScope, true)) {
                $idToken['email'] = $user->email;
            }
            if (in_array('profile', $grantScope, true)) {
                $idToken['name'] = $user->name;
            }
        }

        return $this->hydra->acceptConsentRequest($challenge, [
            'grant_scope' => $grantScope,
            'grant_access_token_audience' => $consentRequest['requested_access_token_audience'] ?? [],
            'remember' => true,
            'remember_for' => config('hydra.remember_for'),
            'session' => ['id_token' => (object) $idToken],
        ]);
    }
}
