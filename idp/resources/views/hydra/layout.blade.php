<!doctype html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ config('app.name') }}</title>
<style>
  body { font-family: system-ui, sans-serif; max-width: 480px; margin: 40px auto; padding: 0 16px; line-height: 1.6; }
  label { display: block; margin: 12px 0 4px; }
  input[type=email], input[type=password] { width: 100%; padding: 8px; box-sizing: border-box; }
  button { margin-top: 16px; padding: 8px 16px; }
  .error { color: #a00; }
  .muted { color: #666; font-size: .9em; }
</style>
</head>
<body>
<h1>{{ config('app.name') }}</h1>
@yield('content')
</body>
</html>
