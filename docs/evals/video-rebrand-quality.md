# Eval contract — video_rebrand quality pass (non-deterministic steps)

> Companion to [docs/plans/2026-06-13-video-rebrand-quality-pass.md](../plans/2026-06-13-video-rebrand-quality-pass.md).
> Deterministic unit tests (Docker `serversideup/php:8.2-cli`) verify the plumbing;
> the LLM/vision steps below only truly certify on a live re-run. `gaspol-verify`
> reports pass@k against these. **Fixture:** job #15's source IG VIDEO carousel URL
> + its captured posters (the first end-to-end `video_rebrand` run).

## Why an eval (not just unit tests)

Three steps are non-deterministic (model output): slide-kind classification, hook
public-figure selection, and hook scene authoring. Unit tests pin the contract
(JSON shape, drop/renumber, ref-count, sanitize, fallback) with canned model
output; they cannot certify the model *chooses well*. These evals score that.

## E1 — Slide-kind classification (Phase B)

**Input:** job #15 posters (tool slides + the source creator's own hook/cta).
**Pass criteria (target ≥ 0.9 precision on drops, 1.0 on content retention):**
- The source creator's intro/cover ("swipe for more" / talking-head, no tool) →
  `source_hook` and is dropped.
- The source creator's follow/like outro → `source_cta` and is dropped.
- Every real tool/tip slide → `content` and is **retained** (false-drop = 0 tolerated;
  the all-dropped guard must never trigger on a genuine tool carousel).
- Survivors renumber to contiguous `1..K`; cta bookend lands at `K+1`.

**How to run:** trigger a live `video_rebrand` on the fixture URL; inspect
`[VideoSlideExtractor] extracted` log `dropped` + the `[ExtractVideoSlides] dropped
source bookend slides` log; compare against a hand-labeled poster set.

## E2 — Hook figure selection (Phase C)

**Pass criteria:**
- For a topic naming a company's product, the picked `figure_name` is that company's
  iconic leader (Google → Sundar Pichai, OpenAI → Sam Altman, etc.), or `null` when
  no single figure clearly fits (must NOT hallucinate an unrelated figure).
- `figure_name` resolves to a license-clean photo via `EntityReferenceService`
  (Wikidata sitelinks ≥ 5 + Commons whitelist) OR degrades to creator-only.
- The figure's name NEVER appears in the emitted image prompt (regex check on the
  authored `scene_prompt` + the post-`sanitizeScene` value).

## E3 — Hook scene quality (Phase C)

**Pass criteria (subjective, operator spot-check):**
- The scene is topic-evocative (not a generic studio portrait).
- When a figure is used, the two people are spatially separated ("creator on the
  left … reference image 2 on the right") with no identity bleed in the keyframe.
- Photorealistic, 9:16, no on-image text/logos.

## E4 — Safety degradation (Phase C)

**Pass criteria (deterministic-ish — forced by a real `PROMINENT_PEOPLE_UPLOAD`):**
- A figure-photo upload refusal sets `figure_dropped` and the retry re-authors a
  creator-only scene; the job reaches `assets_ready`, never `failed`, on figure
  refusal (bounded by `MAX_RETRIES`).

## E5 — CTA + chrome render (Phase A + D, visual)

**Pass criteria (operator spot-check on the rendered carousel):**
- Tool-slide chrome: brand logo visible (header top-left + footer), footer reads
  `@alisadikinma` (NOT `@creator-brand`).
- CTA clip carries the Follow/Save/Comment ask overlay; caption carries the same
  ask; neither promises a comment→DM auto-delivery.
- All slides download 206 over HTTPS.

## Regression fixture

Re-running the eval on job #15's source URL is the canonical regression check after
any change to: the extractor prompt, the hook-author prompt, `dispatchKeyframe`,
`CarouselSlideEnhancer` face logic, or the chrome cjs.
