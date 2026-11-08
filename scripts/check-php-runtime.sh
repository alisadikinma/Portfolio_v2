#!/usr/bin/env bash
# check-php-runtime.sh — PF-2 phase 0A/0C.
#   check-php-runtime.sh                       -> exit 0 iff php on PATH is 8.4
#   check-php-runtime.sh --compare [FILE...]   -> also require every php*-fpm.sock
#                                                 in the nginx conf FILEs (vhost +
#                                                 snippets, e.g. phpmyadmin.conf)
#                                                 to match the CLI version.
#                                                 Default FILE: scripts/nginx/portfolio-8080.conf
# Errors: "check-php-runtime: expected 8.4, got X.Y"
#         "check-php-runtime: fpm X.Y != cli X.Y"
set -euo pipefail
EXPECTED="8.4"
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
    while IFS= read -r sock; do
      found=1
      fpm="${sock#php}"; fpm="${fpm%-fpm.sock}"
      if [[ "$fpm" != "$got" ]]; then
        echo "check-php-runtime: fpm $fpm != cli $got"
        exit 1
      fi
    done < <(grep -o 'php[0-9.]*-fpm\.sock' "$f" || true)
  done
  [[ $found -eq 1 ]] || { echo "check-php-runtime: no fpm socket found"; exit 2; }
fi
echo "check-php-runtime: OK php $got"
