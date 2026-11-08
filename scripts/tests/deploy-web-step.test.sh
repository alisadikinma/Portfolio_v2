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
call=$(grep -n 'deploy_web || web_failed=1' "$DEPLOY" | head -1 | cut -d: -f1)
[[ -n "$fe" && -n "$call" && "$call" -gt "$fe" ]] && ok "deploy_web is called after the frontend build" || bad "deploy_web is called after the frontend build"
has "keeps a rolling SPA backup"            "$src" "frontend/dist.prev"

# ---- dry-run ORDER: atomic build -> rotate -> restart -> health (PF-2 5D review) -------------
pos() { printf '%s' "$1" | grep -n -F -- "$2" | head -1 | cut -d: -f1; }
out="$(run_web)"
has "builds into web/dist.next"             "$out" "npm run build -- --outDir dist.next"
a="$(pos "$out" "npm ci")"; b="$(pos "$out" "--outDir dist.next")"; c="$(pos "$out" "mv dist.next dist")"
d="$(pos "$out" "systemctl restart")"; e="$(pos "$out" "curl -fsS")"; f="$(pos "$out" "mv dist dist.prev")"
[[ -n "$a" && -n "$b" && -n "$f" && -n "$c" && -n "$d" && -n "$e" \
   && "$a" -lt "$b" && "$b" -lt "$f" && "$f" -lt "$c" && "$c" -lt "$d" && "$d" -lt "$e" ]] \
  && ok "order: ci < build < dist->dist.prev < next->dist < restart < health" \
  || { bad "order: ci < build < dist->dist.prev < next->dist < restart < health"; echo "$out"; }

# ---- real execution with stubbed tools (exported functions) -----------------------------------
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
mk_tree() { # fresh fake project: scripts/deploy.sh copy, backend, frontend, web/.env, dist, node stub
  rm -rf "$T/p"; mkdir -p "$T/p/scripts" "$T/p/backend/storage" "$T/p/backend/bootstrap/cache" "$T/p/frontend" "$T/p/web/dist"
  cp "$DEPLOY" "$T/p/scripts/deploy.sh"; cp "$here/../reload-php-fpm.sh" "$T/p/scripts/"; echo env > "$T/p/web/.env"; echo OLD > "$T/p/web/dist/marker"
  printf '#!/bin/sh\necho "${FAKE_NODE_MAJOR:-22}"\n' > "$T/node22"; chmod +x "$T/node22"
  : > "$T/calls"
}
fake_tools() {
  # shellcheck disable=SC2317
  git()      { echo "git $*" >> "$T/calls"; case "$1" in rev-parse) echo abc123;; log) echo subj;; esac; return 0; }
  composer() { echo "composer $*" >> "$T/calls"; }
  php()      { echo "php $*" >> "$T/calls"; }
  node()     { echo 22; }
  npm() {
    echo "npm $*" >> "$T/calls"
    [[ "$*" == "ci"* && "${FAIL_NPM_CI:-0}" = 1 ]] && return 1
    if [[ "$1 $2" == "run build" ]]; then
      [[ "${FAIL_BUILD:-0}" = 1 ]] && return 1
      mkdir -p dist.next && echo NEW > dist.next/marker
    fi
    return 0
  }
  sudo()     { echo "sudo $*" >> "$T/calls"; [[ "$*" == *"restart portfolio-web"* && "${FAIL_RESTART_FIRST:-0}" = 1 && ! -e "$T/restarted" ]] && { : > "$T/restarted"; return 0; }; return 0; }
  curl()     { echo "curl $*" >> "$T/calls"; [[ "$(cat "$T/p/web/dist/marker" 2>/dev/null)" == "NEW" && "${HEALTH_FAIL_NEW:-0}" = 1 ]] && return 1; return 0; }
  sleep()    { :; }
  export -f git composer php node npm sudo curl sleep
}
deploy_run() { # env assignments as args; runs the fake tree's deploy.sh end to end
  fake_tools
  env T="$T" HOME="$T" DEPLOY_SKIP_FRONTEND=1 WEB_NODE_BIN="$T/node22" WEB_HEALTH_SLEEP=0 "$@" bash "$T/p/scripts/deploy.sh" 2>&1
}
calls() { cat "$T/calls"; }

# happy path
mk_tree; out="$(deploy_run)"; rc=$?
[[ $rc -eq 0 ]] && ok "happy path exits 0" || { bad "happy path exits 0 (rc=$rc)"; echo "$out"; }
[[ "$(cat "$T/p/web/dist/marker")" == "NEW" && "$(cat "$T/p/web/dist.prev/marker")" == "OLD" ]] \
  && ok "new build live in web/dist, previous kept in web/dist.prev" || bad "dist rotation"

# build failure: later steps still run, exit non-zero, old dist untouched
mk_tree; out="$(deploy_run FAIL_BUILD=1)"; rc=$?
[[ $rc -ne 0 ]] && ok "deploy_web build failure -> final exit non-zero" || bad "deploy_web build failure -> final exit non-zero"
c="$(calls)"
has "web failure: chown (step 6) still ran"        "$c" "sudo chown -R www-data:www-data"
has "web failure: queue:restart (step 7) still ran" "$c" "php artisan queue:restart"
has "web failure: clear message printed"           "$out" "Astro web deploy FAILED"
[[ "$(cat "$T/p/web/dist/marker")" == "OLD" ]] && ok "web failure: old web/dist untouched" || bad "web failure: old web/dist untouched"

# npm ci failure must stop the build (no `npm run build`)
mk_tree; out="$(deploy_run FAIL_NPM_CI=1)"; rc=$?
c="$(calls)"
[[ "$c" != *"npm run build -- --outDir"* ]] && ok "npm ci failure skips npm run build" || { bad "npm ci failure skips npm run build"; echo "$c"; }
[[ $rc -ne 0 ]] && ok "npm ci failure -> non-zero exit" || bad "npm ci failure -> non-zero exit"

# health failure on the new build -> auto-restore dist.prev, restart again, fail
mk_tree; out="$(deploy_run HEALTH_FAIL_NEW=1)"; rc=$?
[[ $rc -ne 0 ]] && ok "health failure -> non-zero exit" || bad "health failure -> non-zero exit"
[[ "$(cat "$T/p/web/dist/marker")" == "OLD" ]] && ok "health failure -> previous dist restored" || { bad "health failure -> previous dist restored"; echo "$out"; }
n_restart="$(calls | grep -c 'restart portfolio-web')"
[[ "$n_restart" -eq 2 ]] && ok "health failure -> service restarted a 2nd time on the old build" || bad "restart count $n_restart != 2"

# node check uses the unit's binary
mk_tree; out="$(deploy_run FAKE_NODE_MAJOR=20)"; rc=$?
has "node < 22 at WEB_NODE_BIN is rejected"   "$out" "needs node >= 22"

echo "$((n-fails))/$n passed"
[[ $fails -eq 0 ]]
