@extends('hydra.layout')
@section('content')
<p>このアプリはポータル相当の IdP です。Hydra のログイン / 同意プロバイダとして動きます。</p>
@if ($user)
  <p>ポータルのセッション: ログイン中（{{ $user->email }}）</p>
  <form method="post" action="/logout">@csrf<button type="submit">ポータルからログアウト</button></form>
@else
  <p>ポータルのセッション: 未ログイン</p>
  <p class="muted">ログイン画面は Hydra から login_challenge 付きで呼ばれたときだけ表示されます。</p>
@endif
<p><a href="http://localhost:8082/">rp-modern</a> / <a href="http://localhost:8084/">rp-legacy</a></p>
@endsection
