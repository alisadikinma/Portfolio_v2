# LinkedIn Draft — Live Backend-Activity Visibility

> Status: Design approved 2026-06-11 (gaspol-brainstorm). Scope locked: `LinkedInDraftDetail.vue` only, Option A fidelity (cheap-but-honest), Approach A (cache-lock-derived, no migration).

## Design

### Problem
On `/admin/sosmed-drafts/{id}` the operator cannot tell what the backend is doing during the long carousel actions. Concretely: for the ~3–7 min `/carousel-gen` **re-author** phase the draft sits in `manual_review` with the OLD `carousel_slides` and a STALE `last_error`, so the UI shows a red "LAST ERROR" banner + "nothing rendering" while the backend is actually busy. The operator reads this as "still broken" (real incident on draft 149, 2026-06-11). After re-author, image rendering IS already pollable but isn't surfaced as clear progress.

### Two phases, very different visibility
- **Render phase** (GeminiGen per-slide) — already pollable via `carousel_slides[].image_status` (pending→generating→done). `useLinkedInDraft` already polls 5s on this.
- **Re-author phase** (`/carousel-gen` SSH, ~3–7 min) — a black box: the queued `RegenerateLinkedInCarouselContent` job holds a synchronous SSH call and writes nothing pollable until it replaces slides. This is the visibility gap.

