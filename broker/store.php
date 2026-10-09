<?php
// 引換コードの保存先。サンプルでは SQLite。複数台なら Redis 等に置き換える
const EXCHANGE_CODE_TTL = 60;

function store(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . __DIR__ . '/data/broker.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        // 引換コードは 60 秒で消える使い捨てなので、スキーマが古ければ作り直してよい
        $cols = $pdo->query("PRAGMA table_info(exchange_codes)")->fetchAll(PDO::FETCH_COLUMN, 1);
        if ($cols !== [] && !in_array('app_id', $cols, true)) {
            $pdo->exec('DROP TABLE exchange_codes');
        }
        $pdo->exec('CREATE TABLE IF NOT EXISTS exchange_codes (
            code TEXT PRIMARY KEY,
            app_id TEXT NOT NULL,
            claims TEXT NOT NULL,
            expires_at INTEGER NOT NULL,
            used INTEGER NOT NULL DEFAULT 0
        )');
    }
    return $pdo;
}

// 発行先アプリを記録し、そのアプリ以外からは引き換えられないようにする
function issueExchangeCode(string $appId, array $claims): string
{
    $code = bin2hex(random_bytes(32));
    $stmt = store()->prepare('INSERT INTO exchange_codes (code, app_id, claims, expires_at) VALUES (?, ?, ?, ?)');
    $stmt->execute([$code, $appId, json_encode($claims, JSON_UNESCAPED_UNICODE), time() + EXCHANGE_CODE_TTL]);
    return $code;
}

// 一回限り。使用済み・期限切れ・発行先アプリ違いなら null
function consumeExchangeCode(string $appId, string $code): ?array
{
    $pdo = store();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT app_id, claims, expires_at, used FROM exchange_codes WHERE code = ?');
    $stmt->execute([$code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['used'] || $row['expires_at'] < time() || $row['app_id'] !== $appId) {
        $pdo->rollBack();
        return null;
    }
    $pdo->prepare('UPDATE exchange_codes SET used = 1 WHERE code = ?')->execute([$code]);
    $pdo->commit();
    return json_decode($row['claims'], true);
}
