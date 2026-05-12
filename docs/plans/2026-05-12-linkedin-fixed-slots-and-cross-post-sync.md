# LinkedIn Fixed-Slot Scheduling + Cross-Post Atomic Sync + Format-Mix Governor

**Date:** 2026-05-12
**Status:** Design approved by operator (Ali Sadikin). Implementation pending.
**Source brainstorm:** session 2026-05-12, /gaspol-brainstorm invocation.
**Maintainer:** alisadikinma

## Problem Statement

Three coupled production issues with the LinkedIn auto-publish pipeline:

1. **Schedule cadence is wrong.** Current flow: operator approves → 15 min `cancel_window` → `linkedin:process-scheduled` fires publish at `now() + 15min`. Result: posts ship at unpredictable times (e.g., 14:23, 16:47), not aligned with LinkedIn algorithm best practices or operator-preferred hours. Operator wants posts to fire ONLY at fixed slots: **5:00, 6:00, 7:00, 12:00, 17:00, 18:00, 19:00, 20:00 WIB** (8 slots/day, 1 post per slot, FIFO).

2. **Format decision biased to text.** Plugin `/linkedin-brief` (in `linkedin-post-writer`) decides format per draft based on content signals. Production observation: nearly every recent draft outputs `format=text`. Per [ScanLinkedInForCrossPost.php:136-153](../../backend/app/Console/Commands/ScanLinkedInForCrossPost.php), text format only creates Facebook + Threads siblings — Instagram + TikTok are carousel-only platforms. Result: IG/TT distribution is starved. Operator wants 80% carousel / 20% text mix to fan out cross-posts properly.

3. **Cross-post publish is stub.** Per gaspol-debug session 2026-05-12: `PublerClient.php` (550 lines) is real and successfully connects to Publer API on manual tinker calls. But `PublishViaPubler.php` (110 lines) is still a stub job:
   ```
   * Stub Publer publish dispatcher for Phase E admin Approve action.
   *   - Stub job logs the dispatch + returns (does not advance to Published)
   Log::info('[PublishViaPubler] Stub invoked — Publer integration pending Phase H+', ...)
   ```
   Verified: 0 IG, 0 TT, 0 TH, 0 FB posts published with `external_url` set. Operator wants atomic sync publish — when LinkedIn publishes at its slot, IG/TT/Threads carousel siblings publish at the **same minute tick** (not staggered, not async).

## Goals

- LinkedIn publishes ONLY at 5/6/7/12/17/18/19/20 WIB
- 1 post per slot, FIFO collision → next slot
- 80/20 carousel/text mix enforced by backend (governor over-rides plugin when ratio drifts)
- Carousel drafts: IG + TT + Threads siblings publish at SAME slot as LinkedIn
- Atomic — if any sibling not ready, all postpone to next slot (max 2 postpones, then LinkedIn solo + alert)

## Non-Goals

- Direct platform APIs (Meta Graph, TikTok Content Posting, Threads API). Stay on Publer middleware.
- Variable posts-per-slot (e.g., 2 posts at 17:00). Strict 1-per-slot.
- Auto-publish across timezones. Asia/Jakarta only.
- Backfilling historical drafts to new schedule. Existing `awaiting_publish` rows ride out their current `cancel_window_ends_at`, then new system takes over.

## Design

### Component map

```
                    ┌─────────────────────────────────────┐
                    │  ScanLinkedInForCrossPost           │
                    │  - assigns slot to LI               │
                    │  - propagates scheduled_at to       │
                    │    ALL siblings (FB/IG/TT/TH)       │
                    └─────────────────────────────────────┘
                                    │
                                    ▼
                    ┌─────────────────────────────────────┐
                    │  LinkedInGenerationService          │
                    │  + LinkedInFormatMixGovernor (NEW)  │
                    │  - 80/20 carousel ratio enforcer    │
                    │  - re-dispatches plugin with        │
                    │    format_preference if needed      │
                    └─────────────────────────────────────┘
                                    │
              ┌─────────────────────┼─────────────────────┐
              ▼                     ▼                     ▼
        LinkedIn draft        IG/TT/TH siblings      FB sibling
        scheduled_at=5:00     scheduled_at=5:00      scheduled_at=5:00
              │                     │                     │
              └─────────────────────┼─────────────────────┘
                                    │
                                    ▼
                    ┌─────────────────────────────────────┐
                    │  social:publish-slot (NEW)          │
                    │  every minute. At slot time:        │
                    │  1. Atomic readiness check          │
                    │  2. If any sibling not ready →      │
                    │     postpone ALL to next slot       │
                    │     (max 2 postpones)               │
                    │  3. Publish LinkedIn first          │
                    │  4. Parallel dispatch PublishViaPubler│
                    │     for each ready sibling          │
                    └─────────────────────────────────────┘
                                    │
                       ┌────────────┼────────────┐
                       ▼            ▼            ▼
                LinkedInPublishService    PublishViaPubler (real, NEW)
                (existing)                → PublerClient (existing real)
                                          IG / TT / TH
```

### Change 1 — `LinkedInFixedSlotScheduler` (Schedule problem)

**New service:** `app/Services/LinkedInFixedSlotScheduler.php`

- Constructor injects `SettingService` (to read `linkedin_publish_slots` JSON), default `[5, 6, 7, 12, 17, 18, 19, 20]`.
- Method `nextAvailableSlot(?Carbon $from = null): Carbon`:
  - `$from = $from ?? now('Asia/Jakarta')->addMinutes(lead_time)` (default lead = 5 min, configurable)
  - Iterate forward day-by-day (max 14 days lookahead):
    - For each hour in `slots[]`:
      - Compute candidate `Carbon` at that hour TZ Asia/Jakarta
      - Skip if candidate < $from
      - Check collision: `LinkedInPost::where('scheduled_at', '>=', candidate)->where('scheduled_at', '<', candidate->copy()->addHour())->whereIn('status', ['awaiting_publish', 'awaiting_review'])->whereNull('deleted_at')->exists()`
      - If no collision → return candidate
  - If lookahead exhausted → throw `NoAvailableSlotException` (operator alert via Telegram)
- Method `slotForHour(int $hour, Carbon $referenceDate): Carbon` — utility for unit testing.

**Wire to 3 call sites:**

1. `LinkedInDraftController::approve` — replaces `scheduled_at = now()` + `cancel_window_ends_at = now() + linkedin_cancel_window_minutes` with `scheduled_at = $scheduler->nextAvailableSlot()` AND `cancel_window_ends_at = $scheduled_at`. (Cancel window = entire duration from approve click until slot fires.)
2. `AutoScheduleManualReviewLinkedInPosts` — swap `LinkedInAutoSchedulerService::nextAvailableSlot` (which uses `posting_time_rules.score≥85`) for the new fixed-slot scheduler. Keep `posting_time_rules` table for Calendar heatmap visualization only.
3. `LinkedInDraftController::checkConflict` — keep existing logic but augment with fixed-slot awareness: warn if operator manually picks a non-slot time.

**Settings:**
- `linkedin_publish_slots` JSON, default `"[5,6,7,12,17,18,19,20]"`
- `linkedin_slot_lead_time_minutes` int, default `5`

### Change 2 — `LinkedInFormatMixGovernor` (Format problem)

**New service:** `app/Services/LinkedInFormatMixGovernor.php`

- Method `shouldOverrideToCarousel(LinkedInPost $draft, string $pluginEmittedFormat): bool`:
  - Skip if `pluginEmittedFormat === 'carousel'` (no override needed)
  - Skip if bootstrap (< `lookback_window` historical drafts) — let plugin decide freely
  - Query last N=10 active drafts (`status NOT IN ('cancelled','failed') AND created_at < $draft->created_at`)
  - Compute `carousel_ratio = carousel_count / total`
  - If `carousel_ratio < target_ratio (0.8)` → return `true` (override to carousel)
  - Else → return `false` (accept text, ratio already met)

**Wire into `LinkedInGenerationService::persistAndRoute`:**
- After plugin emits brief, before FSM advance to Generating
- If governor returns true → re-dispatch `/linkedin-gen` with new input field `format_preference='carousel'`
- Log decision to `pipeline_state_log[]` with reason `format_overridden_by_governor`
- Idempotent: if re-dispatch ALSO returns text (plugin refused override), accept and log `plugin_refused_format_override`

**Settings:**
- `linkedin_format_carousel_target_ratio` float, default `0.8`
- `linkedin_format_lookback_window` int, default `10`
- `linkedin_format_governor_enabled` bool, default `true`

### Change 3 — Plugin `linkedin-post-writer` v0.7.0

**Schema update** (`linkedin-gen/schema.ts`):
```ts
// Input schema (new optional field)
format_preference: z.enum(['carousel', 'text']).optional(),

// OrchestratorOutputSchema (new field)
format_override_reason: z.string().nullable().optional(),  // why plugin honored/refused
```

