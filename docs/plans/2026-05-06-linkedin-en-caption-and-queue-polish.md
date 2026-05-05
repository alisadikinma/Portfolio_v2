# LinkedIn EN Caption + Queue Polish

**Date:** 2026-05-06
**Status:** Brainstorm complete — ready for `gaspol-plan`
**Origin:** Operator review of `/admin/linkedin-queue`

---

## Design

### Goals

Three operator-driven changes to the LinkedIn admin pipeline:

1. **English-only LinkedIn caption** for both TEXT and CAROUSEL formats — the post body that goes onto LinkedIn. Carousel **slides themselves** stay bilingual (ID main + EN subtitle) — no change to `/carousel-gen` engine output.
2. **Progress % indicator** in the Queue list while a draft is in `generating` / `validating` FSM state — so operators don't stare at a static "GENERATING" pill for 3–7 minutes wondering if anything is happening.
3. **Remove the Depth column** from the Queue list — it's null for carousel drafts (post plugin v0.5.0) and a stub `100` for legacy carousels, so it adds noise without signal. Detail page + Posts list keep the column for TEXT-format inspection.

### Non-goals

- **No** per-post language toggle in admin UI (operator declined — global English for caption is fine)
- **No** translation fallback path (if plugin v0.6.0 isn't deployed yet, drafts still generate Indonesian — operator just doesn't approve them until the plugin lands; no backend post-translate)
- **No** real per-step progress callback from the plugin (would require plugin v0.6.0 hooks + backend endpoint; deferred to a future iteration if synthetic % proves insufficient)
- **No** changes to carousel slide bilingual chrome — the `CarouselSlideEnhancer` brand-chrome rules + `/carousel-gen` plugin output stay identical

### Architecture

Three layers touched, each independently shippable. Order recommended for review safety: **#3 (depth removal) → #2 (progress %) → #1 (English caption)**, because #1 has the plugin-redeploy gate and #3+#2 are zero-risk frontend-only.

#### Req 1 — English-only LinkedIn caption

