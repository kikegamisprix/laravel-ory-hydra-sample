@extends('hydra.layout')
@section('content')
<p>{{ $clientName }} が次の情報へのアクセスを求めています</p>
<form method="post" action="/consent">
  @csrf
  <input type="hidden" name="consent_challenge" value="{{ $challenge }}">
  @foreach ($scopes as $scope)
    <label><input type="checkbox" name="scopes[]" value="{{ $scope }}" checked> {{ $scope }}</label>
  @endforeach
  <button type="submit">許可する</button>
</form>
@endsection
