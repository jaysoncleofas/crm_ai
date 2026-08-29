#!/usr/bin/env bash
# Sync laravel/ to production (excludes .env, vendor, node_modules).
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
LARAVEL="$ROOT"
KEY="${HOME}/.ssh/fittrack_deploy_ed25519"
HOST="root@15.235.169.194"
REMOTE="/var/www/production/crm"

cd "$LARAVEL"
tar czf - \
  --exclude vendor --exclude node_modules --exclude .git \
  --exclude .env --exclude storage/logs --exclude test-results \
  . | ssh -i "$KEY" "$HOST" "mkdir -p $REMOTE && cd $REMOTE && tar xzf -"

if [ -f gemini_credentials.json ]; then
  scp -i "$KEY" gemini_credentials.json "$HOST:$REMOTE/gemini_credentials.json"
fi

echo "Synced to $HOST:$REMOTE"
echo "Next: ssh -i $KEY $HOST 'cd $REMOTE && bash deploy/scripts/finish-deploy.sh'"
