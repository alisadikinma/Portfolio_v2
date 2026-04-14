> **For Claude:** REQUIRED SKILL: Use gaspol-execute to implement this plan.
> **CRITICAL:** This plan specifies real integrations. During execution,
> NEVER substitute placeholders for real data sources without explicit
> user approval. If a data source doesn't exist yet, STOP and ask.

## Goal

Add a configuration modal to the Content Engine ImageGeneration page that lets users configure each image segment with categorized reference images (Face vs. Style/Environment), additional notes, and generation options. Port the modal UX pattern from Sparkfluence's `GenerateBRollModal.tsx`. Also update the default image model from `nano-banana-pro` to `nano-banana-2` across frontend, backend, and the article-content-writer plugin.

## Architecture Context

**From CLAUDE.md:**
- Frontend: Vue 3.5 + Tailwind 4 + `<script setup>` only
- ImageGeneration page: `frontend/src/views/admin/ImageGeneration.vue`
- Existing stock search component: `frontend/src/components/admin/StockImageSearch.vue` (Unsplash/Pexels, 7-day localStorage cache)
- Stock search API: `GET /api/admin/content-engine/stock-images/search` (already exists)
- Image gen service: `backend/app/Services/ImageGenerationService.php` — `queue()` method sends to GeminiGen API
- Image gen controller: `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php` — `generateSegmentImage()`
- GeminiGen API: multipart POST with `prompt`, `model`, `aspect_ratio`, `style`, `file_urls` (reference images)
- Plugin: `D:\Projects\claude-plugin\article-content-writer/` — 4 files need default model update

**From Sparkfluence (port source):**
- `src/screens/ImageGeneration/components/GenerateBRollModal.tsx` — main modal structure, two-column layout, reference image picker
- `src/screens/ImageGeneration/components/ReferenceImageModal.tsx` — AI keyword extraction, stock search
- Key pattern: `BRollOptions { additionalNotes, includeCreatorFace, referenceImages[], layout }`

## Tech Stack

- Vue 3.5 `<script setup>` (port from React TSX)
- Tailwind CSS 4 (existing design system: dark mode, amber accents, neutral backgrounds)
- Existing `StockImageSearch.vue` component — reuse for stock image search
- Existing `useContentEngine.js` composable — `generateSegmentImage()`, `saveDraft()`
- GeminiGen API `file_urls` parameter for reference images

## Data Integration Map

| Feature | Data Source | Hook/API | Exists? | Action |
|---------|-----------|----------|---------|--------|
| Segment data | ContentIdea.generated_article.image_prompts | useContentEngine().getIdea() | Yes | Use existing |
| Stock image search | StockImageController → Unsplash/Pexels | GET /admin/content-engine/stock-images/search | Yes | Reuse via StockImageSearch.vue |
| Image generation trigger | ContentIdeaController.generateSegmentImage | useContentEngine().generateSegmentImage() | Yes | Extend payload with face_refs/style_refs |
| GeminiGen API call | ImageGenerationService.queue() | Internal service | Yes | Add referenceImageUrls + prompt enhancement |
| File upload for references | Laravel storage | POST /admin/content-engine/upload-reference | No | Create new endpoint |
| Reference image URLs (face) | generated_article.image_prompts[].face_refs | JSON column | No | Add to segment schema |
| Reference image URLs (style) | generated_article.image_prompts[].style_refs | JSON column | No | Add to segment schema |
| Additional notes per segment | generated_article.image_prompts[].additional_notes | JSON column | No | Add to segment schema |

## Phases

---

### Phase 1: Plugin Default Model Update

**Estimated time:** 5 minutes

**Files:**
- Modify: `D:\Projects\claude-plugin\article-content-writer\references\image-prompt-guide.md`
- Modify: `D:\Projects\claude-plugin\article-content-writer\skills\article-write\SKILL.md`
- Modify: `D:\Projects\claude-plugin\article-content-writer\skills\article-gen\SKILL.md`
- Modify: `D:\Projects\claude-plugin\article-content-writer\agents\article-writer.md`

