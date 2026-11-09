# PF-2 release runbook — PHP 8.4 upgrade (VPS)

Executed in PF-2 Phase 6A step 3(a): BEFORE the merge/push (3b) and BEFORE the Astro cutover (3c). Only with Ali's explicit OK. All times in this file are WIB (UTC+7).

Rollback of this runbook is independent from the Astro cutover rollback (`pf2-astro-cutover.md`).

## Read this first (3 AM page)

- **Expected duration:** 45 to 60 minutes (Step R to Step 9). Stop and ask Ali if you pass 90 minutes.
- **STOP and roll back at once if ANY of these happens:**
  - an origin check (`curl -H 'Host: alisadikinma.com' http://127.0.0.1:8080/...`) is not 200, or `/api/health` body is not OK;
  - `nginx -t` fails;
  - `apt-get install --simulate` prints a `Remv` line;
  - nginx `error.log` shows `connect() to unix:/run/php/... failed`, `502` or `Primary script unknown`;
  - `php8.4-fpm.log` shows `ERROR` or `child ... exited on signal`;
  - the "STALE DAEMON" line prints in Step 4.
- **Log every step with its WIB time.** Paste this once per SSH session, then end each step with `log "Step N done"`:
  ```bash
  sudo mkdir -p /root/pf2-backup
  log() { echo "$(TZ=Asia/Jakarta date '+%F %T') WIB  $*" | sudo tee -a /root/pf2-backup/steps.log; }
  ```
- **Open a second SSH session first** and leave it running the whole time:
  ```bash
  sudo tail -F /var/log/nginx/error.log /var/log/php8.4-fpm.log     # -F: the 8.4 log does not exist until Step 2
  ```
- **Origin, not edge.** The site sits behind Traefik/Cloudflare, which can answer 200 from cache while PHP is dead. Every verify here hits the origin: `curl -H 'Host: alisadikinma.com' http://127.0.0.1:8080/PATH`.
- **Do not `systemctl restart` the queue workers, the crosspost workers or the GitHub runner.** A restart SIGKILLs a running carousel job after 90 s. The CLI is already 8.4; the workers need no restart.
- **No push to GitHub while this runbook runs.**

## Pre-flight (all must pass, in this order)

```bash
# 1. On your laptop: no GitHub Actions run in progress
gh run list --repo alisadikinma/Portfolio_v2 --status in_progress          # expect: empty

# 2. On your laptop: put the checker on the VPS (it is not in the VPS checkout until the merge)
scp scripts/check-php-runtime.sh <vps>:/tmp/check-php-runtime.sh

# 3. On the VPS: stage it, find who owns bootstrap/cache (artisan runs as that user)
sudo mkdir -p /root/pf2-backup && sudo install -m 755 /tmp/check-php-runtime.sh /root/pf2-backup/check-php-runtime.sh
OWNER="$(stat -c %U /var/www/Portfolio_v2/backend/bootstrap/cache)"; echo "artisan owner: $OWNER"

# 4. No long queue job running
ps -o etime,cmd -u claudesn | grep -E 'queue:work|claude -p' | grep -v grep
sudo -u "$OWNER" php /var/www/Portfolio_v2/backend/artisan tinker --execute="echo DB::table('jobs')->whereNotNull('reserved_at')->count();"   # expect 0; if >0 wait and recheck

# 5. Vhost and snippet: symlink or regular file?
stat -c '%F  %N' /etc/nginx/sites-enabled/portfolio-8080 /etc/nginx/snippets/phpmyadmin.conf
#    expect on 2026-11-08: "regular file" for both. If "symbolic link": the cp -aL / sed --follow-symlinks below already handle it.

# 6. Origin baseline (write the numbers into the log)
for p in /api/health / /faq /blog; do curl -s -o /dev/null -w "$p %{http_code}\n" -H 'Host: alisadikinma.com' "http://127.0.0.1:8080$p"; done | tee -a /root/pf2-backup/baseline.txt   # all 200
curl -s -H 'Host: alisadikinma.com' http://127.0.0.1:8080/api/health | tee -a /root/pf2-backup/baseline.txt
log "pre-flight done"
```

## State read on 2026-11-08 (read-only)

