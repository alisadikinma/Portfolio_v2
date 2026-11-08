# PF-2 release runbook — PHP 8.4 upgrade (VPS)

Executed in PF-2 Phase 6A step 3(a): BEFORE the merge/push (3b) and BEFORE the Astro cutover (3c). Only with Ali's explicit OK. All times in this file are WIB (UTC+7).

Rollback of this runbook is independent from the Astro cutover rollback (`pf2-astro-cutover.md`).

## State read on 2026-11-08 16:20 WIB (read-only)

| Item | Value |
|---|---|
| Web PHP | `php8.2-fpm` 8.2.29, socket `/run/php/php8.2-fpm.sock` |
| 8.2 packages | cli, common, curl, gd, mbstring, mysql, opcache, readline, xml, zip |
| 8.4 | 8.4.12, CLI only. `/etc/php/8.4` has only `cli/` and `mods-available/`. NO `php8.4-fpm` |
| 8.4 packages | bcmath, bz2, cli, common, curl, gd, intl, mbstring, mcrypt, mysql, opcache, readline, xml, zip |
| Upgrade target | all `php8.4-*` to latest 8.4.x (apt candidate 8.4.27) |
| `/etc/php/8.2/fpm/conf.d/99-portfolio.ini` | `upload_max_filesize=50M`, `post_max_size=200M`, `max_execution_time=120`, `max_input_time=120`, `memory_limit=256M` |
| Pool `/etc/php/8.2/fpm/pool.d/www.conf` | user/group `www-data`; listen `/run/php/php8.2-fpm.sock`, `listen.owner/group = www-data`; `pm = dynamic`, `max_children = 30`, `start_servers = 10`, `min_spare_servers = 5`, `max_spare_servers = 15` |
| nginx | `sites-enabled/portfolio-8080` has 5 `fastcgi_pass unix:/var/run/php/php8.2-fpm.sock` (`/api/`, `= /`, `= /faq`, blog/projects regex, `/r/{code}`). Live file equals repo `scripts/nginx/portfolio-8080.conf` except 4 extra FAQ comment lines in the repo |
| phpMyAdmin | `/etc/nginx/snippets/phpmyadmin.conf:18` also uses `php8.2-fpm.sock` (snippet is NOT in the repo) |
| Other vhosts | `myhouse.alisadikinma.com`, `reply.alisadikinma.com`: no php-fpm |
| Services | nginx, php8.2-fpm, portfolio-queue, portfolio-crosspost@1 and @2, GitHub runner `actions.runner.alisadikinma-Portfolio_v2.portfolio-vps` |
| Cron + queue | already run `/usr/bin/php` = 8.4 |
| Content Engine crons | OFF by owner decision. Leave OFF. `blog:process-images` stays ON |

## HAZARD: backup files inside sites-enabled

`nginx.conf` includes `sites-enabled/*`, so these 4 backups are loaded as vhosts:

- `portfolio-8080.backup-20260322-091431`
- `portfolio-8080.backup-20260505-071525`
- `portfolio-8080.backup-shortlink-fix-20260510`
- `portfolio-8080.bak-faq-20260627-041437`

`nginx -t` warns `conflicting server name "_" on [::]:8080, ignored` (x3). The live file wins only by glob order. They also still point to `php8.2-fpm.sock`, so they would conflict with the socket switch. Step 0 removes them from the load path.

Rule for the future: never write backups into `sites-enabled/`. Use `/etc/nginx/backup-sites/`.

---

## Step R — Read-only checks (no change)

```bash
sudo nginx -t 2>&1 | grep -c 'conflicting server name'          # expect 3 today
php8.2 -m | sort > /tmp/m82.txt; php8.4 -m | sort > /tmp/m84.txt
diff /tmp/m82.txt /tmp/m84.txt                                   # lines starting "<" = in 8.2 only
cat /etc/php/8.2/fpm/conf.d/99-portfolio.ini
grep -E '^(user|group|listen|listen\.owner|listen\.group|pm|pm\.max_children|pm\.start_servers|pm\.min_spare_servers|pm\.max_spare_servers) *=' /etc/php/8.2/fpm/pool.d/www.conf
dpkg -l phpmyadmin | tail -1; cat /usr/share/phpmyadmin/version.php 2>/dev/null | head -5
grep -n fastcgi_pass /etc/nginx/snippets/phpmyadmin.conf /etc/nginx/sites-enabled/portfolio-8080
systemctl is-active nginx php8.2-fpm portfolio-queue portfolio-crosspost@1 portfolio-crosspost@2
```

Gate: the `diff` shows NO `<` line (8.2-only module) other than `Zend OPcache` naming noise. If any appears, install it in Step 2 as `php8.4-<name>`.

