# Image Prompt Phase Split — Design Doc

**Date:** 2026-04-15
**Status:** Design approved, awaiting gaspol-plan
**Supersedes parts of:** `2026-04-15-cinematic-image-prompts-design.md` (Phase 5 gap identified mid-execution)

## Goal

Move image prompt generation OUT of the article generation pipeline (`prep → write → score`) and INTO the image generation phase (Gate 2). This:

1. Solves the refs-write.md bundle gap (no need to include `image-prompt-guide.md` there)
2. Makes `article-write` pure prose — lighter bundle, faster generation
3. Lets user approve text at Gate 1 without seeing image prompts
4. Enables per-section image concept editing and granular regeneration
5. Delays image prompt authoring cost until user commits to generating images

## Problem Being Solved

Current state (after prior cinematic prompts refactor was partially applied):
- `article-write` generates both text AND image prompts in one call
- `refs-write.md` currently EXCLUDES `image-prompt-guide.md` (comment says "images handled post-approval") — but SKILL.md still requires image_prompts[] in payload
- Contradiction: writer asked to produce cinematic 300-500 word prompts with NO cinematic guidance in its system prompt → produces generic ~50 word prompts
- User commits compute budget on image prompts even if Gate 1 rejected

## Architecture Overview

**Current pipeline (3 CLI calls):**
```
prep (Sonnet) → write (Sonnet) → score (Sonnet)
                  ↳ text + image_prompts[]
```

**New pipeline (4 phases, 4 CLI calls — but last one deferred to Gate 2):**
```
Article Generation Phase               Image Generation Phase
(Start Research button)                (Generate Images button)
─────────────────────────              ──────────────────────────
prep → write → score                   images → GeminiGen
(Sonnet, text only)                    (Sonnet, then API)
        ↓                                      ↓
   article_ready                         images_ready
   [Gate 1: text]                        [Gate 2: images]
```

**Model uniform:** all phases use Sonnet (matches current VPS `.env`).

## Skill Structure

| Skill | Model | Input | Output | When |
|---|---|---|---|---|
| article-prep | Sonnet | topic + idea config | research + outline (with `image_concept` per section, `image_count`) | Step 1-3 |
| article-write | Sonnet | prep data | **text only** (drop `image_prompts[]` from payload) | Step 4 |
| article-score | Sonnet | article text | 5-gate scores (text-only scoring) | Step 5 |
| **article-images** (NEW) | Sonnet | article + prep outline + image_instructions | `image_prompts[]` (full 300-500w cinematic) | Gate 2 pre |
| article-gen | Sonnet | all-in-one | full article incl. image prompts (fallback) | Fallback |

## Compiled References (`references/compiled/`)

| Bundle | Contents | Changes |
|---|---|---|
| `refs-prep.md` | global-config, frameworks, hook-repo, emotional-arcs, content-templates | No change |
| `refs-write.md` | global-config (trimmed), style-guide, retention-engine, seo-rules-engine (trimmed) | **REMOVE** any image-prompt references in Cinematic rule block (added by prior partial refactor) |
| `refs-score.md` | style-guide, seo-rules-engine, virality-triggers, quality-gate | **REMOVE** any image scoring references |
| `refs-images.md` (NEW) | global-config (§11 Image Generation + §16 Content Templates only), image-prompt-guide (full with cinematic standard + 3 examples), cinematography-lut (full) | NEW, ~20KB |

## Blueprint Awareness (Key Insight)

`article-prep` already populates per-section image blueprint in Step 3 (35% progress):

```json
{
  "outline": {
    "sections": [
      {
        "position": 2,
        "title": "The Hidden Cost",
        "arc_phase": "Problem",
        "image_concept": "stressed team in dim office",  // already exists
        "word_target": 300
      }
    ],
    "image_count": 4  // already exists
  }
}
```

So `article-images` doesn't invent concepts from scratch — it EXPANDS the existing concept into a full cinematic prompt using 8-element WOW framework and 5-paragraph structure. This keeps image selection coherent with article narrative arc (decided during outlining).

## Gate 1 UI Change (ArticlePreview.vue)

Before: Preview shows text + image_prompts section
After: Preview shows text + read-only **image plan metadata**:

```
📸 4 images planned
├── Cover: [concept preview]
├── Section 2 — Problem: stressed team in dim office
├── Section 4 — Solution: confident leader with data
└── Section 6 — CTA: open horizon path
```

User approves text only at this stage. Prompts don't exist yet.

## Gate 2 UI Change (ImageConfigModal.vue)

