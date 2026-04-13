> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

## Goal

Build Steps 2 and 3 of the Content Engine pipeline: a Sparkfluence-style image generation page with per-segment prompt editing, reference image search (Pexels/Unsplash), and a WYSIWYG finalize page showing the complete article with generated images. This connects to the already-built Article Preview (Step 1) to form the full `article_ready → generating_images → images_ready → completed` flow.

## Architecture Context

**From CLAUDE.md:**
- Admin routes use `requiresAuth: true, layout: 'admin'` — pattern at `router/index.js`
- Existing routes: `/:id/preview` (Step 1, already built)
- `useContentEngine.js` composable has `getIdea()`, `approveArticle()`, `startImageGeneration()`, `approveAndPublish()`
- `ImageGenerationService.php` calls GeminiGen API with multipart form — `queue(postId, prompt, type, insertAfterHeading, model, aspectRatio, style)`
- `ContentIdeaController::startImageGeneration()` at `routes/api.php:444` — currently bulk-sends to workflow engine
- `generated_article` JSON column stores all article + image data (no migration needed)
- `generated_images` JSON column also exists on `content_ideas`
- Admin panel uses neutral `bg-white dark:bg-neutral-800` palette
- `useToast.js` for notifications
- `@tailwindcss/typography` installed (prose classes work)

**From Sparkfluence (`D:\Projects\sparkfluence_platform`):**
- Stock image search uses Pexels API primary + Unsplash API fallback
- Pexels: `https://api.pexels.com/v1/search`, header `Authorization: {key}`
- Unsplash: `https://api.unsplash.com/search/photos`, header `Authorization: Client-ID {key}`
- Results normalized to `{ id, provider, url_thumb, url_regular, url_full, width, height, photographer, alt }`
- LocalStorage cache with 7-day TTL for search results
- Reference image URL passed to image gen API as `reference_image_url` parameter

## Tech Stack

- **Frontend:** Vue 3.5 (`<script setup>`), Tailwind CSS 4, Vue Router 4.5
- **Backend:** Laravel 12, PHP 8.2 (`D:\xampp\php\php.exe`)
- **Image Gen API:** GeminiGen (`ImageGenerationService.php` — already exists)
- **Stock Photo APIs:** Pexels (primary, 200 req/hour), Unsplash (fallback, 50 req/hour)
- **Existing composables:** `useContentEngine.js`, `useToast.js`

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---------|-----------|----------|---------|--------|
| Idea data | `ContentIdea` model | `getIdea(id)` | **Yes** | Use directly |
| Image prompts | `generated_article.image_prompts[]` | From idea data | **Yes** | Read + write back |
| Save draft | `ContentIdeaController` | `PUT /ideas/{id}/save-draft` | **No** | Add endpoint |
| Stock photo search | Pexels + Unsplash APIs | `GET /admin/stock-images/search` | **No** | Create controller + proxy |
| Generate single image | `ImageGenerationService::queue()` | New endpoint `POST /ideas/{id}/generate-segment-image` | **No** | Create endpoint using existing service |
| Generated image URL | `ImageGenerationService` | GeminiGen webhook or instant | **Yes** | Use existing |
| Approve & publish | `ContentIdeaController::approveAndPublish()` | `POST /ideas/{id}/publish` | **Yes** | Use directly |
| Toast notifications | `useToast()` | Composable | **Yes** | Use directly |
| Step bar navigation | Route params + idea status | Vue Router + computed | **No** | Create shared component |

---

## Phase 1: Backend — Stock Image Search Proxy

**Estimated time:** 10 minutes

**Files:**
- Create: `backend/app/Http/Controllers/Api/Admin/StockImageController.php`
- Modify: `backend/routes/api.php` (add route)
- Modify: `backend/.env` (add API keys)

**Steps:**