**`/linkedin-brief` honors:**
- If `format_preference='carousel'` AND content can support carousel → emit carousel (skip natural text decision)
- If `format_preference='carousel'` BUT content hopelessly unsuited (e.g., 100-word post, listicle of 1 item) → emit text + set `format_override_reason='content_too_thin_for_carousel'`
- If `format_preference='text'` → emit text (operator can force back when needed)
- If absent → existing natural decision logic

**Deploy steps** (operator manual):
- `git pull` plugin to v0.7.0 in plugin cache
- `npm install && npm run compile-refs`
- 3 bundles produced (refs-linkedin-playbook/templates/formats); `refs-linkedin-carousel` retired in v0.5.0 — unchanged
- Verify symlinks at `/home/claudesn/refs-linkedin-*.md`
- `php artisan config:cache && systemctl restart portfolio-queue.service`

**Backwards-compat:** if plugin not yet deployed, governor sends `format_preference` field but old plugin ignores it. Governor logs `plugin_refused_format_override` and ships whatever plugin emitted. No FSM breakage.

### Change 4 — `PublishViaPubler` real implementation

**Rewrite:** `app/Jobs/PublishViaPubler.php` (replaces 110-line stub with real flow)

- Constructor accepts `$platform` (instagram|tiktok|threads|facebook) and `$siblingPostId` (FK to platform table row)
- `handle(PublerClient $client)`:
  - Load sibling row (e.g., `InstagramPost::find($siblingPostId)`)
  - Idempotency: skip if `publer_post_id IS NOT NULL` (already published)
  - Build payload via per-platform method `buildInstagramPayload` / `buildTiktokPayload` / `buildThreadsPayload`:
    - `caption` (text body)
    - `hashtags[]` joined to caption tail
    - `link_comment` (where supported — IG/TH yes, TT no)
    - `slides[]` resolved to absolute URLs from `linkedin_posts.carousel_slides[].image_url` via parent linkedin_post relation
    - `scheduled_at` from sibling row (matches LinkedIn slot)
  - Call `$client->createPost($payload)` or `$client->schedulePost($payload)` (HTTP wrapper already exists)
  - On success: persist `publer_post_id`, `external_url`, set status='published', `published_at=now()`
  - On 4xx error: persist `last_error`, status='failed', dispatch Telegram alert
  - On 5xx / network error: throw exception, queue worker retries (3 tries, 60s backoff)

**Carousel slide source of truth:** `linkedin_posts.carousel_slides[].image_url` — all 4 platforms share the SAME rendered PNGs (already generated via `/carousel-gen` + GeminiGen). No re-rendering needed per platform.

**TikTok-specific:** caption body MUST carry blog URL (no first-comment API). `link_comment` column populated for parity (May 10 ship) but `buildTiktokPayload` doesn't pass it to Publer — URL is already in caption via `ShortLinkService::forBlogPost($post, 'tiktok')`.

**Tests** (new): `tests/Unit/PublishViaPublerInstagramTest.php`, `...TiktokTest.php`, `...ThreadsTest.php`. Mock `PublerClient` via Bus fake. Cover happy path + 4xx fail + 5xx retry + idempotent skip.

### Change 5 — `social:publish-slot` orchestrator (Atomic sync)

**New command:** `app/Console/Commands/PublishSlotOrchestrator.php`

Signature: `social:publish-slot [--dry-run] [--limit=N]`

Replaces or supplements `linkedin:process-scheduled` (Phase 7 decides). Runs every minute via existing scheduler infra.

**Flow per tick:**
1. Query `LinkedInPost::where('scheduled_at', '<=', now())->where('status', 'awaiting_publish')->whereNull('deleted_at')->limit($limit)`
2. For each due draft:
   - If `format === 'text'`:
     - Publish LinkedIn via existing `LinkedInPublishService::publish($draft)`
     - Async: scanner will fan-out FB+TH siblings (existing flow)
     - DONE — no sibling atomic check needed
   - If `format === 'carousel'`:
     - **Atomic readiness check**: fetch FB+IG+TT+TH siblings; for each `non-null` sibling check:
       - `status IN ('awaiting_publish', 'awaiting_review')`
       - `caption !== '' AND caption !== null`
       - For IG/TT (image-dependent): all `linkedin_posts.carousel_slides[].image_status === 'done'`
     - If any sibling check fails AND `postpone_count < 2`:
       - Find next slot via `LinkedInFixedSlotScheduler::nextAvailableSlot()`
       - Update LinkedIn + all siblings `scheduled_at = next_slot`, `cancel_window_ends_at = next_slot`
       - Log `pipeline_state_log[]` reason `slot_postponed_siblings_not_ready`, increment `postpone_count`
       - Dispatch Telegram `telegram_notify_linkedin_slot_postponed` if enabled
       - SKIP this tick — wait for next slot
     - If `postpone_count >= 2` AND siblings still not ready:
       - Publish LinkedIn solo
       - Mark unready siblings `status=manual_review`, `last_error='slot_missed_max_postpones'`
       - Dispatch Telegram `telegram_notify_linkedin_siblings_dropped`
     - If all siblings ready:
       - Publish LinkedIn via `LinkedInPublishService::publish($draft)`
       - For each ready sibling, dispatch `PublishViaPubler::dispatch($platform, $siblingId)` (parallel queue workers)
       - All siblings get fired at same minute tick

**Schedule entry** (`routes/console.php` or `ScheduledCommandSeeder`):
```php
Schedule::command('social:publish-slot')
  ->everyMinute()
  ->withoutOverlapping(5)
  ->runInBackground();
```

Replaces `linkedin:process-scheduled` (kept as alias for backwards compat for 1 cycle, then removed in Phase 7).

### Change 6 — Sibling `scheduled_at` propagation

**Edit:** `ScanLinkedInForCrossPost.php`

After existing `createInstagram/createTiktok/createThreads/createFacebook` calls succeed, add:

```php
// Propagate slot to all newly-created siblings so social:publish-slot
// can find them at the same tick as the LinkedIn parent.
if ($linkedinPost->scheduled_at !== null) {
    DB::transaction(function () use ($linkedinPost) {
        $linkedinPost->facebookPost?->update(['scheduled_at' => $linkedinPost->scheduled_at]);
        $linkedinPost->instagramPost?->update(['scheduled_at' => $linkedinPost->scheduled_at]);
        $linkedinPost->tiktokPost?->update(['scheduled_at' => $linkedinPost->scheduled_at]);
        $linkedinPost->threadsPost?->update(['scheduled_at' => $linkedinPost->scheduled_at]);
    });
}
```

Also: when `LinkedInDraftController::approve` assigns slot, propagate immediately (don't wait for scanner's 2-min tick). Add propagation call to controller flow.

**Backfill:** new artisan `linkedin:sync-sibling-slots [--dry-run]` (one-shot) — walks existing LinkedIn drafts with `scheduled_at`, mirrors to siblings. Idempotent.

### Change 7 — Admin UI updates

**[AboutSettings.vue](../../frontend/src/views/admin/AboutSettings.vue)** — extend "LinkedIn Integration" card with new section:
- `linkedin_publish_slots` text input (comma-separated hours, e.g. `5,6,7,12,17,18,19,20`)
- `linkedin_slot_lead_time_minutes` number input (default 5)
- `linkedin_format_carousel_target_ratio` slider 0.0–1.0 (default 0.8)
- `linkedin_format_lookback_window` number input (default 10)
- `linkedin_format_governor_enabled` toggle

**[LinkedInDraftDetail.vue](../../frontend/src/views/admin/LinkedInDraftDetail.vue)** — status hero panel:
- For `awaiting_publish` status: replace cancel-window countdown copy with "Scheduled for {DD MMM HH:00 WIB}"
- Show "Cancel" button enabled until slot fires (same logic, longer window)
- If `postpone_count > 0` → amber chip "Postponed {N} time(s) — waiting for siblings"

**[LinkedInPostsCalendar.vue](../../frontend/src/views/admin/LinkedInPostsCalendar.vue)** — overlay all-platform indicators:
- Each cell with a scheduled post shows mini chips for each platform that will fire at that slot (LI / IG / TT / TH / FB)
- Click cell → side panel lists all 4 platform drafts with status pills
- API response from `/admin/linkedin-posts/calendar` extended to include sibling status array

### Change 8 — Settings + telegram + cleanup

**New settings rows** (via `LinkedInSettingsSeeder` + `TelegramSettingsSeeder` `firstOrCreate`):
- `linkedin_publish_slots` JSON
- `linkedin_slot_lead_time_minutes` int
- `linkedin_format_carousel_target_ratio` float
- `linkedin_format_lookback_window` int
- `linkedin_format_governor_enabled` bool
- `telegram_notify_linkedin_slot_postponed` bool (default true)
- `telegram_notify_linkedin_siblings_dropped` bool (default true)

**Retire / rename:**
- `linkedin:process-scheduled` → alias of `social:publish-slot` for 1 cycle, then deleted
- `posting_time_rules.score≥85` logic retired from auto-schedule cron (table retained for Calendar heatmap)
- `linkedin_cancel_window_minutes` setting still works for backwards compat but no longer used by approve flow (cancel window = slot time itself)

## Data Integration Map

