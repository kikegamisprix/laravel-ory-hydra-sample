<?php
require __DIR__ . '/../oidc.php';
startSession();
$user = $_SESSION['user'] ?? null;
?>
<!doctype html>
<meta charset="utf-8">
<title>rp-modern</title>
<h1>rp-modern（OIDC 対応済みクライアント / PHP 8）</h1>
<?php if ($user): ?>
  <p>ログイン中: <?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES) ?></p>
  <pre><?= htmlspecialchars(json_encode($user, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?></pre>
  <p><a href="logout.php">ログアウト（このアプリのセッションのみ）</a> / <a href="login.php?reauth=1">再認証（prompt=login）</a> / <a href="login.php?max_age=10">再認証（max_age=10）</a></p>
<?php else: ?>
  <p>未ログイン</p>
  <p><a href="login.php">Hydra 経由でログイン</a></p>
<?php endif; ?>
<p><a href="http://localhost:8084/">rp-legacy へ</a></p>
