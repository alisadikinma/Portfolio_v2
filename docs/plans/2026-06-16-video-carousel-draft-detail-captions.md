> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations (existing Zernio publish
> endpoint, repurpose job caption storage, LinkedIn draft detail). During
> execution, NEVER substitute placeholders for real data sources without
> explicit user approval. If a data source doesn't exist yet, STOP and ask.

## Goal

Make a `video_carousel` anchor a first-class sosmed draft: clicking it on the
Content Calendar opens the `/sosmed-drafts/{anchor}` LinkedIn draft detail (not
the Social Studio `/repurpose/{job}` page), and that draft detail becomes the
single management home for the post — video preview, **separate editable
Instagram + Threads captions**, and the Approve & Publish (IG + Threads) +
Schedule-for-later actions. Social Studio (`/repurpose/{job}`) stays for video
production (re-render / re-skin / regenerate). Fixes two reported bugs on job 26
/ anchor 167 plus a latent caption inconsistency.

## Background — current (broken) state

- **Bug 1 (route):** [`detailTarget`](frontend/src/views/admin/linkedinHelpers.js#L567)
  routes `format==='video_carousel'` → `admin-repurpose-detail`. The calendar
  card therefore opens Social Studio. [`LinkedInDraftDetail.vue`](frontend/src/views/admin/LinkedInDraftDetail.vue)
  only renders `format==='text'|'carousel'` — a `video_carousel` anchor would
  render mostly empty, so the route swap needs a new view branch.
- **Bug 2 (captions):** the anchor's `content` is the branded
  `rewritten['caption']` (tool list + Follow/Save/Comment ask), but
  [`PublishRepurposeViaZernio`](backend/app/Jobs/PublishRepurposeViaZernio.php#L89)
  publishes with `buildRepurposeVideoCarousel($job, $platform)` → no caption arg
  → [`igCaption()`](backend/app/Models/RepurposeJob.php) = the RAW source IG
  caption (`extracted['caption']`). So what the operator sees ≠ what ships, and
  there are zero per-platform caption fields.

## Architecture Context (from CLAUDE.md)

- video_rebrand publishes to **IG + Threads via Zernio** (`PublishRepurposeViaZernio`,
  per-platform state in `repurpose_jobs.zernio_publish` JSON). NO LinkedIn output,
  NO IG/Threads sibling rows — captions live on the repurpose job, not on
  `instagram_posts`/`threads_posts`.
- Publish/schedule entry point already exists: `POST /admin/repurpose/{id}/publish-zernio`
  ([`RepurposeJobController::publishZernio`](backend/app/Http/Controllers/Api/Admin/RepurposeJobController.php#L371))
  — validates `platforms[] in:instagram,threads` + future `scheduled_at`, mirrors
  onto the calendar anchor via `mirrorAnchorScheduled`.
- The anchor (LinkedInPost, `format='video_carousel'`) is display-only; all 4
  LinkedIn publishers are guarded by `scopeExcludeVideoCarousel`.
- Draft detail data comes from [`LinkedInDraftController::show`](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php).
- `RepurposeJob` already has `compositedVideoUrls()`, `igCaption()`,
  `zernioPublishState($platform)`, `videoAnchor()`.
- Frontend repurpose API: [`useRepurposeJobs.js`](frontend/src/composables/useRepurposeJobs.js)
  (`usePublishRepurposeZernio` exists). LinkedIn draft API:
  [`useLinkedInDrafts.js`](frontend/src/composables/useLinkedInDrafts.js).

## Tech Stack

Laravel 12 + MySQL (JSON columns) + Sanctum; Vue 3.5 `<script setup>` +
TanStack Query + Vite. Tests: PHPUnit (Docker `portfolio_backend`), vitest.

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Per-platform caption storage | `repurpose_jobs.rewritten.caption_instagram` / `.caption_threads` (JSON keys, no migration) | `RepurposeJob::captionFor($platform)` | No | Create resolver + writer |
| Caption default at finalize | `buildVideoCaption()` (branded) | `FinalizeRepurpose` | Yes (single) | Seed both keys (Threads pre-capped 500) |
| Publish uses per-platform caption | `captionFor($platform)` | `PublishRepurposeViaZernio` → `buildRepurposeVideoCarousel($job,$platform,$caption)` | Partial (arg supported, unused) | Pass the resolved caption |
| Caption edit API | `PUT /admin/repurpose/{id}/captions` | `RepurposeJobController::updateCaptions` | No | Create endpoint |
| Draft-detail video data | `show()` `repurpose` block: id, composited_videos[], zernio_publish, caption_instagram, caption_threads | `LinkedInDraftController::show` | No (only id) | Enrich for video_carousel |
| Publish/Schedule from draft detail | `POST /admin/repurpose/{job}/publish-zernio` | existing | Yes | Frontend calls via repurpose_job_id |
| Calendar route | `detailTarget()` | frontend pure fn | Yes (wrong target) | Swap to `admin-sosmed-draft-detail` |
| Draft detail video view | `draft.repurpose` block | `LinkedInDraftDetail.vue` new branch | No | Build video_carousel branch |
| Backfill job 26 captions | `captionFor` seed on existing rows | artisan one-shot | No | Backfill command/tinker |

## Phases

### Phase A — Per-platform caption storage + resolver (backend)
**Files:** Create test `tests/Unit/RepurposeJobCaptionForTest.php`; modify
[`RepurposeJob`](backend/app/Models/RepurposeJob.php).
**Steps:**
1. Write failing test for `RepurposeJob::captionFor('instagram')` /
   `captionFor('threads')`. Expected error: `Error: Call to undefined method
   App\Models\RepurposeJob::captionFor()`.
2. Run it, confirm it fails for that reason.
3. Implement `captionFor(string $platform): string` resolving
   `rewritten["caption_$platform"]` → `rewritten['caption']` → `igCaption()`
   (always non-empty). Add `setCaption(string $platform, string $text)` helper
   that merges into `rewritten` and saves (Threads trimmed to 500).
4. Run tests, confirm pass.
5. Commit: "feat(repurpose): per-platform caption resolver on RepurposeJob".
**Verification:**
- [ ] `captionFor` falls back source→branded→igCaption; unknown platform → branded/igCaption
- [ ] `setCaption('threads', >500 chars)` stores ≤500
- [ ] No placeholder/TODO in new code; suite green

### Phase B — Seed both captions at finalize (backend)
**Files:** modify [`FinalizeRepurpose`](backend/app/Jobs/FinalizeRepurpose.php);
test `tests/Feature/FinalizeRepurposeVideoCaptionsTest.php`.
**Steps:**
1. Write failing test: after `finalizeVideoRebrand`, `rewritten['caption_instagram']`
   and `rewritten['caption_threads']` are set (Threads ≤500). Expected fail:
   asserting the keys exist returns null.
2. Run, confirm fail.
3. In `finalizeVideoRebrand`, after building `$caption`, seed
   `caption_instagram = $caption`, `caption_threads = Str::limit($caption,500,'')`
   into the `rewritten` merge passed to `transitionTo`.
4. Run tests, confirm pass (existing FinalizeRepurposeVideo tests still green).
5. Commit.
**Verification:**
- [ ] Both keys present post-finalize; Threads ≤500; IG == branded caption
- [ ] Existing finalize-video tests unaffected

### Phase C — Wire Zernio publish to per-platform caption (backend)
**Files:** modify [`PublishRepurposeViaZernio`](backend/app/Jobs/PublishRepurposeViaZernio.php);
test `tests/Feature/PublishRepurposeViaZernioCaptionTest.php`.
**Steps:**
1. Write failing test: dispatching the job for `threads` builds a payload whose
   `content` == `captionFor('threads')` (not `igCaption()`). Expected fail:
   content equals igCaption.
2. Run, confirm fail.
3. Change line 89 to `buildRepurposeVideoCarousel($job, $this->platform, $job->captionFor($this->platform))`.
4. Run tests, confirm pass.
5. Commit.
**Verification:**
- [ ] IG payload uses caption_instagram; Threads uses caption_threads
- [ ] Builder's existing 500-cap still applies as a safety net

### Phase D — Caption edit API + enrich draft show() (backend)
**Files:** modify [`RepurposeJobController`](backend/app/Http/Controllers/Api/Admin/RepurposeJobController.php)
(+route in `routes/api.php`), [`LinkedInDraftController::show`](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php);
tests `tests/Feature/RepurposeCaptionUpdateApiTest.php`,
`tests/Feature/VideoCarouselDraftShowEnrichmentTest.php`.
**Steps:**
1. Write failing test: `PUT /admin/repurpose/{id}/captions {instagram, threads}`
   persists both via `setCaption`; non-video_rebrand → 422; missing → 404.
   Expected fail: route not defined (405/404).
2. Run, confirm fail.
3. Add `updateCaptions(int $id, Request)` (validate `instagram` nullable string,
   `threads` nullable string; `mode==='video_rebrand'` gate) + route
   `PUT /admin/repurpose/{id}/captions`.
4. Write failing test: `show()` for a `video_carousel` anchor returns a
   `repurpose` block { id, composited_videos[], zernio_publish, caption_instagram,
   caption_threads }; non-video anchors omit it. Expected fail: key absent.
5. Enrich `show()` — when `format==='video_carousel'`, eager-load the repurpose
   job (+videoSlides) and attach the `repurpose` block.
6. Run tests, confirm pass.
7. Commit.
**Verification:**
- [ ] PUT persists both captions (Threads ≤500), gated to video_rebrand
- [ ] show() emits `repurpose` block only for video_carousel; composited_videos are public MP4 URLs ordered hook→tools→cta
- [ ] Auth required (auth:sanctum)

### Phase E — Frontend route swap + detailTarget test
**Files:** modify [`linkedinHelpers.js`](frontend/src/views/admin/linkedinHelpers.js);
modify [`detailTarget.spec.mjs`](frontend/src/views/admin/detailTarget.spec.mjs).
**Steps:**
1. Update the spec: `video_carousel` item now expects
   `{ name: 'admin-sosmed-draft-detail', params: { id: item.id } }`. Run, confirm fail.
2. Change `detailTarget` — drop the `video_carousel`→repurpose branch so video
   anchors fall through to the `admin-sosmed-draft-detail` target (LinkedIn).
3. Run vitest, confirm pass.
4. Commit.
**Verification:**
- [ ] video_carousel → admin-sosmed-draft-detail; normal carousel/text/cross-post unchanged

### Phase F — Draft-detail video_carousel view (frontend)
**Files:** modify [`LinkedInDraftDetail.vue`](frontend/src/views/admin/LinkedInDraftDetail.vue);
[`useLinkedInDrafts.js`](frontend/src/composables/useLinkedInDrafts.js)
(+`useUpdateRepurposeCaptions`, reuse `usePublishRepurposeZernio` from
useRepurposeJobs).
**Steps:**
1. Write a small pure-helper test if any logic is extractable (e.g.
   `videoCaptionDirty(a,b)`); else rely on the manual verification gate.
2. Add a `v-if="draft.format === 'video_carousel'"` branch rendering:
   composited video `<video>` previews (from `draft.repurpose.composited_videos`),
   two caption editors (IG + Threads, char counters; Threads 500 cap) bound to
   `draft.repurpose.caption_*` with a Save button → `PUT /repurpose/{job}/captions`,
   per-platform Zernio publish-status chips (from `draft.repurpose.zernio_publish`),
   **Approve & Publish (IG + Threads)** + **Schedule for later** buttons calling
   `POST /repurpose/{job}/publish-zernio` (reuse the modal from RepurposeJobDetail),
   and an "Open in Social Studio" link to `/admin/repurpose/{job}` for re-render.
3. Guard the existing text/carousel-only computeds so a video_carousel anchor
   doesn't trip them (e.g. platform tab logic, approval gates).
4. Run vitest + manual smoke; confirm no console errors.
5. Commit.
**Verification:**
- [ ] Clicking a video_carousel calendar card opens the draft detail (not Studio)
- [ ] IG + Threads captions render, edit, save, and round-trip
- [ ] Approve/Schedule from the draft detail hit publish-zernio and reflect status
- [ ] Existing text/carousel drafts render unchanged (no regression in computeds)

### Phase G — Backfill job 26 + deploy
**Steps:**
1. Push (CI/CD deploy) after user approval.
2. One-shot on prod: for each pre-feature video_rebrand job missing caption keys,
   `setCaption('instagram', captionFor('instagram'))` + `setCaption('threads', …)`
   so the editor + publish have seeded values (job 26, 19).
3. Verify job 26: draft detail shows captions; Zernio scheduled state intact.
**Verification:**
- [ ] Job 26 draft detail renders captions + preview + scheduled chips
- [ ] No change to job 26's existing Zernio schedule (Jun 16 11:00)

## Out of scope / anti-patterns
- ❌ NO new publish/schedule backend endpoint — reuse `/repurpose/{id}/publish-zernio`.
- ❌ NO migration — captions are JSON keys on `rewritten`.
- ❌ NO LinkedIn publish for video_carousel (platform has no video carousel).
- ❌ NO duplicate caption source — `captionFor()` is the single resolver used by
  both publish and the editor.