| Item | Value |
|---|---|
| Web PHP | `php8.2-fpm` 8.2.29, socket `/run/php/php8.2-fpm.sock` |
| 8.2 packages | cli, common, curl, gd, mbstring, mysql, opcache, readline, xml, zip |
| 8.4 | 8.4.12, CLI only. `/etc/php/8.4` has only `cli/` and `mods-available/`. NO `php8.4-fpm` |
| 8.4 packages | bcmath, bz2, cli, common, curl, gd, intl, mbstring, mcrypt, mysql, opcache, readline, xml, zip |
| Upgrade target | `php8.4-fpm` + all `php8.4-*` at the latest 8.4.x (apt candidate 8.4.27). `php8.4-fpm` pins `php8.4-common`, so the CLI upgrades in the same transaction |
| `/etc/php/8.2/fpm/conf.d/99-portfolio.ini` | `upload_max_filesize=50M`, `post_max_size=200M`, `max_execution_time=120`, `max_input_time=120`, `memory_limit=256M` |
| `/etc/php/8.2/fpm/php.ini` | differs from `php.ini-production` only in `post_max_size=150M` and `upload_max_filesize=10M`; both are overridden by `99-portfolio.ini`, so nothing extra to copy |
| `/etc/php/8.2/fpm/pool.d` | `www.conf` plus `www.conf.backup-20251106`. The backup name does not end in `.conf`, so php-fpm does NOT load it |
| Pool `www.conf` | user/group `www-data`; listen `/run/php/php8.2-fpm.sock`, `listen.owner/group = www-data`; `pm = dynamic`, `max_children = 30`, `start_servers = 10`, `min_spare_servers = 5`, `max_spare_servers = 15` |
| Security agent | 8.2 FPM loads `conf.d/20-monarxprotect.ini` = `extension=monarxprotect-php82.so` (Hostinger Monarx; packages `monarx-protect` + `monarx-protect-autodetect`). `/etc/php/8.4/mods-available/monarxprotect.ini` and `monarxprotect-php84.so` exist |
| Opcache (8.2 running) | `opcache.enable` On, `validate_timestamps` On, `revalidate_freq` 2 |
| nginx | `sites-enabled/portfolio-8080` is a REGULAR file with 5 `fastcgi_pass unix:/var/run/php/php8.2-fpm.sock` (`/api/`, `= /`, `= /faq`, blog/projects regex, `/r/{code}`). Equals repo `scripts/nginx/portfolio-8080.conf` except 4 extra FAQ comment lines in the repo. No nginx file uses the generic `php-fpm.sock` |
| phpMyAdmin | Ubuntu package `4:5.2.1+dfsg-3` (version in `/usr/share/phpmyadmin/libraries/classes/Version.php`; there is no `version.php`). `/etc/nginx/snippets/phpmyadmin.conf:18` uses `php8.2-fpm.sock`; the snippet is a REGULAR file and NOT in the repo. PHP 8.4 is officially supported only from phpMyAdmin 5.2.2 |
| Other vhosts | `myhouse.alisadikinma.com`, `reply.alisadikinma.com`: no php-fpm |
| Services | nginx, php8.2-fpm, portfolio-queue, portfolio-crosspost@1 and @2, GitHub runner `actions.runner.alisadikinma-Portfolio_v2.portfolio-vps` |
| Cron + queue | already run `/usr/bin/php` = 8.4 |
| `scripts/deploy.sh` | had NO fpm reload. PF-2 adds a guarded `scripts/reload-php-fpm.sh` call (reloads `php8.4-fpm` only if that unit is active) |
| needrestart | 3.6 installed. Use `NEEDRESTART_MODE=l` (list only) so apt never restarts anything on its own |
| Content Engine crons | OFF by owner decision. Leave OFF. `blog:process-images` stays ON |

### Decision: phpMyAdmin stays on `php8.2-fpm` (DEFAULT branch)

Installed phpMyAdmin 5.2.1 is below 5.2.2, so it is not supported on 8.4. Default for this runbook:

- **Branch A (default): phpMyAdmin stays on the 8.2 socket.** Do NOT edit `snippets/phpmyadmin.conf`. `php8.2-fpm` stays installed and running. Removing `php8.2-fpm` becomes a debt item: it can go only after phpMyAdmin is upgraded to 5.2.2+ or dropped, plus 7 days green.
- **Branch B (only if Ali upgraded or dropped phpMyAdmin before this run):** the snippet is switched to the 8.4 socket in Step 5, and `php8.2-fpm` can be removed 7 days after green.

