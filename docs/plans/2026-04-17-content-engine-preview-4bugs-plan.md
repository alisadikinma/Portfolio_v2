> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

## Goal

Fix four bugs in the admin Content Engine preview → image → finalize pipeline so the UX matches its intent: plugin-determined image positions render consistently across Article and Finalize steps, users can revisit earlier steps without spurious 422 errors, the default language tab is Indonesian with a clear untranslated-notice on the English tab, and image variants are preserved until the final Publish click (cleanup happens server-side atomically with Post creation).

Design doc: [docs/plans/2026-04-17-content-engine-preview-4bugs-design.md](2026-04-17-content-engine-preview-4bugs-design.md)

## Architecture Context

Pulled from `CLAUDE.md`:

- **Admin views (Vue 3):** `frontend/src/views/admin/ArticlePreview.vue`, `ArticleFinalize.vue`, `ImageGeneration.vue` are the 3-step Content Engine pipeline UI.
- **Step navigator:** `frontend/src/components/admin/PipelineStepBar.vue` makes completed steps clickable → users can navigate back, which is how Bug 2 and Bug 4 surface.
- **Composable:** `frontend/src/composables/useContentEngine.js` exposes `getIdea`, `approveArticle`, `approveAndPublish`, `saveDraft`, `cleanupVariationImages`, etc. (16+ methods).
- **Backend controller:** `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php` owns the pipeline state machine. `approveArticle` (line 460) hard-requires `status === 'article_ready'`. `approveAndPublish` (line 764) creates `Post` + `PostTranslation` rows.
- **Variation schema:** `image_prompts[].variations[] = [{url, job_uuid, status, source?}]`, plus `selected_variation` index and legacy `generated_url`. Max 3 variations per segment. See commit `bed00194`.
- **Cleanup endpoint:** `POST /admin/content-engine/cleanup-variation-images` in `backend/routes/api.php:789-810` — currently called from frontend `ImageGeneration.vue::handleApprove`. Logic: strip `url(/storage/)` prefix, `Storage::disk('public')->delete($path)`.
- **Post upsert:** `approveAndPublish` writes to both `posts` (keyed by `source_idea_id`) and `post_translations` (keyed by `post_id + language`). Title/content/excerpt live in `post_translations`, NOT `posts`.
- **No unit test runner on frontend** (only Playwright E2E). No backend test suite either. Verification is manual via browser + `php artisan tinker` or DB inspection.

## Tech Stack

- Vue 3.5 `<script setup>`, Pinia 3, Tailwind 4, TanStack Query 5.90.
- Laravel 12, MySQL 8, Sanctum 4, Intervention Image 3.11, `Storage::disk('public')`.
- No new deps required.

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---------|-------------|----------|---------|--------|
| Shared image position resolver | `image_prompts[].suggested_position` + `insert_after_heading` | New `frontend/src/utils/imagePositioning.js` | **No** | Create new pure-JS helper (extract from `ArticlePreview.vue:66-101`) |
| Content idea fetch | `GET /api/admin/content-engine/ideas/{id}` | `useContentEngine().getIdea` | Yes | Use existing |
| Approve article text | `POST /api/admin/content-engine/ideas/{id}/approve-article` | `useContentEngine().approveArticle` | Yes | Use existing; gate call by status |
| Approve & publish | `POST /api/admin/content-engine/ideas/{id}/publish` | `useContentEngine().approveAndPublish` | Yes | Extend backend to do compaction + cleanup |
| Save draft (Image step) | `POST /api/automation/content-ideas/{id}/save-draft` path via `saveDraft` composable | `useContentEngine().saveDraft` | Yes | Use existing |
| Cleanup variation files | `POST /api/admin/content-engine/cleanup-variation-images` | `useContentEngine().cleanupVariationImages` | Yes | Remove frontend caller; logic moves into `approveAndPublish` |
| Storage deletion | `Storage::disk('public')->delete()` | Laravel Facade | Yes | Use existing |
| Translation presence flag | `generated_article.en?.content` existence | Plain boolean check | Yes | Inline in both views |
| Language tab state | Local `ref('id')` / `ref('en')` | Component-local ref | Yes | Change default |
| Pipeline step bar status | `idea.status` → step mapping in `PipelineStepBar.vue:19-24` | Props | Yes | Reuse mapping logic for routing in `handleApprove` |

## Phase A — Extract shared image-positioning helper (TDD)

**Estimated time:** 10 minutes

**Files:**
- Create: `frontend/src/utils/imagePositioning.js`
- Create (smoke test): `frontend/src/utils/imagePositioning.test.mjs`
- Modify: `frontend/src/views/admin/ArticlePreview.vue`

