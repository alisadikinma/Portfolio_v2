> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

## Goal

Move image prompt authoring out of the article generation pipeline (`prep → write → score`) and into the image generation phase (Gate 2). Introduces a new `article-images` skill, a new compiled reference bundle `refs-images.md`, three new backend endpoints, a per-section image concept editor UI, and a clean separation between text approval (Gate 1) and image generation (Gate 2). Solves the `refs-write.md` bundle contradiction, delays compute budget spend until user commits to images, and lets users edit per-section image concepts before cinematic prompts are authored.

Companion design doc: `docs/plans/2026-04-15-image-prompt-phase-split-design.md`.

## Architecture Context

**From Portfolio_v2 CLAUDE.md:**
- Content Idea Pipeline: `draft → researching → article_ready → generating_images → images_ready → completed`
- Split article pipeline (3 CLI calls): `article-prep → article-write → article-score` via `ArticleGenerationService` (SSH driver on VPS)
- 2-Gate approval: Gate 1 approves article text, Gate 2 approves images
- 18 admin endpoints on `ContentIdeaController`, 8 automation endpoints for CLI callbacks
- Frontend Vue 3 SPA with `ContentEngine.vue` admin page, `useContentEngine.js` composable, 5 modals (Trending, Config, Progress, Article Preview, Image Config)
- Backend tests use PHPUnit (`php artisan test`), frontend has NO automated test framework (manual verification via Vite dev server)
- VPS has Claude CLI at `/home/claudesn/` with OAuth via SSH driver (`ARTICLE_GEN_DRIVER=ssh`)
- Plugin location on VPS: `/home/claudesn/.claude/plugins/cache/alisadikinma-ai-content-suite/article-content-writer/{2.3.0,2.0.0}/`
- Compiled refs on VPS: `/home/claudesn/refs-{prep,write,score}.md`

**From article-content-writer plugin structure:**
- 11 reference files in `references/`
- 6 skills: `article-prep`, `article-write`, `article-score`, `article-gen`, `article-brief`, `article-seo`, `article-validate` — will add `article-images` as 8th
- Compile script `scripts/compile-references.sh` builds 3 bundles today; will build 4
- Model uniform across phases: Sonnet (verified via VPS `.env`: all three `ARTICLE_GEN_MODEL_*` = sonnet)

**From in-progress partial refactor (already applied, needs reversal):**
- `skills/article-write/SKILL.md` has Cinematic prompt quality rule block + updated JSON payload comments — must be reverted (image prompts moving out of write)
- `skills/article-gen/SKILL.md` has 300-500 word bullet in Step 4 Section-bound analysis — must be reverted
- `agents/article-writer.md` has Prompt quality cinematic block in Step 4B — must be reverted
- `references/image-prompt-guide.md` has new Cinematic Standard sections + 3 examples — KEEP (these move to refs-images.md bundle)
- `references/cinematography-lut.md` untracked — KEEP (moves to refs-images.md bundle)

## Tech Stack

- Backend: Laravel 12, PHP 8.2, PHPUnit Feature tests
- Frontend: Vue 3.5 + Rolldown-Vite 7.1 + Pinia 3 + TanStack Query 5.90 + Tailwind 4
- Plugin: Markdown reference files + Bash compile script
- VPS: SSH MCP for deployment, Claude CLI for skill execution
- No new dependencies anywhere

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---|---|---|---|---|
| `outline.sections[].image_concept` | article-prep Step 3 output | Stored in `content_ideas.generated_article` JSON → `prep_data.outline` | Yes | Use existing (read via automation GET endpoint) |
| `outline.image_count` | article-prep Step 3 output | Same as above | Yes | Use existing |
| Article content + metadata | `GET /api/automation/content-ideas/{id}` | Existing automation endpoint | Yes | Use existing (already returns full idea state) |
| Trigger article-images skill | `ArticleGenerationService::triggerImages()` | New service method, mirrors `triggerWrite` | No | Create in `app/Services/ArticleGenerationService.php` |
| Skill saves `image_prompts[]` | `PUT /api/automation/content-ideas/{id}/save-image-prompts` | NEW route | No | Add to `routes/api.php` + `ContentIdeaController::saveImagePrompts()` |
| UI updates single concept | `PUT /api/admin/content-engine/ideas/{id}/update-image-concept` | NEW admin route | No | Add to routes + controller method |
| UI regenerates prompts (all or filtered) | `POST /api/admin/content-engine/ideas/{id}/regenerate-image-prompts` | NEW admin route | No | Add to routes + controller method |
| Pipeline continuation after images | `POST /api/automation/content-ideas/{id}/continue-pipeline` with `phase=images` | Extend existing endpoint | Partial | Extend existing handler to route `phase=images` → GeminiGen |
| Trigger GeminiGen from prompts | `ImageGenerationService` | Existing service | Yes | Use existing (called after save-image-prompts) |
| Compiled refs bundle | `references/compiled/refs-images.md` | Built by compile-references.sh | No | Add new build target in compile script |
| Refs path env | `ARTICLE_GEN_REFS_IMAGES` env var | New key in `config/services.php` | No | Add config key + env var |
| Model choice env | `ARTICLE_GEN_MODEL_IMAGES` env var | New key in `config/services.php` | No | Add config key + env var |
| Feature flag gate | `ARTICLE_GEN_USE_IMAGES_PHASE` env var | New key in `config/services.php` | No | Add config key + env var (default `false` for safe rollout) |
| Vue composable helpers | `useContentEngine.js` | Existing composable | Yes | Add new methods (`updateImageConcept`, `regenerateImagePrompts`) |
| Per-section image UI | `ImageConceptEditor.vue` | NEW component | No | Create under `src/components/admin/` |
| Gate 1 image plan metadata | `ArticlePreview.vue` | Existing component | Yes | Modify: drop image_prompts preview, add image plan row |
| Gate 2 concept editor integration | `ImageConfigModal.vue` | Existing component | Yes | Modify: swap instructions textarea for ImageConceptEditor |
| Progress authoring sub-phase | `ProgressModal.vue` | Existing component | Yes | Modify: add sub-phase card |
| Plugin skill definition | `skills/article-images/SKILL.md` | NEW skill | No | Create in plugin repo |
| Plugin scripts | `scripts/compile-references.sh` | Existing | Yes | Modify: build refs-images.md, trim refs-write.md |

