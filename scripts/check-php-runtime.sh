#!/usr/bin/env bash
# check-php-runtime.sh — PF-2 phase 0A/0C.
# Default: fail unless the `php` on PATH is 8.4.
#   check-php-runtime.sh              -> exit 0 iff php is 8.4
# Prints "check-php-runtime: expected 8.4, got X.Y" on mismatch.
set -euo pipefail
EXPECTED="8.4"
got="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
if [[ "$got" != "$EXPECTED" ]]; then
  echo "check-php-runtime: expected $EXPECTED, got $got"
  exit 1
fi
echo "check-php-runtime: OK php $got"
