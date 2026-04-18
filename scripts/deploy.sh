#!/usr/bin/env bash
# ------------------------------------------------------------------------------
# Portfolio_v2 VPS deploy script.
#
# Runs ON the VPS, in the project root. Designed to be:
#   - Idempotent (safe to re-run)
#   - Non-interactive (for CI/CD)
#   - Fail-fast (halts on any error)
#
# Invocation:
#   ./scripts/deploy.sh                   # standard deploy
#   DEPLOY_SKIP_FRONTEND=1 ./scripts/deploy.sh   # skip `npm run build`
#   DEPLOY_SKIP_COMPOSER=1 ./scripts/deploy.sh   # skip composer install
#
# Triggered by .github/workflows/deploy.yml on push to main.
# ------------------------------------------------------------------------------

set -euo pipefail

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

echo "▶ Deploying from $(pwd) @ $(date -u +'%Y-%m-%dT%H:%M:%SZ')"

# ---- 1. Git sync ------------------------------------------------------------
echo "▶ git fetch + fast-forward origin/main"
git fetch origin main --quiet
git reset --hard origin/main
echo "  HEAD: $(git rev-parse --short HEAD) — $(git log -1 --pretty=format:%s)"

# ---- 2. Backend dependencies -------------------------------------------------
if [ "${DEPLOY_SKIP_COMPOSER:-0}" != "1" ]; then
  echo "▶ composer install --no-dev --optimize-autoloader"
  cd backend
  composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
  cd ..
fi

# ---- 3. Migrations + seeders -------------------------------------------------
echo "▶ php artisan migrate --force"
cd backend
php artisan migrate --force

# Idempotent seeders — safe to re-run (uses updateOrCreate)
echo "▶ php artisan db:seed creator_brand (idempotent)"
php artisan db:seed --class=CreatorBrandSettingsSeeder --force || \
  echo "  (seeder failed or already applied — continuing)"

# ---- 4. Cache refresh --------------------------------------------------------
echo "▶ Laravel cache refresh"
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan cache:clear

# Recache for production speed
php artisan config:cache
php artisan route:cache
php artisan view:cache

cd ..

# ---- 5. Frontend build -------------------------------------------------------
if [ "${DEPLOY_SKIP_FRONTEND:-0}" != "1" ]; then
  echo "▶ npm ci + npm run build (frontend)"
  cd frontend
  npm ci --no-audit --no-fund --prefer-offline
  npm run build
  cd ..
fi

# ---- 6. Permissions (www-data owns storage + bootstrap/cache) ----------------
echo "▶ Fix storage permissions"
if command -v sudo >/dev/null 2>&1; then
  sudo chown -R www-data:www-data backend/storage backend/bootstrap/cache 2>/dev/null || \
    chown -R www-data:www-data backend/storage backend/bootstrap/cache 2>/dev/null || \
    echo "  (skipping chown — no sudo)"
fi
chmod -R 775 backend/storage backend/bootstrap/cache 2>/dev/null || true

# ---- 7. Queue worker restart (if supervisor/systemd) -------------------------
echo "▶ Signal queue workers to reload (if any)"
cd backend && php artisan queue:restart && cd ..

echo "✓ Deploy complete @ $(date -u +'%Y-%m-%dT%H:%M:%SZ')"
echo "  HEAD: $(git rev-parse --short HEAD)"
