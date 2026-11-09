# PF-2 Astro cutover runbook (VPS)

Executed in PF-2 Phase 6A step 3(c): AFTER the PHP 8.4 runbook (`pf2-php84-upgrade.md`) and AFTER the merge/push deploy is green. Only with Ali's explicit OK. All times in this file are WIB (UTC+7).

What changes: public pages move from the Vue SPA to Astro (`web/`, node on `127.0.0.1:4321`, systemd `portfolio-web`). The Vue SPA stays for `/admin`, `/login`, `/newsletter/unsubscribe`. Laravel routes (`/api`, `/r/{code}`, `/storage`, `/uploads`) do not change. Rollback of this runbook is independent from the PHP 8.4 one.

## Read this first

- **Expected duration:** 20 minutes. **There is a real outage window.** Phase 5C deleted the Laravel prerender routes, and the old nginx still sends `/`, `/blog…`, `/projects/{slug}` and `/faq` to PHP-FPM. From the moment `deploy.sh` runs `git reset --hard` (script step 1) until you reload the new nginx (Step 6), those four path families return **Laravel 404**, and every other public SPA path serves the new admin-only SPA shell (no public routes). The window is the whole deploy (composer, migrate, two `npm` builds: several minutes), not just the part after the Actions run turns green.
  - Minimise it: run in a low-traffic hour; stage the new nginx file on the VPS before the push (Step 1b); start the push only when Steps 5-6 are one paste away; do not wait to "look around" after the deploy turns green.
  - It cannot be removed completely: swapping nginx first would send everything to an Astro that is not running yet (502).
- **Rollback is NOT "restore the old nginx file".** The old nginx alone leaves those four path families on Laravel 404. See the Rollback section: fast stopgap (R-A, minutes) and the real rollback (R-B, revert the merge and redeploy).
- **STOP and roll back (Rollback section) if:**
  - `nginx -t` fails;
  - `curl -fsS http://127.0.0.1:4321/id/faq` is not 200 after the deploy;
  - after the nginx reload, `https://alisadikinma.com/id/` is not 200 or `/admin` does not show the login screen;
  - `/api/health` is not OK.
- **Origin, not edge.** Cloudflare can serve cached 200s. Verify first on the origin: `curl -H 'Host: alisadikinma.com' http://127.0.0.1:8080/PATH`.
- **Log every step with its WIB time:**
  ```bash
  sudo mkdir -p /root/pf2-backup
  log() { echo "$(TZ=Asia/Jakarta date '+%F %T') WIB  $*" | sudo tee -a /root/pf2-backup/cutover.log; }
  ```
- Second SSH session: `sudo tail -F /var/log/nginx/error.log` and, after Step 3, `journalctl -u portfolio-web -f`.

## Routing after the swap

| Path | Served by |
|---|---|
| `/api/internal/*` (port 8080) | 404 (only reachable on loopback `127.0.0.1:8081`) |
| `/api/*`, `/r/{code}` | Laravel (php8.4-fpm) |
| `/storage/*`, `/uploads/*` | nginx files (as before) |
| `/_astro/*`, `/_image` | Astro |
| `/assets/*`, `/sw.js` | nginx files from `frontend/dist` (Vue build) |
| `/admin*`, `/login`, `/newsletter/unsubscribe` | Vue SPA `frontend/dist/index.html` |
| everything else (pages, `/robots.txt`, `/sitemap*.xml`, `/llms.txt`, `web/public` files) | Astro `127.0.0.1:4321` |

Loopback `127.0.0.1:8081` is the Astro-to-Laravel link: `/api/*`, `/storage/*`, `/uploads/*` only.

## Step 0: Pre-flight (read-only)

```bash
node -v                                            # must be v22.23.4 or newer (Astro engine floor)
php -v | head -1                                   # 8.4.x
systemctl is-active php8.4-fpm portfolio-queue     # active active
ls -d /var/www/Portfolio_v2/frontend/dist          # the current (old, public) SPA build
sudo ss -ltnp | grep -E ':(4321|8081)\b' || echo "4321/8081 free"
```