## Phases

---

### Phase 0: Create feature branch + revert partial prior refactor

**Estimated time:** 5 minutes

**Files:**
- Modify: `D:\Projects\claude-plugin\article-content-writer\skills\article-write\SKILL.md`
- Modify: `D:\Projects\claude-plugin\article-content-writer\skills\article-gen\SKILL.md`
- Modify: `D:\Projects\claude-plugin\article-content-writer\agents\article-writer.md`

**Steps:**
1. `cd D:\Projects\claude-plugin\article-content-writer && git checkout -b feat/image-phase-split`
2. In `skills/article-write/SKILL.md`: remove "Cinematic prompt quality rule" block (between `**CRITICAL:**` and `Save the written article:`); revert JSON payload `prompt` comment back to `"20-80 word image prompt"` (will be fully removed in Phase 6)
3. In `skills/article-gen/SKILL.md`: remove the "Prompt length: 300-500 words..." bullet from Section-bound image analysis "If YES" list; revert JSON output comment `"prompt": "{300-500 word cinematic prompt}"` back to `"prompt": "{full_prompt}"`
4. In `agents/article-writer.md`: remove "Prompt quality:" cinematic block in Step 4B; revert prompt field description from "300-500 word cinematic..." back to "20-80 words describing a scene that SUPPORTS this section's message"; drop `cinematography-lut.md` from the references sentence

**Verification:**
- [ ] Branch `feat/image-phase-split` checked out
- [ ] `grep -n "Cinematic prompt quality\|8-element WOW\|300-500 word" skills/article-write/SKILL.md skills/article-gen/SKILL.md agents/article-writer.md` returns ZERO matches
- [ ] No placeholder/TODO comments introduced
- [ ] `git diff` shows only reversal of prior session's partial Cinematic refactor

---

### Phase 1: Create article-images skill directory + SKILL.md

**Estimated time:** 10 minutes

**Files:**
- Create: `D:\Projects\claude-plugin\article-content-writer\skills\article-images\SKILL.md`

**Steps:**
1. `mkdir D:\Projects\claude-plugin\article-content-writer\skills\article-images`
2. Create `SKILL.md` with frontmatter:
   ```yaml
   ---
   name: article-images
   description: "Pipeline-only skill for Gate 2 image prompt authoring. Reads article + outline blueprint from backend, expands per-section image_concept into 300-500 word cinematic prompts (8-element WOW + 5-paragraph structure). Runs on Sonnet with refs-images.md injected. Part of split pipeline: article-prep → article-write → article-score → [Gate 1] → article-images → GeminiGen."
   ---
   ```
3. Write sections:
   - **1. Pipeline Flags (Required)** — `idea_id`, `idempotency_key`, optional `only_sections=2,4`
   - **2. Don't Read Reference Files** — refs are in system prompt via `refs-images.md`
   - **3. Read Idea Data** — `GET /api/automation/content-ideas/{idea_id}` via curl with Bearer token, parse `prep_data.outline`, `generated_article.content`, `image_instructions`, `reference_images` (face/style)
   - **4. Filter Sections** — if `only_sections` provided, keep only those positions; else keep all sections with `image_concept != null`
   - **5. Authoring Rules** — 8-element WOW framework, 5-paragraph structure, 300-500 words per prompt, reference LUT for values, use `image_instructions` as global style guide, consider `reference_images` if present
   - **6. Cover Image** — always first in output, derived from article title/hook
   - **7. Output Format** — JSON `image_prompts[]` array matching existing schema (type, section, insert_after_heading, concept, prompt, model, style, aspect_ratio, resolution)
   - **8. Save via Callback** — `PUT /save-image-prompts` with full array
   - **9. Continue Pipeline** — `POST /continue-pipeline` body `{phase: "images", next: "gemini_gen"}`
   - **10. Progress Reporting** — `PUT /progress` at 10% (reading), 40% (authoring), 80% (saving), 100% (continuing)

**Verification:**
- [ ] `skills/article-images/SKILL.md` exists
- [ ] Frontmatter `name: article-images` present
- [ ] File contains sections 1-10 as listed
- [ ] References `refs-images.md` (not `refs-write.md`)
- [ ] No reference to "image file generation" — skill only authors prompts, GeminiGen is backend responsibility

