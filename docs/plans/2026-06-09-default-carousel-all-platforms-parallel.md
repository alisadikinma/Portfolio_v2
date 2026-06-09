# Default Carousel for All Platforms + Parallel Fan-out

**Date:** 2026-06-09
**Status:** Design approved → planning
**Author:** Ali Sadikin (operator) + Claude
**Type:** Backend pipeline behavior change (LinkedIn + Instagram + TikTok + Threads cross-post)

---

## Problem

Today the social cross-post pipeline is **format-gated and strictly sequential**:

1. `/linkedin-gen` plugin decides `format` (text vs carousel). The `LinkedInFormatMixGovernor` tries to bias toward carousel but re-dispatches with `format_preference=carousel` — which the current plugin **ignores**, so most posts stay `text`.
2. Instagram + TikTok siblings are **only created when LinkedIn format = carousel** ([ScanLinkedInForCrossPost.php:136-154](../../backend/app/Console/Commands/ScanLinkedInForCrossPost.php)). Text → only Facebook + Threads-text.
3. Fan-out is gated on LinkedIn reaching `awaiting_publish` / `published` **and** all carousel slides `image_status='done'`. So nothing runs in parallel — LinkedIn must fully finish first.
4. IG / TikTok / Threads-carousel **reuse LinkedIn's rendered slide PNGs** — there is no separate image renderer for them (per ADR `adr-2026-04-28-carousel-engine-publisher-separation` — universal carousel engine).

Net effect: operator expects 4-platform carousel content per blog, but gets mostly text LinkedIn posts and no IG/TikTok at all.

## Goal

By default, **every** blog post produces a **carousel** for **all 4 platforms** (LinkedIn, Instagram, TikTok, Threads), and the 4 platform generations are **triggered + executed together** (truly parallel), as soon as the shared carousel slides finish rendering.

## Locked Decisions (from brainstorm 2026-06-09)

| # | Decision | Choice |
|---|---|---|
| 1 | Force carousel | **Always carousel.** Backend always routes to `/carousel-gen`, bypassing the plugin's text/carousel decision + the unreliable governor. `/carousel-gen` failure → `manual_review` (never silent text downgrade). Backend-only, no plugin redeploy. |
| 2 | Image source / aspect | **Shared slides via GeminiGen at 4:5** for LinkedIn + IG + TikTok + Threads. This is ALREADY the current behavior — IG/TikTok/Threads reuse LinkedIn's 4:5 slides. **No rendering change needed** (4:5 is LinkedIn + IG native portrait max). No per-platform renderers. |
| 3 | "Triggered together" point | **Fan out the moment the last carousel slide renders** (`image_status='done'` on all slides) — drop the `awaiting_publish` gate. Event-driven dispatch from the carousel webhook + every-2-min `social-cross-post:scan` as safety reaper with widened gate. |
| 4 | Publish scope | **Generation together only.** Publishing stays exactly as-is: `social:publish-slot` atomic + slot/approval. No auto-publish. |
| 5 | Execution parallelism | **True parallel.** Cross-post caption-gen jobs go on a dedicated queue with a multi-worker pool (systemd) so the 4 SSH→claude caption-gens run concurrently, not serially on the single `default` worker. |

## Architecture

### A. Force carousel
- New `settings` row `linkedin_force_carousel` (group `linkedin`, default `'true'`), seeded idempotently in `LinkedInSettingsSeeder`.
- [LinkedInGenerationService](../../backend/app/Services/LinkedInGenerationService.php): when `linkedin_force_carousel` is on, **always** take the `/carousel-gen` route (`applyCarouselGenAdapter`) regardless of `detectedFormat` / `route_to_carousel_gen`. The `/linkedin-gen` call still runs to author the brief/caption; format is forced.
- Governor (`LinkedInFormatMixGovernor`) short-circuits to "carousel" when force flag on (becomes a no-op; left in place for when flag is off).
- Carousel-gen failure → existing `markFailed` path → FSM `failed`/`manual_review`. No text fallback.

### B. Shared 4:5 slides (NO CHANGE)
- All platforms already render/reuse 4:5: [LinkedInCarouselImageService](../../backend/app/Services/LinkedInCarouselImageService.php) dispatches GeminiGen at 4:5, and IG/TikTok/Threads reuse `linkedin_posts.carousel_slides[].image_url`. 4:5 is LinkedIn + IG native portrait max (1080×1350).
- **No code change for aspect ratio** — requirement already satisfied by current shared-slide architecture. (No new `config/social.php`, no per-platform renderers.)

