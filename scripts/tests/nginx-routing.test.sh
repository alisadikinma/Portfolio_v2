#!/usr/bin/env bash
# Routing checks for scripts/nginx/portfolio-8080.conf (PF-2 5D).
#   bash scripts/tests/nginx-routing.test.sh   -> exit 0 iff all pass
# Static ordering/shape checks always run. `nginx -t` runs in an nginx:1.27-alpine container
# when docker is usable (stubs: snippets/phpmyadmin.conf); otherwise it is SKIPPED, loudly.
set -uo pipefail
here="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONF="$here/../nginx/portfolio-8080.conf"
fails=0; n=0
t() { n=$((n+1)); if eval "$2"; then echo "ok   $1"; else echo "FAIL $1"; fails=$((fails+1)); fi; }

# Line number of the first line matching an ERE (comments excluded), or empty.
ln() { grep -nE "$1" "$CONF" | grep -vE '^[0-9]+:[[:space:]]*#' | head -1 | cut -d: -f1; }
before() { local a b; a="$(ln "$1")"; b="$(ln "$2")"; [[ -n "$a" && -n "$b" && "$a" -lt "$b" ]]; }
nocomment() { grep -vE '^[[:space:]]*#' "$CONF"; }

t "astro assets: ^~ /_astro/ exists"            '[[ -n "$(ln "location \^~ /_astro/")" ]]'
t "astro image endpoint: = /_image exists"      '[[ -n "$(ln "location = /_image")" ]]'
t "^~ /_astro/ above every regex location"      'first_re="$(ln "location (~|~\*) ")"; a="$(ln "location \^~ /_astro/")"; [[ -n "$a" && -n "$first_re" && "$a" -lt "$first_re" ]]'
t "no extension-regex static block remains"     '! nocomment | grep -qE "location ~\*? .*\\\\\.\(css"'
t "/sw.js served from the SPA build root"       '[[ -n "$(ln "location = /sw.js")" ]] && nocomment | sed -n "/location = \/sw.js/,/}/p" | grep -q "frontend/dist"'
t "SPA assets: ^~ /assets/ from frontend/dist"  'nocomment | sed -n "/location \^~ \/assets\//,/}/p" | grep -q "frontend/dist"'
t "SPA routes: admin, login, unsubscribe"       'nocomment | grep -qE "location ~ \^/\(admin\|login\|newsletter/unsubscribe\)"'
t "SPA routes serve index.html in place"        'nocomment | sed -n "/location ~ \^\/(admin/,/}/p" | grep -q "try_files /index.html =404"'
t "/api/internal/ is 404 on the public server"  'nocomment | sed -n "/location \^~ \/api\/internal /,/}/p" | grep -q "return 404"'
t "/api/internal/ block above /api/"            'before "location \^~ /api/internal " "location \^~ /api/ "'
t "no dead 'fastcgi_param REQUEST_URI /api/\$1' (include fastcgi_params overrides it)" '! nocomment | grep -qF "fastcgi_param REQUEST_URI /api/\$1"'
t "/_astro/ sends one Cache-Control directive, no expires" 'blk="$(nocomment | sed -n "/location \^~ \/_astro\//,/^    }/p")"; [[ "$(echo "$blk" | grep -c "add_header Cache-Control")" -eq 1 ]] && ! echo "$blk" | grep -q "expires" && echo "$blk" | grep -q "X-Content-Type-Options"'
t "loopback server on 127.0.0.1:8081"           'nocomment | grep -qE "listen 127\.0\.0\.1:8081;"'
t "loopback server forces public Host + https"  'nocomment | grep -q "fastcgi_param HTTP_HOST alisadikinma.com;" && nocomment | grep -q "fastcgi_param HTTPS on;"'
t "location / proxies to astro"                 'nocomment | sed -n "/^    location \/ {/,/}/p" | grep -q "proxy_pass http://portfolio_web"'
t "proxy sends Host / XFF / XFP"                'p="$(nocomment | grep -c "proxy_set_header")"; [[ "$p" -ge 4 ]] && nocomment | grep -q "proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for" && nocomment | grep -q "proxy_set_header X-Forwarded-Proto"'
t "/r/{code} still goes to Laravel"             'nocomment | grep -qE "location ~ \"\^/r/"'
t "storage + uploads still served"              '[[ -n "$(ln "location \^~ /storage/ ")" && -n "$(ln "location \^~ /uploads/ ")" ]]'
t "no old static robots/favicon stubs for robots" '! nocomment | grep -qE "location = /robots.txt"'
t "php runtime is 8.4 only"                     '! nocomment | grep -q "php8.2"'