**Plugin layer** — `linkedin-post-writer` v0.6.0:
- `/linkedin-gen` text-format path: switch authoring language to English. Update [`refs-linkedin-formats.md`](https://github.com/alisadikinma/linkedin-post-writer/blob/main/docs/rag/linkedin-playbook/) + [`refs-linkedin-templates.md`](https://github.com/alisadikinma/linkedin-post-writer/blob/main/docs/rag/linkedin-playbook/) to author hooks/setup/insights/CTA in English by default. Recompile via `npm run compile-refs`, redeploy bundle to VPS at `/home/claudesn/refs-linkedin-{playbook,templates,formats}.md`.
- `/linkedin-gen` carousel `route_to_carousel_gen` envelope: add `caption_language: 'en'` field for backend telemetry. Schema-level optional; backend ignores if absent (defense-in-depth).
- Plugin tests updated. Bumped to v0.6.0 (minor, additive — no breaking schema changes).

**Backend layer** — [`LinkedInGenerationService::buildCarouselCaption`](backend/app/Services/LinkedInGenerationService.php):
- Hook source: `cover.copy_en` (was `cover.copy_id`)
- Drop the "punchline subtitle" block entirely (currently `cover.copy_en`; would now duplicate the new hook)
- `extractSetupParagraph()`: prefer `post.translations.en.excerpt` when available; fall back to `copy_en` from non-cover slides; last fallback to ID-language excerpt only if no English source exists at all (graceful degradation when translation pipeline is behind)
- `extractInsightsFromSlides()`: switch field preference to `headline_en` → `copy_en` (was `headline_id` → `copy_id`)
- `engagement_question` default: `"What's really happening behind the scenes?"` (was `"Apa yang sebenarnya terjadi di balik layar?"`)
- Swipe CTA line: `"Swipe →"` (was `"Geser →"` — confirm current default in code)
- "Full article: link in comments ↓" — already English ✓

Carousel slide chrome rendering (bilingual ID main + EN subtitle amber) — **no change**. `CarouselSlideEnhancer.appendBrandChrome` and the `/carousel-gen` plugin output stay identical. The bilingual treatment is still the right call for slides themselves; only the **caption that accompanies the carousel post** switches to English.

#### Req 2 — Generating progress %

**Frontend-only.** Zero schema or backend changes.

New helper [`linkedinHelpers.js::generatingProgress(draft)`](frontend/src/views/admin/linkedinHelpers.js):

```js
const BASELINES_MS = {
  text: { generating: 60_000, validating: 8_000 },
  carousel: { generating: 360_000, validating: 8_000 },
}

export function generatingProgress(draft) {
  if (!['generating', 'validating', 'pending_generation'].includes(draft.status)) {
    return null
  }
  const log = Array.isArray(draft.pipeline_state_log) ? draft.pipeline_state_log : []
  // Find latest entry where status entered generating (or pending if not yet)
  const target = draft.status === 'validating' ? 'validating' : 'generating'
  const entry = [...log].reverse().find(e => e?.to === target)
  if (!entry?.timestamp) return null
  const elapsed = Date.now() - new Date(entry.timestamp).getTime()
  const baseline = BASELINES_MS[draft.format ?? 'text']?.[target] ?? 60_000
  const pct = Math.floor((elapsed / baseline) * 100)
  // Hard cap so user never sees 100% on a still-running run
  if (target === 'validating') return Math.min(99, Math.max(95, 95 + pct / 20))
  return Math.min(95, Math.max(0, pct))
}
```

Format-aware baselines:
- **Text** generating: 60_000 ms (P50 30–90s observed per CLAUDE.md)
- **Carousel** generating: 360_000 ms (P50 3–7 min — `/linkedin-gen` short-circuit ~15s + `/carousel-gen` Sonnet ~3-7 min)
- **Validating**: 8s for both (validating phase is uniformly short)

UI render in Queue list status pill:

```
⬤ GENERATING ~42%
```

Append the `~N%` suffix to existing `effectiveStatusMeta(draft).short` text via a new optional `progressSuffix` field on the meta result. Live ticker: `setInterval(1000)` set in `onMounted`, cleared in `onBeforeUnmount` — same pattern already used in [`LinkedInDraftDetail.vue`](frontend/src/views/admin/LinkedInDraftDetail.vue) for the cancel-window countdown.

Reset semantics: when status flips out of `generating`/`validating`/`pending_generation`, `generatingProgress()` returns `null` and the pill reverts to existing meta (no % suffix).

**Why no real progress callback?** The `/linkedin-gen` and `/carousel-gen` SSH calls are synchronous one-shot invocations — no mid-run hook protocol exists. Adding one requires plugin v0.6.0 + backend endpoint + queue-worker IPC. Synthetic time-based % is honest (labeled `~`), zero-risk, and good enough for the operator's actual question: *"is anything happening or is it stuck?"*. If P50 baselines drift significantly post-deploy, tune the constants.

#### Req 3 — Remove Depth column

Frontend-only. In [`LinkedInQueueList.vue`](frontend/src/views/admin/LinkedInQueueList.vue):

- Grid template (lines 298 + 341): `grid-cols-[1fr_90px_130px_70px_1fr_110px_130px]` → `grid-cols-[1fr_90px_130px_1fr_110px_130px]`
- Remove Depth header `<button>` block (lines 316–323) — the sortable header
- Remove Depth row cell `<div>` (lines 379–389) — the per-row score render
- Remove `'depth'` case from `sortValue()` (line 83)
- Keep `depthTone()` helper — still used by Virality column scoring (line 354)

Detail page ([`LinkedInDraftDetail.vue`](frontend/src/views/admin/LinkedInDraftDetail.vue)) and Posts list ([`LinkedInPostsList.vue`](frontend/src/views/admin/LinkedInPostsList.vue)) keep depth display per CLAUDE.md May 4 entry — only Queue is decluttered.

### Data Integration Map

| Component | Data Source | Existing? | Notes |
|---|---|---|---|
| English text `post.content` | `/linkedin-gen` plugin v0.6.0 output | NEW (plugin update) | Operator redeploy required |
| English carousel caption | `cover.copy_en`, `post.translations.en.excerpt`, slide `copy_en`/`headline_en` | YES | Backend field-ref switch in `buildCarouselCaption` |
| `caption_language: 'en'` envelope flag | Plugin v0.6.0 OrchestratorOutput | NEW (plugin update) | Backend reads for telemetry, ignores if absent |
| Progress % timestamp | `linkedin_posts.pipeline_state_log[]` | YES (JSON cast to array, already in API) | Pure frontend `findLast({to:'generating'})` |
| Format-aware baseline | `draft.format` (text/carousel) | YES | Const map in helper |
| Status pill render | `effectiveStatusMeta()` in `linkedinHelpers.js` | YES | Add optional `progressSuffix` field |
| Live ticker | `setInterval` 1s | YES (pattern from detail page) | `onMounted` set / `onBeforeUnmount` clear |
| Queue grid template | `LinkedInQueueList.vue` | YES | Drop one 70px column |
| Sort key removal | `sortValue('depth')` | YES | Delete one switch case |

### Failure modes & graceful degradation

- **Plugin v0.6.0 not deployed yet, but backend caption builder shipped** → carousel caption uses `cover.copy_en` (always present from `/carousel-gen` bilingual output, never null). TEXT drafts continue generating Indonesian `post.content` until plugin lands. No corruption, just a transitional period where TEXT and CAROUSEL caption languages diverge. Mitigation: ship plugin redeploy in same operator session as backend deploy.
- **`post.translations.en.excerpt` missing** (translation pipeline behind for that blog post) → setup paragraph falls back to slide `copy_en` (carousel) or to plugin-authored body (text). No null caption.
- **`pipeline_state_log[]` missing or empty** for an old draft → `generatingProgress()` returns `null` → pill renders without % suffix (silent degradation). No console error.
- **`new Date(entry.timestamp)` fails** (malformed ISO string) → `elapsed` is `NaN` → `Math.floor(NaN)` is `NaN` → `Math.min(95, NaN)` is `NaN` → suffix renders as `~NaN%`. **Defensive fix**: wrap with `if (!Number.isFinite(pct)) return null`. Already in pseudocode above.
- **Baseline drift** (carousel runs averaging 9 min instead of 6) → progress hits 95% at 5.7 min and stays pegged. Operator sees "stuck at 95%" — same UX as a real stuck job but without the false "100%" lie. Acceptable; tune constants if pattern emerges.

### Out of scope

- Real per-step plugin progress hooks (`/automation/linkedin-drafts/{id}/progress` endpoint analogous to `article-gen`) — deferred. Synthetic % is sufficient for v1.
- Per-post language toggle UI — operator declined; global English caption is the desired default.
- Carousel slide language switch — slides stay bilingual.
- Backend post-translate fallback for TEXT format — rejected (voice loss + cost + latency).
- Queue list column reordering / virality threshold filter — separate UX work.

### Operator deploy gate

Req 1 has a manual deploy step that doesn't fit `deploy.sh`:

```bash
# On VPS as claudesn:
cd ~/.claude/plugins/cache/linkedin-post-writer/0.6.0/  # adjust path
git pull
npm install
npm run compile-refs
# refs-linkedin-{playbook,templates,formats}.md regenerate
# Symlinks at /home/claudesn/ should auto-resolve
sudo systemctl restart portfolio-queue.service
```

Backend + frontend changes ship via standard `git push origin main` → CI/CD.

### File-level work items (preview — full per-phase plan via `gaspol-plan`)

1. **Plugin** `linkedin-post-writer` v0.6.0 — English authoring + envelope `caption_language` field + tests
2. **Backend** `LinkedInGenerationService::buildCarouselCaption` — field-ref switch + English defaults + 4 unit-test cases (carousel-with-en-translation / carousel-without-en-translation / text-format-passthrough / legacy-fallback)
3. **Frontend** `linkedinHelpers.js` — add `generatingProgress(draft)` helper + extend `effectiveStatusMeta()` with `progressSuffix` field
4. **Frontend** `LinkedInQueueList.vue` — wire live ticker (setInterval/onBeforeUnmount), append `~N%` to status pill, drop Depth column (header + row + sortValue)
5. **CLAUDE.md update** — document plugin v0.6.0, new caption_language flow, queue UI changes, baseline constants for future tuning

### Acceptance criteria

- [ ] TEXT-format LinkedIn drafts generated post-deploy have English `post.content`
- [ ] CAROUSEL drafts generated post-deploy have English caption (no Indonesian phrases) but bilingual slides (unchanged)
- [ ] Queue row in `generating` status shows `~N%` suffix that increases over time
- [ ] Progress % caps at 95% and never displays 100% on a running draft
- [ ] Progress % resets when status transitions out of generating/validating
- [ ] Depth column absent from Queue list (header + cells + sort)
- [ ] Detail page Depth display unchanged
- [ ] Posts list Depth display unchanged
- [ ] No console errors on drafts with missing/empty `pipeline_state_log`
- [ ] Vite build clean
- [ ] Existing LinkedIn test suite still passes (15+ tests)

---

## Implementation Plan

> **For Claude:** REQUIRED SKILL: Use `gaspol-execute` to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

### Goal

Ship three operator-driven UX improvements to the LinkedIn admin pipeline in one Portfolio_v2 PR (Phases 1–4 + 6) plus one plugin repo PR with a manual VPS deploy gate (Phase 5). Carousel slide bilingual chrome and the `/carousel-gen` engine are explicitly untouched.

### Architecture Context

Pulled from CLAUDE.md (already loaded):

- **FSM source of truth:** `linkedin_posts.pipeline_state_log` JSON column, cast to array via [LinkedInPost model](backend/app/Models/LinkedInPost.php) `$casts`. Each entry: `{from, to, reason, timestamp}` (ISO 8601). Already exposed to frontend via `LinkedInDraftController::show`.
- **Caption builder:** [`LinkedInGenerationService::buildCarouselCaption`](backend/app/Services/LinkedInGenerationService.php#L1090) — 7-block builder (hook / subtitle / setup / pull-quote / insights / question+CTA / link). Refactored April 29 per CLAUDE.md ("LinkedIn carousel caption builder rewrite").
- **Plugin integration:** `linkedin-post-writer` v0.5.0 (post Apr 28 strict `/carousel-gen` enforcement) lives at separate repo `D:\Projects\claude-plugin\linkedin-post-writer`. SSH'd to VPS via `LinkedInGenerationService::dispatchOrchestrator`. Refs deployed at `/home/claudesn/refs-linkedin-{playbook,templates,formats}.md` (3 bundles post v0.5.0). v0.6.0 will be additive (no breaking schema).
- **Frontend status helpers:** [`linkedinHelpers.js`](frontend/src/views/admin/linkedinHelpers.js) — `STATUS_META` map (line 19), `effectiveStatusMeta(draft)` (line 153), `MOOD_CLASSES` (line 175). Already imported by all 3 LinkedIn admin views.
- **Test patterns:**
  - Backend: `tests/Unit/LinkedInGenerationService<X>Test.php` and `tests/Feature/LinkedInGenerationService<X>Test.php` (PHPUnit, not Pest — project convention).
  - Frontend helpers: `.test.mjs` Node smoke tests (precedent: [`imagePositioning.test.mjs`](frontend/src/utils/imagePositioning.test.mjs)).
- **Build/deploy:** `git push origin main` triggers VPS auto-deploy via GitHub Actions per CLAUDE.md. Plugin redeploy is manual operator step (Phase 5).

### Tech Stack

- Backend: PHP 8.2 + Laravel 12 + PHPUnit (existing)
- Frontend: Vue 3.5 `<script setup>` + Vite 7.1 + Tailwind 4 (existing)
- Plugin: TypeScript + Zod schemas + Bun/Node compile-refs script (existing in `linkedin-post-writer` repo)
- No new dependencies in any phase.

### Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Status pill render | `STATUS_META` + `effectiveStatusMeta()` | `import from linkedinHelpers.js` | YES | Extend with `progressSuffix` field |
| Generating progress % | `linkedin_posts.pipeline_state_log[]` | API field already returned in `show`/`index` | YES | New helper `generatingProgress(draft)` reads it client-side |
| Format-aware baseline | `draft.format` ('text'\|'carousel') | API field | YES | Const map in helper |
| Live ticker | `setInterval(1000)` | Vue `onMounted`/`onBeforeUnmount` | YES (pattern from `LinkedInDraftDetail.vue` cancel-window countdown) | Apply same pattern in `LinkedInQueueList.vue` |
| English carousel hook | `cover.copy_en` from `linkedin_posts.carousel_slides[0]` | Direct read in `buildCarouselCaption` | YES (always present in `/carousel-gen` bilingual output) | Switch field reference (was `copy_id`) |
| English setup paragraph | `post.translations.en.excerpt` → `slide.copy_en` fallback | Eager-loaded relation in `LinkedInPost::with(['post.translations'])` | YES | Switch source preference in `extractSetupParagraph` |
| English insights | `slide.headline_en` → `slide.copy_en` fallback | Direct read | YES | Switch field preference in `extractInsightsFromSlides` |
| English engagement question | `brief.engagement_question` (plugin) + literal English fallback | Direct read | YES (plugin currently emits ID/EN per source language) | Change literal fallback string |
| English Swipe CTA | Hardcoded literal in `buildCarouselCaption` line ~1161 | n/a | YES | Change literal string |
| `caption_language: 'en'` envelope flag | Plugin `OrchestratorOutput` schema | Plugin v0.6.0 emit | NO | Plugin v0.6.0 — add optional field, backend reads for telemetry/log only |
| English TEXT `post.content` | Plugin `/linkedin-gen` text format authoring | Plugin v0.6.0 | NO | Plugin v0.6.0 — switch authoring language in refs + skill |
| Depth column removal | `LinkedInQueueList.vue` template | n/a | YES | Pure template delete |
| Vue test runner | n/a — Node `.mjs` smoke pattern | `node --test linkedinHelpers.test.mjs` | YES (precedent: imagePositioning.test.mjs) | Match pattern |

### Phase Order (Reasoning)

| Order | Phase | Why this order |
|---|---|---|
| 1 | Drop Depth column | Zero risk, pure delete, ships standalone, satisfies fastest user ask |
| 2 | Add `generatingProgress` helper + unit test | Pure function, no UI yet — TDD-clean, sets up Phase 3 |
| 3 | Wire progress % into Queue UI | Depends on Phase 2; touches same file as Phase 1 (rebase order) |
| 4 | Backend caption EN-mode + tests | Independent of Phases 1–3; ships with same Portfolio_v2 PR |
| 5 | Plugin v0.6.0 + operator VPS deploy | Separate repo + manual gate; can ship same day or later |
| 6 | CLAUDE.md sync + commit | Closes the loop per CLAUDE.md "After Changes (MANDATORY)" rule |

### Phase Table

| Phase | Code Deliverable | Design Deliverable | Verification |
|---|---|---|---|
| 1 | LinkedInQueueList.vue (Depth col removed) | Per brainstorm (column drop, no new tokens) | Vite build + manual visual + grid alignment |
| 2 | linkedinHelpers.js (`generatingProgress` export) + .test.mjs | n/a (pure helper) | `node --test` passes 6 cases |
| 3 | LinkedInQueueList.vue (live ticker + suffix render) | Per brainstorm (`~N%` suffix on existing pill, no new color/layout) | Visual + ticker cleanup on unmount |
| 4 | LinkedInGenerationService.php (EN-mode caption builder) + LinkedInGenerationServiceCaptionTest.php | n/a (backend) | PHPUnit 5 new cases + 22 existing tests still pass |
| 5 | linkedin-post-writer v0.6.0 (plugin repo, separate PR) | n/a (no UI) | Plugin tests pass + operator VPS deploy verified by manual TEXT regenerate |
| 6 | CLAUDE.md updated entries | n/a | `git diff CLAUDE.md` shows Last Updated bump + new entries documented |

---

### Phase 1: Drop Depth column from Queue list

**Estimated time:** 10 minutes

**Files:**
- Modify: [`frontend/src/views/admin/LinkedInQueueList.vue`](frontend/src/views/admin/LinkedInQueueList.vue)

**Steps:**
1. Write failing test (visual smoke): open `/admin/linkedin-queue` in dev (`npm run dev`), confirm Depth column is currently visible. Expected error: **N/A — visual test only** (this is a pure-delete refactor; the failing-test gate is satisfied by Phase 2's unit test which precedes any helper logic). Document the pre-state with a screenshot or grid-template grep: `grep "70px_1fr_110px_130px" frontend/src/views/admin/LinkedInQueueList.vue` returns 2 hits (lines 298, 341).
2. Run `grep "depth" frontend/src/views/admin/LinkedInQueueList.vue` — confirm 4 occurrences: `sortValue` case (line 83), header button (lines 316–323), row cell (lines 379–389), `depthTone` usage on Virality (line 354 — KEEP this).
3. Edit grid template at line 298 + line 341: `grid-cols-[1fr_90px_130px_70px_1fr_110px_130px]` → `grid-cols-[1fr_90px_130px_1fr_110px_130px]` (drop the 70px column on both header and row).
4. Delete the Depth `<button>` header block (lines 316–323) AND the row `<div>` cell (lines 379–389). Keep `depthTone()` helper and its call on Virality column (line 354).
5. Delete `'depth'` case from `sortValue` switch (line 83). Default case still returns `null` for safety.
6. Run `npm run build` in `frontend/`. Confirm clean build (no Vue compile errors, no Tailwind purge warnings).
7. Manual visual: `npm run dev`, navigate to `/admin/linkedin-queue`, confirm: (a) no Depth column header, (b) row layout aligned (Source | Virality | Status | Issue | Published | Actions), (c) sort still works on remaining columns.
8. Commit: `feat(linkedin): drop Depth column from Queue list (carousels skip depth rubric, text drafts inspect via detail page)`

**Verification:**
- [ ] Vite production build passes (`npm run build` clean)
- [ ] No console errors when navigating to `/admin/linkedin-queue`
- [ ] Grid columns align (manual visual: header text aligns with row cells)
- [ ] Sort by Virality / Published / Source still works (sortValue 'depth' removal didn't break the switch)
- [ ] Detail page (`/admin/linkedin-drafts/:id`) still shows Depth — unchanged
- [ ] Posts list (`/admin/linkedin-posts`) still shows Depth chip — unchanged
- [ ] No placeholder/TODO comments in changed code

---

### Phase 2: Add `generatingProgress(draft)` helper + unit test

**Estimated time:** 15 minutes

**Files:**
- Modify: [`frontend/src/views/admin/linkedinHelpers.js`](frontend/src/views/admin/linkedinHelpers.js)
- Create: `frontend/src/views/admin/linkedinHelpers.test.mjs` (Node smoke test, matches `imagePositioning.test.mjs` precedent)

**Steps:**
1. Write failing test in `linkedinHelpers.test.mjs`. Expected error: **`Cannot find module './linkedinHelpers.js' export 'generatingProgress'`** (when running `node --test linkedinHelpers.test.mjs` against existing helpers.js without the export). Test cases (6 total):
   - `returns null for status='manual_review'` (out of scope)
   - `returns null for status='published'` (out of scope)
   - `returns null when pipeline_state_log is empty/missing`
   - `returns ~50% for text format, 30s elapsed since generating timestamp` (60s baseline)
   - `returns ~50% for carousel format, 180s elapsed` (360s baseline)
   - `caps at 95% for text format, 90s elapsed` (Math.min hard cap)
   - `returns 95–99 ramp for status='validating'` (8s validating baseline)
   - `returns null when entry.timestamp is malformed` (defensive `Number.isFinite` check)
2. Run test: `node --test frontend/src/views/admin/linkedinHelpers.test.mjs`. Confirm fail with the exact module-resolution error.
3. Implement `generatingProgress(draft)` export in [`linkedinHelpers.js`](frontend/src/views/admin/linkedinHelpers.js). Place near the bottom of the file (above the `ICON` block, after `formatDateTime`):

```js
const GEN_BASELINES_MS = {
  text:     { generating: 60_000,  validating: 8_000 },
  carousel: { generating: 360_000, validating: 8_000 },
}

/**
 * Synthetic progress percentage for drafts in `generating`/`validating`/
 * `pending_generation` status. Reads `pipeline_state_log[]` to find the
 * timestamp where the draft entered the current state and computes
 * elapsed/baseline. Format-aware baselines (text 60s, carousel 360s) reflect
 * observed P50 durations.
 *
 * Returns null for drafts not in a generating state, or when the timestamp
 * is missing/malformed (defensive — caller suppresses the % suffix).
 *
 * Hard-capped at 95% during generating so the operator never sees a
 * misleading "100%" on a still-running draft. Validating ramps 95→99.
 */
export function generatingProgress(draft) {
  if (!draft) return null
  const status = draft.status
  if (!['generating', 'validating', 'pending_generation'].includes(status)) {
    return null
  }
  const log = Array.isArray(draft.pipeline_state_log) ? draft.pipeline_state_log : []
  const target = status === 'validating' ? 'validating' : 'generating'
  // findLast — most recent transition into the target state
  const entry = [...log].reverse().find((e) => e?.to === target)
  if (!entry?.timestamp) return null
  const startedAt = new Date(entry.timestamp).getTime()
  if (!Number.isFinite(startedAt)) return null
  const elapsed = Date.now() - startedAt
  if (elapsed < 0) return null
  const baseline = GEN_BASELINES_MS[draft.format ?? 'text']?.[target] ?? 60_000
  const raw = (elapsed / baseline) * 100
  if (target === 'validating') {
    // 95 floor + ramp toward 99 over the validating baseline
    return Math.min(99, Math.max(95, Math.floor(95 + raw / 25)))
  }
  return Math.min(95, Math.max(0, Math.floor(raw)))
}
```

4. Run `node --test linkedinHelpers.test.mjs`. Confirm all 6 cases pass.
5. Commit: `feat(linkedin): add generatingProgress helper for queue % indicator`

**Verification:**
- [ ] `node --test frontend/src/views/admin/linkedinHelpers.test.mjs` passes (6/6)
- [ ] Helper handles malformed timestamp gracefully (no `NaN%` reaches UI)
- [ ] Returns `null` for non-generating statuses (caller can `v-if` cleanly)
- [ ] No new external dependencies imported (pure ES module)
- [ ] No placeholder/TODO comments

---

### Phase 3: Wire progress % into Queue list

**Estimated time:** 15 minutes

**Files:**
- Modify: [`frontend/src/views/admin/LinkedInQueueList.vue`](frontend/src/views/admin/LinkedInQueueList.vue)
- Modify: [`frontend/src/views/admin/linkedinHelpers.js`](frontend/src/views/admin/linkedinHelpers.js) (extend `effectiveStatusMeta` to optionally append progress suffix — keep helpers pure, render-side decides)

**Decision:** keep `generatingProgress()` and `effectiveStatusMeta()` independent — the Vue component composes them. This preserves single responsibility for both helpers and avoids coupling progress derivation to status-meta resolution. (Rejected alternative: adding `progressSuffix` field inside `effectiveStatusMeta` — would require passing `Date.now()` into a pure data lookup, smearing reactivity through helpers.js.)

**Steps:**
1. Write failing visual-spec assertion (manual): open dev `/admin/linkedin-queue` with a draft in `generating` status (use seeded data via `php artisan db:seed --class=LinkedInPostSeeder` per CLAUDE.md) — confirm pill currently shows just `GENERATING` (no `~N%`). Expected error: **N/A — visual smoke test** (Phase 2's unit test is the TDD gate for the underlying logic; this phase is wiring).
2. Import the new helper in `LinkedInQueueList.vue` script setup:
   ```js
   import {
     ...,
     generatingProgress,
   } from './linkedinHelpers'
   ```
3. Add a `tick` ref + `setInterval` for the live ticker (mirrors `LinkedInDraftDetail.vue` cancel-window pattern):
   ```js
   import { ref, onMounted, onBeforeUnmount } from 'vue'
   const tick = ref(0)
   let tickerHandle = null
   onMounted(() => {
     tickerHandle = setInterval(() => { tick.value++ }, 1000)
   })
   onBeforeUnmount(() => {
     if (tickerHandle) clearInterval(tickerHandle)
   })
   ```
4. Modify the status pill `<span>` block in the row template (around line 366–373) to compose the suffix. Reference `tick.value` once inside the computed expression so Vue re-renders every second:
   ```vue
   <span
     class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10px] font-mono uppercase tracking-[0.12em]"
     :class="MOOD_CLASSES[effectiveStatusMeta(draft).mood]?.chip"
   >
     <span class="w-1 h-1 rounded-full" :class="MOOD_CLASSES[effectiveStatusMeta(draft).mood]?.dot" />
     {{ effectiveStatusMeta(draft).short }}
     <template v-if="(tick, generatingProgress(draft)) !== null">
       <span class="opacity-70">~{{ generatingProgress(draft) }}%</span>
     </template>
   </span>
   ```
   Note: `(tick, generatingProgress(draft))` comma-operator pattern forces Vue's reactivity tracker to subscribe to `tick.value` while still returning the helper's result. This is the established pattern; alternative is wrapping in `computed()` per draft which would explode O(n×renders).
5. Run `npm run dev`. Manually trigger a draft regeneration to land in `generating` state (or use seeded data). Confirm:
   - `~N%` suffix appears next to `GENERATING` pill
   - Number increases over time (every 1s)
   - Suffix disappears when status flips to `manual_review`/`awaiting_publish`/`failed`
   - Suffix doesn't appear on `published` or `cancelled` rows
6. Run `npm run build`. Confirm clean build.
7. Verify ticker cleanup: navigate away from queue → DevTools Memory tab → confirm no leaked `setInterval` (orphan timer would persist after route change).
8. Commit: `feat(linkedin): show generating progress % in Queue with live ticker`

**Verification:**
- [ ] Vite production build passes
- [ ] `~N%` suffix renders ONLY for status ∈ {pending_generation, generating, validating}
- [ ] Suffix updates every second (visible counter increment within 5s)
- [ ] Suffix disappears on status transition (no stuck `~95%` on old data)
- [ ] `setInterval` cleanup verified in DevTools (no orphan timer after route change)
- [ ] Existing sort + filter still works
- [ ] Carousel rows in synthetic `carousel_render_*` states do NOT show suffix (those are not in the generatingProgress allow-list)
- [ ] No placeholder/TODO comments

---

### Phase 4: Backend caption EN-mode + tests

**Estimated time:** 25 minutes

**Files:**
- Modify: [`backend/app/Services/LinkedInGenerationService.php`](backend/app/Services/LinkedInGenerationService.php) — methods `buildCarouselCaption` (line 1090), `extractSetupParagraph` (line 1219), `extractInsightsFromSlides` (line 1278)
- Create: `backend/tests/Unit/LinkedInGenerationServiceCaptionTest.php` (matches existing `LinkedInGenerationServiceParseTest.php` pattern)

**Steps:**
1. Write failing test in new file `backend/tests/Unit/LinkedInGenerationServiceCaptionTest.php`. Expected error: **`Failed asserting that '...' contains 'What\'s really happening behind the scenes?'`** (test asserts English engagement question default, but current code emits Indonesian fallback). Test cases (5):
   - `test_carousel_caption_uses_english_hook_from_copy_en` — given carousel slides with `copy_id`/`copy_en` populated, hook block uses `copy_en`
   - `test_carousel_caption_drops_subtitle_block_to_avoid_duplication` — output does not contain `cover.copy_en` as a separate block (it's already the hook)
   - `test_setup_paragraph_prefers_english_excerpt_when_available` — given a draft with `post.translations.en.excerpt` set, setup uses it (not `post.translations.id.excerpt`)
   - `test_setup_paragraph_falls_back_to_slide_copy_en_when_no_en_translation` — when EN translation missing, falls back to non-cover slide `copy_en`
   - `test_engagement_question_english_default` — when `brief.engagement_question` empty, fallback string is "What's really happening behind the scenes?" not Indonesian
   - `test_swipe_cta_is_english` — output contains `"Swipe → for the full breakdown."` (or chosen English variant), not `"Swipe → untuk breakdown lengkap."`
2. Run `D:\xampp\php\php.exe artisan test --filter=LinkedInGenerationServiceCaption`. Confirm all 5 cases fail with the expected substring assertion errors.
3. Modify [`buildCarouselCaption`](backend/app/Services/LinkedInGenerationService.php#L1090):
   - Line 1109: Hook block — change `(string) ($coverSlide['copy_id'] ?? $coverSlide['copy'] ?? '')` to `(string) ($coverSlide['copy_en'] ?? $coverSlide['copy'] ?? '')` (prefer EN, fall back to legacy `copy` field for pre-bilingual drafts)
   - Lines 1113–1117: DELETE the subtitle block entirely (the `$subtitle = trim((string) ($coverSlide['copy_en'] ?? ''));` and its conditional append). The new hook IS the EN copy, so the subtitle would duplicate.
   - Line 1140: Engagement question fallback — change literal Indonesian to:
     ```php
     $question = trim((string) ($brief['engagement_question'] ?? $brief['question'] ?? ''));
     if ($question === '') {
         $question = "What's really happening behind the scenes?";
     }
     ```
   - Line 1161: Swipe CTA — change `"{$question}\n\nSwipe → untuk breakdown lengkap."` to `"{$question}\n\nSwipe → for the full breakdown."`
4. Modify [`extractSetupParagraph`](backend/app/Services/LinkedInGenerationService.php#L1219):
   - Add EN translation preference at top:
     ```php
     // Prefer EN excerpt when available — caption is now English-only per
     // operator decision. Falls back to ID excerpt if EN missing, then
     // to slide-derived setup. See docs/plans/2026-05-06-linkedin-en-...
     $enExcerpt = $draft->post?->translations?->firstWhere('locale', 'en')?->excerpt;
     if (is_string($enExcerpt) && trim($enExcerpt) !== '') {
         return trim($enExcerpt);
     }
     ```
   - Line 1251: When iterating non-cover slides, change `$s['copy_id']` to `$s['copy_en']` preference (with fallback chain: `copy_en` → `copy` → `copy_id`).
5. Modify [`extractInsightsFromSlides`](backend/app/Services/LinkedInGenerationService.php#L1278):
   - Line 1288: Change `(string) ($s['headline_id'] ?? $s['copy_id'] ?? $s['copy'] ?? $s['headline'] ?? '');` to `(string) ($s['headline_en'] ?? $s['copy_en'] ?? $s['copy'] ?? $s['headline'] ?? '');`
6. Run `D:\xampp\php\php.exe artisan test --filter=LinkedInGenerationServiceCaption`. Confirm all 5 new cases pass.
7. Run full LinkedIn test suite: `D:\xampp\php\php.exe artisan test --filter=LinkedIn`. Confirm 22+ existing tests still pass (no regression in `LinkedInGenerationServiceParseTest`, `LinkedInGenerationServiceCarouselGenRouterTest`, etc.).
8. Run `D:\xampp\php\php.exe -l backend/app/Services/LinkedInGenerationService.php`. Confirm syntax clean.
9. Commit: `feat(linkedin): switch caption builder to English (carousel slides stay bilingual)`

**Verification:**
- [ ] All 5 new test cases pass
- [ ] All existing LinkedIn tests pass (`--filter=LinkedIn` 22+ tests, zero failures)
- [ ] `php -l` syntax clean on the service
- [ ] `extractSetupParagraph` handles missing `post.translations.en` gracefully (falls back, no null deref)
- [ ] No regression in carousel slide rendering pipeline (`/carousel-gen` still produces bilingual slide JSON — caption builder only changes how that JSON gets surfaced into post body)
- [ ] `last_error` not set on existing carousel drafts during test (defensive — re-running caption builder should be idempotent and side-effect-free)
- [ ] No placeholder/TODO comments

---

### Phase 5: Plugin v0.6.0 — English text authoring + envelope flag

**Estimated time:** 45 minutes (plugin work) + 10 minutes (operator VPS deploy)

**Files (separate repo `D:\Projects\claude-plugin\linkedin-post-writer`):**
- Modify: `docs/rag/linkedin-playbook/02-formats.md` (or equivalent — formats reference)
- Modify: `docs/rag/linkedin-playbook/03-templates.md` (templates reference — change Indonesian sample posts to English)
- Modify: `skills/linkedin-gen/schema.ts` (add optional `caption_language: z.enum(['en','id']).optional()` field)
- Modify: `skills/linkedin-gen/SKILL.md` (operator instructions: "Author all TEXT-format post.content in English. Default `caption_language='en'` in envelope.")
- Modify: `package.json` version bump 0.5.0 → 0.6.0
- Run: `npm run compile-refs` to regenerate `refs-linkedin-playbook.md`, `refs-linkedin-templates.md`, `refs-linkedin-formats.md`
- Plugin tests in `__tests__/` — update fixtures expecting English output

**Steps:**
1. In plugin repo, write failing schema test. Expected error: **`zodError: caption_language is required`** (initially make it required, then loosen to optional after seeing the test pass on the strict version). Test asserts `OrchestratorOutput.parse({...}).caption_language === 'en'`.
2. Update `schema.ts` with `caption_language: z.enum(['en','id']).default('en')`. Run schema test, confirm pass.
3. Edit Indonesian-language samples in `templates.md` to English equivalents. Re-author 5–10 sample posts that currently demonstrate ID hooks/setups — replace with English idiomatic equivalents.
4. Edit `formats.md` text-format spec — add explicit "Authoring language: English" directive at top. Document `caption_language` envelope field.
5. Update `SKILL.md` step-by-step instructions: prepend "Step 0: All TEXT-format `post.content` must be authored in English. Set `caption_language: 'en'` in the output envelope."
6. Update fixtures in `__tests__/fixtures/*.json` — change ID literals to EN equivalents.
7. Run plugin test suite (`bun test` or `npm test` per repo convention). Confirm 221+ tests pass (per CLAUDE.md v0.5.0 baseline).
8. `npm run compile-refs`. Verify 3 bundles emitted (per CLAUDE.md v0.5.0 — carousel bundle retired): `refs-linkedin-playbook.md`, `refs-linkedin-templates.md`, `refs-linkedin-formats.md`.
9. Commit + tag: `git tag v0.6.0 && git push --tags origin main`
10. **Operator VPS deploy gate** (manual step — NOT in deploy.sh):
    ```bash
    # On VPS as claudesn:
    cd ~/.claude/plugins/cache/linkedin-post-writer/
    git fetch origin && git checkout v0.6.0
    npm install
    npm run compile-refs
    # Bundles output to dist/ (or repo-defined location)
    cp dist/refs-linkedin-{playbook,templates,formats}.md /home/claudesn/
    sudo systemctl restart portfolio-queue.service
    # Verify:
    claude -p "/linkedin-gen --help" | grep -i 'caption_language\|english'
    ```
11. Smoke test on production: trigger a TEXT draft regeneration via `POST /api/admin/linkedin-drafts/{id}/regenerate` for a known TEXT draft. Wait ~60s. Confirm new draft's `post.content` is in English.
12. Commit on Portfolio_v2 side (no code change — just CLAUDE.md doc update happens in Phase 6).

**Verification:**
- [ ] Plugin tests pass (221+ cases per v0.5.0 baseline)
- [ ] `npm run compile-refs` emits exactly 3 bundles (no carousel bundle)
- [ ] Schema accepts `caption_language: 'en'` (default) and `'id'` (legacy override)
- [ ] VPS bundles deployed (`ls -la /home/claudesn/refs-linkedin-*.md` shows updated mtimes)
- [ ] `systemctl is-active portfolio-queue.service` returns active
- [ ] Manual TEXT regeneration produces English `post.content` (assert via DB query: `SELECT content FROM linkedin_posts WHERE id=X` shows English)
- [ ] No regression in carousel format (carousel drafts still route to `/carousel-gen` and produce bilingual slides)
- [ ] Plugin git tag `v0.6.0` exists on `origin/main`

---

### Phase 6: CLAUDE.md sync + final commit

**Estimated time:** 15 minutes

**Files:**
- Modify: [`D:\Projects\Portfolio_v2\CLAUDE.md`](CLAUDE.md)

**Steps:**
1. Add new entry to "Last Updated" rolling log at bottom (single-paragraph format per existing convention). Cover: 3-issue ship summary, files changed count (8: 1 backend service + 1 backend test + 2 frontend (helpers + queue view) + 1 frontend test + 1 plugin schema + 2 plugin docs), plugin v0.6.0 deploy gate as operator manual step.
2. Update "LinkedIn Plugin Integration Environment Variables" section if Phase 5 added new env vars. (Likely none — `caption_language` is plugin-internal.)
3. If progress baseline constants need to be tunable in future, document them (line ref `linkedinHelpers.js GEN_BASELINES_MS`).
4. Update LinkedIn Admin UI Pipeline section — note that Queue list no longer shows Depth column, document the `~N%` progress suffix derivation source (`pipeline_state_log[]`).
5. Bump "Last Updated" date to 2026-05-06.
6. Commit: `docs(linkedin): document EN caption + queue progress % + depth col removal`
7. Final consolidated PR commit message (or single bundled commit per CLAUDE.md "Git Push Policy"): operator chooses git-push timing per repo policy.

**Verification:**
- [ ] `git diff CLAUDE.md` shows new entry + Last Updated bump
- [ ] No links broken (relative paths to backend/frontend files resolve)
- [ ] Phase 5 deploy gate explicitly documented as operator manual step (not auto-CI)
- [ ] All 6 phases checked off in operator's mental model

---

### Cross-phase Quality Gate

Before declaring the plan complete (after Phase 6):

- [ ] All phases 1–4 land in single Portfolio_v2 PR (one commit each, or one squashed commit per phase, operator preference)
- [ ] Phase 5 lands in plugin repo PR (separate)
- [ ] CLAUDE.md "Last Updated" reflects today's date and summarizes all 3 issues addressed
- [ ] No placeholder strings (`TODO`, `FIXME`, `XXX`, `lorem`, hardcoded "test data") in any changed file
- [ ] No backend secrets logged
- [ ] Frontend bundle size impact: <1KB added (new helper is ~30 LOC)
- [ ] Backend test count increases by 5 (caption test cases), no decrease in any existing suite
- [ ] Plugin test count unchanged or increased (no removed tests)
- [ ] Manual smoke verified on dev: regenerate a draft, watch progress %, see EN caption (after Phase 5 deploy)

---

### Execution Handoff

Three options after plan approval:

**Option 1 — Execute in this session:**
> Run `/gaspol-dev:gaspol-execute` and start Phase 1. Phases 1–4 are sequential within Portfolio_v2 PR; ~65 min total estimated implementation time.

**Option 2 — Parallel execution:**
> Phases **1, 2, 4** are independent (no shared files among Phase 1 view template + Phase 2 helpers.js + Phase 4 backend service). Run `/gaspol-dev:gaspol-parallel mode=plan-phases` to dispatch 3 fresh subagents. Phase 3 must wait for Phase 2 (depends on the helper export). Phase 5 is a separate repo and cannot be parallelized via this plan.

**Option 3 — Save for new session:**
> Plan is fully self-contained at [docs/plans/2026-05-06-linkedin-en-caption-and-queue-polish.md](docs/plans/2026-05-06-linkedin-en-caption-and-queue-polish.md). Future session reads CLAUDE.md + this file + Architecture Context section above and has zero-prep onboarding.


