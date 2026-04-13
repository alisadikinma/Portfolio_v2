# 3-Step Content Pipeline — Image Generation + Finalize

**Date:** April 13, 2026
**Status:** Design Approved
**Scope:** Frontend (2 new pages), Backend (stock search proxy + image gen updates), Router

---

## Problem Statement

After the Article Preview redesign, the Content Engine pipeline needs two more steps:
1. **Image Generation** — Sparkfluence-style per-segment image generation with editable prompts, reference images, and stock photo search
2. **Finalize** — WYSIWYG preview of the complete article with generated images before publishing

Currently, image generation uses a basic modal in ContentEngine.vue with no per-image control, no reference images, and no final preview.

## Solution

A 3-step pipeline with separate URLs, draft persistence at each step, and a Sparkfluence-inspired image generation UI.

---

## Pipeline Architecture

### 3-Step Flow

```
Content Engine (list)
      │
      ├── Click idea (status: article_ready)
      │
      ▼
Step 1: /admin/content-engine/:id/preview     ← ALREADY BUILT
      │  Article Preview + SEO + Edit title + Image markers
      │  [Approve & Continue]
      ▼
Step 2: /admin/content-engine/:id/images      ← NEW
      │  Per-segment image generation (Sparkfluence-style)
      │  4 editable fields + reference images + stock search
      │  Generate All + per-segment Regenerate
      │  [Approve Images & Continue]
      ▼
Step 3: /admin/content-engine/:id/finalize    ← NEW
      │  WYSIWYG: full article with generated images inserted
      │  Final review before publish
      │  [Publish to Blog]
      ▼
Status → completed
```

### Step Indicator Bar (shared component)
Each page shows a step indicator bar at the top:
```
① Article  ──────  ② Images  ──────  ③ Finalize
   ✓ Done           ● Active           ○ Pending
```
- Completed steps are clickable (navigate back)
- Active step is highlighted
- Pending steps are disabled

### Status Mapping

| Step | Accessible When | Status After Action | Route |
|------|----------------|--------------------|----|
| 1. Article Preview | `article_ready` | stays `article_ready` | `/:id/preview` |
| 2. Image Generation | `article_ready` or `generating_images` or `images_ready` | `generating_images` → `images_ready` | `/:id/images` |
| 3. Finalize | `images_ready` | `completed` | `/:id/finalize` |

### Draft Persistence
- All edits at each step are saved to the `generated_article` JSON column via API
- Navigating between steps preserves all data
- Closing and reopening a tab resumes from the last state
- Status tracks which step the idea is at

---

## Step 2: Image Generation Page

### Route
```
/admin/content-engine/:id/images
```

### Layout

```
┌── Step Bar ───────────────────────────────────────────────────────────┐
│  ① Article ✓  ────────  ② Images ●  ────────  ③ Finalize ○           │
└───────────────────────────────────────────────────────────────────────┘

┌── Title Bar ──────────────────────────────────────────────────────────┐
│  ← Back to Article    "Article Title..."       [Generate All ▶]      │
└───────────────────────────────────────────────────────────────────────┘

┌── COVER ── Segment 1 ────────────────────────────────────────────────┐
│  ┌─ Prompt Fields (~55%) ──────────┐  ┌─ Preview (~45%) ──────────┐ │
│  │  Subject:   [textarea]          │  │  ┌─────────────────────┐  │ │
│  │  Environment: [textarea]        │  │  │  Generated image    │  │ │
│  │  Composition: [textarea]        │  │  │  preview (16:9)     │  │ │
│  │  Style:  [dropdown]             │  │  │                     │  │ │
│  │  Model:  [dropdown]             │  │  └─────────────────────┘  │ │
│  │  Ratio:  [dropdown]             │  │  [🔄 Regenerate]          │ │
│  │                                 │  └────────────────────────────┘ │
│  │  Reference Image (optional):    │                                 │
│  │  [🔍 Search...] [Upload] [URL] │                                 │
│  │  ┌────┐ ┌────┐ ┌────┐          │                                 │
│  │  │img │ │img │ │img │ ...       │                                 │
│  │  └────┘ └────┘ └────┘          │                                 │
│  └─────────────────────────────────┘                                 │
├── Status: ✅ Generated / ⏳ Generating... / ○ Pending ───────────────┤
└───────────────────────────────────────────────────────────────────────┘

┌── BODY-1 · "Section title" ──────────────────────────────────────────┐
│  ... same layout per segment ...                                      │
└───────────────────────────────────────────────────────────────────────┘

┌── BODY-2 · "Section title" ──────────────────────────────────────────┐
│  ... same layout per segment ...                                      │
└───────────────────────────────────────────────────────────────────────┘

┌── Bottom Bar ─────────────────────────────────────────────────────────┐
│  3/3 images generated                  [Approve Images & Continue →]  │
└───────────────────────────────────────────────────────────────────────┘
```

