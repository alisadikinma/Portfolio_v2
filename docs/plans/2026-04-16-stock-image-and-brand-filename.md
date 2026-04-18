# Stock Image Variation + Brand Filename Mapping + Finalize Cleanup

## Goal

Two enhancements to the Image Generation page:

1. **Brand ref filename mapping** — `brand_refs` stores `{filename, url}` objects instead of flat URLs, so the article-images plugin can match uploaded images to manifest entries by filename.
2. **Stock image as variation** — users can search and select stock images (Pexels/Unsplash) directly as a variation alongside GeminiGen results. Downloaded to local storage for self-hosting.
3. **Finalize cleanup** — on Approve & Publish, delete all non-selected variation files from storage (both stock and GeminiGen).

## Locked Decisions (from brainstorm)

| # | Decision | Choice |
|---|---|---|
| 1 | Brand ref filename handling | Map URL to filename: `{filename: "logo-brand.png", url: "https://..."}` objects |
| 2 | Stock image UI placement | Inline button in preview area: "Generate Image" + "Use Stock Image" |
| 3 | Stock image storage | Download to local `/storage/blog-images/`, not external CDN URL |
| 4 | Stock image data model | Enters `variations[]` as `{url, status: 'done', source: 'stock'}` — selectable alongside GeminiGen |
| 5 | Finalize cleanup | Keep only `selected_variation` per segment. Delete all non-selected files from disk (stock + GeminiGen). If selected = stock → keep it. |

## Architecture

### Feature 1: Brand Ref Filename Mapping

**Current:** `brand_refs: ["https://storage/...abc123.png"]` (flat URL array)
**New:** `brand_refs: [{filename: "logo-brand.png", url: "https://storage/...abc123.png"}]` (object array)

**Data flow:**
- ImageConfigModal: on upload per manifest entry, creates `{filename: item.filename, url: uploadedUrl}`
- Backend `generateSegmentImage`: extracts `.url` from each object, merges into faceRefs for GeminiGen `file_urls`
- Plugin `article-images`: reads `brand_refs[].filename` to match manifest entries

### Feature 2: Stock Image as Variation

**Flow:**
```
Preview area (pending state)
  ├── [Generate Image] → existing GeminiGen flow → variation added
  └── [Use Stock Image] → inline StockImageSearch opens
        └── User clicks image → POST /download-stock-image {url, segment_index}
              → Backend downloads to /storage/blog-images/
              → Returns local URL
              → Frontend adds to variations[]: {url, status: 'done', source: 'stock'}
              → Image appears in main preview + thumbnail strip
```

**Also available in variation strip:**
- `+` button (when < 3 variations) shows same two options via a small dropdown: "Generate" or "Stock Image"

**StockImageSearch component:** Already exists at `src/components/admin/StockImageSearch.vue`, used in ImageConfigModal for style refs. Reuse inline in preview area.

### Feature 3: Finalize Cleanup

**On `handleApprove` (Approve & Continue):**
1. For each segment, identify the `selected_variation` URL → keep
2. All other variation URLs → collect for deletion
3. POST to new backend endpoint `POST /admin/content-engine/cleanup-variation-images`
4. Backend deletes files from disk using URL-to-path mapping

**Backend endpoint:**
```
POST /admin/content-engine/cleanup-variation-images
Body: { urls_to_delete: ["https://...url1", "https://...url2"] }
→ Extract storage path from URL → Storage::delete(path) → return count deleted
```

## Data Integration Map

| Feature | Data Source | Existing? | Notes |
|---|---|---|---|
| Brand ref objects `{filename, url}` | ImageConfigModal form state | Partial | Change from flat URL to object |
| Brand ref extraction in backend | `generateSegmentImage` controller | Partial | Extract `.url` from objects |
| StockImageSearch component | `src/components/admin/StockImageSearch.vue` | Yes | Reuse inline in preview area |
| Stock image download endpoint | Backend controller | **No** | New `POST /download-stock-image` |
| Variation `source` field | `variations[].source` | **No** | New field: `'gemini'` or `'stock'` |
| Cleanup endpoint | Backend controller | **No** | New `POST /cleanup-variation-images` |
| Cleanup trigger | `handleApprove` in ImageGeneration.vue | Partial | Add cleanup call before navigate |

## Implementation Phases

### Phase 1: Brand ref filename mapping

- `ImageConfigModal.vue` — `brand_refs` stores `{filename, url}` objects
- `ContentIdeaController.php` — `generateSegmentImage` extracts `.url` from brand_ref objects
- `ImageGeneration.vue` — `hasSegmentConfig` + chips handle object brand_refs

### Phase 2: Backend stock image download endpoint

- New endpoint `POST /admin/content-engine/download-stock-image`
  - Input: `{url: "https://images.pexels.com/...", filename: "optional.jpg"}`
  - Downloads image via HTTP, stores in `storage/app/public/blog-images/`
  - Returns: `{url: "https://alisadikinma.com/storage/blog-images/stock-xxx.jpg"}`
- `useContentEngine.js` — add `downloadStockImage(url)` method

### Phase 3: Frontend stock image variation UI

- Preview area (pending state): add "Use Stock Image" button alongside "Generate Image"
- Clicking opens inline `StockImageSearch` inside the preview area
- On image select → calls `downloadStockImage` → adds to `variations[]` with `source: 'stock'`
- Variation strip `+` button: dropdown with "Generate" / "Stock Image" options
- Stock variations show a small stock badge on thumbnail

### Phase 4: Finalize cleanup

- New endpoint `POST /admin/content-engine/cleanup-variation-images`
- `handleApprove` collects non-selected variation URLs, calls cleanup endpoint
- Backend deletes files from storage disk

### Phase 5: Build + verify + deploy + reinstall plugin

## File Change Summary

| Layer | File | Action |
|---|---|---|
| Backend | `ContentIdeaController.php` | MODIFY — extract `.url` from brand_ref objects; new download-stock-image endpoint; new cleanup endpoint |
| Backend | `routes/api.php` | MODIFY — add 2 new routes |
| Frontend | `ImageConfigModal.vue` | MODIFY — brand_refs as `{filename, url}` objects |
| Frontend | `ImageGeneration.vue` | MODIFY — stock image button in preview, inline StockImageSearch, variation source tracking, cleanup on approve |
| Frontend | `useContentEngine.js` | MODIFY — add downloadStockImage + cleanupVariationImages methods |

## YAGNI Cuts

- No stock image watermark removal (Pexels/Unsplash free images are watermark-free)
- No stock image attribution tracking (Pexels/Unsplash license allows usage without attribution in most cases)
- No stock image quality/resolution picker
- No batch stock image selection (one at a time per variation slot)
- No stock image preview modal before adding (click = add)