1. Create `StockImageController` with a `search()` method:
   - Accept `q`, `orientation` (landscape/portrait/square, default landscape), `per_page` (default 20), `page` (default 1)
   - Try Pexels first: `GET https://api.pexels.com/v1/search` with `Authorization: {PEXELS_API_KEY}` header
   - Map Pexels response to normalized format: `{ id, provider: 'pexels', url_thumb: photo.src.small, url_regular: photo.src.medium, url_full: photo.src.original, width, height, photographer, alt }`
   - If Pexels fails or returns 0 results, fallback to Unsplash: `GET https://api.unsplash.com/search/photos` with `Authorization: Client-ID {UNSPLASH_ACCESS_KEY}`
   - Map Unsplash response: `{ id, provider: 'unsplash', url_thumb: photo.urls.thumb, url_regular: photo.urls.regular, url_full: photo.urls.full, width, height, photographer: photo.user.name, alt: photo.alt_description }`
   - Return unified response: `{ success: true, data: { results: [...], total: N, query: '...' } }`

2. Add route in `routes/api.php` inside the admin content-engine group:
   ```php
   Route::get('/stock-images/search', [StockImageController::class, 'search']);
   ```

3. Add to `.env`:
   ```
   PEXELS_API_KEY=
   UNSPLASH_ACCESS_KEY=
   ```
   Add to `config/services.php`: `'pexels' => ['api_key' => env('PEXELS_API_KEY')]` and `'unsplash' => ['access_key' => env('UNSPLASH_ACCESS_KEY')]`

**Verification:**
- [ ] `GET /api/admin/stock-images/search?q=office` returns normalized results from Pexels
- [ ] If Pexels key is empty/invalid, falls back to Unsplash
- [ ] Response matches the normalized format from design doc
- [ ] No placeholder data

---

## Phase 2: Backend — Save Draft + Per-Segment Image Generation Endpoints

**Estimated time:** 10 minutes

**Files:**
- Modify: `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php` (add `saveDraft()`, `generateSegmentImage()`)
- Modify: `backend/routes/api.php` (add 2 routes)

**Steps:**

1. Add `saveDraft()` method to `ContentIdeaController`:
   ```php
   public function saveDraft($id, Request $request): JsonResponse
   {
       $idea = ContentIdea::find($id);
       if (!$idea) return response()->json(['success' => false, 'message' => 'Not found.'], 404);
       if ($request->has('generated_article')) {
           $idea->generated_article = $request->input('generated_article');
       }
       $idea->save();
       return response()->json(['success' => true, 'data' => $idea->fresh()]);
   }
   ```

2. Add `generateSegmentImage()` method that generates a single image via GeminiGen:
   - Accept: `segment_index`, `prompt`, `style`, `model`, `aspect_ratio`, `reference_image_url` (optional)
   - Call `ImageGenerationService::queue()` with the prompt
   - Update `generated_article.image_prompts[segment_index].status = 'generating'`
   - Return the job UUID for polling
   - Also accept `resolution` parameter, default `1K`

3. Add routes:
   ```php
   Route::put('/ideas/{id}/save-draft', [ContentIdeaController::class, 'saveDraft']);
   Route::post('/ideas/{id}/generate-segment-image', [ContentIdeaController::class, 'generateSegmentImage']);
   ```

**Verification:**
- [ ] `PUT /ideas/{id}/save-draft` saves modified `generated_article` without changing status
- [ ] `POST /ideas/{id}/generate-segment-image` calls GeminiGen and returns UUID
- [ ] Segment status updated in `generated_article.image_prompts[]`
- [ ] No placeholder data

---

## Phase 3: Frontend — Shared PipelineStepBar Component

**Estimated time:** 5 minutes

**Files:**
- Create: `frontend/src/components/admin/PipelineStepBar.vue`

**Steps:**

1. Create `PipelineStepBar.vue` with props: `currentStep` (1/2/3), `ideaId`, `ideaStatus`
2. Render 3 steps with connecting lines:
   - Step 1 "Article": completed (green check) when status is past `article_ready`, clickable → `/:id/preview`
   - Step 2 "Images": active (amber dot) when on images page, completed when `images_ready`/`completed`, clickable → `/:id/images`
   - Step 3 "Finalize": active when on finalize page, completed when `completed`, clickable → `/:id/finalize`
