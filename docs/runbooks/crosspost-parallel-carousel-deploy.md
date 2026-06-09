# Runbook — Default Carousel + Parallel Cross-Post Fan-Out

**Ships:** [docs/plans/2026-06-09-default-carousel-all-platforms-parallel.md](../plans/2026-06-09-default-carousel-all-platforms-parallel.md)
**Date:** 2026-06-09
**Impact:** Every new blog post produces a CAROUSEL for LinkedIn + Instagram + TikTok + Threads, fanned out together as soon as the slides render.

---

## What changes for the operator

- **Before:** `/linkedin-gen` decided text vs carousel → mostly text → Instagram/TikTok never created. Fan-out only happened after LinkedIn reached `awaiting_publish`, on a 2-min cron.
- **After:** Backend forces every draft to carousel (`linkedin_force_carousel='true'`). The moment the carousel slides finish rendering, all 4 platform drafts are generated in parallel. Publishing is unchanged (still `social:publish-slot` atomic + your slot/approval).

Nothing in the publish flow changes — you still review/approve and posts ship at their scheduled slots.

---

## Deploy (automatic via `deploy.sh`)

```bash
git push origin main   # triggers GitHub Actions → deploy.sh on VPS
```

`deploy.sh` runs:
- `php artisan migrate --force` — **no new migrations** in this ship.
- Idempotent seeders → creates `linkedin_force_carousel` setting + the `social-cross-post:scan` scheduler row.
- `queue:restart` — reloads all workers with new code.

No nginx change. No env change required (the feature is on by default).

---

## Manual step 1 — Parallel worker pool (one-time, per VPS)

The 4 caption-gens run on a dedicated `social-crosspost` queue. Without a worker
on that queue they will sit unprocessed. Install the pool:

```bash
# Check RAM headroom first — each instance ≈ ~325MB peak during a claude caption-gen.
free -h

sudo cp /var/www/Portfolio_v2/scripts/systemd/portfolio-crosspost@.service /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now portfolio-crosspost@{1..4}     # 4 = one per platform

# Verify
sudo systemctl list-units 'portfolio-crosspost@*'           # → 4 active
sudo journalctl -u 'portfolio-crosspost@*' -f --since "5 minutes ago"
# expect: [INFO] Processing jobs from the [social-crosspost] queue.
```

On a constrained box use `@{1..2}` (still 2× faster than serial). **If you skip
this entirely, fan-out still works** — the jobs just run serially on the
`default` worker (~2–6 min for 4 platforms).

## Manual step 2 — Verify scheduler row

`/admin/settings?tab=scheduler` → confirm **Social — Cross-Post Fan-Out** is
present, enabled, cron `*/2 * * * *`. (The static `routes/console.php` entry was
removed; it's now this DB row.)

## Manual step 3 — Regenerate in-flight text drafts (optional)

Drafts created before this ship are `format=text`. To convert them to carousel,
open each in the admin and **Regenerate** — they'll re-run under force-carousel.
(Or let the next blog-scan pick up new posts as carousel automatically.)

---

## Verify it works end-to-end

1. Trigger a LinkedIn draft (blog scan or admin regenerate).
2. Watch it route to `/carousel-gen` (log: `linkedin_force_carousel ON — overriding plugin format to carousel`).
3. When all slides render (`image_status=done`), the log shows
   `all slides done — dispatched targeted cross-post fan-out`.
4. Within seconds, Instagram/TikTok/Threads/Facebook sibling drafts appear in
   the admin queue, generating in parallel.

```bash
# tail the relevant logs on VPS
tail -f /var/www/Portfolio_v2/backend/storage/logs/laravel.log | grep -E "force_carousel|cross-post fan-out|CrossPostScan"
```

---

## Rollback

Per-concern, no redeploy needed for the flag:

- **Disable force-carousel** (revert to plugin-decided format): set
  `linkedin_force_carousel='false'` via `/admin` settings (or
  `Setting::where('key','linkedin_force_carousel')->update(['value'=>'false'])`),
  then `php artisan config:clear`. New drafts honor the plugin again.
- **Disable early/auto fan-out**: toggle the **Social — Cross-Post Fan-Out** row
  OFF in the Scheduler tab. (Event-driven dispatch from the webhook still fires;
  to stop that too, revert the code.)
- **Stop the parallel pool**: `sudo systemctl disable --now portfolio-crosspost@{1..4}`.
  Fan-out jobs fall back to the `default` worker (serial).
- **Full code rollback**: revert the Phase A–G commits and redeploy.

---

## Notes / caveats

- **Aspect ratio is 4:5** for all platforms (shared LinkedIn-rendered slides;
  TikTok shows 4:5, not 9:16 native). Tunable later — no per-platform renderer.
- **More `manual_review` drafts possible**: forcing carousel removes the text
  fallback, so a `/carousel-gen` failure (e.g. Sonnet truncation) routes to
  `manual_review` instead of shipping text. Retry from the admin.
- **RAM**: 4 concurrent claude CLI processes during a fan-out. The leak-safe
  empty-MCP config (April 29 fix) applies. Watch `free -h` under load.
