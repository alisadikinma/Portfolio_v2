#!/usr/bin/env bash
# ------------------------------------------------------------------------------
# Portfolio_v2 VPS deploy script.
#
# Runs ON the VPS, in the project root. Designed to be:
#   - Idempotent (safe to re-run)
#   - Non-interactive (for CI/CD)
#   - Fail-fast (halts on any error, with clear diagnostic output)
#
# Invocation:
#   ./scripts/deploy.sh                   # standard deploy
#   DEPLOY_SKIP_FRONTEND=1 ./scripts/deploy.sh   # skip `npm run build`
#   DEPLOY_SKIP_COMPOSER=1 ./scripts/deploy.sh   # skip composer install
#
# Triggered by .github/workflows/deploy.yml on push to main.
# ------------------------------------------------------------------------------

set -euo pipefail

# ---- 0. Load shell environment (non-interactive SSH skips these by default) --
# Tools like composer/npm/php/node often live in non-default PATH — must source
# login profiles to pick them up.
[ -f /etc/profile ]          && . /etc/profile           || true
[ -f "$HOME/.bashrc" ]       && . "$HOME/.bashrc"        || true
[ -f "$HOME/.profile" ]      && . "$HOME/.profile"       || true
[ -f "$HOME/.bash_profile" ] && . "$HOME/.bash_profile"  || true

# Extend PATH with common install locations so we don't depend on shell rc
export PATH="$HOME/.local/bin:$HOME/bin:/usr/local/bin:/usr/local/sbin:/usr/bin:/usr/sbin:/bin:/sbin:$PATH"

# Load NVM if present (Node.js via nvm is common on VPS installs)
if [ -s "$HOME/.nvm/nvm.sh" ]; then
  export NVM_DIR="$HOME/.nvm"
  # shellcheck disable=SC1091
  . "$NVM_DIR/nvm.sh"
fi

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_ROOT"

echo "▶ Deploying from $(pwd) @ $(date -u +'%Y-%m-%dT%H:%M:%SZ')"
echo "▶ User: $(whoami) | Host: $(hostname)"
echo "▶ PATH: $PATH"

# ---- 0.5. Preflight tool check — fail fast with actionable message -----------
echo "▶ Preflight tool check:"
missing_tools=()
for tool in git composer php node npm; do
  if path="$(command -v "$tool" 2>/dev/null)"; then
    printf "  ✓ %-10s %s\n" "$tool" "$path"
  else
    printf "  ✗ %-10s NOT FOUND\n" "$tool"
    missing_tools+=("$tool")
  fi
done

if [ ${#missing_tools[@]} -gt 0 ]; then
  echo ""
  echo "❌ Missing tools: ${missing_tools[*]}"
  echo "   Fix: ensure they're installed on the VPS and in the deploy user's PATH."
  echo "   Common install paths to check:"
  echo "     composer → /usr/local/bin/composer"
  echo "     npm/node → /usr/bin/, /usr/local/bin/, or via nvm in ~/.nvm/"
  echo "     php      → /usr/bin/php"
  echo ""
  echo "   Workarounds if tools exist but aren't in PATH:"
  echo "     1. Add to ~/.bashrc: export PATH=\"/path/to/tool:\$PATH\""
  echo "     2. Or set DEPLOY_SKIP_COMPOSER=1 / DEPLOY_SKIP_FRONTEND=1 to bypass"
  exit 127
fi

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