phpMyAdmin gate: the installed version must support PHP 8.4 (phpMyAdmin 5.2.2+ officially supports 8.4). If older, STOP: either upgrade phpMyAdmin first, or keep phpMyAdmin on the 8.2 pool (leave `snippets/phpmyadmin.conf` on `php8.2-fpm.sock` in Step 5 and keep `php8.2-fpm` running). Record the decision in the ledger.

## Step 0 — Move backup files out of sites-enabled

```bash
sudo mkdir -p /etc/nginx/backup-sites
cd /etc/nginx/sites-enabled
sudo mv portfolio-8080.backup-20260322-091431 portfolio-8080.backup-20260505-071525 \
        portfolio-8080.backup-shortlink-fix-20260510 portfolio-8080.bak-faq-20260627-041437 \
        /etc/nginx/backup-sites/
sudo nginx -t && sudo systemctl reload nginx
```

Verify: `nginx -t` prints no `conflicting server name` warning; `ls /etc/nginx/sites-enabled` shows no `backup`/`bak` file; `curl -s -o /dev/null -w '%{http_code}\n' https://alisadikinma.com/api/health` = 200.

Rollback: `sudo mv /etc/nginx/backup-sites/portfolio-8080.{backup,bak}* /etc/nginx/sites-enabled/ && sudo nginx -t && sudo systemctl reload nginx`.

## Step 1 — Back up current config

```bash
sudo mkdir -p /etc/nginx/backup-sites /root/pf2-backup
sudo cp -a /etc/nginx/sites-enabled/portfolio-8080 /etc/nginx/backup-sites/portfolio-8080.pre-php84
sudo cp -a /etc/nginx/snippets/phpmyadmin.conf /etc/nginx/backup-sites/phpmyadmin.conf.pre-php84
sudo cp -a /etc/php/8.2/fpm /root/pf2-backup/php8.2-fpm
```

Verify: the 3 copies exist (`ls -la`). Rollback: nothing to undo (copies only).

## Step 2 — Install php8.4-fpm and upgrade 8.4 to latest

```bash
sudo apt-get update
sudo apt-get install -y php8.4-fpm            # plus every module found missing in Step R
sudo apt-get install -y --only-upgrade php8.4-cli php8.4-common php8.4-bcmath php8.4-bz2 php8.4-curl \
  php8.4-gd php8.4-intl php8.4-mbstring php8.4-mcrypt php8.4-mysql php8.4-opcache php8.4-readline php8.4-xml php8.4-zip
```

Stop here if apt wants to REMOVE `php8.2-*` or nginx. Answer N and investigate.

Verify: `php8.4 -v` shows 8.4.27 (or the candidate apt printed); `dpkg -l 'php8.4-*' | awk '/^ii/{print $2,$3}'` all on one version; `systemctl is-active php8.2-fpm` still `active`; site still 200.

Rollback: `sudo apt-get remove php8.4-fpm` (the 8.4 CLI stays; cron and queue already use it).

## Step 3 — Copy ini and pool settings to 8.4

```bash
sudo cp /etc/php/8.2/fpm/conf.d/99-portfolio.ini /etc/php/8.4/fpm/conf.d/99-portfolio.ini
sudo sed -i \
  -e 's#^listen *=.*#listen = /run/php/php8.4-fpm.sock#' \
  -e 's#^;\?listen.owner *=.*#listen.owner = www-data#' \
  -e 's#^;\?listen.group *=.*#listen.group = www-data#' \
  -e 's#^user *=.*#user = www-data#' -e 's#^group *=.*#group = www-data#' \
  -e 's#^pm *=.*#pm = dynamic#' \
  -e 's#^pm.max_children *=.*#pm.max_children = 30#' \
  -e 's#^pm.start_servers *=.*#pm.start_servers = 10#' \
  -e 's#^pm.min_spare_servers *=.*#pm.min_spare_servers = 5#' \
  -e 's#^pm.max_spare_servers *=.*#pm.max_spare_servers = 15#' \
  /etc/php/8.4/fpm/pool.d/www.conf
diff <(grep -vE '^\s*(;|$)' /etc/php/8.2/fpm/pool.d/www.conf) <(grep -vE '^\s*(;|$)' /etc/php/8.4/fpm/pool.d/www.conf)
```

Verify: the diff shows only the `listen =` socket line (8.2 vs 8.4) and nothing else of the values above. `99-portfolio.ini` holds 50M / 200M / 120 / 120 / 256M.

Rollback: `sudo rm /etc/php/8.4/fpm/conf.d/99-portfolio.ini` and `sudo apt-get install --reinstall -o Dpkg::Options::=--force-confnew php8.4-fpm` to restore the stock pool file.

## Step 4 — Test and start php8.4-fpm

