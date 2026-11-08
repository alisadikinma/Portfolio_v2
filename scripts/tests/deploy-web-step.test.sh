#!/usr/bin/env bash
# Test for the deploy_web step and the PageSectionSeeder step in scripts/deploy.sh (PF-2 5D).
#   bash scripts/tests/deploy-web-step.test.sh   -> exit 0 iff all cases pass
# Sources deploy.sh with DEPLOY_SOURCE_ONLY=1 (functions only, nothing is deployed) and runs
# deploy_web with DEPLOY_DRY_RUN=1 (commands are printed, never executed).
set -uo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DEPLOY="$here/../deploy.sh"
fails=0; n=0
ok()   { n=$((n+1)); echo "ok   $1"; }
bad()  { n=$((n+1)); fails=$((fails+1)); echo "FAIL $1"; }
has()  { [[ "$2" == *"$3"* ]] && ok "$1" || { bad "$1 (missing '$3')"; echo "---- output:"; echo "$2"; echo "----"; }; }

run_web() { # extra env as args, e.g. DEPLOY_SKIP_WEB=1
  env DEPLOY_SOURCE_ONLY=1 DEPLOY_DRY_RUN=1 "$@" bash -c 'source "$1" && deploy_web' _ "$DEPLOY" 2>&1
}

out="$(run_web)"
has "dry run prints npm ci"                 "$out" "npm ci"
has "dry run prints npm run build"          "$out" "npm run build"
has "dry run prints systemctl restart"      "$out" "systemctl restart portfolio-web"
has "dry run prints health check"           "$out" "curl -fsS http://127.0.0.1:4321/id/faq"

out="$(run_web DEPLOY_SKIP_WEB=1)"
[[ -z "$out" ]] && ok "DEPLOY_SKIP_WEB=1 prints nothing" || { bad "DEPLOY_SKIP_WEB=1 prints nothing"; echo "$out"; }

# Static ordering checks on deploy.sh itself.
src="$(cat "$DEPLOY")"
has "seeds PageSectionSeeder"               "$src" "db:seed --class=PageSectionSeeder --force"
mig=$(grep -n 'php artisan migrate --force' "$DEPLOY" | head -1 | cut -d: -f1)
seed=$(grep -n 'PageSectionSeeder' "$DEPLOY" | head -1 | cut -d: -f1)
[[ -n "$mig" && -n "$seed" && "$seed" -gt "$mig" ]] && ok "PageSectionSeeder runs after migrate" || bad "PageSectionSeeder runs after migrate"
fe=$(grep -n 'npm ci + npm run build (frontend' "$DEPLOY" | head -1 | cut -d: -f1)
call=$(grep -n '^deploy_web$' "$DEPLOY" | head -1 | cut -d: -f1)
[[ -n "$fe" && -n "$call" && "$call" -gt "$fe" ]] && ok "deploy_web is called after the frontend build" || bad "deploy_web is called after the frontend build"
has "keeps a rolling SPA backup"            "$src" "frontend/dist.prev"

echo "$((n-fails))/$n passed"
[[ $fails -eq 0 ]]