---

### Phase 2: Update compile-references.sh — build refs-images.md + trim refs-write.md

**Estimated time:** 8 minutes

**Files:**
- Modify: `D:\Projects\claude-plugin\article-content-writer\scripts\compile-references.sh`

**Steps:**
1. Locate the `refs-write.md` block (lines ~91-121)
2. In the `append_ref_excluding "$WRITE" "$REFS_DIR/global-config.md"` call, add one more pattern to skip: `"## 11. Image Generation"` — already skipped. Confirm it's still there after Phase 0 revert. No other change to refs-write.
3. Add NEW block after `refs-score.md` section:
   ```bash
   # --- refs-images.md (Gate 2: Image Prompt Authoring) ---
   IMAGES="$OUT_DIR/refs-images.md"
   cat > "$IMAGES" << 'HEADER'
   # Article Generation Reference — Images (Gate 2)

   System prompt reference for the `/article-images` skill.
   Contains: global-config (Image Generation + Content Templates only), image-prompt-guide, cinematography-lut.
   These references are injected via --append-system-prompt-file. Do NOT read them with the Read tool.
   HEADER

   # global-config: keep §11 Image Generation + §16 Content Templates only, skip rest
   append_ref_excluding "$IMAGES" "$REFS_DIR/global-config.md" \
     "## 1. " "## 2. " "## 3. " "## 4. " "## 5. " \
     "## 6. " "## 7. " "## 8. " "## 9. " "## 10. " \
     "## 12. " "## 13. " "## 14. " "## 15. " "## 17. "

   append_ref "$IMAGES" "$REFS_DIR/image-prompt-guide.md"
   append_ref "$IMAGES" "$REFS_DIR/cinematography-lut.md"
   ```
4. Add `$IMAGES` to the size-report loop at the bottom

**Verification:**
- [ ] Script has `refs-images.md` build block
- [ ] `bash scripts/compile-references.sh` exits 0
- [ ] Output shows 4 compiled files including `refs-images.md`
- [ ] `grep -c "Cinematographer Signatures\|8-Element WOW\|Kodak Portra" references/compiled/refs-images.md` returns ≥ 3 matches
- [ ] `grep -c "8-Element WOW\|cinematography-lut" references/compiled/refs-write.md` returns 0 matches (write bundle trim verified)

---

### Phase 3: Recompile all reference bundles + size check

**Estimated time:** 3 minutes

**Files:**
- Run: `bash D:\Projects\claude-plugin\article-content-writer\scripts\compile-references.sh`
- Verify: `references/compiled/{refs-prep.md, refs-write.md, refs-score.md, refs-images.md}`

**Steps:**
1. Run compile script
2. Capture file sizes:
   - `refs-prep.md` — expect unchanged
   - `refs-write.md` — expect ≤49KB (trimmed back after Phase 0 revert)
   - `refs-score.md` — expect unchanged
   - `refs-images.md` — expect 18KB-25KB (NEW)

**Verification:**
- [ ] All 4 files exist
- [ ] `refs-images.md` size between 18-25KB
- [ ] `refs-write.md` size ≤ 49KB
- [ ] `wc -l references/compiled/refs-images.md` returns > 400 lines

---

### Phase 4: Plugin commit — reverts + new skill + new refs bundle

**Estimated time:** 3 minutes

**Files:** All plugin changes from Phases 0-3

**Steps:**
1. `cd D:\Projects\claude-plugin\article-content-writer`
2. `git add skills/article-write/SKILL.md skills/article-gen/SKILL.md skills/article-images/ agents/article-writer.md scripts/compile-references.sh references/compiled/ references/cinematography-lut.md references/image-prompt-guide.md`
3. Commit:
   ```
   feat: split image prompt authoring into dedicated Gate 2 skill

   - NEW skill article-images (Sonnet, authors 300-500w cinematic prompts from blueprint)
   - NEW refs-images.md bundle (global-config image sections + image-prompt-guide + cinematography-lut)
   - Revert partial cinematic refactor in article-write/article-gen/article-writer (images move out of Step 4)
   - refs-write.md trimmed back — no image-prompt-guide bloat
   - cinematography-lut.md + enhanced image-prompt-guide.md promoted to refs-images.md
   ```
4. Do NOT push yet — wait until backend + frontend also ready

**Verification:**
- [ ] `git status` clean on the plugin branch
- [ ] Commit exists on `feat/image-phase-split` branch
- [ ] `git log --oneline -1` shows the expected message

---

### Phase 5: Backend config — add article-images keys + fix model_write default

