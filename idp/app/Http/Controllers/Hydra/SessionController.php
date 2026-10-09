<?php

namespace App\Http\Controllers\Hydra;

use App\Http\Controllers\Controller;
use App\Services\HydraAdmin;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** ポータル自身のログイン状態の確認とログアウト */
class SessionController extends Controller
{
    public function __construct(private readonly HydraAdmin $hydra)
    {
    }

    public function index(): View
    {
        return view('hydra.index', ['user' => Auth::user()]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $subject = (string) Auth::id();
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // ポータルのログアウトを SSO のログアウトにする。
        // これを呼ばないと remember_for の間は他のアプリから再ログインなしで通ってしまう
        if ($subject !== '') {
            $this->hydra->revokeLoginSessions($subject);
        }

        return redirect('/');
    }
}
