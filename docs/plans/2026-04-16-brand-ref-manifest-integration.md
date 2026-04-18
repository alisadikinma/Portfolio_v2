# Brand Reference Manifest Integration — Design & Plan

## Goal

Integrate the article-content-writer plugin's **Reference Image Manifest** system with the Image Generation UI. When the `article-images` skill identifies brands/products needed per section, the frontend shows a checklist in the per-segment gear modal. Users upload brand images (logo, product screenshots, etc.) which get passed to GeminiGen as `file_urls` for accurate rendering.

## Locked Decisions (from brainstorm)

| # | Decision | Choice |
|---|---|---|
| 1 | UI placement | Per-segment gear modal (ImageConfigModal), not top-level |
| 2 | Manifest handling | Auto-populate from plugin manifest — not manual |
| 3 | Ref types unified | Face + Style + Brand in one modal |
| 4 | Blocking | **Manual UI only:** Wajib items block generation; Opsional can be skipped. **Automation/AI agent:** all items optional — pipeline continues without blocking |

## Architecture

### Data Flow

```
article-images skill runs
  → Context Extraction identifies brands per section
  → POST /progress { step: "manifest_needed", manifest: [{filename, description, used_in, required}] }
Backend
  → Stores manifest in generated_article.brand_manifest
  → Sets status = "awaiting_brand_images"
Frontend (Image Gen page)
  → Detects brand_manifest exists
  → Gear modal shows brand ref checklist per segment (filename, desc, wajib/opsional)
  → User uploads brand images → stored in image_prompts[i].brand_refs[]
  → "Resume" button → POST /continue-pipeline { phase: "images_resume" }
article-images resumes at 20%
  → Generates prompts with brand_refs in file_urls
  → Normal GeminiGen flow continues
```

### Backend Changes

#### 1. Progress callback — handle `manifest_needed` step

`routes/api.php` — progress callback route (line ~340):

When `step === "manifest_needed"` and request has `manifest` array:
- Store manifest in `generated_article.brand_manifest`
- Check trigger source: if `auto_mode === true` (AI agent), skip blocking — continue pipeline immediately with empty brand_refs (all manifest items treated as optional)
- If manual (UI-triggered), set idea status to `awaiting_brand_images` — blocks until user uploads wajib items and clicks Resume

#### 2. Continue pipeline — add `images_resume` phase

`routes/api.php` — continue-pipeline route:

When `phase === "images_resume"`:
- Set idea status back to `generating_images`
- Re-invoke `articleGen->triggerImages()` with brand_refs populated
- Brand refs from `image_prompts[i].brand_refs[]` get merged into the pipeline

#### 3. generateSegmentImage — merge brand_refs into file_urls

`ContentIdeaController.php`:

Add `brand_refs` to validation + segment data. Merge `brand_refs` into the GeminiGen queue call alongside existing `faceRefs`.

### Frontend Changes

#### 4. ImageConfigModal — Brand References section

Add a third section below Style References:
- Only visible when `segment.brand_manifest_items` has entries
- Each manifest entry shows: filename, description, wajib/opsional badge
- Upload button per entry (reuses existing upload-reference endpoint)
- Uploaded URL stored in `brand_refs[]`
- Green checkmark when uploaded, red warning when missing + wajib

#### 5. ImageGeneration.vue — brand awareness

- On initSegments, map `brand_manifest` entries to relevant segments via `used_in`
- Pass `brand_refs` in generateSingle API call
- Detect `awaiting_brand_images` status — show banner: "Brand images needed"
- "Resume Pipeline" button calls `resumeImagePipeline`

#### 6. useContentEngine.js — new method

Add `resumeImagePipeline(id)` → `POST /admin/content-engine/ideas/{id}/continue-pipeline` with `{ phase: "images_resume" }`

## Data Integration Map

| Feature | Data Source | Existing? | Notes |
|---|---|---|---|
| Brand manifest from plugin | `POST /progress { step: "manifest_needed", manifest }` | Partial | Progress callback exists, add manifest handling |
| Manifest storage | `generated_article.brand_manifest` | No | New field in existing JSON column |
| `awaiting_brand_images` status | ContentIdea.status | No | New status value |
| Brand refs per segment | `image_prompts[i].brand_refs[]` | No | New field alongside face_refs/style_refs |
| Brand refs in gear modal | ImageConfigModal | No | New section with manifest checklist |
| `file_urls` in GeminiGen call | ImageGenerationService::queue | Partial | Need to merge brand_refs |
| Resume pipeline | continue-pipeline route | Partial | Add `images_resume` handler |
| Resume composable | useContentEngine.js | No | New `resumeImagePipeline` method |

## Implementation Phases

### Phase 1: Backend — manifest handling + status

- Modify progress callback to detect `manifest_needed`, store manifest, set status
- Add `images_resume` handler to continue-pipeline route
- Add `brand_refs` to generateSegmentImage validation + queue call

### Phase 2: Frontend — ImageConfigModal brand section

- Add `brandRefs` state + `brandManifestItems` computed
- Brand ref upload UI (checklist with wajib/opsional badges)
- Emit `brandRefs` in apply event

### Phase 3: Frontend — ImageGeneration page integration

- Map manifest to segments on init
- Pass brand_refs in generateSingle
- `awaiting_brand_images` banner + resume button
- `resumeImagePipeline` composable method

### Phase 4: Build + verify + deploy

## File Change Summary

| Layer | File | Action |
|---|---|---|
| Backend | `routes/api.php` | MODIFY — progress callback + continue-pipeline |
| Backend | `ContentIdeaController.php` | MODIFY — generateSegmentImage brand_refs |
| Frontend | `ImageConfigModal.vue` | MODIFY — add brand refs section |
| Frontend | `ImageGeneration.vue` | MODIFY — manifest mapping + brand_refs + resume |
| Frontend | `useContentEngine.js` | MODIFY — add resumeImagePipeline |

## YAGNI Cuts

- No drag-drop reorder of brand refs
- No image preview/crop for brand uploads
- No brand ref sharing across segments
- No auto-detect brands from article text
- No separate page/step for brand uploads — stays in gear modal
