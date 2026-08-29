#!/usr/bin/env bash
# Git pull + production deploy (run on the VPS — GitHub self-hosted runner or manual).
set -euo pipefail

DEPLOY_PATH="${DEPLOY_PATH:-/var/www/production/crm}"
REPO="${GITHUB_REPOSITORY:-jaysoncleofas/crm_ai}"
BRANCH="${DEPLOY_BRANCH:-main}"
TOKEN="${GITHUB_TOKEN:-${GIT_CLONE_TOKEN:-}}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

ORIGIN_PUBLIC="https://github.com/${REPO}.git"
if [ -n "$TOKEN" ]; then
  ORIGIN_AUTH="https://x-access-token:${TOKEN}@github.com/${REPO}.git"
else
  ORIGIN_AUTH="$ORIGIN_PUBLIC"
fi

mkdir -p "$DEPLOY_PATH"

ENV_BAK="$(mktemp -d)"
if [ -f "$DEPLOY_PATH/.env" ]; then
  cp "$DEPLOY_PATH/.env" "$ENV_BAK/.env"
fi
if [ -f "$DEPLOY_PATH/gemini_credentials.json" ]; then
  cp "$DEPLOY_PATH/gemini_credentials.json" "$ENV_BAK/gemini_credentials.json"
fi

if [ -d "$DEPLOY_PATH/.git" ]; then
  cd "$DEPLOY_PATH"
  git remote set-url origin "$ORIGIN_AUTH"
  git fetch --depth 1 origin "$BRANCH"
  git checkout -B "$BRANCH" "origin/$BRANCH"
  git reset --hard "origin/$BRANCH"
  git clean -fd -e .env -e gemini_credentials.json
  git remote set-url origin "$ORIGIN_PUBLIC"
else
  CLONE_DIR="$(mktemp -d)"
  git clone --branch "$BRANCH" --depth 1 "$ORIGIN_AUTH" "$CLONE_DIR/repo"
  cp -a "$CLONE_DIR/repo/." "$DEPLOY_PATH/"
  cd "$DEPLOY_PATH"
  git remote set-url origin "$ORIGIN_PUBLIC"
  rm -rf "$CLONE_DIR"
fi

if [ -f "$ENV_BAK/.env" ]; then
  cp "$ENV_BAK/.env" "$DEPLOY_PATH/.env"
fi
if [ -f "$ENV_BAK/gemini_credentials.json" ]; then
  cp "$ENV_BAK/gemini_credentials.json" "$DEPLOY_PATH/gemini_credentials.json"
fi
rm -rf "$ENV_BAK"

cd "$DEPLOY_PATH"

if [ ! -f .env ]; then
  echo "Creating production .env from template..."
  cp .env.production.example .env
  DB_PASS="$(openssl rand -base64 32 | tr -d '/+=' | head -c 32)"
  sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${DB_PASS}/" .env
  sed -i "s/^DB_ROOT_PASSWORD=.*/DB_ROOT_PASSWORD=${DB_PASS}/" .env
  APP_KEY="base64:$(openssl rand -base64 32)"
  sed -i "s/^APP_KEY=.*/APP_KEY=${APP_KEY}/" .env
fi

if grep -qE '^DB_USERNAME=(root|)$' .env; then
  sed -i 's/^DB_USERNAME=.*/DB_USERNAME=crm/' .env
fi

if [ -f deploy/nginx/crm.conf ]; then
  cp deploy/nginx/crm-security.conf /etc/nginx/snippets/crm-security.conf
  cp deploy/nginx/crm.conf /etc/nginx/sites-available/crm
  ln -sf /etc/nginx/sites-available/crm /etc/nginx/sites-enabled/crm
  nginx -t && systemctl reload nginx
fi

bash "$SCRIPT_DIR/finish-deploy.sh" "$DEPLOY_PATH"

echo "Deploy OK — $(git rev-parse --short HEAD)"
