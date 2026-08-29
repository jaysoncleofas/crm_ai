#!/usr/bin/env bash
# First-time production bootstrap on 15.235.169.194 — run ON THE SERVER from the deploy root.
set -euo pipefail

DEPLOY_PATH="${1:-/var/www/production/crm}"
cd "$DEPLOY_PATH"

if [ ! -f docker-compose.prod.yml ]; then
  echo "ERROR: $DEPLOY_PATH does not look like a CRM deploy root." >&2
  exit 1
fi

dc() {
  if docker info >/dev/null 2>&1; then
    docker compose -f docker-compose.prod.yml --env-file .env "$@"
  else
    sudo docker compose -f docker-compose.prod.yml --env-file .env "$@"
  fi
}

if [ ! -f .env ]; then
  echo "Creating .env from .env.production.example"
  cp .env.production.example .env
  DB_PASS="$(openssl rand -base64 32 | tr -d '/+=' | head -c 32)"
  sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${DB_PASS}/" .env
  sed -i "s/^DB_ROOT_PASSWORD=.*/DB_ROOT_PASSWORD=${DB_PASS}/" .env
  APP_KEY="base64:$(openssl rand -base64 32)"
  sed -i "s/^APP_KEY=.*/APP_KEY=${APP_KEY}/" .env
fi

# MySQL Docker image rejects MYSQL_USER=root (never sync a dev .env with DB_USERNAME=root).
if grep -qE '^DB_USERNAME=(root|)$' .env; then
  sed -i 's/^DB_USERNAME=.*/DB_USERNAME=crm/' .env
fi

if [ ! -f gemini_credentials.json ]; then
  echo "WARNING: gemini_credentials.json missing — copy it before enabling Ask CRM." >&2
fi

echo "Installing nginx site..."
cp deploy/nginx/crm-security.conf /etc/nginx/snippets/crm-security.conf
cp deploy/nginx/crm.conf /etc/nginx/sites-available/crm
ln -sf /etc/nginx/sites-available/crm /etc/nginx/sites-enabled/crm
nginx -t && systemctl reload nginx

if ! certbot certificates 2>/dev/null | grep -q "crm.jaysoncleofas.com"; then
  echo "Requesting TLS certificate..."
  certbot --nginx -d crm.jaysoncleofas.com --non-interactive --agree-tos --register-unsafely-without-email || true
fi

echo "Building and starting containers..."
dc build app
dc build web
dc up -d

echo "Waiting for app..."
for _ in $(seq 1 45); do
  if curl -fsS "http://127.0.0.1:${CRM_PORT:-8070}/up" >/dev/null 2>&1; then
    break
  fi
  sleep 3
done

if ! curl -fsS "http://127.0.0.1:${CRM_PORT:-8070}/up" >/dev/null 2>&1; then
  echo "ERROR: health check failed"
  dc logs --tail=60 app mysql web
  exit 1
fi

USER_COUNT="$(dc exec -T app php artisan tinker --execute="echo App\\Models\\User::count();" 2>/dev/null | tail -1 || echo 0)"
if [ "${USER_COUNT}" = "0" ]; then
  echo "Seeding demo data..."
  dc exec -T app php artisan db:seed --force
fi

echo "CRM: https://crm.jaysoncleofas.com"
dc ps
