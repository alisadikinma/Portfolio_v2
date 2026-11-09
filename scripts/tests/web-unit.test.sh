#!/usr/bin/env bash
# Static checks for scripts/systemd/portfolio-web.service (PF-2 5D).
#   bash scripts/tests/web-unit.test.sh   -> exit 0 iff all pass
set -uo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
U="$here/../systemd/portfolio-web.service"
Q="$here/../systemd/portfolio-queue.service"
fails=0; n=0
check() { n=$((n+1)); if grep -qE "$2" "$U"; then echo "ok   $1"; else echo "FAIL $1 (no match: $2)"; fails=$((fails+1)); fi; }
check "MemoryMax=512M"            '^MemoryMax=512M$'
check "Restart=always"            '^Restart=always$'
check "HOST loopback"             '^Environment=HOST=127\.0\.0\.1$'
check "PORT 4321"                 '^Environment=PORT=4321$'
check "NODE_ENV production"       '^Environment=NODE_ENV=production$'
check "EnvironmentFile web/.env"  '^EnvironmentFile=/var/www/Portfolio_v2/web/\.env$'
check "WorkingDirectory web"      '^WorkingDirectory=/var/www/Portfolio_v2/web$'
check "ExecStart pins HOST/PORT after EnvironmentFile" '^ExecStart=/usr/bin/env HOST=127\.0\.0\.1 PORT=4321 /usr/bin/node dist/server/entry\.mjs$'
check "multi-user target"         '^WantedBy=multi-user\.target$'
qu="$(grep -E '^User=' "$Q" | head -1)"; qg="$(grep -E '^Group=' "$Q" | head -1)"
n=$((n+1)); if [[ -n "$qu" ]] && grep -qx "$qu" "$U" && grep -qx "$qg" "$U"; then echo "ok   same User/Group as queue unit ($qu)"; else echo "FAIL same User/Group as queue unit"; fails=$((fails+1)); fi
echo "$((n-fails))/$n passed"
[[ $fails -eq 0 ]]