### C. Early parallel fan-out
- [LinkedInCarouselImageService::handleWebhook](../../backend/app/Services/LinkedInCarouselImageService.php): after mirroring a slide to `done`, if **all** slides are now `done`, dispatch a **targeted** `social-cross-post:scan --draft-id={id}` (bypasses window + virality + status gate — the targeted path already exists) so fan-out fires immediately, event-driven.
- [ScanLinkedInForCrossPost](../../backend/app/Console/Commands/ScanLinkedInForCrossPost.php): widen the non-targeted gate from `status ∈ {awaiting_publish, published}` to "carousel format + all slides `done` + any non-terminal status" so the every-2-min reaper also catches drafts that completed slides while still in `validating` / `manual_review`. Keep idempotency (skip if siblings already exist).
- Forcing carousel (A) means IG + TikTok + Threads-carousel + FB-carousel are always created.

### D. True parallel execution
- New dedicated queue `social-crosspost`. The 4 jobs (`GenerateInstagramPost`, `GenerateTiktokPost`, `GenerateThreadsPost`, `GenerateFacebookPost`) dispatched `->onQueue('social-crosspost')`.
- New systemd template unit `scripts/systemd/portfolio-crosspost@.service` listening on `social-crosspost` with `Restart=always`, `--max-time=3600`. Operator enables N instances (recommended 4 = one per platform): `systemctl enable --now portfolio-crosspost@{1..4}`.
- Main `portfolio-queue.service` keeps owning the `default` queue (LinkedIn gen, carousel images, etc.) so cross-post load doesn't starve the primary pipeline.
- Concurrency cap = number of enabled instances (4). Each is an SSH→claude call with `--mcp-config empty-mcp.json` (leak-safe per April 29 fix).
- Operator runbook: install + verify steps (deploy.sh does NOT manage systemd).

## Data Integration Map

| Component | Data Source | Existing? | Change |
|---|---|---|---|
| Force carousel flag | `settings` group `linkedin` | new row | `linkedin_force_carousel` default `true` |
| Carousel route force | `LinkedInGenerationService::persistAndRoute` / route logic | yes | always `/carousel-gen` when flag on |
| Aspect ratio | `linkedin_posts.carousel_slides[].image_url` (4:5) | yes | **no change** — already 4:5, shared across all platforms |
| Slide reuse (IG/TT/TH) | `linkedin_posts.carousel_slides[].image_url` | yes | unchanged |
| Early fan-out | `LinkedInCarouselImageService::handleWebhook` | yes | dispatch targeted scan on all-slides-done |
| Widened reaper gate | `ScanLinkedInForCrossPost` | yes | gate on slides-done not awaiting_publish |
| Dedicated queue | `Generate{IG,TT,TH,FB}Post::dispatch` | yes | `->onQueue('social-crosspost')` |
| Worker pool | `scripts/systemd/portfolio-crosspost@.service` (new) | no | template unit + runbook |
| Scheduler row | `ScheduledCommandSeeder` | static-only | add real DB row for `social-cross-post:scan` |
| Publish | `social:publish-slot` + `PublishViaPubler` | yes | unchanged |

## Out of Scope / Non-goals
- No per-platform native image renderers (TikTok stays on shared 3:4, not 9:16).
- No auto-publish — publishing flow untouched.
- No plugin (`linkedin-post-writer` / `social-short-form-writer`) code changes — all backend.
- No change to virality gate logic (still respected on the non-targeted reaper path).