**Steps:**
1. Create `frontend/src/utils/imagePositioning.js` exporting two pure functions:
   - `parseBlockElements(html)` — copy from `ArticlePreview.vue:66-71`
   - `resolveImagePosition(img, index, totalImages, blocks)` — copy from `ArticlePreview.vue:74-101`. No changes to logic.
2. Write a Node smoke test `frontend/src/utils/imagePositioning.test.mjs` exercising:
   - Cover image → `0`
   - `suggested_position: 5` with 10 blocks → `5`
   - `insert_after_heading: "Introduction"` matches `<h2>Introduction</h2>` at block index 2 → `3`
   - No hints, 4 images over 12 blocks → evenly distributed
3. Run: `cd frontend && node src/utils/imagePositioning.test.mjs` — confirm all assertions log `PASS`. (Requires `DOMParser`; use `linkedom` if unavailable, OR run in `node --experimental-vm-modules` with a minimal DOM shim. Simpler: use regex-based `parseBlockElements` fallback when `DOMParser` absent — see implementation note below.)
4. In `ArticlePreview.vue`:
   - Add import: `import { parseBlockElements, resolveImagePosition } from '@/utils/imagePositioning'`
   - Remove the two local function definitions (lines 66-101).
5. Verify in browser: open `/admin/content-engine/{id}/preview` for an idea with article generated → images distributed correctly (no regression).
6. Commit: `refactor(content-engine): extract image positioning to shared helper`

**Implementation note (step 2):** If running the smoke test in Node without a DOM fails because `DOMParser` is missing, the simplest portable approach is to stub `global.DOMParser` with a regex-based parser for the test only. Keep `imagePositioning.js` using real `DOMParser` (which works in the browser). Document this in the test file header.

**Verification:**
- [ ] `frontend/src/utils/imagePositioning.js` exists and exports both functions
- [ ] Smoke test logs 4 PASS assertions (cover=0, suggested=5, heading-match=3, even-distribute)
- [ ] `ArticlePreview.vue` imports from the helper, no duplicated local definitions
- [ ] Browser: Article Preview page still renders images at correct positions (manual spot-check against an existing idea where body images previously appeared under H2 sections)
- [ ] No placeholder/TODO comments in new code

---

## Phase B — Finalize uses shared resolver + default lang=id + untranslated banner

**Estimated time:** 12 minutes

**Files:**
- Modify: `frontend/src/views/admin/ArticleFinalize.vue`

**Steps:**
1. Import the helper: `import { parseBlockElements, resolveImagePosition } from '@/utils/imagePositioning'`
2. Change `const activeLang = ref('en')` → `const activeLang = ref('id')`.
3. Update `onMounted` to pick best initial lang: after `getIdea` resolves, if the article has no `id` translation but has `en`, set `activeLang.value = 'en'`.
4. Rewrite `contentWithImages` computed (currently `ArticleFinalize.vue:52-81`):
   - Parse HTML into blocks via shared `parseBlockElements`
   - Filter `image_prompts` to `type !== 'cover'` and `generated_url` truthy
   - For each such image, compute `const pos = resolveImagePosition(img, origIndex, total, blocks)` where `origIndex` is the image's original index in `imagePrompts.value` and `total` is the count of non-cover images with generated URL
   - Sort by resolved `pos` descending, splice into blocks, join
5. Add `isUntranslated` computed: `computed(() => activeLang.value === 'en' && !article.value?.en?.content)`.
6. In template, wrap cover + title + article body in `<template v-if="!isUntranslated">` and add a sibling `<div v-else>` rendering the info banner:
   ```
   "Belum diterjemahkan. Terjemahan otomatis akan berjalan saat Publish."
   ```
   Styling: same container max-width, amber-tinted card (`bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4`), heroicon globe or translate icon.
7. Keep language tabs above the banner so user can switch back to Indonesia.
8. Commit: `fix(content-engine): finalize uses plugin positioning + id default + untranslated banner`

**Verification:**
- [ ] Load `/admin/content-engine/{id}/finalize` for an existing Indonesian idea with approved images
- [ ] Indonesia tab is active by default
- [ ] Images appear at the same positions as in Article Preview step (spot-check 2-3 heading-matched images)
- [ ] Click English tab → banner shown, no body/cover/title rendered
- [ ] Click Indonesia tab again → full content returns
- [ ] `vite dev` console shows no errors/warnings from this view
- [ ] No placeholder/TODO comments in new code

---

## Phase C — ArticlePreview: status-gated approve + untranslated banner

**Estimated time:** 10 minutes