```bash
sudo php-fpm8.4 -t
sudo systemctl enable --now php8.4-fpm
php-fpm8.4 -i | grep -E 'upload_max_filesize|post_max_size|max_execution_time|memory_limit'
ls -l /run/php/php8.4-fpm.sock
```

Verify: `-t` ends `test is successful`; `-i` shows 50M / 200M / 120 / 256M; the socket exists, owner `www-data`. Do NOT add a temporary PHP info route (not allowed); `php-fpm8.4 -i` is the check.

Rollback: `sudo systemctl disable --now php8.4-fpm`. Live traffic still runs on 8.2.

## Step 5 — Switch nginx to the 8.4 socket

Vhost (repo template already has the 8.4 socket):

```bash
sudo sed -i 's#php8\.2-fpm\.sock#php8.4-fpm.sock#g' /etc/nginx/sites-enabled/portfolio-8080
sudo sed -i 's#php8\.2-fpm\.sock#php8.4-fpm.sock#g' /etc/nginx/snippets/phpmyadmin.conf     # only if phpMyAdmin passed the Step R gate
grep -c 'php8.4-fpm.sock' /etc/nginx/sites-enabled/portfolio-8080                           # expect 5
grep -rn 'php8.2-fpm.sock' /etc/nginx/ --include='*' | grep -v backup-sites                 # expect nothing
sudo nginx -t && sudo systemctl reload nginx
```

Verify:
```bash
curl -s https://alisadikinma.com/api/health
curl -s -o /dev/null -w '%{http_code}\n' https://alisadikinma.com/faq
curl -s -o /dev/null -w '%{http_code}\n' https://alisadikinma.com/blog
```
All 200. phpMyAdmin login page loads. `ps -u www-data -o cmd | grep 'php-fpm: pool'` shows `php-fpm8.4` workers.

Rollback: `sudo cp /etc/nginx/backup-sites/portfolio-8080.pre-php84 /etc/nginx/sites-enabled/portfolio-8080; sudo cp /etc/nginx/backup-sites/phpmyadmin.conf.pre-php84 /etc/nginx/snippets/phpmyadmin.conf; sudo nginx -t && sudo systemctl reload nginx`. `php8.2-fpm` stays installed and running, so rollback is a reload.

## Step 6 — Restart workers

```bash
sudo systemctl restart portfolio-queue portfolio-crosspost@1 portfolio-crosspost@2
cd /var/www/Portfolio_v2/backend && php artisan queue:restart
sudo systemctl restart actions.runner.alisadikinma-Portfolio_v2.portfolio-vps   # only if no deploy is running
```

Verify: `systemctl is-active portfolio-queue portfolio-crosspost@1 portfolio-crosspost@2` all `active`; `php -v` = 8.4; `php artisan schedule:list` still works. Content Engine crons stay OFF; `blog:process-images` stays ON.

Rollback: workers use the CLI (already 8.4 before this runbook), so there is nothing to roll back.

## Step 7 — Release env

In `/var/www/Portfolio_v2/backend/.env` set:

```
ADMIN_EMAIL=<Ali's admin mailbox>
```

New in Phase 0E. Without it, contact notifications are skipped with a warning in `laravel.log`.

```bash
cd /var/www/Portfolio_v2/backend && php artisan config:cache
```

Verify: `php artisan tinker --execute="echo config('mail.admin_email');"` prints the address; submit the contact form, no `ADMIN_EMAIL` warning in the log.

Rollback: remove the line, `php artisan config:cache`.

## Step 8 — Full verify on the OLD site (gate before Phase 6A step 3b)

1. `curl -s https://alisadikinma.com/api/health` returns OK.
2. Admin login at `/admin` works.
3. One Content Engine page opens (`/admin/content-engine`), no 500.
4. A gallery upload of a >10 MB file succeeds (proves 50M limit on the 8.4 pool).
5. `tail -n 50 /var/www/Portfolio_v2/backend/storage/logs/laravel.log` has no new fatal.

Any failure: run the Step 5 rollback right away, then report to Ali.

## Step 9 — Do NOT remove php8.2 yet

`php8.2-fpm` stays installed and running until Phase 6A is green. Removing it is a separate task after 7 days green (tracked in "Utang terbuka"). Upgrading 8.2 to 8.2.35 is not needed once traffic is off it.

## Final rollback (whole runbook)

Step 5 rollback, then `sudo systemctl disable --now php8.4-fpm`, then Step 0 rollback only if the backup-file move is the suspect. State returns to the 2026-11-08 16:20 WIB reading above.

## Local check

`scripts/check-php-runtime.sh --compare [nginx conf ...]` fails when any `php*-fpm.sock` in the given conf files differs from the CLI version. Pass the phpMyAdmin snippet as an extra file:

```bash
scripts/check-php-runtime.sh --compare scripts/nginx/portfolio-8080.conf /etc/nginx/snippets/phpmyadmin.conf
```