If `node -v` is older than 22, install node 22 for the deploy user (`claudesn`) first. The unit calls `/usr/bin/node`: confirm `command -v node` is that path or edit `ExecStart` in the installed unit.

## Step 1: Back up what rollback needs (BEFORE the push)

`deploy.sh` rebuilds `frontend/dist` as the admin-only SPA. The old public SPA exists only in the current build, so copy it now. `deploy.sh` never touches `dist.pre-pf2` (it keeps a separate rolling `dist.prev`).

```bash
cd /var/www/Portfolio_v2
sudo cp -a frontend/dist frontend/dist.pre-pf2
sudo cp -a /etc/nginx/sites-enabled/portfolio-8080 /root/pf2-backup/portfolio-8080.pre-astro
ls frontend/dist.pre-pf2/index.html /root/pf2-backup/portfolio-8080.pre-astro
log "Step 1 backups done"
```

Verify: both files exist. Rollback of this step: nothing to undo.

### Step 1b: Stage the new nginx file (BEFORE the push)

So Step 5 is a single copy, fetch the file from the pushed branch now (read-only for the live site):

```bash
git -C /var/www/Portfolio_v2 fetch origin feat/PF-2-portfolio-redesign-seo
git -C /var/www/Portfolio_v2 show origin/feat/PF-2-portfolio-redesign-seo:scripts/nginx/portfolio-8080.conf | sudo tee /root/pf2-backup/portfolio-8080.astro >/dev/null
grep -c portfolio_web /root/pf2-backup/portfolio-8080.astro      # 1 or more
```

(If the branch is already merged, the same file is `scripts/nginx/portfolio-8080.conf` in the tree.)

## Step 2: Environment files (BEFORE the push)

`web/` does not exist on the VPS until the merge, but untracked files survive `git reset --hard`, so create the env file now:

```bash
sudo -u claudesn mkdir -p /var/www/Portfolio_v2/web
TOKEN="$(openssl rand -hex 32)"
sudo -u claudesn tee /var/www/Portfolio_v2/web/.env >/dev/null <<EOF
API_INTERNAL_URL=http://127.0.0.1:8081/api
IMAGE_DIMS_ORIGIN=http://127.0.0.1:8081
SITE_URL=https://alisadikinma.com
GA4_MEASUREMENT_ID=<G-XXXXXXX from Ali, or leave empty>
INTERNAL_API_TOKEN=$TOKEN
HOST=127.0.0.1
PORT=4321
EOF
sudo chmod 600 /var/www/Portfolio_v2/web/.env
```

Put the **same** token in the Laravel env, then re-cache:

```bash
sudo -u claudesn grep -q '^INTERNAL_API_TOKEN=' /var/www/Portfolio_v2/backend/.env \
  && echo "already set: copy ITS value into web/.env instead" \
  || echo "INTERNAL_API_TOKEN=$TOKEN" | sudo -u claudesn tee -a /var/www/Portfolio_v2/backend/.env >/dev/null
# php artisan config:cache runs in the deploy (Step 4); do not run it by hand now.
```

Verify later (Step 4b): `grep -c '^INTERNAL_API_TOKEN=' backend/.env web/.env` prints 1 for each and the values match (compare without printing: `cmp -s <(grep ^INTERNAL_API_TOKEN= backend/.env) <(grep ^INTERNAL_API_TOKEN= web/.env) && echo same || echo DIFFERENT`).
Rollback: delete the line / file.

## Step 3: Install the systemd unit and sudoers rule (BEFORE the push)