**Estimated time:** 5 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\backend\config\services.php`

**Steps:**
1. In the `'article_generation'` array, add:
   ```php
   'refs_images' => env('ARTICLE_GEN_REFS_IMAGES', ''),
   'model_images' => env('ARTICLE_GEN_MODEL_IMAGES', 'sonnet'),
   'use_images_phase' => env('ARTICLE_GEN_USE_IMAGES_PHASE', false),
   ```
2. FIX existing line `'model_write' => env('ARTICLE_GEN_MODEL_WRITE', 'opus'),` → change default `'opus'` to `'sonnet'` (match VPS `.env` reality)
3. No code logic change yet — just config keys available

**Verification:**
- [ ] `config('services.article_generation.refs_images')` returns empty string by default (PHP tinker check)
- [ ] `config('services.article_generation.model_images')` returns `'sonnet'`
- [ ] `config('services.article_generation.use_images_phase')` returns `false`
- [ ] `config('services.article_generation.model_write')` returns `'sonnet'` (default corrected)
- [ ] `php artisan config:clear` runs clean

---

### Phase 6: Backend service — add triggerImages() method

**Estimated time:** 8 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\backend\app\Services\ArticleGenerationService.php`
- Create: `D:\Projects\Portfolio_v2\backend\tests\Feature\ArticleGenerationServiceTest.php` (if not exists — otherwise modify)

**Steps:**
1. Add method after `triggerScore()`:
   ```php
   public function triggerImages(int $ideaId, string $idempotencyKey, array $onlySections = []): array
   {
       $extra = !empty($onlySections)
           ? ' only_sections=' . implode(',', $onlySections)
           : '';
       $prompt = "/article-images idea_id={$ideaId} idempotency_key={$idempotencyKey}{$extra}";
       $refsFile = config('services.article_generation.refs_images', '');
       $model = config('services.article_generation.model_images', 'sonnet');
       return $this->executePrompt($prompt, $ideaId, 'images', $model, $refsFile);
   }
   ```
2. Write Feature test asserting prompt string construction with and without `only_sections`
3. Run `php artisan test --filter=ArticleGenerationServiceTest` — expect it to fail first (RED) if test is fresh
4. Implement passes the test (GREEN)
5. No commit yet — batch with Phase 7-11 backend work

**Verification:**
- [ ] `triggerImages` method exists and matches signature
- [ ] `php artisan test --filter=triggerImages` passes
- [ ] Prompt string includes `/article-images idea_id=` prefix
- [ ] `only_sections` appended only when non-empty array passed

---

### Phase 7: Backend automation endpoint — save-image-prompts

**Estimated time:** 10 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\backend\routes\api.php`
- Modify: `D:\Projects\Portfolio_v2\backend\app\Http\Controllers\Api\Admin\ContentIdeaController.php`
- Create/modify: `D:\Projects\Portfolio_v2\backend\tests\Feature\ContentIdeaImagePromptsTest.php`

**Steps:**
1. Add route (automation group, throttle:60/min):
   ```php
   Route::put('/content-ideas/{id}/save-image-prompts', [ContentIdeaController::class, 'saveImagePrompts']);
   ```
2. Add controller method `saveImagePrompts(int $id, Request $request)`:
   - Validate: `image_prompts` array required, each item requires `type`, `insert_after_heading`, `concept`, `prompt`
   - Load `ContentIdea::findOrFail($id)`
   - Merge `image_prompts[]` into `generated_article` JSON (preserve existing article body + metadata)
   - Save model
   - Return `{success: true, data: {image_prompts_count: N}}`
3. Write Feature test:
   - Seed idea in `article_ready` state with no image_prompts
   - PUT the endpoint with 3 prompts + valid Bearer token
   - Assert 200 response + `generated_article.image_prompts` contains 3 items
4. Run test — expect RED before implementation, GREEN after

**Verification:**
- [ ] Route registered (`php artisan route:list | grep save-image-prompts` shows it)
- [ ] Feature test passes
- [ ] Request without Bearer token returns 401
- [ ] Malformed payload (missing `prompt`) returns 422

---

### Phase 8: Backend admin endpoint — update-image-concept

**Estimated time:** 8 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\backend\routes\api.php`
- Modify: `D:\Projects\Portfolio_v2\backend\app\Http\Controllers\Api\Admin\ContentIdeaController.php`
- Modify: `D:\Projects\Portfolio_v2\backend\tests\Feature\ContentIdeaImagePromptsTest.php`

**Steps:**
1. Add admin route:
   ```php
   Route::put('/content-engine/ideas/{id}/update-image-concept', [ContentIdeaController::class, 'updateImageConcept']);
   ```
2. Add controller method `updateImageConcept(int $id, Request $request)`:
   - Validate: `section_position` integer required, `image_concept` string or null required
   - Load idea, parse `generated_article.prep_data.outline.sections[]`
   - Find section by `position` field, update its `image_concept` field
   - Save
   - Return `{success: true, data: {updated_position: N, new_concept: "..."}}`
3. Write Feature test: seed idea with outline, PATCH concept on section position 2, assert concept updated

**Verification:**
- [ ] Route registered under `auth:sanctum` admin group
- [ ] Feature test passes
- [ ] Unauthenticated request returns 401
- [ ] Invalid `section_position` (not in outline) returns 404 with descriptive message

---

### Phase 9: Backend admin endpoint — regenerate-image-prompts

