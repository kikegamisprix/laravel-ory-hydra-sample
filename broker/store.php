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
        if ($cols !== [] && !in_array('state_hash', $cols, true)) {
            $pdo->exec('DROP TABLE exchange_codes');
        }
        $pdo->exec('CREATE TABLE IF NOT EXISTS exchange_codes (
            code TEXT PRIMARY KEY,
            app_id TEXT NOT NULL,
            state_hash TEXT NOT NULL,
            claims TEXT NOT NULL,
            expires_at INTEGER NOT NULL,
            used INTEGER NOT NULL DEFAULT 0
        )');
    }
    return $pdo;
}

function stateHash(string $state): string
{
    return hash('sha256', $state);
}

// 発行先アプリと、アプリが生成した state のハッシュを一緒に記録する。
// 引き換え時にアプリはセッションに持つ state を送り、ここで照合する（PKCE と同じ考え方）。
// これで、盗まれたコードを攻撃者が自分のセッションで引き換えることはできない
function issueExchangeCode(string $appId, string $state, array $claims): string
{
    $code = bin2hex(random_bytes(32));
    $stmt = store()->prepare('INSERT INTO exchange_codes (code, app_id, state_hash, claims, expires_at) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$code, $appId, stateHash($state), json_encode($claims, JSON_UNESCAPED_UNICODE), time() + EXCHANGE_CODE_TTL]);
    return $code;
}

// 一回限り。条件付き UPDATE 1 回で使用済みにし、更新できた場合だけ claims を返す。
// SELECT してから UPDATE する書き方だと、保存先を変えたときに二重引き換えが起きる
function consumeExchangeCode(string $appId, string $state, string $code): ?array
{
    $pdo = store();
    $stmt = $pdo->prepare('UPDATE exchange_codes SET used = 1
        WHERE code = ? AND app_id = ? AND state_hash = ? AND used = 0 AND expires_at >= ?');
    $stmt->execute([$code, $appId, stateHash($state), time()]);
    if ($stmt->rowCount() !== 1) {
        return null;
    }
    $row = $pdo->prepare('SELECT claims FROM exchange_codes WHERE code = ?');
    $row->execute([$code]);
    return json_decode((string) $row->fetchColumn(), true);
}
