<?php

namespace App\Http\Controllers\Hydra;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\HydraAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Hydra のログインプロバイダ。
 * Hydra は認可要求を受けると login_challenge を付けてここへリダイレクトする。
 *
 * SSO のセッションは Laravel 側のもの。Hydra のログインセッション（remember）は
 * 実際にパスワードを入力したときだけ作る。Laravel セッションの再利用で Hydra に
 * 「今認証した」セッションを作ると、max_age の再認証要求がすり抜けるため。
 *
 * prompt=login、max_age 超過、subject 不一致は Hydra 自身が処理する（skip を立てない、
 * または prompt=login を付けて戻す）。ここで見るのは Laravel セッションを再利用する経路だけ
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

        // Hydra 側にログインセッションが残っている場合。画面は出さないが、
        // 台帳で無効化されたユーザーをそのまま通さないよう、有効性だけは確認する
        if ($loginRequest['skip'] ?? false) {
            $user = User::find($loginRequest['subject']);
            if ($user === null || ! $user->isActive()) {
                return redirect()->away($this->hydra->rejectLoginRequest(
                    $challenge, 'access_denied', 'このアカウントは無効です'
                ));
            }
            return redirect()->away($this->hydra->acceptLoginRequest($challenge, [
                'subject' => $loginRequest['subject'],
            ]));
        }

        // ポータル自身のセッションでログイン済みなら、それをそのまま使う（SSO の要）。
        // remember は付けない。Hydra 側に認証時刻の新しいセッションを作らないため
        if (Auth::check() && Auth::user()->isActive() && ! $this->reauthRequired($loginRequest, $request)) {
            return redirect()->away($this->hydra->acceptLoginRequest($challenge, [
                'subject' => (string) Auth::id(),
            ]));
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
            return $this->failed($request, 'メールアドレスかパスワードが違います');
        }
        if (! Auth::user()->isActive()) {
            Auth::logout();
            return $this->failed($request, 'このアカウントは無効です');
        }
        $request->session()->regenerate();
        $request->session()->put('auth_time', time());

        // パスワードを入力したときだけ Hydra 側にもログインセッションを残す
        return redirect()->away($this->hydra->acceptLoginRequest($data['login_challenge'], [
            // sub にはログイン ID やメールではなく不変の内部 ID を使う
            'subject' => (string) Auth::id(),
            'remember' => true,
            'remember_for' => config('hydra.remember_for'),
        ]));
    }

    private function failed(Request $request, string $message): RedirectResponse
    {
        return back()->withInput($request->only('email', 'login_challenge'))->withErrors(['email' => $message]);
    }

    /**
     * Laravel セッションを再利用してよいか。
     * 認可要求の prompt=login / max_age（request_url のクエリ。Request Object や PAR は見ていない）と、
     * 最後のパスワード入力からの絶対上限を見る
     */
    private function reauthRequired(array $loginRequest, Request $request): bool
    {
        $authTime = (int) $request->session()->get('auth_time', 0);
        if ($authTime === 0 || time() - $authTime > (int) config('hydra.max_sso_age')) {
            return true;
        }

        $query = [];
        parse_str((string) parse_url($loginRequest['request_url'] ?? '', PHP_URL_QUERY), $query);
        if (in_array('login', explode(' ', (string) ($query['prompt'] ?? '')), true)) {
            return true;
        }
        if (isset($query['max_age'])) {
            return time() - $authTime > (int) $query['max_age'];
        }
        return false;
    }
}