**Estimated time:** 10 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\backend\routes\api.php`
- Modify: `D:\Projects\Portfolio_v2\backend\app\Http\Controllers\Api\Admin\ContentIdeaController.php`
- Modify: `D:\Projects\Portfolio_v2\backend\tests\Feature\ContentIdeaImagePromptsTest.php`

**Steps:**
1. Add admin route:
   ```php
   Route::post('/content-engine/ideas/{id}/regenerate-image-prompts', [ContentIdeaController::class, 'regenerateImagePrompts']);
   ```
2. Add controller method `regenerateImagePrompts(int $id, Request $request, ArticleGenerationService $service)`:
   - Validate: `sections` array optional, each an integer
   - Load idea; verify status is `article_ready` OR `images_ready` (regen allowed)
   - Generate idempotency_key
   - Call `$service->triggerImages($id, $idempotencyKey, $request->input('sections', []))`
   - Update `current_step` to `authoring_image_prompts`, `progress_percentage` to 0
   - Return `{success: true, data: {job_id: idempotency_key}}`
3. Feature test: seed idea in `article_ready`, POST with `{sections: [2]}`, assert 200 + current_step updated

**Verification:**
- [ ] Route registered
- [ ] Feature test passes with mocked `ArticleGenerationService`
- [ ] Calling on `draft` or `researching` idea returns 409 Conflict
- [ ] Empty `sections` array regenerates all

---

### Phase 10: Backend — extend continue-pipeline for phase=images

**Estimated time:** 8 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\backend\app\Http\Controllers\Api\Admin\ContentIdeaController.php` (method `continuePipeline`)
- Modify: `D:\Projects\Portfolio_v2\backend\tests\Feature\ContentIdeaImagePromptsTest.php`

**Steps:**
1. Locate existing `continuePipeline` method (handles `phase=prep|write|score`)
2. Add branch for `phase === 'images'`:
   - Expected `next === 'gemini_gen'`
   - Verify idea has `generated_article.image_prompts[]` populated (else return 409)
   - Dispatch GeminiGen job via existing `ImageGenerationService` (or whatever the current pattern is — READ the service before writing)
   - Update idea status to `generating_images`, progress to 30%
3. Feature test: seed idea with image_prompts[], POST continue-pipeline body `{phase: "images", next: "gemini_gen"}`, assert GeminiGen triggered

**Verification:**
- [ ] continue-pipeline handles `phase=images`
- [ ] Returns 409 if no image_prompts exist yet
- [ ] Status transitions to `generating_images` at 30%
- [ ] Feature test passes

---

### Phase 11: Backend — rewrite generateImages() chain logic + feature flag

**Estimated time:** 10 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\backend\app\Http\Controllers\Api\Admin\ContentIdeaController.php` (method `generateImages`)
- Modify: `D:\Projects\Portfolio_v2\backend\tests\Feature\ContentIdeaImagePromptsTest.php`

**Steps:**
1. Locate existing `generateImages` method (currently triggers GeminiGen directly from saved prompts)
2. Rewrite with branch:
   ```php
   if (config('services.article_generation.use_images_phase')
       && empty(data_get($idea->generated_article, 'image_prompts'))
   ) {
       // New flow: author prompts first
       $key = Str::uuid()->toString();
       $this->articleGenerationService->triggerImages($id, $key);
       $idea->update(['current_step' => 'authoring_image_prompts', 'progress_percentage' => 5]);
       return response()->json(['success' => true, 'data' => ['mode' => 'authoring_prompts']]);
   }

   // Existing flow: prompts exist, go straight to GeminiGen
   // ... existing code unchanged ...
   ```
3. Feature test covering both branches:
   - Flag off → existing direct GeminiGen flow
   - Flag on + no prompts → article-images skill triggered
   - Flag on + prompts exist → straight GeminiGen (idempotent regen)

**Verification:**
- [ ] `generateImages` branches correctly on feature flag + prompts state
- [ ] Both Feature test cases pass
- [ ] Old flow preserved when flag is off (safety)

---

### Phase 12: Backend commit

**Estimated time:** 2 minutes

**Steps:**
1. `cd D:\Projects\Portfolio_v2\backend`
2. `git add config/services.php app/Services/ArticleGenerationService.php app/Http/Controllers/Api/Admin/ContentIdeaController.php routes/api.php tests/Feature/`
3. Commit:
   ```
   feat: image phase split — service method + 3 endpoints + feature flag

   - New triggerImages() service method (mirrors triggerWrite pattern)
   - New endpoints: save-image-prompts (automation), update-image-concept +
     regenerate-image-prompts (admin)
   - continue-pipeline handles phase=images → GeminiGen
   - generateImages() branches on ARTICLE_GEN_USE_IMAGES_PHASE flag
   - Fix model_write default (opus → sonnet) to match VPS reality
   - Feature tests for all new endpoints + service method
   ```

**Verification:**
- [ ] Commit created on main (or feature branch if preferred — decide per git workflow)
- [ ] `php artisan test` all passing (no regressions)

---

### Phase 13: Frontend — create ImageConceptEditor.vue component

**Estimated time:** 15 minutes

**Files:**
- Create: `D:\Projects\Portfolio_v2\frontend\src\components\admin\ImageConceptEditor.vue`

**Steps:**
1. Props:
   - `sections: Array` — filtered sections where `image_concept !== null` (includes position, title, arc_phase, image_concept)
   - `cover: Object` — cover image blueprint (concept derived from title)
   - `disabled: Boolean` — prevent edits while generating
2. Emits:
   - `update:concept` → payload `{position, concept}`
   - `regenerate` → payload `{sections: [positions]}`
3. Template:
   - List rendering for cover + each inline section
   - Each row: position label, section title, inline editable concept (double-click or pencil icon), individual "Regenerate" button
   - "Regenerate All" button at bottom
4. Use Tailwind classes consistent with existing admin UI (match `AwardEdit.vue` or `ImageConfigModal.vue` aesthetic)
5. Manual test: mount in Vue DevTools with mock props, verify edit + emit fires

**Verification:**
- [ ] Component renders with real `sections` array prop
- [ ] `update:concept` fires with correct payload on edit + blur
- [ ] `regenerate` fires with single-position on row button, all positions on "Regenerate All"
- [ ] No placeholder/TODO comments
- [ ] Disabled state prevents edits

---

### Phase 14: Frontend — modify ArticlePreview.vue (drop image_prompts preview, add plan row)

**Estimated time:** 8 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\frontend\src\views\admin\ContentEngine.vue` (if ArticlePreview is inline) OR `src/components/admin/ArticlePreview.vue` (verify path during Phase 0 exploration)

