# Content Engine Preview/Finalize — 4-Bug Fix Design

**Date:** 2026-04-17
**Status:** Design approved, ready for planning
**Scope:** Admin Content Engine pipeline — Article Preview, Image Generation, Finalize steps

## Problem Statement

Four bugs reported in the Content Engine multi-step preview flow:

1. **Image positions collapse at Finalize** — Article step shows images distributed across body; Finalize step piles all images at the top.
2. **"Article must be ready before approval"** — revisiting Step 1 (Article Preview) after pipeline has advanced triggers 422 error from backend.
3. **Wrong default language tab + no untranslated notice** — Finalize defaults to English, but English translation doesn't exist yet at this stage. Should default to Indonesian, and English tab should show a "not yet translated" notice.
4. **Image variants deleted when navigating back from Finalize** — user generates 3 variants for Cover / Body-2, clicks Approve & Continue, then navigates back from Finalize → only 1 variant remains (files already deleted). Expected: variants preserved until final Publish, then cleaned up.

## Root Causes

### Bug 1 — Finalize ignores plugin positioning metadata
- Plugin provides `image_prompts[].insert_after_heading` (primary) and sometimes `suggested_position`.
- `ArticlePreview.vue::resolveImagePosition` (lines 74-101) resolves final placement using: cover → 0, `suggested_position` if numeric, heading-text match against parsed blocks, even-distribution fallback.
- `ArticleFinalize.vue::contentWithImages` (lines 68-78) only reads raw `img.suggested_position` — which is usually 0 or missing, so `blocks.splice(0, 0, …)` piles every image at the top.
- The two views disagree because they don't share the same positioning logic.

**Location:** `frontend/src/views/admin/ArticleFinalize.vue:68-78`

### Bug 2 — Status-gated endpoint + unconditional frontend call
- `ContentIdeaController::approveArticle` hard-requires `status === 'article_ready'` (line 467) → returns 422 otherwise.
- `PipelineStepBar.vue:38` makes completed steps clickable, so user can navigate back to Step 1 from Images / Finalize.
- `ArticlePreview.vue::handleApprove` calls the endpoint unconditionally.

**Location:** `frontend/src/views/admin/ArticlePreview.vue:317-357`

### Bug 3 — Hardcoded default + flat-format fallback includes 'en' spuriously
- `ArticleFinalize.vue:15` hardcodes `activeLang = ref('en')`.
- `availableLanguages` computed includes `'en'` when either `article.en` OR `article.title` exists (legacy flat fallback). So an Indonesian article with a flat `title` field still shows an English tab rendering empty/wrong content.

**Location:** `frontend/src/views/admin/ArticleFinalize.vue:15, 30-36` + `ArticlePreview.vue:126-133`

### Bug 4 — Cleanup runs at every forward navigation instead of at Publish
- `ImageGeneration.vue::handleApprove` (lines 441-475):
  - Collects non-selected variation URLs
  - Compacts `variations[]` to only the selected one (line 463)
  - Resets `selected_variation = 0` (line 464)
  - Saves compacted state via `saveDraft`
  - Deletes files from storage via `cleanupVariationImages` (line 471)
- This runs every time user clicks "Approve & Continue" to move to Finalize, not at the final "Publish to Blog" step.
- Intent per commit `0ad41d4f` was to clean up at finalize, but placement was wrong — it's at Step 2→3 transition, not at Step 3 Publish.

**Location:** `frontend/src/views/admin/ImageGeneration.vue:441-475`

## Fix Design

### Fix 1 — Share positioning logic between Article and Finalize

**Source of truth:** plugin's `suggested_position` + `insert_after_heading` on each image prompt.

1. Extract `parseBlockElements()` and `resolveImagePosition()` from `ArticlePreview.vue` into a shared helper:
   - **New file:** `frontend/src/utils/imagePositioning.js`
   - Pure functions, no Vue dependency.
2. Import the helper in both `ArticlePreview.vue` and `ArticleFinalize.vue`.
3. `ArticleFinalize.vue::contentWithImages`: run `parseBlockElements(html)` → then for each non-cover image with `generated_url`, compute `const pos = resolveImagePosition(img, index, total, blocks)`. Splice at those positions in descending order.

Finalize no longer reads `img.suggested_position` directly; it runs the same resolver Article step uses, so placements match exactly.

### Fix 2 — Skip approval when past `article_ready`

**File:** `frontend/src/views/admin/ArticlePreview.vue`

