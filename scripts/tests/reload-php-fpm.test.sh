#!/usr/bin/env bash
# Test for scripts/reload-php-fpm.sh guard. Fake `systemctl` + `sudo` record calls.
set -uo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SCRIPT="$here/../reload-php-fpm.sh"
tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
fails=0; n=0
mkbin() { # $1 dir, $2 = "active" | "inactive" | "nounit" | "reloadfail"
  mkdir -p "$1"
  cat > "$1/systemctl" <<SH
#!/bin/sh
echo "systemctl \$*" >> "$1/calls"
case "\$1" in
  is-active) [ "$2" = active ] || [ "$2" = reloadfail ] && exit 0; exit 3;;
  reload) [ "$2" = reloadfail ] && exit 1; exit 0;;
esac
SH
  printf '#!/bin/sh\n[ "$1" = "-n" ] && shift\nexec "$@"\n' > "$1/sudo"
  chmod +x "$1/systemctl" "$1/sudo"
}
check() { # name want_rc want_reload(yes|no) mode
  local name="$1" want_rc="$2" want_reload="$3" mode="$4" d="$tmp/case$((n+1))" out rc did=no
  mkbin "$d" "$mode"
  out="$(PATH="$d:/usr/bin:/bin" bash "$SCRIPT" 2>&1)"; rc=$?
  grep -q '^systemctl reload php8.4-fpm' "$d/calls" 2>/dev/null && did=yes
  n=$((n+1))
  if [[ $rc -eq $want_rc && $did == "$want_reload" ]]; then echo "ok   $name"
  else echo "FAIL $name: rc=$rc reload=$did out='$out'"; fails=$((fails+1)); fi
}
check "unit missing: no-op"        0 no  nounit
check "unit inactive: no-op"       0 no  inactive
check "unit active: reloads"       0 yes active
check "reload fails: warn, exit 0" 0 yes reloadfail
echo "$((n-fails))/$n passed"
[[ $fails -eq 0 ]]