**Steps:**
1. Locate the image_prompts rendering block (look for `v-for="prompt in article.image_prompts"`)
2. Remove the block entirely
3. Add new "Image Plan" metadata row under article metadata:
   ```vue
   <div v-if="imagePlan.count > 0" class="mt-4 p-3 bg-slate-50 rounded">
     <div class="flex items-center gap-2 text-sm font-medium">
       📸 {{ imagePlan.count }} images planned
     </div>
     <ul class="mt-2 text-sm text-slate-600 space-y-1">
       <li v-for="item in imagePlan.items" :key="item.key">
         <strong>{{ item.label }}:</strong> {{ item.concept }}
       </li>
     </ul>
   </div>
   ```
4. Computed `imagePlan` reads `idea.generated_article.prep_data.outline.sections[]` where `image_concept` not null + cover from title

**Verification:**
- [ ] Image prompts preview block removed
- [ ] Image plan metadata row shows correct count + list on ideas with outline data
- [ ] Nothing renders when outline missing (graceful)
- [ ] Dev server manual test: open idea in `article_ready` state, plan visible, approve button still works

---

### Phase 15: Frontend — modify ImageConfigModal.vue to embed ImageConceptEditor

**Estimated time:** 12 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\frontend\src\views\admin\ContentEngine.vue` (modal section) OR separate `ImageConfigModal.vue`

**Steps:**
1. Locate existing ImageConfigModal template
2. Keep: reference image upload (face/style), global `image_instructions` textarea
3. Add: `<ImageConceptEditor :sections="editableConcepts" :cover="coverConcept" :disabled="busy" @update:concept="handleConceptEdit" @regenerate="handleRegenerate" />`
4. Bind editableConcepts + coverConcept computed from idea data
5. `handleConceptEdit({position, concept})` → call `updateImageConcept(id, position, concept)` composable method
6. `handleRegenerate({sections})` → call `regenerateImagePrompts(id, sections)` composable method
7. "Generate All Images" existing button flow: saves instructions, then calls `generateImages(id)` composable; backend branches on flag

**Verification:**
- [ ] Modal renders with ImageConceptEditor populated from real idea data
- [ ] Edit concept → API PUT called → state updates
- [ ] Regenerate single section → API POST called with `{sections: [N]}`
- [ ] "Generate All" still works as before (backend handles chain)
- [ ] No placeholders — all data from `idea.generated_article.prep_data.outline`

---

### Phase 16: Frontend — extend useContentEngine.js composable

**Estimated time:** 6 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\frontend\src\composables\useContentEngine.js`

**Steps:**
1. Add methods (mirror existing auth + error handling patterns):
   ```js
   async function updateImageConcept(ideaId, sectionPosition, imageConcept) {
     const { data } = await api.put(
       `/admin/content-engine/ideas/${ideaId}/update-image-concept`,
       { section_position: sectionPosition, image_concept: imageConcept }
     );
     return data;
   }

   async function regenerateImagePrompts(ideaId, sections = []) {
     const { data } = await api.post(
       `/admin/content-engine/ideas/${ideaId}/regenerate-image-prompts`,
       { sections }
     );
     return data;
   }
   ```
2. Export both from composable return object
3. Update TanStack Query invalidation: both methods should invalidate idea detail query on success

**Verification:**
- [ ] Both methods exported from useContentEngine
- [ ] Cache invalidation fires on success (idea query refetches)
- [ ] Error paths propagate to caller (no silent swallow)

---

### Phase 17: Frontend — modify ProgressModal.vue — add authoring sub-phase card

