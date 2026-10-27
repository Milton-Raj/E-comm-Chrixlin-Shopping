#!/usr/bin/env bash
# Deploys the Laravel API on Hostinger (run over SSH from ~/apps/ecom). DEPLOYMENT.md §5.
# Usage: bash deployment/hostinger/deploy.sh [git-ref]   (default: origin/main)
set -euo pipefail

REF="${1:-origin/main}"
APP_DIR="${APP_DIR:-$HOME/apps/ecom}"
PHP="${PHP_BIN:-php}"
COMPOSER="${COMPOSER_BIN:-composer}"
API_URL="${API_URL:-https://api.chrixlin.com}"
# The domain's web root only forwards to backend/public/index.php, so /storage must be linked there too.
API_WEB_ROOT="${API_WEB_ROOT:-$HOME/domains/chrixlin.com/public_html/api}"

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
if [ -d "$API_WEB_ROOT" ]; then
  if [ -L "$API_WEB_ROOT/storage" ] || [ ! -e "$API_WEB_ROOT/storage" ]; then
    ln -sfn "$APP_DIR/backend/storage/app/public" "$API_WEB_ROOT/storage"
  else
    echo "! $API_WEB_ROOT/storage exists and is not a link; left as is (public media will 404)" >&2
  fi
fi
"$PHP" artisan config:cache
"$PHP" artisan route:cache
"$PHP" artisan event:cache
"$PHP" artisan view:cache
"$PHP" artisan queue:restart || true

echo "→ Health check"
curl -fsS "$API_URL/api/v1/health" && echo && echo "✓ Deployed $(git rev-parse --short HEAD)"
