> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

# Phase C — Trim Scoring to Mechanical-Only — Implementation Plan

**Parent design:** [2026-04-19-viral-first-content-pipeline-v3.md](2026-04-19-viral-first-content-pipeline-v3.md) §Phase C
**Depends on:** Phase A shipped ([phase-a plan](2026-04-19-viral-first-content-pipeline-v3-phase-a-plan.md), 11 commits local)
**Scope:** Phase C only. Phase B (Tiered Deep Research + Skill Split) deferred to a separate plan doc.
**Target branch:** `feat/phase-c-mechanical-scoring`

## Goal

Cut article generation pipeline time by ~10% (from ~11 min to ~10 min on average) by skipping the `/article-score` AI step on the default path. Mechanical scoring already exists ([MechanicalScoringService](backend/app/Services/MechanicalScoringService.php)) and runs in pure PHP — zero AI cost, ~50ms latency, fully deterministic. When `ARTICLE_GEN_USE_SCORE_PHASE=false` (default after this phase), `/article-write` completion triggers mechanical snapshot + flip to `article_ready` directly, skipping the Sonnet score call. Users who want deep AI scoring get an opt-in "Run Deep Quality Analysis" button in the Article Preview modal that dispatches the existing `/article-score` skill on demand, reusing the existing progress polling.

## Architecture Context

Pulled from [root CLAUDE.md](../../CLAUDE.md) + [backend/CLAUDE.md](../../backend/CLAUDE.md) + code exploration:

**Existing code to reuse (no reinvention):**
- [MechanicalScoringService::analyze()](backend/app/Services/MechanicalScoringService.php#L56) — returns full mechanical metric struct: `{word_count, seo: {title_length, keyword_in_title, title_word_count, body_keyword_density, keyword_in_first_100, keyword_in_headings}, freshness_signals, faq_pair_count, h2_count, h3_count, ai_humanization: {tier1_violations, tier1_violation_count, tier2_clusters, tier3_density_issues}, language}`. Pure, deterministic, English + Indonesian aware.
- [ArticleGenerationService::triggerScore()](backend/app/Services/ArticleGenerationService.php#L97) — existing dispatcher for `/article-score` skill. Phase C reuses it inside the new `run-deep-score` endpoint.
- [routes/api.php continue-pipeline](backend/routes/api.php#L652) — public automation route; lines 736-741 is the `triggerScore` dispatch branch Phase C gates.
- [routes/api.php mechanical-scores GET](backend/routes/api.php#L560) — on-demand endpoint `GET /automation/content-ideas/{id}/mechanical-scores` already wraps MechanicalScoringService. Phase C reuses the same analyze call inline but stores the result on the idea instead of returning live.
- [ArticlePreview.vue](frontend/src/views/admin/ArticlePreview.vue) — existing side panel toggled by `showSeoPanel` handles live SEO analysis. Phase C adds a NEW inline scorecard at the top of the article column (distinct from the side panel) that reads `idea.mechanical_scores_snapshot` — no live compute, no flicker.
- [useContentEngine.js getProgress()](frontend/src/composables/useContentEngine.js#L71) — existing progress polling. Reused unchanged for deep-score progress.
- [config/services.php use_images_phase](backend/config/services.php#L80) — env-flag pattern to mirror for `use_score_phase`.

**Existing patterns (follow verbatim):**
- Config env flag: `env('ARTICLE_GEN_USE_SCORE_PHASE', false)` → `config('services.article_generation.use_score_phase')` (same as `use_images_phase` / `use_translate_phase`).
- Admin endpoint convention: add route inside `Route::middleware(['auth:sanctum'])->prefix('admin/content-engine')` group at [routes/api.php L783](backend/routes/api.php#L783).
- API envelope: `response()->json(['success' => true|false, 'data' => ..., 'message' => ...])`.
- Migration naming: `YYYY_MM_DD_HHMMSS_<description>.php`. Use `2026_04_19_110000_add_mechanical_scores_snapshot_to_content_ideas.php`.

**Testing stack:**
- PHPUnit via `php artisan test` (Windows XAMPP PHP at `/d/xampp/php/php.exe`)
- Mockery for DI swaps; reuse `app()->instance()` pattern proven in Phase A
- Feature tests should scope `url()->forceRootUrl('http://localhost')` in `setUp()` to sidestep the APP_URL subpath quirk documented in Phase A.6
- Frontend: no existing Vitest suite for admin views — rely on Vite build (syntactic check) + manual browser smoke test per CLAUDE.md

## Tech Stack

- **Backend:** Laravel 12, PHP 8.2. Use existing `MechanicalScoringService` (no new service needed). PHPUnit + Mockery for tests.
- **Frontend:** Vue 3.5 Composition API, TailwindCSS 4. Extend [ArticlePreview.vue](frontend/src/views/admin/ArticlePreview.vue) + [useContentEngine.js](frontend/src/composables/useContentEngine.js) only — no new components or libraries.
- **Database:** MySQL 8. Additive nullable `json` column on `content_ideas`. No rollback risk.

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Mechanical metric compute | `MechanicalScoringService::analyze(title, content, keyword, options)` | Service method | Yes | Use directly — do NOT re-implement |
| Snapshot persistence | `content_ideas.mechanical_scores_snapshot` (json nullable) | Eloquent | No | Add via migration + `$casts` |
| Env flag | `config('services.article_generation.use_score_phase')` | Laravel config | No | Add to `config/services.php` + `.env.example` |
| Pipeline gate | `routes/api.php` continue-pipeline L736-741 | Inline route closure | Yes (extend) | Wrap `triggerScore` in flag check; when off, inline mechanical snapshot + `article_ready` |
| Deep-score trigger | `ArticleGenerationService::triggerScore($ideaId)` | Service method | Yes | Reuse unchanged inside new controller method |
| Deep-score endpoint | `POST /api/admin/content-engine/ideas/{id}/run-deep-score` | `ContentIdeaController::runDeepScore` | No | Add controller method + route |
| Deep-score progress | `GET /api/admin/content-engine/ideas/{id}/progress` | Existing `getProgress` route | Yes | Reuse — no change |
| Frontend composable | `useContentEngine.runDeepScore(id)` | Axios via `request()` wrapper | Yes (extend) | Add method + export |
| Frontend composable | `useContentEngine.getMechanicalScores(id)` | Axios via `request()` wrapper | Yes (extend) | Add method + export (for live recompute button, optional) |
| Scorecard UI | `ArticlePreview.vue` inline section | Vue template | Yes (extend) | Add scorecard block reading `idea.mechanical_scores_snapshot` |
| Deep Analysis button | `ArticlePreview.vue` + progress polling | Vue template + existing `getProgress` | Yes (extend) | Add button + progress modal wiring |

**Contract:** Every "Yes" row uses the existing integration directly. Every "No" row produces a real working integration, never a placeholder. If during execution any "No" row turns out to depend on a piece that doesn't exist, STOP and ask.

## Phases

### Phase C.1 — Migration + Model Cast (mechanical_scores_snapshot column)

**Estimated time:** 5 minutes

**Files:**
- Create: `backend/database/migrations/2026_04_19_110000_add_mechanical_scores_snapshot_to_content_ideas.php`
- Modify: `backend/app/Models/ContentIdea.php` (fillable + casts)
- Test: `backend/tests/Feature/MechanicalScoresSnapshotMigrationTest.php`

**Steps:**
1. Write failing test for persistence. Expected error: `Failed asserting that null matches expected array` when asserting `$idea->mechanical_scores_snapshot` on a fresh `ContentIdea::create(['mechanical_scores_snapshot' => [...]])` call. Test body: persist an idea with a representative snapshot (SEO block + faq count + tier1 count), read it back, assert JSON cast returns array with nested keys intact.
2. Run test, confirm it fails: `php artisan test --filter=MechanicalScoresSnapshotMigrationTest`.
3. Create migration: add `$table->json('mechanical_scores_snapshot')->nullable()->after('virality_breakdown')`. Run `php artisan migrate`.
4. Modify [ContentIdea.php](backend/app/Models/ContentIdea.php): append `'mechanical_scores_snapshot'` to `$fillable` (after `'virality_breakdown'`); append `'mechanical_scores_snapshot' => 'array'` to `$casts`.
5. Re-run test, confirm pass.
6. Also add a second test case: legacy idea (no snapshot key) → field is null; no crash on read.
7. Commit: `feat(content-engine): add mechanical_scores_snapshot column to content_ideas`

**Verification:**
- [ ] `php artisan migrate` runs clean (adds column after `virality_breakdown` for neat ordering)
- [ ] `ContentIdea::create(['title' => 'x', 'mechanical_scores_snapshot' => ['seo' => [...]]])` persists and reads back as array
- [ ] Null-tolerant: legacy rows return null (no exception)
- [ ] Rollback `php artisan migrate:rollback --step=1` drops the column cleanly
- [ ] No placeholder/TODO comments

---

### Phase C.2 — Config + Env Flag Scaffolding

**Estimated time:** 4 minutes

**Files:**
- Modify: `backend/config/services.php` (add `use_score_phase` under `article_generation`)
- Modify: `backend/.env.example` (add `ARTICLE_GEN_USE_SCORE_PHASE=false` with comment)
- Test: `backend/tests/Unit/ArticleGenerationConfigTest.php`

**Steps:**
1. Write failing test for config resolution. Expected error: `Failed asserting that null is false` when asserting `config('services.article_generation.use_score_phase') === false`. Test body: assert default is `false` when env not set; assert `true` when `config()->set(...)` flips it.
2. Run test, confirm failure: `php artisan test --filter=ArticleGenerationConfigTest`.
3. Edit [config/services.php](backend/config/services.php) — inside `'article_generation' => [...]` array, next to `'use_images_phase' => env('ARTICLE_GEN_USE_IMAGES_PHASE', false)`, add:
   ```php
   'use_score_phase' => env('ARTICLE_GEN_USE_SCORE_PHASE', false),
   ```
4. Edit [backend/.env.example](backend/.env.example) — next to `ARTICLE_GEN_USE_IMAGES_PHASE=false`, add `ARTICLE_GEN_USE_SCORE_PHASE=false` with a one-line comment: `# Gate /article-score AI step (default off → mechanical snapshot only, saves ~1 min/article)`.
5. Run test, confirm pass.
6. Commit: `feat(content-engine): add ARTICLE_GEN_USE_SCORE_PHASE env flag (default off)`

**Verification:**
- [ ] `config('services.article_generation.use_score_phase')` returns `false` by default
- [ ] Env override works: `ARTICLE_GEN_USE_SCORE_PHASE=true php artisan tinker` → true
- [ ] `.env.example` documents the new flag with rationale comment
- [ ] Convention matches existing `use_images_phase` + `use_translate_phase` flags
- [ ] No placeholder/TODO comments

---

### Phase C.3 — MechanicalSnapshotWriter Helper

**Estimated time:** 8 minutes

**Files:**
- Create: `backend/app/Services/MechanicalSnapshotWriter.php` (thin wrapper around MechanicalScoringService)
- Create: `backend/tests/Unit/MechanicalSnapshotWriterTest.php`

**Steps:**
1. Write failing test for `MechanicalSnapshotWriter::captureFor(ContentIdea $idea): array`. Expected error: `Error: Class "App\Services\MechanicalSnapshotWriter" not found`. Test body: build a `ContentIdea` with `generated_article = ['title' => 'Best AI Coding Tools 2026', 'content' => '<h2>Intro</h2><p>We discuss ai coding tools.</p>', 'keyword' => 'ai coding tools', 'language' => 'en']`, save to DB, call `captureFor($idea)`, assert: (a) return value is the full mechanical snapshot array with `seo`, `ai_humanization`, `faq_pair_count`, `freshness_signals`, `word_count` keys; (b) `$idea->fresh()->mechanical_scores_snapshot` equals the returned array (persisted side-effect); (c) a `captured_at` ISO-8601 timestamp is present on the stored snapshot for provenance.
2. Run test, confirm failure.
3. Implement `MechanicalSnapshotWriter::captureFor()`:
   - Extracts `title`, `content`, `keyword`, `language` from `$idea->generated_article` using `data_get()` (same fallback chain as existing `mechanical-scores` route at L566-569).
   - If title or content missing, returns early with `['error' => 'missing article fields']` and does NOT persist.
   - Calls `app(MechanicalScoringService::class)->analyze($title, $content, $keyword, ['language' => $language, 'current_year' => (int) date('Y')])`.
   - Wraps the result in `['captured_at' => now()->toIso8601String(), ...mechanicalResult]`.
   - Persists via `$idea->update(['mechanical_scores_snapshot' => $payload])`.
   - Returns the payload.
4. Add 3 more tests: (a) missing-title idea → returns error, does NOT persist, does NOT throw; (b) Indonesian article (language=id) → ai_humanization block includes the language-specific `note` field per [MechanicalScoringService L140](backend/app/Services/MechanicalScoringService.php#L140); (c) re-running `captureFor` on the same idea overwrites the previous snapshot (idempotent refresh).
5. Run tests, confirm pass.
6. Commit: `feat(content-engine): add MechanicalSnapshotWriter to persist scorecards`

**Verification:**
- [ ] `php artisan test --filter=MechanicalSnapshotWriterTest` passes with 4+ tests
- [ ] Service resolvable via `app(MechanicalSnapshotWriter::class)`
- [ ] Delegates pure scoring to existing `MechanicalScoringService` (no duplication)
- [ ] Missing-field case returns error instead of throwing
- [ ] Snapshot includes `captured_at` for audit trail
- [ ] No placeholder/TODO comments

---

### Phase C.4 — Gate /article-score in continue-pipeline

**Estimated time:** 10 minutes

**Files:**
- Modify: `backend/routes/api.php` (wrap triggerScore branch at L736-741 in env-flag check)
- Create: `backend/tests/Feature/ContinuePipelineScoreGatingTest.php`

**Steps:**
1. Write failing test for gated dispatch. Expected error: when `config('services.article_generation.use_score_phase')` is `false`, POSTing to `/automation/content-ideas/{id}/continue-pipeline` for an idea at progress 85 should return `next_phase: score_skipped` and the idea should flip to `status=article_ready, progress_percentage=100` with `mechanical_scores_snapshot` populated — current code unconditionally calls `triggerScore` so test fails with `Failed asserting that 'score' matches expected 'score_skipped'`. Test body uses an authenticated automation request, seeds idea with progress=85 + valid `generated_article`, asserts JSON response + DB state.
2. Write second test (flag ON): when `ARTICLE_GEN_USE_SCORE_PHASE=true`, response should still be `next_phase: score` (unchanged legacy behavior) and triggerScore should be called — use Mockery on `ArticleGenerationService` to assert call count. Also fails on current code because the endpoint uses `app(\App\Services\...)` ad-hoc, but becomes mockable once we refactor to inject via container binding.
3. Run both tests, confirm they fail for the expected reasons.
4. Edit [routes/api.php L727-741](backend/routes/api.php#L727-L741): replace the score branch:
   ```php
   // Write done (85%) → trigger score OR skip based on env flag
   if ($progress >= 85 && $progress < 100) {
       $useScorePhase = (bool) config('services.article_generation.use_score_phase', false);

       if (!$useScorePhase) {
           // Phase C default: skip AI score, snapshot mechanical metrics, flip to article_ready.
           $snapshot = app(\App\Services\MechanicalSnapshotWriter::class)->captureFor($idea);
           $idea->update([
               'status' => 'article_ready',
               'progress_percentage' => 100,
               'current_step' => 'mechanical_snapshot',
               'process_pid' => null,
           ]);
           return response()->json([
               'success' => true,
               'next_phase' => 'score_skipped',
               'mechanical_snapshot_saved' => !isset($snapshot['error']),
           ]);
       }

       $result = $service->triggerScore($idea->id);
       $idea->update(['process_pid' => $result['pid']]);
       return response()->json(['success' => true, 'next_phase' => 'score', 'pid' => $result['pid']]);
   }
   ```
5. Re-run tests, confirm both pass.
6. Add regression test: legacy `article_ready` transition still works downstream — pick an existing passing flow (e.g. `AutoPipelineOrchestrator` image dispatch advances from `article_ready`) and assert it still fires after the skipped path.
7. Commit: `feat(content-engine): gate /article-score behind ARTICLE_GEN_USE_SCORE_PHASE env flag`

**Verification:**
- [ ] With flag OFF (default): write-done → mechanical snapshot captured + status `article_ready` + progress 100, `triggerScore` NOT called
- [ ] With flag ON: legacy path preserved — `triggerScore` invoked exactly once, response unchanged
- [ ] Skipped-path snapshot is readable via `$idea->fresh()->mechanical_scores_snapshot`
- [ ] Downstream image dispatch (`article_ready → generating_images`) still fires after skip
- [ ] Idea `current_step` set to `mechanical_snapshot` so progress UI can distinguish skip path from legacy
- [ ] No placeholder/TODO comments

---

### Phase C.5 — POST /ideas/{id}/run-deep-score endpoint

**Estimated time:** 8 minutes

**Files:**
- Modify: `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php` (add `runDeepScore` method)
- Modify: `backend/routes/api.php` (add route inside admin content-engine group at L783)
- Create: `backend/tests/Feature/RunDeepScoreEndpointTest.php`

**Steps:**
1. Write failing test. Expected error: `Expected response status code [200] but received 404` — POST `/api/admin/content-engine/ideas/{id}/run-deep-score` hits 404 because route isn't registered. Test body: auth admin, seed idea in `article_ready` with `generated_article`, Mockery-bind `ArticleGenerationService::triggerScore` to return `['success' => true, 'pid' => 12345, 'error' => null]`, POST endpoint, assert 200 response + `pid: 12345` + idea status flipped to `researching` progress 85 current_step `deep_scoring` so existing `/progress` polling picks it up.
2. Write second test: idea not in `article_ready` status → 409 response `Cannot run deep score: idea must be in article_ready status (current: ...)`.
3. Write third test: Sonnet trigger fails (`success: false`) → 500 response with error message surfaced; idea status unchanged.
4. Write fourth test: unauthenticated → 401.
5. Run all four, confirm failure.
6. Add route in [routes/api.php admin content-engine group L783](backend/routes/api.php#L783), next to existing pipeline routes:
   ```php
   Route::post('/ideas/{id}/run-deep-score', [ContentIdeaController::class, 'runDeepScore']);
   ```
7. Implement `ContentIdeaController::runDeepScore($id)`:
   - Find idea or 404
   - Validate status === 'article_ready' (409 otherwise)
   - Call `$this->articleGen->triggerScore($idea->id)` (existing method, already DI'd)
   - On success: update idea `status=researching, progress_percentage=85, current_step=deep_scoring, process_pid=$result['pid']`, push progress_log entry with `step: deep_scoring_started`
   - On failure: return 500 with `$result['error']`; leave idea unchanged
   - Success response: `response()->json(['success' => true, 'data' => ['pid' => $result['pid']]])`
8. Re-run tests, confirm pass.
9. Commit: `feat(content-engine): add POST /ideas/{id}/run-deep-score endpoint`

**Verification:**
- [ ] `php artisan route:list | grep run-deep-score` shows POST under `auth:sanctum`
- [ ] Status guard enforces `article_ready` precondition
- [ ] Reuses `ArticleGenerationService::triggerScore()` unchanged — no duplicate Sonnet dispatch code
- [ ] After trigger, existing `/ideas/{id}/progress` endpoint reports the deep-score progress (no new polling surface)
- [ ] 401 on unauthenticated, 409 on bad status, 500 on dispatch error, 200 on success
- [ ] No placeholder/TODO comments

---

### Phase C.6 — Composable methods (getMechanicalScores, runDeepScore)

**Estimated time:** 5 minutes

**Files:**
- Modify: `frontend/src/composables/useContentEngine.js` (add 2 methods + exports)

**Design Deliverable:** n/a (composable, no UI)

**Steps:**
1. Write failing smoke-test expectation. Expected error: `TypeError: runDeepScore is not a function` when evaluated in browser DevTools from `/admin/content-engine/ideas/{id}/preview` with the composable return object destructured. Smoke test: open Vue DevTools, inspect composable — `runDeepScore` and `getMechanicalScores` keys must exist.
2. Open [useContentEngine.js L71](frontend/src/composables/useContentEngine.js#L71) near `getProgress`. Add two methods:
   ```javascript
   const getMechanicalScores = (id) =>
     request('get', `/automation/content-ideas/${id}/mechanical-scores`)

   const runDeepScore = (id) =>
     request('post', `/admin/content-engine/ideas/${id}/run-deep-score`)
   ```
   Note: `getMechanicalScores` hits the existing automation route (public-auth via token OR session, matches how other automation reads work); `runDeepScore` hits the admin guarded route.
3. Add both keys to the returned object near [L148](frontend/src/composables/useContentEngine.js#L148). Alphabetical position next to `getProgress` + `approveArticle`.
4. Manual smoke test: in browser DevTools console from preview page, call `useContentEngine().runDeepScore(42)` → observe promise resolving with `{success: ..., data: {...}}`.
5. Commit: `feat(content-engine): add getMechanicalScores + runDeepScore composable methods`

**Verification:**
- [ ] Both methods exported from composable
- [ ] Browser console smoke test returns resolved promise
- [ ] Method style matches existing wrappers (request helper, same error handling)
- [ ] No placeholder/TODO comments

---

### Phase C.7 — ArticlePreview.vue Scorecard Section

**Estimated time:** 14 minutes

**Files:**
- Modify: `frontend/src/views/admin/ArticlePreview.vue` (add scorecard block + supporting computed props)

**Design Deliverable:** Inline mockup (no new design tokens — uses existing amber/emerald/red traffic-light pattern already used in [AutomationTokens.vue](frontend/src/views/admin/AutomationTokens.vue) status chips and Phase A trending badges)

```
Article Preview header → Title editor → [NEW: Mechanical Scorecard] → Content

┌─ Mechanical Scorecard ─────────────────────────────────────────────────┐
│  SEO (6 metrics):                                                      │
│   [●🟢 Title 52c] [●🟢 Keyword in Title] [●🟡 Title 11 words]          │
│   [●🟢 Density 1.2%] [●🟢 In first 100] [●🟢 In 2 headings]            │
│                                                                        │
│  GEO signals:                                                          │
│   [📅 3 freshness signals] [❓ 5 FAQ pairs] [📝 8 H2 · 12 H3]          │
│                                                                        │
│  AI Humanization:                                                      │
│   [🚫 Tier 1: 0 violations] [⚠️ Tier 2: 1 cluster] [✓ Tier 3: clean]   │
│                                                                        │
│  ┌─────────────────────────────────────────────────────────────────┐   │
│  │  🔬 Run Deep Quality Analysis (5-gate AI score, ~1 min, opt-in)│   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                        │
│  ── OR when deep score has run ──                                      │
│  ┌─ Deep Analysis Results (scored 2m ago) ──────────────────────────┐  │
│  │  Quality 8/10 · Virality 4/5 · AI Humanization 85/100 · GEO 4/5 │  │
│  │  Combined: 82 / 100  [Re-run]                                    │  │
│  └──────────────────────────────────────────────────────────────────┘  │
└────────────────────────────────────────────────────────────────────────┘

Chip color rules (match MechanicalScoringService traffic-light status):
  green  → bg-emerald-500/15 text-emerald-600 border-emerald-500/30
  amber  → bg-amber-500/15 text-amber-600 border-amber-500/30
  red    → bg-red-500/15 text-red-600 border-red-500/30
  neutral → bg-neutral-200 text-neutral-500 border-neutral-300
```

**Steps:**
1. Write failing visual smoke test. Expected error: `Element not found: [data-testid="mechanical-scorecard"]` when inspecting preview page for an idea in `article_ready` with `mechanical_scores_snapshot` populated. Before implementation → no scorecard block → fails visual expectation. After implementation → scorecard renders with chips.
2. In [ArticlePreview.vue script setup](frontend/src/views/admin/ArticlePreview.vue) add a computed `mechanicalSnapshot` that returns `idea.value?.mechanical_scores_snapshot` OR null. Add helper `scoreStatusClasses(status)` returning Tailwind classes for `green | amber | red | neutral` mapped to the color rules above.
3. Add 3 computed aggregates:
   - `seoChips` — array of `{label, value, status}` derived from `mechanicalSnapshot.seo.*` (6 entries matching the 6 SEO keys)
   - `geoChips` — array for `freshness_signals`, `faq_pair_count`, `h2_count`, `h3_count` (status: green/amber/red by threshold — faq≥3 green / ≥1 amber / 0 red; freshness≥2 green / ≥1 amber / 0 red; etc.)
   - `aiHumanizationChips` — array for `tier1_violation_count`, `tier2_clusters`, `tier3_density_issues.length` (status: 0 green, 1-2 amber, 3+ red)
4. In the template, insert a new section BEFORE the main article content block (above the H1 / title editor area — find the appropriate anchor in the template). Use `data-testid="mechanical-scorecard"` on the outer div. Render the three chip groups with clear sub-headings. Use `v-if="mechanicalSnapshot"` so ideas without snapshots hide the whole block gracefully.
5. Handle the empty / legacy case: `v-else` block `<div class="text-xs text-neutral-500">Mechanical scores not yet available. They will appear after article generation completes.</div>`
6. Start dev server: `npm run dev`. Open preview page for an idea that completed via the new skip path — observe scorecard with correct chips.
7. Run Vite build to sanity-check syntax: `npm run build` — should emit `ArticlePreview-*.js` chunk with no errors.
8. Commit: `feat(content-engine): add mechanical scorecard to article preview`

**Verification:**
- [ ] `npm run build` succeeds with no errors
- [ ] Scorecard renders when `mechanical_scores_snapshot` exists
- [ ] 6 SEO chips show correct color per MechanicalScoringService traffic-light status
- [ ] GEO + AI humanization chips render with threshold-based colors
- [ ] Empty-state hint renders for legacy ideas without snapshot
- [ ] Dark-mode classes applied (parity with existing ArticlePreview styling)
- [ ] No placeholder/TODO comments

---

### Phase C.8 — Run Deep Quality Analysis button + progress wiring

**Estimated time:** 11 minutes

**Files:**
- Modify: `frontend/src/views/admin/ArticlePreview.vue` (add button, deep-score state, progress polling hook, re-read idea after completion)

**Design Deliverable:** Inline mockup (button integrated into scorecard footer)

```
Button states:
  [default]      🔬 Run Deep Quality Analysis       (emerald, enabled when status=article_ready)
  [disabled]     🔬 Deep Analysis Unavailable       (neutral, disabled when status != article_ready)
  [running]      ⏳ Scoring...  {{ progressPct }}%  (amber, progress indicator)
  [done]         ✅ Deep Score: {{ combined }}/100  (emerald, shows combined score + Re-run link)
  [failed]       ❌ Score Failed — Retry            (red, retry button)

Progress display reads existing progress_percentage + current_step from /progress polling.
```

**Steps:**
1. Write failing smoke test for button state machine. Expected error: `Element not found: [data-testid="run-deep-score-btn"]`. Before implementation → button not in DOM → fails. After implementation → button renders with correct label per idea state.
2. In script setup, add refs:
   ```javascript
   const deepScoring = ref(false)
   const deepScoreError = ref(null)
   let deepScorePollId = null
   ```
3. Add `deepScoreStatus` computed returning one of `'unavailable' | 'ready' | 'running' | 'done' | 'failed'` by inspecting `idea.value.status`, `idea.value.current_step`, presence of `idea.value.generated_article.scores` (the existing 5-gate combined struct from `/article-score` skill — confirm schema shape by reading [ContentIdeaController::updateComplete](backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php) before wiring). Done state triggers when progress=100 AND `current_step=completed` AND scores exist.
4. Add `startDeepScore()` function:
   - Calls `useContentEngine().runDeepScore(idea.value.id)`
   - On success: set `deepScoring = true`, start polling `getProgress(id)` every 3s (mirrors existing polling in [ContentEngine.vue](frontend/src/views/admin/ContentEngine.vue) L3s-polling block — reuse the exact interval + cleanup pattern)
   - On each poll: update `idea.value.progress_percentage`, `current_step`. When `progress === 100` OR `status === 'article_ready'` with scores populated, stop poll, re-fetch full idea via `getIdea(id)` to pick up mechanical + AI scores, set `deepScoring = false`.
   - On error: set `deepScoreError`, show toast, stop poll.
5. Add `stopDeepScorePolling()` for cleanup. Call it in `onBeforeUnmount` to avoid leaks.
6. In template (inside the scorecard from Phase C.7 footer), add the button with `data-testid="run-deep-score-btn"`. Use the 5-state mockup above; compute label + classes from `deepScoreStatus`. Show progress percentage during running state.
7. When deep score completes, render the "Deep Analysis Results" block showing 4 sub-scores + combined total + `[Re-run]` link.
8. Start dev server, test manually:
   - Idea in `article_ready` without deep score → button enabled, click → spinner starts, progress ticks, completes → scores appear
   - Idea NOT in `article_ready` (e.g. `draft`, `researching`) → button disabled with explanation tooltip
9. `npm run build` → confirm build still clean
10. Commit: `feat(content-engine): add Run Deep Quality Analysis button with progress polling`

**Verification:**
- [ ] Button renders with 5 distinct states driven by idea status + scores
- [ ] Clicking dispatches POST to `/run-deep-score`, Network tab confirms
- [ ] Progress polling uses existing `getProgress` endpoint at 3s interval
- [ ] On complete, idea re-fetched so 5-gate scores display in-place
- [ ] Poll interval cleaned up on component unmount (no memory leak)
- [ ] `npm run build` succeeds
- [ ] No placeholder/TODO comments

---

### Phase C.9 — End-to-End Integration Test

**Estimated time:** 10 minutes

**Files:**
- Create: `backend/tests/Feature/PhaseCPipelineSkipAndDeepScoreE2ETest.php`

**Steps:**
1. Write failing end-to-end feature test covering the full Phase C flow:
   - Seed admin user + idea at `researching` with valid `generated_article` (title + content + keyword + language)
   - Flag OFF path: POST `/automation/content-ideas/{id}/continue-pipeline` with progress=85 → assert 200, `next_phase=score_skipped`, idea now `article_ready` + progress 100 + `mechanical_scores_snapshot` populated with all 4 top-level keys (seo/geo/ai_humanization/captured_at)
   - Admin preview load: GET `/api/admin/content-engine/ideas/{id}` → assert response includes `mechanical_scores_snapshot` in data
   - User triggers deep score: Mockery-bind `ArticleGenerationService::triggerScore` to return success + pid, POST `/api/admin/content-engine/ideas/{id}/run-deep-score` → assert 200 + idea status flipped to `researching` + `current_step=deep_scoring`
   - Simulate deep-score completion: directly update idea to mimic the Sonnet callback (progress 100, current_step=completed, scores in generated_article), then GET `/ideas/{id}/progress` → assert progress 100
   - Assert mechanical snapshot STILL present (wasn't wiped by deep score run)
2. Run test, confirm it fails at first assertion before implementation glue.
3. Fix any integration gaps that surface (should be minimal since prior phases tested in isolation).
4. Run test, confirm pass.
5. Run all Phase C tests together: `php artisan test --filter="MechanicalScores|SnapshotWriter|ContinuePipelineScore|RunDeepScore|PhaseCPipeline"` — expect all green.
6. Commit: `test(content-engine): add Phase C pipeline skip + deep-score flow E2E test`

**Verification:**
- [ ] E2E test passes start to finish
- [ ] Verifies skip path → mechanical snapshot → deep-score trigger chain
- [ ] Confirms mechanical snapshot persists across deep-score re-run (not wiped)
- [ ] All Phase C test files pass together (no interaction bugs)
- [ ] No placeholder/TODO comments

---

## Rollback Strategy

Phase C is **fully additive** and **env-flag gated**, so rollback is near-zero-risk:

1. **Immediate rollback** — flip `ARTICLE_GEN_USE_SCORE_PHASE=true` in production `.env` on the VPS. Pipeline instantly reverts to legacy `/article-score` dispatch path. No deploy, no migration, no code revert needed.
2. **Partial rollback (frontend only)** — revert Phase C.7 + C.8 commits, leave backend as-is. Scorecard UI disappears; backend still snapshots mechanical scores (harmless JSON rows). Button goes away cleanly since the endpoint still works.
3. **Full rollback** — `git revert` the Phase C commits in reverse order. Then:
   - `php artisan migrate:rollback --step=1` drops the nullable `mechanical_scores_snapshot` column — no data loss because nothing critical depends on it.
   - Remove `.env` flag line (optional; default `false` is harmless if flag unread).
4. **Production validation gates** — after deploy, spot-check:
   - Tail `storage/logs/laravel.log` for any `[MechanicalSnapshot]` errors during first 10 article completions
   - Admin `/admin/content-engine/ideas/{id}/preview` renders scorecard for a recently completed idea
   - Click "Run Deep Quality Analysis" on one idea → progress polls, completes, 5-gate scores render

Per root CLAUDE.md: push policy is **commit-only-never-push**; deploy is user-triggered via `git push origin main` when explicitly approved.

## Open Questions Resolved During Plan

1. **Should the /automation/content-ideas/{id}/mechanical-scores live endpoint stay?** Yes — keep it. It's a useful on-demand recompute tool for debugging and plugin callbacks. Phase C adds snapshot persistence on top without removing the live compute.
2. **Where does the mechanical snapshot write happen — in `continue-pipeline` inline, or in `ContentPublishService`?** In `continue-pipeline` when the write-done branch flips progress past 85%. Reason: that's the exact single point where the new skip path diverges from the legacy path, and it's the same closure that currently owns the `triggerScore` call we're replacing. No service layer change needed.
3. **Does the deep-score button reuse the existing Progress Modal from ContentEngine.vue list page?** No — the button lives in `ArticlePreview.vue` and uses its own inline progress chip (since the user is already on the preview page). Reusing the list-page modal would be over-engineered navigation coupling.
4. **What about stale snapshots when a user re-edits the article?** Out of scope for Phase C. Current behavior: snapshot captures at write-done, edits after that won't refresh it until the user explicitly re-runs the deep score (which re-triggers `/article-score` and downstream save). If this becomes a real problem, add `$idea->observe()` to null the snapshot on `generated_article.content` change — flag for a separate patch.
5. **Indonesian articles — does the tier-word AI humanization check still make sense?** Yes, handled already by `MechanicalScoringService::scoreAiHumanization()` at L140 — returns a `note: 'Tier word lists are English-specific; skipped for Indonesian article.'` for `language=id`. Scorecard UI should surface this note in the AI humanization section when present.

## Execution Handoff

**Recommended: Option 1 — Execute in this session via `/gaspol-dev:gaspol-execute`.**

Plan is decomposed into **9 phases**, each 4-14 minutes, TDD-gated, with clear verification. All integration points map to real existing code — zero placeholders. Phase C ships as a standalone increment; Phase B plan will come in a later session per the approved A → C → B rollout.

**Alternative:** Save plan for a new session — plan file at [docs/plans/2026-04-19-viral-first-content-pipeline-v3-phase-c-plan.md](docs/plans/2026-04-19-viral-first-content-pipeline-v3-phase-c-plan.md) has everything needed.

**Total estimated time:** ~75 minutes (9 phases @ avg 8 min)
**Target branch:** `feat/phase-c-mechanical-scoring` (or continue on `main` since Phase A already landed there — pragmatic for single-dev flow)