**Estimated time:** 8 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\frontend\src\views\admin\ContentEngine.vue` (ProgressModal section) OR separate component

**Steps:**
1. Locate existing 3-phase card layout (prep, write, score)
2. Add 4th card conditionally rendered when `idea.status === 'generating_images' && !hasImagePrompts`:
   ```vue
   <PhaseCard
     v-if="authoringPrompts"
     title="Authoring Image Prompts"
     :model="'sonnet'"
     :skill="'article-images'"
     :progress="idea.progress_percentage"
     :active="true"
   />
   ```
3. Computed: `authoringPrompts = idea.current_step === 'authoring_image_prompts'`
4. Once prompts saved, this card completes and GeminiGen card becomes active (existing logic)

**Verification:**
- [ ] Card appears during authoring phase
- [ ] Card disappears after prompts saved (status updates)
- [ ] Progress bar reflects 0-30% range for authoring phase
- [ ] Dev server manual test: trigger Generate Images, watch card appear

---

### Phase 18: Frontend commit + build verification

**Estimated time:** 5 minutes

**Steps:**
1. `cd D:\Projects\Portfolio_v2\frontend`
2. `npm run build` — confirm no type errors, builds clean
3. `git add src/components/admin/ImageConceptEditor.vue src/composables/useContentEngine.js src/views/admin/ContentEngine.vue` (and any other modified files)
4. Commit:
   ```
   feat: image phase split — UI for per-section concept editing + progress phasing

   - NEW ImageConceptEditor.vue component (inline concept editing + per-section regenerate)
   - ArticlePreview drops image_prompts preview, shows image plan metadata row
   - ImageConfigModal embeds ImageConceptEditor; preserves instructions + reference upload
   - ProgressModal adds "Authoring Image Prompts" sub-phase card
   - useContentEngine adds updateImageConcept + regenerateImagePrompts methods
   ```

**Verification:**
- [ ] `npm run build` succeeds
- [ ] Commit created
- [ ] Manual smoke test: Vite dev server shows admin content engine loading without errors

---

### Phase 19: Update .env + .env.example (local + VPS)

**Estimated time:** 4 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\backend\.env`
- Modify: `D:\Projects\Portfolio_v2\backend\.env.example`
- Modify (via SSH): `/var/www/Portfolio_v2/backend/.env`

**Steps:**
1. Add to local `.env` and `.env.example`:
   ```
   ARTICLE_GEN_REFS_IMAGES=
   ARTICLE_GEN_MODEL_IMAGES=sonnet
   ARTICLE_GEN_USE_IMAGES_PHASE=false
   ```
2. SSH to VPS, edit `/var/www/Portfolio_v2/backend/.env`:
   ```
   ARTICLE_GEN_REFS_IMAGES=/home/claudesn/refs-images.md
   ARTICLE_GEN_MODEL_IMAGES=sonnet
   ARTICLE_GEN_USE_IMAGES_PHASE=false
   ```
3. Run `php artisan config:clear` on VPS

**Verification:**
- [ ] Local `config('services.article_generation.refs_images')` returns empty
- [ ] VPS `config('services.article_generation.refs_images')` returns `/home/claudesn/refs-images.md`
- [ ] VPS flag remains `false` (safe rollout)

---

### Phase 20: Plugin push + VPS deploy

**Estimated time:** 5 minutes

**Files:**
- GitHub: push `feat/image-phase-split` branch, merge to main
- VPS: deploy updated plugin files + refs-images.md

**Steps:**
1. Plugin: `git push origin feat/image-phase-split`
2. Merge to main (PR or direct, per git workflow)
3. SSH deployment script:
   ```bash
   for v in 2.3.0 2.0.0; do
     BASE="/home/claudesn/.claude/plugins/cache/alisadikinma-ai-content-suite/article-content-writer/$v"
     [ -d "$BASE" ] || continue
     mkdir -p "$BASE/skills/article-images"
     for f in \
       "skills/article-images/SKILL.md" \
       "skills/article-write/SKILL.md" \
       "skills/article-gen/SKILL.md" \
       "agents/article-writer.md" \
       "scripts/compile-references.sh" \
       "references/cinematography-lut.md" \
       "references/image-prompt-guide.md" \
       "references/compiled/refs-prep.md" \
       "references/compiled/refs-write.md" \
       "references/compiled/refs-score.md" \
       "references/compiled/refs-images.md"; do
       curl -sL "https://raw.githubusercontent.com/alisadikinma/article-content-writer/main/$f" -o "$BASE/$f"
     done
   done
   # Deploy refs-images.md to canonical location
   cp /home/claudesn/.claude/plugins/cache/alisadikinma-ai-content-suite/article-content-writer/2.3.0/references/compiled/refs-images.md /home/claudesn/refs-images.md
   ```

**Verification:**
- [ ] Plugin merged to main
- [ ] Both plugin versions have `skills/article-images/SKILL.md`
- [ ] `/home/claudesn/refs-images.md` exists, size 18-25KB
- [ ] `grep "Kodak Portra" /home/claudesn/refs-images.md` returns matches
- [ ] `ls -la /home/claudesn/refs-*.md` shows 4 files (prep, write, score, images)

---

### Phase 21: E2E regression test — flag OFF path

**Estimated time:** 5 minutes (manual)

**Steps:**
1. Keep `ARTICLE_GEN_USE_IMAGES_PHASE=false` on VPS
2. In admin panel, regenerate an existing idea from scratch (Start Research)
3. Wait for completion, approve at Gate 1, click Generate Images
4. Verify: old flow works unchanged — images generated from in-pipeline prompts
5. Confirm: no article-images skill invocation in logs

**Verification:**
- [ ] Full flow completes without errors
- [ ] Images generated correctly via old path
- [ ] No regressions introduced by refactor

---

### Phase 22: E2E activation test — flag ON path

**Estimated time:** 10 minutes (manual)

