<?php

namespace App\Http\Controllers\Hydra;

use App\Http\Controllers\Controller;
use App\Services\HydraAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Hydra のログインプロバイダ。
 * Hydra は認可要求を受けると login_challenge を付けてここへリダイレクトする。
 */
class LoginController extends Controller
{
    public function __construct(private readonly HydraAdmin $hydra)
    {
    }

    public function show(Request $request): View|RedirectResponse
    {
        $challenge = (string) $request->query('login_challenge', '');
        abort_if($challenge === '', 400, 'login_challenge がありません');

        $loginRequest = $this->hydra->getLoginRequest($challenge);

        // Hydra 側にログインセッションが残っている場合は画面を出さずに受理する
        if ($loginRequest['skip'] ?? false) {
            return redirect()->away($this->hydra->acceptLoginRequest($challenge, [
                'subject' => $loginRequest['subject'],
            ]));
        }

        // ポータル自身のセッションでログイン済みなら、それをそのまま使う（SSO の要）
        if (Auth::check()) {
            return redirect()->away($this->accept($challenge, Auth::id()));
        }

        return view('hydra.login', [
            'challenge' => $challenge,
            'clientName' => $loginRequest['client']['client_name'] ?? $loginRequest['client']['client_id'] ?? '',
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'login_challenge' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt(['email' => $data['email'], 'password' => $data['password']])) {
            return back()->withInput($request->only('email', 'login_challenge'))
                ->withErrors(['email' => 'メールアドレスかパスワードが違います']);
        }
        $request->session()->regenerate();

        return redirect()->away($this->accept($data['login_challenge'], Auth::id()));
    }

    private function accept(string $challenge, int|string $userId): string
    {
        return $this->hydra->acceptLoginRequest($challenge, [
            // sub にはログイン ID やメールではなく不変の内部 ID を使う
            'subject' => (string) $userId,
            'remember' => true,
            'remember_for' => config('hydra.remember_for'),
        ]);
    }
}