Before: generic `image_instructions` textarea + reference uploads → "Generate Images" → GeminiGen
After: per-section editable concept list + global instructions + reference uploads:

```
Global image_instructions: [textarea]
Reference images: [upload face / style]

Per-section concepts (editable before generation):
[Cover]  concept: [stressed dev working late]      [edit] [regen]
[S2]     concept: [team hitting data wall]         [edit] [regen]
[S4]     concept: [breakthrough moment]            [edit] [regen]
[S6]     concept: [open horizon, aspirational]     [edit] [regen]

[Generate All Images] [Regenerate Selected]
```

Flow on "Generate All":
1. If any concept edited → PUT `/update-image-concept` per section
2. Trigger `article-images` skill (author prompts, 0-30% progress)
3. Trigger GeminiGen (generate files, 30-100% progress)
4. Status transitions to `images_ready` (Gate 2)

## Regenerate Granularity

| Action | Flow | API |
|---|---|---|
| Regenerate single image file (prompt unchanged) | GeminiGen only | Existing |
| Edit 1 concept, regenerate that image | `update-image-concept` → `article-images only_sections=[N]` → GeminiGen (single) | NEW |
| Edit instructions, regenerate all | `article-images` (all) → GeminiGen (all) | NEW |

## Backend Changes

### New files
- None (reuse existing service + controller patterns)

### Modified files

**`app/Services/ArticleGenerationService.php`** — add method:
```php
public function triggerImages(int $ideaId, string $idempotencyKey, array $onlySections = []): array
{
    $extra = $onlySections ? " only_sections=" . implode(',', $onlySections) : '';
    $prompt = "/article-images idea_id={$ideaId} idempotency_key={$idempotencyKey}{$extra}";
    $refsFile = config('services.article_generation.refs_images', '');
    $model = config('services.article_generation.model_images', 'sonnet');
    return $this->executePrompt($prompt, $ideaId, 'images', $model, $refsFile);
}
```

**`config/services.php`** — add + fix:
```php
'refs_images' => env('ARTICLE_GEN_REFS_IMAGES', ''),
'model_images' => env('ARTICLE_GEN_MODEL_IMAGES', 'sonnet'),
// FIX: model_write default 'opus' → 'sonnet' (match VPS + reality)
'model_write' => env('ARTICLE_GEN_MODEL_WRITE', 'sonnet'),
```

**`app/Http/Controllers/Api/Admin/ContentIdeaController.php`:**
- `generateImages()` — new chain: if no prompts yet, trigger article-images first, continue-pipeline resumes to GeminiGen
- `updateImageConcept()` — NEW endpoint, update single section's `image_concept` in `prep_data.outline.sections[N]`
- `regenerateImagePrompts()` — NEW endpoint, trigger article-images with `only_sections` filter

**`routes/api.php`** — add 3 new endpoints:
| Method | Route | Handler |
|---|---|---|
| PUT | `/api/automation/content-ideas/{id}/save-image-prompts` | automation callback (skill) |
| PUT | `/api/admin/content-engine/ideas/{id}/update-image-concept` | admin (UI edit) |
| POST | `/api/admin/content-engine/ideas/{id}/regenerate-image-prompts` | admin (regen) |

### `continue-pipeline` extension
Existing endpoint gains `phase=images` → triggers GeminiGen after article-images saves prompts.

## Plugin Changes (`D:\Projects\claude-plugin\article-content-writer\`)

### New files
- `skills/article-images/SKILL.md` — new skill per spec below
- `references/compiled/refs-images.md` — compiled bundle

### Modified files
- `skills/article-write/SKILL.md` — drop `image_prompts[]` from JSON payload, remove Cinematic rule block, remove image instructions
- `skills/article-gen/SKILL.md` — fallback still generates all (single-session), no change to image logic (or update to call article-images internally — TBD in plan phase)
- `agents/article-writer.md` — drop image generation Step 4B (or refactor to call article-images logic)
- `scripts/compile-references.sh` — build refs-images.md, trim refs-write.md of image-prompt references
- `plugin.json` (if exists) — register new skill

### Skill Spec: `article-images`

**Invocation:**
```
/article-images idea_id={id} idempotency_key={key} [only_sections=2,4]
```

**Reads:**
- `GET /api/automation/content-ideas/{id}` → `prep_data.outline`, `generated_article.content`, `image_instructions`, per-section `image_concept`