| Component | Data Source | Existing? | Notes |
|---|---|---|---|
| Fixed slots config | `setting('linkedin_publish_slots')` JSON | NEW row in seeder | Default `[5,6,7,12,17,18,19,20]` |
| Slot collision query | `linkedin_posts.scheduled_at` | Existing | `WHERE scheduled_at BETWEEN slot AND slot+1h AND status IN ('awaiting_publish')` |
| Format ratio query | `linkedin_posts.format, created_at` | Existing | Last-10 lookback ordered DESC |
| Plugin re-dispatch | `LinkedInGenerationService::dispatch` | Existing | New input field requires plugin v0.7.0 |
| Publer publish | `PublerClient::createPost` / `schedulePost` | Existing real HTTP | Currently unwired from job (stub fix in Change 4) |
| Sibling scheduled_at | `{platform}_posts.scheduled_at` | Existing columns | New propagation step in scanner + controller |
| Postpone counter | `pipeline_state_log[]` JSON | Existing column | New reason `slot_postponed_siblings_not_ready` |
| Calendar view | `GET /admin/linkedin-posts/calendar` endpoint | Existing (May 6 ship) | Response shape extended with sibling status |
| Atomic readiness check | `carousel_slides[].image_status` JSON | Existing | Per-slide done check |
| Telegram alerts | `DispatchTelegramNotification` job | Existing | New event keys for postpone/dropped |

## Trade-offs

| Concern | Impact | Mitigation |
|---|---|---|
| 8 slots/day × 1-post-per-slot = max 8 posts/day | Backlog cliff on busy days | Calendar view shows backlog. Operator can hand-trigger off-slot via existing Publish Now button. Backlog clears at 8/day rate. |
| Plugin re-dispatch doubles SSH cost on ~20% of runs | ~60-90s extra per overridden draft | Async via queue worker. Operator UI shows `generating` longer (already supported by linkedinHelpers progress %). |
| Atomic postpone can cascade — Monday's stuck IG pushes Tue+Wed posts | Backlog ripple | Max 2 postpones then LinkedIn ships solo. Telegram alert surfaces stuck siblings before they snowball. |
| TikTok via Publer has no first-comment support | Link in body shortens caption budget | Already handled — TikTok URL in caption body via ShortLinkService `/r/{code}` (May 10 ship). `buildTiktokPayload` doesn't pass `link_comment`. |
| Plugin v0.7.0 deploy = recompile refs on VPS | Manual operator step | Document in plan. Backwards-compat: governor gracefully no-ops if plugin ignores `format_preference`. |
| Existing `linkedin:auto-schedule` uses `posting_time_rules.score≥85` AI logic | Wasted research data | `posting_time_rules` retained for Calendar heatmap. Auto-schedule cron swaps to fixed-slot scheduler. |
| `LinkedInPublishService::publish` was synchronous in queue context | Slot orchestrator could timeout on bad LinkedIn API day | Wrap LinkedIn publish in 30s timeout. On timeout → postpone increments, retry next minute tick. |
| `social:publish-slot` every minute = 1440 ticks/day for ~8 publishes | Cron noise | Acceptable — same cost profile as current `linkedin:process-scheduled`. `withoutOverlapping(5)` ensures no double-fire. |
| First-comment URL needs LinkedIn URN before posting | If LinkedIn publish fails, comment can't fire | Existing pattern (current `publishText` + `PostLinkedInFirstComment` delay job) handles this. Sibling publishes don't need LinkedIn URN. |
| Posts approved on Sunday afternoon all flow to Monday 5:00+ | Weekend → Monday peak | Acceptable — operator can manually shift via existing reschedule modal if undesired. |

## Implementation Phases (7 phases for /gaspol-plan to expand)

| Phase | Scope | Plugin dep | Test cost |
|---|---|---|---|
| **P1** | `LinkedInFixedSlotScheduler` service + settings seeder + wire to approve + auto-schedule cron + admin UI inputs | No | 8 unit (slot finder + collision) + 4 feature (approve flow + auto-schedule cron) |
| **P2** | `LinkedInFormatMixGovernor` service + integrate into `LinkedInGenerationService::persistAndRoute` + 3 setting rows + admin UI | No (graceful no-op) | 6 unit (ratio math + bootstrap + override decision) + 3 feature (re-dispatch flow) |
| **P3** | Plugin `linkedin-post-writer` v0.7.0 — schema + `/linkedin-brief` honors format_preference + compile-refs + VPS deploy | Yes (plugin repo work) | Plugin tests (~5-10 cases) + VPS deploy verification |
| **P4** | `PublishViaPubler` real impl (3 platform methods) wired to PublerClient | No | 12 unit (3 platforms × happy + 4xx + 5xx + idempotent) + 3 feature (end-to-end Publer mock) |
| **P5** | `social:publish-slot` orchestrator + sibling `scheduled_at` propagation + atomic postpone logic + backfill artisan | No | 10 feature (atomic readiness paths + postpone cascade + LinkedIn-solo fallback) |
| **P6** | Admin UI — AboutSettings slots card + LinkedInDraftDetail "Scheduled for {slot}" + Calendar all-platform overlay | No | Vue smoke tests (manual) |
| **P7** | CLAUDE.md update + retire/rename `linkedin:process-scheduled` + telegram notify wiring + ops doc | No | Docs only |

**Ship order:** P1 standalone (urgent fix to scheduling), then P2+P3 together (format-mix requires plugin v0.7.0 for full enforcement; ships graceful no-op until plugin deployed), then P4+P5 together (atomic sync needs working Publer), then P6+P7 cleanup.

## Open Questions Resolved

| Q | Answer |
|---|---|
| Skip cancel_window or keep with snap-to-slot? | **Skip entirely.** `cancel_window_ends_at = scheduled_at` (slot time). Operator can cancel anytime between approve and slot fire. |
| Format decision location? | **Backend override.** Plugin decides naturally; governor over-rides when ratio drifts < 80% carousel. |
| Slot collision policy? | **1 post per slot, FIFO antri ke slot berikutnya.** Strict. |
| Sibling not ready at slot? | **Delay whole publish ke slot berikutnya.** Atomic all-or-nothing. Max 2 postpones, then LinkedIn solo + alert. |
| Plan size? | **Bundle all 4 concerns in one plan, ~1.5 day.** Fix scheduling + format mix + complete PublishViaPubler + atomic sync. |

## Risks

| Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|
| Plugin v0.7.0 honor of `format_preference` is partial — refuses overrides too aggressively | Medium | Format mix stays text-biased | Tune plugin's "content too thin" heuristic empirically; log refusal reasons; operator can manual override per draft. |
| Publer API rate limits hit at slot time (4 posts fired simultaneously) | Low | Some siblings 429 → retry | Queue worker handles 5xx retry. Stagger sibling dispatches by 5-10 sec within same minute. |
| LinkedIn slot fires but blog URL not yet ready in `link_comment` | Low | First comment fires with empty body | `LinkedInPost::ensureLinkCommentHasUrl()` (May 5 defense-in-depth) catches this — defensive rewrite. |
| Operator changes `linkedin_publish_slots` mid-day, breaks existing scheduled drafts | Medium | Some drafts orphaned at retired slots | On setting change, run `linkedin:resync-slots` artisan that re-runs `nextAvailableSlot()` for all `awaiting_publish` drafts. Show warning in admin UI. |
| `social:publish-slot` cron overlaps with `linkedin:process-scheduled` during P1→P5 transition | Low | Double publish | `withoutOverlapping(5)` on both. Plus `linkedin:process-scheduled` retired in P7. Plus DB-level idempotency: `WHERE status='awaiting_publish'` flips to `published` inside transaction. |
| `posting_time_rules` Calendar heatmap shows misleading colors after fixed-slot switch | Low | Operator confusion | Add disclaimer banner: "Slots fire only at fixed hours per `linkedin_publish_slots` setting. Heatmap shows historical research, not active schedule." |
| Bootstrap period — first 10 drafts after governor enables get noisy ratio | Low | Format mix unstable early | Governor skips override when `total < lookback_window`. Stabilizes after 10 drafts (~1-2 days). |

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

### Goal

Ship LinkedIn fixed-slot scheduling + backend format-mix governor (80% carousel / 20% text) + complete `PublishViaPubler` stub → real wiring + atomic cross-post sync at slot time, so LinkedIn + IG + TikTok + Threads all fire at the same minute tick on carousel-format drafts.

### Architecture Context (from CLAUDE.md)

