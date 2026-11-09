#!/usr/bin/env bash
# Repo hygiene guard (PF-2): no vendored deps, DB dumps, backups, generated
# reports or debug pages may be tracked.
#   bash scripts/tests/repo-hygiene.test.sh   -> exit 0 iff the tracked tree is clean
set -uo pipefail
cd "$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
fails=0; n=0
# check <label> <ERE matched against `git ls-files` paths>
check() {
  n=$((n+1))
  local hits; hits="$(git ls-files | grep -E "$2" || true)"
  if [[ -z "$hits" ]]; then echo "ok   $1"
  else
    fails=$((fails+1)); echo "FAIL $1 ($(printf '%s\n' "$hits" | wc -l | tr -d ' ') tracked):"
    printf '%s\n' "$hits" | head -5 | sed 's/^/       /'
  fi
}
check "no root vendor/"                 '^vendor/'
check "no tracked node_modules"         '(^|/)node_modules/'
check "no root-level .sql dump"         '^[^/]+\.sql$'
check "no .bak/.old/.orig files"        '\.(bak|old|orig)$'
check "no .backup-* files"              '\.backup-'
check "no public debug html"            '^backend/public/.*debug.*\.html$'
check "no one-off .sql in backend/database" '^backend/database/[^/]+\.sql$'
check "no tracked root .env"            '^\.env$'
check "no playwright-report output"     '(^|/)playwright-report/'
check "no playwright test-results"      '(^|/)test-results/'
check "no root composer/npm manifests (backend/frontend/web own theirs)" '^(composer\.(json|lock)|package(-lock)?\.json|playwright\.config\.js)$'
echo "$((n-fails))/$n passed"
[[ $fails -eq 0 ]]