**Files:**
- Modify: `frontend/src/views/admin/ArticlePreview.vue`

**Steps:**
1. In `handleApprove` (line 317), add an early branch **before** the existing API call:
   ```js
   const status = idea.value?.status
   if (status && status !== 'article_ready') {
     const route = status === 'completed' ? 'finalize' : 'images'
     router.push(`/admin/content-engine/${idea.value.id}/${route}`)
     return
   }
   ```
   Keep existing approve-flow for `article_ready`.
2. Add `isUntranslated` computed (same pattern as Phase B): `computed(() => activeLang.value === 'en' && !article.value?.en?.content)`.
3. Wrap the `<!-- Title Editor -->` and `<!-- Article Body with Image Placeholders -->` blocks (lines 470-580) in `<template v-if="!isUntranslated">`, add sibling `<div v-else>` with the same banner copy used in Phase B.
4. Keep Image Plan Summary (lines 440-452) visible regardless — it's plugin-output, lang-agnostic. Keep language tabs above so user can switch.
5. Commit: `fix(content-engine): preview skips approve call past article_ready, adds EN banner`

**Verification:**
- [ ] Manually set an idea to `images_ready` status (or use one already in that state): navigate to `/admin/content-engine/{id}/preview`, click "Approve & Continue" → routes to `/images` without 422 error
- [ ] For `completed` idea: same click → routes to `/finalize`
- [ ] For `article_ready` idea: existing approve flow still works (API called, routes to `/images`)
- [ ] Click English tab on an Indonesian-only article → banner shown, title/body hidden
- [ ] No placeholder/TODO comments in new code

**Manual status override** (if needed to test):
```
php artisan tinker
>>> App\Models\ContentIdea::find(88)->update(['status' => 'images_ready']);
```

---

## Phase D — ImageGeneration: remove early cleanup

**Estimated time:** 5 minutes

**Files:**
- Modify: `frontend/src/views/admin/ImageGeneration.vue`

**Steps:**
1. Replace `handleApprove` body (lines 441-475) with:
   ```js
   async function handleApprove() {
     await persistDraft()
     router.push(`/admin/content-engine/${idea.value.id}/finalize`)
   }
   ```
2. Remove `cleanupVariationImages` from the `useContentEngine()` destructure on line 13.
3. Leave the composable export + route + backend route as-is (unused now; separate cleanup task).
4. Commit: `fix(content-engine): preserve image variants until publish`

**Verification:**
- [ ] Generate 3 variants for Cover on a test idea, select variant 2, click "Approve & Continue"
- [ ] Navigate back to `/images` via Pipeline Step Bar → **all 3 variants still present**, variant 2 still marked selected
- [ ] `ls storage/app/public/content-engine/...` shows all 3 variant files still on disk
- [ ] No console errors on approve
- [ ] No placeholder/TODO comments in new code

---

## Phase E — Backend: compaction + file cleanup inside approveAndPublish

**Estimated time:** 15 minutes

**Files:**
- Modify: `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php`

**Steps:**
1. Locate `approveAndPublish` (line 764). **Before** the `$featuredImage = …` line (around 780):
   ```php
   // Compact variations to selected-only, collect non-selected URLs for deletion
   $imagePrompts = $article['image_prompts'] ?? [];
   $urlsToDelete = [];
   foreach ($imagePrompts as $i => $prompt) {
       $variations = $prompt['variations'] ?? [];
       if (empty($variations)) continue;

       $selectedIdx = $prompt['selected_variation'] ?? 0;
       $selectedVar = $variations[$selectedIdx] ?? $variations[0];
       $selectedUrl = $selectedVar['url'] ?? ($prompt['generated_url'] ?? null);

       foreach ($variations as $vi => $v) {
           if ($vi !== $selectedIdx && !empty($v['url']) && $v['url'] !== $selectedUrl) {
               $urlsToDelete[] = $v['url'];
           }
       }

       $imagePrompts[$i]['variations'] = [$selectedVar];
       $imagePrompts[$i]['selected_variation'] = 0;
       $imagePrompts[$i]['generated_url'] = $selectedUrl;
   }
   $article['image_prompts'] = $imagePrompts;
   $idea->generated_article = $article;
   $idea->save();
   ```
2. Ensure `$featuredImage` still resolves correctly. Current code reads `$idea->generated_images`, which is a separate top-level field — **not** `image_prompts`. Confirm with `php artisan tinker`:
   ```
   >>> App\Models\ContentIdea::find(88)->generated_images
   ```
   If `generated_images` is empty/null on newer ideas, fall back to the first selected variation URL:
   ```php
   $featuredImage = data_get($idea->generated_images, '0.url')
       ?? data_get($idea->generated_images, '0')
       ?? data_get($imagePrompts, '0.generated_url')
       ?? null;
   ```
   Only add this fallback if the tinker check shows `generated_images` is unreliable for the current pipeline.
