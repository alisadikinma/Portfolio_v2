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
#   DEPLOY_SKIP_WEB=1 ./scripts/deploy.sh        # skip the Astro web/ build + restart
#   DEPLOY_DRY_RUN=1 (with DEPLOY_SOURCE_ONLY=1, tests only) prints deploy_web commands
#
# Triggered by .github/workflows/deploy.yml on push to main.
# ------------------------------------------------------------------------------

set -euo pipefail

# ---- Functions (sourceable for tests: DEPLOY_SOURCE_ONLY=1 source scripts/deploy.sh) --

# run: print the command; execute it unless DEPLOY_DRY_RUN=1.
run() {
  echo "  + $*"
  if [ "${DEPLOY_DRY_RUN:-0}" = "1" ]; then return 0; fi
  "$@"
}

# deploy_web — build the Astro public site (web/) and restart its systemd service.
# Prints nothing when DEPLOY_SKIP_WEB=1. DEPLOY_DRY_RUN=1 prints without executing.
# Needs: node >= 22, web/.env, the portfolio-web unit installed, and passwordless
# `sudo -n systemctl restart portfolio-web` (see docs/runbooks/pf2-astro-cutover.md).
deploy_web() {
  if [ "${DEPLOY_SKIP_WEB:-0}" = "1" ]; then return 0; fi
  local root; root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
  echo "▶ Astro web build + restart (portfolio-web)"
  if [ "${DEPLOY_DRY_RUN:-0}" != "1" ]; then
    local major; major="$(node -p 'process.versions.node.split(".")[0]' 2>/dev/null || echo 0)"
    if [ "$major" -lt 22 ]; then
      echo "❌ web/ needs node >= 22 (found: $major). Fix the deploy user's node, or DEPLOY_SKIP_WEB=1."
      return 1
    fi
    if [ ! -f "$root/web/.env" ]; then
      echo "❌ web/.env missing — create it first (runbook: docs/runbooks/pf2-astro-cutover.md)."
      return 1
    fi
  fi
  (
    cd "$root/web" || exit 1
    run npm ci --no-audit --no-fund --prefer-offline
    run npm run build
  ) || return 1
  run sudo -n systemctl restart portfolio-web || {
    echo "❌ could not restart portfolio-web (unit not installed, or no passwordless sudo)."
    return 1
  }
  # Health check: the service needs a moment to listen. Fail the deploy if it never answers.
  local i
  for i in 1 2 3 4 5 6 7 8 9 10; do
    if [ "${DEPLOY_DRY_RUN:-0}" = "1" ]; then
      echo "  + curl -fsS http://127.0.0.1:4321/id/faq -o /dev/null (retry up to 10x, 2s apart)"
      return 0
    fi
    if curl -fsS http://127.0.0.1:4321/id/faq -o /dev/null; then
      echo "  ✓ portfolio-web answers /id/faq"
      return 0
    fi
    sleep 2
  done
  echo "❌ portfolio-web did not answer http://127.0.0.1:4321/id/faq — check: journalctl -u portfolio-web -n 50"
  return 1
}

# Test seam: stop after defining functions, before touching git/composer/php.
if [ "${DEPLOY_SOURCE_ONLY:-0}" = "1" ]; then
  return 0 2>/dev/null || exit 0
fi

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

# PF-2: the public homepage is now Astro and reads page_sections by kebab-case
# section_type. This seeder deletes the obsolete snake_case homepage ghost rows
# (so admin toggles that do nothing disappear). Idempotent — safe to re-run.
echo "▶ php artisan db:seed PageSectionSeeder (idempotent)"
php artisan db:seed --class=PageSectionSeeder --force || \
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
  echo "▶ npm ci + npm run build (frontend — admin/login/unsubscribe SPA only after PF-2)"
  # Rolling backup of the previous SPA build, for a fast admin rollback.
  # The PRE-PF-2 (full public SPA) build is backed up by hand as frontend/dist.pre-pf2
  # (runbook step 1) — this script never touches that one.
  if [ -d frontend/dist ]; then
    rm -rf frontend/dist.prev
    cp -a frontend/dist frontend/dist.prev
  fi
  cd frontend
  npm ci --no-audit --no-fund --prefer-offline
  npm run build
  cd ..
fi

# ---- 5b. Astro public site (web/) -------------------------------------------
deploy_web

# ---- 6. Permissions (www-data owns storage + bootstrap/cache) ----------------
echo "▶ Fix storage permissions"
if command -v sudo >/dev/null 2>&1; then
  sudo chown -R www-data:www-data backend/storage backend/bootstrap/cache 2>/dev/null || \
    chown -R www-data:www-data backend/storage backend/bootstrap/cache 2>/dev/null || \
    echo "  (skipping chown — no sudo)"
fi
chmod -R 775 backend/storage backend/bootstrap/cache 2>/dev/null || true

# ---- 6b. FPM reload (opcache) ------------------------------------------------
# After PF-2 the web runtime is php8.4-fpm (NOT php8.2-fpm). No-op if the unit
# is missing or inactive. Never fails the deploy.
echo "▶ Reload php8.4-fpm (if active)"
bash scripts/reload-php-fpm.sh

# ---- 7. Queue worker restart (if supervisor/systemd) -------------------------
echo "▶ Signal queue workers to reload (if any)"
cd backend && php artisan queue:restart && cd ..

echo "✓ Deploy complete @ $(date -u +'%Y-%m-%dT%H:%M:%SZ')"
echo "  HEAD: $(git rev-parse --short HEAD)"
