# Video Carousel → Content Calendar (LinkedIn-tab anchor) + Social Studio hand-off

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

## Goal

An IG `video_rebrand` repurpose job currently dead-ends in Social Studio forever — it never
hands off and never appears in any calendar (`finalizeVideoRebrand` sets neither
`content_idea_id` nor `linkedin_post_id`). This plan makes a finalized video carousel **enter
the Content Calendar (LinkedIn tab) via a `LinkedInPost` anchor** and **immediately disappear
from Social Studio** once it does — while ensuring it is **NEVER published to LinkedIn** (it
publishes to Instagram + Threads via the existing Zernio path). The anchor `LinkedInPost` is a
**display/tracking mirror**, not a LinkedIn publish target.

User decisions (locked):
- Publish target: **IG + Threads only via Zernio** (no LinkedIn publish, no LinkedIn caption).
- Calendar placement: **LinkedIn tab via a `LinkedInPost` anchor** (chosen over the Instagram-sibling
  option despite the semantic oddity — see ADR note).

## Architecture Context (from CLAUDE.md + code)

- **video_rebrand pipeline:** `GenerateRebrandAssets` → `PollRebrandAssets` → `ComposeToolSlides` →
  `FinalizeRepurpose::finalizeVideoRebrand()` ([backend/app/Jobs/FinalizeRepurpose.php:315](backend/app/Jobs/FinalizeRepurpose.php#L315)).
  Output lives in `repurpose_video_slides`; publish state in `repurpose_jobs.zernio_publish` JSON.
- **Existing Zernio publish:** `RepurposeJobController::publishZernio` (`POST /admin/repurpose/{id}/publish-zernio`)
  → `PublishRepurposeViaZernio` job (IG + Threads, `scheduledFor` or `publishNow`) →
  `ZernioPayloadBuilder::buildRepurposeVideoCarousel`. UI in `RepurposeJobDetail.vue` via
  `usePublishRepurposeZernio()`. **This path is NOT changed** — it stays the publish mechanism.
- **LinkedInPost FSM** ([app/Enums/LinkedInPostStatus.php](backend/app/Enums/LinkedInPostStatus.php)):
  `feedStatuses = [awaiting_publish, published, cancelled]`, `queueStatuses = [pending_generation,
  generating, validating, manual_review, failed]`. `format` currently `text|carousel`.
- **Dangerous LinkedIn publishers (the guard surface)** — each selects by status:
  - `PublishSlotOrchestrator` (`social:publish-slot`, every minute) → `status=awaiting_publish` +
    cancel-window passed → **publishes**. [app/Console/Commands/PublishSlotOrchestrator.php:64](backend/app/Console/Commands/PublishSlotOrchestrator.php#L64)
  - `AutoScheduleManualReviewLinkedInPosts` (`linkedin:auto-schedule`, daily) → `status=manual_review`
    → promotes to `awaiting_publish`. [app/Console/Commands/AutoScheduleManualReviewLinkedInPosts.php:200](backend/app/Console/Commands/AutoScheduleManualReviewLinkedInPosts.php#L200)
  - `PromptScheduleReadyDrafts` (`linkedin:prompt-schedule`) → `status=manual_review` → Telegram
    schedule prompt. [app/Console/Commands/PromptScheduleReadyDrafts.php:57](backend/app/Console/Commands/PromptScheduleReadyDrafts.php#L57)
  - `GenerateLinkedInPost` → acts on `pending_generation|failed|cancelled`. [app/Jobs/GenerateLinkedInPost.php:70](backend/app/Jobs/GenerateLinkedInPost.php#L70)
- **Calendar source:** LinkedIn tab reads `LinkedInPost` rows; calendar pin = `published_at ?? scheduled_at`
  (a row needs `scheduled_at` or `published_at` to land on a grid date). `useSocialCalendar` →
  `/admin/linkedin-posts/calendar`. List/format validation is `Rule::in(['text','carousel'])`
  ([LinkedInDraftController.php:58](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php#L58)).
- **Cross-post scan** `ScanLinkedInForCrossPost` gates on `format='carousel'` + all `carousel_slides[].image_status='done'`
  → a `video_carousel` row (no `carousel_slides`) is naturally ignored; we add a test to lock this.
- **Social Studio list:** `RepurposeJobController::index` with `?exclude_settled=1`
  ([app/Http/Controllers/Api/Admin/RepurposeJobController.php:60](backend/app/Http/Controllers/Api/Admin/RepurposeJobController.php#L60)).
  Today it hides blog jobs via `whereNull('content_idea_id')`; video jobs (no `linkedin_post_id`,
  no `content_idea_id`) never settle. SocialStudio.vue passes `exclude_settled: 1`.

## Tech Stack

Laravel 12 (PHP 8.2), Pest/PHPUnit (`docker exec portfolio_backend php artisan test --filter=...`),
Vue 3.5 + TanStack Query. No new packages. Reuse existing FSM trait (`HasStatusTransitions`),
`PublisherResolver`/Zernio path, and the per-platform calendar.

## Design: the `video_carousel` anchor lifecycle

```
finalizeVideoRebrand
  └─ create LinkedInPost(format='video_carousel', status='manual_review', content=caption)
     set repurpose_jobs.linkedin_post_id   →  job LEAVES Social Studio (Phase D)
     (NO GenerateLinkedInPost dispatch)        appears in LinkedIn QUEUE tab
operator schedules via Zernio (existing publishZernio + scheduled_at, from repurpose detail)
  └─ mirror onto anchor: status='awaiting_publish', scheduled_at=<slot>  → lands on CALENDAR grid
Zernio publishes (all target platforms done)
  └─ mirror onto anchor: status='published', published_at=now            → calendar shows shipped
```

The anchor is guarded out of EVERY LinkedIn publisher by a single predicate `format='video_carousel'`,
so `awaiting_publish`/`manual_review` are safe statuses for it. The Zernio path is the only real
publisher. Publish/schedule UI stays on the repurpose detail; the calendar/queue card for a
`video_carousel` row **deep-links to `/admin/repurpose/{jobId}`**.

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---------|-------------|----------|---------|--------|
| Video anchor row | `linkedin_posts` (format=`video_carousel`) | `LinkedInPost::create` in `finalizeVideoRebrand` | No (new branch) | Create real row, no GenerateLinkedInPost |
| Guard predicate | `LinkedInPost` scope/const | `LinkedInPost::isVideoCarousel()` + query scope | No | Create reusable scope + const |
| Publish guards | 3 cron selectors + 1 job | existing `where(...)` queries | Yes (queries) | Add `format != video_carousel` clause |
| Social Studio hide | `repurpose_jobs.linkedin_post_id` | `RepurposeJobController::index` exclude_settled | Yes (filter) | Add mode-aware clause |
| Schedule mirror | `repurpose_jobs.zernio_publish` → anchor | `publishZernio` + `PublishRepurposeViaZernio` | Yes (write sites) | Mirror scheduled_at/published_at onto anchor |
| Calendar deep-link | `LinkedInPost` → RepurposeJob | reverse relation `repurposeJob` + serialized `repurpose_job_id` | No | Add relation + serialize for video rows |
| Calendar/list format filter | `LinkedInDraftController` validation | `Rule::in([...])` | Yes | Widen to include `video_carousel` |
| Zernio publish path | `PublishRepurposeViaZernio` | unchanged | Yes | **Reuse as-is** |

## Phases

### Phase A: `video_carousel` format constant + reusable guard predicate

**Estimated time:** 8 min

**Files:**
- Modify: `backend/app/Models/LinkedInPost.php`
- Test: `backend/tests/Unit/LinkedInPostVideoCarouselTest.php`

**Steps:**
1. Write failing test `test_is_video_carousel_and_scope_exclude` asserting `LinkedInPost::factory()->create(['format'=>'video_carousel'])->isVideoCarousel()===true`, a `text` row is `false`, and `LinkedInPost::query()->excludeVideoCarousel()->pluck('id')` omits the video row. Expected error: `BadMethodCallException: Call to undefined method ...isVideoCarousel()`.
2. Run it, confirm it fails for that reason.
3. Add `public const FORMAT_VIDEO_CAROUSEL = 'video_carousel';`, `public function isVideoCarousel(): bool { return $this->format === self::FORMAT_VIDEO_CAROUSEL; }`, and `public function scopeExcludeVideoCarousel(Builder $q): Builder { return $q->where('format','!=',self::FORMAT_VIDEO_CAROUSEL); }` (handles NULL-safe: video rows always have the explicit value, others differ).
4. Run tests, confirm pass.
5. Commit: `feat(linkedin): add video_carousel format constant + excludeVideoCarousel scope`.

**Verification:**
- [ ] `docker exec portfolio_backend php artisan test --filter=LinkedInPostVideoCarouselTest` passes
- [ ] `isVideoCarousel()` + `scopeExcludeVideoCarousel()` exist and behave
- [ ] No placeholder/TODO comments

### Phase B: Guard every LinkedIn publisher against `video_carousel`

**Estimated time:** 14 min

**Files:**
- Modify: `backend/app/Console/Commands/PublishSlotOrchestrator.php`, `AutoScheduleManualReviewLinkedInPosts.php`, `PromptScheduleReadyDrafts.php`, `backend/app/Jobs/GenerateLinkedInPost.php`
- Test: `backend/tests/Feature/VideoCarouselPublishGuardTest.php`

**Steps:**
1. Write failing test `test_video_carousel_never_selected_by_publishers`: seed a `video_carousel` LinkedInPost in `awaiting_publish` (cancel_window passed) AND one in `manual_review`; run `PublishSlotOrchestrator`, `AutoScheduleManualReviewLinkedInPosts`, `PromptScheduleReadyDrafts` (each `--dry-run` where supported, or assert no transition/no Zernio LinkedIn publish call) and assert the video row's status is UNCHANGED and `LinkedInPublishService` is never invoked for it. Expected error: assertion fails (row got promoted/published) before guards exist.
2. Run it, confirm it fails (a publisher acts on the row).
3. Add `->excludeVideoCarousel()` to each selector query (PublishSlotOrchestrator:64, AutoSchedule:200, PromptSchedule:57). In `GenerateLinkedInPost::handle`, add an early defensive bail: if `$draft->isVideoCarousel()` log + return (never dispatched, but defends a future caller).
4. Run tests, confirm pass.
5. Commit: `fix(linkedin): exclude video_carousel anchors from all LinkedIn publishers`.

**Verification:**
- [ ] Guard test passes; the `text`/`carousel` rows in the same test still get selected (no over-filtering)
- [ ] All 4 publishers skip `video_carousel`
- [ ] `php artisan test --filter=PublishSlot` + existing publisher suites still green (no regression)
- [ ] Security/correctness: no path can call `LinkedInPublishService::publish*` on a `video_carousel` row

### Phase C: `finalizeVideoRebrand` creates the anchor (no LinkedIn generation)

**Estimated time:** 12 min

**Files:**
- Modify: `backend/app/Jobs/FinalizeRepurpose.php` (`finalizeVideoRebrand`)
- Test: `backend/tests/Feature/FinalizeRepurposeVideoTest.php` (extend existing)

**Steps:**
1. Write failing test `test_video_finalize_creates_video_carousel_anchor`: a `composed` video job with ≥1 `done` slide; after `FinalizeRepurpose`, assert (a) `repurpose_jobs.linkedin_post_id` is set, (b) a `LinkedInPost` exists with `format='video_carousel'`, `status='manual_review'`, `content` = the built caption, (c) `Queue::assertNotPushed(GenerateLinkedInPost::class)`, (d) job is still `drafted` and caption is still stored in `rewritten['caption']`. Expected error: `linkedin_post_id` is null.
2. Run it, confirm it fails.
3. In `finalizeVideoRebrand`, after building `$caption`, create the anchor inside a transaction: `LinkedInPost::create(['post_id'=>null,'format'=>'video_carousel','content'=>$caption,'hashtags'=>[],'status'=>'manual_review'])`; pass `linkedin_post_id` in the existing `transitionTo(Drafted, 'finalize_video', [...])` extra array. Do NOT dispatch `GenerateLinkedInPost`.
4. Run tests, confirm pass. Verify `LinkedInPost` `post_id` nullable (it is — repurpose carousels also create anchored posts, but here we have no Post; confirm the column/relation tolerate null, else create a minimal anchor Post like `finalizeCarousel` does — STOP and ask if NOT NULL).
5. Commit: `feat(repurpose): video_rebrand finalize creates a video_carousel calendar anchor`.

**Verification:**
- [ ] Test passes; no `GenerateLinkedInPost` queued
- [ ] `linkedin_post_id` set, job leaves `Social Studio` once Phase D lands
- [ ] Existing `FinalizeRepurposeVideoTest` + carousel/blog finalize tests still green
- [ ] No placeholder/TODO

### Phase D: Social Studio hides video jobs once anchored

**Estimated time:** 8 min

**Files:**
- Modify: `backend/app/Http/Controllers/Api/Admin/RepurposeJobController.php` (`index` exclude_settled)
- Test: `backend/tests/Feature/RepurposeJobListExcludeSettledTest.php` (extend)

**Steps:**
1. Write failing test `test_exclude_settled_hides_video_rebrand_once_anchored`: a `video_rebrand` job with `linkedin_post_id` set is ABSENT from `GET /admin/repurpose?exclude_settled=1`, while a video job with `linkedin_post_id=null` is PRESENT. Expected error: the anchored job still appears.
2. Run it, confirm fail.
3. In the `exclude_settled` branch, add a mode-aware clause: a `video_rebrand` job is settled once `linkedin_post_id` is not null. Keep carousel behavior intact (carousel stays visible while its LinkedInPost is in queue statuses). E.g. wrap existing predicate so video rows additionally require `whereNull('linkedin_post_id')`.
4. Run tests, confirm pass.
5. Commit: `feat(social-studio): hide video_rebrand jobs once they enter the LinkedIn calendar`.

**Verification:**
- [ ] Test passes; carousel + blog exclude_settled behavior unchanged (run full `RepurposeJobListExcludeSettledTest`)
- [ ] Anchored video job disappears from Social Studio; un-anchored stays
- [ ] No placeholder/TODO

### Phase E: Mirror Zernio schedule/publish state onto the anchor

**Estimated time:** 14 min

**Files:**
- Modify: `backend/app/Http/Controllers/Api/Admin/RepurposeJobController.php` (`publishZernio`), `backend/app/Jobs/PublishRepurposeViaZernio.php` (`markPublished`/scheduling)
- Test: `backend/tests/Feature/VideoCarouselAnchorMirrorTest.php`

**Steps:**
1. Write failing test `test_schedule_then_publish_mirrors_onto_anchor`: anchored video job; call `publishZernio` with a future `scheduled_at` → assert anchor `status='awaiting_publish'` + `scheduled_at` == that instant. Then simulate all target platforms reaching `zernio_publish[*].status='published'` via `PublishRepurposeViaZernio::markPublished` → assert anchor `status='published'` + `published_at` set. Expected error: anchor unchanged after schedule.
2. Run it, confirm fail.
3. In `publishZernio`, after dispatching, if `$job->linkedin_post_id` and a future `scheduled_at`, mirror onto the anchor via `HasStatusTransitions`/`update` (`awaiting_publish` + `scheduled_at`; `publishNow` → `awaiting_publish` + `scheduled_at=now`). In `PublishRepurposeViaZernio::markPublished`, after writing `zernio_publish`, if ALL dispatched target platforms are `published`, mirror anchor → `published` + `published_at=now` (row-locked, idempotent). Use the FSM `transitionTo` with reason `zernio_mirror`; ensure `manual_review → awaiting_publish` and `awaiting_publish → published` are legal edges (they are in the standard FSM).
4. Run tests, confirm pass.
5. Commit: `feat(repurpose): mirror Zernio schedule/publish state onto the video calendar anchor`.

**Verification:**
- [ ] Test passes; anchor lands on the calendar grid date after scheduling and flips to published when shipped
- [ ] Mirror is idempotent (re-running markPublished doesn't double-transition / throw)
- [ ] Partial publish (IG done, Threads pending) does NOT prematurely mark anchor published
- [ ] No placeholder/TODO

### Phase F: Calendar/queue display + deep-link to repurpose detail

**Estimated time:** 14 min

**Files:**
- Modify: `backend/app/Models/LinkedInPost.php` (reverse relation), `backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php` (widen `format` validation + serialize `repurpose_job_id` for video rows), `frontend/src/views/admin/LinkedInPostsCalendar.vue` + `LinkedInQueueList.vue` (deep-link + video badge)
- Test: `backend/tests/Feature/VideoCarouselCalendarSerializationTest.php`; FE: extend `LinkedInPostsCalendar.spec.js`

**Steps:**
1. Write failing backend test `test_calendar_row_exposes_repurpose_job_id_for_video`: scheduled `video_carousel` anchor linked from a RepurposeJob appears in `GET /admin/linkedin-posts/calendar` for its date with `format='video_carousel'` and `repurpose_job_id=<jobId>`. Expected error: `repurpose_job_id` absent / row filtered out by format validation.
2. Run it, confirm fail.
3. Add `LinkedInPost::repurposeJob()` (`hasOne(RepurposeJob::class, 'linkedin_post_id')`). Widen `Rule::in(['text','carousel'])` → include `'video_carousel'` at [LinkedInDraftController.php:58](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php#L58) and any calendar method's equivalent. In the calendar/list row serializer, when `format==='video_carousel'`, include `repurpose_job_id` = `repurposeJob?->id`.
4. Run backend test, confirm pass.
5. FE: in calendar + queue card, when `row.format === 'video_carousel'`, render a "🎬 Video (IG·Threads)" badge and make the card link to `/admin/repurpose/${row.repurpose_job_id}` instead of the LinkedIn draft detail. Update `LinkedInPostsCalendar.spec.js` to assert the deep-link target for a video row.
6. Run FE test (`npm run test` scoped), confirm pass.
7. Commit: `feat(calendar): show video_carousel anchors with a video badge + deep-link to repurpose detail`.

**Verification:**
- [ ] Backend serialization test passes; `repurpose_job_id` present on video rows only
- [ ] Calendar/queue render the video badge and deep-link to the repurpose detail (where Zernio publish/schedule lives)
- [ ] Non-video rows unchanged (regression: existing calendar spec green)
- [ ] `tsc`/lint or `npm run build` clean

### Phase G: Lock cross-post-scan isolation + full regression

**Estimated time:** 6 min

**Files:**
- Test: `backend/tests/Feature/VideoCarouselNotCrossPostScannedTest.php`

**Steps:**
1. Write test `test_scan_ignores_video_carousel`: a `video_carousel` anchor (no `carousel_slides`) is NOT turned into IG/TikTok/Threads siblings by `ScanLinkedInForCrossPost`. Expected: `InstagramPost`/`TiktokPost`/`ThreadsPost` count stays 0.
2. Run it — it should PASS immediately (scan gates on `format='carousel'`). If it FAILS, add `->excludeVideoCarousel()` / explicit `format='carousel'` gate to the scan's eligibility query and re-run.
3. Run the full repurpose + linkedin feature suites: `docker exec portfolio_backend php artisan test --filter='Repurpose|LinkedIn|PublishSlot|Zernio'`.
4. Commit: `test(repurpose): lock video_carousel isolation from cross-post scan`.

**Verification:**
- [ ] Scan-isolation test passes
- [ ] Full `Repurpose|LinkedIn|PublishSlot|Zernio` suite green (note pre-existing legacy fails per memory `laravel-docker-phpunit-mount-flaky` — scope to these filters)
- [ ] Run `graphify update .` to refresh the local graph

## ADR note (captured for the record)

- **Chosen:** LinkedIn-tab anchor (`LinkedInPost` format=`video_carousel`). User-selected over the
  Instagram-sibling option. Trade-off: a "LinkedIn" row that is really an IG/Threads post → requires
  the Phase B guard surface (3 crons + 1 job). Accepted for calendar-tab familiarity.
- **Rejected:** Instagram-sibling (`InstagramPost`) anchor — would appear in the Instagram tab where it
  actually publishes and need zero LinkedIn guards, but the user wants the LinkedIn tab.
- **Open follow-up (not in scope):** whether finalize should auto-schedule the anchor onto a slot so it
  lands on the calendar grid immediately (v1: operator schedules via the repurpose detail Zernio action,
  which then mirrors `scheduled_at` onto the anchor — Phase E). Revisit if operators want zero-click calendar placement.

## Execution Handoff

- **Option 1 — Execute now:** Start Phase A with gaspol-execute (per-phase checkpoints, TDD hard gate).
- **Option 2 — Parallel:** Phases are mostly sequential (B depends on A; C–F build on each other).
  Phase G can run after C. Not a strong parallel candidate; prefer sequential gaspol-execute.
- **Option 3 — Separate session:** This file has everything needed to resume.