- **FSM:** [`HasStatusTransitions`](../../backend/app/Traits/HasStatusTransitions.php) trait + [`PipelineGuard::advance`](../../backend/app/Services/PipelineGuard.php) gate every status write. Reason strings mandatory. `pipeline_state_log[]` JSON column on `linkedin_posts` retains last 20 transitions.
- **Existing scheduler:** [`LinkedInAutoSchedulerService::nextAvailableSlot`](../../backend/app/Services/LinkedInAutoSchedulerService.php) uses `posting_time_rules.score≥85`, 14-day lookahead, audience b2b_tech. This is what P1 swaps to fixed slots. `posting_time_rules` table kept for Calendar heatmap viz.
- **Existing crons** (registered in [`routes/console.php`](../../backend/routes/console.php) via `Schedule::command()`):
  - `linkedin:process-scheduled` every minute → fires `LinkedInPublishService::publish` when `cancel_window_ends_at <= now()`
  - `linkedin:auto-schedule` daily 04:30 WIB → promotes `manual_review` drafts to `awaiting_publish`
  - `social-cross-post:scan` every 2 min → fans out FB+TH (text) or FB+IG+TT+TH (carousel) siblings
- **Existing settings group `linkedin`** ([`LinkedInSettingsSeeder`](../../backend/database/seeders/LinkedInSettingsSeeder.php)): 9 keys including `linkedin_auto_publish`, `linkedin_cancel_window_minutes`, `linkedin_virality_min_score`, `linkedin_auto_approve_enabled`. New rows added via `firstOrCreate` (idempotent).
- **Existing Publer infra:** [`PublerClient`](../../backend/app/Services/PublerClient.php) 550 lines, real HTTP wrapper with `createPost()` + `schedulePost()` + `account()` methods. [`PublishViaPubler`](../../backend/app/Jobs/PublishViaPubler.php) currently 110-line stub — Phase 4 rewrites to call PublerClient.
- **Existing models:** `LinkedInPost`, `FacebookPost`, `InstagramPost`, `TiktokPost`, `ThreadsPost`. All cross-post tables have `scheduled_at`, `published_at`, `external_url`, `caption`, `hashtags`, `link_comment` (except FB which intentionally has no link_comment).
- **Existing FSM transitions** (for `LinkedInPost`): legal paths to `published` from `awaiting_publish` only. New transitions for cross-post tables: define `AwaitingPublish → Publishing → Published / Failed` in each platform's status enum.
- **Test patterns:** PHPUnit (NOT Pest). Existing tests use `Tests\TestCase` + `RefreshDatabase`. Mock SSH/HTTP via `Process::fake()` + `Http::fake()`. Carbon test time via `Carbon::setTestNow()`.
- **Admin Scheduler tab** (May 9 ship) — DB-driven cron config via `scheduled_commands` table. New `social:publish-slot` schedule row seeded in [`ScheduledCommandSeeder`](../../backend/database/seeders/ScheduledCommandSeeder.php).

### Tech Stack

- **Backend:** Laravel 12 + PHP 8.2 + MySQL 8, Carbon 3 for slot math, Eloquent for queries, `DB::transaction` for atomic sibling updates
- **Plugin:** TypeScript + Zod schemas in `linkedin-post-writer` repo; bumped to v0.7.0
- **Frontend:** Vue 3.5 + Composition API + TanStack Query 5.90 + Tailwind 4 (existing patterns from `LinkedInDraftDetail.vue`)
- **Testing:** PHPUnit 10 for backend, Node smoke tests for frontend helpers
- **Deployment:** GitHub Actions auto-deploy on push to main (see deploy.sh)

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Fixed slots config | `setting('linkedin_publish_slots')` JSON | `Setting::get()` via `SettingService` | Pattern exists | New row in `LinkedInSettingsSeeder` (idempotent firstOrCreate) |
| Slot collision check | `linkedin_posts.scheduled_at` query | Eloquent | Yes | Use existing column + status whitelist filter |
| Format ratio query | `linkedin_posts.format, created_at` | Eloquent | Yes | New `LinkedInFormatMixGovernor::computeRatio()` method |
| Plugin format override input | `format_preference` field on plugin input JSON | `LinkedInGenerationService::buildBlogPayload()` | No — needs plugin v0.7.0 | P2 emits field; P3 plugin honors. Backwards-compat no-op if plugin ignores. |
| Publer HTTP call | `PublerClient::createPost()` | Existing real HTTP wrapper | Yes | Wire from `PublishViaPubler::handle()` (Phase 4) |
| Sibling `scheduled_at` write | `{platform}_posts.scheduled_at` UPDATE | Eloquent `update()` | Yes | New propagation step in scanner + controller |
| Postpone counter | `pipeline_state_log[]` JSON | `LinkedInPost.$casts['array']` | Yes | New reason `slot_postponed_siblings_not_ready` |
| Slot orchestrator query | `LinkedInPost::where('scheduled_at', '<=', now())->where('status', 'awaiting_publish')` | Eloquent | Yes | Used by new `social:publish-slot` cron |
| Atomic readiness check | `carousel_slides[].image_status` JSON path | `LinkedInPost.$casts` | Yes | New `LinkedInSlotReadinessService::isReady($draft)` |
| LinkedIn publish | `LinkedInPublishService::publish($draft)` | Existing service | Yes | Called by orchestrator first (before siblings) |
| TikTok URL in caption | `ShortLinkService::forBlogPost($post, 'tiktok')` | Existing | Yes (May 10 ship) | Used by `buildTiktokPayload` |
| Telegram alerts | `DispatchTelegramNotification::dispatch($idea, $event)` | Existing job | Yes | New event keys `linkedin_slot_postponed`, `linkedin_siblings_dropped`, `linkedin_no_available_slot` |
| Admin UI settings inputs | `PUT /api/admin/settings/linkedin` | Existing endpoint | Yes | Extend `SettingsController::updateLinkedInSettings` validation + sanitization |
| Calendar sibling overlay | `GET /admin/linkedin-posts/calendar` | Existing endpoint | Yes (May 6 ship) | Response shape extended with sibling status array |

---

### Phase 1 (P1): Fixed-Slot Scheduler Service

**Estimated time:** 90 minutes total (broken into 9 ~10-min sub-steps)

**Files:**
- Create: `backend/app/Services/LinkedInFixedSlotScheduler.php`
- Create: `backend/app/Exceptions/NoAvailableSlotException.php`
- Modify: `backend/database/seeders/LinkedInSettingsSeeder.php` (2 new rows)
- Modify: `backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php` (approve method)
- Modify: `backend/app/Console/Commands/AutoScheduleManualReviewLinkedInPosts.php` (swap scheduler)
- Modify: `backend/app/Http/Controllers/Api/SettingsController.php` (validation for new settings)
- Test: `backend/tests/Unit/LinkedInFixedSlotSchedulerTest.php` (NEW)
- Test: `backend/tests/Feature/LinkedInDraftApproveSlotAssignmentTest.php` (NEW)
- Test: `backend/tests/Feature/AutoScheduleManualReviewLinkedInPostsTest.php` (EXTEND — existing 5 tests)

**Steps:**
1. Write failing test `LinkedInFixedSlotSchedulerTest::test_returns_next_hour_in_slot_list`. Expected error: `Error: Class "App\Services\LinkedInFixedSlotScheduler" not found`.
2. Run test, confirm it fails for the expected reason: `vendor/bin/phpunit --filter test_returns_next_hour_in_slot_list`
3. Create `NoAvailableSlotException.php` extending `RuntimeException` with `$lookaheadDays` constructor param.
4. Create `LinkedInFixedSlotScheduler.php` with constructor accepting `array $slots = null` (resolves from setting if null) + `int $leadTimeMinutes = null`. Implement `nextAvailableSlot(?Carbon $from = null): Carbon` — iterates day-by-day max 14 days, hour-by-hour from `$slots`, skips occupied slots via collision query.
5. Run test, confirm pass.
6. Write 7 more unit tests covering: empty slots → throws, lead-time guard (slot at `now()+3min` skipped when lead=5), TZ Asia/Jakarta enforcement, day-boundary roll (last slot today exhausted → first slot tomorrow), 14-day exhaustion → throws `NoAvailableSlotException`, custom slots override via constructor, collision with `awaiting_review` status (not just `awaiting_publish`).
7. Run all unit tests, confirm pass.
8. Add 2 settings rows to `LinkedInSettingsSeeder` (firstOrCreate): `linkedin_publish_slots` JSON `"[5,6,7,12,17,18,19,20]"`, `linkedin_slot_lead_time_minutes` int `"5"`. Run seeder `php artisan db:seed --class=LinkedInSettingsSeeder` and verify with `Setting::where('group','linkedin')->count() >= 11`.
9. Write failing feature test `LinkedInDraftApproveSlotAssignmentTest::test_approve_assigns_next_fixed_slot`. Mock `Carbon::setTestNow('2026-05-13 04:00:00 Asia/Jakarta')`. Approve a `manual_review` draft. Assert `scheduled_at === '2026-05-13 05:00:00 Asia/Jakarta'` AND `cancel_window_ends_at === scheduled_at` (NOT `now() + 15min`).
10. Modify `LinkedInDraftController::approve()` — inject `LinkedInFixedSlotScheduler` via constructor, replace `cancel_window_ends_at = now()->addMinutes($cancelWindow)` with `$slot = $scheduler->nextAvailableSlot(); scheduled_at = $slot; cancel_window_ends_at = $slot;`. Keep `linkedin_cancel_window_minutes` setting unchanged for backwards compat (no longer used by approve).
11. Run feature test, confirm pass.
12. Add 2 more feature tests: FIFO collision (3 approvals in 1 min → slots 5, 6, 7), kill-switch OFF still assigns slot (operator can flip on later).
13. Modify `AutoScheduleManualReviewLinkedInPostsTest` — update existing 5 tests to expect fixed-slot output instead of score-based. Modify `AutoScheduleManualReviewLinkedInPosts::handle()` to inject `LinkedInFixedSlotScheduler` instead of `LinkedInAutoSchedulerService`. Run feature suite, confirm pass.
14. Modify `SettingsController::updateLinkedInSettings()` — add validation: `linkedin_publish_slots` must be JSON array of ints 0-23, dedupe + sort, between 1 and 24 entries. Reject malformed.
15. Commit: `feat(linkedin): fixed-slot scheduler service + wire to approve + auto-schedule cron`