Each step below states the expected output for both branches. Record the branch in the ledger.

## HAZARD: backup files inside sites-enabled

`nginx.conf` includes `sites-enabled/*`, so these 4 backups are loaded as vhosts:

- `portfolio-8080.backup-20260322-091431`
- `portfolio-8080.backup-20260505-071525`
- `portfolio-8080.backup-shortlink-fix-20260510`
- `portfolio-8080.bak-faq-20260627-041437`

They are full copies of the vhost (same `listen 8080` / `server_name _`). That is why `nginx -t` warns `conflicting server name "_" on [::]:8080, ignored` (x3): nginx keeps the first block in glob order (the live file) and ignores each extra copy on the same listen address. They also still point to `php8.2-fpm.sock`, so they would conflict with the socket switch. Step 0 proves this with a grep, then removes them from the load path.

Rule for the future: never write backups into `sites-enabled/`. Use `/etc/nginx/backup-sites/`.

---

## Step R — Read-only checks (no change)

```bash
sudo nginx -t 2>&1 | grep -c 'conflicting server name'           # expect 3 today
php8.2 -m | sort > /tmp/m82.txt; php8.4 -m | sort > /tmp/m84.txt
diff /tmp/m82.txt /tmp/m84.txt                                    # lines starting "<" = in 8.2 only

# --- config parity: what 8.2 FPM loads today
ls -la /etc/php/8.2/fpm/pool.d /etc/php/8.2/fpm/conf.d            # pool.d: www.conf + www.conf.backup-20251106 (not loaded)
cat /etc/php/8.2/fpm/conf.d/99-portfolio.ini
diff /etc/php/8.2/fpm/php.ini /usr/lib/php/8.2/php.ini-production # expect ONLY post_max_size=150M and upload_max_filesize=10M
grep -E '^(user|group|listen|listen\.owner|listen\.group|pm|pm\.max_children|pm\.start_servers|pm\.min_spare_servers|pm\.max_spare_servers) *=' /etc/php/8.2/fpm/pool.d/www.conf
# snapshot of the running 8.2 values, compared with 8.4 in Step 4
php-fpm8.2 -i | grep -E '^(opcache\.(enable|validate_timestamps|revalidate_freq)|upload_max_filesize|post_max_size|memory_limit|max_[a-z_]+) =>' | sort | sudo tee /root/pf2-backup/fpm82-i.txt

# --- phpMyAdmin, security agent, symlinks
dpkg -l phpmyadmin | tail -1                                      # expect 4:5.2.1+dfsg-3
grep -i VERSION /usr/share/phpmyadmin/libraries/classes/Version.php | head -3
ls /etc/php/8.2/fpm/conf.d | grep -i monarx                       # expect 20-monarxprotect.ini
ls /etc/php/8.4/mods-available | grep -i monarx; ls /usr/lib/php/*/ | grep -i monarx
grep -n fastcgi_pass /etc/nginx/snippets/phpmyadmin.conf /etc/nginx/sites-enabled/portfolio-8080
systemctl is-active nginx php8.2-fpm portfolio-queue portfolio-crosspost@1 portfolio-crosspost@2
log "Step R done"
```

Gates:

1. The module `diff` shows NO `<` line (a module only in 8.2) apart from `Zend OPcache` naming noise. If one appears, install it in Step 2 as `php8.4-<name>`.
2. The `php.ini` diff shows only the two known lines. **Any other line: copy that setting into `/etc/php/8.4/fpm/conf.d/99-portfolio.ini` before Step 4.**
3. phpMyAdmin: version 5.2.1 means Branch A (stays on 8.2). 5.2.2 or higher means Branch B is allowed. Record the branch.

## Step 0 — Move backup files out of sites-enabled

First prove the backups are plain copies and explain the x3:

```bash
cd /etc/nginx/sites-enabled
grep -nE '^\s*(listen|server_name|map |limit_req_zone|upstream)' portfolio-8080 portfolio-8080.backup-* portfolio-8080.bak-* | cat
```

Expect: every file shows `listen 8080;` and `server_name _;`; the files that also show `listen [::]:8080;` are the ones that cause a warning, so 3 of them match the x3 count. **STOP if:** any backup has a different `server_name`, any `map `, `limit_req_zone` or `upstream` line appears that the live file lacks (a global definition the live vhost might depend on), or the live file `portfolio-8080` is not in the output.

Then move them:

