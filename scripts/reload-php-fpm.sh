#!/usr/bin/env bash
# reload-php-fpm.sh — PF-2. Reload php8.4-fpm after a deploy so opcache drops old code.
# No-op (exit 0) when the unit is missing or not active (dev boxes, old hosts).
# A failed reload only warns: opcache revalidates files every 2s anyway.
# Called by scripts/deploy.sh. Needs passwordless `sudo systemctl reload` (sudo -n).
set -uo pipefail
UNIT="php8.4-fpm"
# is-active is false for a missing unit and for a stopped one: one check covers both.
if ! systemctl is-active --quiet "$UNIT" 2>/dev/null; then
  echo "  ($UNIT not installed or not active — skipping reload)"; exit 0
fi
if sudo -n systemctl reload "$UNIT"; then
  echo "  reloaded $UNIT"
else
  echo "  WARN: could not reload $UNIT (needs passwordless sudo). Run: sudo systemctl reload $UNIT"
fi
exit 0