### Segment Card Details

**Left side (~55%) — Prompt Configuration:**

| Field | Input Type | Description |
|-------|-----------|-------------|
| Subject | Textarea | What's in the image (people, objects, scene) |
| Environment | Textarea | Setting, lighting, atmosphere, mood |
| Composition | Textarea | Camera angle, framing, depth |
| Style | Dropdown | Cinematic, Photorealistic, Minimal, Portrait Cinematic, etc. |
| Model | Dropdown | nano-banana-pro, nano-banana-2, imagen-4 |
| Aspect Ratio | Dropdown | 16:9, 4:3, 1:1 |

**Prompt Decomposition:** The single `prompt` string from article generation is split into Subject/Environment/Composition fields on page load. When generating, these 3 fields are concatenated back into a single prompt string.

**Reference Image Section:**
- Search bar → searches Pexels (primary) + Unsplash (fallback) via backend proxy
- Results grid (4 columns) — click to select as reference
- Upload button — local file upload
- Paste URL field — paste any image URL
- Selected reference shown as thumbnail with X to remove
- LocalStorage cache (7-day TTL) for search results

**Right side (~45%) — Image Preview:**
- Loading spinner during generation
- Generated image displayed at correct aspect ratio
- Regenerate button below image
- Error state with retry

### Image Generation Flow

1. **Generate All** — top button sends all segment prompts simultaneously
2. **Per-segment progress** — each segment shows its own status (Pending → Generating → Done/Failed)
3. **Regenerate** — per-segment button to re-generate a single image
4. **Reference image** sent as parameter to image gen API

### Buttons

- **Generate All** (top bar) — generates all pending segments
- **Regenerate** (per segment) — re-generates one segment
- **Approve Images & Continue** (bottom) — only enabled when all segments are generated. Saves data + navigates to Step 3

---

## Step 3: Finalize Page

### Route
```
/admin/content-engine/:id/finalize
```

### Layout

```
┌── Step Bar ───────────────────────────────────────────────────────────┐
│  ① Article ✓  ────────  ② Images ✓  ────────  ③ Finalize ●           │
└───────────────────────────────────────────────────────────────────────┘

┌── WYSIWYG Article Preview ────────────────────────────────────────────┐
│                                                                        │
│  ┌── Language Tabs ────────────────────────────────────────────────┐   │
│  │  [🇺🇸 English]  [🇮🇩 Indonesia]                                │   │
│  └─────────────────────────────────────────────────────────────────┘   │
│                                                                        │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │              [COVER IMAGE — full width, 16:9]                    │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                        │
│  # Article Title                                                       │
│                                                                        │
│  Something satisfying just happened...                                 │
│  ...                                                                   │
│                                                                        │
│  ### Section heading                                                   │
│  ...                                                                   │
│                                                                        │
│  ┌──────────────────────────────────────────────────────────────────┐  │
│  │              [BODY-1 IMAGE — inline, 16:9]                       │  │
│  └──────────────────────────────────────────────────────────────────┘  │
│                                                                        │
│  ... rest of article with images at their positions ...                │
│                                                                        │
└────────────────────────────────────────────────────────────────────────┘

┌── Bottom Bar ──────────────────────────────────────────────────────────┐
│  ← Back to Images               [Publish to Blog →]                   │
└────────────────────────────────────────────────────────────────────────┘
```

### Finalize Behavior
- Read-only WYSIWYG view — article with real generated images inserted at their positions
- Language tabs to switch between EN/ID versions
- Cover image at the top (full width)
- Inline images at their `suggested_position` indices
- "Publish to Blog" button → calls `approveAndPublish()` → status becomes `completed`
- "Back to Images" navigates to Step 2

---

## Backend Changes

