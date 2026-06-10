# IG Repurpose → Carousel — Operator Runbook

Feature: send an Instagram post URL to the Telegram bot → it captures the
slides, fact-checks the claims, rewrites a sharper version in Ali's voice, and
lands either a **blog idea** (Content Engine) or a **carousel draft**
(`/admin/draft-posts`) + cross-post fan-out.

Design + plan: [docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md](../plans/2026-06-10-telegram-ig-repurpose-carousel.md).

Pipeline FSM (`repurpose_jobs.status`):
```
received → capturing → captured → extracting → extracted →
researching → researched → rewriting → rewritten → finalizing → drafted
(any step → failed; failed → re-enter any step entrypoint for retry)
```
`received` = waiting for the operator to tap a mode button (📝 Blog+Carousel / 🎠 Carousel saja).

---

## One-time VPS setup

Run as the **queue-worker user** (`claudesn` — same user/key as the carousel-gen
pipeline). Never edit files on the VPS; deploy via `git push` (auto CI/CD).

### 1. Playwright headless Chromium (Phase B capture)

The capture script is `scripts/playwright/ig-capture.cjs` (repo root). It needs
Node + the `playwright` package + a Chromium browser binary on the VPS.

```bash
sudo -iu claudesn
cd /var/www/Portfolio_v2          # repo root (scripts/ lives here)
# Install playwright + browser (once; ~300MB). Pin to repo if you add it to
# a package.json, else install globally for claudesn:
npm install playwright            # or: npm i -g playwright
npx playwright install chromium   # downloads the browser binary
npx playwright install-deps chromium 2>/dev/null || true   # system libs (may need sudo)
# Smoke test (public post):
node scripts/playwright/ig-capture.cjs --url 'https://www.instagram.com/p/SOME_ID/' --out /tmp/igtest --timeout 60
ls /tmp/igtest    # expect slide-01.jpg ... + caption.txt; stdout ends with {"ok":true,...}
```

Memory: Chromium runs headless `--no-sandbox --single-process` and is killed
after each capture. On a 2-core VPS keep an eye on `free -h` if many jobs queue.

If `node` / `playwright` is missing at runtime the job fails cleanly with
`playwright_not_installed` and the operator gets a Telegram notice — no silent hang.

### 2. Claude CLI exec (Phases C/D/E — vision, research, rewrite)

Reuses the article-generation VPS auth by default (`services.repurpose.*` falls
back to `ARTICLE_GEN_*`). No new auth if article generation already works.
**All claude calls carry `--mcp-config <empty-mcp> --strict-mcp-config`** (anti
MCP-leak) — confirm `/home/claudesn/empty-mcp.json` exists (it already does for
the article/linkedin pipelines).

Research (Phase D) uses Claude CLI's **native** WebSearch/WebFetch (permission-
gated, not MCP) — no firecrawl MCP needed, empty-mcp guard intact.

### 3. Inbound Telegram webhook

The bot is **reused** (same token/secret/webhook as the LinkedIn retry buttons).
Inbound text messages are already routed — `telegram:set-webhook` covers it. No
new webhook registration. Confirm the webhook is set:
```bash
cd /var/www/Portfolio_v2/backend && php artisan telegram:set-webhook   # idempotent
```

### 4. Optional — authenticated capture (IG login wall)

Public posts mostly capture anonymously. For reliability on wall-gated carousels,
provide a Playwright `storageState` cookie JSON and point the config at it:
```bash
# Generate once on a desktop with `npx playwright codegen instagram.com` → save storageState,
# scp to the VPS (claudesn-owned, mode 600), then set:
#   IG_CAPTURE_STORAGE_STATE=/home/claudesn/ig-storage-state.json
```
Refresh periodically (IG sessions expire). Leave empty for anonymous capture.

---

## Enable the feature

```bash
# In /admin/settings (Telegram card) OR directly:
# settings: group=telegram, key=telegram_repurpose_enabled → 'true'
```
Default is `'false'` (seeded by `TelegramSettingsSeeder`). While off, IG URLs sent
to the bot are silently ignored.

Verify the retention reaper appears in **/admin/settings?tab=scheduler**:
`repurpose:reap` (daily 04:00 WIB, `--days=7`). Seeded idempotently by
`ScheduledCommandSeeder` on deploy.

---

## Operate

1. Send an Instagram post/reel URL to the bot (optionally add a one-line angle
   after the URL, e.g. `https://instagram.com/p/ABC/ fokus ke sisi bisnis`).
2. Bot replies with two buttons — tap **📝 Blog + Carousel** or **🎠 Carousel saja**.
3. Pipeline runs (capture → extract → research → rewrite → finalize).
4. Done:
   - **blog mode** → ContentIdea `article_ready` in `/admin/content-engine`
     (source=instagram, `source_data.source=ig_repurpose`). Approve → Gate-2 images
     → publish → carousel + cross-post fire automatically (event-driven ingest).
   - **carousel mode** → carousel draft in `/admin/draft-posts/{id}` (anchored to
     an unpublished blog Post) + render + cross-post fan-out.
5. Failure at any step → Telegram notice with the reason; the artifact dir is
   retained for retry/debug (success purges it inline).

## Retention

- Success → `storage/app/repurpose/{job}/` purged inline at finalize (D6).
- Failed/abandoned → swept by `repurpose:reap` after 7 days (`--days=N` override,
  `--dry-run` to preview).

---

## Feasibility flags (watch on first runs)

1. **Chromium on VPS** — install step above; job fails clean if missing.
2. **Vision via Claude CLI image-input** — if `SlideVisionExtractor` returns
   `vision_unparseable` repeatedly, the CLI may not be reading local images
   reliably; fallback is Anthropic API image blocks (needs an API key) — not
   built yet, flag if hit.
3. **IG login wall** — anonymous capture fails on private/wall posts → job
   `failed` (`login_wall`); use the optional `storageState` cookie.
4. **Render completion = poller** — carousel slide render finishes via the
   `blog:process-images` poller, never a GeminiGen webhook (keep that cron on).
5. **MCP leak** — every claude call already passes the empty-mcp flags.

## Rollback

Flip `telegram_repurpose_enabled='false'` — IG URLs are ignored, the rest of the
bot (LinkedIn retry buttons) is unaffected. No schema rollback needed; tables
and the reaper row are inert when the toggle is off.
