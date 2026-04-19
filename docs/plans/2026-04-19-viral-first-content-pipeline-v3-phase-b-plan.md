> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

# Phase B — Tiered Deep Research + Skill Split — Implementation Plan

**Parent design:** [2026-04-19-viral-first-content-pipeline-v3.md](2026-04-19-viral-first-content-pipeline-v3.md) §Phase B (L127-339)
**Depends on:** Phase A shipped (virality_score + breakdown columns live), Phase C shipped (env-flag gating pattern proven, mechanical snapshot works)
**Scope:** Phase B only — tiered research + skill split + brand-aware images.
**Target branch:** `feat/phase-b-tiered-deep-research`

## Goal

Lift the research ceiling for high-virality topics from "content farm grade" (5-8 snippet data points) to "journalistic grade" (20-30 deeply-read data points, 10+ entities with visual_style prose, 3-5 verbatim personas, 5+ sourced quotes, 1-3 written how-to guides) by splitting the monolithic `/article-prep` skill into two pipeline phases:

1. **`/article-research`** — Opus for virality_score ≥ 70, Sonnet for lower — runs a 4-layer deep research framework (broader discovery → deep reading → synthesis → psychology + written guides) and persists to existing `content_ideas.research_data` column.
2. **`/article-strategy-outline`** — always Sonnet — reads research output, then performs framework/arc/hook/template selection + outline + retention planning + image concept mapping, persisting to `generated_article.prep_data`.

Model budget: Opus used only for the research sub-step (~50% savings vs upgrading the whole prep call to Opus). Auto-tier gate: `virality_score >= 70 → deep`, else `quick`. User can override via Gate 1 Config tier picker (`auto | quick | deep`). Feature-flagged via `ARTICLE_GEN_SKILL_SPLIT_ENABLED=false` during rollout → falls back to legacy single `/article-prep` call. Secondary outcome: `/article-images` reads `research_data.entities[].visual_style` to compose brand-aware cinematic prompts (ChatGPT → "purple gradient + speech bubbles", not "generic AI chat UI").

## Architecture Context

Pulled from [root CLAUDE.md](../../CLAUDE.md) + [backend/CLAUDE.md](../../backend/CLAUDE.md) + [Phase A plan](2026-04-19-viral-first-content-pipeline-v3-phase-a-plan.md) + [Phase C plan](2026-04-19-viral-first-content-pipeline-v3-phase-c-plan.md) + code reads:

**Existing code to reuse (no reinvention):**
- [ArticleGenerationService::triggerPrep()](../../backend/app/Services/ArticleGenerationService.php#L52) — current single-call prep dispatch. Phase B adds `triggerResearch()` + `triggerStrategyOutline()` alongside; keeps `triggerPrep` as legacy fallback path.
- [ArticleGenerationService::executePrompt()](../../backend/app/Services/ArticleGenerationService.php#L523) — existing SSH/local dispatcher accepting `$model` + `$refsFile`. Already supports Opus via `--model` CLI flag. No changes needed — new trigger methods call through it.
- [routes/api.php continue-pipeline](../../backend/routes/api.php#L652) — Phase B extends the branch matrix. Currently: `progress 35 → write`, `progress 85 → score-or-skip`. Phase B inserts: `progress 15 → strategy-outline` when split flag enabled.
- [routes/api.php save-prep](../../backend/routes/api.php#L587) — existing. `/article-strategy-outline` reuses it unchanged (writes to `generated_article.prep_data`).
- [routes/api.php content-ideas/{id} GET](../../backend/routes/api.php#L552) — existing. `/article-strategy-outline` uses it to read `research_data` from previous step.
- [ContentIdea model](../../backend/app/Models/ContentIdea.php) — `research_data` column already exists (L24 fillable, L53 array cast). Phase B uses it directly — no nested `prep_data.research_data`.
- [ArticleGenerationService constructor config loads](../../backend/app/Services/ArticleGenerationService.php#L18-L27) — env/config pattern to mirror for new model + refs paths.
- [config/services.php `article_generation` array](../../backend/config/services.php) — already has `model_prep`, `refs_prep`, `use_images_phase` etc. Phase B adds 5 keys.
- [useContentEngine.js getProgress()](../../frontend/src/composables/useContentEngine.js#L71) — existing progress polling. Unchanged; reads new step names emitted by new skills.
- [ContentEngine.vue pipelinePhases](../../frontend/src/views/admin/ContentEngine.vue#L855-L913) — UI progress phase array. Phase B refactors the `Prep` phase into two cards (`Research` + `Strategy+Outline`) plus a model badge column.
- [ContentEngine.vue Config Modal](../../frontend/src/views/admin/ContentEngine.vue#L483-L510) — Phase B inserts a tier picker block.
- Plugin dir: [D:\Projects\claude-plugin\article-content-writer\](D:/Projects/claude-plugin/article-content-writer) — plugin repo. Skills in `skills/<name>/SKILL.md`. Compiled refs in `references/compiled/refs-*.md`.
- Plugin skill anatomy: [article-prep SKILL.md](D:/Projects/claude-plugin/article-content-writer/skills/article-prep/SKILL.md) — existing template for new skills (CLI flags, progress reporting, JSON save payload, continue-pipeline trigger).
- Plugin images skill: [article-images SKILL.md L60-L86](D:/Projects/claude-plugin/article-content-writer/skills/article-images/SKILL.md#L60) — "Context Extraction" block is where brand-aware prompt enrichment lands.

**Existing patterns (follow verbatim):**
- Env flag convention: `env('ARTICLE_GEN_*')` → `config('services.article_generation.*')`. Mirror Phase C's `use_score_phase` approach.
- Migration naming: `2026_04_19_120000_<description>.php`.
- Feature-flag gating: default false during rollout, flip to true once validated (Phase C pattern).
- Progress reporting from skill: `PUT /automation/content-ideas/{id}/progress` with `{step, percentage, message}`.
- Skill completion: `PUT /save-*` with file-based curl (heredoc) → `POST /continue-pipeline` with `{completed_step: <phase>}`.
- API envelope: `{success: bool, data|message: ...}`.

**Plugin deployment:**
- The compiled refs + skill files live on the VPS at `/home/claudesn/` (see CLAUDE.md env `ARTICLE_GEN_REFS_PREP=/home/claudesn/refs-prep.md`). Plugin skills themselves are packaged inside the Claude CLI's plugin system — user installs/updates the plugin on the VPS separately. This plan only specifies the plugin file structure; the actual VPS plugin update is a deploy step the user runs when they choose to roll out. Backend continues working with legacy skills when split flag is off — zero-risk staging.

**Testing stack:**
- PHPUnit via `php artisan test` — backend changes (config, service, routes, tier resolver) are testable.
- Mockery on `ArticleGenerationService` for tier/dispatch tests (same pattern as Phase A + C).
- Plugin skills (CLI) are NOT unit-testable via PHPUnit — rely on: (a) schema validator tests on the saved `research_data` shape after a smoke run, (b) one live E2E smoke test per tier (quick + deep) recorded in staging with real idea IDs, (c) backend-side validators that reject malformed plugin output.
- Frontend: no Vitest suite — `npm run build` syntactic check + manual browser smoke test (same convention as Phase A/C).

## Tech Stack

- **Backend:** Laravel 12, PHP 8.2. Extend `ArticleGenerationService` with 2 new trigger methods + 1 tier resolver. New route in automation group. PHPUnit + Mockery.
- **Frontend:** Vue 3.5 Composition API, TailwindCSS 4. Extend Config Modal + Progress Modal in [ContentEngine.vue](../../frontend/src/views/admin/ContentEngine.vue) only — no new components.
- **Database:** MySQL 8. One additive nullable `enum` column (`research_tier_override`). `research_data` column already exists — zero migration for that.
- **Plugin (external repo):** [article-content-writer](D:/Projects/claude-plugin/article-content-writer). Create 2 new skills + 1 new compiled refs file + augment 1 existing skill. Plain markdown + bash files — no test framework, verified by live CLI smoke runs.

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| Tier override persistence | `content_ideas.research_tier_override` enum | Eloquent | No | Add via migration + model fillable |
| Research output persistence | `content_ideas.research_data` JSON | Eloquent | **Yes** (column already cast as array) | Use directly — do NOT add new column |
| Virality score read (auto-tier) | `content_ideas.virality_score` (Phase A) | Eloquent | Yes | Read directly |
| Tier resolution | `content_ideas.research_tier_override` + `virality_score` | Pure computation | No | Add `ArticleGenerationService::resolveResearchTier()` |
| Model resolution | `config('services.article_generation.model_research_*')` | Laravel config | No | Add 3 keys under `article_generation` in `config/services.php` |
| Feature flag | `config('services.article_generation.skill_split_enabled')` | Laravel config | No | Add 1 key, mirror `use_score_phase` pattern |
| `triggerResearch` dispatch | `ArticleGenerationService` | Service method | No | Add method |
| `triggerStrategyOutline` dispatch | `ArticleGenerationService` | Service method | No | Add method |
| SSH/local exec + refs file | `ArticleGenerationService::executePrompt()` | Service method | Yes | Use directly — no changes to private method |
| Save research endpoint | `PUT /automation/content-ideas/{id}/save-research` | Inline route closure | No | Add route closure (matches `/save-prep` style) |
| Continue-pipeline branching | [routes/api.php L652-766](../../backend/routes/api.php#L652) | Inline route closure | Yes (extend) | Add `progress 15 → strategy-outline` branch gated by split flag; `progress 35 → write` still works for legacy |
| Auto-pipeline entry | `ContentIdeaController::startResearch` | Controller method | Yes (extend) | Add "call triggerResearch instead of triggerPrep when split flag on" branch |
| `/article-research` skill | `D:\Projects\claude-plugin\article-content-writer\skills\article-research\SKILL.md` | Plugin skill file | No | Create |
| `/article-strategy-outline` skill | `D:\Projects\claude-plugin\article-content-writer\skills\article-strategy-outline\SKILL.md` | Plugin skill file | No | Create |
| `refs-research.md` compiled reference | `D:\Projects\claude-plugin\article-content-writer\references\compiled\refs-research.md` | Compiled ref | No | Create (~30 KB assembled from global-config + new deep-research framework + entity-extraction rules + written-guide patterns) |
| `refs-strategy-outline.md` compiled reference | `D:\Projects\claude-plugin\article-content-writer\references\compiled\refs-strategy-outline.md` | Compiled ref | No | Create by SLIMMING `refs-prep.md` (strip research-only sections, keep frameworks + hooks + arcs + templates + retention-engine) |
| `refs-prep.md` legacy fallback | `D:\Projects\claude-plugin\article-content-writer\references\compiled\refs-prep.md` | Compiled ref | Yes | Keep unchanged for legacy `/article-prep` fallback path |
| `/article-images` brand-aware prompt enrichment | [article-images SKILL.md](D:/Projects/claude-plugin/article-content-writer/skills/article-images/SKILL.md) Context Extraction section | Plugin skill | Yes (augment) | Insert "Brand Visual Style Resolution" sub-step that reads `research_data.entities[].visual_style` |
| Gate 1 Config modal tier picker | [ContentEngine.vue L483-L510](../../frontend/src/views/admin/ContentEngine.vue#L483) | Vue template | Yes (extend) | Add radio group + model-badge preview below Languages row |
| Progress modal phase cards | [ContentEngine.vue L855-L913](../../frontend/src/views/admin/ContentEngine.vue#L855) | `pipelinePhases` array | Yes (refactor) | Split `Prep` entry into `Research` (with model badge Sonnet/Opus) + `Strategy+Outline` (Sonnet) |
| Composable tier state | `useContentEngine` return value | Composable | Yes (extend) | Add `startResearch(idea, config)` to accept `research_tier` flag in config payload |
| Env example | `backend/.env.example` | Env documentation | Yes (extend) | Add 5 new keys with one-line rationale per Phase C style |

**Contract:** Every "Yes" row uses the existing integration directly. Every "No" row produces a real working integration, never a placeholder. If during execution any "No" row turns out to depend on a piece that doesn't exist, STOP and ask.

## Phases

### Phase B.1 — Migration: `research_tier_override` column + model update

**Estimated time:** 6 minutes

**Files:**
- Create: `backend/database/migrations/2026_04_19_120000_add_research_tier_override_to_content_ideas.php`
- Modify: `backend/app/Models/ContentIdea.php` (fillable)
- Test: `backend/tests/Feature/ResearchTierOverrideMigrationTest.php`

**Steps:**
1. Write failing test for persistence. Expected error: `Illuminate\Database\QueryException: SQLSTATE[42S22]: Column not found: 'research_tier_override'`. Test body: `$idea = ContentIdea::create(['title' => 'x', 'research_tier_override' => 'deep'])`; assert `$idea->fresh()->research_tier_override === 'deep'`. Second assertion: create without field, assert default is `'auto'`.
2. Run test, confirm failure: `php artisan test --filter=ResearchTierOverrideMigrationTest`.
3. Create migration with `php artisan make:migration add_research_tier_override_to_content_ideas --table=content_ideas`. In up(): `$table->enum('research_tier_override', ['auto','quick','deep'])->default('auto')->after('mechanical_scores_snapshot');`. In down(): drop the column. Run `php artisan migrate`.
4. Modify [ContentIdea.php](../../backend/app/Models/ContentIdea.php): append `'research_tier_override'` to `$fillable` (after `'mechanical_scores_snapshot'` to preserve neat ordering).
5. Re-run test, confirm pass.
6. Add a third test case: invalid value (`'turbo'`) → throws `QueryException` (enum guard). Confirms column is real enum, not free-form string.
7. Commit: `feat(content-engine): add research_tier_override column to content_ideas`

**Verification:**
- [ ] `php artisan migrate` runs clean
- [ ] `ContentIdea::create(['title' => 'x', 'research_tier_override' => 'deep'])` persists and reads back
- [ ] Default on fresh create is `'auto'`
- [ ] Enum guard rejects `'turbo'` (QueryException)
- [ ] Rollback drops cleanly
- [ ] No placeholder/TODO comments

---

### Phase B.2 — Config + env flag scaffolding (5 keys)

**Estimated time:** 6 minutes

**Files:**
- Modify: `backend/config/services.php` (add 5 keys under `article_generation`)
- Modify: `backend/.env.example` (5 new env entries with comments)
- Test: `backend/tests/Unit/PhaseBConfigTest.php`

**Steps:**
1. Write failing test for config resolution. Expected error: `Failed asserting that null matches expected 'opus'`. Test body: assert `config('services.article_generation.model_research_deep') === 'opus'`, `model_research_quick === 'sonnet'`, `model_strategy_outline === 'sonnet'`, `refs_research` is non-empty string, `skill_split_enabled === false` (default off during rollout).
2. Run test, confirm failure: `php artisan test --filter=PhaseBConfigTest`.
3. Edit [config/services.php](../../backend/config/services.php) — inside `'article_generation' => [...]` array, next to existing `model_prep` / `refs_prep` keys, add:
   ```php
   'model_research_deep' => env('ARTICLE_GEN_MODEL_RESEARCH_DEEP', 'opus'),
   'model_research_quick' => env('ARTICLE_GEN_MODEL_RESEARCH_QUICK', 'sonnet'),
   'model_strategy_outline' => env('ARTICLE_GEN_MODEL_STRATEGY_OUTLINE', 'sonnet'),
   'refs_research' => env('ARTICLE_GEN_REFS_RESEARCH', ''),
   'refs_strategy_outline' => env('ARTICLE_GEN_REFS_STRATEGY_OUTLINE', ''),
   'skill_split_enabled' => env('ARTICLE_GEN_SKILL_SPLIT_ENABLED', false),
   'deep_research_enabled' => env('ARTICLE_GEN_DEEP_RESEARCH_ENABLED', true),
   ```
4. Edit [backend/.env.example](../../backend/.env.example) — next to existing `ARTICLE_GEN_REFS_*` entries add:
   ```env
   # Phase B — Tiered Deep Research + Skill Split
   ARTICLE_GEN_MODEL_RESEARCH_DEEP=opus       # Opus only for deep research step (virality_score >= 70)
   ARTICLE_GEN_MODEL_RESEARCH_QUICK=sonnet    # Sonnet for quick research tier
   ARTICLE_GEN_MODEL_STRATEGY_OUTLINE=sonnet  # Always Sonnet for strategy + outline
   ARTICLE_GEN_REFS_RESEARCH=/home/claudesn/refs-research.md
   ARTICLE_GEN_REFS_STRATEGY_OUTLINE=/home/claudesn/refs-strategy-outline.md
   ARTICLE_GEN_SKILL_SPLIT_ENABLED=false      # Feature flag — flip to true once plugin skills deployed to VPS
   ARTICLE_GEN_DEEP_RESEARCH_ENABLED=true     # Kill-switch for tier resolution; false forces quick even if virality_score >= 70
   ```
5. Re-run test, confirm pass.
6. Add a second test for override behavior: `config()->set('services.article_generation.skill_split_enabled', true)`; re-read; assert true. Confirms runtime override works (needed for Mockery in later phases).
7. Commit: `feat(content-engine): add Phase B env flags (tier models + refs + split gate)`

**Verification:**
- [ ] `config('services.article_generation.model_research_deep')` returns `'opus'` by default
- [ ] `skill_split_enabled` defaults to `false` (safe rollout)
- [ ] `deep_research_enabled` defaults to `true` (when split is on, tier auto-decision active)
- [ ] Runtime override via `config()->set()` works
- [ ] `.env.example` documents all 7 flags with rationale
- [ ] No placeholder/TODO comments

---

### Phase B.3 — `resolveResearchTier()` service method

**Estimated time:** 8 minutes

**Files:**
- Modify: `backend/app/Services/ArticleGenerationService.php` (add `resolveResearchTier` + `resolveResearchModel`)
- Test: `backend/tests/Unit/ArticleGenerationTierResolverTest.php`

**Steps:**
1. Write failing test for `resolveResearchTier(ContentIdea $idea): string`. Expected error: `Error: Call to undefined method ArticleGenerationService::resolveResearchTier()`. Test body covers 6 cases:
   - `research_tier_override='quick'` → returns `'quick'` regardless of score
   - `research_tier_override='deep'` → returns `'deep'` regardless of score
   - `research_tier_override='auto'` + `virality_score >= 70` → `'deep'`
   - `research_tier_override='auto'` + `virality_score < 70` → `'quick'`
   - `research_tier_override='auto'` + `virality_score = null` → `'quick'` (safe default when Phase A didn't score)
   - `deep_research_enabled = false` (config override) + `research_tier_override='auto'` + `virality_score = 95` → `'quick'` (kill switch honored)
2. Write second failing test for `resolveResearchModel(string $tier): string`. Cases:
   - `'deep'` → returns value of `config('services.article_generation.model_research_deep')` (default `'opus'`)
   - `'quick'` → returns `config('services.article_generation.model_research_quick')` (default `'sonnet'`)
   - `'auto'` or anything else → throws `InvalidArgumentException` (tier must be resolved upstream)
3. Run tests, confirm failures.
4. In [ArticleGenerationService.php](../../backend/app/Services/ArticleGenerationService.php), add two public methods near the other triggers:
   ```php
   public function resolveResearchTier(\App\Models\ContentIdea $idea): string
   {
       $override = $idea->research_tier_override ?? 'auto';

       // Kill switch: force quick when deep research globally disabled
       $deepEnabled = (bool) config('services.article_generation.deep_research_enabled', true);
       if (!$deepEnabled && $override === 'auto') {
           return 'quick';
       }

       if ($override === 'quick' || $override === 'deep') {
           return $override;
       }

       // Auto: deep when Phase A virality_score >= 70, else quick
       $score = $idea->virality_score;
       if ($score !== null && $score >= 70) {
           return 'deep';
       }
       return 'quick';
   }

   public function resolveResearchModel(string $tier): string
   {
       if ($tier === 'deep') {
           return config('services.article_generation.model_research_deep', 'opus');
       }
       if ($tier === 'quick') {
           return config('services.article_generation.model_research_quick', 'sonnet');
       }
       throw new \InvalidArgumentException("resolveResearchModel expects 'quick' or 'deep', got '{$tier}'");
   }
   ```
5. Re-run both tests, confirm pass.
6. Commit: `feat(content-engine): add research tier + model resolvers to ArticleGenerationService`

**Verification:**
- [ ] `php artisan test --filter=ArticleGenerationTierResolverTest` passes with 8+ assertions
- [ ] All 6 tier cases + 3 model cases covered
- [ ] Kill-switch path (`deep_research_enabled=false`) verified
- [ ] Null virality_score → safe `'quick'` default (no crash)
- [ ] Bad model tier → `InvalidArgumentException` with helpful message
- [ ] No placeholder/TODO comments

---

### Phase B.4 — `triggerResearch()` + `triggerStrategyOutline()` dispatch methods

**Estimated time:** 10 minutes

**Files:**
- Modify: `backend/app/Services/ArticleGenerationService.php` (add 2 public methods)
- Test: `backend/tests/Unit/ArticleGenerationTriggerResearchTest.php`
- Test: `backend/tests/Unit/ArticleGenerationTriggerStrategyOutlineTest.php`

**Steps:**
1. Write failing test for `triggerResearch(int $ideaId, array $config, string $tier): array`. Expected error: method-not-found. Test body: construct `ArticleGenerationService` with driver=`local`; partial-mock `executePrompt` via reflection OR by extending the class with a test-double (match approach Phase A used for `runSonnetSync` tests); call `triggerResearch(42, ['topic'=>'AI tools','keyword'=>'ai','languages'=>['en']], 'deep')`; assert returned array matches shape `['success' => true, 'pid' => ..., 'error' => null]`. Verify recorded call to executePrompt:
   - Prompt starts with `/article-research --idea-id 42 --api-url ... --api-token ...`
   - Prompt contains `--research-tier deep`, `--topic "AI tools"`, `--languages en`, `--keyword "ai"`
   - `$model` argument == `'opus'` (from resolveResearchModel('deep'))
   - `$refsFile` argument == value of `config('services.article_generation.refs_research')`
   - `$phase` label passed to executePrompt == `'research'`
2. Write failing test for `triggerStrategyOutline(int $ideaId): array`. Expected error: method-not-found. Test body:
   - Prompt starts with `/article-strategy-outline --idea-id 42 --api-url ... --api-token ...`
   - No `--topic` or `--languages` flag (skill reads those from DB via GET /content-ideas/{id})
   - `$model` == `'sonnet'` (from `model_strategy_outline`)
   - `$refsFile` == value of `refs_strategy_outline`
   - `$phase` label == `'strategy-outline'`
3. Run both tests, confirm failures.
4. Add methods to [ArticleGenerationService.php](../../backend/app/Services/ArticleGenerationService.php) near existing `triggerPrep`:
   ```php
   /**
    * Trigger Phase B Step 1: Research (NEW — replaces /article-prep steps 1).
    * Model selected per tier: Opus (deep) or Sonnet (quick).
    *
    * @return array{success: bool, pid: int|null, error: string|null}
    */
   public function triggerResearch(int $ideaId, array $config, string $tier): array
   {
       $topic = $config['topic'] ?? '';
       $languages = implode(',', $config['languages'] ?? ['en']);
       $keyword = $config['keyword'] ?? '';
       $instructions = $config['instructions'] ?? '';

       $prompt = "/article-research --idea-id {$ideaId} --api-url {$this->apiUrl} --api-token {$this->apiToken}";
       $prompt .= ' --topic "' . addslashes($topic) . '"';
       $prompt .= " --languages {$languages}";
       $prompt .= " --research-tier {$tier}";
       if ($keyword) {
           $prompt .= ' --keyword "' . addslashes($keyword) . '"';
       }
       if ($instructions) {
           $prompt .= ' --instructions "' . addslashes($instructions) . '"';
       }

       $model = $this->resolveResearchModel($tier);
       $refsFile = config('services.article_generation.refs_research', '');

       return $this->executePrompt($prompt, $ideaId, 'research', $model, $refsFile);
   }

   /**
    * Trigger Phase B Step 2: Strategy + Outline (NEW).
    * Always runs on Sonnet. Reads research_data from DB — no CLI args beyond ID.
    *
    * @return array{success: bool, pid: int|null, error: string|null}
    */
   public function triggerStrategyOutline(int $ideaId): array
   {
       $prompt = "/article-strategy-outline --idea-id {$ideaId} --api-url {$this->apiUrl} --api-token {$this->apiToken}";

       $model = config('services.article_generation.model_strategy_outline', 'sonnet');
       $refsFile = config('services.article_generation.refs_strategy_outline', '');

       return $this->executePrompt($prompt, $ideaId, 'strategy-outline', $model, $refsFile);
   }
   ```
5. Re-run tests, confirm pass.
6. Commit: `feat(content-engine): add triggerResearch + triggerStrategyOutline to ArticleGenerationService`

**Verification:**
- [ ] Both test files pass
- [ ] `triggerResearch` passes `--research-tier {tier}` to CLI prompt
- [ ] `triggerResearch` picks Opus for deep, Sonnet for quick
- [ ] `triggerStrategyOutline` is flagless beyond `--idea-id --api-url --api-token`
- [ ] Both use existing `executePrompt` plumbing (no duplicated SSH code)
- [ ] Phase labels distinct (`research`, `strategy-outline`) so log files don't collide
- [ ] No placeholder/TODO comments

---

### Phase B.5 — `PUT /save-research` automation endpoint

**Estimated time:** 10 minutes

**Files:**
- Modify: `backend/routes/api.php` (add route inside public automation group near save-prep)
- Test: `backend/tests/Feature/SaveResearchEndpointTest.php`

**Steps:**
1. Write failing feature test. Expected error: 404 on PUT to unregistered route. Test body: authenticated via automation token, PUT `/api/automation/content-ideas/42/save-research` with payload matching the lean schema v2 from design:
   ```json
   {
     "research_data": {
       "data_points": [{"stat":"...","source":"...","url":"https://x","year":2025}],
       "quotes": [{"text":"...","attribution":"...","url":"https://y"}],
       "entities": [{"name":"ChatGPT","url":"https://x","visual_style":"Green-teal..."}],
       "personas": [{"name":"...","pain":"...","emotion":"...","voice":"..."}],
       "written_guides": [{"task":"...","steps":["...","..."],"source_url":"https://z"}]
     }
   }
   ```
   Assert 200 response `{"success": true}` and `$idea->fresh()->research_data` equals payload.
2. Write second test: malformed body (missing `research_data` key, or non-array) → 400 response with helpful message (mirror save-prep guard style at [routes/api.php L597-L608](../../backend/routes/api.php#L597)).
3. Write third test: non-existent idea → 404.
4. Write fourth test: idempotency — second call with same payload overwrites first without error.
5. Run all 4, confirm failures.
6. In [routes/api.php](../../backend/routes/api.php), inside the `auth:sanctum,automation` public group near `/save-prep` (around L587), add:
   ```php
   Route::put('/content-ideas/{id}/save-research', function (\Illuminate\Http\Request $request, $id) {
       $idea = \App\Models\ContentIdea::find($id);
       if (!$idea) {
           return response()->json(['success' => false, 'message' => 'Idea not found.'], 404);
       }

       $researchData = $request->input('research_data', []);
       if (!is_array($researchData) || empty($researchData) || !isset($researchData['data_points'])) {
           \Illuminate\Support\Facades\Log::warning('[save-research] rejected malformed body', [
               'idea_id' => $id,
               'keys_received' => array_keys($request->all()),
               'raw_body_preview' => substr($request->getContent(), 0, 300),
           ]);
           return response()->json([
               'success' => false,
               'message' => 'Missing or invalid research_data. Body must be JSON with research_data.data_points at minimum. Use file-based curl (curl -d @file.json) to avoid shell quoting issues.',
               'keys_received' => array_keys($request->all()),
           ], 400);
       }

       $idea->update(['research_data' => $researchData]);

       return response()->json(['success' => true, 'message' => 'Research data saved.']);
   });
   ```
7. Re-run tests, confirm pass.
8. Commit: `feat(content-engine): add PUT /save-research automation endpoint`

**Verification:**
- [ ] `php artisan route:list | grep save-research` shows PUT under `auth:sanctum,automation`
- [ ] 200 on happy path; `research_data` column populated with full payload
- [ ] 400 on malformed body (missing `data_points`)
- [ ] 404 on unknown idea
- [ ] Idempotent: second PUT overwrites cleanly
- [ ] Guard log entry appears on rejected bodies
- [ ] No placeholder/TODO comments

---

### Phase B.6 — `continue-pipeline` branch: `progress 15 → strategy-outline`

**Estimated time:** 10 minutes

**Files:**
- Modify: `backend/routes/api.php` (extend continue-pipeline closure at L652-766)
- Test: `backend/tests/Feature/ContinuePipelineResearchSplitTest.php`

**Steps:**
1. Write failing test for split-flag ON path. Expected error: response `next_phase` does not match expected `strategy_outline`. Test body: `config()->set('services.article_generation.skill_split_enabled', true)`; seed idea with `progress_percentage=15`, `research_data` populated; Mockery-bind `ArticleGenerationService::triggerStrategyOutline` to return `['success'=>true,'pid'=>999,'error'=>null]`; POST `/automation/content-ideas/{id}/continue-pipeline`; assert 200 + `next_phase=strategy_outline` + `pid=999` + idea `process_pid=999`.
2. Write second test for split-flag OFF path: with flag false, progress=15 should fall through to existing "prep done 35%" branch miss — i.e. return 400 `No next phase to trigger` because in legacy path progress 15 doesn't trigger anything (prep is still inside a single call at that progress). Confirms we don't accidentally fire strategy-outline when split disabled.
3. Write third test for boundary: progress=35 with split flag ON should still call `triggerWrite` (strategy-outline completion point), NOT re-trigger strategy-outline. Validates we don't double-fire.
4. Run all 3, confirm failures.
5. Edit [routes/api.php L727-764 continue-pipeline closure](../../backend/routes/api.php#L727). Insert new branch BEFORE the existing `progress >= 35 && < 85 → triggerWrite` block:
   ```php
   $splitEnabled = (bool) config('services.article_generation.skill_split_enabled', false);

   // Research done (15%) → trigger strategy-outline (Phase B split flow only)
   if ($splitEnabled && $progress >= 15 && $progress < 35) {
       if (empty($idea->research_data)) {
           return response()->json([
               'success' => false,
               'message' => 'Cannot continue: research_data not yet saved. /article-research must call /save-research first.',
           ], 409);
       }
       $result = $service->triggerStrategyOutline($idea->id);
       $idea->update(['process_pid' => $result['pid']]);
       return response()->json(['success' => true, 'next_phase' => 'strategy_outline', 'pid' => $result['pid']]);
   }
   ```
   (The existing `progress >= 35 && < 85 → triggerWrite` block is untouched — it handles strategy-outline completion in the split flow AND legacy prep completion identically. Both land at 35%.)
6. Re-run tests, confirm pass.
7. Add regression test: legacy path (split flag OFF, progress=35) still dispatches `triggerWrite`. Ensures we didn't break Phase A/C E2E behavior.
8. Commit: `feat(content-engine): add strategy-outline dispatch branch to continue-pipeline`

**Verification:**
- [ ] With split flag ON + progress=15 + research_data saved → dispatches `triggerStrategyOutline`, response `next_phase=strategy_outline`
- [ ] With split flag ON + progress=15 + research_data EMPTY → 409 with clear message
- [ ] With split flag OFF + progress=15 → falls through to 400 (legacy unchanged)
- [ ] With split flag ON + progress=35 → dispatches `triggerWrite` (single-fire, not double)
- [ ] Legacy regression: split OFF + progress=35 still triggers `triggerWrite` correctly
- [ ] No placeholder/TODO comments

---

### Phase B.7 — `ContentIdeaController::startResearch` gate for split flow

**Estimated time:** 10 minutes

**Files:**
- Modify: `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php` (method that handles admin "Start Research" POST — locate by grepping for `triggerPrep` or `triggerGeneration`)
- Test: `backend/tests/Feature/StartResearchSplitGateTest.php`

**Steps:**
1. Run `grep -n "triggerPrep\|triggerGeneration" backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php` to locate the admin-side research kickoff method. Read it to understand current config-passing convention (topic, languages, instructions, keyword).
2. Write failing test. Expected error: when split flag ON and config includes `research_tier='deep'`, the controller calls `triggerResearch` with the correct tier; currently it calls `triggerPrep` ignoring the tier. Test body: Mockery on `ArticleGenerationService` — assert `triggerResearch(ideaId, $config, 'deep')` called once, `triggerPrep` NOT called. Also assert idea's `research_tier_override` column persists whatever value was passed.
3. Write second test: split flag OFF → still dispatches `triggerPrep` (legacy unchanged). Confirms feature-flag gating.
4. Write third test: `research_tier` omitted from config + split flag ON + idea.virality_score=85 + override='auto' → resolver picks `'deep'`, `triggerResearch` dispatched with `'deep'`. Validates auto-tier honored.
5. Run all 3, confirm failures.
6. Modify the controller method to:
   - Accept optional `research_tier` field in request (values: `'auto'|'quick'|'deep'`, default `'auto'`).
   - Persist `research_tier_override` on the idea before dispatch.
   - When `config('services.article_generation.skill_split_enabled') === true`: resolve tier via `$this->articleGen->resolveResearchTier($idea)` and call `$this->articleGen->triggerResearch($idea->id, $config, $tier)`.
   - When split flag OFF: keep current `triggerPrep` call path intact.
7. Re-run tests, confirm pass.
8. Commit: `feat(content-engine): gate Start Research to triggerResearch when split flag enabled`

**Verification:**
- [ ] Split flag ON + tier='deep' → `triggerResearch` called with `'deep'`, `triggerPrep` NOT called
- [ ] Split flag ON + tier='auto' + high virality → auto-resolves to `'deep'`
- [ ] Split flag OFF → `triggerPrep` called (backward compat, no regression)
- [ ] `research_tier_override` column persists the user's choice (for audit + subsequent re-runs)
- [ ] No placeholder/TODO comments

---

### Phase B.8 — Plugin: `refs-research.md` compiled reference

**Estimated time:** 18 minutes

**Files:**
- Create: `D:\Projects\claude-plugin\article-content-writer\references\compiled\refs-research.md` (target ~30 KB)
- Reference only (no new test — file size + content-check smoke test below)

**Design Deliverable:** The compiled ref assembles existing reference content already authored in the plugin repo. Before writing, list the sources and line ranges:

| Source | Purpose | Target section in refs-research.md |
|---|---|---|
| `references/global-config.md` §1-§4 (identity, tone, audience — trimmed) | Voice + audience grounding | §1 Voice |
| NEW deep-research framework (authored in this phase) | 4-layer framework spec | §2 Research Framework |
| NEW entity extraction rules (authored) | visual_style prose capture | §3 Entity Extraction |
| NEW written-guide extraction patterns (authored) | how-to topic step capture | §4 Written Guides |
| `references/global-config.md` §source-tiers (if present) | Source diversity matrix | §5 Source Diversity |
| NEW Opus prompt skeleton (from design L267-283) | Skeleton + self-review gate | §6 Opus Prompt |

**Steps:**
1. Read existing `D:\Projects\claude-plugin\article-content-writer\references\compiled\refs-prep.md` to understand assembly style + current size (~59 KB).
2. Read `D:\Projects\claude-plugin\article-content-writer\references\global-config.md` sections on voice, tone, audience, source tiers — identify re-usable blocks.
3. Create `refs-research.md` with 6 sections:
   - **§1 Voice** — trimmed global-config identity block (~3 KB)
   - **§2 Research Framework** — 4-layer framework from parent design L172-L202 verbatim (~6 KB expanded with examples)
   - **§3 Entity Extraction Rules** — explicit rules for capturing `visual_style` as PROSE (not nested JSON), 1 paragraph per entity, must include: color palette + layout pattern + typography + hero element + mood. Include 3 worked examples (ChatGPT, Figma, GitHub Copilot) from L305-L313 of design. (~5 KB)
   - **§4 Written Guides Extraction** — when to extract (how-to topics detected via topic keyword match: `setup, configure, install, how to, getting started, tutorial, guide`), format (task + flat `steps[]` array of imperatives + source_url), extraction heuristic: find step-numbered lists in official docs. 2 worked examples. (~4 KB)
   - **§5 Source Diversity Matrix** — hard rule: ≥4 primary_research, ≥3 forum, ≥3 expert, ≥2 counter, ≥2 case_study, ≥3 news. Enforce before save. Include 8 example queries from parent design L174-L182. (~3 KB)
   - **§6 Opus Prompt Skeleton + Self-Review Gate** — full XML skeleton from design L269-L283 with inline commentary on each tag. Self-review checklist: count check (20-30 data_points, 10+ entities, 3-5 personas, 5+ quotes), source matrix check, freshness check (flag >24mo stale), JSON schema validation. (~5 KB)
   - **§7 Output Schema v2** — paste schema from design L206-L239 verbatim, annotated with field-level constraints + target counts per tier (Quick vs Deep, from L244-L250 table). Include a fully-worked example payload for a sample topic ("best AI coding tools 2026"). (~4 KB)
4. Verify final file ~30 KB (target from design L169). If materially off, trim §1 voice section first (it's least load-bearing).
5. Smoke test: `wc -c refs-research.md` → confirm 25-35 KB; `grep -c "^##" refs-research.md` → confirm 7 top-level headers. No PHPUnit needed (it's a markdown reference).
6. Commit inside plugin repo: `feat(plugin): add refs-research.md compiled reference for deep research tier`. Note: this commit lives in `D:\Projects\claude-plugin\article-content-writer`, not Portfolio_v2.

**Verification:**
- [ ] File exists at `D:\Projects\claude-plugin\article-content-writer\references\compiled\refs-research.md`
- [ ] Size 25-35 KB (matches design target)
- [ ] 7 top-level `##` sections present
- [ ] 4-layer framework section quotes design L172-L202 verbatim
- [ ] Output schema §7 matches parent design v2 exactly (no field drift)
- [ ] 3 worked `visual_style` examples, 2 worked written-guide examples
- [ ] No placeholder text like `TODO: fill this in`

---

### Phase B.9 — Plugin: create `/article-research` skill

**Estimated time:** 16 minutes

**Files:**
- Create: `D:\Projects\claude-plugin\article-content-writer\skills\article-research\SKILL.md`

**Design Deliverable:** SKILL.md following the exact template of [article-prep SKILL.md](D:/Projects/claude-plugin/article-content-writer/skills/article-prep/SKILL.md). Sections:

1. **Frontmatter** — `name: article-research`, description explicitly scopes to pipeline-only + tiered + `--append-system-prompt-file refs-research.md` injection note.
2. **§1 Pipeline Flags** — table of: `--idea-id`, `--api-url`, `--api-token`, `--topic`, `--languages`, `--research-tier {quick|deep}`, `--keyword` (optional), `--instructions` (optional).
3. **§2 Don't Read Reference Files** — identical warning to article-prep L10: refs pre-injected.
4. **§3 Progress Reporting** — curl template + sub-step table:
   | Sub-step | Step Name | Percentage | Description |
   |---|---|---|---|
   | Input parsed | `input_collection` | 2 | Flags parsed |
   | Layer 1 started | `research_layer_1` | 5 | Broader discovery queries fired |
   | Layer 2 started | `research_layer_2` | 9 | Deep reads + entity extraction |
   | Layer 3 started | `research_layer_3` | 12 | Synthesis + gap analysis |
   | Layer 4 + Self-review | `research_layer_4` | 14 | Psychology + self-review gate |
   | Research saved | `research` | 15 | PUT /save-research complete |
5. **§4 Workflow** — 4 Layer sub-sections, each with instructions matching design L174-L202. Layer 2 explicitly calls out `WebFetch` (not snippets). Layer 4 conditional on how-to detection.
6. **§5 Tier Branching** — `if --research-tier quick`: run lightweight Layer 1 (2-3 queries) + Layer 4 persona extraction only; skip Layer 2 WebFetch + Layer 3 synthesis; target counts from parent design Quick column (L244). `if --research-tier deep`: full 4 layers, Opus self-review gate mandatory, target counts from Deep column.
7. **§6 Self-Review Gate (Deep only)** — before save, verify counts + source diversity; if under target, run targeted fill-in queries and re-check.
8. **§7 Save Research** — file-based curl heredoc example to `PUT /save-research` matching the Phase B.5 endpoint schema.
9. **§8 Continue Pipeline** — POST to `/continue-pipeline` with `{"completed_step": "research"}`.
10. **§9 Error Handling** — match article-prep §6: PUT /progress with `step=failed`, do NOT call continue-pipeline on error.

**Steps:**
1. Read [article-prep SKILL.md](D:/Projects/claude-plugin/article-content-writer/skills/article-prep/SKILL.md) end-to-end to internalize the template.
2. Read [article-images SKILL.md](D:/Projects/claude-plugin/article-content-writer/skills/article-images/SKILL.md) §1-§3 to understand the "don't read refs" + "read idea data via GET" patterns (article-research will use the same GET /content-ideas/{id} to read existing fields like `virality_score` or `source_data`).
3. Draft `skills/article-research/SKILL.md` using the 10-section outline above. Use Bash curl examples verbatim from article-prep but adjusted endpoint paths + step names.
4. Worked save-research payload example embedded (copy from parent design L206-L239 + inline token count target).
5. Smoke sanity: ensure total section count matches other skills (~200-280 lines), no placeholder text.
6. Commit inside plugin repo: `feat(plugin): add /article-research skill for tiered deep research`.

**Verification:**
- [ ] File exists at `D:\Projects\claude-plugin\article-content-writer\skills\article-research\SKILL.md`
- [ ] Frontmatter `name: article-research` present
- [ ] 6 progress sub-steps with step names that match the Progress Modal in Phase B.12 frontend phase
- [ ] `--research-tier` flag documented as required
- [ ] Layer 1-4 instructions correspond 1:1 to parent design L174-L202
- [ ] Save payload JSON matches Phase B.5 endpoint schema exactly
- [ ] Continue-pipeline trigger uses `{"completed_step": "research"}` (matches Phase B.6 branch)
- [ ] No placeholder text, no TODOs

---

### Phase B.10 — Plugin: create `/article-strategy-outline` skill + slim `refs-strategy-outline.md`

**Estimated time:** 14 minutes

**Files:**
- Create: `D:\Projects\claude-plugin\article-content-writer\skills\article-strategy-outline\SKILL.md`
- Create: `D:\Projects\claude-plugin\article-content-writer\references\compiled\refs-strategy-outline.md`
- (Leave existing `refs-prep.md` + `skills/article-prep/` UNTOUCHED — backward-compat fallback path.)

**Design Deliverable:**

**`refs-strategy-outline.md`** — slim rebuild of `refs-prep.md` without research sections:
- Keep: frameworks-library, hook-repository, emotional-arcs, content-templates, retention-engine, image-prompt-guide references (for outline image_concept mapping)
- Remove: research-specific guidance (source tiers, data point collection, entity extraction — all now in refs-research.md)
- Target size: ~25 KB (vs refs-prep.md ~59 KB).

**`skills/article-strategy-outline/SKILL.md`** — identical template to article-prep but:
1. Frontmatter: `name: article-strategy-outline`, description explicitly "Reads research_data from DB, runs Step 2 Strategy + Step 3 Outline on Sonnet".
2. **§1 Flags:** `--idea-id`, `--api-url`, `--api-token` only. (Topic/languages/instructions/keyword come from DB.)
3. **§2 Read Idea Data** — curl GET `/automation/content-ideas/{id}`. Extract:
   - `research_data.*` — all 5 fields (data_points, quotes, entities, personas, written_guides)
   - `title`, `description`, `languages`, `instructions`, `niche`, `pillar` — original idea metadata
4. **§3 Progress Reporting:**
   | Sub-step | Step | % | Description |
   |---|---|---|---|
   | Read input | `input_collection` | 17 | Research loaded from DB |
   | Strategy done | `strategy` | 25 | Framework + arc + hook + template picked |
   | Outline done | `outline` | 35 | Full outline + retention + image concepts |
5. **§4 Step 2 Strategy** — exact copy of article-prep Step 2 (framework selection, arc selection, 3 hooks, template). References the research_data personas for emotional targeting.
6. **§5 Step 3 Outline** — exact copy of article-prep Step 3 (retention, citations, image_concept mapping). New instruction: "When mapping `image_concept` for a section that primarily features a brand named in `research_data.entities[]`, include the entity name in the concept so /article-images can resolve the brand's visual_style." Example: `image_concept: "ChatGPT interface showing conversation flow"` (entity `ChatGPT` matches, visual_style applied later).
7. **§6 Save + Continue** — PUT /save-prep (existing endpoint, unchanged schema), POST /continue-pipeline `{"completed_step": "prep"}` (same label — triggers /article-write, same downstream).
8. **§7 Error Handling** — match article-prep §6.

**Steps:**
1. Read current `refs-prep.md`. Identify the research-only blocks to strip (Step 1 web-research guidance, source tiers, data-point collection rules). Leave frameworks / hooks / arcs / templates / retention / image-prompt-guide intact.
2. Create `refs-strategy-outline.md` by copying `refs-prep.md` → delete research sections → verify size drops to ~20-30 KB.
3. Create `skills/article-strategy-outline/SKILL.md` with the 8-section outline above. Copy Step 2 + Step 3 prose verbatim from article-prep (no reinvention — same strategy logic, same outline algorithm, same save payload schema) then append the brand-entity instruction in §5 Step 3.
4. Verify save payload schema matches existing `prep_data` structure on [save-prep endpoint L597](../../backend/routes/api.php#L597) exactly (outline is still the required key).
5. Smoke: `wc -c refs-strategy-outline.md` → 20-30 KB range. `grep -c "^##" SKILL.md` → 8 sections.
6. Commit inside plugin: `feat(plugin): add /article-strategy-outline skill + refs-strategy-outline.md`.

**Verification:**
- [ ] Both files created
- [ ] `refs-strategy-outline.md` is ~20-30 KB (significantly smaller than refs-prep.md)
- [ ] `refs-strategy-outline.md` contains frameworks + hooks + arcs + templates + retention sections
- [ ] `refs-strategy-outline.md` does NOT contain research / source-tier / data-point sections (those are in refs-research.md)
- [ ] SKILL.md reads research_data from DB via GET /content-ideas/{id}
- [ ] Save payload uses existing `/save-prep` endpoint unchanged
- [ ] Continue-pipeline label `completed_step: prep` preserved (so existing `progress >= 35 → triggerWrite` branch fires)
- [ ] `refs-prep.md` + `skills/article-prep/SKILL.md` UNTOUCHED (legacy fallback intact)
- [ ] No placeholder text

---

### Phase B.11 — Plugin: augment `/article-images` to read `visual_style`

**Estimated time:** 10 minutes

**Files:**
- Modify: `D:\Projects\claude-plugin\article-content-writer\skills\article-images\SKILL.md` (Context Extraction section + brand prompt assembly section)
- Modify: `D:\Projects\claude-plugin\article-content-writer\references\compiled\refs-images.md` (add a "Brand Visual Style Resolution" subsection, if image-prompt-guide doesn't already cover it)

**Design Deliverable:** In [article-images SKILL.md §3.5 Context Extraction](D:/Projects/claude-plugin/article-content-writer/skills/article-images/SKILL.md#L60), after the existing "Brands/Products/Tools" step, insert a new sub-step:

```markdown
### Brand Visual Style Resolution (NEW — Phase B)

After identifying brands/products in the article, resolve each brand's
visual_style by looking it up in `research_data.entities[]`:

1. Read `research_data.entities` from the idea payload (already fetched in §3 Read Idea Data)
2. For each brand/product identified in Context Extraction, find the
   matching entity by name (case-insensitive, partial match allowed —
   "ChatGPT" matches entity name "ChatGPT", "chat gpt", "openai chatgpt")
3. Capture the entity's `visual_style` prose paragraph
4. When composing the image prompt for this section, append the
   visual_style paragraph under a new "Brand aesthetic:" line, preceded
   by: "Feature {brand_name}-style UI mockup matching brand identity."

Example:
  Article mentions ChatGPT in section 3.
  Entity lookup finds: visual_style = "Green-teal + dark grey palette.
    Centered chat with speech bubbles and sidebar history. Söhne sans-serif.
    Prominent input field with Send icon. Clean, clinical mood."

  Image prompt for section 3 appends:
    "Feature ChatGPT-style UI mockup matching brand identity.
     Brand aesthetic: Green-teal + dark grey palette. Centered chat with
     speech bubbles and sidebar history. Söhne sans-serif. Prominent input
     field with Send icon. Clean, clinical mood."

If no entity matches (research didn't capture that brand OR research_data
is absent entirely e.g. legacy prep path), skip this sub-step — the prompt
falls back to the generic cinematic description.
```

**Steps:**
1. Read current article-images SKILL.md §3.5 (Context Extraction) to find the exact anchor line where the new sub-step should land.
2. Insert the "Brand Visual Style Resolution" block AFTER the existing `3.5` Context Extraction substeps but BEFORE `3.6` Reference Image Manifest. Renumber subsequent sub-steps if needed.
3. Verify `research_data.entities[]` is already part of the GET /content-ideas/{id} response (it is — the full idea row is returned per [routes/api.php L552-L558](../../backend/routes/api.php#L552)).
4. Decide whether refs-images.md needs an expanded section: if image-prompt-guide.md already covers "brand-aware prompts", no change needed; otherwise append ~2 KB summarizing the resolution algorithm (the skill already contains the primary instructions; ref is backup context).
5. Smoke: confirm the brand-entity instruction matches the one mandated in Phase B.10 §5 (skill-to-skill contract alignment — same entity name convention).
6. Commit inside plugin: `feat(plugin): augment /article-images with research_data.entities visual_style resolution`.

**Verification:**
- [ ] article-images SKILL.md has new "Brand Visual Style Resolution" sub-step between 3.5 and 3.6
- [ ] Sub-step references `research_data.entities[]` as the source
- [ ] Fallback branch: skip + use generic prompt if entity missing (backward-compat for legacy prep ideas)
- [ ] Example block (ChatGPT case) shows exact prompt structure
- [ ] Sub-step contract matches Phase B.10 entity-name instruction
- [ ] No placeholder text

---

### Phase B.12 — Frontend: Gate 1 Config Modal tier picker + model badge

**Estimated time:** 12 minutes

**Files:**
- Modify: `frontend/src/views/admin/ContentEngine.vue` (Config Modal template at L483-L510 + script setup)
- Modify: `frontend/src/composables/useContentEngine.js` (extend `startResearch` to accept `research_tier`)

**Design Deliverable:** Inline mockup — inserts below Languages row and above Instructions textarea:

```
Configure Research
─────────────────────
  Languages: [☑ EN] [☐ ID]

  Research Tier:
    ○ Auto     (picks Deep for virality_score ≥ 70, else Quick)
                → Preview: "Deep (score 82 ≥ 70, Opus, ~5-8 min)"
                [model badge: Opus]   [time: ~5-8 min]

    ○ Quick    (Sonnet, 1-2 min, 5-8 data points)
                [model badge: Sonnet] [time: ~1-2 min]

    ○ Deep     (Opus, 5-8 min, 20-30 data points + entities + personas)
                [model badge: Opus]   [time: ~5-8 min]

  Instructions: [textarea]

  [Cancel]  [Confirm & Research →]

Tier picker rules:
- Default: 'auto' (preselected)
- Radio group, tight spacing (not 3 big cards)
- Right-side badges: model name (Sonnet/Opus) in pill + estimated time
- Auto's preview line computes from idea.virality_score reactively
- If virality_score is null (no Phase A score), auto preview: "Quick (no score yet, Sonnet)"
- When skill_split_enabled=false (feature flag check via backend), the
  tier picker is hidden entirely and config falls through to legacy.
  Skip this complexity for v1 — show the picker always; backend
  ignores tier when flag off (no harm).
```

**Steps:**
1. Write failing smoke test. Expected error: `Element not found: [data-testid="research-tier-picker"]` in the Config Modal when opened. Before implementation → tier picker not rendered → fails. Also: `useContentEngine().startResearch` should accept `research_tier` key in config — before implementation, it silently drops the field; test asserts request body includes `research_tier: 'deep'` in Network tab.
2. In [useContentEngine.js](../../frontend/src/composables/useContentEngine.js), locate the `startResearch` method (around L80-ish, grep for it). Ensure the config payload passes `research_tier` through to the backend (the axios body already uses the full `config` object spread — likely no change needed; confirm during implementation).
3. In [ContentEngine.vue script setup](../../frontend/src/views/admin/ContentEngine.vue) near existing `configLanguages` / `configInstructions` refs, add:
   ```javascript
   const configResearchTier = ref('auto')  // 'auto' | 'quick' | 'deep'

   const tierPreview = computed(() => {
     const tier = configResearchTier.value
     const score = currentIdea.value?.virality_score
     if (tier === 'quick') return { model: 'Sonnet', time: '1-2 min' }
     if (tier === 'deep') return { model: 'Opus', time: '5-8 min' }
     // auto
     if (score == null) return { model: 'Sonnet', time: '1-2 min', note: 'no score yet → Quick' }
     if (score >= 70) return { model: 'Opus', time: '5-8 min', note: `score ${score} ≥ 70 → Deep` }
     return { model: 'Sonnet', time: '1-2 min', note: `score ${score} < 70 → Quick` }
   })
   ```
4. In `openConfigModal`, reset `configResearchTier.value = currentIdea.value?.research_tier_override || 'auto'` so editing an already-configured idea preserves the choice.
5. In `handleStartResearch`, include `research_tier: configResearchTier.value` in the config payload passed to `startResearch`.
6. In the Config Modal template (between the Languages row at L491-L496 and the Instructions row at L498-L501), insert:
   ```vue
   <div data-testid="research-tier-picker">
     <label class="block text-sm font-medium text-neutral-700 dark:text-neutral-300 mb-2">Research Tier</label>
     <div class="space-y-2">
       <label v-for="tier in ['auto','quick','deep']" :key="tier" class="flex items-start gap-2 cursor-pointer">
         <input type="radio" :value="tier" v-model="configResearchTier" class="mt-1 text-amber-600 focus:ring-amber-500" />
         <div class="flex-1 text-sm">
           <div class="flex items-center gap-2">
             <span class="capitalize text-neutral-700 dark:text-neutral-300 font-medium">{{ tier }}</span>
             <span v-if="tier === configResearchTier"
                   class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold border"
                   :class="tierPreview.model === 'Opus' ? 'bg-purple-500/15 text-purple-600 dark:text-purple-400 border-purple-500/30' : 'bg-sky-500/15 text-sky-600 dark:text-sky-400 border-sky-500/30'">
               {{ tierPreview.model }}
             </span>
             <span v-if="tier === configResearchTier" class="text-neutral-500 dark:text-neutral-400 text-xs">~{{ tierPreview.time }}</span>
           </div>
           <div v-if="tier === configResearchTier && tierPreview.note" class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">
             {{ tierPreview.note }}
           </div>
           <p v-else-if="tier === 'auto'" class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">Auto-picks Deep for virality_score ≥ 70</p>
           <p v-else-if="tier === 'quick'" class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">Sonnet, 5-8 data points, fast</p>
           <p v-else class="text-xs text-neutral-500 dark:text-neutral-400 mt-0.5">Opus, 20-30 data points + entities + personas</p>
         </div>
       </label>
     </div>
   </div>
   ```
7. Start dev server. Open `/admin/content-engine`, click Play ▶ on a draft idea → Config Modal should render the tier picker below Languages. Switch tiers → badges + preview note update reactively.
8. `npm run build` → clean.
9. Commit: `feat(content-engine): add research tier picker + model badge to Config modal`.

**Verification:**
- [ ] Tier picker renders with 3 radio options
- [ ] Default selection is `'auto'`
- [ ] Model badge updates per-selection (Opus purple, Sonnet sky)
- [ ] Preview note under Auto shows the resolution reasoning
- [ ] Editing an already-configured idea preselects its saved override
- [ ] Confirm click POSTs `research_tier` in the request body (Network tab)
- [ ] `npm run build` clean
- [ ] No placeholder/TODO comments

---

### Phase B.13 — Frontend: Progress Modal refactor — Research + Strategy+Outline cards

**Estimated time:** 14 minutes

**Files:**
- Modify: `frontend/src/views/admin/ContentEngine.vue` — `pipelinePhases` array at L855-L913 + `articleGenerationPhases` derived array + any template that renders phase cards

**Design Deliverable:** Replace the single `Prep` entry with two entries. Structure:

```javascript
pipelinePhases = [
  {
    name: 'Research',
    skill: '/article-research',
    modelDynamic: true,  // tier-dependent; fallback badge 'Sonnet/Opus'
    pctRange: '0–15%',
    minPct: 0,
    maxPct: 15,
    steps: [
      { name: 'input_collection', label: 'Input', pct: 2 },
      { name: 'research_layer_1', label: 'Layer 1 (Discovery)', pct: 5 },
      { name: 'research_layer_2', label: 'Layer 2 (Deep Read)', pct: 9, deepOnly: true },
      { name: 'research_layer_3', label: 'Layer 3 (Synthesis)', pct: 12, deepOnly: true },
      { name: 'research_layer_4', label: 'Layer 4 + Review', pct: 14 },
      { name: 'research', label: 'Saved', pct: 15 },
    ],
  },
  {
    name: 'Strategy+Outline',
    skill: '/article-strategy-outline',
    model: 'Sonnet',
    pctRange: '15–35%',
    minPct: 15,
    maxPct: 35,
    steps: [
      { name: 'input_collection', label: 'Input', pct: 17 },
      { name: 'strategy', label: 'Strategy', pct: 25 },
      { name: 'outline', label: 'Outline', pct: 35 },
    ],
  },
  // Write + Score + Images entries UNCHANGED
]
```

Cards in the Progress Modal render a model badge per phase:
- `Research` card: shows dynamic badge that reads the idea's resolved tier — Opus pill if `idea.research_tier_override === 'deep'` OR (`'auto'` and `virality_score >= 70`), else Sonnet pill.
- Layer 2 + Layer 3 sub-steps dimmed (neutral gray) + suffix `(deep only)` when the resolved tier is quick.
- `Strategy+Outline` card: static Sonnet badge.

Fallback: when `skill_split_enabled=false` backend still emits old `research/strategy/outline` step names at 15/25/35%. The refactored array still contains those step names (research_layer_4 maps to `research` completion), so the progress bar animates correctly in legacy mode. The two cards will just look like a "Prep" split visually — that's fine.

**Steps:**
1. Write failing visual smoke test. Expected error: `Element not found: phase card labeled "Research"` AND `Element not found: phase card labeled "Strategy+Outline"` in open Progress Modal. Before refactor → single "Prep" card. After → two distinct cards.
2. Locate the template block that iterates `articleGenerationPhases` (grep ContentEngine.vue for `pipelinePhases` and for the v-for that renders each phase card). Read the current card markup.
3. Replace the first entry of `pipelinePhases` (the `Prep` object) with the two new `Research` + `Strategy+Outline` objects from the design above.
4. In the card template, add a model badge element that reads:
   - `phase.model` when set (static: Sonnet for Strategy+Outline, Write, Score, Images)
   - dynamic resolver `resolvedResearchModel(idea)` when `phase.modelDynamic === true`
5. Add a small helper function in script setup:
   ```javascript
   function resolvedResearchModel(idea) {
     if (!idea) return 'Sonnet'
     const tier = idea.research_tier_override ?? 'auto'
     if (tier === 'deep') return 'Opus'
     if (tier === 'quick') return 'Sonnet'
     // auto
     return (idea.virality_score != null && idea.virality_score >= 70) ? 'Opus' : 'Sonnet'
   }
   ```
6. Add `deepOnly` dimming logic to sub-steps: `v-bind:class="{ 'opacity-40': step.deepOnly && resolvedResearchModel(progressIdea) === 'Sonnet' }"`.
7. Manual browser test:
   - Start generation on a `draft` idea with tier='auto' + virality_score=85 → Progress Modal opens showing 2 separate cards (Research + Strategy+Outline), Research card model badge = Opus, Layer 2/3 visible at full opacity.
   - Start on tier='quick' → Research card badge = Sonnet, Layer 2/3 dimmed with "(deep only)" visual.
   - Legacy mode (split flag OFF) → old `research/strategy/outline` step names still animate the progress bar; visual is just "Research" card progressing 0-15, "Strategy+Outline" card progressing 15-35. Acceptable.
8. `npm run build` → clean.
9. Commit: `feat(content-engine): split Prep phase card into Research + Strategy+Outline in Progress modal`.

**Verification:**
- [ ] Progress modal shows Research card and Strategy+Outline card as separate entries
- [ ] Research card model badge reads Opus for deep tier, Sonnet for quick (reactive to idea.research_tier_override + virality_score)
- [ ] Layer 2 + Layer 3 sub-steps dim when resolved tier is Sonnet (quick)
- [ ] Progress bar animates smoothly across both cards (0-15 Research, 15-35 Strategy+Outline, then 35-85 Write, 85-100 Score)
- [ ] Legacy mode (split flag OFF) still renders without visual regression — bar animates with old step names hitting each card's %
- [ ] `npm run build` succeeds
- [ ] No placeholder/TODO comments

---

### Phase B.14 — End-to-End Integration Test: split-flow tier resolution + dispatch

**Estimated time:** 12 minutes

**Files:**
- Create: `backend/tests/Feature/PhaseBSplitPipelineE2ETest.php`

**Steps:**
1. Write failing end-to-end feature test covering the backend split flow end-to-end without invoking real Claude CLI (all dispatches mocked):
   - Seed admin user + idea with `virality_score = 85` (deep-tier-eligible)
   - Flip `config('services.article_generation.skill_split_enabled', true)`
   - Mockery-bind `ArticleGenerationService`:
     - `resolveResearchTier($idea)` real (not mocked) — must return `'deep'`
     - `resolveResearchModel('deep')` real — must return `'opus'`
     - `triggerResearch(ideaId, Mockery::any(), 'deep')` → returns `['success'=>true,'pid'=>101,'error'=>null]`
     - `triggerStrategyOutline(ideaId)` → returns `['success'=>true,'pid'=>102,'error'=>null]`
     - `triggerWrite(ideaId)` → returns `['success'=>true,'pid'=>103,'error'=>null]`
   - Admin POST to the Start Research controller endpoint with config `{research_tier: 'auto', languages: ['en'], topic: 'AI tools'}` → assert 200, `triggerResearch` called once, idea `research_tier_override='auto'`, `process_pid=101`
   - Simulate /article-research callback: PUT `/automation/content-ideas/{id}/save-research` with full lean-schema-v2 payload → assert 200, `research_data` populated
   - Simulate progress tick: PUT `/automation/content-ideas/{id}/progress` with `{step:'research',percentage:15}` → idea progress_percentage=15
   - POST `/automation/content-ideas/{id}/continue-pipeline` → assert 200, `next_phase=strategy_outline`, `triggerStrategyOutline` called, `triggerResearch` NOT called again, process_pid=102
   - Simulate /article-strategy-outline callback: PUT `/save-prep` with valid prep_data (including outline.sections referencing entity names matching research_data) → 200
   - Simulate progress tick to 35% → `continue-pipeline` → `next_phase=write`, `triggerWrite` called, process_pid=103
   - Final assertions: idea has both `research_data` (5 fields) and `generated_article.prep_data` (outline etc.) populated; progress at 50% (write started); `triggerPrep` never called throughout
2. Run test, confirm failure at first assertion (or a wiring gap exposed during integration).
3. Fix any integration gaps that surface.
4. Write a second parallel test: `skill_split_enabled = false` + same idea → Start Research calls `triggerPrep` (legacy), `triggerResearch` never called. Confirms no regression.
5. Run all Phase B tests together: `php artisan test --filter="PhaseB|ResearchTier|TriggerResearch|TriggerStrategyOutline|SaveResearch|ContinuePipelineResearchSplit|StartResearchSplit"` — all green.
6. Commit: `test(content-engine): add Phase B split-pipeline E2E test (deep tier + strategy-outline dispatch)`.

**Verification:**
- [ ] E2E test passes with flag ON — full research → strategy-outline → write dispatch chain fires in order
- [ ] Auto tier correctly resolves to Deep + Opus for virality_score=85
- [ ] `triggerPrep` NEVER called when split flag on
- [ ] Legacy regression test passes with flag OFF — `triggerPrep` called, `triggerResearch` not called
- [ ] All Phase B test files green together (no inter-test pollution)
- [ ] `research_data` (column) and `generated_article.prep_data` (JSON nested) populated independently at correct steps
- [ ] No placeholder/TODO comments

---

### Phase B.15 — Plugin smoke test checklist (manual, staging)

**Estimated time:** 20 minutes (mostly waiting for CLI runs)

**Files:**
- Create: `docs/plans/2026-04-19-viral-first-content-pipeline-v3-phase-b-smoke-checklist.md` (one-page runbook, not a code artifact)

**Steps:**
This phase is NOT unit-testable — the plugin skills run live on the VPS. Instead it's a structured manual runbook executed in staging after:

1. Plugin commits from Phases B.8/B.9/B.10/B.11 pushed to the plugin repo.
2. Plugin installed/updated on VPS via the user's plugin distribution mechanism.
3. Compiled refs files copied to VPS: `refs-research.md` + `refs-strategy-outline.md` at `/home/claudesn/`.
4. Portfolio backend deployed to staging with `ARTICLE_GEN_SKILL_SPLIT_ENABLED=true` in staging `.env`.

Runbook content (~60 lines of markdown):

- **Smoke 1 — Quick tier happy path**
  - Pick idea with `virality_score < 70` (or set override='quick')
  - Start Research → tail `/tmp/article-research-*.log` → verify `--model sonnet` in command
  - Wait ~1-2 min → verify `research_data` column populated with 5-8 data_points, 0-2 entities, 1 persona
  - Auto-triggers `/article-strategy-outline` at 15% → tail `/tmp/article-strategy-outline-*.log` → verify `--model sonnet`
  - Wait ~1 min → verify `generated_article.prep_data.outline` saved
  - Auto-triggers `/article-write` at 35% → standard path

- **Smoke 2 — Deep tier happy path**
  - Pick idea with `virality_score >= 70` (or override='deep')
  - Start Research → tail log → verify `--model opus`
  - Wait 5-8 min → verify `research_data` has 20-30 data_points, 10+ entities with visual_style prose, 3-5 personas, 5+ quotes, 0-3 written_guides
  - Spot-check 3 entities: `visual_style` is a prose paragraph (not nested JSON), 30-60 words each
  - Spot-check 1 persona: `voice` is a single verbatim quote string (not an array)
  - Continue → strategy-outline (Sonnet) → write

- **Smoke 3 — Brand-aware /article-images**
  - Use a deep-tier idea whose research_data.entities include a well-known brand (e.g. ChatGPT, Figma)
  - After article_ready, click "Generate Images" Gate 2
  - Inspect `image_prompts[i].prompt` for the section that mentions the brand — verify it contains the `visual_style` prose paragraph (specific color / layout / typography phrases visible)

- **Smoke 4 — Legacy fallback**
  - Flip `ARTICLE_GEN_SKILL_SPLIT_ENABLED=false` via .env
  - Start Research on a new idea → `/tmp/article-prep-*.log` appears (legacy path), NOT article-research
  - Full pipeline runs on `/article-prep` → `/article-write` → `/article-score` (or skip per Phase C)
  - Confirms backward-compat intact

- **Smoke 5 — Kill switch**
  - Flip `ARTICLE_GEN_DEEP_RESEARCH_ENABLED=false`, leave `skill_split_enabled=true`
  - Start Research on an idea with `virality_score=95` + `override='auto'`
  - Verify log shows `--model sonnet` (kill switch honored — deep never picked)

**Steps:**
1. Draft the checklist file with 5 smoke-run sections above, each with: prereqs, execution commands, expected artifacts, pass/fail criteria.
2. Commit: `docs(content-engine): add Phase B plugin smoke-test runbook`.
3. (Post-commit, post-plugin-deploy to VPS) run the 5 smoke scenarios; record output + any fixes under a `## Actual Results` section at the bottom.

**Verification:**
- [ ] Runbook file created with 5 scenarios + pass/fail criteria
- [ ] Prereqs section lists plugin deploy + .env flag flip
- [ ] Each scenario includes tail-log command + field-level assertions on saved data
- [ ] Legacy fallback + kill-switch scenarios included
- [ ] No placeholder text — every assertion is concrete and verifiable

---

## Rollback Strategy

Phase B is **fully additive + feature-flagged + backward-compatible**. Rollback is near-zero-risk:

1. **Immediate rollback (no deploy)** — flip `ARTICLE_GEN_SKILL_SPLIT_ENABLED=false` in production `.env` on the VPS. Pipeline instantly reverts to legacy single `/article-prep` call. Nothing else changes. `triggerPrep` + `refs-prep.md` + `skills/article-prep/` are untouched throughout the plan.
2. **Kill-switch rollback (no deploy)** — flip `ARTICLE_GEN_DEEP_RESEARCH_ENABLED=false`. Split flow still active but all research forced to Sonnet/quick tier. Useful if Opus costs balloon unexpectedly.
3. **Frontend-only rollback** — revert Phases B.12 + B.13 commits; UI reverts to single Prep card + no tier picker. Backend continues working (tier defaults to 'auto' server-side; auto = Opus when virality_score ≥ 70).
4. **Full rollback** — `git revert` Phase B commits in reverse order. Then:
   - `php artisan migrate:rollback --step=1` drops `research_tier_override` — no data loss because nothing critical depends on it.
   - Remove 7 new `.env` keys (optional; defaults are harmless if flag unread).
   - Delete plugin commits (external repo) OR leave them in place — they don't affect the legacy path.
5. **Production validation gates** — after deploy:
   - Tail `storage/logs/laravel.log` for `[ArticleGeneration]` entries — verify `phase=research` + `model=opus` entries appear for high-virality ideas
   - Check `process_pid` column on recently-started ideas — should be populated
   - Click "Start Research" on a test idea with tier=deep → verify modal shows Opus badge + Layer 1-4 sub-steps animating

Per root CLAUDE.md: push policy is **commit-only-never-push**. Plugin repo commits are separate — user pushes/deploys plugin independently when ready.

## Open Questions Resolved During Plan

1. **`research_data` storage shape** — **RESOLVED:** use the existing `content_ideas.research_data` column (already present, already cast to array in model at L53). No nested `prep_data.research_data` path. Cleaner separation: research phase owns `research_data`, strategy-outline phase owns `generated_article.prep_data`.
2. **Plugin skill versioning** — `refs-prep.md` + `skills/article-prep/SKILL.md` kept UNCHANGED as legacy fallback. Backend switches skill based on `skill_split_enabled` flag. Zero plugin-side backward-compat burden.
3. **Batch cap on Layer 1 WebSearch** — parent design specifies 6-8 parallel queries. Embedded in skill `§4 Workflow` and refs-research `§2 Research Framework`. Not a hard guard in backend (Claude CLI enforces its own rate).
4. **Legacy `ARTICLE_GEN_MODEL_PREP`** — kept in env.example + config (still used by legacy `triggerPrep` fallback). NOT removed, despite design suggesting "Remove" at L327. Removal would be a breaking change for anyone still on legacy path. Re-evaluate after Phase B proves stable in production for ~30 days.
5. **Self-review gate enforcement in Opus** — relies on prompt instruction inside refs-research.md §6. No code-side count validator — the cost of enforcing counts server-side (reject-and-retry loop) exceeds the benefit since Opus is reliable on counted targets when the prompt explicitly demands it. If field counts routinely fall short in production, add a post-save validator as a follow-up.
6. **Progress step names — legacy overlap** — new skill emits `research` step at 15%, matching the legacy article-prep's "research" step name. Progress modal works seamlessly in both modes. Similarly `strategy` + `outline` step names are preserved from legacy for 25% + 35% checkpoints.
7. **Plugin deployment mechanism** — out of scope for this plan. User manages plugin installation on VPS via their existing plugin distribution workflow. Plan only specifies the plugin-repo file structure.
8. **Cost ceiling** — parent design flags as future ops concern. Kill switch (`ARTICLE_GEN_DEEP_RESEARCH_ENABLED=false`) provides immediate throttle if Opus spend exceeds budget. Per-day-per-user soft limits deferred.

## Execution Handoff

**Recommended: Option 3 — Save plan for a new session.**

Phase B spans 15 phases (~2-3 hours of focused work), crosses two repos (Portfolio_v2 backend + frontend AND article-content-writer plugin repo at `D:\Projects\claude-plugin\article-content-writer`), and depends on Phases A + C being shipped first. Starting in a fresh session is cleaner than interleaving.

**Option 2 alternative — Parallelize:**
Phases B.1-B.7 (backend) and B.8-B.11 (plugin) are largely independent. After Phase B.5 (save-research endpoint) is on a test branch, plugin skill work can proceed in parallel against a mocked backend. Consider `gaspol-parallel` with two worktrees once B.5 ships.

**Option 1 — Execute in this session:**
Possible but heavy. Expect interleaved context switches between Laravel (B.1-B.7 + B.14) and markdown plugin authoring (B.8-B.11) and Vue (B.12-B.13). If the user has 3+ uninterrupted hours, `gaspol-execute` will carry through with per-phase checkpoints.

Plan file at [docs/plans/2026-04-19-viral-first-content-pipeline-v3-phase-b-plan.md](2026-04-19-viral-first-content-pipeline-v3-phase-b-plan.md) is self-contained — everything needed to resume in any session.

Ready to start Phase B.1? Invoke `gaspol-execute` to implement with per-phase checkpoints + TDD hard gate.