3. Pending steps are visually dimmed and not clickable
4. Style: horizontal flex with amber active accent, green completed, neutral pending

**Verification:**
- [ ] Component renders 3 steps correctly based on `currentStep` and `ideaStatus`
- [ ] Completed steps are clickable links
- [ ] Pending steps are disabled
- [ ] Build passes

---

## Phase 4: Frontend — StockImageSearch Component

**Estimated time:** 10 minutes

**Files:**
- Create: `frontend/src/components/admin/StockImageSearch.vue`
- Modify: `frontend/src/composables/useContentEngine.js` (add `searchStockImages()`)

**Steps:**

1. Add `searchStockImages()` to composable:
   ```js
   const searchStockImages = (query, options = {}) => {
     const params = { q: query, ...options }
     return request('get', '/admin/stock-images/search', null, params)
   }
   ```
   Add to return object.

2. Create `StockImageSearch.vue` component with:
   - Props: `modelValue` (selected reference image URL), `orientation` (default 'landscape')
   - Emits: `update:modelValue`
   - **Search bar**: input + Search button. On enter/click → calls `searchStockImages(query)`
   - **Results grid**: 4-column grid of thumbnails. Click → emits URL as selected reference
   - **Upload button**: `<input type="file" accept="image/*">` → convert to object URL or upload to server
   - **Paste URL**: text input + button → validates URL looks like an image
   - **Selected reference**: shows thumbnail with X to remove (emits null)
   - **LocalStorage cache**: cache search results with key `stock_search_{query}_{orientation}`, TTL 7 days
   - **Loading state**: spinner during search
   - **Empty state**: "No images found" message

**Verification:**
- [ ] Component searches Pexels/Unsplash via backend proxy
- [ ] 4-column grid displays thumbnails
- [ ] Clicking a thumbnail emits the URL
- [ ] Upload and paste URL work
- [ ] Selected reference shows with remove button
- [ ] LocalStorage caching works (repeat search hits cache)
- [ ] Build passes

---

## Phase 5: Frontend — Composable Methods for Image Pipeline

**Estimated time:** 5 minutes

**Files:**
- Modify: `frontend/src/composables/useContentEngine.js`

**Steps:**

1. Add these methods:
   ```js
   const saveDraft = (id, data) => request('put', `/admin/content-engine/ideas/${id}/save-draft`, data)

   const generateSegmentImage = async (id, segmentData) => {
     return request('post', `/admin/content-engine/ideas/${id}/generate-segment-image`, segmentData)
   }
   ```

2. Add both to the return object.

**Verification:**
- [ ] `saveDraft(id, { generated_article })` saves without status change
- [ ] `generateSegmentImage(id, { segment_index, prompt, style, model, aspect_ratio })` returns UUID
- [ ] Build passes

---

## Phase 6: Frontend — ImageGeneration.vue (Step 2)

**Estimated time:** 20 minutes

**Files:**
- Create: `frontend/src/views/admin/ImageGeneration.vue`
- Modify: `frontend/src/router/index.js` (add route)

**Steps:**

1. Add route BEFORE the catch-all in `router/index.js`:
   ```js
   {
     path: '/admin/content-engine/:id/images',
     name: 'admin-content-engine-images',
     component: () => import('@/views/admin/ImageGeneration.vue'),
     meta: { title: 'Image Generation - Admin', requiresAuth: true, layout: 'admin' }
   }
   ```