**Steps:**
1. In `image-prompt-guide.md`: change default model from `nano-banana-pro` to `nano-banana-2` in Model Selection Guide (default section), Model Decision Table, and all example/template references
2. In `article-write/SKILL.md`: change `"model": "nano-banana-pro"` to `"model": "nano-banana-2"` in the JSON payload example
3. In `article-gen/SKILL.md`: change `"model": "{model}"` default comment and any nano-banana-pro references
4. In `article-writer.md`: change default model references to `nano-banana-2`
5. Commit: `feat: change default image model from nano-banana-pro to nano-banana-2`

**Verification:**
- [ ] `grep -r "nano-banana-pro" references/ skills/ agents/` returns 0 hits as default (may still exist as alternative option)
- [ ] Default model in all JSON examples is `nano-banana-2`

---

### Phase 2: Backend — Reference Upload Endpoint + Service Enhancement

**Estimated time:** 15 minutes

**Files:**
- Modify: `backend/app/Services/ImageGenerationService.php`
- Modify: `backend/app/Http/Controllers/Api/Admin/ContentIdeaController.php`
- Modify: `backend/routes/api.php`

**Steps:**

**2A. ImageGenerationService — accept reference URLs array + prompt enhancement**
1. Add `?array $referenceImageUrls = null` parameter to `queue()` method
2. Before building multipart request, check if `$referenceImageUrls` is non-empty
3. If face refs present (detected by checking request metadata): append "Maintain exact facial identity, appearance, and features from the provided face reference image(s)." to prompt
4. If style refs present: append "Maintain visual consistency with the provided reference image(s) for environment, style, and composition." to prompt
5. Add `file_urls` to multipart payload: `['name' => 'file_urls', 'contents' => json_encode($referenceImageUrls)]`

**2B. ContentIdeaController.generateSegmentImage — accept categorized refs**
1. Add validation for `face_refs` (array of URLs) and `style_refs` (array of URLs) and `additional_notes` (string)
2. Merge face_refs + style_refs into one URL array for GeminiGen
3. Build `refCategory` metadata: `['has_face' => count(face_refs) > 0, 'has_style' => count(style_refs) > 0]`
4. Pass merged URLs + category to `ImageGenerationService.queue()`
5. If `additional_notes` provided, append to prompt before reference instructions
6. Save `face_refs`, `style_refs`, `additional_notes` to `image_prompts[$segmentIndex]`

**2C. Reference image upload endpoint**
1. Add route: `POST /admin/content-engine/upload-reference`
2. Accept file upload, store in `content-engine/references/` on public disk
3. Return full URL

**Verification:**
- [ ] `ImageGenerationService::queue()` accepts `referenceImageUrls` array
- [ ] Prompt is enhanced with correct instruction based on face vs style refs
- [ ] `file_urls` sent in GeminiGen multipart request when refs provided
- [ ] Upload endpoint returns valid public URL
- [ ] `face_refs`, `style_refs`, `additional_notes` persisted in generated_article JSON

---

### Phase 3: Frontend — ImageConfigModal Component

**Estimated time:** 25 minutes

**Files:**
- Create: `frontend/src/components/admin/ImageConfigModal.vue`
- Modify: `frontend/src/views/admin/ImageGeneration.vue`

**Steps:**

**3A. Create ImageConfigModal.vue** (port from Sparkfluence GenerateBRollModal.tsx)

Structure (two-column layout):
```
┌──────────────────────────────────────────────────┐
│  Configure [LABEL]                            ✕  │
│  "[concept text]"                                │
├────────────────────┬─────────────────────────────┤
│  LEFT COLUMN       │  RIGHT COLUMN               │
│                    │                              │
│  Additional Notes  │  Style/Environment Refs      │
│  [textarea]        │  [StockImageSearch]           │
│                    │  [selected thumbnails grid]   │
│  Face Refs         │                              │
│  [upload/URL area] │                              │
│  [thumbnail grid]  │                              │
│                    │                              │
│  Model | Style     │                              │
│  [select] [select] │                              │
├────────────────────┴─────────────────────────────┤
│                  [Cancel]  [Apply & Generate]     │
└──────────────────────────────────────────────────┘
```