**Process:**
- For each section where `image_concept != null` (filtered by `only_sections` if passed):
  - Apply 8-element WOW framework (Lighting, Depth Layers, Atmosphere, Color Contrast, Emotional Peak, Camera, Texture, Cinematic Reference)
  - Follow 5-paragraph structure
  - Target 300-500 words per prompt
  - Use `image_instructions` as global style/subject guidance
  - Reference `cinematography-lut.md` values (in system prompt)

**Saves:**
```bash
PUT /api/automation/content-ideas/{id}/save-image-prompts
body: { image_prompts: [{type, section, insert_after_heading, concept, prompt, model, style, aspect_ratio, resolution}] }
```

**Then:**
```bash
POST /api/automation/content-ideas/{id}/continue-pipeline
body: { phase: "images", next: "gemini_gen" }
```

## Frontend Changes

### New component
- `ImageConceptEditor.vue` — per-section concept editor (inline edit + regenerate button)

### Modified components
- `ArticlePreview.vue` — drop image_prompts preview, add image plan metadata row
- `ImageConfigModal.vue` — swap generic instructions textarea for per-section editor
- `ProgressModal.vue` — add "Authoring Image Prompts" sub-phase card (shown when `status=generating_images AND !has_image_prompts`)
- `useContentEngine.js` — new methods: `updateImageConcept`, `regenerateImagePrompts`
- `Regenerate-single-image` flow — split "change concept" from "regenerate file"

## Environment Variables

Add to `backend/.env` (and VPS):
```
ARTICLE_GEN_REFS_IMAGES=/home/claudesn/refs-images.md
ARTICLE_GEN_MODEL_IMAGES=sonnet
```

Update `.env.example` similarly.

## Data Integration Map

| Component | Data Source | Existing? | Notes |
|---|---|---|---|
| `outline.sections[].image_concept` | article-prep Step 3 | ✅ | Already populated |
| `outline.image_count` | article-prep Step 3 | ✅ | Already in payload |
| Article retrieval | `GET /api/automation/content-ideas/{id}` | ✅ | article-images reads here |
| `image_prompts[]` save | `PUT /save-image-prompts` | ❌ NEW | |
| Per-concept edit | `PUT /update-image-concept` | ❌ NEW | |
| Per-section regenerate | `POST /regenerate-image-prompts` body `{sections: [N]}` | ❌ NEW | |
| `triggerImages` service method | `ArticleGenerationService` | ❌ NEW | Mirrors triggerWrite |
| GeminiGen | `ImageGenerationService` | ✅ | Unchanged |
| Compiled refs | `refs-images.md` | ❌ NEW | Via compile-references.sh |
| UI blueprint render | `prep_data.outline.sections[]` | ✅ | Data exists, UI render only |

## Status Flow (Unchanged)

```
draft → researching → article_ready [Gate 1 text]
  → (user: Generate Images)
  → generating_images (internally: authoring prompts 0-30% → GeminiGen 30-100%)
  → images_ready [Gate 2 images]
  → completed
```

No new status. Progress modal shows sub-phases.

## In-Flight Migration

Existing ideas in `article_ready` state already have `image_prompts[]` populated (from old write flow). Strategy: **no migration**. Old ideas continue with old prompts if user hits Generate Images (backend checks `has_image_prompts` — skips article-images, goes straight to GeminiGen). New ideas use new flow. After the dust settles (30-90 days), optional cleanup pass.

## Rollout Plan

1. Ship plugin changes (new skill + compiled refs) — no behavior change until backend toggles
2. Ship backend endpoints + service method (feature-flag gated — env `ARTICLE_GEN_USE_IMAGES_PHASE=false` by default)
3. Ship frontend UI behind same flag
4. Test with single idea on VPS (manual smoke test)
5. Flip flag to `true` on VPS
6. Monitor — if issues, flip back to `false` (old flow intact in plugin as fallback)

## Open Questions for Plan Phase

- Does `article-gen` (single-session fallback) need to internally call article-images, or keep inline generation? (leaning: inline, since fallback = refs not configured anyway)
- Do we need rate limiting on regenerate-image-prompts? (probably not — per-idea user-triggered)
- How to handle race: user clicks "Generate Images" twice? (idempotency key in request)
- Reference images (face/style) from existing ImageConfigModal — how do they feed into article-images skill? (pass in `image_instructions`? separate field?)

## Dependencies

- Backend: Laravel 12 + ArticleGenerationService pattern (existing)
- Frontend: Vue 3 + existing ContentEngine composable pattern
- Plugin: article-content-writer plugin structure
- VPS: Claude CLI + SSH driver (existing)
- Compiled refs: bash script pattern (existing)

## Next Step

→ `gaspol-plan` to turn this design into implementation plan with phases + verification criteria.
