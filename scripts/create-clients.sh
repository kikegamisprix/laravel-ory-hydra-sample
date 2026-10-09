#!/usr/bin/env bash
# Hydra に rp-modern と broker の OAuth2 クライアントを登録し、各 .env に書き込む
# 前提: docker compose up -d 済みで hydra が起動していること
# 注意: --secret をコマンドライン引数で渡すため、実行中はプロセス一覧から見える（ローカル用途の前提）
set -euo pipefail
cd "$(dirname "$0")/.."

create() {
  local name="$1" redirect="$2" envfile="$3"
  local secret
  secret=$(openssl rand -hex 32)
  local json
  json=$(docker compose exec -T hydra hydra create client \
    --endpoint http://127.0.0.1:4445 \
    --name "$name" \
    --secret "$secret" \
    --grant-type authorization_code,refresh_token \
    --response-type code \
    --scope openid,offline,email,profile \
    --redirect-uri "$redirect" \
    --token-endpoint-auth-method client_secret_basic \
    --skip-consent \
    --format json)
  local client_id
  client_id=$(printf '%s' "$json" | docker run --rm -i alpine:3.20 sh -c "sed -n 's/.*\"client_id\":\"\([^\"]*\)\".*/\1/p'")
  if [ -z "$client_id" ]; then
    echo "failed to create client $name" >&2
    echo "$json" >&2
    exit 1
  fi
  {
    echo "OIDC_CLIENT_ID=$client_id"
    echo "OIDC_CLIENT_SECRET=$secret"
  } > "$envfile"
  echo "$name: client_id=$client_id -> $envfile"
}

create rp-modern http://localhost:8082/callback.php rp-modern/.env
create broker    http://localhost:8083/callback.php broker/.env

docker compose up -d --force-recreate rp-modern broker
echo "done"
