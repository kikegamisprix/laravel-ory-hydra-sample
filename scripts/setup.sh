#!/usr/bin/env bash
# 秘密値を生成して .env を作る。既にある場合は上書きしない
set -euo pipefail
cd "$(dirname "$0")/.."

rand() { openssl rand -hex "$1"; }

if [ ! -f .env ]; then
  cat > .env <<ENV
POSTGRES_PASSWORD=$(rand 16)
HYDRA_SECRETS_SYSTEM=$(rand 32)
BROKER_EXCHANGE_SECRET=$(rand 32)
DEMO_USER_EMAIL=demo@example.com
DEMO_USER_PASSWORD=$(rand 8)
ENV
  echo "created .env"
else
  echo ".env exists, skipped"
fi

if [ ! -f idp/.env ]; then
  sed "s#^APP_KEY=.*#APP_KEY=base64:$(openssl rand -base64 32)#" idp/.env.example > idp/.env
  echo "created idp/.env"
else
  echo "idp/.env exists, skipped"
fi

for d in rp-modern broker; do
  if [ ! -f "$d/.env" ]; then
    cp "$d/.env.example" "$d/.env"
    echo "created $d/.env (client_id/secret は scripts/create-clients.sh で埋める)"
  fi
done

echo
echo "demo user: $(grep DEMO_USER_EMAIL .env | cut -d= -f2) / $(grep DEMO_USER_PASSWORD .env | cut -d= -f2)"