**Verification:**
- [ ] `php artisan test --filter LinkedInFixedSlotScheduler` passes 8/8 unit
- [ ] `php artisan test --filter LinkedInDraftApproveSlotAssignment` passes 3/3 feature
- [ ] `php artisan test --filter AutoScheduleManualReview` passes 5/5 (existing, modified)
- [ ] `php -l` clean across 5 modified files
- [ ] `Setting::where('group','linkedin')->count() >= 11` (was 9, +2 new)
- [ ] Manual smoke: tinker `app(LinkedInFixedSlotScheduler::class)->nextAvailableSlot()` returns valid Carbon at one of `[5,6,7,12,17,18,19,20]` WIB hours
- [ ] No placeholder/TODO comments in new code

---

### Phase 2 (P2): Format-Mix Governor Service

**Estimated time:** 60 minutes total

**Files:**
- Create: `backend/app/Services/LinkedInFormatMixGovernor.php`
- Modify: `backend/app/Services/LinkedInGenerationService.php` (inject + integrate into `persistAndRoute`)
- Modify: `backend/app/Services/LinkedInGenerationService.php` (extend `buildBlogPayload` to emit `format_preference`)
- Modify: `backend/database/seeders/LinkedInSettingsSeeder.php` (3 new rows)
- Modify: `backend/app/Http/Controllers/Api/SettingsController.php` (validation for new settings)
- Test: `backend/tests/Unit/LinkedInFormatMixGovernorTest.php` (NEW)
- Test: `backend/tests/Feature/LinkedInGenerationServiceFormatOverrideTest.php` (NEW)

**Steps:**
1. Write failing test `LinkedInFormatMixGovernorTest::test_returns_false_when_plugin_already_emitted_carousel`. Expected error: `Error: Class "App\Services\LinkedInFormatMixGovernor" not found`.
2. Run, confirm fail.
3. Create governor with constructor injecting `SettingService`. Methods: `shouldOverrideToCarousel(LinkedInPost $draft, string $pluginEmittedFormat): bool` + `computeRatio(LinkedInPost $draft): float` (public for testability).
4. Implement: skip-if-carousel rule → return false. Implement: skip-if-disabled rule via `linkedin_format_governor_enabled` setting. Run test, confirm pass.
5. Write 5 more unit tests: bootstrap skip (< lookback drafts) → false, ratio met (8/10 carousel) → false, ratio below (4/10 carousel, plugin emitted text) → true, exclude cancelled/failed from lookback, exclude current draft from lookback (use `created_at < $draft->created_at`).
6. Run all unit tests, confirm 6/6 pass.
7. Add 3 settings rows to seeder: `linkedin_format_carousel_target_ratio` `"0.8"`, `linkedin_format_lookback_window` `"10"`, `linkedin_format_governor_enabled` `"true"`. Re-seed.
8. Write failing feature test `LinkedInGenerationServiceFormatOverrideTest::test_text_brief_redispatched_as_carousel_when_ratio_low`. Mock `Process::fake()` to return text on first call + carousel on second. Assert plugin invoked twice, FSM transitions through `format_overridden_by_governor` log entry, final draft format=carousel.
9. Modify `LinkedInGenerationService::persistAndRoute()`: after parsing brief but BEFORE FSM advance to Generating, call `$governor->shouldOverrideToCarousel($draft, $parsed['format'])`. If true, log to `pipeline_state_log[]` with reason `format_overridden_by_governor`, re-invoke `dispatchGen($draft, ['format_preference' => 'carousel'])`. Cap re-dispatch at 1 (no infinite loop).
10. Modify `LinkedInGenerationService::buildBlogPayload()` to accept optional `?string $formatPreference = null` arg, emit `format_preference` key in plugin input JSON when non-null. Backwards-compat: when null, key omitted entirely.
11. Run feature test, confirm pass.
12. Write 2 more feature tests: plugin still emits text on 2nd dispatch (refused override) → log `plugin_refused_format_override`, accept text; governor disabled → no override even with bad ratio.
13. Modify `SettingsController::updateLinkedInSettings()` — add validation: ratio 0.0-1.0 float, lookback_window 1-100 int, governor_enabled bool.
14. Commit: `feat(linkedin): format-mix governor (80/20 carousel/text) with plugin redispatch path`

**Verification:**
- [ ] `php artisan test --filter LinkedInFormatMixGovernor` passes 6/6 unit
- [ ] `php artisan test --filter LinkedInGenerationServiceFormatOverride` passes 3/3 feature
- [ ] Existing `LinkedInGenerationServiceCarouselGenRouter` tests still pass (no regression)
- [ ] `Setting::where('group','linkedin')->count() >= 14` (was 11 after P1, +3 new)
- [ ] Governor gracefully no-ops when plugin v0.7.0 NOT deployed (plugin ignores `format_preference`, log shows `plugin_refused_format_override`, draft ships as text)
- [ ] No placeholder/TODO comments in new code

---

### Phase 3 (P3): Plugin `linkedin-post-writer` v0.7.0

**Estimated time:** 75 minutes total (plugin work + VPS deploy verification)

**Repo:** `D:\Projects\claude-plugin\linkedin-post-writer` (separate from Portfolio_v2)

**Files (plugin repo):**
- Modify: `linkedin-gen/schema.ts` (add `format_preference` input + `format_override_reason` output)
- Modify: `linkedin-brief/SKILL.md` (honor logic, refusal heuristic, hard rules update)
- Modify: `package.json` (bump version 0.6.0 → 0.7.0)
- Modify: `tests/linkedin-brief.test.ts` (3 new test cases)
- Modify: `scripts/compile-refs.ts` (no change; verify still produces 3 bundles)

**Steps:**
1. Plugin repo: write failing test `linkedin-brief.test.ts::"emits carousel when format_preference=carousel and content is rich"`. Expected error: TypeScript compile fail on missing `format_preference` field in schema.
2. Edit `linkedin-gen/schema.ts`: add to input schema `format_preference: z.enum(['carousel', 'text']).optional()`. Add to `OrchestratorOutputSchema` `format_override_reason: z.string().nullable().optional()`.
3. Edit `linkedin-brief/SKILL.md` — add new section "Format Preference Override":
   - If `format_preference='carousel'` AND content has ≥4 substantive points (each ≥30 words) → emit carousel
   - If `format_preference='carousel'` AND content too thin (< 4 points, OR total body < 400 words) → emit text + set `format_override_reason='content_too_thin_for_carousel'`
   - If `format_preference='text'` → emit text + set `format_override_reason='text_forced_by_caller'`
   - If absent → existing natural decision
4. Run plugin tests `npm test`, confirm 3 new tests pass + 0 regressions on existing 221 tests (sanity).
5. Add 2 more tests: refusal path (thin content → text + reason), absent field path (no preference → natural decision unchanged).
6. Bump `package.json` version to `0.7.0`.
7. Run `npm run compile-refs`, verify 3 refs bundles produced (no change in count from v0.5.0+).
8. Commit + tag + push: `git tag v0.7.0; git push --tags`.
9. **VPS deploy** (manual operator step, documented in plan):
   ```bash
   ssh claudesn@vps
   cd /home/claudesn/plugin-cache/linkedin-post-writer
   git fetch && git checkout v0.7.0
   npm install
   npm run compile-refs
   # Verify symlinks
   ls -la /home/claudesn/refs-linkedin-*.md
   # Restart workers so cached config refreshes
   sudo systemctl restart portfolio-queue.service
   ```
10. Smoke test on VPS via tinker:
    ```bash
    cd /var/www/Portfolio_v2/backend && php artisan tinker --execute="\$s=app(App\Services\LinkedInGenerationService::class); \$r=\$s->testDispatch(['format_preference'=>'carousel', 'blog_url'=>'https://alisadikinma.com/blog/test']); echo \$r['format'].' '.\$r['format_override_reason'];"
    ```
    Expected: `carousel <null>` or `text content_too_thin_for_carousel`.
11. Commit Portfolio_v2 docs update: `docs(linkedin): document plugin v0.7.0 deploy step`

