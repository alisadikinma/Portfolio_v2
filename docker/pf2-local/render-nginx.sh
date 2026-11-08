#!/usr/bin/env bash
# Render the PRODUCTION nginx template (scripts/nginx/portfolio-8080.conf) for the
# local docker stack. Only transport details change; every location block, its order
# and its headers stay exactly as shipped:
#   - PHP-FPM unix socket        -> backend:9000 (docker service)
#   - Astro upstream 127.0.0.1   -> web:4321     (docker service)
#   - loopback listener 127.0.0.1:8081 -> 8081   (reachable by the web container)
#   - Laravel forced Host/HTTPS  -> localhost:8000 over http, so URLs Laravel builds
#     point at this stack instead of production
# Usage: render-nginx.sh <template> <out-dir>
set -euo pipefail
tpl="${1:?template}"; out="${2:?out dir}"
mkdir -p "$out/snippets"
: > "$out/snippets/phpmyadmin.conf"   # prod-only include; empty locally
sed \
  -e 's#unix:/var/run/php/php8\.4-fpm\.sock#backend:9000#g' \
  -e 's#server 127\.0\.0\.1:4321;#server web:4321;#' \
  -e 's#listen 127\.0\.0\.1:8081;#listen 8081;#' \
  -e 's#fastcgi_param HTTP_HOST alisadikinma\.com;#fastcgi_param HTTP_HOST localhost:8000;#g' \
  -e 's#fastcgi_param HTTPS on;#fastcgi_param HTTPS "";#g' \
  -e '/^[[:space:]]*listen \[::\]:8080;/d' \
  "$tpl" > "$out/portfolio.conf"
# Fail loudly if the template drifted and a substitution silently did nothing.
grep -q 'fastcgi_pass backend:9000;' "$out/portfolio.conf"
grep -q 'server web:4321;' "$out/portfolio.conf"
grep -q 'listen 8081;' "$out/portfolio.conf"
! grep -q 'php8\.4-fpm\.sock' "$out/portfolio.conf"
! grep -q '127\.0\.0\.1:4321' "$out/portfolio.conf"
echo "rendered $out/portfolio.conf"
