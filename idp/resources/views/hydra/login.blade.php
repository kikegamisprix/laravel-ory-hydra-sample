@extends('hydra.layout')
@section('content')
<p class="muted">{{ $clientName }} がログインを求めています</p>
<form method="post" action="/login">
  @csrf
  <input type="hidden" name="login_challenge" value="{{ $challenge }}">
  <label for="email">メールアドレス</label>
  <input id="email" type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
  <label for="password">パスワード</label>
  <input id="password" type="password" name="password" autocomplete="current-password" required>
  @error('email')<p class="error">{{ $message }}</p>@enderror
  <button type="submit">ログイン</button>
</form>
@endsection
