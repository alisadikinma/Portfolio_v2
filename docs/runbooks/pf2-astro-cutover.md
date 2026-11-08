# PF-2 Astro cutover runbook (VPS)

Executed in PF-2 Phase 6A step 3(c): AFTER the PHP 8.4 runbook (`pf2-php84-upgrade.md`) and AFTER the merge/push deploy is green. Only with Ali's explicit OK. All times in this file are WIB (UTC+7).

What changes: public pages move from the Vue SPA to Astro (`web/`, node on `127.0.0.1:4321`, systemd `portfolio-web`). The Vue SPA stays for `/admin`, `/login`, `/newsletter/unsubscribe`. Laravel routes (`/api`, `/r/{code}`, `/storage`, `/uploads`) do not change. Rollback of this runbook is independent from the PHP 8.4 one.

## Read this first

- **Expected duration:** 20 minutes. The public site is degraded between Step 4 (deploy finished) and Step 6 (nginx reload): old nginx then serves the new admin-only SPA shell for public URLs. Keep that gap to a few minutes: have Steps 5-6 ready before you push.
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
node -v                                            # must be v22 or newer
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

Verify later (Step 4b): `grep -c '^INTERNAL_API_TOKEN=' backend/.env web/.env` prints 1 for each and the values match (compare without printing: `diff <(grep ^INTERNAL_API_TOKEN= backend/.env) <(grep ^INTERNAL_API_TOKEN= web/.env) && echo same`).
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

Order inside the deploy: composer install, `migrate --force` (includes `2026_11_08_000001_add_slug_to_awards_table`: award slugs), `PageSectionSeeder` (deletes the old homepage ghost toggles; idempotent), cache refresh, frontend build (admin-only SPA, previous build saved as `frontend/dist.prev`), `deploy_web` (`npm ci`, `npm run build`, `systemctl restart portfolio-web`, health check on `127.0.0.1:4321/id/faq`), `php8.4-fpm` reload, `queue:restart`.

Watch the GitHub Actions run. After it is green (4b):

```bash
systemctl is-active portfolio-web                                  # active
curl -fsS -o /dev/null -w '%{http_code}\n' http://127.0.0.1:4321/id/faq     # 200
curl -fsS http://127.0.0.1:4321/en | grep -c 'hx-h1-name'          # 1 or more
sudo -u claudesn php /var/www/Portfolio_v2/backend/artisan tinker --execute="echo \App\Models\Award::whereNull('slug')->count();"   # 0
log "Step 4 deploy green"
```

If `deploy_web` failed (the Actions run is red at that step), read `journalctl -u portfolio-web -n 50`; the old nginx still serves the old site except for the SPA shell issue above, so go straight to Step 5 only after the service answers.

## Step 5: nginx swap (test before reload)

```bash
cd /var/www/Portfolio_v2
sudo cp scripts/nginx/portfolio-8080.conf /etc/nginx/sites-enabled/portfolio-8080
sudo nginx -t
log "Step 5 nginx -t ok"
```

The template includes `snippets/phpmyadmin.conf` (already on the VPS). If `nginx -t` fails: restore with Rollback step R1 and stop.

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

## Rollback (restores the old site in one reload)

Use if Step 6/7 fails. The old public SPA is `frontend/dist.pre-pf2`, the old nginx file is `/root/pf2-backup/portfolio-8080.pre-astro`.

```bash
cd /var/www/Portfolio_v2
# R1: old nginx + old public SPA build (one reload)
sudo cp /root/pf2-backup/portfolio-8080.pre-astro /etc/nginx/sites-enabled/portfolio-8080
sudo mv frontend/dist frontend/dist.pf2-admin 2>/dev/null
sudo cp -a frontend/dist.pre-pf2 frontend/dist
sudo nginx -t && sudo systemctl reload nginx
log "ROLLBACK R1 done"
# R2: stop Astro
sudo systemctl disable --now portfolio-web
log "ROLLBACK R2 done"
```

Verify: `curl -H Host:alisadikinma.com http://127.0.0.1:8080/` returns the old SPA shell, `/api/health` OK, `/admin` login works.

Caveats:
- This restores the OLD front end only. The backend is already the PF-2 backend (migrations, API changes are backward compatible for the old SPA except removed prerender routes). If the old SPA misbehaves, also redeploy the pre-PF-2 commit through CI (`git revert` of the merge, push).
- Do NOT run `deploy.sh` or push while rolled back with `dist.pre-pf2` in place: the deploy rebuilds `frontend/dist` as admin-only and breaks the old public pages again. Revert the merge first (that restores the full SPA source).
- Back to PF-2 afterwards: `sudo rm -rf frontend/dist && sudo mv frontend/dist.pf2-admin frontend/dist`, copy `scripts/nginx/portfolio-8080.conf` again, `nginx -t && reload`, `sudo systemctl enable --now portfolio-web`.
- Admin-only rollback (public Astro site fine, only the new admin build is bad): `sudo rm -rf frontend/dist && sudo cp -a frontend/dist.prev frontend/dist` (no nginx change).

## Local verification (repo)

```bash
bash scripts/tests/deploy-web-step.test.sh     # deploy_web dry run + seeder step
bash scripts/tests/web-unit.test.sh            # portfolio-web.service
bash scripts/tests/nginx-routing.test.sh       # ordering, nginx -t, route behaviour (needs docker)
```