```bash
sudo mkdir -p /etc/nginx/backup-sites
sudo mv portfolio-8080.backup-20260322-091431 portfolio-8080.backup-20260505-071525 \
        portfolio-8080.backup-shortlink-fix-20260510 portfolio-8080.bak-faq-20260627-041437 \
        /etc/nginx/backup-sites/
sudo nginx -t && sudo systemctl reload nginx
log "Step 0 done"
```

Verify: `sudo nginx -t 2>&1 | grep -c 'conflicting server name'` prints `0`; `ls /etc/nginx/sites-enabled` shows no `backup`/`bak` file; origin check `curl -s -o /dev/null -w '%{http_code}\n' -H 'Host: alisadikinma.com' http://127.0.0.1:8080/api/health` = 200.

Rollback: `sudo sh -c 'mv /etc/nginx/backup-sites/portfolio-8080.ba* /etc/nginx/sites-enabled/' && sudo nginx -t && sudo systemctl reload nginx`.

## Step 1 — Back up current config

```bash
sudo mkdir -p /etc/nginx/backup-sites /root/pf2-backup
sudo cp -aL /etc/nginx/sites-enabled/portfolio-8080 /etc/nginx/backup-sites/portfolio-8080.pre-php84      # -L: real content even if the live file is a symlink
sudo cp -aL /etc/nginx/snippets/phpmyadmin.conf /etc/nginx/backup-sites/phpmyadmin.conf.pre-php84
sudo cp -a /etc/php/8.2/fpm /root/pf2-backup/php8.2-fpm
log "Step 1 done"
```

Verify: the 3 copies exist (`ls -la /etc/nginx/backup-sites /root/pf2-backup`) and `file /etc/nginx/backup-sites/*.pre-php84` says "ASCII text", not "symbolic link". Rollback: nothing to undo (copies only).

## Step 2 — Install php8.4-fpm and upgrade 8.4 to latest

The `php8.4-fpm` postinst STARTS the daemon at once with the STOCK config (pool `pm.start_servers = 2`, no `99-portfolio.ini`). It listens on `/run/php/php8.4-fpm.sock` but nginx does not use it yet. Step 4 restarts it with the real config.

```bash
sudo apt-get update
# Dry run first. STOP on any line starting with "Remv".
sudo apt-get install --simulate php8.4-fpm | grep -E '^(Remv|Inst)'
# then the real install (plus every module found missing in Step R):
sudo NEEDRESTART_MODE=l apt-get install -y php8.4-fpm
```

`php8.4-fpm` pins `php8.4-common`, so the **CLI is upgraded 8.4.12 to the latest in this same transaction**. Cron, `artisan` and new queue processes use the new binary from this moment. **There is NO rollback for the CLI upgrade** (the sury repo keeps only the latest build). It is a patch release inside 8.4 and the CLI was already 8.4.

Then check for stragglers (some `php8.4-*` left on an older version, for example `php8.4-mcrypt` from PECL):

```bash
dpkg -l 'php8.4-*' | awk '/^ii/{print $2,$3}'
# if versions are mixed, simulate again (no Remv) and upgrade only those:
#   sudo NEEDRESTART_MODE=l apt-get install -y --only-upgrade <names>
```

### Step 2b — Monarx security agent on 8.4

8.2 FPM runs the Monarx malware agent. 8.4 must run it too.

```bash
php-fpm8.4 -m | grep -i monarx            # expect: monarxprotect
# if empty:
sudo phpenmod -v 8.4 -s fpm monarxprotect
php-fpm8.4 -m | grep -i monarx            # now expect: monarxprotect
ls /etc/php/8.4/fpm/conf.d | grep -i monarx
log "Step 2 + 2b done"
```

The restart in Step 4 loads it into the running daemon.

Verify Step 2: `php8.4 -v` shows the new version (8.4.27 or the candidate apt printed); all `php8.4-*` on one version; `systemctl is-active php8.2-fpm` still `active`; origin check of `/api/health` still 200.

Rollback: `sudo apt-get remove php8.4-fpm` (the 8.4 CLI stays; cron and queue already use it). `sudo phpdismod -v 8.4 -s fpm monarxprotect` undoes 2b.

## Step 3 — Copy ini and pool settings to 8.4