**Steps:**
1. SSH VPS: set `ARTICLE_GEN_USE_IMAGES_PHASE=true`, `php artisan config:clear`
2. Create new idea, Start Research, approve at Gate 1 (verify: no image_prompts in article)
3. Open Image Config modal — verify ImageConceptEditor shows with concepts from outline
4. Edit one concept, save; click "Regenerate Selected"
5. Watch Progress modal — verify "Authoring Image Prompts" card appears, then "Generating Images" card
6. Wait for completion, inspect generated image_prompts[] — verify 300-500 word cinematic prompts with 8-element WOW compliance
7. Approve at Gate 2 → status `completed`

**Verification:**
- [ ] New flow completes successfully
- [ ] Image prompts meet cinematic quality (length + 8-element WOW + 5-paragraph structure)
- [ ] Edit-one-concept regenerates only that section
- [ ] Regenerate-all rewrites every prompt
- [ ] `generated_article.image_prompts[]` in DB contains full cinematic prompts

---

### Phase 23: Update CLAUDE.md documentation

**Estimated time:** 5 minutes

**Files:**
- Modify: `D:\Projects\Portfolio_v2\CLAUDE.md`

**Steps:**
1. Update "Content Pipeline (CLI-Based)" section:
   - Change pipeline diagram to 4 phases (prep → write → score → images)
   - Add article-images skill to the skill list
   - Update model table (all uniform Sonnet)
2. Update env var list: add `ARTICLE_GEN_REFS_IMAGES`, `ARTICLE_GEN_MODEL_IMAGES`, `ARTICLE_GEN_USE_IMAGES_PHASE`
3. Update endpoint list: add 3 new endpoints
4. Commit:
   ```
   docs: update CLAUDE.md — image phase split architecture
   ```

**Verification:**
- [ ] CLAUDE.md reflects new pipeline accurately
- [ ] All new env vars documented
- [ ] All new endpoints listed
- [ ] Model column shows uniform Sonnet

---

## File Change Summary

| Phase | File | Action | Location |
|---|---|---|---|
| 0 | article-write/SKILL.md | REVERT cinematic rule | plugin/skills |
| 0 | article-gen/SKILL.md | REVERT cinematic rule | plugin/skills |
| 0 | article-writer.md | REVERT cinematic rule | plugin/agents |
| 1 | article-images/SKILL.md | CREATE | plugin/skills |
| 2 | compile-references.sh | MODIFY | plugin/scripts |
| 3 | refs-images.md | BUILD | plugin/references/compiled |
| 4 | Plugin commit | COMMIT | plugin git |
| 5 | config/services.php | MODIFY | backend/config |
| 6 | ArticleGenerationService.php | ADD METHOD | backend/app/Services |
| 7-11 | ContentIdeaController.php | ADD 4 METHODS + MODIFY 2 | backend/app/Http/Controllers |
| 7-11 | routes/api.php | ADD 3 ROUTES | backend/routes |
| 7-11 | tests/Feature/ContentIdeaImagePromptsTest.php | CREATE | backend/tests |
| 12 | Backend commit | COMMIT | backend git |
| 13 | ImageConceptEditor.vue | CREATE | frontend/components/admin |
| 14 | ArticlePreview logic | MODIFY | frontend/views/admin |
| 15 | ImageConfigModal logic | MODIFY | frontend/views/admin |
| 16 | useContentEngine.js | ADD METHODS | frontend/composables |
| 17 | ProgressModal logic | MODIFY | frontend/views/admin |
| 18 | Frontend commit | COMMIT | frontend git |
| 19 | .env + .env.example (local + VPS) | ADD ENV VARS | both |
| 20 | Plugin push + VPS deploy | DEPLOY | VPS |
| 21-22 | E2E regression + activation | TEST | admin panel |
| 23 | CLAUDE.md | UPDATE DOCS | repo root |

## Dependencies

- Plugin repo clone at `D:\Projects\claude-plugin\article-content-writer\`
- SSH access to VPS (already established via `mcp__ssh-prod-vps__exec` MCP)
- GeminiGen API operational (for Phase 22 test)
- Existing content_ideas with outline data in DB (for manual smoke tests)

## Rollout Safety

- Feature flag `ARTICLE_GEN_USE_IMAGES_PHASE` default `false` — old flow preserved
- In-flight ideas with existing `image_prompts[]` continue with old flow (backend checks emptiness before triggering article-images)
- Rollback plan: set flag to `false`, existing code paths intact — no DB migration to reverse

## Estimated Total Time

- Plugin changes (Phases 0-4): ~30 min
- Backend changes (Phases 5-12): ~60 min
- Frontend changes (Phases 13-18): ~55 min
- Env + deploy (Phases 19-20): ~10 min
- Testing + docs (Phases 21-23): ~20 min
- **Total: ~175 min (~3 hours)**

## Open Questions (Resolve During Execution)

1. Does `article-gen` single-session fallback need updating, or stays inline? (Lean: inline — fallback is refs-not-configured edge case)
2. Reference images (face/style) from ImageConfigModal — pass to article-images via `image_instructions` concat, or separate `reference_images` field? (Lean: separate field, richer context)
3. Rate-limiting on regenerate-image-prompts? (Lean: no — per-idea admin-only, low frequency)
4. Race condition: user clicks Generate Images twice quickly — idempotency_key uniqueness sufficient? (Lean: yes, existing pattern)
