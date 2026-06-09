# Portfolio_v2 — VPS Background Process Setup

This directory ships the systemd unit + cron snippet needed to run Laravel
**queue jobs** and **scheduled commands** on the VPS. Without these in place,
the LinkedIn pipeline (and Content Engine cron tasks) silently stall — jobs
dispatch but never execute, schedules are defined but never fire.

## What's here

| File | Purpose |
|---|---|
| `portfolio-queue.service` | systemd unit running `php artisan queue:work` on the `default` queue |
| `portfolio-crosspost@.service` | systemd **template** unit — N parallel workers on the `social-crosspost` queue (cross-post caption-gen) |
| `portfolio-scheduler.crontab` | crontab line running `php artisan schedule:run` every minute |

## One-time installation (manual operator step)

These are infra-level — they don't ship via `deploy.sh`. Install once per
VPS, then leave alone.

### 1. Queue worker (systemd)

```bash
# On VPS, as root:
sudo cp /var/www/Portfolio_v2/scripts/systemd/portfolio-queue.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable portfolio-queue.service
sudo systemctl start portfolio-queue.service

# Verify:
sudo systemctl status portfolio-queue.service
sudo journalctl -u portfolio-queue.service -f --since "5 minutes ago"
```

You should see `[INFO] Processing jobs from the [default] queue.` (idle) or
job batch lines (active).

### 1b. Cross-post worker pool (systemd template — June 9, 2026)

The default-carousel pipeline fans out each blog post to Instagram + TikTok +
Threads + Facebook at once. Those caption-gen jobs run on the dedicated
`social-crosspost` queue so they execute **in parallel** instead of serially
behind the `default` worker. Run 4 instances (one per platform):

```bash
# On VPS, as root:
sudo cp /var/www/Portfolio_v2/scripts/systemd/portfolio-crosspost@.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now portfolio-crosspost@{1..4}

# Verify all 4 are active:
sudo systemctl list-units 'portfolio-crosspost@*'
sudo journalctl -u 'portfolio-crosspost@*' -f --since "5 minutes ago"
```

You should see `[INFO] Processing jobs from the [social-crosspost] queue.`

**RAM check first:** each instance ≈ worker (~75MB) + one claude subprocess
(~250MB) during a caption-gen → ~1.3GB peak for 4. Run `free -h` and confirm
headroom before enabling all 4; drop to `@{1..2}` on a constrained box (still
2× faster than serial). Without this pool the fan-out still works — it just
runs serially on the `default` worker (~2–6 min for 4 platforms).

### 2. Scheduler (crontab)

```bash
# On VPS, as the project user (claudesn):
crontab -u claudesn -e
# Paste the contents of portfolio-scheduler.crontab — exactly one line:
* * * * * cd /var/www/Portfolio_v2/backend && /usr/bin/php artisan schedule:run >> /dev/null 2>&1

# Verify it's installed:
crontab -u claudesn -l

# Watch it fire (within ~1 minute):
tail -f /var/log/syslog | grep CRON
tail -f /var/www/Portfolio_v2/backend/storage/logs/laravel.log
```

## Verifying the full pipeline works

```bash
# 1. Queue worker is running:
sudo systemctl is-active portfolio-queue.service   # → active

# 2. Scheduler fires every minute:
grep "schedule:run" /var/log/syslog | tail -5

# 3. Stuck job count is zero (or trending down):
mysql -u ali -p portfolio_v2 -e "SELECT COUNT(*) FROM jobs;"

# 4. Stuck LinkedIn drafts are getting reaped:
mysql -u ali -p portfolio_v2 -e "
  SELECT id, status, format, updated_at
  FROM linkedin_posts
  WHERE deleted_at IS NULL
    AND (
      status IN ('pending_generation','generating','validating')
      OR (status='manual_review' AND format='carousel')
    )
  ORDER BY updated_at DESC LIMIT 30;
"
```

## After every deploy

`scripts/deploy.sh` step 7 runs `php artisan queue:restart`, which signals
the running worker to gracefully exit at end of its current job.
`Restart=always` in the unit then brings it back with the new code.

No operator action required after deploys — but if `systemctl status` shows
`activating (auto-restart)` for more than ~30s, something fundamental broke;
check `journalctl -u portfolio-queue -n 100`.

## Troubleshooting

**26 stuck "in-progress" carousel drafts** (the symptom that prompted this
infra ship):

1. Confirm the queue worker is actually running:
   ```bash
   ps aux | grep queue:work
   sudo systemctl status portfolio-queue.service
   ```
   If it's not running, the systemd unit was never installed — run the steps
   in section 1 above.

2. Confirm the scheduler is actually running:
   ```bash
   crontab -u claudesn -l | grep schedule:run
   ```
   If empty, install the crontab line per section 2 above.

3. Manually reap stuck carousel images to confirm the new command works:
   ```bash
   cd /var/www/Portfolio_v2/backend
   php artisan linkedin:reap-stuck-carousel-images --dry-run
   php artisan linkedin:reap-stuck-carousel-images
   ```
