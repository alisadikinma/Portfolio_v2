> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

# Phase A — Topic Virality Pre-Scoring — Implementation Plan

**Parent design:** [2026-04-19-viral-first-content-pipeline-v3.md](2026-04-19-viral-first-content-pipeline-v3.md) §Phase A
**Scope:** Phase A only. Phase C + B deferred to separate plan docs post-ship per approved A → C → B rollout.
**Target branch:** `feat/phase-a-topic-virality-scoring`

## Goal

Rank trending topics by virality potential BEFORE user picks them, so writing quality investment goes into topics that can actually go viral. Today's [TrendingTopicService::getAllTrends()](backend/app/Services/TrendingTopicService.php#L67) returns un-ranked, AI-relevance-filtered topics — users pick blind. After Phase A, topics carry a composite 0-100 score (momentum mechanical + virality batch-AI) and the Trending Preview modal displays badges + sortable ranking. Score persists on imported `content_ideas.virality_score` to inform Phase B auto-tier decision downstream.

## Architecture Context

Pulled from [root CLAUDE.md](../../CLAUDE.md) + [backend/CLAUDE.md](../../backend/CLAUDE.md) + code exploration:

**Existing services (reuse):**
- [TrendingTopicService](backend/app/Services/TrendingTopicService.php) — fetches Google Trends + Google News + (disabled) TikTok/YouTube. Method `getAllTrends(?source)` returns raw array. Each trend has fields: `title`, `description`, `source`, `score` (coarse heuristic 60-80), `pub_date`, `publisher`, `publisher_tier`, `publisher_count`, `heat`.
- [ArticleGenerationService](backend/app/Services/ArticleGenerationService.php) — has `executeSyncPrompt()` pattern used by `rewriteVisualDirectionForFace()` ([line 173](backend/app/Services/ArticleGenerationService.php#L173)) for blocking Sonnet calls. Reuse same pattern for batch virality scoring.
- Laravel Cache facade — Portfolio uses file driver by default; Redis available. Use `cache()->remember()` with 3600s TTL.

**Existing controller/routes (extend):**
- [ContentIdeaController](backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php) — DI'd with `TrendingTopicService`. `pullTrending()` at L281, `importTrending()` at L306.
- [routes/api.php](backend/routes/api.php) — admin content-engine group at L802: add new route in same group.

**Existing model/migration patterns:**
- [ContentIdea model](backend/app/Models/ContentIdea.php) — fillable array L12-42, casts array L44-58. Add 2 new fields.
- Migration naming: `YYYY_MM_DD_HHMMSS_<description>.php`. Use `2026_04_19_100000_add_virality_score_to_content_ideas.php`.

**Existing frontend patterns (extend):**
- [ContentEngine.vue](frontend/src/views/admin/ContentEngine.vue) — Trending Preview modal at L360. Existing badge row at L402-416 (heat + publisher_tier + source). Add virality score badge alongside.
- [useContentEngine.js](frontend/src/composables/useContentEngine.js) — `pullTrending()` at L60, uses `request()` wrapper. Add `scoreBatchTrending()` method.
- Tailwind dark-mode pattern: `bg-X dark:bg-Y` already established.

**Testing stack:**
- Only [ContentPublishServiceTest.php](backend/tests/Feature/ContentPublishServiceTest.php) exists for content pipeline. Establish new test files for Phase A: `TopicScoringServiceTest.php` (unit), `TrendingScoreBatchEndpointTest.php` (feature).
- PHPUnit via `php artisan test` (confirmed in root CLAUDE.md Essential Commands).
- Frontend: no existing Vitest suite for ContentEngine — manual browser test for Phase A frontend phases.

## Tech Stack

- **Backend:** Laravel 12, PHP 8.2. Use [ArticleGenerationService::executeSyncPrompt()](backend/app/Services/ArticleGenerationService.php) pattern for AI calls. Use Laravel `Cache` facade for 1-hour TTL cache. PHPUnit for testing.
- **Frontend:** Vue 3.5 Composition API, Pinia, TailwindCSS 4. Extend existing composable + modal — no new components or libraries.
- **Database:** MySQL 8. Additive nullable columns on `content_ideas` — no rollback risk.

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---------|-------------|----------|---------|--------|
| Raw trending topics | `TrendingTopicService::getAllTrends()` | Service method | Yes | Use directly |
| Momentum scoring (mechanical) | source weight + pub_date recency + publisher_tier | Pure computation | No | Implement in `TopicScoringService::computeMomentum()` |
| Virality batch AI scoring | Sonnet via `ArticleGenerationService::executeSyncPrompt()` | Sync SSH call | Yes (pattern) | Wrap in new method `scoreViralityBatch()` |
| Score cache | Laravel Cache (file/Redis) | `cache()->remember()` | Yes | Use directly, key `topic_scores_v1_{sha1(titles)}` |
| Score persistence on import | `content_ideas.virality_score` (tinyint) + `virality_breakdown` (json) | Eloquent model | No | Add via migration + model fillable/casts |
| Scored trending endpoint | `POST /api/admin/content-engine/trending/score-batch` | `ContentIdeaController::scoreTrendingBatch()` | No | Add controller method + route |
| Frontend API call | `useContentEngine.scoreBatchTrending()` | Axios via `request()` wrapper | Yes (extend) | Add method |
| Score badges in modal | ContentEngine.vue Trending Preview badge row | Vue template | Yes (extend) | Add badge element + tooltip |
| Sort dropdown + logic | ContentEngine.vue filteredTrending computed | Vue computed | Yes (extend) | Add sort state + apply in sort step |

**Contract:** All "Yes (extend)" rows use existing code patterns — no reinvention. All "No" rows produce REAL integrations with working data, not placeholders.

## Phases

### Phase A.1 — Migration + Model Updates (virality_score + virality_breakdown columns)

**Estimated time:** 6 minutes

**Files:**
- Create: `backend/database/migrations/2026_04_19_100000_add_virality_score_to_content_ideas.php`
- Modify: `backend/app/Models/ContentIdea.php`
- Test: `backend/tests/Feature/ViralityScoreMigrationTest.php`

**Steps:**
1. Write failing test for migration + model updates. Expected error: `PDOException: SQLSTATE[42S22]: Column not found: 'virality_score'` when running `ContentIdea::create([...'virality_score' => 75])`.
2. Run test, confirm it fails for the expected reason: `php artisan test --filter=ViralityScoreMigrationTest`.
3. Create migration: `php artisan make:migration add_virality_score_to_content_ideas --table=content_ideas` then add columns `$table->unsignedTinyInteger('virality_score')->nullable()->after('source_data')` and `$table->json('virality_breakdown')->nullable()->after('virality_score')`. Run `php artisan migrate`.
4. Update [ContentIdea.php](backend/app/Models/ContentIdea.php): add `'virality_score'` and `'virality_breakdown'` to `$fillable`; add `'virality_breakdown' => 'array'` to `$casts`.
5. Run tests, confirm pass: `php artisan test --filter=ViralityScoreMigrationTest`.
6. Commit: `feat(content-engine): add virality_score + virality_breakdown to content_ideas`

**Verification:**
- [ ] `php artisan migrate` runs without error
- [ ] `ContentIdea::create(['title' => 'test', 'virality_score' => 75, 'virality_breakdown' => ['momentum' => 60, 'virality' => 85]])` persists both fields correctly
- [ ] `$idea->virality_breakdown` returns array (not string) — confirms cast working
- [ ] No placeholder/TODO comments in new code
- [ ] Rollback works: `php artisan migrate:rollback --step=1` drops columns cleanly

---

### Phase A.2 — TopicScoringService Skeleton + Momentum Scoring (Mechanical, No AI)

**Estimated time:** 10 minutes

**Files:**
- Create: `backend/app/Services/TopicScoringService.php`
- Create: `backend/tests/Unit/TopicScoringServiceMomentumTest.php`

**Steps:**
1. Write failing test for `computeMomentum()`. Expected error: `Error: Class "App\Services\TopicScoringService" not found`. Test body: given topic `['source' => 'google_news', 'publisher_tier' => 1, 'pub_date' => now()->subHours(2)->toIso8601String(), 'publisher_count' => 5]`, assert returned momentum_score between 80-100.
2. Run test, confirm failure: `php artisan test --filter=TopicScoringServiceMomentumTest`.
3. Create [TopicScoringService.php](backend/app/Services/TopicScoringService.php). Implement `computeMomentum(array $topic): int` (0-100 int). Formula:
   - Base from `source` weight: `google_news` = 30, `google_trends` = 35, `tiktok` = 40, `youtube` = 35, default 25
   - `publisher_tier` bonus: tier 1 → +20, tier 2 → +10, tier 3 → +0, null → +0
   - Recency bonus from `pub_date`: <6h → +20, 6-24h → +15, 1-3d → +8, 3-7d → +3, >7d → +0, null → +0
   - `publisher_count` bonus: 1 → +0, 2-3 → +5, 4-10 → +10, 11+ → +15
   - Cap at 100
4. Add unit tests covering each formula branch (source, tier, recency buckets, publisher_count). Target 6+ assertions.
5. Run tests, confirm pass: `php artisan test --filter=TopicScoringServiceMomentumTest`.
6. Commit: `feat(content-engine): add TopicScoringService with mechanical momentum scoring`

**Verification:**
- [ ] `php artisan test --filter=TopicScoringServiceMomentumTest` passes with 6+ assertions
- [ ] Service is resolvable from Laravel container: `app(TopicScoringService::class)` returns instance
- [ ] Formula is deterministic (same input → same output)
- [ ] No placeholder/TODO comments
- [ ] All formula branches (each source, each tier, each recency bucket, each publisher_count range) covered by at least one assertion

---

### Phase A.3 — Virality Batch AI Scoring (Sonnet Sync Call)

**Estimated time:** 15 minutes

**Files:**
- Modify: `backend/app/Services/TopicScoringService.php` (add `scoreViralityBatch()`)
- Modify: `backend/app/Services/ArticleGenerationService.php` (add `executeSyncPromptPublic()` wrapper if needed — verify `executeSyncPrompt` visibility first)
- Test: `backend/tests/Unit/TopicScoringServiceViralityTest.php`

**Steps:**
1. Write failing test for `scoreViralityBatch(array $topics): array`. Expected error: `BadMethodCallException` or method-not-found error. Test body: mock `ArticleGenerationService::executeSyncPrompt()` to return JSON string `[{"title":"t1","virality_score":80,"triggers":{"social_currency":true,"high_arousal":true,"practical_utility":false,"identity_signaling":true,"cognitive_gap":false}}]`; call `scoreViralityBatch([['title' => 't1']])`; assert returns array with `virality_score` and `triggers` populated.
2. Run test, confirm failure.
3. In [ArticleGenerationService.php](backend/app/Services/ArticleGenerationService.php), verify `executeSyncPrompt()` visibility — if `private`, change to `public` OR add public wrapper `runSonnetSync(string $prompt): array`. Signature: `public function runSonnetSync(string $prompt, int $timeoutSec = 60): array { return $this->executeSyncPrompt($prompt, 'topic-scoring', 'sonnet'); }`.
4. Implement `TopicScoringService::scoreViralityBatch(array $topics): array`:
   - Validate count 1-20 (batch cap). Throw `InvalidArgumentException` if over 20.
   - Build prompt: JSON-enumerated list of topic titles + source + description. System instruction: "Score each topic 0-100 on virality potential. Evaluate 5 triggers (social_currency, high_arousal, practical_utility, identity_signaling, cognitive_gap). Return JSON array one entry per input topic."
   - Call `app(ArticleGenerationService::class)->runSonnetSync($prompt)`.
   - Parse response as JSON. Extract `virality_score` (int 0-100) + `triggers` (object of 5 booleans) per topic.
   - Handle parse failures: log warning, return array where each topic gets `virality_score: 0, triggers: {all false}` — graceful degradation so cache doesn't poison.
5. Add tests: (a) happy path with mock Sonnet response, (b) batch-size validation (21 topics → throws), (c) AI failure → returns zero-scores gracefully, (d) malformed JSON → returns zero-scores.
6. Run tests, confirm pass.
7. Commit: `feat(content-engine): add virality batch AI scoring via Sonnet`

**Verification:**
- [ ] `php artisan test --filter=TopicScoringServiceViralityTest` passes (4+ test cases)
- [ ] ArticleGenerationService mockable via Laravel container binding override in tests
- [ ] Batch-size cap enforced (throws on 21+ topics)
- [ ] Graceful degradation on AI failure (zero-scores, no crash)
- [ ] Prompt string constructed with all 20 topic titles visible (no truncation)
- [ ] No placeholder/TODO comments

---

### Phase A.4 — Combined Composite Score + Cache

**Estimated time:** 8 minutes

**Files:**
- Modify: `backend/app/Services/TopicScoringService.php` (add `scoreBatch()` public method)
- Test: `backend/tests/Unit/TopicScoringServiceBatchTest.php`

**Steps:**
1. Write failing test for `scoreBatch(array $topics): array`. Expected error: method-not-found. Test body: given 3 topics, assert each returned topic has `momentum_score` (int 0-100), `virality_score` (int 0-100), `composite_score` (int 0-100), `triggers` (object). Verify composite = round(momentum * 0.4 + virality * 0.6).
2. Run test, confirm failure.
3. Implement `scoreBatch(array $topics): array`:
   - Build cache key: `'topic_scores_v1_' . sha1(implode('|', array_column($topics, 'title')))`.
   - `cache()->remember($key, 3600, function() use ($topics) { ... })`.
   - Inside callback: for each topic, call `computeMomentum()` (mechanical). Then `scoreViralityBatch($topics)` once for whole batch.
   - Merge: attach `momentum_score`, `virality_score`, `triggers`, `composite_score = (int) round($momentum * 0.4 + $virality * 0.6)` onto each topic.
   - Return array.
4. Add tests: (a) composite formula correctness, (b) cache hit avoids second AI call (assert `ArticleGenerationService::runSonnetSync` called exactly once across two consecutive `scoreBatch()` invocations with same titles), (c) different title sets produce different cache keys (independent caches).
5. Run tests, confirm pass.
6. Commit: `feat(content-engine): add composite scoring + 1-hour cache to TopicScoringService`

**Verification:**
- [ ] `php artisan test --filter=TopicScoringServiceBatchTest` passes
- [ ] Cache key incorporates sha1 of titles (same title set → same key)
- [ ] Cache hit skips AI call (verifiable via mock call count)
- [ ] `composite_score` = round(momentum * 0.4 + virality * 0.6), clamped 0-100
- [ ] Each returned topic preserves original fields + appends 4 new fields
- [ ] No placeholder/TODO comments

---

### Phase A.5 — TrendingTopicService::getScoredTopics() Refactor

**Estimated time:** 6 minutes

**Files:**
- Modify: `backend/app/Services/TrendingTopicService.php` (add `getScoredTopics()` method)
- Test: `backend/tests/Unit/TrendingTopicServiceScoredTest.php`

**Steps:**
1. Write failing test for `getScoredTopics(?string $source = null): array`. Expected error: method-not-found. Test body: partial-mock `TrendingTopicService` so `getAllTrends()` returns fixed 3 topics; mock `TopicScoringService::scoreBatch()` to return same topics with scores; assert `getScoredTopics()` returns the scored version sorted by `composite_score` desc.
2. Run test, confirm failure.
3. Implement `getScoredTopics(?string $source = null): array`:
   - Call existing `$this->getAllTrends($source)`.
   - Slice to first 20 topics (batch cap from Phase A.3).
   - Call `app(TopicScoringService::class)->scoreBatch($topics)`.
   - Sort by `composite_score` desc.
   - Return.
4. Add test: sort order correct when scores differ.
5. Run tests, confirm pass.
6. Commit: `feat(content-engine): add getScoredTopics() to TrendingTopicService`

**Verification:**
- [ ] `php artisan test --filter=TrendingTopicServiceScoredTest` passes
- [ ] Legacy `getBestTopic()` and `getAllTrends()` untouched (no regression)
- [ ] Returns array sorted by `composite_score` desc
- [ ] Hard cap of 20 topics enforced (matches batch cap)
- [ ] No placeholder/TODO comments

---

### Phase A.6 — POST /trending/score-batch Endpoint

**Estimated time:** 10 minutes

**Files:**
- Modify: `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php` (add `scoreTrendingBatch()` method)
- Modify: `backend/routes/api.php` (add route inside admin content-engine group)
- Test: `backend/tests/Feature/TrendingScoreBatchEndpointTest.php`

**Steps:**
1. Write failing test for `POST /api/admin/content-engine/trending/score-batch`. Expected error: 404 (route not registered). Test body: authenticated user posts `{source: 'google_news'}`; assert 200 response with `data[*]` containing `composite_score`, `virality_score`, `momentum_score`, `triggers`.
2. Run test, confirm failure.
3. Add route in [routes/api.php](backend/routes/api.php) inside admin content-engine group near L802: `Route::post('/trending/score-batch', [ContentIdeaController::class, 'scoreTrendingBatch']);`.
4. Implement `ContentIdeaController::scoreTrendingBatch(Request $request): JsonResponse`:
   - Accept optional `source` query/body param (same semantics as `pullTrending`).
   - Call `$this->trending->getScoredTopics($source)`.
   - Return `response()->json(['success' => true, 'data' => $scored])` matching existing pattern.
5. Add tests: (a) auth required (401 without token), (b) 200 with scored data, (c) empty result returns `data: []`.
6. Run tests, confirm pass: `php artisan test --filter=TrendingScoreBatchEndpointTest`.
7. Commit: `feat(content-engine): add POST /trending/score-batch endpoint`

**Verification:**
- [ ] `php artisan route:list | grep score-batch` shows route registered under `auth:sanctum`
- [ ] Endpoint returns scored + sorted topics
- [ ] 401 on unauthenticated request
- [ ] Matches existing API response envelope (`success`, `data`)
- [ ] No placeholder/TODO comments

---

### Phase A.7 — Import Trending Persists virality_score

**Estimated time:** 5 minutes

**Files:**
- Modify: `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php::importTrending()` (L306-336)
- Test: extend `TrendingScoreBatchEndpointTest.php` with import persistence test

**Steps:**
1. Write failing test for import persistence of score fields. Expected error: `Illuminate\Database\QueryException` or `AssertionFailedError: Failed asserting that null matches expected 75` when asserting `ContentIdea::first()->virality_score === 75`. Test body: POST `/trending/import` with `topics: [{title: 'X', virality_score: 75, virality_breakdown: {momentum: 60, virality: 85, triggers: {...}}}]`; assert created `ContentIdea` has `virality_score === 75` and `virality_breakdown` array populated.
2. Run test, confirm failure.
3. Modify `importTrending()`: when creating `ContentIdea`, if `$topic` has `composite_score` (or `virality_score`) or `virality_breakdown`, pass them to `create()`. Backward compatible: topics without these fields import as before with nulls.
4. Run tests, confirm pass.
5. Commit: `feat(content-engine): persist virality_score on trending import`

**Verification:**
- [ ] Import with score fields → created idea has populated fields
- [ ] Import without score fields (legacy) → created idea has null fields (no crash)
- [ ] `virality_breakdown` stored as JSON, retrieved as array (cast works)
- [ ] No placeholder/TODO comments

---

### Phase A.8 — Frontend composable method scoreBatchTrending()

**Estimated time:** 4 minutes

**Files:**
- Modify: [frontend/src/composables/useContentEngine.js](frontend/src/composables/useContentEngine.js) — add export + method

**Design Deliverable:** n/a (composable, no UI)

**Steps:**
1. Write failing smoke test for composable export. Expected error: `TypeError: scoreBatchTrending is not a function` when evaluated in browser DevTools console from admin/content-engine page. Smoke test: in Vue DevTools, inspect the composable return object from ContentEngine.vue — `scoreBatchTrending` key must exist. Before implementation → key absent → TypeError on invocation. After implementation → key present, returns promise.
2. In [useContentEngine.js](frontend/src/composables/useContentEngine.js), near existing `pullTrending` at L60, add method:
   ```javascript
   const scoreBatchTrending = (source = '') => {
     const params = source ? { source } : {}
     return request('post', '/admin/content-engine/trending/score-batch', null, params)
   }
   ```
3. Add `scoreBatchTrending` to the returned object near L160.
4. Manual smoke test: in Vue DevTools or browser console from admin Content Engine page, call `scoreBatchTrending('google_news')` — verify returns promise resolving to `{data: [...]}`.
5. Commit: `feat(content-engine): add scoreBatchTrending composable method`

**Verification:**
- [ ] Method exported from composable
- [ ] Browser console smoke test: returns resolved data with scored topics
- [ ] No placeholder/TODO comments
- [ ] Matches existing composable method style (request wrapper, same error handling)

---

### Phase A.9 — Frontend UI: Score Badge in Trending Modal Cards

**Estimated time:** 10 minutes

**Files:**
- Modify: [frontend/src/views/admin/ContentEngine.vue](frontend/src/views/admin/ContentEngine.vue) — badge row at L402-416, script setup imports

**Design Deliverable:** Inline mockup (matches existing badge style at L403-414 — no new design system tokens)

```
Cards in grid get one more chip in badge row:
  [🔥 HOT] [TIER 1] [google_news]   ← existing
  [🔥 HOT] [TIER 1] [google_news] [⚡ 87]   ← after Phase A.9

Score chip variants by composite_score:
  ≥ 80 → green bg  text-emerald:   ⚡ 87  (like TIER 1)
  50-79 → amber bg text-amber:     ⚡ 63  (like TRENDING)
  < 50  → neutral bg text-gray:    ⚡ 42  (muted)

Tooltip on hover shows breakdown:
  "Virality: 85 · Momentum: 60
   Triggers: social_currency, high_arousal, identity_signaling"
```

**Steps:**
1. Write failing visual smoke test. Expected error: `Element not found: span with class "bg-emerald-500/15" displaying ⚡ score` when inspecting Trending modal cards in browser DevTools. Smoke test: open admin/content-engine, click Pull Trending. Before implementation → cards show only heat/tier/source chips, no ⚡ badge → fails visual expectation. After implementation → cards show ⚡ {composite_score} chip with tier-colored border.
2. In Trending Preview modal badge row (L402-416 of ContentEngine.vue), add conditional span after `topic.source` badge:
   ```vue
   <span
     v-if="topic.composite_score != null"
     :class="[
       'inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded text-[10px] font-bold border',
       topic.composite_score >= 80 ? 'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 border-emerald-500/30' :
       topic.composite_score >= 50 ? 'bg-amber-500/15 text-amber-600 dark:text-amber-400 border-amber-500/30' :
       'bg-neutral-200 text-neutral-500 dark:bg-neutral-700 dark:text-neutral-400 border-neutral-300 dark:border-neutral-600'
     ]"
     :title="`Virality: ${topic.virality_score} · Momentum: ${topic.momentum_score}${topic.triggers ? '\nTriggers: ' + Object.keys(topic.triggers).filter(k => topic.triggers[k]).join(', ') : ''}`"
   >
     ⚡ {{ topic.composite_score }}
   </span>
   ```
3. Start dev server: `npm run dev` in frontend. Open http://localhost:5173/admin/content-engine.
4. Manually trigger: click Pull Trending → observe cards show ⚡ score badges with correct color tiers. Hover → tooltip shows breakdown.
5. Commit: `feat(content-engine): add virality score badge to trending cards`

**Verification:**
- [ ] Vite dev server starts without errors
- [ ] Trending modal cards show ⚡ {score} badge when `composite_score != null`
- [ ] Cards without score (legacy import) do NOT show badge (no crash, no empty chip)
- [ ] Color tiers correct: ≥80 green, 50-79 amber, <50 neutral
- [ ] Tooltip on hover displays virality + momentum + active triggers
- [ ] Dark mode styling consistent with existing badges
- [ ] No placeholder/TODO comments

---

### Phase A.10 — Frontend UI: Sort Dropdown + Scored Fetch Integration

**Estimated time:** 12 minutes

**Files:**
- Modify: [frontend/src/views/admin/ContentEngine.vue](frontend/src/views/admin/ContentEngine.vue) — modal header (add sort dropdown), script setup (add sort state + computed), pullTrending handler (switch to scoreBatchTrending)

**Design Deliverable:** Inline mockup

```
Trending modal header row:
  [Trending Topics]                 [All Sources ▼]   ← current
  [Trending Topics]  [Sort: Virality ▼]  [All Sources ▼]   ← after Phase A.10

Sort options:
  - Virality (default) — composite_score desc
  - Momentum — momentum_score desc
  - Recency — pub_date desc

When Pull Trending is clicked:
  - Show "Scoring topics..." in loading state (not just "Loading trending...")
  - Call scoreBatchTrending() instead of pullTrending()
  - Toggle: if user clicks a secondary "Raw trends (no scoring)" button, fall back to legacy pullTrending()
    (OPTIONAL — skip if too much scope; default behavior is always scored)
```

**Steps:**
1. Write failing visual smoke test. Expected error: `Element not found: <select> with sort options in Trending modal header` AND `pullTrending still invoked instead of scoreBatchTrending` when network tab inspected during Pull Trending click. Smoke test: open admin/content-engine DevTools Network tab, click Pull Trending. Before implementation → request hits `GET /trending` (legacy unscored endpoint), no sort dropdown in modal. After implementation → request hits `POST /trending/score-batch`, sort dropdown renders with 3 options, selecting reorders cards.
2. In [useContentEngine.js](frontend/src/composables/useContentEngine.js), ensure `scoreBatchTrending` is imported in ContentEngine.vue (around L616).
3. In ContentEngine.vue script setup, add refs: `const trendingSortBy = ref('virality')` (default virality).
4. Add computed `sortedTrending` that wraps `filteredTrending` with sort logic:
   ```javascript
   const sortedTrending = computed(() => {
     const list = [...filteredTrending.value]
     if (trendingSortBy.value === 'virality') {
       return list.sort((a, b) => (b.composite_score ?? 0) - (a.composite_score ?? 0))
     } else if (trendingSortBy.value === 'momentum') {
       return list.sort((a, b) => (b.momentum_score ?? 0) - (a.momentum_score ?? 0))
     } else {
       return list.sort((a, b) => {
         const ad = a.pub_date ? new Date(a.pub_date).getTime() : 0
         const bd = b.pub_date ? new Date(b.pub_date).getTime() : 0
         return bd - ad
       })
     }
   })
   ```
5. Modify `pagedTrending` computed to source from `sortedTrending` instead of `filteredTrending` (preserve search + page behavior, change sort source).
6. In modal header (L362-370), insert sort dropdown BEFORE source filter:
   ```vue
   <select v-model="trendingSortBy" class="rounded-lg border border-neutral-300 dark:border-neutral-600 bg-white dark:bg-neutral-700 text-neutral-900 dark:text-neutral-100 text-xs px-2 py-1 focus:ring-amber-500 focus:border-amber-500 mr-2">
     <option value="virality">⚡ Virality</option>
     <option value="momentum">📊 Momentum</option>
     <option value="recency">🕒 Recency</option>
   </select>
   ```
7. In the Pull Trending handler (around L1175, `openTrendingModal` or similar), replace `pullTrending(source)` with `scoreBatchTrending(source)`. Update loading message in the modal loading block (L384-387) to "Scoring topics..." when `trendingLoading && scoring`.
8. Start dev server: `npm run dev`. Manual test: (a) Pull Trending → modal opens with sorted list by virality, (b) change sort to Momentum → list reorders, (c) change to Recency → list reorders by date, (d) search + sort combined works, (e) topics without score appear at end (nullish → 0).
9. Commit: `feat(content-engine): add trending sort dropdown + scored fetch integration`

**Verification:**
- [ ] Sort dropdown renders with 3 options
- [ ] Default sort is Virality (composite_score desc)
- [ ] Switching sort reorders list immediately (reactive)
- [ ] Pull Trending now calls `scoreBatchTrending` — badge + sort work end-to-end
- [ ] Loading indicator reads "Scoring topics..." during the AI call
- [ ] Topics without score (edge case) sort to bottom without crash
- [ ] Search + sort compose correctly
- [ ] No placeholder/TODO comments

---

### Phase A.11 — End-to-End Integration Smoke Test

**Estimated time:** 8 minutes

**Files:**
- Create: `backend/tests/Feature/PhaseATrendingScoringE2ETest.php`

**Steps:**
1. Write failing end-to-end test covering: pull scored trends → import top-scored → verify ContentIdea persistence.
   - Authenticate Sanctum user
   - Mock `TopicScoringService::scoreBatch()` to return 3 known-scored topics
   - POST `/api/admin/content-engine/trending/score-batch?source=google_news` → assert 200 + sorted data
   - POST `/api/admin/content-engine/trending/import` with top-scored topic (including `composite_score`, `virality_breakdown`) → assert 201
   - Query `ContentIdea::where('title', $title)->first()` → assert `virality_score` + `virality_breakdown` persisted correctly
2. Run test, confirm it fails at first assertion before fix.
3. Implement any glue missing (should be minimal — each prior phase already tested in isolation).
4. Run test, confirm pass.
5. Commit: `test(content-engine): add Phase A end-to-end trending scoring flow test`

**Verification:**
- [ ] E2E test passes start to finish
- [ ] Verifies scored endpoint → import persistence chain intact
- [ ] All assertions meaningful (not tautological)
- [ ] No placeholder/TODO comments

---

## Rollback Strategy

Phase A has NO env flag because it's purely additive:
- New nullable columns: `content_ideas.virality_score`, `virality_breakdown` — no existing query depends on them
- New service: `TopicScoringService` — standalone
- New endpoint: `POST /trending/score-batch` — old `GET /trending` untouched
- Frontend: old `pullTrending()` composable method untouched; only `openTrendingModal` swapped to new method

**If issues in production:**
1. Revert frontend commit (Phases A.8-A.10) → UI falls back to legacy `pullTrending` (unscored trends)
2. Revert backend commits (Phases A.2-A.7) if needed → endpoint 404s but `GET /trending` still works
3. Migration rollback (`php artisan migrate:rollback --step=1`) drops nullable columns — no data loss because nothing critical depends on them

Per root CLAUDE.md: push policy is commit-only-never-push, deploy is manual-triggered via git push when user approves.

## Open Questions Resolved During Plan

1. **`executeSyncPrompt()` visibility** — `private`. Adding public wrapper `runSonnetSync()` in Phase A.3 rather than broadening visibility.
2. **Cache driver** — Use Laravel default (file). No Redis dependency introduced. User can configure Redis in `.env` separately if desired; code is driver-agnostic via Cache facade.
3. **Batch cap** — 20 topics per AI call. Ensures prompt fits comfortably within Sonnet context + avoids truncation.
4. **Sort default** — Virality (matches "prevent failure at topic selection" design intent).

## Execution Handoff

**Recommended: Option 1 — Execute in this session.**

Plan is decomposed into 11 phases, each 4-15 minutes, TDD-gated, with clear verification. All integration points mapped to real code. Phase A ships as standalone increment — Phase C + B plan docs create separately after validation.

Ready to start Phase A.1? Invoke `gaspol-execute` to implement with per-phase checkpoints + TDD hard gate.

Alternative: save plan for a new session — plan file at [docs/plans/2026-04-19-viral-first-content-pipeline-v3-phase-a-plan.md](docs/plans/2026-04-19-viral-first-content-pipeline-v3-phase-a-plan.md) has everything needed.
