<?php

namespace App\Http\Controllers\Hydra;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** ポータル自身のログイン状態の確認とログアウト（Hydra のセッションは触らない） */
class SessionController extends Controller
{
    public function index(): View
    {
        return view('hydra.index', ['user' => Auth::user()]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