## Risks
1. **Concurrent claude CLI memory** — 4 parallel SSH→claude processes. Mitigated by empty-mcp config + `--max-time` recycle; verify VPS RAM headroom in runbook.
2. **Carousel-gen failure rate** — forcing carousel removes the text fallback; more drafts may sit in `manual_review` if `/carousel-gen` Sonnet truncates (known May-2 issue). Acceptable per Decision 1; operator retries.
3. **In-flight text drafts** (#141/#142) — produced under old behavior; will need regenerate after deploy to become carousel.

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** Real integrations only. Never substitute placeholders for real
> data sources without explicit user approval. If a data source doesn't exist, STOP and ask.

### Goal
Make every blog post produce a carousel for all 4 platforms (LinkedIn/Instagram/TikTok/Threads), rendered once as shared 3:4 GeminiGen slides, with all 4 platform caption-gens fanned out and executed in parallel the moment slides finish — publishing untouched.

### Architecture Context (from CLAUDE.md + code map)
- `LinkedInGenerationService` owns format routing; post-v0.5.0 carousel = always `/carousel-gen` via `applyCarouselGenAdapter`; `route_to_carousel_gen` short-circuit.
- `LinkedInFormatMixGovernor` biases carousel via `linkedin_format_*` settings (plugin ignores `format_preference` → unreliable).
- `LinkedInCarouselImageService` dispatches GeminiGen per slide (currently 4:5) + `handleWebhook` mirrors `image_status` onto `linkedin_posts.carousel_slides[]`.
- `ScanLinkedInForCrossPost` (`social-cross-post:scan`) fans out IG/TT/Threads/FB siblings; gate = LinkedIn `{awaiting_publish, published}` + slides `done` + virality. Targeted `--draft-id` bypasses gates. Static fallback in `routes/console.php` every 2 min (NO DB scheduler row).
- IG/TT/Threads reuse `linkedin_posts.carousel_slides[].image_url` — no own renderers.
- `social:publish-slot` + `PublishViaPubler` = publish (unchanged).
- Settings via `Setting::get/set`; seeders idempotent `firstOrCreate`. FSM via `HasStatusTransitions`.

### Tech Stack
Laravel 12, PHP 8.2, MySQL 8, queue driver `database`, systemd worker. **No PHP on dev Mac** — unit tests (no DB) verifiable on VPS via `php artisan test --filter`; feature tests (RefreshDatabase) run on CI. Syntax via `php -l`.

### Data Integration Map

| Feature | Data Source | API/Service | Exists? | Action |
|---|---|---|---|---|
| Force-carousel flag | `settings{group=linkedin,key=linkedin_force_carousel}` | `Setting::get` | No | Seed default `'true'` in `LinkedInSettingsSeeder` |
| Carousel route force | `LinkedInGenerationService` | self | Yes | Always `/carousel-gen` when flag on |
| Aspect ratio (4:5) | `LinkedInCarouselImageService` dispatch | GeminiGen `/generate_image` | Yes | **no change** — already 4:5, IG/TT/Threads reuse |
| Early fan-out | `LinkedInCarouselImageService::handleWebhook` | `Artisan::queue('social-cross-post:scan',['--draft-id'=>id])` | Yes (svc) | Dispatch on all-slides-done |
| Widened gate | `ScanLinkedInForCrossPost` | self | Yes | Gate on slides-done + non-terminal status |
| Dedicated queue | `Generate{IG,TT,TH,FB}Post::dispatch` | `->onQueue('social-crosspost')` | Yes (jobs) | Route to new queue |
| Worker pool | `scripts/systemd/portfolio-crosspost@.service` | systemd | No | New template unit + runbook |
| Scheduler row | `ScheduledCommandSeeder` | DB `scheduled_commands` | Partial | Add real row; drop static fallback |

---

### Phase A: Force-carousel setting (foundation)
**Estimated time:** 8 min
**Files:**
- Modify: `backend/database/seeders/LinkedInSettingsSeeder.php`
- Test: `backend/tests/Feature/LinkedInForceCarouselSettingTest.php`

**Steps:**
1. Write failing test: after seeding, `Setting::get('linkedin_force_carousel')` === `'true'`. Expected error: assertion fails — key absent / null.
2. Run `php artisan test --filter=LinkedInForceCarouselSettingTest` (CI; VPS scratch if available), confirm fail.
3. Add `linkedin_force_carousel` (group `linkedin`, default `'true'`) to `LinkedInSettingsSeeder` via `firstOrCreate`.
4. Run test, confirm pass. Commit: `feat(linkedin): add linkedin_force_carousel setting (default true)`.

**Verification:**
- [ ] `Setting::get('linkedin_force_carousel')` = `'true'` after seed
- [ ] `LinkedInSettingsSeeder` idempotent (re-seed = 0 new rows)
- [ ] `php -l` clean; no TODO/placeholder
- [ ] Aspect ratio: NO work — 4:5 already universal via shared slides (see Decision 2)

### Phase B: Force carousel route in LinkedInGenerationService
**Estimated time:** 15 min
**Files:**
- Modify: `backend/app/Services/LinkedInGenerationService.php`
- Test: `backend/tests/Feature/LinkedInGenerationServiceForceCarouselTest.php`

**Steps:**
1. Write failing test: with `linkedin_force_carousel='true'` and a plugin envelope where `format='text'`, assert the service takes the `/carousel-gen` route (`applyCarouselGenAdapter` invoked) NOT text persistence. Expected error: assertion fails — text path taken.
2. Run on CI/VPS, confirm fail.
3. In the route decision, add: when `Setting::get('linkedin_force_carousel','true')` is truthy → set `$isCarouselRoute = true` / force `$detectedFormat='carousel'` before the existing carousel branch. Governor call short-circuits (skip when force on).
4. Add test: flag off → existing plugin-decided behavior preserved (regression).
5. Run tests, confirm pass. Commit: `feat(linkedin): force carousel route when linkedin_force_carousel enabled`.

**Verification:**
- [ ] Force on + text envelope → `/carousel-gen` dispatched
- [ ] Force off → existing behavior unchanged (regression test green)
- [ ] Carousel-gen failure still → `markFailed` (no text fallback)
- [ ] Existing `LinkedInGenerationService*Test` suite green; `php -l` clean

### Phase B2: ~~3:4 aspect~~ — DROPPED
Aspect ratio stays 4:5 (already universal via shared slides). No code change. See Decision 2.

### Phase C: Early parallel fan-out on all-slides-done
**Estimated time:** 15 min
**Files:**
- Modify: `backend/app/Services/LinkedInCarouselImageService.php` (`handleWebhook`)
- Test: `backend/tests/Feature/CarouselWebhookFanoutTest.php`

**Steps:**
1. Write failing test: webhook marking the LAST slide `done` triggers `Artisan::queue('social-cross-post:scan', ['--draft-id'=>$id])` (fake/spy the queue). Partial completion → NOT triggered. Expected error: no dispatch asserted.
2. Run, confirm fail.
3. In `handleWebhook` after the `DB::transaction` slide mirror, compute "all slides done"; if newly-all-done AND format=carousel, enqueue the targeted scan. Idempotency guard: only when no live sibling rows exist yet (or rely on scan's own idempotency).
4. Run tests, confirm pass. Commit: `feat(carousel): fan out cross-post the moment all slides render`.

**Verification:**
- [ ] All-slides-done → targeted `social-cross-post:scan --draft-id` enqueued once
- [ ] Partial slide completion → no dispatch
- [ ] Idempotent (re-fired webhook doesn't double-dispatch)
- [ ] Existing webhook tests green; `php -l` clean

### Phase D: Widen reaper gate in ScanLinkedInForCrossPost
**Estimated time:** 12 min
**Files:**
- Modify: `backend/app/Console/Commands/ScanLinkedInForCrossPost.php`
- Test: `backend/tests/Feature/CrossPostScanWidenedGateTest.php`

**Steps:**
1. Write failing test: a carousel LinkedIn draft in `validating` (NOT awaiting_publish) with all slides `done` IS picked up by the non-targeted scan and fanned out. Expected error: skipped (status gate excludes validating).
2. Run, confirm fail.
3. Replace the `whereIn('status',{awaiting_publish,published})` non-targeted gate with: carousel format + all slides `done` + status NOT in terminal set `{cancelled, failed}` (still skip already-fanned-out via existing idempotency). Keep virality gate + targeted bypass intact.
4. Add regression: terminal/cancelled drafts still skipped; idempotency holds.
5. Run tests, confirm pass. Commit: `feat(crosspost): gate fan-out on slides-done, not awaiting_publish`.

**Verification:**
- [ ] `validating`/`manual_review` + slides done → fans out
- [ ] `cancelled`/`failed` → skipped
- [ ] Virality gate + idempotency preserved; targeted `--draft-id` unchanged
- [ ] `php -l` clean

### Phase E: Dedicated queue + parallel worker pool
**Estimated time:** 15 min
**Files:**
- Modify: `backend/app/Console/Commands/ScanLinkedInForCrossPost.php` (dispatch `->onQueue`)
- Create: `scripts/systemd/portfolio-crosspost@.service`
- Modify: `scripts/systemd/README.md`
- Test: `backend/tests/Feature/CrossPostQueueRoutingTest.php`

**Steps:**
1. Write failing test using `Queue::fake()`: fan-out dispatches `GenerateInstagramPost`/`GenerateTiktokPost`/`GenerateThreadsPost`/`GenerateFacebookPost` `onQueue('social-crosspost')`. Expected error: asserted on `default`.
2. Run, confirm fail.
3. Add `->onQueue('social-crosspost')` to the 4 dispatch calls in the create methods.
4. Create systemd template `portfolio-crosspost@.service` (`queue:work --queue=social-crosspost --sleep=3 --tries=3 --max-time=3600 --backoff=60,300,900`, `User=claudesn`, `Restart=always`, `--mcp-config` not needed here — env carries it). Document `systemctl enable --now portfolio-crosspost@{1..4}` + verify steps in README.
5. Run test, confirm pass. Commit: `feat(crosspost): dedicated social-crosspost queue + parallel worker pool`.

**Verification:**
- [ ] 4 jobs dispatched on `social-crosspost` queue
- [ ] Template unit valid (`systemd-analyze verify` note in runbook), 4 instances documented
- [ ] Main `portfolio-queue.service` still owns `default` (no starvation)
- [ ] `php -l` clean

### Phase F: Register social-cross-post:scan in DB scheduler
**Estimated time:** 10 min
**Files:**
- Modify: `backend/database/seeders/ScheduledCommandSeeder.php`
- Modify: `backend/routes/console.php` (drop static fallback)
- Test: `backend/tests/Feature/ScheduledCommandSeederCrossPostTest.php`

**Steps:**
1. Write failing test: after seeding, a `scheduled_commands` row exists for `social-cross-post:scan` (category `linkedin`/`social`, cron `*/2 * * * *`, enabled). Expected error: row absent.
2. Run, confirm fail.
3. Add idempotent `firstOrCreate` row in `ScheduledCommandSeeder`. Remove the static `Schedule::command('social-cross-post:scan')` block from `routes/console.php` (now DB-driven, matching May-9 convention).
4. Run test, confirm pass. Commit: `feat(scheduler): register social-cross-post:scan as DB-driven schedule`.

**Verification:**
- [ ] Seeder idempotent; row appears in `/admin/settings?tab=scheduler`
- [ ] No double-run (static fallback removed; `withoutOverlapping` anyway)
- [ ] `php -l` clean

### Phase G: Docs sync (CLAUDE.md) + operator runbook
**Estimated time:** 12 min
**Files:**
- Modify: `CLAUDE.md` (cross-post section, scheduler inventory, new setting/queue, force-carousel behavior)
- Create: `docs/runbooks/crosspost-parallel-carousel-deploy.md`

**Steps:**
1. Update CLAUDE.md: force-carousel default, early fan-out trigger, widened gate, `social-crosspost` queue + worker pool, scheduler row, Last-Updated changelog line.
2. Write runbook: deploy order (migrate/seed via deploy.sh), systemd worker-pool install + verify, regenerate in-flight text drafts (#141/#142), rollback (flip `linkedin_force_carousel='false'`).
3. Commit: `docs: sync CLAUDE.md + runbook for default-carousel parallel fan-out`.

**Verification:**
- [ ] CLAUDE.md reflects all 4 decisions + new keys
- [ ] Runbook covers systemd pool + rollback
- [ ] Via `gaspol-sync-docs` consistency check

---

### Phase dependency / parallelization
- **A** (setting) is foundation; **B** (force carousel) depends on A.
- **C** (fan-out webhook) + **D, E** (both touch `ScanLinkedInForCrossPost` — serialize) depend on the carousel path.
- **F** (scheduler row) + **G** (docs) independent.
- Given shared files (`LinkedInGenerationService`, `LinkedInCarouselImageService`, `ScanLinkedInForCrossPost`), **recommend sequential A→B→C→D→E→F→G** to avoid merge churn. Use `gaspol-execute` per-phase. (Aspect-ratio phase dropped — 4:5 already universal.)

### Post-deploy operator actions
1. `git push` → `deploy.sh` runs `migrate --force` + idempotent seeders (force-carousel setting + scheduler row).
2. Install worker pool: `systemctl enable --now portfolio-crosspost@{1..4}` (manual, per runbook).
3. Regenerate in-flight text drafts #141/#142 (or let next scan re-pick under force-carousel).
4. Verify `social-cross-post:scan` row in `/admin/settings?tab=scheduler`.
5. Confirm VPS RAM headroom for 4 concurrent claude CLI processes.