```bash
sudo cp -a /etc/php/8.4/fpm/pool.d/www.conf /root/pf2-backup/www.conf.84-stock
sudo cp /etc/php/8.2/fpm/conf.d/99-portfolio.ini /etc/php/8.4/fpm/conf.d/99-portfolio.ini
sed 's/php8\.2-fpm/php8.4-fpm/g' /etc/php/8.2/fpm/pool.d/www.conf | sudo tee /etc/php/8.4/fpm/pool.d/www.conf >/dev/null
diff /root/pf2-backup/www.conf.84-stock /etc/php/8.4/fpm/pool.d/www.conf
log "Step 3 done"
```

Verify: read the `diff` (new pool vs stock 8.4 pool). Expected differences: `pm.max_children`, `pm.start_servers`, `pm.min_spare_servers`, `pm.max_spare_servers`, and maybe a few comment/`;` lines. `listen = /run/php/php8.4-fpm.sock`, `user`/`group`/`listen.owner`/`listen.group` = `www-data`. Review any other line; if you cannot explain it, STOP. `99-portfolio.ini` holds 50M / 200M / 120 / 120 / 256M. The pool name in the file must read `[www]` and stay unique.

Rollback: `sudo cp -a /root/pf2-backup/www.conf.84-stock /etc/php/8.4/fpm/pool.d/www.conf; sudo rm /etc/php/8.4/fpm/conf.d/99-portfolio.ini`.

## Step 4 — Test, then RESTART php8.4-fpm onto the real config

The daemon is already running on the stock config (Step 2). `enable --now` would do nothing to a running daemon. Use `restart`.

```bash
sudo php-fpm8.4 -t && sudo systemctl enable php8.4-fpm && sudo systemctl restart php8.4-fpm
sleep 3
ls -l /run/php/php8.4-fpm.sock
log "Step 4 restarted"
```

Verify the RUNNING daemon, not the files:

```bash
# 1. The daemon started after the config files were written:
ST=$(date -d "$(systemctl show -p ActiveEnterTimestamp --value php8.4-fpm)" +%s)
for f in /etc/php/8.4/fpm/pool.d/www.conf /etc/php/8.4/fpm/conf.d/99-portfolio.ini; do
  [ "$ST" -gt "$(stat -c %Y "$f")" ] && echo "daemon newer than $f: OK" || echo "STALE DAEMON: $f"
done
# 2. Pool values live: start_servers is 10 (stock is 2), so >= 10 workers
pgrep -P "$(cat /run/php/php8.4-fpm.pid)" | wc -l            # expect >= 10
# 3. Config parity with 8.2 (snapshot from Step R)
php-fpm8.4 -i | grep -E '^(opcache\.(enable|validate_timestamps|revalidate_freq)|upload_max_filesize|post_max_size|memory_limit|max_[a-z_]+) =>' | sort > /root/pf2-backup/fpm84-i.txt
diff /root/pf2-backup/fpm82-i.txt /root/pf2-backup/fpm84-i.txt
```

Expected: `-t` ends `test is successful`; "daemon newer" prints OK twice (never `STALE DAEMON`); worker count >= 10; the socket exists, owner `www-data`. The parity `diff` must show no change in `upload_max_filesize` (50M), `post_max_size` (200M), `max_execution_time` (120), `max_input_time` (120), `memory_limit` (256M), `opcache.enable` (On), `opcache.validate_timestamps` (On), `opcache.revalidate_freq` (2). Explain or fix every other difference (copy the 8.2 value into `99-portfolio.ini`, then `sudo systemctl restart php8.4-fpm` and recheck). Monarx: `php-fpm8.4 -m | grep -i monarx` prints `monarxprotect`. Do NOT add a temporary PHP info route (not allowed); `php-fpm8.4 -i` is the check.

Rollback: `sudo systemctl disable --now php8.4-fpm`. Live traffic still runs on 8.2.

## Step 5 — Switch nginx to the 8.4 socket

```bash
# vhost (always):
sudo sed -i --follow-symlinks 's#php8\.2-fpm\.sock#php8.4-fpm.sock#g' /etc/nginx/sites-enabled/portfolio-8080
# phpMyAdmin snippet: ONLY in Branch B (phpMyAdmin 5.2.2+). Branch A (default): SKIP this line.
# sudo sed -i --follow-symlinks 's#php8\.2-fpm\.sock#php8.4-fpm.sock#g' /etc/nginx/snippets/phpmyadmin.conf
grep -c 'php8.4-fpm.sock' /etc/nginx/sites-enabled/portfolio-8080          # expect 5
grep -rn 'php8.2-fpm.sock' /etc/nginx/ | grep -v backup-sites
sudo nginx -t && sudo systemctl reload nginx
log "Step 5 reloaded"
```