### Approach A (chosen) — cache-lock-derived activity, NO migration
Piggyback on the existing dispatch lock `linkedin_regenerate_lock:{id}` (set by `LinkedInDraftController` on regenerate dispatch, released in the job's `finally`):
- Enrich the lock VALUE to `{ started_at, phase }` instead of a bare flag.
  - Controller writes `phase = 're_authoring'` + `started_at = now()` at dispatch.
  - `RegenerateLinkedInCarouselContent` flips `phase = 'rendering'` right before `GenerateLinkedInCarouselImages::dispatch` (slides already replaced).
  - Lock released in the job `finally` (existing) — after release, render progress is carried by per-slide `image_status` (job already returned; renders outlive it).
- The `show` endpoint (`LinkedInDraftController::show`) returns a derived block:
  ```json
  "regenerate_activity": { "active": true, "phase": "re_authoring", "started_at": "2026-06-11T06:34:55Z" }
  ```
  (~10 lines reading the cache key; `active=false` when absent.)

### Frontend live-status resolution (precedence)
Single computed in `LinkedInDraftDetail.vue`:
1. `regenerate_activity.active && phase === 're_authoring'` → **"Re-authoring slides"** — indeterminate bar + elapsed timer (from `started_at` + client clock) + "~3–7 min" ETA copy + style label (`sketchnote`).
2. any slide `image_status ∈ {pending, generating}` → **"Rendering N/7"** — determinate bar + live done-count (already pollable).
3. all slides `done` → **"Ready to review"**.
4. else `failed` / idle → existing normal hero.

### Stale-error kill
`showLastError` gains one guard: suppress when `regenerate_activity.active` **OR** any slide is rendering. Removes the "red error + nothing happening" contradiction. `last_error` stays in DB for debugging; only the in-progress UI hides it.

### Polling
Add `regenerate_activity.active` to `useLinkedInDraft`'s poll-on predicate (currently triggers on slide generation + FSM in-progress) so the re-author window also updates live (~5s).

### Design language (reuse existing tokens — no new system)
Dark-Cinema: `accent-cyan #06B6D4` for in-progress, `accent-gold #D4A843` accents, glass-card, JetBrains-Mono uppercase labels. Reuse the existing status-hero panel + thumbnail-strip status dots (emerald done / cyan-pulse generating / red failed / grey pending). `prefers-reduced-motion` zeroes the bar shimmer + timer animation.

```
┌────────────────────────────────────────────────────────┐
│ ◐ RE-AUTHORING SLIDES   carousel · 7 slides             │
│ ▓▓▓▓▓▓▓▓▓░░░░░░░  elapsed 3m12s · ~3–7 min · sketchnote │
│ Pulling teaching content from your blog & rebuilding…   │
│ (LAST ERROR hidden while working)                        │
└────────────────────────────────────────────────────────┘
        ↓ slides replaced
┌────────────────────────────────────────────────────────┐
│ ◐ RENDERING IMAGES   4 / 7 done                         │
│ ▓▓▓▓▓▓▓▓▓▓▓░░░░░  ✓✓✓✓◐◐·  GeminiGen                    │
└────────────────────────────────────────────────────────┘
```

### Data Integration Map
| UI element | Data source | Exists? | Notes |
|---|---|---|---|
| re-author active + started_at + phase | cache lock `linkedin_regenerate_lock:{id}` (value enriched) | lock exists; value enrichment new | ephemeral, expires with lock TTL |
| render progress N/M | `carousel_slides[].image_status` | ✅ already polled | — |
| elapsed timer / ETA | `started_at` + client clock | new (client-side) | `setInterval` 1s, cleared on unmount |
| stale-error suppression | derived from activity + slide status | new guard in `showLastError` | DB `last_error` untouched |

### Feasibility
Zero migration, real data only. Backend = enrich lock value (controller + job) + ~10-line `regenerate_activity` in `show`. Frontend = `LinkedInDraftDetail.vue` status-hero computeds + `useLinkedInDrafts.js` poll predicate. ~1 controller + 1 job + 1 composable + 1 view.

### Rejected — Approach B (DB columns `regenerate_phase` + `regenerate_started_at`)
Durable across worker restarts + gives history, but costs a migration + more write points. For a single-operator tool, A's ephemeral cache signal is sufficient: if the worker dies mid-job the lock TTL expires and the UI falls back to slide-status (honest). Revisit only if multi-step durability/history is needed.

### Out of scope
Queue-list badges, generate-from-scratch / publish / cross-post surfaces (this pass is detail-page only per operator decision).

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution, NEVER substitute placeholders for real data sources without explicit user approval. If a data source doesn't exist yet, STOP and ask.

## Goal

Make `/admin/sosmed-drafts/{id}` honestly show what the backend is doing during the long carousel actions, so the operator never again sees "red error + nothing happening" while `/carousel-gen` is actually re-authoring (the draft-149 incident). Deliver: (1) a live phase resolution (Re-authoring → Rendering N/M → Ready), (2) an elapsed timer + "~3–7 min" ETA during re-author, (3) suppression of stale `last_error` while any work is in flight. Approach A — reuse the existing `reauthoring` slide marker + the `started_at` already stored in the dispatch lock; **no migration**.

## Architecture Context

Pulled from CLAUDE.md (root + backend + frontend) and verified against current code:

- **Already exists (reuse, do NOT reinvent):**
  - Dispatch lock `linkedin_regenerate_lock:{id}` — value is `now()->toIso8601String()` (the re-author start time), TTL 960s. Set in [`LinkedInDraftController::regenerateAllImages`](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php#L977-L978); released in [`RegenerateLinkedInCarouselContent`](backend/app/Jobs/RegenerateLinkedInCarouselContent.php#L63) `finally` + `failed()`.
  - Per-slide `image_status='reauthoring'` marker set during the re-author window ([controller L1021-1026](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php#L1021)); `last_error` cleared on dispatch ([L1030](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php#L1030)).
  - Render-phase progress: `carousel_slides[].image_status ∈ {pending,generating,done,failed}` — already polled.
  - [`useLinkedInDraft`](frontend/src/composables/useLinkedInDrafts.js#L69) (the `show` query) already polls every 5s while any slide is `reauthoring|generating|pending` ([L84-L88](frontend/src/composables/useLinkedInDrafts.js#L84)) — so surfacing activity via `show()` is live for free.
  - Status-hero already detects `reauthoring` and prints a phase line ([LinkedInDraftDetail.vue L890-893](frontend/src/views/admin/LinkedInDraftDetail.vue#L890)); pure helpers live in [`linkedinHelpers.js`](frontend/src/views/admin/linkedinHelpers.js) with smoke tests in [`linkedinHelpers.test.mjs`](frontend/src/views/admin/linkedinHelpers.test.mjs) (`node --test`).
  - [`showLastError`](frontend/src/views/admin/LinkedInDraftDetail.vue#L542) computed — status gate (`failed|manual_review`) + staleness gate (`STALE_ERROR_HOURS`). **Missing**: an in-progress guard.
- **Gap to close:** `started_at` is not surfaced to the FE (so no elapsed/ETA); `showLastError` doesn't suppress during re-author/render; no determinate render progress bar.

## Tech Stack

Laravel 12 (controller + `Illuminate\Support\Facades\Cache` already imported at [LinkedInDraftController L20](backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php#L20)) · PHPUnit feature tests (sqlite `:memory:`, run on CI / Docker `serversideup/php:8.2-cli` — no PHP on dev Mac) · Vue 3 `<script setup>` + TanStack Query · pure JS helpers tested via `node --test` `.mjs` (project convention, no vitest). Dark-Cinema tokens: `accent-cyan #06B6D4` (in-progress), `accent-gold #D4A843`, glass-card, JetBrains-Mono labels. Respect `prefers-reduced-motion`.

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| re-author active + started_at | cache `linkedin_regenerate_lock:{id}` (value = ISO start) | `show()` → new `regenerate_activity` block | Lock exists; not surfaced | Add derived block to `show()` |
| re-author phase marker | `carousel_slides[].image_status==='reauthoring'` | `useLinkedInDraft()` | ✅ Yes | Use existing |
| render progress N/M | `carousel_slides[].image_status` | `useLinkedInDraft()` | ✅ Yes | Use existing |
| live poll during re-author | `useLinkedInDraft` refetchInterval (covers `reauthoring`) | `useLinkedInDrafts.js#L84` | ✅ Yes | Use existing (no change) |
| elapsed timer / ETA | `regenerate_activity.started_at` + client clock | `setInterval` in view | No | Create (Phase C) |
| stale-error suppression | derived (activity + slide status) | `shouldShowLastError()` helper | No | Create (Phase B+C) |

---

### Phase A: Surface `regenerate_activity` in `show()` (backend)

**Estimated time:** 8 min

**Files:**
- Modify: `backend/app/Http/Controllers/Api/Admin/LinkedInDraftController.php` (`show()`, ~L121-149)
- Test: `backend/tests/Feature/LinkedInDraftRegenerateActivityTest.php` (create)

**Steps:**
1. Write failing test `test_show_includes_regenerate_activity_active_when_lock_present`: `Cache::put("linkedin_regenerate_lock:{$draft->id}", now()->toIso8601String(), 960)`, GET `/api/admin/linkedin-drafts/{id}` (acting as sanctum user), assert JSON path `data.regenerate_activity.active === true`, `phase === 're_authoring'`, `started_at` non-null. Expected fail: `Undefined array key "regenerate_activity"` / missing key assertion fails.
2. Run `php artisan test --filter=LinkedInDraftRegenerateActivityTest` (Docker sqlite) — confirm fail for that reason.
3. Implement in `show()`: after loading `$draft`, build `$activity = $this->resolveRegenerateActivity($draft)` and return `array_merge($draft->toArray(), ['regenerate_activity' => $activity])` as `data`. Add private `resolveRegenerateActivity(LinkedInPost $draft): array` returning `['active'=>bool,'phase'=>?string,'started_at'=>?string]`: `started_at = Cache::get("linkedin_regenerate_lock:{$draft->id}")`; `reauthoring = collect($draft->carousel_slides ?? [])->contains(fn($s)=>($s['image_status']??null)==='reauthoring')`; `active = (bool)$started_at || $reauthoring`; `phase = $active ? 're_authoring' : null`; `started_at` left as the ISO string or null.
4. Add second test `test_show_regenerate_activity_inactive_when_no_lock_and_no_reauthoring`: fresh carousel draft (slides `done`), assert `data.regenerate_activity.active === false`. Run both — confirm pass.
5. Commit: `feat(linkedin): surface regenerate_activity (phase + started_at) in draft show`

**Verification:**
- [ ] `php artisan test --filter=LinkedInDraftRegenerateActivityTest` green (Docker sqlite)
- [ ] `show()` response unchanged except the added `regenerate_activity` key (existing fields intact — assert `data.id` still present in test)
- [ ] No migration added; reads existing cache key only
- [ ] No placeholder/TODO comments
- [ ] Security: read-only auth:sanctum endpoint, no new input; no secrets

---

### Phase B: Pure helpers in `linkedinHelpers.js` (frontend logic, TDD)

**Estimated time:** 12 min

**Files:**
- Modify: `frontend/src/views/admin/linkedinHelpers.js`
- Test: `frontend/src/views/admin/linkedinHelpers.test.mjs` (append cases)

**Steps:**
1. Write failing `node --test` cases in `linkedinHelpers.test.mjs` for three new exports:
   - `shouldShowLastError({ lastError, status, pipelineLog, slides, regenerateActive, nowMs, staleHours })` — returns `false` when `regenerateActive` true; `false` when any slide `image_status ∈ {reauthoring,pending,generating}`; otherwise existing logic (no `lastError`→false; status not in `failed|manual_review`→false; stale by `staleHours`→false; else true).
   - `resolveCarouselActivity({ slides, regenerateActive, regenerateStartedAt, nowMs })` → `{ phase, renderDone, renderTotal, elapsedMs }` where phase = `re_authoring` if `regenerateActive` or any slide `reauthoring`; else `rendering` if any slide `pending|generating`; else `ready` if total>0 && all `done`; else `idle`. `renderDone`=count `done`, `renderTotal`=slides.length, `elapsedMs`= `regenerateStartedAt? nowMs-Date.parse(startedAt) : null`.
   - `formatElapsed(ms)` → `"3m12s"` / `"0m05s"` / `''` for null.
   Expected fail: `TypeError: shouldShowLastError is not a function` (not yet exported).
2. Run `node --test src/views/admin/linkedinHelpers.test.mjs` — confirm fail for that reason.
3. Implement the three pure functions in `linkedinHelpers.js` (match existing export style — named exports, no Vue imports). Keep `resolveCarouselActivity` defensive (null/empty slides → `idle`, `renderTotal=0`).
4. Run `node --test ...` — confirm all green (existing cases still pass).
5. Commit: `feat(linkedin): pure helpers for live carousel activity + error suppression`

**Verification:**
- [ ] `node --test src/views/admin/linkedinHelpers.test.mjs` all green (new + existing)
- [ ] Functions are pure (no Vue/DOM imports), handle null/empty/malformed input without throwing
- [ ] No placeholder/TODO comments
- [ ] `shouldShowLastError` reproduces existing status+staleness behavior when `regenerateActive=false` and no slides rendering (regression case asserted)

---

### Phase C: Wire helpers into the view + live clock (frontend UI)

**Estimated time:** 14 min

| Phase | Code Deliverable | Design Deliverable | Verification |
|---|---|---|---|
| C | LinkedInDraftDetail.vue status-hero + showLastError | Reuse existing Dark-Cinema status-hero tokens (cyan in-progress, gold accent, JetBrains-Mono label); indeterminate bar for re-author, determinate bar for render N/M; `prefers-reduced-motion` zeroes shimmer/tick | build + visual states |

**Files:**
- Modify: `frontend/src/views/admin/LinkedInDraftDetail.vue`

**Steps:**
1. Add a `nowMs` ref + `setInterval(()=>nowMs.value=Date.now(),1000)` started `onMounted`, cleared `onBeforeUnmount` (mirror the existing cancel-window ticker pattern already in this file). Gate the interval/transition under `prefers-reduced-motion` (no per-second tick when reduced — fall back to a static "running" label).
2. Replace `showLastError` computed body (L542) to delegate: `shouldShowLastError({ lastError: draft.value?.last_error, status: draft.value?.status, pipelineLog: draft.value?.pipeline_state_log, slides: draft.value?.carousel_slides, regenerateActive: draft.value?.regenerate_activity?.active, nowMs: nowMs.value, staleHours: STALE_ERROR_HOURS })`.
3. Add `liveActivity` computed = `resolveCarouselActivity({ slides: draft.value?.carousel_slides, regenerateActive: draft.value?.regenerate_activity?.active, regenerateStartedAt: draft.value?.regenerate_activity?.started_at, nowMs: nowMs.value })`. In the status-hero template: when `phase==='re_authoring'` show indeterminate cyan bar + `Re-authoring slides · {{ formatElapsed(liveActivity.elapsedMs) }} · ~3–7 min · {style}`; when `phase==='rendering'` show determinate bar (`renderDone/renderTotal`) + `Rendering images · {{ renderDone }} / {{ renderTotal }}`; when `ready` show "Ready to review". Reuse existing `reauthoring` phase text block (L890) — replace its static copy with `liveActivity`-driven copy.
4. Run `npm run build` — confirm clean. Manually reason through the 3 states render (re_author / render / ready) + reduced-motion fallback.
5. Commit: `feat(linkedin): live phase + elapsed timer + stale-error suppression on draft detail`

**Verification:**
- [ ] `npm run build` clean
- [ ] `showLastError` no longer true while `regenerate_activity.active` OR any slide rendering/reauthoring (trace via the Phase-B unit cases)
- [ ] Elapsed timer ticks from `regenerate_activity.started_at`; ETA "~3–7 min" shown during re-author; render bar shows live N/M
- [ ] `setInterval` cleared on unmount (no orphan timer); `prefers-reduced-motion` path has no per-second animation
- [ ] No placeholder/TODO comments; reuses existing tokens (no new design system)

---

### Phase D: Docs sync

**Estimated time:** 4 min

**Files:**
- Modify: root `CLAUDE.md` (LinkedIn Carousel section + Last Updated changelog)

**Steps:**
1. Add a short entry: `show()` now returns `regenerate_activity {active,phase,started_at}` (cache-lock-derived, no migration); detail page renders live phase (re-author→render N/M→ready) + elapsed/ETA; `showLastError` suppressed while in-flight; new pure helpers `shouldShowLastError`/`resolveCarouselActivity`/`formatElapsed` in `linkedinHelpers.js`.
2. Commit: `docs: live backend-activity visibility on LinkedIn draft detail`

**Verification:**
- [ ] CLAUDE.md reflects the new `regenerate_activity` field + behavior
- [ ] "Last Updated" line refreshed

---

## Execution Handoff

- **Option 1 (recommended): Execute in this session** — `gaspol-execute`, per-phase checkpoints + TDD hard gate. A→B→C→D sequential (C depends on A's field + B's helpers).
- **Option 2: Parallel** — Phase A (backend) and Phase B (pure helpers) are file-disjoint and independent → could run in parallel via `gaspol-parallel`, then C (needs both) then D. Marginal benefit at this size.
- **Option 3: Separate session** — plan file has everything: [docs/plans/2026-06-11-linkedin-draft-live-backend-activity.md](docs/plans/2026-06-11-linkedin-draft-live-backend-activity.md).

**Note (no PHP on dev Mac):** Phase A authored full-fidelity + verified in Docker `serversideup/php:8.2-cli` sqlite (or CI). Phases B/C verified locally (`node --test` + `npm run build`). Per project policy: commit only, do not push (operator authorizes pushes).
