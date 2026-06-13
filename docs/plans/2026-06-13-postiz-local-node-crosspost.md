# Postiz Local-Node Cross-Post Hub (VPS-coordinated, pull model)

**Date:** 2026-06-13
**Status:** Design approved — ready for implementation plan
**Author:** Ali Sadikin (brainstorm via gaspol-brainstorm)
**Related memory:** [[postiz-self-host-publish]], [[publer-video-carousel-impossible]], [[video-rebrand-build-state]], [[ig-video-carousel-rebrand-poc]]

---

## Design

### Problem & Motivation

Postiz ([gitroomhq/postiz-app](https://github.com/gitroomhq/postiz-app), AGPL-3.0, free self-host) is the **source-verified** path to publish IG **video carousels** — the official Meta Graph child-container status-poll sequence that Publer crashes on ([[publer-video-carousel-impossible]]). The `video_rebrand` feature already SHIPPED but ships with **manual download** (operator hand-posts in IG app); Postiz auto-publish was deferred.

Postiz's stack is heavy — **Postgres + Redis + Temporal + Node** — and the production Hostinger VPS is already saturated with Laravel + MySQL + queue workers. **Decision: host Postiz on a local machine, not the VPS.** The VPS remains the orchestrator (it already owns all scheduling intelligence); local Postiz is a dumb "publish now" executor.

### Locked Decisions (from brainstorm Q&A)

| # | Decision | Rationale |
|---|---|---|
| D1 | **Postiz on local PC**, not VPS | VPS resource-limited; Temporal+PG+Redis+Node shouldn't share the prod box |
| D2 | **Pull model** — local polls VPS, no inbound to local | NAT-friendly, zero attack surface; publish calls go OUT to Meta only |
| D3 | **Full cross-post hub** — Postiz replaces Publer for ALL platforms (IG/LinkedIn/TikTok/FB/Threads) | One uniform publish backbone |
| D4 | **Keep Publer as fallback** | When local PC is offline at slot time, VPS auto-fires Publer for Publer-capable platforms; IG video carousel waits (only Postiz can do it) |
| D5 | **VPS owns the timer** — marks job `ready_to_publish` at slot; local executes | Single timer authority; reuses existing cron + fixed-slot scheduler; simplest reasoning |
| D6 | **Job-lease anti-double-publish** (claim-on-fetch + per-platform callback) | Prevents the fallback race from double-posting |

### Architecture (3 components)

```
┌─────────────────────────────────────────────┐         ┌──────────────────────────────┐
│  VPS (alisadikinma.com) — ORCHESTRATOR        │         │  LOCAL PC — DUMB EXECUTOR      │
│  Laravel + MySQL + cron + queue (existing)    │         │  (mini-PC/NUC ideal, 24/7)     │
│                                               │         │                              │
│  • Content Calendar + FSM + scheduler         │         │  ┌────────────────────────┐  │
│  • slot fires → job → `ready_to_publish`      │ ◀─poll──┤  │ Poller (Node+node-cron)│  │
│  • GET  /api/automation/postiz/pending        │         │  │ ~80 LoC                │  │
│      → atomic CLAIM + lease (10m)             │ ──job──▶│  │  1. claim job          │  │
│      → media URL + caption + integration_id   │         │  │  2. fetch media (HTTPS)│  │
│  • POST /api/automation/postiz/{job}/result   │ ◀callback  │  3. POST /upload       │  │
│      → flip FSM, store permalink, mark        │         │  │  4. POST /posts {now}  │  │
│         published_platforms[]                 │         │  │  5. callback result    │  │
│  • POST /api/automation/postiz/channels/sync  │ ◀─sync──┤  └──────────┬─────────────┘  │
│      → store {platform→integration_id}        │         │             ▼                │
│  • Watchdog cron (every min):                 │         │  ┌────────────────────────┐  │
│      job past slot+N AND (unclaimed OR lease  │         │  │ Postiz (Docker Compose)│  │
│      expired) AND platform∉published          │         │  │ Postgres+Redis+Temporal│  │
│      → fire Publer fallback (Publer-capable)  │         │  │ +Node                  │  │
│      → IG-video-carousel → wait + Telegram    │         │  │ /public/v1 API         │  │
│                                               │         │  └──────────┬─────────────┘  │
│  • Publer (FALLBACK ONLY, kept)               │         │       Cloudflare Tunnel      │
└─────────────────────────────────────────────┘         │   postiz.alisadikinma.com    │
                                                          │   (OAuth callback, outbound) │
        media served from /storage (public, 206) ────────▶│   → out to Meta/LinkedIn/... │
                                                          └──────────────────────────────┘
```

#### 1. VPS — orchestrator & source of truth (Laravel, mostly existing)

- Content Calendar + FSM + fixed-slot scheduler stay on VPS. At slot time, instead of firing Publer directly, the job flips to a **new FSM state `ready_to_publish`**.
- **3 new endpoints** (token-auth, reuse the automation-token pattern):
  - `GET /api/automation/postiz/pending` — returns `ready_to_publish` jobs **with atomic claim** (see Job-Lease below) + upcoming jobs (next 24–48h, for pre-fetch). Payload per job: `{job_id, platforms[], media_urls[], caption, hashtags, post_type, integration_id_per_platform, lease_until}`.
  - `POST /api/automation/postiz/{job}/result` — callback `{platform, status: published|failed, permalink, error}`. Appends to `published_platforms[]`, flips FSM to `published` when all platforms done (or `failed`).
  - `POST /api/automation/postiz/channels/sync` — local reports Postiz integration list; VPS upserts `postiz_channels` mapping.
- **Deadline watchdog** (cron every minute, reuse `DynamicScheduleRegistrar`): fire Publer fallback for a job ONLY if `ready_to_publish` AND (never claimed OR lease expired) AND target platform ∉ `published_platforms`. IG video carousel → no fallback, just wait + Telegram alert.

#### 2. Local PC — dumb executor (new machine)

- **Postiz stack** via Docker Compose (Postgres + Redis + Temporal + Node). ~2–4 GB RAM idle; **8 GB min / 16 GB comfortable**. Mini-PC Intel N100 16GB (~$150–200, ~6W) ideal for 24/7.
- **Cloudflare Tunnel** (`cloudflared`, **named tunnel** for stable URL) → `postiz.alisadikinma.com`. Outbound-only, zero inbound port, Cloudflare-Access-gateable. Required only during OAuth channel connect; token refresh runs outbound via Temporal (tunnel may idle after). Kept up permanently for convenience.
- **Poller** (Node + node-cron, ~80 LoC; can be a 4th container in the same compose):
  - Calendar-sync (~every 10–15 min): pre-fetch media for upcoming jobs.
  - Ready-poll (~every 2 min): claim `ready` jobs → fetch media from VPS `/storage` (HTTPS) → `POST /public/v1/upload` (multipart) → `POST /public/v1/posts {type:now}` → callback result per platform.
  - Integration-sync (startup + periodic): push Postiz integration list to VPS `channels/sync`.

#### 3. Publer — fallback only (kept on VPS, not ripped out)

Retained behind the watchdog. LinkedIn direct-OAuth path also kept during cutover (see Migration).

### Job-Lease: anti-double-publish (core safety mechanism)

The race: VPS marks `ready` at 18:00; local publishes 18:01 (IG video child-poll ~4 min); watchdog deadline 18:05 → watchdog fires Publer **while local is mid-publish** → **double post** (IG has no dedupe → real harm).

**Solution — claim-on-fetch lease + per-platform completion callback:**

New job columns: `claimed_by`, `claimed_at`, `publish_lease_until`, `published_platforms` (JSON).

1. `GET /pending` performs an **atomic claim** inside a transaction (`SELECT … FOR UPDATE`): on fetch, job is marked `claimed_by=local, publish_lease_until=now()+10m`. Claimed jobs are NOT returned to subsequent polls; watchdog **skips jobs with an active lease**.
2. Watchdog fires Publer fallback ONLY if: `ready_to_publish` AND (never claimed OR lease expired) AND target platform ∉ `published_platforms`.
   - **PC offline** → never claimed → watchdog fallback at deadline ✓
   - **PC online but slow** → active lease → watchdog waits, no double ✓
   - **Local crash mid-publish** → lease expires (10m) → watchdog may fallback, but **skips platforms already in `published_platforms`** (local callbacks per-platform as each completes) → no re-publish of done platforms ✓
3. Lease window ≥ worst-case publish time. IG video carousel child-poll ~4 min → lease **10m** safe.

### OAuth tunnel + token refresh

- **Cloudflare named tunnel** (stable URL — quick-tunnel's random URL would mismatch redirect URIs). `NEXT_PUBLIC_BACKEND_URL=https://postiz.alisadikinma.com`.
- Tunnel required only at **channel connect** (one-time per channel): Postiz UI → platform OAuth → callback to tunnel URL → token stored.
- **Token refresh is outbound** (Temporal workflow): IG/FB long-lived 60d, LinkedIn 60d + refresh, TikTok 24h + refresh — no inbound needed. Tunnel may idle after connect; re-needed only if a refresh token itself expires (>60d no posting).
- Redirect URI in each dev-app (Meta/LinkedIn/TikTok) must match the tunnel URL exactly.

### Channel ID mapping (auto-sync, never manual)

- Each Postiz channel = "integration" with an `id` used in `POST /posts → integration:{id}`.
- Local poller **auto-syncs**: fetch Postiz integration list → `POST /postiz/channels/sync` → VPS upserts `postiz_channels {platform, handle, postiz_integration_id, enabled}`.
- At publish time **VPS resolves** platform→integration_id and sends it in the `pending` payload; local stays dumb (just forwards the ID to Postiz).
- Multi-account keyed `(platform, handle)`; single-operator → 1:1.

### LinkedIn migration (D3 = replace Publer total — phased, not rip-and-replace)

LinkedIn currently uses **direct OAuth on the VPS**. Cutover runs **in parallel**: connect LinkedIn in Postiz, run both paths side-by-side, retire the VPS LinkedIn OAuth/publish path **only after** Postiz proves stable. Reduces risk.

### Data Integration Map

| Component | Data Source | Exists? | Notes |
|---|---|---|---|
| Scheduled jobs + datetime | Content Calendar / social publish (VPS) | ✅ | Add `ready_to_publish` FSM state + lease columns |
| Media (MP4/PNG) | `alisadikinma.com/storage/...` (public, 206) | ✅ | Local fetch via HTTPS, no special channel |
| `GET /postiz/pending` (+claim) | New Laravel controller | ❌ | Token-auth; atomic claim + lease |
| `POST /postiz/{job}/result` | New Laravel controller | ❌ | Flip FSM, store permalink, append published_platforms |
| `POST /postiz/channels/sync` | New Laravel controller | ❌ | Upsert postiz_channels mapping |
| `postiz_channels` table | New migration | ❌ | platform→integration_id |
| Watchdog → Publer fallback | `PublerPayloadBuilder` / `PublishViaPubler` | ✅ reuse | Trigger only on claim-aware deadline |
| Postiz publish API | `/public/v1/upload` + `/public/v1/posts` | ✅ Postiz | Source-verified IG video carousel |
| OAuth callback | Cloudflare named tunnel → Postiz | ❌ | One-time per channel |
| Local poller | New standalone Node service | ❌ | node-cron, claim+upload+publish+callback |
| Postiz stack | Docker Compose (PG+Redis+Temporal+Node) | ❌ | Local machine, 8GB+ RAM |

### Resource footprint (local) — corrected after source read

Real `docker-compose.yaml` stack is heavier than first estimated: `postiz` (NestJS backend + Next.js frontend) + `postiz-postgres` (PG17) + `postiz-redis` (7.2) + `temporal` (1.28.1) + **`temporal-postgresql` (PG16, separate)** + **`temporal-elasticsearch` (ES 7.17, `ENABLE_ES=true`)** + `temporal-ui` + `temporal-admin-tools`. `spotlight` (Sentry) is **optional** — no service depends on it; drop it.
- ES heap is configured small (`-Xmx256m`); Postiz Node app is the real RAM consumer (~1 GB). Slim baseline ~1.4 GB + Node → realistic **~3–4 GB idle**. **8 GB min (tight), 16 GB comfortable.** Mini-PC N100 16GB ideal for 24/7. Disk ~5–10 GB. No encoding locally.
- **Optimization (optional):** Temporal can drop Elasticsearch (`ENABLE_ES=false` + remove the ES service + its `depends_on`) → standard SQL visibility, saves ~0.5 GB. Document but don't require in v1.

### Source-Verification Findings (postiz-app source read 2026-06-13)

Verified directly against `/Users/alisadikin/Drive-D/Projects/postiz-app` — these are the **real contracts** the poller (Phase I) and endpoints must honor:

- **Public API** (`apps/backend/src/public-api/routes/v1/public.integrations.controller.ts`): `POST /public/v1/upload` (multipart `file`), `POST /public/v1/posts`, `GET /public/v1/posts` (date-range), `GET /public/v1/integrations`.
- **Auth** (`services/auth/public.auth.middleware.ts`): API key in `Authorization` header (plain key, or `pos_`-prefixed OAuth token). Generated in Postiz Settings → Developers.
- **POST envelope** (`dtos/posts/create.post.dto.ts`): `{type:'now'|'schedule'|'draft'|'update', date:ISO (REQUIRED even for now), shortLink:bool (REQUIRED), tags:[] (REQUIRED), posts:[{integration:{id}, value:[{content, image:[{id,path}]}], settings:{post_type,...}}]}`. Backend auto-overwrites `settings.__type` from the integration's `providerIdentifier` (`posts.service.ts mapTypeToPost`) — but provider settings (IG `post_type:'post'|'story'`) must still be passed. **IG reels = `post_type:'post'` + `is_trial_reel`/`audio`, NOT a separate type** (irrelevant for our carousels).
- **Upload response** (`media.repository.ts saveFile`): `{id, name, originalName, path, thumbnail, alt}` — use `id` + `path`.
- **IG carousel — ALL THREE TYPES** (`instagram.provider.ts:597-822`): the `post()` `.map` decides each child container's type **per-item** via `hasExtension(m.path,'mp4')` — mp4 → `video_url&media_type=VIDEO`, else → `image_url` — all tagged `is_carousel_item=true`, then grouped into one parent `media_type=CAROUSEL&children=…` → `media_publish`. Each VIDEO child is 30s status_code-polled until ≠IN_PROGRESS. **There is NO same-type guard** (grep-confirmed): the only carousel validations are ≤10 items (`:56`) and aspect 4:5–1.91:1 (`:289/318`); "Trial Reels must be a video" (`:60/66`) applies only to the trial-reel flag, not carousels. ⇒ **all-image, all-video, AND mixed video+image carousels all work** — exactly where Publer dies (`undefined method 'first' for nil` on mixed/all-video). Our 1080×1350 (4:5) rebrand slides pass the aspect gate.
- **ASYNC publish** (`posts.service.ts`, Temporal orchestrator): `type:'now'` sets publishDate=now and **enqueues to Temporal** — the API returns BEFORE the post is live. Confirmation = poll `GET /public/v1/posts` (`state` ∈ `QUEUE/PUBLISHED/ERROR`, `releaseURL` = permalink, `releaseId` = platform post id) OR a Postiz **webhook** (per-org, `Webhooks` model; fires on success only via `post.activity.ts sendWebhooks`, payload includes `releaseURL`+`state`; **no error webhook**).
- **State enum** (`schema.prisma`): `QUEUE | PUBLISHED | ERROR | DRAFT`. `releaseURL`/`releaseId` persisted on publish (`posts.repository.ts updatePost`).
- **Integration list** (`GET /public/v1/integrations`): `[{id, name, identifier(=platform), picture, disabled, profile(=handle), customer}]` → maps cleanly to `postiz_channels {platform=identifier, handle=profile, postiz_integration_id=id}`.
- **Rate limit** (`app.module.ts ThrottlerModule`): `API_LIMIT` env, default 90/hr, **docker-compose default 30/hr** — raise on self-host (carousel = ~11 calls).
- **Provider coverage CONFIRMED:** `linkedin` + `linkedin-page`, `instagram` (FB Business) + `instagram-standalone`, `tiktok`, `threads`, `facebook`, `medium` (canonical via `settings.canonical` → `medium.provider.ts:120`).

### Operator prerequisites (infra)

- Local Docker-capable machine (mini-PC/NUC recommended for 24/7; regular PC works with Publer fallback covering downtime).
- Cloudflare account (free tunnel) + `postiz.alisadikinma.com` subdomain.
- OAuth dev-app keys per platform (Meta FB+IG, LinkedIn, TikTok) in Postiz `.env`.

### Anti-patterns / explicit non-goals (v1)

- ❌ NO inbound exposure of the local PC (pull model only).
- ❌ NO blind 1-min polling — calendar-sync + claim-poll; VPS owns the timer.
- ❌ NO ripping out Publer or LinkedIn direct-OAuth on day 1 (parallel cutover).
- ❌ NO publishing without a job lease (double-post guard is mandatory).
- ❌ NO auto-DM / comment-to-DM (separate Meta App Review build, deferred per [[postiz-self-host-publish]]).
- ❌ NO local-side scheduling logic (VPS is single timer authority).

### Open questions for the implementation plan

- Watchdog deadline N (minutes after slot) before Publer fallback fires — tune vs lease (10m).
- Whether the poller is a compose container or a host-level service on the local machine.
- Hardware decision (dedicated mini-PC 24/7 vs dev PC + aggressive fallback) — affects fallback aggressiveness.
- Exact FSM wiring point in the existing social-publish flow where `ready_to_publish` is inserted.

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

### Goal

Wire a self-hosted Postiz (running on a local machine) as the primary cross-post publish backbone, coordinated by the existing Laravel VPS via a **pull model**: the VPS marks per-platform publish work `ready_to_publish`, a local Node poller claims it under a lease, publishes through Postiz's `/public/v1` API (the only path that can do IG video carousels), and callbacks the result. Publer is retained as an automatic **fallback** fired by a claim-aware watchdog when the local node is offline. This unblocks autonomous IG-video-carousel publishing without burdening the resource-limited VPS, while preserving 24/7 reliability for Publer-capable platforms.

### Architecture Context (from CLAUDE.md + code read)

- **Cross-post unit** = primary [`LinkedInPost`](backend/app/Models/LinkedInPost.php) + per-platform sibling rows [`InstagramPost`](backend/app/Models/InstagramPost.php) / [`TiktokPost`](backend/app/Models/TiktokPost.php) / [`ThreadsPost`](backend/app/Models/ThreadsPost.php) / [`FacebookPost`](backend/app/Models/FacebookPost.php). The publish unit is **per-(platform, sibling_post_id)**.
- **Slot orchestrator** = [`PublishSlotOrchestrator`](backend/app/Console/Commands/PublishSlotOrchestrator.php) (`social:publish-slot`, every-minute). At slot tick it publishes LinkedIn, then `PublishViaPubler::dispatch($platform, $siblingPostId)` per non-null sibling on the `social-crosspost` queue.
- **Publer path (reuse as fallback)** = [`PublishViaPubler`](backend/app/Jobs/PublishViaPubler.php) job + [`PublerClient`](backend/app/Services/PublerClient.php) + [`PublerPayloadBuilder`](backend/app/Services/PublerPayloadBuilder.php). Idempotent on `publer_post_id`.
- **FSM infra** = [`HasStatusTransitions`](backend/app/Traits/HasStatusTransitions.php) + [`PipelineGuard`](backend/app/Services/PipelineGuard.php) (enum-generic). **Decision: do NOT extend `LinkedInPostStatus`.** Use a decoupled `postiz_publish_jobs` side-table with its own minimal status (`ready_to_publish`/`claimed`/`published`/`failed`).
- **Cron registration** = DB-driven [`DynamicScheduleRegistrar`](backend/app/Services/DynamicScheduleRegistrar.php) + [`ScheduledCommandSeeder`](backend/database/seeders/ScheduledCommandSeeder.php) (idempotent `firstOrCreate`).
- **Auth** = protected automation routes use `auth:sanctum` bearer (automation token). Postiz endpoints live under `Route::middleware(['auth:sanctum'])->prefix('automation')`.
- **Settings** = key-value `Setting` rows by group, seeded idempotently (mirror [`LinkedInSettingsSeeder`](backend/database/seeders/LinkedInSettingsSeeder.php) pattern).
- **Media** = composited MP4/PNG already public at `alisadikinma.com/storage/...` (206-served). Local fetches over HTTPS — no special transfer channel.

### Tech Stack

- **VPS:** Laravel 12, PHP 8.2, MySQL 8, Sanctum. Tests = phpunit via Docker on dev Mac (no host PHP): `docker run --rm -v "$(pwd)":/app -w /app serversideup/php:8.2-cli php vendor/bin/phpunit --filter <Name>`.
- **Local node:** Postiz (Docker Compose: Postgres + Redis + Temporal + Node), `cloudflared` named tunnel, Node 20 poller (`node-cron`, `undici`/fetch). Poller tests = `node --test`.
- **Patterns to reuse:** `PublishViaPubler` (fallback), `DynamicScheduleRegistrar`/seeder (watchdog cron), `auth:sanctum` automation token, `Setting` group seeder, atomic `DB::transaction` + `lockForUpdate()` (mirrors `LinkedInCarouselImageService::handleWebhook`).

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Sibling media URLs + caption | InstagramPost/TiktokPost/ThreadsPost/FacebookPost rows | Eloquent | ✅ | Use existing columns (media_urls, caption) |
| Composited media files | `alisadikinma.com/storage/...` | HTTPS public | ✅ | Local fetch directly |
| `postiz_publish_jobs` (status+lease) | New table | Eloquent model | ❌ | Create (Phase A) |
| `postiz_channels` (platform→integration_id) | New table | Eloquent model | ❌ | Create (Phase A) |
| Job creation at slot tick | PublishSlotOrchestrator + sibling crons | modify dispatch point | ✅ | Branch on `postiz_enabled` (Phase B) |
| `GET /automation/postiz/pending` (claim+lease) | New controller | atomic SELECT…FOR UPDATE | ❌ | Create (Phase C) |
| `POST /automation/postiz/{job}/result` | New controller | Eloquent update | ❌ | Create (Phase D) |
| `POST /automation/postiz/channels/sync` | New controller | upsert | ❌ | Create (Phase E) |
| Watchdog → Publer fallback | `postiz:reap-unclaimed` cmd → `PublishViaPubler::dispatch` | reuse existing job | ✅ (job) / ❌ (cmd) | Create cmd (Phase F) |
| `postiz` settings group | Setting rows + seeder | mirror LinkedInSettingsSeeder | ❌ | Create (Phase G) |
| Postiz stack + tunnel | Docker Compose + cloudflared | operator infra | ❌ | Runbook (Phase H) |
| Local poller | Node service | node-cron | ❌ | Create (Phase I) |
| LinkedIn cutover | parallel run + monitor | runbook | ✅ (existing OAuth kept) | Runbook (Phase J) |

---

### Phase A: Schema — `postiz_publish_jobs` + `postiz_channels`

**Estimated time:** 12 minutes

**Files:**
- Create: `backend/database/migrations/2026_06_13_000001_create_postiz_publish_jobs_table.php`
- Create: `backend/database/migrations/2026_06_13_000002_create_postiz_channels_table.php`
- Create: `backend/app/Models/PostizPublishJob.php`, `backend/app/Models/PostizChannel.php`
- Create: `backend/database/factories/PostizPublishJobFactory.php`, `PostizChannelFactory.php`
- Test: `backend/tests/Unit/PostizPublishJobModelTest.php`

**Schema — `postiz_publish_jobs`:** `id`, `platform` (string, indexed), `sibling_post_id` (uint), `sibling_type` (string — IG/TT/TH/FB model class), `status` (enum: `ready_to_publish`/`claimed`/**`accepted`**/`published`/`failed`, default `ready_to_publish`, indexed), `claimed_by` (string nullable), `claimed_at` (ts nullable), `publish_lease_until` (ts nullable, indexed), `slot_due_at` (ts — when it became due, for watchdog deadline), `postiz_integration_id` (string nullable — resolved at create), **`postiz_post_id` (string nullable — Postiz `Post.id` returned from POST /posts, used to correlate GET /posts state + webhook)**, `permalink` (string nullable — Postiz `releaseURL`), `last_error` (text nullable), `fallback_fired_at` (ts nullable), timestamps. Unique index `(platform, sibling_post_id)` (one live Postiz job per sibling — idempotency).

> **State semantics (async-aware):** `ready_to_publish` → `claimed` (local leased it) → **`accepted`** (poller got OK from `POST /public/v1/posts`; Temporal now owns it — **watchdog hands off, NO Publer fallback past this point** to avoid double-publish) → `published` (poller confirmed `state=PUBLISHED` + stored `releaseURL`) OR `failed` (`state=ERROR` or enqueue failed). Publer fallback fires ONLY for jobs that never reached `accepted`.

**Schema — `postiz_channels`:** `id`, `platform` (string), `handle` (string), `postiz_integration_id` (string), `enabled` (bool default true), `last_synced_at` (ts), timestamps. Unique `(platform, handle)`.

**Steps:**
1. Write failing test for `PostizPublishJob` model casts + `scopeClaimable`. Expected error: `Error: Class "App\Models\PostizPublishJob" not found`.
2. Run test (Docker phpunit), confirm fail for that reason.
3. Write both migrations + both models (casts: `claimed_at`/`publish_lease_until`/`slot_due_at` → datetime; status string) + `scopeClaimable($q)` = `status=ready_to_publish` AND (`publish_lease_until` IS NULL OR `< now()`).
4. Write factories. Run `migrate` on the sqlite test DB.
5. Run tests, confirm pass.
6. Commit: "feat(postiz): postiz_publish_jobs + postiz_channels schema + models".

**Verification:**
- [ ] Both migrations run clean on sqlite (test) — `php artisan migrate` in CI DB.
- [ ] `PostizPublishJob::factory()->create()` persists; `scopeClaimable` returns only unclaimed/lease-expired ready rows.
- [ ] Unique `(platform, sibling_post_id)` enforced (second create throws).
- [ ] No placeholder/TODO comments in new code.

---

### Phase B: Job creation at slot tick (gated by `postiz_enabled`)

**Estimated time:** 14 minutes

**Files:**
- Create: `backend/app/Services/PostizPublishDispatcher.php` (decides Postiz-job-vs-Publer at the dispatch point)
- Modify: `backend/app/Console/Commands/PublishSlotOrchestrator.php` (replace direct `PublishViaPubler::dispatch` with `PostizPublishDispatcher::dispatchSibling`)
- Test: `backend/tests/Feature/PostizPublishDispatcherTest.php`

**Behavior:** `PostizPublishDispatcher::dispatchSibling(string $platform, int $siblingPostId)`:
- If `postiz_enabled` setting is `false` → `PublishViaPubler::dispatch($platform, $siblingPostId)` (unchanged legacy path).
- If `true` → resolve `postiz_integration_id` from `postiz_channels` for that platform; create a `postiz_publish_jobs` row (`status=ready_to_publish`, `slot_due_at=now()`) via `firstOrCreate` on `(platform, sibling_post_id)` (idempotent re-dispatch). If no enabled channel mapping exists → log warning + fall back to Publer (defensive: never silently drop a publish).

**Steps:**
1. Write failing test: `postiz_enabled=true` + mapped channel → creates a `ready_to_publish` job, does NOT dispatch Publer (`Queue::fake()` assertNotPushed). Expected error: `Error: Class "App\Services\PostizPublishDispatcher" not found`.
2. Run test, confirm fail.
3. Implement `PostizPublishDispatcher`. Wire into `PublishSlotOrchestrator` at the sibling dispatch loop.
4. Add tests: `postiz_enabled=false` → dispatches Publer, no job row; mapped-channel-missing → Publer fallback + warning.
5. Run tests, confirm pass.
6. Commit: "feat(postiz): branch slot dispatch to Postiz job vs Publer fallback".

**Verification:**
- [ ] `postiz_enabled=true` + channel mapped → `postiz_publish_jobs` row created, Publer NOT dispatched.
- [ ] `postiz_enabled=false` → legacy Publer dispatch unchanged (regression guard).
- [ ] Re-dispatch same sibling → no duplicate job (idempotent `firstOrCreate`).
- [ ] Missing channel mapping → Publer fallback + logged warning (no silent drop).
- [ ] No placeholder/TODO comments in new code.

---

### Phase C: `GET /automation/postiz/pending` — atomic claim + lease

**Estimated time:** 14 minutes

**Files:**
- Create: `backend/app/Http/Controllers/Api/Automation/PostizPublishController.php` (`pending` action)
- Modify: `backend/routes/api.php` (add under protected `automation` prefix)
- Test: `backend/tests/Feature/PostizPendingClaimTest.php`

**Behavior:** `GET /automation/postiz/pending?worker=local-1&limit=10`:
- Inside `DB::transaction`: select `scopeClaimable` jobs `lockForUpdate()`, limit; for each → set `status=claimed`, `claimed_by=$worker`, `claimed_at=now()`, `publish_lease_until=now()+postiz_lease_minutes` (setting, default 10).
- Return per job: `{job_id, platform, postiz_integration_id, media_urls[], caption, hashtags, post_type, lease_until}` (media + caption resolved from the sibling row via `sibling_type`/`sibling_post_id`).
- Also return `upcoming[]` (jobs `ready_to_publish` with `slot_due_at` within next 48h, NOT claimed) for prefetch — read-only.

**Steps:**
1. Write failing test: two concurrent-style claims don't double-claim the same row; claimed row gets lease set. Expected error: `404` / route-not-found.
2. Run test, confirm fail.
3. Implement controller `pending` with `lockForUpdate` claim; add route (`auth:sanctum`).
4. Add tests: claimable filter respects active lease (claimed row not re-returned); payload shape includes resolved media+caption from sibling.
5. Run tests, confirm pass.
6. Commit: "feat(postiz): pending endpoint with atomic claim + lease".

**Verification:**
- [ ] Claimed job not returned to a second `pending` call (lease active).
- [ ] Lease window = `postiz_lease_minutes` setting.
- [ ] Payload carries real media_urls + caption from the sibling row (no placeholder).
- [ ] Route requires `auth:sanctum` (401 without token).
- [ ] Security: token-auth enforced, no mass-assignment, worker param validated.
- [ ] No placeholder/TODO comments in new code.

---

### Phase D: `POST /automation/postiz/{job}/result` — callback

**Estimated time:** 10 minutes

**Files:**
- Modify: `backend/app/Http/Controllers/Api/Automation/PostizPublishController.php` (`result` action)
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/PostizResultCallbackTest.php`

**Behavior:** `POST /automation/postiz/{job}/result` body `{status: accepted|published|failed, postiz_post_id?, permalink?, error?}`:
- `accepted` → set `status=accepted`, store `postiz_post_id`, **extend lease** (Temporal publish + IG child-poll can take minutes). **Watchdog now permanently hands off this job** (no Publer fallback past `accepted`, even if it later ERRORs — re-publishing could double-post). Poller will follow up with `published`/`failed`.
- `published` → set `status=published`, `permalink` (Postiz `releaseURL`), clear lease. Mirror onto the sibling row's existing published columns (status + permalink/publer-equivalent field) so Social Studio reflects it — reuse the sibling's existing "published" setter path.
- `failed` → set `status=failed`, `last_error`. If job had NOT reached `accepted` (enqueue itself failed) → lease cleared → eligible for Publer fallback (Publer-capable platforms). If already `accepted` (Temporal ERROR) → mark failed + Telegram alert, NO auto-Publer (operator handles tail — avoids double-publish).
- Idempotent: already-`published` job → 200 no-op; `accepted` after `published` ignored.

**Steps:**
1. Write failing test: `published` callback flips job + mirrors sibling status. Expected error: `405`/route-not-found.
2. Run test, confirm fail.
3. Implement `result`; add route.
4. Add tests: `failed` sets last_error + clears lease; double `published` callback is no-op 200.
5. Run tests, confirm pass.
6. Commit: "feat(postiz): result callback flips job + mirrors sibling status".

**Verification:**
- [ ] `accepted` → status=accepted, `postiz_post_id` stored, lease extended, watchdog hand-off flag effective.
- [ ] `published` → job + sibling reflect published + `releaseURL` permalink stored.
- [ ] `failed` pre-accepted → last_error + lease cleared (fallback-eligible); `failed` post-accepted → no Publer dispatch, Telegram alert.
- [ ] Idempotent double-callback (no double mirror; accepted-after-published ignored).
- [ ] Security: token-auth, job-id bound to model (no IDOR beyond authed automation scope), body validated.
- [ ] No placeholder/TODO comments in new code.

---

### Phase E: `POST /automation/postiz/channels/sync` — mapping upsert

**Estimated time:** 8 minutes

**Files:**
- Modify: `PostizPublishController.php` (`channelsSync` action)
- Modify: `backend/routes/api.php`
- Test: `backend/tests/Feature/PostizChannelsSyncTest.php`

**Behavior:** `POST /automation/postiz/channels/sync` body `{channels:[{platform, handle, integration_id, enabled}]}` → upsert `postiz_channels` on `(platform, handle)`, set `last_synced_at`. Channels absent from payload but present in DB → mark `enabled=false` (disappeared from Postiz), don't delete (audit).

**Steps:**
1. Write failing test: sync inserts new mapping + flips missing one to disabled. Expected error: route-not-found.
2. Run test, confirm fail.
3. Implement `channelsSync`; add route.
4. Run tests, confirm pass.
5. Commit: "feat(postiz): channels sync endpoint (auto-mapping)".

**Verification:**
- [ ] New channel upserted; existing updated; missing → `enabled=false` not deleted.
- [ ] Security: token-auth, payload validated.
- [ ] No placeholder/TODO comments in new code.

---

### Phase F: Claim-aware deadline watchdog → Publer fallback

**Estimated time:** 12 minutes

**Files:**
- Create: `backend/app/Console/Commands/ReapUnclaimedPostizJobs.php` (`postiz:reap-unclaimed`)
- Modify: `backend/database/seeders/ScheduledCommandSeeder.php` (register cron, every minute)
- Test: `backend/tests/Feature/PostizWatchdogFallbackTest.php`

**Behavior:** every minute — find jobs that **never reached Postiz** (`postiz_post_id IS NULL` — the async hand-off guard) where `status` IN (`ready_to_publish` unclaimed, OR `claimed` with `publish_lease_until < now()`, OR `failed` pre-accepted) AND `slot_due_at <= now()-postiz_fallback_deadline_minutes` (setting, default 6) AND `fallback_fired_at` IS NULL:
- If platform is **Publer-capable** (IG-image/LinkedIn/TikTok/FB/Threads — NOT IG video carousel) → `PublishViaPubler::dispatch($platform, $sibling_post_id)`, set `fallback_fired_at`, status→`failed` with reason `postiz_offline_publer_fallback`.
- If **IG video carousel** (only Postiz can do) → do NOT fallback; if waiting > alert threshold, dispatch existing Telegram alert; leave for the local node to pick up when back online.

> **Hand-off guard = `postiz_post_id IS NOT NULL`.** Once the poller got an OK from `POST /public/v1/posts`, Postiz/Temporal owns the publish; the watchdog must NEVER fire Publer for it (even on a later Temporal ERROR) — re-publishing risks a double post. Such tail failures route to Telegram + manual handling.

**Determining "IG video carousel":** inspect the sibling's media (all-video + carousel) — reuse the same predicate Publer-impossibility relied on (`PublerPayloadBuilder` mixed/all-video detection).

**Steps:**
1. Write failing test: unclaimed past-deadline IG-image job → Publer dispatched + fallback_fired_at set. Expected error: command class not found.
2. Run test, confirm fail.
3. Implement command + register in seeder (`firstOrCreate`, category `instagram`/`system`, `* * * * *`).
4. Add tests: active-lease job NOT faulted over (no fallback while leased); IG-video-carousel job → no Publer dispatch, Telegram alert path; already-`fallback_fired_at` → skipped (no double).
5. Run tests, confirm pass.
6. Commit: "feat(postiz): claim-aware watchdog fires Publer fallback when local offline".

**Verification:**
- [ ] Past-deadline unclaimed (or lease-expired) Publer-capable job with `postiz_post_id IS NULL` → Publer dispatched once.
- [ ] Job with `postiz_post_id` set (reached `accepted`) → NEVER Publer-fallback, even if later `failed` (anti-double-publish).
- [ ] Active-lease job → NOT faulted (no double-publish).
- [ ] IG video carousel → no Publer fallback (waits + Telegram alert).
- [ ] `fallback_fired_at` guards against double dispatch.
- [ ] Seeder idempotent (re-seed = 0 new rows).
- [ ] No placeholder/TODO comments in new code.

---

### Phase G: `postiz` settings group + seeder

**Estimated time:** 8 minutes

**Files:**
- Create: `backend/database/seeders/PostizSettingsSeeder.php`
- Modify: `backend/database/seeders/DatabaseSeeder.php` (wire in)
- Test: `backend/tests/Feature/PostizSettingsSeederTest.php`

**Settings (group `postiz`):** `postiz_enabled` (`'false'`), `postiz_lease_minutes` (`'10'`), `postiz_fallback_deadline_minutes` (`'6'`), `postiz_api_base_url` (null — set per deploy), `postiz_worker_alert_minutes` (`'20'` — IG-video stuck alert).

**Steps:**
1. Write failing test: seeder creates 5 keys idempotently. Expected error: seeder class not found.
2. Run test, confirm fail.
3. Implement seeder (`firstOrCreate`), wire into DatabaseSeeder.
4. Run tests, confirm pass.
5. Commit: "feat(postiz): postiz settings group seeder (default OFF)".

**Verification:**
- [ ] 5 keys seeded; re-seed idempotent; `postiz_enabled` defaults `'false'` (zero prod impact until flipped).
- [ ] No placeholder/TODO comments in new code.

---

### Phase H: Local infra — Postiz Docker Compose + Cloudflare named tunnel (RUNBOOK)

**Estimated time:** operator step (~60–90 min real setup)

**Files:**
- Create: `docs/runbooks/postiz-local-node-deploy.md`
- Create: `infra/postiz-local/docker-compose.yml` (Postgres + Redis + Temporal + Postiz + poller), `infra/postiz-local/.env.example`, `infra/postiz-local/cloudflared-config.example.yml`

**Content (runbook):**
- Machine sizing (8GB min/16GB; mini-PC N100 for 24/7). Real stack = postiz + 2× Postgres (app PG17 + Temporal PG16) + Redis + Temporal + Elasticsearch (ES7) + temporal-ui. **Drop `spotlight` (Sentry)** from compose (optional). Optional slim: `ENABLE_ES=false` + remove ES service.
- **Raise `API_LIMIT`** in `.env` (docker default 30/hr too low — a 10-video carousel ≈ 11 calls; set e.g. `API_LIMIT=300`).
- Docker Compose bring-up; Cloudflare **named** tunnel → `postiz.alisadikinma.com` (stable URL — quick-tunnel's random URL breaks redirect URIs).
- **Per-platform channel connect** in Postiz UI (each needs its own OAuth dev-app keys + the tunnel redirect URI registered):
  - **Instagram** — choose `instagram` (needs FB Business + linked FB Page) OR `instagram-standalone` (professional account, no FB Page). Pick based on the account setup.
  - **LinkedIn** — `linkedin` (personal profile) and/or `linkedin-page` (company page).
  - **TikTok**, **Threads**, **Facebook** (Page).
  - **Medium** — `medium`. ⚠️ Medium largely stopped issuing new integration tokens (~2023); verify the account can still mint one before relying on the blog→Medium path (Phase K).
- Generate Postiz **API key** (Settings → Developers) for the poller. Capture nothing manually — integration IDs auto-sync via Phase I.
- Verify token refresh runs (outbound via Temporal). No automated tests — verification is manual checklist.

**Verification:**
- [ ] Postiz stack healthy (`docker compose ps` all up; Temporal UI reachable).
- [ ] Tunnel resolves `https://postiz.alisadikinma.com` (named, stable).
- [ ] At least one channel connected; token refresh confirmed.
- [ ] Runbook documents rollback (flip `postiz_enabled=false` → instant Publer revert).

---

### Phase I: Local Node poller service

**Estimated time:** 15 minutes

**Files:**
- Create: `infra/postiz-local/poller/index.mjs`, `poller/postiz-client.mjs`, `poller/vps-client.mjs`, `poller/package.json`
- Test: `infra/postiz-local/poller/poller.test.mjs` (`node --test`, mocked fetch)

**Behavior:**
- **claim-poll** (`node-cron` ~every 2 min): `GET {VPS}/automation/postiz/pending` → for each claimed job:
  1. fetch each media URL (HTTPS) → `POST {POSTIZ}/public/v1/upload` (multipart `file`) → collect `{id, path}`.
  2. `POST {POSTIZ}/public/v1/posts` with the **real envelope** (all required fields): `{type:'now', date:<now ISO>, shortLink:false, tags:[], posts:[{integration:{id}, value:[{content, image:[{id,path}]}], settings:{post_type:'post'}}]}`. (Backend overwrites `settings.__type` from the integration; for Medium include `settings.canonical`.) Capture the returned Postiz `Post.id`.
  3. On OK → `POST {VPS}/.../result {accepted, postiz_post_id}` immediately (hands off the watchdog). On enqueue error → `{failed, error}`.
- **confirm-poll** (`node-cron` ~every 1 min): for jobs in local `accepted` set, `GET {POSTIZ}/public/v1/posts?startDate&endDate` (window around now), match by `postiz_post_id`, read `state` → `PUBLISHED` ⇒ `POST {VPS}/.../result {published, permalink: releaseURL}`; `ERROR` ⇒ `{failed, error}`. (Webhook is an optional future optimization — GET-poll covers both success AND error, which the success-only webhook can't.)
- **integration-sync** (startup + ~hourly): `GET {POSTIZ}/public/v1/integrations` → map `{identifier→platform, profile→handle, id→integration_id}` → `POST {VPS}/automation/postiz/channels/sync`.
- **prefetch** (optional, from `upcoming[]`): warm media cache to local tmp.
- Auth: VPS automation bearer token + Postiz API key (`Authorization` header), from env. Mind `API_LIMIT` (batch-friendly; back off on 429).

**Steps:**
1. Write failing test: given a mocked `pending` payload, poller uploads media + posts and callbacks `accepted` with `postiz_post_id`. Expected error: module not found.
2. Run test (`node --test`), confirm fail.
3. Implement `vps-client`, `postiz-client`, `index` orchestration (claim-poll + confirm-poll).
4. Add tests: confirm-poll matches `postiz_post_id` in `GET /posts`, `state=PUBLISHED` → `published`+releaseURL; `state=ERROR` → `failed`; enqueue error → `failed`; integration-sync posts mapping; already-claimed dedupe (job_id processed once per tick).
5. Run tests, confirm pass.
6. Commit: "feat(postiz): local Node poller — claim, upload, enqueue, confirm, callback".

**Verification:**
- [ ] `node --test` passes (mocked fetch).
- [ ] Envelope includes ALL required fields (`type/date/shortLink/tags`) + `settings.post_type`; Medium job includes `settings.canonical`.
- [ ] `POST /posts` OK → `result {accepted, postiz_post_id}` (watchdog hand-off).
- [ ] confirm-poll: `PUBLISHED` → `{published, permalink=releaseURL}`; `ERROR` → `{failed, error}`.
- [ ] integration-sync posts real Postiz integration list (no hardcoded IDs).
- [ ] Backs off on 429 (API_LIMIT aware).
- [ ] No secrets committed (env-only); `.env.example` only.

---

### Phase J: Phased LinkedIn cutover (RUNBOOK)

**Estimated time:** operator step (staged over days)

**Files:**
- Modify: `docs/runbooks/postiz-local-node-deploy.md` (cutover section)

**Content:** keep VPS LinkedIn direct-OAuth path live. Connect LinkedIn in Postiz. Flip `postiz_enabled=true` for IG first (proves the loop on the platform Publer can't do). Once stable, route LinkedIn cross-post siblings through Postiz too, run parallel for N posts, compare permalinks/quality, then retire the VPS LinkedIn direct-OAuth publish only after Postiz proven. Rollback at any point = `postiz_enabled=false`.

**Verification:**
- [ ] IG video carousel published end-to-end via Postiz (real permalink).
- [ ] Parallel-run LinkedIn comparison documented before retiring direct-OAuth.
- [ ] Rollback lever (`postiz_enabled=false`) verified to revert to Publer within one slot tick.

---

### Phase K: Blog → Medium cross-post (canonical backlink)

**Estimated time:** 12 minutes

**Files:**
- Modify: `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php` (at `approveAndPublish`, after the existing `linkedin:scan-blog --post-id` queue)
- Modify: `backend/app/Services/PostizPublishDispatcher.php` (add `dispatchBlogToMedium(Post $post)`)
- Create: `backend/database/seeders/PostizSettingsSeeder.php` add key `postiz_medium_enabled` (`'false'`)
- Test: `backend/tests/Feature/PostizMediumCrossPostTest.php`

**Behavior:** when a blog Post publishes AND `postiz_enabled` AND `postiz_medium_enabled` AND a `medium` channel is mapped → create a `postiz_publish_jobs` row with `platform='medium'`, `sibling_type=Post::class`, `sibling_post_id=$post->id`, `slot_due_at=now()` (publish-now, not slot-scheduled). Medium has **no Publer fallback** (Publer can't post Medium articles) → watchdog treats `medium` like IG-video-carousel (wait + Telegram alert, never fallback).

**Poller (Phase I) medium branch — DUAL SEO (canonical + in-body backlink):** for `platform='medium'`, the `pending` payload carries the article with BOTH SEO layers:
- `settings.canonical = https://alisadikinma.com/blog/{slug}` — the `rel=canonical` signal (consolidates ranking to the original, anti duplicate-content; confirmed `medium.provider.ts:120`). **Strongest SEO lever.**
- `value[].content` = post HTML (primary translation body) **+ an appended attribution footer containing a real clickable backlink**: e.g. `<hr><p><em>Originally published at <a href="https://alisadikinma.com/blog/{slug}">alisadikinma.com</a></em></p>`. This is the explicit reader-visible backlink + referral driver. (Honest caveat: Medium typically `rel="nofollow"`s outbound links → limited classic link-equity, but drives referral traffic + brand and pairs correctly with the canonical.)
- The footer is **built server-side on the VPS** (in the `pending` payload for medium jobs — `value[].content` already includes it) so the URL is authoritative, never hardcoded in the poller.
- Also pass `settings.title`, optional cover image. The poller posts the same way; confirm-poll reads `state`/`releaseURL` as usual.

**Steps:**
1. Write failing test: published blog Post + flags on + medium mapped → creates a `medium` postiz job with canonical resolved. Expected error: method `dispatchBlogToMedium` not found.
2. Run test, confirm fail.
3. Implement `dispatchBlogToMedium`; wire into `approveAndPublish` (non-fatal try/catch, mirrors the existing `linkedin:scan-blog` queue pattern).
4. Add tests: flags off → no medium job; medium unmapped → skip + warning; watchdog never Publer-fallbacks a `medium` job.
5. Run tests, confirm pass.
6. Commit: "feat(postiz): blog→Medium cross-post with canonical backlink".

**Verification:**
- [ ] Published blog + flags on + medium mapped → one `medium` job, `slot_due_at=now`, canonical URL = blog permalink.
- [ ] `postiz_medium_enabled=false` OR medium unmapped → no job (no silent error).
- [ ] Watchdog never fires Publer for `platform='medium'`.
- [ ] `pending` payload for medium carries article content + `settings.canonical` (real blog URL, not placeholder).
- [ ] Article `content` ends with the attribution footer containing a clickable `<a href>` backlink to the blog permalink (real URL, server-built).
- [ ] No placeholder/TODO comments in new code.

### Phase dependency / parallelism

- **Sequential core:** A → B → (C, D, E parallelizable after A) → F → G.
- **Independent track:** H (infra) and I (poller) can proceed in parallel with the backend track; I integration-tests against C/D/E once those land.
- **Medium:** K depends on B + G (settings) + I (poller medium branch); independent of the carousel slot path.
- **Last:** J (cutover) after all green + Phase H done.
- Candidates for `gaspol-parallel` (mode: plan-phases): **{C, D, E}** (same controller, separate actions/tests — coordinate to avoid route-file merge conflict) and **{H, I}** vs backend track.

### Red-flag self-check

- ✅ Data Integration Map present; every data source named.
- ✅ Every phase has TDD step 1 (failing test + expected error) + Verification block.
- ✅ Security lines on endpoint phases (C/D/E) + watchdog (F).
- ✅ No placeholder language; Publer fallback is a real reused job, not a stub.
- ✅ References real files from CLAUDE.md + code read.
- ✅ Default-OFF (`postiz_enabled='false'`) → zero prod impact until operator flips.