Expected `grep -rn 'php8.2-fpm.sock'`:

- **Branch A (default):** exactly one line, `/etc/nginx/snippets/phpmyadmin.conf:18:...php8.2-fpm.sock`.
- **Branch B:** nothing.

Verify (origin, not edge):

```bash
for p in /api/health / /faq /blog; do curl -s -o /dev/null -w "$p %{http_code}\n" -H 'Host: alisadikinma.com' "http://127.0.0.1:8080$p"; done   # all 200
curl -s -H 'Host: alisadikinma.com' http://127.0.0.1:8080/api/health      # same body as in baseline.txt
sudo ss -xap | grep -c php8.4-fpm.sock                                    # expect >= 1 (listener + any live connection)
sudo /root/pf2-backup/check-php-runtime.sh --compare /etc/nginx/sites-enabled/portfolio-8080
sudo /root/pf2-backup/check-php-runtime.sh --compare /etc/nginx/sites-enabled/portfolio-8080 /etc/nginx/snippets/phpmyadmin.conf
```

Expected from the two checker calls:

| Call | Branch A (phpMyAdmin on 8.2) | Branch B |
|---|---|---|
| vhost only | `check-php-runtime: OK php 8.4`, exit 0 | same |
| vhost + snippet | `check-php-runtime: fpm 8.2 != cli 8.4`, exit 1. This is EXPECTED: the snippet is the one allowed 8.2 user | `check-php-runtime: OK php 8.4`, exit 0 |

Also: the phpMyAdmin login page loads (Branch A: served by 8.2, Branch B: by 8.4). Watch the second SSH session: no `connect() ... failed`, no 502. The "workers are php-fpm8.4" check is NOT used (it cannot tell the versions apart).

Rollback: `sudo cp /etc/nginx/backup-sites/portfolio-8080.pre-php84 /etc/nginx/sites-enabled/portfolio-8080; sudo cp /etc/nginx/backup-sites/phpmyadmin.conf.pre-php84 /etc/nginx/snippets/phpmyadmin.conf; sudo nginx -t && sudo systemctl reload nginx`. `php8.2-fpm` stays installed and running, so rollback is a reload. Then repeat the origin checks above.

## Step 6 — Workers: graceful signal only

Do NOT `systemctl restart portfolio-queue`, `portfolio-crosspost@*` or the GitHub runner (a restart SIGKILLs a running job after 90 s; the CLI is already 8.4).

```bash
ps -o etime,cmd -u claudesn | grep -E 'queue:work|claude -p' | grep -v grep     # nothing long-running mid-job
OWNER="$(stat -c %U /var/www/Portfolio_v2/backend/bootstrap/cache)"
cd /var/www/Portfolio_v2/backend && sudo -u "$OWNER" php artisan queue:restart
log "Step 6 done"
```

`queue:restart` is a signal: each worker finishes its current job, then systemd starts it again.

Verify: `systemctl is-active portfolio-queue portfolio-crosspost@1 portfolio-crosspost@2` all `active`; `php -v` = 8.4; `sudo -u "$OWNER" php artisan schedule:list | head` works. Content Engine crons stay OFF; `blog:process-images` stays ON.

Rollback: nothing to roll back (workers use the CLI, already 8.4 before this runbook).

## Step 7 — Deploy reload and who reloads what

After this runbook the web runtime is `php8.4-fpm`. Operators reload **`php8.4-fpm`**, not 8.2: `sudo systemctl reload php8.4-fpm` (phpMyAdmin in Branch A keeps using `php8.2-fpm`, which never needs a reload for app code).

`scripts/deploy.sh` now calls `scripts/reload-php-fpm.sh` after the cache step: it reloads `php8.4-fpm` only if that unit is active and does nothing on other hosts. It needs passwordless sudo for the deploy user. Check once as the user the runner uses:

```bash
sudo -n systemctl reload php8.4-fpm; echo "exit=$?"      # expect exit=0
```

If it is not 0, the deploy prints `WARN: could not reload php8.4-fpm` and continues (opcache revalidates files every 2 s, so a stale cache clears itself). Either add a sudoers line for `systemctl reload php8.4-fpm` or reload by hand after each deploy. Rollback: none (reload only changes nothing).