**Verification:**
- [ ] Plugin `npm test` passes 226/226 (221 existing + 5 new)
- [ ] Plugin tagged `v0.7.0`, pushed to GitHub
- [ ] VPS plugin cache at v0.7.0 commit hash
- [ ] `/home/claudesn/refs-linkedin-playbook.md`, `refs-linkedin-templates.md`, `refs-linkedin-formats.md` symlinks resolve to v0.7.0 compiled files
- [ ] `portfolio-queue.service` restarted, queue worker uptime ~0 sec at deploy time
- [ ] Smoke test: tinker dispatches plugin with `format_preference=carousel`, response carries `format_override_reason` key
- [ ] No regressions: existing carousel-gen route, text-format flow, validation gate all functional

---

### Phase 4 (P4): PublishViaPubler Real Implementation

**Estimated time:** 120 minutes total

**Files:**
- Modify: `backend/app/Jobs/PublishViaPubler.php` (rewrite 110 lines stub → ~280 lines real)
- Create: `backend/app/Services/PublerPayloadBuilder.php` (per-platform payload composition)
- Test: `backend/tests/Unit/PublerPayloadBuilderTest.php` (NEW)
- Test: `backend/tests/Unit/PublishViaPublerInstagramTest.php` (NEW)
- Test: `backend/tests/Unit/PublishViaPublerTiktokTest.php` (NEW)
- Test: `backend/tests/Unit/PublishViaPublerThreadsTest.php` (NEW)
- Test: `backend/tests/Feature/PublishViaPublerEndToEndTest.php` (NEW)
- Modify: `backend/app/Models/InstagramPost.php`, `TiktokPost.php`, `ThreadsPost.php` (add `publer_post_id` to `$fillable`)
- Create: `backend/database/migrations/2026_05_13_000001_add_publer_post_id_to_cross_post_tables.php`

**Steps:**
1. Write failing migration test (or manual check): `Schema::hasColumn('instagram_posts', 'publer_post_id')` returns false initially.
2. Create migration adding `publer_post_id VARCHAR(64) NULLABLE INDEXED` to `instagram_posts`, `tiktok_posts`, `threads_posts`, `facebook_posts` (4 tables). Run migrate.
3. Update model `$fillable` arrays.
4. Write failing unit test `PublerPayloadBuilderTest::test_instagram_payload_carries_caption_hashtags_link_comment_slides`. Expected error: `Error: Class "App\Services\PublerPayloadBuilder" not found`.
5. Create `PublerPayloadBuilder` with 4 methods: `buildInstagram(InstagramPost)`, `buildTiktok(TiktokPost)`, `buildThreads(ThreadsPost)`, `buildFacebook(FacebookPost)`. Each returns array matching Publer `/posts/schedule` API shape. Reads slides from parent `linkedinPost->carousel_slides[].image_url`.
6. Implement Instagram payload: caption = sibling.caption + "\n\n" + hashtags.join(' '), media[] = slide image_urls, accounts: [{provider:'instagram', id:fromSetting}], comments[]: link_comment present + delay 30s. Tests pass.
7. Implement TikTok: caption with URL in body (no comments[] — TikTok limitation), title from sibling.title (90-char Publer cap), accounts: [{provider:'tiktok', id:fromSetting}]. Tests pass.
8. Implement Threads: caption + hashtags, comments[] with link_comment, accounts: [{provider:'threads', id:fromSetting}]. Tests pass.
9. Implement Facebook: caption (no link_comment per CLAUDE.md May 10), accounts: [{provider:'facebook', id:fromSetting}]. Tests pass.
10. Run `PublerPayloadBuilderTest`, confirm 4/4 platforms tested.
11. Write failing unit test `PublishViaPublerInstagramTest::test_happy_path_persists_publer_id_and_advances_to_published`. Expected: stub still in place, test fails with "stub invoked, no DB update".
12. Rewrite `PublishViaPubler.php`:
    - Constructor: `__construct(public string $platform, public int $siblingPostId)`
    - `handle(PublerClient $client, PublerPayloadBuilder $builder)`:
      - Load sibling model based on $platform
      - Idempotency: skip if `publer_post_id !== null`
      - Build payload via $builder
      - Call `$client->schedulePost($payload)` (returns `['id' => 'publer_xxx', 'external_url' => 'https://...']`)
      - On success: persist `publer_post_id`, `external_url`, advance FSM `awaiting_publish → published` via PipelineGuard
      - On Publer 4xx (validation error): log to `last_error`, advance FSM `→ failed`, dispatch Telegram alert
      - On Publer 5xx / network: throw exception, queue retries (3 tries, backoff 60s/300s/900s)
    - `tries = 3`, `backoff = [60, 300, 900]`, `timeout = 60`
13. Run `PublishViaPublerInstagramTest`, confirm pass.
14. Write 3 more unit test per platform (4xx, 5xx retry, idempotent skip) × 3 platforms = 9 tests. Run, confirm 12 total pass.
15. Write feature test `PublishViaPublerEndToEndTest::test_dispatching_4_platforms_at_slot_publishes_all_via_publer_mock`. Mock `PublerClient` via `Http::fake(['*' => Http::response(['id'=>'publer_abc', 'external_url'=>'https://publer.io/posts/abc'])])`. Dispatch 4 platform jobs sequentially. Assert: 4 rows updated, 4 `external_url` populated, FSM all at `published`.
16. Run feature test, confirm pass.
17. Commit: `feat(publer): real PublishViaPubler wiring to PublerClient (4 platforms)`

**Verification:**
- [ ] Migration applied: `Schema::hasColumn('instagram_posts', 'publer_post_id')` true on all 4 tables
- [ ] `php artisan test --filter PublerPayloadBuilder` passes 4/4 platform payload tests
- [ ] `php artisan test --filter PublishViaPubler` passes 12/12 unit (4 platforms × 3 cases) + 1 feature
- [ ] No `Stub invoked` log lines emitted by `PublishViaPubler`
- [ ] PublerClient HTTP calls verified via `Http::assertSent()` in tests
- [ ] No placeholder/TODO comments in rewritten job
- [ ] Manual VPS smoke: dispatch 1 IG sibling via tinker, verify Publer API hit (check Publer dashboard for new scheduled post)

---

### Phase 5 (P5): `social:publish-slot` Atomic Orchestrator + Sibling Propagation

**Estimated time:** 120 minutes total

**Files:**
- Create: `backend/app/Console/Commands/PublishSlotOrchestrator.php`
- Create: `backend/app/Services/LinkedInSlotReadinessService.php` (extracted readiness check, testable in isolation)
- Modify: `backend/app/Console/Commands/ScanLinkedInForCrossPost.php` (propagate `scheduled_at` to siblings after creation)
- Modify: `backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php` (propagate `scheduled_at` to siblings on approve)
- Modify: `backend/database/seeders/ScheduledCommandSeeder.php` (new schedule row for `social:publish-slot`, mark `linkedin:process-scheduled` as deprecated/alias for 1 cycle)
- Modify: `backend/database/seeders/TelegramSettingsSeeder.php` (3 new notification toggles)
- Modify: `backend/database/seeders/LinkedInSettingsSeeder.php` (`linkedin_max_postpones` setting, default 2)
- Create: `backend/app/Console/Commands/SyncSiblingSlots.php` (one-shot backfill for existing drafts)
- Test: `backend/tests/Unit/LinkedInSlotReadinessServiceTest.php` (NEW)
- Test: `backend/tests/Feature/PublishSlotOrchestratorTest.php` (NEW)
- Test: `backend/tests/Feature/ScanLinkedInForCrossPostSiblingPropagationTest.php` (EXTEND existing)

**Steps:**
1. Write failing unit test `LinkedInSlotReadinessServiceTest::test_carousel_ready_when_all_siblings_caption_filled_and_slides_done`. Expected: class not found.
2. Create `LinkedInSlotReadinessService::isReady(LinkedInPost $draft): array` returning `['ready' => bool, 'blockers' => ['sibling_xx_caption_empty', 'slide_3_pending', ...]]`. For text format: trivially ready (no sibling deps). For carousel: check IG+TT+TH all have non-empty caption + status in [awaiting_publish, awaiting_review] + all `carousel_slides[].image_status === 'done'`. FB checked separately (non-blocking).
3. Run unit test, confirm pass.
4. Write 6 more unit tests: text format always ready, all carousel siblings ready, IG caption empty → not ready with blocker, slide 3 still pending → not ready, sibling soft-deleted → ignore, mix of ready+failed → not ready.
5. Run all 7 unit tests, confirm pass.
6. Modify `ScanLinkedInForCrossPost.php`: after `createInstagram/Tiktok/Threads/Facebook` calls in main loop, add propagation block:
   ```php
   if ($linkedinPost->scheduled_at !== null) {
       DB::transaction(function () use ($linkedinPost) {
           foreach (['facebookPost', 'instagramPost', 'tiktokPost', 'threadsPost'] as $rel) {
               $linkedinPost->$rel?->update(['scheduled_at' => $linkedinPost->scheduled_at]);
           }
       });
   }
   ```
