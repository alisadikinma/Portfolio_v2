# Repurpose: Telegram Per-Step Progress + Admin Monitoring Panel

> Status: Design approved 2026-06-11 (gaspol-brainstorm). Implementation plan appended by gaspol-plan.

## Design

### Problem

The Telegram → Instagram repurpose pipeline (shipped June 11, see root CLAUDE.md) works end-to-end, but has two operator/audience-facing UX gaps surfaced during the first live run (RepurposeJob #2, `https://www.instagram.com/p/DZWTY4ficO1/`):

1. **Tapping a mode button (Blog/Carousel) gives no visible response.** The button handler only calls `answerCallbackQuery` — a transient toast over the button, not a chat message. The pipeline then runs silently through `capturing → extracting → researching → rewriting → finalizing` (several minutes of Claude CLI calls) and only emits a Telegram message at the terminal `drafted`/`failed` state. To the operator (and any audience watching a demo) it looks like a bug.
2. **No admin UI to monitor progress.** The feature added a `repurpose_jobs` table + 12-state FSM but no controller, route, or Vue view. Progress is only observable via direct DB/log inspection on the VPS.

### Decisions (locked via AskUserQuestion)

- **Telegram progress style:** separate message per step (NOT a single edited checklist). Rationale: reuses the existing `TelegramNotificationService::send()` path, zero new DB columns, zero `editMessageText` plumbing. Accepted trade-off: ~6 chat bubbles per job.
- **Admin panel depth:** List + Detail + Retry (mirrors the LinkedIn Queue admin surface).
- **Retry semantics:** per-step retry — re-dispatch the job appropriate to where the FSM failed (`failed → step entrypoint`), NOT a full restart from `capturing`.

### Precedent reused (from CLAUDE.md, zero research)

- **Telegram:** `TelegramNotificationService` already has `sendRepurposeModePrompt` / `sendRepurposeFailed` / `sendRepurposeDrafted` + private `send(string $text, ?array $replyMarkup)` + `signCallback`/`verifyCallback`. `send()` always POSTs a NEW `sendMessage` (no in-place edit) — which is exactly the chosen style.
- **Admin UI:** LinkedIn Queue pattern — `LinkedInDraftController` (index/show/action endpoints) + `useLinkedInDrafts.js` (TanStack Query, 30s `staleTime` + `refetchOnMount:'always'`) + List/Detail views + `AdminLayout.vue` sidebar section.
- **FSM:** `RepurposeJobStatus` (12 states) via generic `HasStatusTransitions`; `failed → any step entrypoint` already legal, so retry just re-dispatches the right job.
- **Retry precedent:** `LinkedInDraftController::regenerate` (re-dispatch a queued job after a status reset).
- Dev Mac has no PHP → backend tests authored full-fidelity, run on CI / Docker sqlite. Frontend verified locally (`npm run build` + `.mjs` smoke).

### Part A — Telegram feedback

1. **New wrapper** `TelegramNotificationService::sendRepurposeProgress(RepurposeJob $job, string $text): bool` — thin wrapper over `send()`, gated by `telegram_enabled` (same as existing repurpose methods). No new DB column.
2. **Ack on button tap** — in `TelegramWebhookController::resolveRepurposeAction()`, after setting `mode` + dispatching `CaptureInstagramPost`, send a chat bubble: `✅ Mode {Carousel|Blog} dipilih — mulai memproses…`. Keep the existing `answerCallbackQuery` toast.
3. **Four progress call-sites** — emit one `sendRepurposeProgress` at the end of each step job, BEFORE the FSM advance (so a notify failure can never block the transition; wrap in try/catch + `Log::warning`, mirroring the `maybeCascadeToPublisher` non-fatal precedent):
   - `CaptureInstagramPost` → `📸 {N} slide ke-capture`
   - `ExtractSlideContent` → `🔎 Ekstrak: {N} klaim ditemukan`
   - `ResearchRepurposeClaims` → `✅ Fact-check: {M} klaim dikoreksi`
   - `RewriteRepurposeContent` → `✍️ Artikel ditulis ulang — finalisasi…`
   - Terminal `drafted` (`sendRepurposeDrafted`) and `failed` (`sendRepurposeFailed`) already exist — no change.

### Part B — Admin `/admin/repurpose`

**Backend** — new `App\Http\Controllers\Api\Admin\RepurposeJobController`:
- `index` — paginated list, optional `?status` filter, newest first. Compact shape (id, status, mode, source_url, content_idea_id/linkedin_post_id/anchor_post_id, timestamps).
- `show` — full detail: extracted JSON (slides/caption/claims), research verdicts, rewritten payload, `pipeline_state_log[]`, linked draft IDs. Slide thumbnails served from the PRIVATE `storage/app/repurpose/{job}/` dir via a guarded read route (auth:sanctum) since they are not in `public/`.
- `retry` — for a `failed` job, transition `failed → {step entrypoint}` and re-dispatch the matching job. The "where did it fail" inference reads the last non-`failed` state in `pipeline_state_log[]` (e.g. failed-from `researching` → re-dispatch `ResearchRepurposeClaims`).
- 3–4 routes under `auth:sanctum` in the existing admin group.

**Frontend** — mirror `useLinkedInDrafts.js`:
- `composables/useRepurposeJobs.js` — `useRepurposeJobs()` (list, 30s staleTime + `refetchOnMount:'always'`, poll while any job non-terminal) + `useRepurposeJob(id)` + `useRetryRepurposeJob()` mutation.
- `views/admin/RepurposeJobsList.vue` — status filter tabs + auto-refresh table (status pill, mode, source link, relative time).
- `views/admin/RepurposeJobDetail.vue` — slide thumbnail strip, claims+verdict list, rewrite preview, state-log timeline, per-step retry button (shown only when `failed`).
- Sidebar entry "Repurpose" in `AdminLayout.vue` (near LinkedIn) + router entries.

### Data Integration Map

| Component | Data source | Existing? | Notes |
|---|---|---|---|
| Telegram progress | `RepurposeJob` + `send()` | ✅ | thin wrapper, 4 call-sites, non-fatal try/catch |
| Button-tap ack | `resolveRepurposeAction` | ✅ | +1 `send()` line |
| Admin list/detail | `repurpose_jobs` (status, *_path, extracted/research/rewritten JSON, FKs, pipeline_state_log) | ✅ | read directly |
| Slide thumbnails | `storage/app/repurpose/{job}/` (private) | ✅ | guarded auth:sanctum read route |
| Retry | FSM `failed → step` + job dispatch | ✅ | mirror `LinkedInDraftController::regenerate` |
| FE polling | TanStack Query | ✅ | `useLinkedInDrafts` pattern |

### Scope guards (YAGNI)

- NO migration required for Part A (separate-bubble style needs no `progress_message_id`).
- NO new infra — reuse `portfolio-queue.service` worker + existing routes group.
- NO per-step progress webhook to the frontend — admin polls the same `repurpose_jobs` rows.
- Slide thumbnails stay private (served through an auth-gated route), never copied to `public/`.
- Telegram notify is best-effort — never block or reverse an FSM transition.

### Test strategy

- Backend (CI/Docker sqlite): `sendRepurposeProgress` emits gated by `telegram_enabled`; each step job calls progress before advancing + still advances when notify throws; `RepurposeJobController` index/show/retry (auth gate, status filter, retry re-dispatches correct job per failed-from step, retry on non-failed → 422). `Http::fake()` for Telegram.
- Frontend: `npm run build` clean + `.mjs` smoke for the composable/list helpers.

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. NEVER substitute placeholders
> for real data sources without explicit user approval. If a data source doesn't
> exist yet, STOP and ask.

### Goal

Close the two UX gaps in the Telegram→IG repurpose pipeline: (A) every mode-button tap and every pipeline step emits a Telegram chat bubble so the operator/audience sees live progress instead of silence; (B) an `/admin/repurpose` panel lists jobs, shows per-job detail, and retries a failed step. Foundation is proven (live job #2 reached `drafted`).

### Architecture Context (from CLAUDE.md + source)

- FSM: `App\Enums\RepurposeJobStatus` (12 states: received/capturing/captured/extracting/extracted/researching/researched/rewriting/rewritten/finalizing/drafted/failed) on `App\Models\RepurposeJob` via `HasStatusTransitions`. `failed → step entrypoint` legal.
- Step jobs: `CaptureInstagramPost`, `ExtractSlideContent`, `ResearchRepurposeClaims`, `RewriteRepurposeContent`, `FinalizeRepurpose` (one queued job per step, each advances FSM).
- Telegram: `App\Services\TelegramNotificationService` — `sendRepurposeModePrompt/Failed/Drafted` exist; private `send(string $text, ?array $replyMarkup = null): bool` (POSTs new sendMessage, `telegram_enabled`-gated).
- Tap handler: `TelegramWebhookController::resolveRepurposeAction()` already does `update(mode)` + `transitionTo(Capturing)` + `CaptureInstagramPost::dispatch()` and returns the `answerCallbackQuery` toast string.
- Admin precedent: `Route::middleware(['auth:sanctum'])->prefix('admin/linkedin-drafts')` group in `backend/routes/api.php:1292`; `LinkedInDraftController` (index/show/regenerate); `frontend/src/composables/useLinkedInDrafts.js`; `AdminLayout.vue` sidebar; `frontend/src/router/index.js`.

### Tech Stack

Laravel 12 + PHPUnit (sqlite `:memory:` on CI) + `Http::fake()` for Telegram. Vue 3 `<script setup>` + TanStack Vue Query + Tailwind 4. No PHP on dev Mac → backend tests authored full-fidelity, verified on CI/Docker; frontend via `npm run build` + `node --test` `.mjs`. Commit per phase, **no push**.

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Progress bubble | `RepurposeJob` + `send()` | `sendRepurposeProgress()` | No | Create wrapper over existing `send()` |
| Tap ack | `resolveRepurposeAction` | `sendRepurposeProgress()` | Partial | Add 1 call |
| Step progress | step jobs | `sendRepurposeProgress()` | No | 4 call-sites, non-fatal |
| Admin list/detail | `repurpose_jobs` table | `RepurposeJobController` | No | Create controller |
| Slide thumbnails | `storage/app/repurpose/{job}/` (private) | guarded read route | No | Create auth:sanctum route |
| Retry | FSM `failed→step` + dispatch | `RepurposeJobController::retry` | No | Mirror `LinkedInDraftController::regenerate` |
| FE state | TanStack Query | `useRepurposeJobs.js` | No | Mirror `useLinkedInDrafts.js` |

---

### Phase A: Telegram `sendRepurposeProgress` wrapper + tap ack

**Estimated time:** 12 min · **Backend (CI-verified)**

**Files:**
- Modify: `backend/app/Services/TelegramNotificationService.php`
- Modify: `backend/app/Http/Controllers/Api/Automation/TelegramWebhookController.php`
- Test: `backend/tests/Feature/RepurposeProgressNotificationTest.php`

**Steps:**
1. Write failing test for `sendRepurposeProgress()`. Expected error: `Error: Call to undefined method App\Services\TelegramNotificationService::sendRepurposeProgress()`. Cases: emits when `telegram_enabled='true'` (assert `Http::fake` sent one sendMessage containing the text); no-ops returning false when `telegram_enabled='false'`.
2. Run test, confirm it fails for the expected reason.
3. Implement `public function sendRepurposeProgress(RepurposeJob $job, string $text): bool` → `return $this->send($text);` (the `send()` gate already handles `telegram_enabled`).
4. Add tap-ack: in `resolveRepurposeAction()`, after the `CaptureInstagramPost::dispatch()` line, call `app(TelegramNotificationService::class)->sendRepurposeProgress($job, "✅ Mode {$label} dipilih — mulai memproses…")` inside try/catch + `Log::warning` (non-fatal; never alter the returned toast string).
5. Add a test asserting a successful blog/carousel tap triggers exactly one progress sendMessage (extend existing `TelegramRepurposeCallback`-style test or new case).
6. Run tests, confirm pass.
7. Commit: `feat(repurpose): telegram progress wrapper + mode-tap ack`

**Verification:**
- [ ] `php artisan test --filter=RepurposeProgressNotification` green (CI/Docker)
- [ ] `sendRepurposeProgress` returns false + sends nothing when telegram disabled
- [ ] Tap-ack wrapped in try/catch — FSM transition + dispatch happen even if notify throws
- [ ] No placeholder/TODO in new code

---

### Phase B: Per-step progress at 4 job call-sites

**Estimated time:** 14 min · **Backend (CI-verified)**

**Files:**
- Modify: `backend/app/Jobs/CaptureInstagramPost.php`, `ExtractSlideContent.php`, `ResearchRepurposeClaims.php`, `RewriteRepurposeContent.php`
- Test: `backend/tests/Feature/RepurposeStepProgressTest.php`

**Steps:**
1. Write failing test asserting each step job sends one progress bubble before advancing FSM. Expected error: assertion fails — `Http::fake` records 0 sendMessage (no progress call yet). Use a job whose upstream service is faked/stubbed so `handle()` reaches the notify line.
2. Run test, confirm fail for expected reason.
3. In each job's `handle()`, immediately BEFORE the FSM advance to the step's "-ed" state, call `app(TelegramNotificationService::class)->sendRepurposeProgress($job, <text>)` inside try/catch + `Log::warning`:
   - Capture → `📸 {count} slide ke-capture` (count from captured result)
   - Extract → `🔎 Ekstrak: {claimsCount} klaim ditemukan`
   - Research → `✅ Fact-check: {corrected} klaim dikoreksi`
   - Rewrite → `✍️ Artikel ditulis ulang — finalisasi…`
4. Add a test per job: notify throws (`Http::fake` → 500) but FSM still advances (assert job status moved to the step's done-state).
5. Run tests, confirm pass.
6. Commit: `feat(repurpose): per-step telegram progress bubbles`

**Verification:**
- [ ] `php artisan test --filter=RepurposeStepProgress` green
- [ ] Each of 4 jobs emits exactly one progress bubble per run
- [ ] Notify failure never blocks/reverses FSM advance (try/catch proven by test)
- [ ] No placeholder/TODO

---

### Phase C: Admin `RepurposeJobController` (index/show/retry/thumbnail) + routes

**Estimated time:** 15 min · **Backend (CI-verified) · auth-sensitive**

**Files:**
- Create: `backend/app/Http/Controllers/Api/Admin/RepurposeJobController.php`
- Modify: `backend/routes/api.php` (new `prefix('admin/repurpose')` group, mirror line 1292)
- Test: `backend/tests/Feature/RepurposeJobAdminControllerTest.php`

**Steps:**
1. Write failing test: `GET /api/admin/repurpose` without auth → 401; with auth → 200 `{success:true,data:[...]}` newest-first, `?status=failed` filters. Expected error: 404/`RouteNotFoundException` (route/controller absent).
2. Run test, confirm fail.
3. Implement `index` (paginated, optional `?status` validated against `RepurposeJobStatus` values, compact fields) using the standard `{success:true,data:...}` envelope.
4. Add test for `show` (full detail incl. `pipeline_state_log`, extracted/research/rewritten JSON, FK draft ids) + 404 on missing id → implement `show`.
5. Add test for `retry`: on a `failed` job, infers the failed-from step via last non-`failed` entry in `pipeline_state_log[]`, transitions `failed → {step}`, dispatches the matching job (assert `Queue::fake` pushed the right job class); retry on a non-failed job → 422. Implement `retry`.
6. Add test for thumbnail route: `GET /api/admin/repurpose/{id}/slide/{n}` auth:sanctum returns the private file bytes / 404 when absent. Implement single-action read with path-traversal guard (basename + integer `{n}` only).
7. Register 4 routes in the `admin/repurpose` auth:sanctum group.
8. Run tests, confirm pass.
9. Commit: `feat(repurpose): admin controller — list/detail/retry/thumbnail`

**Verification:**
- [ ] `php artisan test --filter=RepurposeJobAdminController` green
- [ ] All routes under `auth:sanctum` (401 unauth) — server-side authz
- [ ] `retry` re-dispatches the correct step job; 422 on non-failed
- [ ] Thumbnail route path-traversal-safe (integer index + basename only), serves private storage
- [ ] No placeholder/TODO

---

### Phase D: Frontend — composable + List + Detail + nav

**Estimated time:** 15 min · **Frontend (local build/smoke)**

**Files:**
- Create: `frontend/src/composables/useRepurposeJobs.js`, `frontend/src/views/admin/RepurposeJobsList.vue`, `frontend/src/views/admin/RepurposeJobDetail.vue`
- Modify: `frontend/src/router/index.js`, `frontend/src/layouts/AdminLayout.vue`
- Test: `frontend/src/composables/repurposeJobs.test.mjs`

**Steps:**
1. Write failing `node --test` smoke for a pure helper in the composable (e.g. `statusTone(status)` / `isTerminal(status)` / `inferFailedStep(log)`). Expected error: module/export not found.
2. Run `node --test`, confirm fail.
3. Implement `useRepurposeJobs.js` mirroring `useLinkedInDrafts.js`: `useRepurposeJobs()` (list, 30s staleTime + `refetchOnMount:'always'`, `refetchInterval` while any job non-terminal), `useRepurposeJob(id)`, `useRetryRepurposeJob()` mutation invalidating on success + the pure helpers under test.
4. Build `RepurposeJobsList.vue` (status filter tabs, auto-refresh table: status pill, mode, source link, relative time, row→detail) using `base/*` components + Dark Cinema tokens.
5. Build `RepurposeJobDetail.vue` (slide thumbnail strip via thumbnail route, claims+verdict list, rewrite preview, `pipeline_state_log` timeline, retry button shown only when `failed`).
6. Add router entries (`/admin/repurpose`, `/admin/repurpose/:id`) + sidebar "Repurpose" link near LinkedIn in `AdminLayout.vue`.
7. `node --test` green; `npm run build` clean.
8. Commit: `feat(repurpose): admin panel — list/detail/retry UI`

**Verification:**
- [ ] `node --test src/composables/repurposeJobs.test.mjs` pass
- [ ] `npm run build` clean
- [ ] List auto-refreshes; detail shows real `repurpose_jobs` data (no mock)
- [ ] Retry button only when `failed`; calls retry endpoint + invalidates
- [ ] No placeholder/TODO

---

### Phase E: Docs sync

**Estimated time:** 6 min

**Steps:**
1. Update root `CLAUDE.md`: note `sendRepurposeProgress` + 4 step bubbles + tap ack, the `/admin/repurpose` panel + 4 routes, and the `useRepurposeJobs` composable. Update "Last Updated".
2. Mark this plan's phases complete.
3. Commit: `docs(repurpose): sync CLAUDE.md — progress bubbles + admin panel`

**Verification:**
- [ ] CLAUDE.md reflects new routes/composable/notify methods
- [ ] All phase verifications checked

---

### Out of scope / follow-up (logged, not built here)

- IG capture over-grab (26 images vs ~10 real slides) — selector tightening in `scripts/playwright/ig-capture.cjs`. Separate fix; current behavior still produced a valid draft.
- No `editMessageText` checklist style (explicitly rejected — separate-bubble chosen).
