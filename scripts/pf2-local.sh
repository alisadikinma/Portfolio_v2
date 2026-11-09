#!/usr/bin/env bash
# PF-2 local review stack: the new site on http://localhost:8000 with a copy of prod data.
#
#   scripts/pf2-local.sh import   # load .local-stack/pf2-local.sql.gz into the local DB
#                                 # (WIPES the local pf2 DB first), migrate, neutralise
#   scripts/pf2-local.sh up       # render nginx from the prod template, build admin SPA,
#                                 # start everything, wait until healthy, smoke check
#   scripts/pf2-local.sh status   # container health + smoke check
#   scripts/pf2-local.sh logs [service]
#   scripts/pf2-local.sh down     # stop (data kept)
#
# Inputs in .local-stack/ (gitignored):
#   pf2-local.sql.gz   mysqldump of prod (no newsletters/contacts/sessions/tokens rows)
#   storage-public/    copy of prod backend/storage/app/public
#   uploads/           copy of prod backend/public/uploads
#
# Safety: nothing here writes to production. Mail = log driver, no queue worker, no
# scheduler, plain-text secrets in `settings` are blanked, Telegram/auto-publish off.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
LS="$ROOT/.local-stack"
COMPOSE=(docker compose -f "$ROOT/docker/pf2-local/docker-compose.yml")
DUMP="$LS/pf2-local.sql.gz"
BASE="http://localhost:8000"

log() { printf '\n== %s\n' "$*"; }
die() { printf 'ERROR: %s\n' "$*" >&2; exit 1; }

mysql_q() { docker exec -i pf2_local_mysql mysql -uroot -proot --default-character-set=utf8mb4 portfolio_v2 "$@" 2>&1 | grep -v "Using a password"; }

wait_healthy() { # container, max seconds
  local c="$1" max="$2" i=0
  until [[ "$(docker inspect -f '{{.State.Health.Status}}' "$c" 2>/dev/null)" == healthy ]]; do
    (( i++ >= max )) && die "$c not healthy after ${max}s — see: scripts/pf2-local.sh logs"
    sleep 1
  done
}

render_nginx() {
  bash "$ROOT/docker/pf2-local/render-nginx.sh" "$ROOT/scripts/nginx/portfolio-8080.conf" "$LS/nginx"
}

build_admin() {
  log "Admin SPA build (API = same origin /api, no GA4)"
  # Env vars set here win over frontend/.env.production, which points at PRODUCTION.
  ( cd "$ROOT/frontend" && VITE_API_BASE_URL=/api VITE_API_URL=/api VITE_GA4_MEASUREMENT_ID= \
      npx vite build --outDir "$LS/frontend-dist" --emptyOutDir >/dev/null )
  # The axios baseURL compiles to a bare quoted string; a quoted prod API URL means the
  # override failed. (Unquoted mentions, e.g. the OAuth help text in Settings, are fine.)
  grep -rqsE "[\"'\`]https://(www\.)?alisadikinma\.com/api/?[\"'\`]" "$LS/frontend-dist/assets" \
    && die "admin build still points at production API"
  grep -rqsE "baseURL:[\"'\`]/api[\"'\`]" "$LS/frontend-dist/assets" || die "admin build has no same-origin /api base URL"
  echo "ok"
}

neutralise() {
  log "Neutralise outbound integrations in the LOCAL db"
  mysql_q <<'SQL'
UPDATE settings SET value='log'   WHERE `key`='mail_mailer';
UPDATE settings SET value='false' WHERE `key` IN ('telegram_enabled','linkedin_auto_publish','linkedin_auto_approve_enabled','linkedin_telegram_schedule_enabled','telegram_repurpose_enabled','telegram_video_full_enabled');
UPDATE settings SET value=NULL
 WHERE (`key` LIKE '%token%' OR `key` LIKE '%secret%' OR `key` LIKE '%password%' OR `key` LIKE '%api_key%')
   AND value IS NOT NULL;
UPDATE scheduled_commands SET enabled=0;
SELECT CONCAT('settings blanked/forced: ', COUNT(*)) AS info FROM settings
 WHERE `key`='mail_mailer' OR value IS NULL;
SQL
}

