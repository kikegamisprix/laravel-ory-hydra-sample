<?php
// 引換コードの保存先。サンプルでは SQLite。複数台なら Redis 等に置き換える
const EXCHANGE_CODE_TTL = 60;

function store(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . __DIR__ . '/data/broker.sqlite');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE IF NOT EXISTS exchange_codes (
            code TEXT PRIMARY KEY,
            claims TEXT NOT NULL,
            expires_at INTEGER NOT NULL,
            used INTEGER NOT NULL DEFAULT 0
        )');
    }
    return $pdo;
}

function issueExchangeCode(array $claims): string
{
    $code = bin2hex(random_bytes(32));
    $stmt = store()->prepare('INSERT INTO exchange_codes (code, claims, expires_at) VALUES (?, ?, ?)');
    $stmt->execute([$code, json_encode($claims, JSON_UNESCAPED_UNICODE), time() + EXCHANGE_CODE_TTL]);
    return $code;
}

// 一回限り。使用済みか期限切れなら null
function consumeExchangeCode(string $code): ?array
{
    $pdo = store();
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT claims, expires_at, used FROM exchange_codes WHERE code = ?');
    $stmt->execute([$code]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['used'] || $row['expires_at'] < time()) {
        $pdo->rollBack();
        return null;
    }
    $pdo->prepare('UPDATE exchange_codes SET used = 1 WHERE code = ?')->execute([$code]);
    $pdo->commit();
    return json_decode($row['claims'], true);
}