## Step 8 — Full verify on the OLD site (gate before Phase 6A step 3b)

1. Origin: `/api/health` OK, `/`, `/faq`, `/blog` all 200 (the Step 5 loop). Then once through the edge: `curl -s https://alisadikinma.com/api/health`.
2. Admin login at `/admin` works.
3. One Content Engine page opens (`/admin/content-engine`), no 500.
4. A gallery upload of a >10 MB file succeeds (proves the 50M limit on the 8.4 pool). **Then delete that test upload** in `/admin` (gallery item delete) and confirm the file is gone from `storage/app/public` (`find /var/www/Portfolio_v2/backend/storage/app/public -newer /root/pf2-backup/steps.log -size +10M`).
5. `tail -n 50 /var/www/Portfolio_v2/backend/storage/logs/laravel.log` has no new fatal. The second SSH session showed no error since Step 5.

```bash
log "Step 8 done — site verified on 8.4"
```

Any failure: run the Step 5 rollback right away, then report to Ali.

## Step 9 — Do NOT remove php8.2 yet

`php8.2-fpm` stays installed and running until Phase 6A is green. Upgrading 8.2 to 8.2.35 is not needed once traffic is off it.

Expected end state:

- **Branch A (default):** `systemctl is-active php8.2-fpm php8.4-fpm` = `active active`; `sudo ss -xap | grep -c php8.2-fpm.sock` >= 1 (phpMyAdmin only). **`php8.2-fpm` cannot be removed** until phpMyAdmin is upgraded to 5.2.2+ or dropped, then 7 days green. Tracked as debt in "Utang terbuka".
- **Branch B:** `systemctl is-active php8.2-fpm php8.4-fpm` = `active active`; `grep -rn 'php8.2-fpm.sock' /etc/nginx/ | grep -v backup-sites` prints nothing. Remove `php8.2-fpm` 7 days after green (separate task, in "Utang terbuka").

```bash
log "Step 9 done — runbook complete"
```

## Step 10 — ADMIN_EMAIL (set any time, VERIFY only after Phase 6A step 3b)

New in Phase 0E. Without it, contact notifications are skipped with a warning in `laravel.log`. Setting `.env` early is harmless: the config is cached, so the new value is read only when the deploy in 3b runs `config:cache`. The verify therefore works only AFTER the deploy.

Set (any time, keep file owner and mode):

```env
ADMIN_EMAIL=<Ali's admin mailbox>
```

Verify, after Phase 6A step 3b finished green:

```bash
OWNER="$(stat -c %U /var/www/Portfolio_v2/backend/bootstrap/cache)"
cd /var/www/Portfolio_v2/backend && sudo -u "$OWNER" php artisan tinker --execute="echo config('mail.admin_email');"   # prints the address
```

Submit the contact form once: no `ADMIN_EMAIL` warning in `laravel.log`.

Rollback: remove the line, then `sudo -u "$OWNER" php artisan config:cache`.

## Final rollback (whole runbook)

Step 5 rollback, then `sudo systemctl disable --now php8.4-fpm`, then Step 0 rollback only if the backup-file move is the suspect. The CLI stays on the new 8.4.x (no rollback, harmless). State otherwise returns to the 2026-11-08 reading above.

## Local check

`scripts/check-php-runtime.sh --compare [nginx conf ...]` fails when any `php8.N-fpm.sock` in the given conf files differs from the CLI version. Commented lines are ignored. Exit codes: 0 OK, 1 mismatch (or a generic `php-fpm.sock`), 2 cannot check (php not on PATH, file unreadable, no socket). Tests: `bash scripts/tests/check-php-runtime.test.sh` and `bash scripts/tests/reload-php-fpm.test.sh`.

```bash
scripts/check-php-runtime.sh --compare scripts/nginx/portfolio-8080.conf
# check-php-runtime: OK php 8.4   (exit 0)

scripts/check-php-runtime.sh --compare scripts/nginx/portfolio-8080.conf /etc/nginx/snippets/phpmyadmin.conf
# Branch A (phpMyAdmin on 8.2): check-php-runtime: fpm 8.2 != cli 8.4   (exit 1, expected for the snippet)
# Branch B (phpMyAdmin on 8.4): check-php-runtime: OK php 8.4           (exit 0)
```