cmd_import() {
  [[ -f "$DUMP" ]] || die "missing $DUMP"
  log "Start mysql"
  "${COMPOSE[@]}" up -d mysql
  wait_healthy pf2_local_mysql 120
  log "Recreate local DB portfolio_v2 (local container only)"
  docker exec pf2_local_mysql mysql -uroot -proot -e "DROP DATABASE IF EXISTS portfolio_v2; CREATE DATABASE portfolio_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL ON portfolio_v2.* TO 'portfolio'@'%';" 2>&1 | grep -v "Using a password" || true
  log "Import dump (image URLs rewritten to $BASE)"
  gzcat "$DUMP" \
    | sed -e 's#https://\(www\.\)\{0,1\}alisadikinma\.com/storage/#http://localhost:8000/storage/#g' \
          -e 's#https://\(www\.\)\{0,1\}alisadikinma\.com/uploads/#http://localhost:8000/uploads/#g' \
          -e 's#https:\\\\/\\\\/\(www\.\)\{0,1\}alisadikinma\.com\\\\/storage\\\\/#http:\\\\/\\\\/localhost:8000\\\\/storage\\\\/#g' \
          -e 's#https:\\\\/\\\\/\(www\.\)\{0,1\}alisadikinma\.com\\\\/uploads\\\\/#http:\\\\/\\\\/localhost:8000\\\\/uploads\\\\/#g' \
    | docker exec -i pf2_local_mysql mysql -uroot -proot --default-character-set=utf8mb4 portfolio_v2 2>&1 | grep -v "Using a password" || true
  log "Migrate to the PF-2 schema + homepage sections"
  "${COMPOSE[@]}" up -d backend
  docker exec pf2_local_backend php artisan migrate --force
  docker exec pf2_local_backend php artisan db:seed --class=PageSectionSeeder --force
  neutralise
  mysql_q -e "SELECT (SELECT COUNT(*) FROM posts) posts, (SELECT COUNT(*) FROM projects) projects, (SELECT COUNT(*) FROM awards WHERE slug IS NOT NULL) awards_with_slug, (SELECT COUNT(*) FROM users) users;"
}

smoke() {
  log "Smoke check $BASE"
  local fail=0 p code
  for p in /id /en /id/about /id/blog /id/projects /id/work /id/pelatihan-ai /id/pembicara-ai /id/faq /id/contact \
           /sitemap.xml /robots.txt /llms.txt /admin /login /api/health; do
    code="$(curl -s -o /dev/null -w '%{http_code}' "$BASE$p")"
    printf '  %-22s %s\n' "$p" "$code"
    [[ "$code" == 200 ]] || fail=1
  done
  code="$(curl -s -o /dev/null -w '%{http_code}' "$BASE/api/internal/crawler-hit")"
  printf '  %-22s %s (must be 404)\n' "/api/internal/*" "$code"; [[ "$code" == 404 ]] || fail=1
  img="$(curl -s "$BASE/id" | grep -oE 'http://localhost:8000/(storage|uploads)/[^"? ]+' | head -1 || true)"
  if [[ -n "$img" ]]; then
    code="$(curl -s -o /dev/null -w '%{http_code}' "$img")"; printf '  local image           %s  %s\n' "$code" "$img"
    [[ "$code" == 200 ]] || fail=1
  fi
  (( fail == 0 )) && echo "SMOKE OK" || { echo "SMOKE FAILED"; return 1; }
}

cmd_up() {
  [[ -d "$LS/storage-public" && -d "$LS/uploads" ]] || die "images missing in $LS (storage-public/, uploads/)"
  render_nginx
  build_admin
  log "Start stack"
  "${COMPOSE[@]}" up -d
  wait_healthy pf2_local_mysql 120
  echo "waiting for Astro (first start runs npm ci + build, a few minutes)…"
  wait_healthy pf2_local_web 900
  smoke
  printf '\nOpen: %s/id   Admin: %s/admin (prod login)\n' "$BASE" "$BASE"
}

case "${1:-}" in
  import) cmd_import ;;
  up)     cmd_up ;;
  status) "${COMPOSE[@]}" ps; smoke ;;
  logs)   shift; "${COMPOSE[@]}" logs --tail=200 -f "$@" ;;
  down)   "${COMPOSE[@]}" down ;;
  *) sed -n '2,12p' "$0"; exit 1 ;;
esac