2. Create `ImageGeneration.vue` with `<script setup>`:

   **Data loading:**
   - `onMounted`: call `getIdea(route.params.id)` to load idea
   - Initialize `segments` ref from `generated_article.image_prompts[]`
   - Each segment has: `subject`, `environment`, `composition`, `style`, `model`, `aspect_ratio`, `reference_image_url`, `generated_url`, `status` (pending/generating/done/failed)

   **Prompt decomposition** (on load, split single `prompt` into 3 fields):
   ```js
   function decomposePrompt(prompt) {
     // Heuristic: split on comma-separated clauses
     const parts = prompt.split(',').map(p => p.trim())
     if (parts.length >= 3) {
       return {
         subject: parts.slice(0, Math.ceil(parts.length / 3)).join(', '),
         environment: parts.slice(Math.ceil(parts.length / 3), Math.ceil(parts.length * 2 / 3)).join(', '),
         composition: parts.slice(Math.ceil(parts.length * 2 / 3)).join(', '),
       }
     }
     return { subject: prompt, environment: '', composition: '' }
   }
   ```

   **Template layout:**
   - `<PipelineStepBar :current-step="2" :idea-id="id" :idea-status="idea.status" />`
   - Title bar with back link + "Generate All" button
   - For each segment in `segments`: render a card with:
     - Left (~55%): 3 textareas (Subject, Environment, Composition) + 3 dropdowns (Style, Model, Ratio) + `<StockImageSearch v-model="segment.reference_image_url" />`
     - Right (~45%): image preview area (placeholder when pending, spinner when generating, `<img>` when done, error with retry when failed) + Regenerate button
     - Bottom strip: status badge (COVER/BODY-N label + status icon)
   - Bottom bar: "N/N images generated" counter + "Approve Images & Continue" button (disabled until all done)

   **Generate All** button:
   - Loops through all segments with status !== 'done'
   - Calls `generateSegmentImage()` for each
   - Updates segment.status to 'generating'
   - Polls `getIdea()` every 5 seconds to check for completion (or use webhook if available)

   **Per-segment Regenerate:**
   - Recomposes prompt from 3 fields: `${subject}, ${environment}, ${composition}`
   - Calls `generateSegmentImage()` for that segment
   - Auto-save draft after each change

   **Approve & Continue:**
   - Save final draft with all segment data
   - Navigate to `/:id/finalize`

   **Auto-save draft:**
   - Debounced save (2 seconds) on any field change
   - Calls `saveDraft(id, { generated_article: updatedArticle })`

**Verification:**
- [ ] Page loads at `/admin/content-engine/{id}/images`
- [ ] PipelineStepBar shows Step 2 active
- [ ] Segments render from `image_prompts[]` with decomposed prompt fields
- [ ] Dropdowns for Style/Model/Ratio work
- [ ] StockImageSearch shows per segment with search + upload + URL
- [ ] Generate All triggers generation for all segments
- [ ] Per-segment Regenerate works
- [ ] Image preview shows generated image when done
- [ ] Auto-save draft on field changes
- [ ] "Approve & Continue" navigates to finalize
- [ ] Build passes

---

## Phase 7: Frontend — ArticleFinalize.vue (Step 3)

**Estimated time:** 12 minutes

**Files:**
- Create: `frontend/src/views/admin/ArticleFinalize.vue`
- Modify: `frontend/src/router/index.js` (add route)

**Steps:**

1. Add route BEFORE the catch-all:
   ```js
   {
     path: '/admin/content-engine/:id/finalize',
     name: 'admin-content-engine-finalize',
     component: () => import('@/views/admin/ArticleFinalize.vue'),
     meta: { title: 'Finalize Article - Admin', requiresAuth: true, layout: 'admin' }
   }
   ```

2. Create `ArticleFinalize.vue` with `<script setup>`:

   **Data loading:**
   - Load idea via `getIdea(route.params.id)`
   - Get article content and image prompts with generated URLs
   - Language tabs (EN/ID) from `availableLanguages` (reuse pattern from ArticlePreview.vue)

   **WYSIWYG rendering:**
   - Parse article HTML content into blocks (reuse `contentBlocks` pattern from ArticlePreview.vue)
   - At each `suggested_position`, insert the actual generated image (not placeholder)
   - Cover image (position 0) rendered full-width at top
   - Inline images rendered at their positions with proper aspect ratio
   - All images use `<img :src="segment.generated_url">` — real generated URLs
   - Content styled with `prose dark:prose-invert prose-lg max-w-3xl mx-auto`

   **Template layout:**
   - `<PipelineStepBar :current-step="3" :idea-id="id" :idea-status="idea.status" />`
   - Language tabs (EN/ID)
   - Cover image (full width, aspect-video)
   - Article title (read-only, styled)
   - Article body with generated images inserted at positions
   - Bottom bar: "Back to Images" link + "Publish to Blog" button

   **Publish to Blog:**
   - Calls `approveAndPublish(idea.id)` — existing endpoint that sets status to `completed`
   - On success: toast + redirect to Content Engine list
   - "Back to Images" navigates to `/:id/images`