In `handleApprove()`:
- If `idea.value.status !== 'article_ready'`, skip the `approveArticle` API call entirely.
- Route by status:
  - `generating_images` | `images_ready` → `/admin/content-engine/{id}/images`
  - `completed` → `/admin/content-engine/{id}/finalize`
  - default (shouldn't reach) → fall through to existing API path.

### Fix 3 — Default to Indonesian + untranslated banner

**Files:** `ArticleFinalize.vue` + `ArticlePreview.vue`

1. `ArticleFinalize.vue:15`: `const activeLang = ref('id')` (or pick first available language, preferring `'id'`).
2. Both views: add computed
   ```js
   const isUntranslated = computed(() =>
     activeLang.value === 'en' && !article.value?.en?.content
   )
   ```
3. In template, when `isUntranslated`:
   - Render info banner: "Belum diterjemahkan. Terjemahan otomatis akan berjalan saat Publish."
   - Hide cover image, title, article body, image placeholders.
   - Keep language tabs visible so user can switch back to Indonesian.

### Fix 4 — Move variation cleanup to backend Publish

**Frontend — `ImageGeneration.vue:handleApprove`:**
```js
async function handleApprove() {
  await persistDraft()
  router.push(`/admin/content-engine/${idea.value.id}/finalize`)
}
```
Remove `cleanupVariationImages` from the `useContentEngine()` destructure (no longer used here).

**Backend — `ContentIdeaController::approveAndPublish` (before Post upsert):**

Add compaction + cleanup step:
```php
// Compact variations to selected-only, collect URLs to delete
$imagePrompts = $article['image_prompts'] ?? [];
$urlsToDelete = [];
foreach ($imagePrompts as $i => &$prompt) {
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

    $prompt['variations'] = [$selectedVar];
    $prompt['selected_variation'] = 0;
    $prompt['generated_url'] = $selectedUrl;
}
unset($prompt);
$article['image_prompts'] = $imagePrompts;
$idea->generated_article = $article;
$idea->save();

// ... existing Post / PostTranslation upsert logic ...

// After Post created successfully, delete files
$storageBase = url('/storage/');
foreach ($urlsToDelete as $imageUrl) {
    if (!str_starts_with($imageUrl, $storageBase)) continue;
    $relativePath = str_replace($storageBase . '/', '', $imageUrl);
    if (Storage::disk('public')->exists($relativePath)) {
        Storage::disk('public')->delete($relativePath);
    }
}
```

The `/cleanup-variation-images` route in `routes/api.php` stays (unused but harmless; can be removed later).

## Data Integration Map

| Concern | Data Source | Existing? | Notes |
|---------|-------------|-----------|-------|
| Image position | `image_prompts[].insert_after_heading` + `.suggested_position` | Yes | Plugin is source of truth; shared resolver in both views |
| Idea status | `idea.status` | Yes | Gate approve-skip logic |
| Translation presence | `generated_article.en?.content` | Yes | Boolean check |
| Variation array | `image_prompts[].variations[]` | Yes | Preserve until Publish |
| Selected variation index | `image_prompts[].selected_variation` | Yes | Preserve until Publish |
| File deletion | Storage `public` disk, URL→path strip | Yes | Move logic into `approveAndPublish` |

## Files Touched

| Layer | File | Change |
|-------|------|--------|
| Frontend | `frontend/src/utils/imagePositioning.js` | **NEW** — shared `parseBlockElements` + `resolveImagePosition` |
| Frontend | `frontend/src/views/admin/ArticlePreview.vue` | Import helpers; `handleApprove` status-gated routing; untranslated banner |
| Frontend | `frontend/src/views/admin/ArticleFinalize.vue` | Default lang = id; use shared resolver for image placement; untranslated banner |
| Frontend | `frontend/src/views/admin/ImageGeneration.vue` | Remove cleanup from `handleApprove` |
| Backend | `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php` | Add variation compaction + file cleanup in `approveAndPublish` |

~60-80 lines total. No new migrations, no new endpoints, no new dependencies.

## YAGNI Cuts

- Keep `/cleanup-variation-images` route (frontend no longer calls it; removing is a separate cleanup).
- No i18n for banner copy — hardcoded Indonesian string (matches admin panel locale).
- No new composable for banner — inline in both views (two usages, not three).

## Verification Checklist

- [ ] Bug 1: Generate article, reorder images in Article step, navigate to Finalize — images appear at user-picked positions.
- [ ] Bug 2: Navigate pipeline to `completed`, go back to Step 1, click Approve & Continue — routes to Finalize without API error.
- [ ] Bug 3a: Load Finalize step on Indonesian article — Indonesia tab active by default.
- [ ] Bug 3b: Click English tab — banner shown, no empty body rendered.
- [ ] Bug 4a: Generate 3 variants for Cover, select variant 2, click Approve & Continue, navigate back from Finalize — all 3 variants still present.
- [ ] Bug 4b: Click "Publish to Blog" at Finalize — variations compacted to 1, non-selected files deleted from `storage/app/public/...`, Post created.
- [ ] No regressions: existing successful publish flow still produces correct Post + featured_image.
