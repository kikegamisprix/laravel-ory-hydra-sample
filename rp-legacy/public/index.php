<?php
// PHP 5.6 想定。短縮配列や null 合体演算子は使わない
session_name('rp_legacy_session');
session_start();
$user = isset($_SESSION['user']) ? $_SESSION['user'] : null;
$loginUrl = getenv('BROKER_START_URL') . '?return_to=' . rawurlencode(getenv('SELF_CALLBACK_URL'));
?>
<!doctype html>
<meta charset="utf-8">
<title>rp-legacy</title>
<h1>rp-legacy（OIDC 非対応クライアント / PHP <?php echo htmlspecialchars(PHP_VERSION, ENT_QUOTES); ?>）</h1>
<?php if ($user): ?>
  <p>ログイン中: <?php echo htmlspecialchars($user['email'], ENT_QUOTES); ?></p>
  <pre><?php echo htmlspecialchars(json_encode($user), ENT_QUOTES); ?></pre>
  <p><a href="logout.php">ログアウト（このアプリのセッションのみ）</a></p>
<?php else: ?>
  <p>未ログイン</p>
  <p><a href="<?php echo htmlspecialchars($loginUrl, ENT_QUOTES); ?>">ブローカー経由でログイン</a></p>
<?php endif; ?>
<p><a href="http://localhost:8082/">rp-modern へ</a></p>