```bash
# The unit file lives in the feature branch. Before the merge, copy it from your checkout
# (scp scripts/systemd/portfolio-web.service <vps>:/tmp/) or read it from the pushed branch:
#   git -C /var/www/Portfolio_v2 fetch origin feat/PF-2-portfolio-redesign-seo
#   git -C /var/www/Portfolio_v2 show origin/feat/PF-2-portfolio-redesign-seo:scripts/systemd/portfolio-web.service | sudo tee /tmp/portfolio-web.service
sudo cp /tmp/portfolio-web.service /etc/systemd/system/portfolio-web.service
sudo systemctl daemon-reload
sudo systemctl enable portfolio-web.service        # do NOT start yet: there is no dist/ build
echo 'claudesn ALL=(root) NOPASSWD: /usr/bin/systemctl restart portfolio-web' \
  | sudo tee /etc/sudoers.d/portfolio-web >/dev/null
sudo chmod 440 /etc/sudoers.d/portfolio-web && sudo visudo -cf /etc/sudoers.d/portfolio-web
sudo -u claudesn sudo -n -l | grep portfolio-web
log "Step 3 unit installed"
```

The unit must be installed before Step 4: otherwise `deploy_web` fails at `systemctl restart portfolio-web` and the Actions run turns red (the build itself is already done by then; install the unit, then re-run the workflow).

Verify: `systemctl cat portfolio-web | grep -E 'User=|MemoryMax=|ExecStart='`. Rollback: `sudo systemctl disable portfolio-web; sudo rm /etc/systemd/system/portfolio-web.service /etc/sudoers.d/portfolio-web; sudo systemctl daemon-reload`.

## Step 4: Deploy (merge + push; CI runs `scripts/deploy.sh`)

> **Lesson from the 2026-11-09 release (read before Step 4):**
> - `deploy_web`'s health check (`/id/faq` on `127.0.0.1:4321`) calls Laravel through the `127.0.0.1:8081` listener, which exists only in the NEW nginx file. Before Step 5 it answers 503, so the first deploy turns red even when the build is fine. Do Step 5-6 as soon as `systemctl is-active portfolio-web` is `active`, then re-run the workflow: it turns green.
> - Astro needs node `>=22.23.4` (`apt-get install --only-upgrade nodejs` on the nodesource repo). Check in Step 0.
> - Before the push, build from a clean `git archive HEAD web` copy: the root `.gitignore` drops `*.jpg/*.png/*.webp`, and `web/.gitignore` must re-include the site's own images.

Order inside the deploy: composer install, `migrate --force` (includes `2026_11_08_000001_add_slug_to_awards_table`: award slugs), `PageSectionSeeder` (deletes the old homepage ghost toggles; idempotent), cache refresh, frontend build (admin-only SPA, previous build saved as `frontend/dist.prev`), `deploy_web` (checks `/usr/bin/node` >= 22, `npm ci`, builds into `web/dist.next`, keeps the old build as `web/dist.prev`, swaps it into `web/dist`, `systemctl restart portfolio-web`, health check on `127.0.0.1:4321/id/faq`; if the health check fails it restores `web/dist.prev`, restarts again and fails), chown, `php8.4-fpm` reload, `queue:restart`. A `deploy_web` failure no longer aborts the script: chown, FPM reload and `queue:restart` still run, then the script exits non-zero with `Astro web deploy FAILED`.

Watch the GitHub Actions run. After it is green (4b):

```bash
systemctl is-active portfolio-web                                  # active
curl -fsS -o /dev/null -w '%{http_code}\n' http://127.0.0.1:4321/id/faq     # 200
curl -fsS http://127.0.0.1:4321/en | grep -c 'hx-h1-name'          # 1 or more
sudo -u claudesn php /var/www/Portfolio_v2/backend/artisan tinker --execute="echo \App\Models\Award::whereNull('slug')->count();"   # 0
log "Step 4 deploy green"
```

If `deploy_web` failed (the Actions run is red at that step), read `journalctl -u portfolio-web -n 50`; the public site is still in the outage window described under "Read this first". Fix the service (the previous web build is back in `web/dist` if the health check failed) and re-run the workflow; go to Step 5 only after `curl http://127.0.0.1:4321/id/faq` answers 200.

## Step 5: nginx swap (test before reload)

```bash
cd /var/www/Portfolio_v2
sudo cp /root/pf2-backup/portfolio-8080.astro /etc/nginx/sites-enabled/portfolio-8080   # staged in Step 1b
sudo nginx -t
log "Step 5 nginx -t ok"
```