7. Write extension to `ScanLinkedInForCrossPostTest::test_scheduled_at_propagated_to_all_siblings_after_creation`. Run, confirm pass.
8. Modify `LinkedInDraftController::approve()` — after slot assignment, eagerly propagate to siblings (don't wait for scanner's 2-min tick). Same propagation block as scanner.
9. Add settings row `linkedin_max_postpones` int `"2"` to seeder.
10. Add 3 telegram toggles to `TelegramSettingsSeeder`: `telegram_notify_linkedin_slot_postponed` (true), `telegram_notify_linkedin_siblings_dropped` (true), `telegram_notify_linkedin_no_available_slot` (true).
11. Write failing feature test `PublishSlotOrchestratorTest::test_text_format_publishes_linkedin_solo_at_slot`. Mock `Carbon::setTestNow('2026-05-13 05:00:00 Asia/Jakarta')`. Create draft with `scheduled_at='2026-05-13 05:00:00'`, format=text. Run command. Assert: LinkedIn published, no Publer dispatched.
12. Create `PublishSlotOrchestrator.php` with signature `social:publish-slot {--dry-run} {--limit=10}`. Implementation:
    - Query due drafts
    - For each: branch on format
    - Text → call `LinkedInPublishService::publish($draft)` directly
    - Carousel → call `LinkedInSlotReadinessService::isReady($draft)`:
      - Not ready + postpone_count < linkedin_max_postpones:
        - Find next slot via `LinkedInFixedSlotScheduler::nextAvailableSlot()`
        - Update LinkedIn + ALL siblings `scheduled_at = next_slot`, `cancel_window_ends_at = next_slot`
        - Append `pipeline_state_log[]` reason `slot_postponed_siblings_not_ready` with blockers array, increment `postpone_count`
        - Dispatch `DispatchTelegramNotification` if enabled
      - Not ready + postpone_count >= linkedin_max_postpones:
        - Publish LinkedIn solo
        - Mark unready siblings status='manual_review', last_error='slot_missed_max_postpones'
        - Dispatch Telegram alert
      - Ready:
        - Publish LinkedIn via `LinkedInPublishService::publish($draft)`
        - For each non-null sibling: `PublishViaPubler::dispatch($platform, $siblingId)`
13. Run feature test, confirm pass.
14. Write 5 more feature tests:
    - carousel ready → LinkedIn + 3 PublishViaPubler jobs dispatched
    - carousel IG caption empty → all postponed to next slot, postpone_count=1
    - carousel 3rd postpone → LinkedIn solo + manual_review for siblings
    - dry-run → no DB writes, log shows decisions
    - kill switch `linkedin_auto_publish=false` → orchestrator skips entirely (existing pattern)
15. Run all feature tests, confirm 6/6 pass.
16. Write one-shot artisan `SyncSiblingSlots` (`linkedin:sync-sibling-slots {--dry-run}`) — walks `LinkedInPost::whereNotNull('scheduled_at')->whereNull('deleted_at')`, mirrors to siblings. Idempotent. Output count modified.
17. Update `ScheduledCommandSeeder`: add new row `social:publish-slot` cron `* * * * *` enabled=true, category='linkedin' (or new 'cross-post' category), with `without_overlapping_minutes=5`, `run_in_background=true`. Mark `linkedin:process-scheduled` row `description` field to include "DEPRECATED — alias of social:publish-slot, retire in P7".
18. Modify `linkedin:process-scheduled` command to delegate to `PublishSlotOrchestrator::class@handle` (zero functional difference during transition).
19. Commit: `feat(linkedin): atomic slot orchestrator with sibling readiness + postpone cascade`

**Verification:**
- [ ] `php artisan test --filter LinkedInSlotReadinessService` passes 7/7 unit
- [ ] `php artisan test --filter PublishSlotOrchestrator` passes 6/6 feature
- [ ] `php artisan test --filter ScanLinkedInForCrossPostSiblingPropagation` passes new test
- [ ] `Setting::where('group','linkedin')->count() >= 15` (was 14 after P2, +1 new = `linkedin_max_postpones`)
- [ ] `Setting::where('group','telegram')->count() >= 14` (3 new toggles)
- [ ] `scheduled_commands` table has row `signature='social:publish-slot'` enabled=true
- [ ] One-shot smoke: `php artisan linkedin:sync-sibling-slots --dry-run` outputs count without DB writes
- [ ] Manual VPS: existing approved drafts get sibling `scheduled_at` mirrored via backfill artisan
- [ ] No placeholder/TODO comments

---

### Phase 6 (P6): Admin UI — Settings + Calendar Overlay + Detail Page

**Estimated time:** 90 minutes total

**Design Deliverable column applies — UI phase.**

**Files:**
- Modify: `frontend/src/views/admin/AboutSettings.vue` (extend LinkedIn card with new section)
- Modify: `frontend/src/views/admin/LinkedInDraftDetail.vue` (status hero panel copy update)
- Modify: `frontend/src/views/admin/LinkedInPostsCalendar.vue` (sibling status overlay)
- Modify: `frontend/src/composables/useLinkedInDrafts.js` (calendar response shape extended)
- Modify: `frontend/src/views/admin/linkedinHelpers.js` (new helper `formatSlotLabel`)
- Modify: `backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php` (calendar response includes sibling status)
- Test: `frontend/src/views/admin/linkedinHelpers.test.mjs` (EXTEND — add formatSlotLabel tests)

**Design deliverable:**
- Slots input field: comma-separated hours, validated client + server side, help text "8 fixed slots WIB"
- Carousel/text ratio slider: visual 0-100% with gold accent thumb, "Target: 80% carousel" label
- Status hero panel: replace cancel-window countdown with "Scheduled for 13 May · 05:00 WIB" + relative "in 4h 22m" + amber postpone chip when `postpone_count > 0`
- Calendar cell: small platform chips (LI / IG / TT / TH / FB) below post title, colored per platform brand, status dot per chip

**Steps:**
1. Write failing helper test `linkedinHelpers.test.mjs::"formatSlotLabel returns DD MMM HH:00 WIB"`. Expected: function not exported.
2. Add `formatSlotLabel(carbonString, lang='en')` to `linkedinHelpers.js`. Returns "13 May · 05:00 WIB" with Asia/Jakarta conversion. Run test, confirm pass.
3. Write 4 more helper tests: handles null, handles invalid Carbon, ID language variant ("13 Mei · 05:00 WIB"), relative-time suffix ("in 4h 22m").
4. Modify `LinkedInDraftDetail.vue` — find status hero panel section. Replace the cancel-window countdown block with:
   ```vue
   <div v-if="draft.scheduled_at" class="...">
     Scheduled for {{ formatSlotLabel(draft.scheduled_at) }}
     <span class="text-neutral-500">· {{ relativeTime(draft.scheduled_at) }}</span>
   </div>
   <div v-if="postponeCount > 0" class="bg-amber-500/20 text-amber-300 ...">
     Postponed {{ postponeCount }} time(s) — waiting for siblings to be ready
   </div>
   ```
5. Compute `postponeCount` from `draft.pipeline_state_log` (filter reason `slot_postponed_siblings_not_ready`).
6. Extend `AboutSettings.vue` LinkedIn card with new card section:
   - `<input v-model="linkedinSettings.publish_slots" type="text">` with parsing/validation hint "Hours 0-23, comma-separated. Default: 5,6,7,12,17,18,19,20"
   - `<input v-model="linkedinSettings.slot_lead_time_minutes" type="number" min="0" max="60">` (default 5)
   - `<input v-model="linkedinSettings.format_carousel_target_ratio" type="range" min="0" max="1" step="0.05">` (default 0.8) with live percent label
   - `<input v-model="linkedinSettings.format_lookback_window" type="number" min="1" max="100">` (default 10)
   - `<input v-model="linkedinSettings.format_governor_enabled" type="checkbox">` (default true)
   - `<input v-model="linkedinSettings.max_postpones" type="number" min="0" max="5">` (default 2)
7. Update settings save handler to PUT new fields.
8. Modify `LinkedInPostsCalendar.vue` — add cell chip overlay logic. For each post in cell, render small platform chips next to title:
   ```vue
   <div class="flex gap-0.5">
     <span v-if="post.linkedin_status" :class="platformChipClass('linkedin', post.linkedin_status)">LI</span>
     <span v-if="post.instagram_status" :class="platformChipClass('instagram', post.instagram_status)">IG</span>
     ...
   </div>
   ```
9. Extend backend calendar endpoint `LinkedInDraftController::calendar()` to include sibling status fields in response shape:
   ```php
   return [
       'id' => $draft->id,
       'status' => $draft->status,
       // existing fields...
       'instagram_status' => $draft->instagramPost?->status,
       'tiktok_status' => $draft->tiktokPost?->status,
       'threads_status' => $draft->threadsPost?->status,
       'facebook_status' => $draft->facebookPost?->status,
   ];
   ```
10. Update `useLinkedInDrafts.js::useLinkedInCalendar()` composable — no schema change needed (TanStack Query stays generic).
11. Vite production build clean: `cd frontend && npm run build`.
12. Manual smoke: open `/admin/settings`, see new LinkedIn config fields. Open `/admin/linkedin-posts` calendar, see platform chips on existing scheduled drafts. Open a specific draft detail, see "Scheduled for ..." copy instead of cancel-window countdown.
13. Commit: `feat(admin): slot/format settings UI + calendar sibling overlay + draft detail scheduled copy`