### New: Stock Image Search Proxy
```
GET /api/admin/stock-images/search?q={query}&orientation={landscape|portrait|square}&per_page=20&page=1
```

**Controller:** `StockImageController` (new)
**Logic:**
1. Try Pexels API (`https://api.pexels.com/v1/search`) with `PEXELS_API_KEY`
2. If fails/empty → fallback to Unsplash API with `UNSPLASH_ACCESS_KEY`
3. Normalize response to unified format:
   ```json
   {
     "success": true,
     "data": {
       "results": [
         {
           "id": "string",
           "provider": "pexels|unsplash",
           "url_thumb": "string",
           "url_regular": "string",
           "url_full": "string",
           "width": 1920,
           "height": 1080,
           "photographer": "string",
           "alt": "string"
         }
       ],
       "total": 100,
       "query": "solopreneur"
     }
   }
   ```

**Environment Variables:**
```env
PEXELS_API_KEY=your-pexels-key
UNSPLASH_ACCESS_KEY=your-unsplash-key
```

### Update: Image Generation per Segment
The existing `startImageGeneration()` endpoint needs updating to support per-segment generation with reference images. Each segment sends:
```json
{
  "prompt": "concatenated subject + environment + composition",
  "style": "Cinematic",
  "model": "nano-banana-pro",
  "aspect_ratio": "16:9",
  "reference_image_url": "https://...",
  "segment_index": 0
}
```

### Update: Draft Save Endpoint
Need an endpoint to save intermediate state (edited prompts, selected references) without changing status:
```
PUT /api/admin/content-engine/ideas/{id}/save-draft
```
Saves the full `generated_article` JSON with any modifications.

---

## Frontend Changes

### New Files
| File | Description |
|------|-------------|
| `views/admin/ImageGeneration.vue` | Step 2 — per-segment image generation page |
| `views/admin/ArticleFinalize.vue` | Step 3 — WYSIWYG preview + publish |
| `components/admin/PipelineStepBar.vue` | Shared step indicator component |
| `components/admin/StockImageSearch.vue` | Reference image search + grid + upload component |

### Modified Files
| File | Change |
|------|--------|
| `router/index.js` | Add `/:id/images` and `/:id/finalize` routes |
| `composables/useContentEngine.js` | Add `saveDraft()`, `searchStockImages()`, `generateSegmentImage()` methods |
| `views/admin/ArticlePreview.vue` | Update approve flow → navigate to `/:id/images` instead of ContentEngine |
| `views/admin/ContentEngine.vue` | Update click handlers for `images_ready` status → open `/:id/finalize` |

### No New Migrations
All data stored in existing `generated_article` JSON column + `generated_images` JSON column.

---

## Data Flow

### Image Prompt Decomposition (on page load)
```js
// Single prompt string from article generation:
"A cinematic wide shot of a futuristic workspace with holographic AI interfaces, warm golden light, dark moody atmosphere"

// Decomposed into 3 fields (heuristic split or stored pre-split):
Subject:     "Futuristic workspace with holographic AI interfaces"
Environment: "Warm golden light, dark moody atmosphere"
Composition: "Cinematic wide shot"
```

### Generated Images Storage
After generation, results stored in `generated_article.image_prompts[].generated_url`:
```json
{
  "image_prompts": [
    {
      "type": "cover",
      "concept": "...",
      "prompt": "...",
      "subject": "...",
      "environment": "...",
      "composition": "...",
      "style": "Portrait Cinematic",
      "model": "nano-banana-pro",
      "aspect_ratio": "16:9",
      "reference_image_url": "https://images.pexels.com/...",
      "generated_url": "https://storage.alisadikinma.com/images/...",
      "status": "done",
      "suggested_position": 0
    }
  ]
}
```

---

## Design Decisions

1. **Separate URLs per step** — each step is its own page with browser back/forward support
2. **Draft persistence** — all edits saved via API, can close and resume
3. **Sparkfluence-style segments** — 4 editable fields + reference image per segment
4. **Pexels + Unsplash** — dual stock photo provider with automatic fallback (same as Sparkfluence)
5. **Generate All + Regenerate** — batch generation with per-segment retry
6. **WYSIWYG finalize** — final review with real images before publishing
7. **Step indicator bar** — shared component showing pipeline progress, clickable completed steps
