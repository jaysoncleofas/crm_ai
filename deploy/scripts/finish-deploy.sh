#!/usr/bin/env bash
# Resume or repair a production deploy (run ON THE SERVER).
set -euo pipefail

cd "${1:-/var/www/production/crm}"

if [ ! -f .env ]; then
  echo "ERROR: .env missing. Run deploy/scripts/bootstrap-server.sh first." >&2
  exit 1
fi

dc() {
  docker compose -f docker-compose.prod.yml --env-file .env "$@"
}

# Dev .env synced by mistake will break MySQL.
if grep -qE '^DB_USERNAME=(root|)$' .env; then
  echo "Fixing DB_USERNAME=crm in .env"
  sed -i 's/^DB_USERNAME=.*/DB_USERNAME=crm/' .env
fi

echo "Rebuilding and starting..."
dc build app
dc build web
dc down
dc up -d

echo "Waiting for /up..."
for _ in $(seq 1 45); do
  if curl -fsS "http://127.0.0.1:${CRM_PORT:-8070}/up" >/dev/null 2>&1; then
    echo "Health OK"
    break
  fi
  sleep 3
done

if ! curl -fsS "http://127.0.0.1:${CRM_PORT:-8070}/up" >/dev/null 2>&1; then
  echo "ERROR: still unhealthy"
  dc ps -a
  dc logs --tail=40 mysql app web
  exit 1
fi

USER_COUNT="$(dc exec -T app php artisan tinker --execute="echo App\\Models\\User::count();" 2>/dev/null | tail -1 || echo 0)"
if [ "${USER_COUNT}" = "0" ]; then
  dc exec -T app php artisan db:seed --force
fi

if ! certbot certificates 2>/dev/null | grep -q crm.jaysoncleofas.com; then
  certbot --nginx -d crm.jaysoncleofas.com --non-interactive --agree-tos --register-unsafely-without-email || true
fi

dc ps
echo "Try: https://crm.jaysoncleofas.com"
