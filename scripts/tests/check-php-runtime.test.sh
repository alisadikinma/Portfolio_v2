#!/usr/bin/env bash
# Test for scripts/check-php-runtime.sh. Uses a fake `php` on PATH, no real PHP needed.
#   bash scripts/tests/check-php-runtime.test.sh   -> exit 0 iff all cases pass
set -uo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SCRIPT="$here/../check-php-runtime.sh"
BASH_BIN="$(command -v bash)"
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
fails=0; n=0

mkphp() { # $1 = version printed by the fake php
  mkdir -p "$tmp/bin-$1"
  printf '#!/bin/sh\necho "%s"\n' "$1" > "$tmp/bin-$1/php"; chmod +x "$tmp/bin-$1/php"
}
mkphp 8.4; mkphp 8.2
mkdir -p "$tmp/empty"

# expect NAME EXIT SUBSTRING PATHDIR ARGS...
expect() {
  local name="$1" want_rc="$2" want_msg="$3" pdir="$4"; shift 4
  local out rc syspath=":/usr/bin:/bin"
  [[ "$pdir" == "$tmp/empty" ]] && syspath=""   # no system dirs: a real php there would hide the case
  out="$(PATH="$pdir$syspath" "$BASH_BIN" "$SCRIPT" "$@" 2>&1)"; rc=$?
  n=$((n+1))
  if [[ $rc -eq $want_rc && "$out" == *"$want_msg"* ]]; then
    echo "ok   $name"
  else
    echo "FAIL $name: rc=$rc (want $want_rc) out='$out' (want substring '$want_msg')"; fails=$((fails+1))
  fi
}

printf 'location / {\n  fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;\n}\n' > "$tmp/ok.conf"
printf 'fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;\n' > "$tmp/old.conf"
printf '# fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;\n   # old php8.2-fpm.sock\nfastcgi_pass unix:/var/run/php/php8.4-fpm.sock;\n' > "$tmp/commented.conf"
printf '# only php8.4-fpm.sock in a comment\nlocation / {}\n' > "$tmp/onlycomment.conf"
printf 'fastcgi_pass unix:/var/run/php/php-fpm.sock;\n' > "$tmp/generic.conf"
printf 'server {}\n' > "$tmp/nosock.conf"

expect "cli 8.4 passes"                0 "OK php 8.4"                       "$tmp/bin-8.4"
expect "cli 8.2 fails"                 1 "expected 8.4, got 8.2"            "$tmp/bin-8.2"
expect "php not on PATH"               2 "check-php-runtime: php not on PATH" "$tmp/empty"
expect "compare ok"                    0 "OK php 8.4"                       "$tmp/bin-8.4" --compare "$tmp/ok.conf"
expect "compare mismatch"              1 "fpm 8.2 != cli 8.4"               "$tmp/bin-8.4" --compare "$tmp/old.conf"
expect "commented 8.2 line ignored"    0 "OK php 8.4"                       "$tmp/bin-8.4" --compare "$tmp/commented.conf"
expect "only commented socket"         2 "no fpm socket found"              "$tmp/bin-8.4" --compare "$tmp/onlycomment.conf"
expect "generic php-fpm.sock"          1 "generic php-fpm.sock"             "$tmp/bin-8.4" --compare "$tmp/generic.conf"
expect "no socket"                     2 "no fpm socket found"              "$tmp/bin-8.4" --compare "$tmp/nosock.conf"
expect "unreadable file"               2 "cannot read"                      "$tmp/bin-8.4" --compare "$tmp/missing.conf"
expect "multi-file, second mismatches" 1 "fpm 8.2 != cli 8.4"               "$tmp/bin-8.4" --compare "$tmp/ok.conf" "$tmp/old.conf"
expect "real repo template passes"     0 "OK php 8.4"                       "$tmp/bin-8.4" --compare

echo "$((n-fails))/$n passed"
[[ $fails -eq 0 ]]