**Verification:**
- [ ] Page loads at `/admin/content-engine/{id}/finalize`
- [ ] PipelineStepBar shows Step 3 active
- [ ] Cover image renders at top (full width, real generated URL)
- [ ] Inline images render at correct positions in the article
- [ ] Language tabs switch between EN/ID
- [ ] "Publish to Blog" calls API and redirects to Content Engine
- [ ] "Back to Images" navigates correctly
- [ ] Build passes

---

## Phase 8: Frontend — Update ArticlePreview + ContentEngine Navigation

**Estimated time:** 8 minutes

**Files:**
- Modify: `frontend/src/views/admin/ArticlePreview.vue`
- Modify: `frontend/src/views/admin/ContentEngine.vue`

**Steps:**

1. **ArticlePreview.vue:**
   - Add `<PipelineStepBar :current-step="1" :idea-id="route.params.id" :idea-status="idea?.status" />` at top of main content (below sticky top bar)
   - Update `handleApprove()`: after successful approve, navigate to `/:id/images` instead of ContentEngine with `?imageConfig`:
     ```js
     window.location.href = `/admin/content-engine/${idea.value.id}/images`
     ```
   - Remove the `window.opener` logic since we now navigate directly to Step 2

2. **ContentEngine.vue:**
   - In `openResearchModal()`: add handling for `images_ready` status → open `/:id/finalize` in new tab
     ```js
     if (idea.status === 'images_ready') {
       window.open(`/admin/content-engine/${idea.id}/finalize`, '_blank')
       return
     }
     ```
   - Remove the `?imageConfig` query param handling from `onMounted()` (no longer needed since Step 1 now navigates directly to Step 2)
   - Remove old Image Config Modal (`showImageConfigModal`) — replaced by ImageGeneration.vue page

3. **Router cleanup:** Ensure all 3 pipeline routes are before the catch-all and after the content-engine base route.

**Verification:**
- [ ] ArticlePreview shows PipelineStepBar at Step 1
- [ ] Approve navigates to `/:id/images` (Step 2)
- [ ] ContentEngine click on `article_ready` → opens `/:id/preview` (Step 1)
- [ ] ContentEngine click on `images_ready` → opens `/:id/finalize` (Step 3)
- [ ] Old Image Config Modal removed
- [ ] Build passes

---

## Phase Summary

| Phase | Description | Est. Time | Dependencies |
|-------|-------------|-----------|--------------|
| 1 | Backend: Stock image search proxy (Pexels/Unsplash) | 10 min | None |
| 2 | Backend: Save draft + per-segment image gen endpoints | 10 min | None |
| 3 | Frontend: PipelineStepBar shared component | 5 min | None |
| 4 | Frontend: StockImageSearch component | 10 min | Phase 1 |
| 5 | Frontend: Composable methods (saveDraft, generateSegmentImage) | 5 min | Phase 2 |
| 6 | Frontend: ImageGeneration.vue (Step 2 full page) | 20 min | Phase 3, 4, 5 |
| 7 | Frontend: ArticleFinalize.vue (Step 3 WYSIWYG) | 12 min | Phase 3 |
| 8 | Frontend: Update ArticlePreview + ContentEngine navigation | 8 min | Phase 6, 7 |
| **Total** | | **~80 min** | |

**Parallel-eligible:** Phase 1 + 2 + 3 can run in parallel. Phase 4 depends on 1. Phase 5 depends on 2. Phase 6 depends on 3+4+5. Phase 7 depends on 3. Phase 8 depends on 6+7.

---

## Design Reference

Full design spec: `docs/plans/2026-04-13-image-gen-pipeline-design.md`