The template includes `snippets/phpmyadmin.conf` (already on the VPS). If `nginx -t` fails: nothing was reloaded, so running nginx still has the old config. Put the old file back (`sudo cp /root/pf2-backup/portfolio-8080.pre-astro /etc/nginx/sites-enabled/portfolio-8080`), fix the staged file, and retry. The outage window is still open: if you cannot fix it in minutes, run R-A.

If you edit the live file instead of copying, keep the live-only lines (phpMyAdmin snippet is already referenced in the template).

## Step 6: Reload and verify on the origin

```bash
sudo systemctl reload nginx
log "Step 6 nginx reloaded"
o() { curl -sS -o /dev/null -w "$1  %{http_code}\n" -H 'Host: alisadikinma.com' "http://127.0.0.1:8080$1" "${@:2}"; }
o /id/
o /en/faq
o "$(curl -s -H 'Host: alisadikinma.com' http://127.0.0.1:8080/id/ | grep -o '/_astro/[^"]*\.css' | head -1)"
o /robots.txt
o /llms.txt
o /admin
o /sw.js
o /api/health
o /api/internal/crawler-hit -X POST      # want 404
o /r/zzzzzzz                            # want 404 from Laravel (a known code is 301)
```

All 200 except `/api/internal/crawler-hit` = 404 and `/r/zzzzzzz` = 404. `/sw.js` must be the kill-switch: `curl -s -H 'Host: alisadikinma.com' http://127.0.0.1:8080/sw.js | grep -c unregister` prints 1.

## Step 7: Post-cutover checks (through the edge)

```bash
cd /var/www/Portfolio_v2
node web/scripts/seo-gate.mjs --base https://alisadikinma.com --routes all      # exit 0
curl -s https://alisadikinma.com/sitemap.xml | head -20                          # lists /id/ and /en/ URLs
curl -s https://alisadikinma.com/sitemap-pages.xml | grep -c '<loc>https://alisadikinma.com/\(id\|en\)/'
mysql -u ali -p portfolio_v2 -e "SELECT COUNT(*) total, SUM(slug IS NULL) missing FROM awards;"   # missing = 0 or NULL
```

Also by hand: log in at `/admin` (login screen shows, dashboard loads), open one `/r/<known code>` (301), check `https://alisadikinma.com/id/pelatihan-ai`. `scripts/smoke-prod.sh` (Phase 6A) automates part of this.

The seo-gate reads the live sitemap. Local runs before the release showed "route missing from sitemap" because prod served the old sitemap format; after the deploy the sitemaps are PF-2 format, so this must pass now.

## Leftovers (harmless, note only)

- Production env may still have `SEO_SPA_SHELL_PATH` and `SEO_HTML_CACHE_TTL`: unused after PF-2 5C. Remove them from `backend/.env` at leisure, then `php artisan config:cache`.
- Cache keys `seo_html:*` stay in the cache until expiry. `php artisan cache:clear` (done in each deploy) removes them.
- `php8.2-fpm` stays installed until phpMyAdmin moves (see the PHP 8.4 runbook).
- Search Console / Bing: Ali submits the sitemap after Step 7.

## Rollback

Facts that shape every option:
- The backend is already the PF-2 backend: `SpaPrerenderController` routes are gone (only `/login` and `/r/{code}` remain in `backend/routes/web.php`). The pre-Astro nginx file sends `/`, `/blog…`, `/projects/{slug}` and `/faq` to those deleted routes, so **restoring that file alone gives Laravel 404 on those paths**.
- `frontend/dist` is now the admin-only SPA. The old public SPA exists only as `frontend/dist.pre-pf2` (Step 1).
- Do NOT run `deploy.sh` or push while `dist.pre-pf2` is in place under `dist`: the deploy rebuilds `frontend/dist` as admin-only.

### R-A: fast stopgap (minutes): old public SPA served client-side, no SSR

Result: public pages work again as the old SPA (no server-side HTML, so SEO is degraded until R-B). Needs `frontend/dist.pre-pf2` and the old nginx file.