if command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1; then
  tmp="$(mktemp -d)"; trap 'rm -rf "$tmp"' EXIT
  mkdir -p "$tmp/snippets"; echo "# stub for test" > "$tmp/snippets/phpmyadmin.conf"
  n=$((n+1))
  out="$(docker run --rm \
      -v "$CONF":/etc/nginx/conf.d/default.conf:ro \
      -v "$tmp/snippets":/etc/nginx/snippets:ro \
      nginx:1.27-alpine nginx -t 2>&1)"; rc=$?
  echo "$out" | sed 's/^/     /'
  if [[ $rc -eq 0 ]]; then echo "ok   nginx -t (nginx:1.27-alpine)"; else echo "FAIL nginx -t"; fails=$((fails+1)); fi

  # Behaviour: run the real template with a fake Astro (nginx on 127.0.0.1:4321 answering
  # "ASTRO <uri>") and stub SPA/storage dirs. PHP-FPM is absent, so a route that reaches
  # Laravel answers 502 (the upstream marker) instead of ASTRO or an SPA file.
  mkdir -p "$tmp/spa/assets" "$tmp/storage"
  echo spa-index > "$tmp/spa/index.html"; echo killsw > "$tmp/spa/sw.js"; echo a > "$tmp/spa/assets/a.js"; echo f > "$tmp/storage/f.png"
  printf 'server { listen 127.0.0.1:4321; location / { return 200 "ASTRO $uri\\n"; } }\n' > "$tmp/fake.conf"
  cname="nginx-routing-test-$$"
  docker run -d --name "$cname" \
      -v "$CONF":/etc/nginx/conf.d/default.conf:ro -v "$tmp/fake.conf":/etc/nginx/conf.d/fake.conf:ro \
      -v "$tmp/snippets":/etc/nginx/snippets:ro -v "$tmp/spa":/var/www/Portfolio_v2/frontend/dist:ro \
      -v "$tmp/storage":/var/www/Portfolio_v2/backend/storage/app/public:ro nginx:1.27-alpine >/dev/null
  trap 'docker rm -f "$cname" >/dev/null 2>&1; rm -rf "$tmp"' EXIT
  sleep 2
  get() { docker exec "$cname" wget -S -O- -q "http://127.0.0.1:${2:-8080}$1" 2>&1; }
  status() { get "$@" | awk '/^ *HTTP\//{c=$2} END{print c}'; }
  body()   { get "$@" | grep -v '^ ' | head -1; }
  r() { n=$((n+1)); if [[ "$2" == "$3" ]]; then echo "ok   route $1 -> $3"; else echo "FAIL route $1: got '$2', want '$3'"; fails=$((fails+1)); fi; }
  r "/_astro/x.css"            "$(body /_astro/x.css)"          "ASTRO /_astro/x.css"
  r "/_image?href=a"           "$(body '/_image?href=a')"       "ASTRO /_image"
  r "/robots.txt"              "$(body /robots.txt)"            "ASTRO /robots.txt"
  r "/sitemap-pages.xml"       "$(body /sitemap-pages.xml)"     "ASTRO /sitemap-pages.xml"
  r "/llms.txt"                "$(body /llms.txt)"              "ASTRO /llms.txt"
  r "/en/faq"                  "$(body /en/faq)"                "ASTRO /en/faq"
  r "/images/p.png"            "$(body /images/p.png)"          "ASTRO /images/p.png"
  r "/administrator (not SPA)" "$(body /administrator)"         "ASTRO /administrator"
  r "/admin"                   "$(body /admin)"                 "spa-index"
  r "/admin/posts"             "$(body /admin/posts)"           "spa-index"
  r "/login"                   "$(body /login)"                 "spa-index"
  r "/newsletter/unsubscribe"  "$(body /newsletter/unsubscribe)" "spa-index"
  r "/assets/a.js"             "$(body /assets/a.js)"           "a"
  r "/sw.js"                   "$(body /sw.js)"                 "killsw"
  r "/storage/f.png"           "$(body /storage/f.png)"         "f"
  r "/api/health -> Laravel"   "$(status /api/health)"          "502"
  r "/r/abc1234 -> Laravel"    "$(status /r/abc1234)"           "502"
  r "/api/internal/x public"   "$(status /api/internal/crawler-hit)" "404"
  r "/api/internal (no slash)" "$(status /api/internal)"        "404"
  r "/api//internal/x"         "$(status /api//internal/x)"     "404"
  r "/api/%69nternal/x"        "$(status /api/%69nternal/x)"    "404"
  r "/api/x/../internal/y"     "$(status /api/x/../internal/y)" "404"
  cc="$(get /_astro/x.css | grep -ci '^ *cache-control:')"
  r "/_astro/ one Cache-Control header" "$cc" "1"
  r "/.env denied"             "$(status /.env)"                "403"
  r "8081 /api -> Laravel"     "$(status /api/internal/crawler-hit 8081)" "502"
  r "8081 /storage"            "$(body /storage/f.png 8081)"    "f"
else
  echo "SKIP nginx -t + route behaviour (docker not available)"
fi
echo "$((n-fails))/$n passed"
[[ $fails -eq 0 ]]
