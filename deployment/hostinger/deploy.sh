#!/usr/bin/env bash
# Deploys the Laravel API on Hostinger (run over SSH from ~/apps/ecom). DEPLOYMENT.md §5.
# Usage: bash deployment/hostinger/deploy.sh [git-ref]   (default: origin/main)
set -euo pipefail

REF="${1:-origin/main}"
APP_DIR="${APP_DIR:-$HOME/apps/ecom}"
PHP="${PHP_BIN:-php}"
COMPOSER="${COMPOSER_BIN:-composer}"
API_URL="${API_URL:-https://api.chrixlin.com}"

cd "$APP_DIR"
echo "→ Fetching $REF"
git fetch --prune origin
git checkout --force --detach "$REF"

cd backend
echo "→ Installing PHP dependencies"
"$PHP" "$(command -v "$COMPOSER")" install --no-dev --optimize-autoloader --no-interaction --prefer-dist

if [ ! -f .env ]; then
  echo "✗ backend/.env is missing. Copy deployment/hostinger/env.production.example to backend/.env and fill it in." >&2
  exit 1
fi

echo "→ Migrating the database"
"$PHP" artisan migrate --force

echo "→ Linking public storage and caching config"
"$PHP" artisan storage:link 2>/dev/null || true
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan event:cache
"$PHP" artisan view:cache
"$PHP" artisan queue:restart || true

echo "→ Health check"
curl -fsS "$API_URL/api/v1/health" && echo && echo "✓ Deployed $(git rev-parse --short HEAD)"