**Verification:**
- [ ] `node frontend/src/views/admin/linkedinHelpers.test.mjs` passes 5 new + existing tests
- [ ] Vite build clean, no Vue warnings
- [ ] Manual: AboutSettings shows 6 new LinkedIn config inputs, save works
- [ ] Manual: Calendar shows platform chips per cell
- [ ] Manual: Draft detail shows "Scheduled for 13 May · 05:00 WIB" copy
- [ ] Manual: Postponed draft shows amber chip
- [ ] No console errors

---

### Phase 7 (P7): CLAUDE.md + Cron Rename/Retire + Telegram Wire + Ops Doc

**Estimated time:** 45 minutes total

**Files:**
- Modify: `CLAUDE.md` (root) — update LinkedIn pipeline section + Last Updated entry
- Modify: `backend/routes/console.php` (retire `linkedin:process-scheduled`, registered solely via `ScheduledCommandSeeder` now)
- Delete: `backend/app/Console/Commands/ProcessScheduledLinkedInPosts.php` (replaced by `PublishSlotOrchestrator`)
- Modify: `backend/app/Services/TelegramNotificationService.php` (3 new event handlers)
- Modify: `backend/database/seeders/ScheduledCommandSeeder.php` (remove `linkedin:process-scheduled` row OR mark deleted)
- Create: `docs/runbooks/linkedin-fixed-slots-ops.md` (operator runbook)

**Steps:**
1. Verify all prior-phase tests still pass: `php artisan test --filter LinkedIn`. Capture baseline.
2. Delete `ProcessScheduledLinkedInPosts.php` (functionality moved to `PublishSlotOrchestrator`). Update any imports.
3. Remove `linkedin:process-scheduled` row from `ScheduledCommandSeeder`. Re-seed: `php artisan db:seed --class=ScheduledCommandSeeder`. Verify row removed.
4. Run full test suite: `php artisan test --filter LinkedIn`, confirm 0 regressions.
5. Add 3 new methods to `TelegramNotificationService`:
   - `sendLinkedInSlotPostponed(LinkedInPost $draft, array $blockers): void`
   - `sendLinkedInSiblingsDropped(LinkedInPost $draft, array $platforms): void`
   - `sendLinkedInNoAvailableSlot(int $lookaheadDays): void`
   Each reads its toggle from settings, formats payload, sends via existing Telegram Bot API wrapper.
6. Wire calls from `PublishSlotOrchestrator` (replace TODO comments from P5 with real method calls).
7. Re-run P5 feature tests, confirm Telegram notification assertions now pass with real method names.
8. Write operator runbook `docs/runbooks/linkedin-fixed-slots-ops.md`:
   - How to change `linkedin_publish_slots` setting (via admin UI vs tinker)
   - How to manually reschedule a stuck draft
   - How to bypass governor for one-off text post (Regenerate with `force_format=text`)
   - How to monitor postpone counts (`SELECT id, status, JSON_LENGTH(JSON_EXTRACT(pipeline_state_log, '$[*].reason')) FROM linkedin_posts WHERE ...`)
   - How to retire backfill artisan after first run
   - Troubleshooting: "all drafts stuck at awaiting_publish, none firing" → check `linkedin_auto_publish` setting + Admin Scheduler tab for `social:publish-slot` enabled
9. Update root `CLAUDE.md` LinkedIn pipeline section:
   - Add `LinkedInFixedSlotScheduler` service entry
   - Add `LinkedInFormatMixGovernor` service entry
   - Add `LinkedInSlotReadinessService` service entry
   - Replace `ProcessScheduledLinkedInPosts` entry with `PublishSlotOrchestrator`
   - Update settings group table with new keys
   - Update `posting_time_rules` section noting it's now visualization-only
   - Update "Last Updated" footer entry with summary of this 7-phase ship
10. Update root `CLAUDE.md` "VPS Background Process Setup" section if needed (no infra change, but `social:publish-slot` is the new operative cron — mention it).
11. Commit: `docs(linkedin): update CLAUDE.md + ops runbook for fixed-slot publishing system`

**Verification:**
- [ ] Full LinkedIn test suite passes: `php artisan test --filter LinkedIn`
- [ ] `php artisan schedule:list` shows `social:publish-slot` cron, does NOT show `linkedin:process-scheduled`
- [ ] `routes/console.php` has no references to deleted command
- [ ] `docs/runbooks/linkedin-fixed-slots-ops.md` covers 5 operator scenarios
- [ ] CLAUDE.md "LinkedIn pipeline" section reflects all 5 new services
- [ ] CLAUDE.md "Last Updated" entry summarizes 7-phase ship
- [ ] `php artisan tinker --execute="app(TelegramNotificationService::class)->sendLinkedInSlotPostponed(LinkedInPost::find(1), [])"` doesn't throw

---

### Cross-Phase Verification (Pre-merge)

Run before final commit / PR:

```bash
# Backend full suite
php artisan test --filter LinkedIn  # all LinkedIn-tagged tests
php artisan test --filter Publer    # Publer-tagged tests

# PHP syntax across modified files
for f in $(git diff --name-only main..HEAD | grep "\.php$"); do
  php -l "$f"
done

# Frontend build
cd frontend && npm run build

# Helper tests
node frontend/src/views/admin/linkedinHelpers.test.mjs

# Routes audit
php artisan route:list | grep linkedin

# Schedule audit
php artisan schedule:list | grep -E "social|linkedin"

# Settings count check
php artisan tinker --execute="echo Setting::where('group','linkedin')->count();"  # >= 15
php artisan tinker --execute="echo Setting::where('group','telegram')->count();"  # >= 14

# Migrations applied
php artisan migrate:status | grep "2026_05_13_000001"  # cross-post publer_post_id

# CLAUDE.md sync
grep -c "LinkedInFixedSlotScheduler" CLAUDE.md  # >= 1
grep -c "LinkedInFormatMixGovernor" CLAUDE.md  # >= 1
grep -c "PublishSlotOrchestrator" CLAUDE.md  # >= 1
```

### Risk Mitigation per Phase

| Phase | Risk | Pre-execution mitigation |
|---|---|---|
| P1 | Approve flow breaks for in-flight drafts | Test against fresh test DB, NOT production. Verify `cancel_window_ends_at` semantic preserved (still allows cancel). |
| P2 | Plugin v0.6.0 ignores `format_preference`, governor stuck redispatching forever | Cap re-dispatch at 1 per draft. If 2nd dispatch also emits text, log `plugin_refused_format_override` and accept. |
| P3 | Plugin v0.7.0 deploy fails on VPS | Operator manual rollback: `git checkout v0.6.0 + npm run compile-refs + systemctl restart`. Backend governor degrades gracefully. |
| P4 | Real Publer API quota exhausted during tests | Use `Http::fake()` for all unit tests. End-to-end smoke test runs ONCE manually post-deploy. |
| P5 | `social:publish-slot` races with `linkedin:process-scheduled` during transition | `withoutOverlapping(5)` on both. DB-level `status='awaiting_publish' → 'published'` flip is atomic per draft. |
| P6 | Admin UI breaks existing AboutSettings save flow | Add new fields as separate handler block; existing handler unchanged. |
| P7 | Deleted `ProcessScheduledLinkedInPosts.php` referenced from somewhere | Grep before delete: `grep -r "ProcessScheduledLinkedInPosts" backend/`. Should only match the file itself. |

### Phase Dependency Graph (for /gaspol-parallel)

```
P1 ─┬─ P2 ─┬─ P3 (plugin work, sequential)
    │      │
    │      └─ P4 (PublishViaPubler, independent of plugin)
    │             │
    │             └─ P5 (atomic orchestrator, needs P1+P2+P4)
    │
    └─ P6 (admin UI, needs P1+P2+P5 settings/orchestrator) ─ P7 (docs, last)
```

**Parallel candidates:**
- P3 + P4 can run in parallel (plugin work in separate repo, PublishViaPubler in backend)
- P6 sub-tasks: AboutSettings card / Calendar overlay / Detail page can run as 3 parallel mini-tasks within P6

**Sequential gates:**
- P5 BLOCKED until P1 (scheduler), P2 (governor), P4 (Publer) all green
- P7 BLOCKED until all prior phases green (it documents the final state)

---

## Execution Handoff

**Option 1: Execute in this session**
> Use `/gaspol-execute` to ship P1 with per-phase checkpoints. P1 alone solves the urgent scheduling pain. Plan rest in next session if scope feels too big.

**Option 2: Parallel execution**
> Use `/gaspol-parallel` mode=plan-phases targeting P3+P4 once P1+P2 are green. Plugin and Publer work are independent.

**Option 3: Separate session**
> All 7 phases ship as one cohesive bundle in a dedicated multi-hour session. Total estimated 8-10 hours of execution + verification.