```bash
cd /var/www/Portfolio_v2
# 1. Old nginx file, edited: DROP the prerender locations so those paths fall to the SPA fallback.
sudo cp /root/pf2-backup/portfolio-8080.pre-astro /root/pf2-backup/portfolio-8080.stopgap
sudo grep -n 'index.php' /root/pf2-backup/portfolio-8080.stopgap
```

In `/root/pf2-backup/portfolio-8080.stopgap`, delete these whole `location` blocks (they are the ones that `fastcgi_pass` to `index.php` for a page, not for `/api/`; the June 2026 SSR runbook `seo-geo-ssr-deploy.md` lists them): `location = /`, the `/blog` location(s), the `/projects/{slug}` location, `location = /faq`. Keep `/api/`, `/r/`, `/storage/`, `/uploads/`, the SPA `try_files $uri /index.html` fallback and everything else. If the old file has no `location = /` fallback to `index.html`, the `index index.html;` directive plus the SPA `location /` already serves `/`.

```bash
# 2. Check no prerender block is left, then swap in the old public SPA and reload.
sudo grep -n 'index.php' /root/pf2-backup/portfolio-8080.stopgap     # only the /api/ and /r/ blocks may remain
sudo cp /root/pf2-backup/portfolio-8080.stopgap /etc/nginx/sites-enabled/portfolio-8080
sudo mv frontend/dist frontend/dist.pf2-admin
sudo cp -a frontend/dist.pre-pf2 frontend/dist
sudo nginx -t && sudo systemctl reload nginx
log "ROLLBACK R-A done"
```

Verify on the origin: `curl -sS -o /dev/null -w '%{http_code}\n' -H 'Host: alisadikinma.com' http://127.0.0.1:8080/` is 200 and the body has the old SPA `<div id="app">`; same for `/blog`, `/projects/<slug>`, `/faq`; `/api/health` OK; `/admin` login works. If nginx -t fails: `sudo cp /root/pf2-backup/portfolio-8080.pre-astro /etc/nginx/sites-enabled/portfolio-8080` does NOT help (404s), fix the edit instead.

### R-B: real rollback: revert the PF-2 merge and redeploy

This restores the backend prerender routes, the public SPA source and the old `deploy.sh`.

```bash
# on your machine, in the repo
git checkout main && git pull
git revert -m 1 <PF-2 merge commit sha>
git push origin main        # CI runs scripts/deploy.sh (reverted version)
```

If the Actions runner cannot reach the VPS, run the reverted `scripts/deploy.sh` on the VPS by hand. Then on the VPS:

```bash
cd /var/www/Portfolio_v2
sudo cp /root/pf2-backup/portfolio-8080.pre-astro /etc/nginx/sites-enabled/portfolio-8080   # real old file, not the stopgap
sudo nginx -t && sudo systemctl reload nginx
sudo systemctl disable --now portfolio-web
log "ROLLBACK R-B done"
```

Order: if the site is down, do R-A first (minutes), then R-B, then restore the original nginx file as shown. R-B alone has the same kind of window as the cutover: until the revert deploy has run, the backend still lacks the prerender routes.

Caveats:
- The revert redeploy rebuilds `frontend/dist` from the restored SPA source, so `frontend/dist.pre-pf2` is only needed for R-A.
- Back to PF-2 afterwards: revert the revert, push, then Steps 5-6 again.
- Admin-only rollback (public Astro site fine, only the new admin build is bad): `sudo rm -rf frontend/dist && sudo cp -a frontend/dist.prev frontend/dist` (no nginx change).
- Astro-only rollback (the new web build is bad, the old one was fine): `deploy_web` already does it on a failed health check. By hand: `cd web && rm -rf dist && mv dist.prev dist && sudo systemctl restart portfolio-web`.

## Local verification (repo)

```bash
bash scripts/tests/deploy-web-step.test.sh     # deploy_web dry run + seeder step
bash scripts/tests/web-unit.test.sh            # portfolio-web.service
bash scripts/tests/nginx-routing.test.sh       # ordering, nginx -t, route behaviour (needs docker)
```
