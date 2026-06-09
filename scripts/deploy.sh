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
# IMPORTANT: disable `nounset` temporarily — system profile scripts (byobu,
# bash-completion, etc.) often reference unset vars which would trip `set -u`.
set +u
[ -f /etc/profile ]          && . /etc/profile           || true
[ -f "$HOME/.bashrc" ]       && . "$HOME/.bashrc"        || true
[ -f "$HOME/.profile" ]      && . "$HOME/.profile"       || true
[ -f "$HOME/.bash_profile" ] && . "$HOME/.bash_profile"  || true
set -u

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

# Idempotent seeders — safe to re-run (uses firstOrCreate, never clobbers admin values)
echo "▶ php artisan db:seed creator_brand (idempotent)"
php artisan db:seed --class=CreatorBrandSettingsSeeder --force || \
  echo "  (seeder failed or already applied — continuing)"

echo "▶ php artisan db:seed telegram (idempotent)"
php artisan db:seed --class=TelegramSettingsSeeder --force || \
  echo "  (seeder failed or already applied — continuing)"

# LinkedIn operator flags (incl. linkedin_force_carousel, June 9 2026).
# Idempotent via firstOrCreate — operator-edited values survive deploys.
echo "▶ php artisan db:seed linkedin (idempotent)"
php artisan db:seed --class=LinkedInSettingsSeeder --force || \
  echo "  (seeder failed or already applied — continuing)"

# Phase 6 partial — May 5: 4 LinkedIn-sourced testimonials. Idempotent
# via updateOrCreate keyed on (client_name, source). Safe to re-run.
echo "▶ php artisan db:seed linkedin_testimonials (idempotent)"
php artisan db:seed --class=LinkedInTestimonialsSeeder --force || \
  echo "  (seeder failed or already applied — continuing)"

# CV Master Export schema_version 2.0.0 (May 6, 2026): seeds summary_variants
# + work_experience + skills_matrix + education JSON keys consumed by
# /api/cv/export. Idempotent via firstOrCreate — operator-edited values
# survive deploys.
echo "▶ php artisan db:seed cv (idempotent)"
php artisan db:seed --class=CvSettingsSeeder --force || \
  echo "  (seeder failed or already applied — continuing)"

# Admin Scheduler (May 9, 2026): seeds 14 real artisan commands + 4 IG/FB/TikTok
# placeholder rows into scheduled_commands. DynamicScheduleRegistrar reads this
# table to materialize the cron schedule (replaces hardcoded routes/console.php
# entries). Idempotent via firstOrCreate — operator-edited cron expressions and
# enabled flags survive deploys.
echo "▶ php artisan db:seed scheduled_commands (idempotent)"
php artisan db:seed --class=ScheduledCommandSeeder --force || \
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