Props:
```js
defineProps({
  segment: Object,        // current segment data
  visible: Boolean,       // show/hide
})
defineEmits(['apply', 'close'])
```

State:
```js
const additionalNotes = ref('')
const faceRefs = ref([])          // { url, source }[]
const styleRefs = ref([])         // { url, source }[]
const selectedModel = ref('nano-banana-2')
const selectedStyle = ref('Photorealistic')
```

Features:
1. Two-column layout with Teleport to body (dark backdrop)
2. Left column: Additional Notes textarea, Face Reference section (upload + paste URL, multiple thumbnails with remove), Model/Style selectors
3. Right column: Reuse `StockImageSearch.vue` but modified — multiple selection mode, selected thumbnails with remove buttons
4. Apply button emits `{ additionalNotes, faceRefs, styleRefs, model, style }` 
5. Cancel button emits close

**3B. Modify StockImageSearch.vue — add multi-select mode**
1. Add prop `multiple: Boolean` (default false for backward compat)
2. When `multiple=true`, emit array of URLs instead of single URL
3. Show selected count badge
4. Allow removing individual selections
5. Existing single-mode behavior unchanged

**3C. Wire modal into ImageGeneration.vue**
1. Import ImageConfigModal
2. Add `configSegment` ref (which segment is being configured, null = modal closed)
3. Add "Configure" button to each segment card (gear icon next to status badge)
4. On modal Apply: update segment's `face_refs`, `style_refs`, `additional_notes`, `model`, `style`, then trigger `generateSingle()`
5. Update `generateSingle()` to pass `face_refs` and `style_refs` to `generateSegmentImage()` API call
6. Update `persistDraft()` to save `face_refs`, `style_refs`, `additional_notes` in image_prompts

**Verification:**
- [ ] Modal opens when clicking Configure on any segment
- [ ] Face reference upload/paste works, shows thumbnails, supports multiple
- [ ] Style reference search (stock images) works, supports multiple selections
- [ ] Model defaults to `nano-banana-2`, style to `Photorealistic`
- [ ] Apply triggers generation with all reference data
- [ ] Cancel closes modal without side effects
- [ ] Auto-save persists face_refs, style_refs, additional_notes

---

### Phase 4: Integration Test & Deploy

**Estimated time:** 10 minutes

**Steps:**
1. Test full flow: open modal → add face ref (upload) → add style ref (stock search) → add notes → Apply & Generate
2. Verify GeminiGen receives `file_urls` with all reference URLs
3. Verify prompt includes correct instructions (face identity + visual consistency)
4. Run `php artisan blog:process-images` to verify completed images sync back
5. Commit all changes
6. Deploy to VPS: git pull + npm build + artisan cache:clear
7. Deploy plugin updates to VPS cache directories

**Verification:**
- [ ] End-to-end: modal → generate → poll → image appears
- [ ] Face refs produce identity-preserved images
- [ ] Style refs produce visually consistent images
- [ ] Default model is nano-banana-2 everywhere
- [ ] No regressions in existing generation flow (without modal)

---

## File Change Summary

| File | Action | Phase |
|------|--------|-------|
| `image-prompt-guide.md` | Change default model → nano-banana-2 | 1 |
| `article-write/SKILL.md` | Change default model in JSON | 1 |
| `article-gen/SKILL.md` | Change default model | 1 |
| `article-writer.md` | Change default model | 1 |
| `ImageGenerationService.php` | Add referenceImageUrls + prompt enhancement | 2 |
| `ContentIdeaController.php` | Accept face_refs/style_refs, forward to service | 2 |
| `api.php` | Add upload-reference endpoint | 2 |
| `ImageConfigModal.vue` | **NEW** — config modal component | 3 |
| `StockImageSearch.vue` | Add multi-select mode | 3 |
| `ImageGeneration.vue` | Wire modal, update generate/save flow | 3 |
