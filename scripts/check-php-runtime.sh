#!/usr/bin/env bash
# check-php-runtime.sh — PF-2 phase 0A/0C.
#   check-php-runtime.sh                       -> exit 0 iff php on PATH is 8.4
#   check-php-runtime.sh --compare [FILE...]   -> also require every php8.N-fpm.sock
#                                                 in the nginx conf FILEs (vhost +
#                                                 snippets, e.g. phpmyadmin.conf)
#                                                 to match the CLI version.
#                                                 Commented lines (# ...) are ignored.
#                                                 Default FILE: scripts/nginx/portfolio-8080.conf
# Exit codes:
#   0  OK
#   1  version mismatch: CLI is not 8.4, a socket differs from the CLI,
#      or a generic php-fpm.sock (no version) is used
#   2  cannot run the check: php not on PATH, FILE unreadable, or no socket found
# Messages: "check-php-runtime: expected 8.4, got X.Y"
#           "check-php-runtime: fpm X.Y != cli X.Y"
#           "check-php-runtime: generic php-fpm.sock in FILE (use php8.4-fpm.sock)"
#           "check-php-runtime: php not on PATH"
set -euo pipefail
EXPECTED="8.4"
if ! command -v php >/dev/null 2>&1; then
  echo "check-php-runtime: php not on PATH"
  exit 2
fi
got="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
if [[ "$got" != "$EXPECTED" ]]; then
  echo "check-php-runtime: expected $EXPECTED, got $got"
  exit 1
fi

if [[ "${1:-}" == "--compare" ]]; then
  shift
  root="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
  files=("$@")
  [[ ${#files[@]} -eq 0 ]] && files=("$root/scripts/nginx/portfolio-8080.conf")
  found=0
  for f in "${files[@]}"; do
    [[ -r "$f" ]] || { echo "check-php-runtime: cannot read $f"; exit 2; }
    # Drop commented lines first, so "# php8.2-fpm.sock" notes never count.
    active="$(grep -vE '^[[:space:]]*#' "$f" || true)"
    if grep -qE '(^|[^0-9.])php-fpm\.sock' <<<"$active"; then
      echo "check-php-runtime: generic php-fpm.sock in $f (use php$got-fpm.sock)"
      exit 1
    fi
    while IFS= read -r sock; do
      [[ -n "$sock" ]] || continue
      found=1
      fpm="${sock#php}"; fpm="${fpm%-fpm.sock}"
      if [[ "$fpm" != "$got" ]]; then
        echo "check-php-runtime: fpm $fpm != cli $got"
        exit 1
      fi
    done < <(grep -oE 'php[0-9]+\.[0-9]+-fpm\.sock' <<<"$active" || true)
  done
  [[ $found -eq 1 ]] || { echo "check-php-runtime: no fpm socket found"; exit 2; }
fi
echo "check-php-runtime: OK php $got"
