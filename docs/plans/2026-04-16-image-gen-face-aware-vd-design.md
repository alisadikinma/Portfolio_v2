# Image Generation — Face-Aware VD + Config Visibility Design

## Goal

Fix the face-reference/Visual-Direction mismatch that makes COVER image generation produce people who don't match the uploaded reference (uploaded bald 40s man → got young 20s woman). Auto-regenerate Visual Direction via Claude CLI (Sonnet with vision) when a face reference is present, triggered at Apply & Generate. Also surface modal config (face refs, style refs, notes) as visual chips on the segment card so user knows what's wired up without reopening the gear modal. Image view popup (BaseLightbox) already shipped in prior session — confirming it covers request #2.

## Locked Decisions (from brainstorm)

| # | Decision | Choice |
|---|---|---|
| 1 | Face-ref → VD strategy | Auto-regenerate VD via LLM when face ref uploaded |
| 2 | LLM provider | Claude CLI (Sonnet) via existing SSH infrastructure |
| 3 | Regenerate trigger | At "Apply & Generate" click (inside modal) |
| 4 | Card config chips | Face avatar (24px) + style thumbs (40×30) + notes icon |

## Diagnostic Findings

**COVER generated a young woman instead of matching face ref — why:**
- `$faceRefs` was sent to GeminiGen as `file_urls` (now fixed: repeated multipart entries)
- `ImageGenerationService::queue()` appends the clause `"Maintain exact facial identity, appearance, and features from the provided face reference image(s)."`
- But Visual Direction explicitly describes: `"Young Indonesian woman in her mid-20s sitting at a modern minimal desk..."`
- GeminiGen follows the descriptive text OVER the reference image when conflict exists (text has stronger signal than identity-preservation clause)

**Root cause:** contradiction between VD demographics (young woman) and face ref (bald older man). Fix = rewrite VD to match the reference's actual appearance.

## Architecture

### Backend: New VD Rewrite Service Method

`ArticleGenerationService::rewriteVisualDirectionForFace()`:

```php
public function rewriteVisualDirectionForFace(
    string $originalVd,
    string $faceRefUrl,
    array $segmentContext = []  // label, concept, style, etc
): array {
    // Build mini-prompt for Sonnet with vision capability
    $prompt = $this->buildVdRewritePrompt($originalVd, $faceRefUrl, $segmentContext);
    // Run via SSH → claude -p with --image flag (if supported) OR embed URL in prompt

    // Expected output: single paragraph rewritten VD, no preamble, no quotes
    // Parse response, strip whitespace, return:
    return [
        'success' => bool,
        'rewritten_vd' => string|null,
        'error' => string|null,
    ];
}
```

**Prompt structure (mini, 1-shot):**
```
Rewrite this Visual Direction to match the person in the reference image URL.
Keep the scene, setting, lighting, mood, and composition intact.
Only update age, gender, hair, attire, and appearance to match the reference.

Reference image: {face_ref_url}
Original Visual Direction: {original_vd}

Output ONLY the rewritten Visual Direction text, no preamble, no quotes, no markdown.
```

**Execution path:**
- Use `ArticleGenerationService::executePrompt()` helper (already exists)
- Phase tag: `'vd-rewrite'`
- Model: `sonnet` (via `ARTICLE_GEN_MODEL_VD_REWRITE` env, default sonnet)
- No refs bundle needed (simple 1-shot task)
- Capture stdout → parse as the new VD string

### Backend: New Endpoint

`POST /api/admin/content-engine/ideas/{id}/rewrite-vd`

Controller method `ContentIdeaController::rewriteSegmentVd()`:
- Input: `{segment_index: int, face_ref_url: string}`
- Load idea → extract original VD from segment
- Call `rewriteVisualDirectionForFace()`
- If success: update `image_prompts[segment_index].visual_direction` (preserve original as `visual_direction_original` for rollback)
- Response: `{success: true, data: {new_vd: string, original_vd: string}}`

### Frontend: Wire Into Apply & Generate Flow

`ImageGeneration.vue::handleConfigApply`:
1. User uploads face ref in modal, clicks Apply & Generate
2. Modal emits apply event with `{faceRefs, styleRefs, ...}`
3. Handler checks: if `faceRefs.length > 0` AND face_refs changed since last generation:
   - Show toast: "Rewriting visual direction for face reference..."
   - Set `seg.status = 'rewriting_vd'` (new intermediate status)
   - POST `/admin/content-engine/ideas/{id}/rewrite-vd`
   - Response updates `seg.visual_direction` (old preserved as `seg.visual_direction_original`)
   - Then continue to existing `generateSegmentImage` flow
4. If no face_ref (only style ref or plain): skip rewrite, go straight to generate

### Frontend: Card Config Chips

`ImageGeneration.vue` — insert chips row AFTER Style/Model/Ratio pills, BEFORE image preview:

```vue
<div v-if="hasSegmentConfig(seg)" class="flex items-center gap-2 pt-1 border-t border-neutral-100 dark:border-neutral-700/40 mt-2">
  <span class="text-[10px] text-neutral-400 uppercase tracking-wider">Config:</span>

  <!-- Face ref avatars -->
  <template v-for="(url, i) in (seg.face_refs || [])" :key="'face-' + i">
    <img
      v-if="!url.startsWith('blob:')"
      :src="url"
      class="w-6 h-6 rounded-full object-cover border border-neutral-200 dark:border-neutral-700 cursor-pointer hover:ring-2 hover:ring-amber-400"
      :title="`Face reference ${i + 1}`"
      @click="previewConfigImage(url)"
    />
  </template>

  <!-- Style ref thumbnails -->
  <template v-for="(url, i) in (seg.style_refs || [])" :key="'style-' + i">
    <img
      v-if="!url.startsWith('blob:')"
      :src="url"
      class="w-10 h-7 rounded object-cover border border-neutral-200 dark:border-neutral-700 cursor-pointer hover:ring-2 hover:ring-amber-400"
      :title="`Style reference ${i + 1}`"
      @click="previewConfigImage(url)"
    />
  </template>

  <!-- Notes indicator -->
  <span v-if="seg.additional_notes" class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] rounded-full bg-neutral-100 dark:bg-neutral-900/50 text-neutral-600 dark:text-neutral-400" :title="seg.additional_notes">
    📝 Notes
  </span>
</div>
```

Click any chip → reuse BaseLightbox to preview full-size + download. New `previewConfigImage(url)` helper creates an ad-hoc single-image lightbox state.

## Data Integration Map

| Feature | Data Source | Existing? | Notes |
|---|---|---|---|
| Original Visual Direction | `segment.visual_direction` in ContentEngine JSON | Yes | Read source, rewrite target |
| Face ref URL | `segment.face_refs[0]` (post-upload, /storage/...) | Yes | Already uploaded via `upload-reference` endpoint |
| Claude CLI (Sonnet with vision) | `ArticleGenerationService::executePrompt` | Yes | New phase tag `vd-rewrite`, new prompt builder method |
| VD rewrite endpoint | `POST /admin/content-engine/ideas/{id}/rewrite-vd` | No | NEW controller method + route |
| Rewritten VD persistence | `content_ideas.generated_article.image_prompts[i].visual_direction` | Yes | Overwrite + preserve original as `_original` sibling |
| Frontend trigger | `ImageGeneration.vue::handleConfigApply` | Partial | Wrap existing logic with pre-rewrite conditional |
| Segment chips render | Inline Vue template + seg.face_refs/style_refs/additional_notes | Yes | No new data |
| Chip click → lightbox | `BaseLightbox.vue` (already wired for generated images) | Yes | Extend `generatedSegments` computed to also handle ad-hoc single-image preview |

## YAGNI Cuts

- ❌ NO user preview/edit of rewritten VD before it applies (keep 1-shot, auto-save)
- ❌ NO Claude vision direct API (using existing SSH CLI infra)
- ❌ NO per-face-ref regeneration if multiple face refs — take first one
- ❌ NO "undo rewrite" UI button (original stored silently as `_original` — rollback is manual DB edit if ever needed)
- ❌ NO new image view popup — BaseLightbox already shipped in prior session
- ❌ NO vision-model caption extraction (we let Sonnet see the URL directly, minimal prompt)

## Implementation Feasibility

✅ Reuses `ArticleGenerationService::executePrompt` SSH pipeline
✅ Face ref URLs already public (200 OK verified previously)
✅ Claude CLI supports `--image` flag for vision

⚠️ **Claude CLI vision flag verification needed** — if `--image URL` not supported, fallback is to inline the URL in prompt text and ask Sonnet to fetch it. Needs quick check during Phase 1 of execution.

⚠️ **Latency** — Sonnet call adds ~10-20s to "Apply & Generate" flow. User sees spinner + "Rewriting VD..." toast. Acceptable.

## File Change Summary

| Layer | File | Action |
|---|---|---|
| Backend | `app/Services/ArticleGenerationService.php` | ADD `rewriteVisualDirectionForFace` + `buildVdRewritePrompt` methods |
| Backend | `app/Http/Controllers/Api/Admin/ContentIdeaController.php` | ADD `rewriteSegmentVd` method |
| Backend | `routes/api.php` | ADD admin route `/ideas/{id}/rewrite-vd` |
| Backend | `config/services.php` | ADD optional `model_vd_rewrite` config key |
| Frontend | `frontend/src/composables/useContentEngine.js` | ADD `rewriteSegmentVd` composable method |
| Frontend | `frontend/src/views/admin/ImageGeneration.vue` | MODIFY `handleConfigApply` to branch on face_refs; ADD chips row + `previewConfigImage` helper |

**Total: 5 source files modified.** No migrations, no new composables, no new Vue components.

## Estimated Total Time

- Backend service method + prompt builder: ~15 min
- Backend endpoint + route: ~8 min
- Frontend compose flow integration: ~12 min
- Frontend chips UI + lightbox wiring: ~15 min
- Build + manual smoke: ~10 min
- Commit + VPS deploy: ~10 min
- **Total: ~70 minutes**

## Open Questions

None — all 4 design decisions locked upfront.