3. **After** `PostTranslation::updateOrCreate` completes (around line 842, before the translate-phase `if` block), add the file-deletion block:
   ```php
   // Clean up non-selected variation files from storage
   $storageBase = url('/storage/');
   foreach ($urlsToDelete as $imageUrl) {
       if (!str_starts_with($imageUrl, $storageBase)) continue;
       $relativePath = str_replace($storageBase . '/', '', $imageUrl);
       if (\Illuminate\Support\Facades\Storage::disk('public')->exists($relativePath)) {
           \Illuminate\Support\Facades\Storage::disk('public')->delete($relativePath);
       }
   }
   ```
4. Add `use Illuminate\Support\Facades\Storage;` at top of file if not already imported. Check with Grep before adding.
5. Run `php artisan route:clear` (defensive; shouldn't be needed for controller changes, but harmless).
6. Commit: `fix(content-engine): move variation cleanup into approveAndPublish`

**Verification:**
- [ ] End-to-end manual test with a real idea:
  1. Reach Images step with 3 variants for Cover + Body-2 each (6 total non-selected candidates + 2 selected)
  2. Select preferred variant per segment
  3. Click Approve & Continue → Finalize shows images correctly
  4. Click "Publish to Blog"
  5. Check `posts` table: new row with `source_idea_id = {id}`, `published = true`
  6. Check `post_translations` table: primary-language row with correct title/content
  7. Check filesystem: `ls storage/app/public/content-engine/` — only 2 files remain (the selected cover + body-2), 4 others deleted
  8. Check DB: `ContentIdea::find({id})->generated_article['image_prompts']` — each prompt's `variations` is a 1-element array
- [ ] No 500 errors in `storage/logs/laravel.log` during publish
- [ ] Existing code path still works for ideas without `variations` (legacy flat `generated_url` only) — skip compaction gracefully via `empty($variations)` guard
- [ ] No placeholder/TODO comments in new code

---

## Phase F — Regression sweep + cleanup

**Estimated time:** 8 minutes

**Files:** none modified; pure verification

**Steps:**
1. Run through all four bug scenarios end-to-end from a fresh browser session on a single idea:
   - (1) Article → Finalize: images at correct positions in both views
   - (2) Navigate back from Finalize to Step 1, click Approve & Continue: routes forward without 422
   - (3) English tab on ID article: banner; Indonesia tab default
   - (4) Generate 3 variants → approve → back to images: all 3 still present; then publish → only selected remains on disk
2. Check `storage/logs/laravel.log` for any new errors introduced.
3. Check browser devtools console on each of the 3 admin views for errors/warnings.
4. Update [CLAUDE.md](../../CLAUDE.md) if any architectural note changed (new helper file deserves a mention under the Frontend Architecture section, e.g., `imagePositioning.js — shared image positioning resolver for Content Engine pipeline`).
5. Final commit if CLAUDE.md updated: `docs: note imagePositioning helper in CLAUDE.md`

**Verification:**
- [ ] All four bug scenarios pass manual test
- [ ] Zero new errors in `laravel.log` and browser console
- [ ] `git log --oneline -6` shows 5 focused commits (Phases A-E) plus optional docs commit
- [ ] `git status` clean

---

## Red-flag self-check

- [x] Data Integration Map present (10 rows)
- [x] Every phase has a Verification block
- [x] CLAUDE.md referenced; no reinvention (reuses existing `useContentEngine`, `approveAndPublish`, `PipelineStepBar`, Storage facade)
- [x] Data sources specified precisely (no "connect to backend" language)
- [x] No phase estimated > 15 min
- [x] No placeholder/TODO language in plan
- [x] Test steps included (smoke test Phase A; manual E2E per phase — matches project's actual workflow since no unit test runner exists)

## Execution Handoff

**Option 1: Execute in this session** — Ready to start Phase A? I'll use `gaspol-execute` to run phases sequentially with per-phase checkpoints.

**Option 2: Parallel execution** — Phases C and D are independent of each other (different files, no shared state) and could run in parallel via `gaspol-parallel`. Phase B depends on Phase A (shared helper). Phase E is backend-only. Phase F must be last.

**Option 3: Separate session** — Plan file is self-contained at `docs/plans/2026-04-17-content-engine-preview-4bugs-plan.md`. Pair with design doc at `docs/plans/2026-04-17-content-engine-preview-4bugs-design.md`.
