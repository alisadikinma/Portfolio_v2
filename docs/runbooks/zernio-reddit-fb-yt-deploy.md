# Deploy runbook — Reddit + Facebook + YouTube via Zernio

**Date:** 2026-06-16
**Plan:** [docs/plans/2026-06-16-reddit-zernio-crosspost-plan.md](../plans/2026-06-16-reddit-zernio-crosspost-plan.md)
**Scope:** Adds Reddit (4th Zernio platform, reuses the Threads workspace key) + Facebook & YouTube
(new 3rd workspace key `zernio_api_key_fbyt`). Facebook migrates Publer→Zernio; YouTube is
video_full-only (single 60s → Short).

> ⚠️ **This deploy changes a LIVE publish path (Facebook) with no Publer fallback and is enabled
> on deploy.** The pre-deploy steps below are **BLOCKING** — skipping them breaks Facebook
> publishing on the first post after deploy.

---

## 0. Why this is risk-managed

- **Reddit** ships `crosspost_publisher_reddit = off` (seeded). It creates siblings but never
  publishes until you flip it. Zero risk on deploy.
- **Facebook** ships `crosspost_publisher_facebook = zernio` (cut from Publer, no fallback).
- **YouTube** ships `crosspost_publisher_youtube = zernio` and is exercised only from
  `/admin/video-full` (it has no carousel sibling / LinkedIn-draft tab).
- Master switch `ZERNIO_PUBLISH_ENABLED` still gates ALL Zernio publishing. If it's currently
  `false`, nothing publishes regardless of selectors.

---

## 1. BLOCKING pre-deploy (do BEFORE the push that lands this code)

1. **Connect a Facebook *Page* in the FB+YT Zernio workspace.** Zernio's FB API posts to **Pages
   only** — a personal profile will not publish. Publer-FB is retired, so this is the *only* FB
   path after deploy.
2. **Connect the YouTube *channel*** in the same FB+YT workspace (channel must not be suspended;
   60s clips are under the 15-min unverified cap, so phone-verification is optional but the upload
   scope must be granted).
3. Confirm the Reddit account (`u_alisadikinma`) is connected in the **Threads** workspace (Reddit
   reuses that key) — only needed before you flip Reddit on (step 4 below), not for deploy itself.

## 2. Deploy

Standard CI/CD: `git push origin main` → GitHub Actions → `deploy.sh` (migrate + idempotent
seeders + build + queue:restart). The two new migrations
(`2026_06_16_000001_create_reddit_posts_table`, `2026_06_16_000002_add_zernio_to_facebook_posts`)
and the re-seed of `ZernioSettingsSeeder` (8 new keys) run automatically.

## 3. Configure the fbyt key (admin UI — NOT `.env`)

1. `/admin/settings → Zernio` tab → paste the **Facebook + YouTube** workspace key into
   **"API Key — Facebook + YouTube workspace"** → **Save**.
2. Click **Verify** on that key → it lists the workspace accounts and **auto-fills** the
   `Facebook account ID` + `YouTube account ID` fields → **Save** again to persist them.
3. (Reddit) On the **Threads** workspace, Verify and confirm the Reddit account id auto-fills
   `Reddit account ID` → Save.

## 4. Live-probe BEFORE relying on the fan-out

With `ZERNIO_PUBLISH_ENABLED=true`:

- **Facebook**: trigger a carousel cross-post (or approve a Facebook sibling) → confirm a
  multi-image post lands on the Page.
- **YouTube**: from `/admin/video-full/{id}`, select **YouTube** + publish a finished 60s reel →
  confirm it appears as a **Short** with the AI-disclosure (`containsSyntheticMedia`) set.
- **Reddit**: keep `crosspost_publisher_reddit = off` until you run one probe — temporarily flip
  it to `zernio`, publish one carousel to `u_alisadikinma`, confirm the gallery posts, then decide
  whether to leave it on. Reddit's 53.9% platform failure rate is why it defaults off.

## 5. Rotate the keys 🔑

Both pasted Zernio workspace keys (the Threads key and the FB+YT key `sk_e4ceed…`) were entered in
plaintext during development → **rotate them in the Zernio dashboard after deploy**, then re-paste
+ Verify + Save the new keys via the admin UI. The backend always reads keys from the encrypted
`zernio` settings group — they are never hardcoded.

---

## Rollback

- **Disable everything fast:** set `ZERNIO_PUBLISH_ENABLED=false` (master switch) — stops all
  Zernio publishing; drafts/siblings stay put, re-dispatchable later.
- **Per-platform:** set `crosspost_publisher_{facebook|youtube|reddit}` = `off` in the Zernio tab.
- **Facebook only, revert to Publer:** set `crosspost_publisher_facebook = publer` (the Publer-FB
  adapter classes are retained as a selectable fallback — not deleted).

## What's intentionally NOT included (per design — YAGNI)

Per-draft subreddit picker · Reddit/YouTube flair/playlists/thumbnails · `video_rebrand` → Reddit/FB/YT
(multi-clip; only `video_full` single-video reaches YouTube) · YouTube carousel (impossible — video-only) ·
`BackfillZernioPostUrls` Reddit branch (Reddit publishes fine without the "Open on Reddit" link backfill).
