<?php

namespace App\Services;

use App\Exceptions\CarouselGenAdapterException;

/**
 * Maps the /carousel-gen plugin's stdout JSON output onto the existing
 * linkedin_posts.carousel_slides JSON column shape.
 *
 * The plugin's contract lives at:
 *   D:/Projects/claude-plugin/ai-image-carousel-prompt-gen/skills/carousel-gen/schema.ts
 *
 * That schema is a discriminated union on `status`:
 *   - 'complete' → full narrative envelope with N slides (5-15)
 *   - 'failed'   → minimal envelope (status + format + generated_at + error?)
 *
 * On the complete path, this adapter:
 *   1. Normalizes per-slide copy into a uniform { copy_id, copy_en } pair.
 *      Bilingual mode (`bilingual=true`) → both fields populated from the
 *      slide's `copy_id` / `copy_en` source fields.
 *      Single-language mode (`bilingual=false`) → the slide's `copy` field
 *      becomes `copy_id`; `copy_en` is null. This is the back-compat shape
 *      that draft #28+ already use in production.
 *   2. Passes through the plugin's `image_prompt` VERBATIM. The 6 brand chrome
 *      placeholder tokens ({{CREATOR_FACE}}, {{BRAND_LOGO}}, {{HANDLE}},
 *      {{PORTFOLIO_URL}}, {{PAGE_INDICATOR}}, {{SWIPE_TEXT}}) MUST be
 *      preserved untouched — CarouselSlideEnhancer resolves them at GeminiGen
 *      dispatch time. Any mutation here breaks the brand chrome contract.
 *   3. Initializes the four backend-managed image lifecycle fields
 *      (image_status='pending', image_url=null, image_job_uuid=null,
 *      image_error=null). The webhook-driven LinkedInCarouselImageService
 *      flips these as GeminiGen returns slide PNGs.
 *
 * On the failed path, throws CarouselGenAdapterException so the caller
 * (Phase A6 LinkedInGenerationService::routeCarouselGeneration) can route the
 * draft to FSM Failed state via the existing markFailed() pattern.
 *
 * Single public method by design — adapters should be plumbing, not
 * orchestration.
 */
class CarouselGenOutputAdapter
{
    /**
     * Map a /carousel-gen stdout JSON envelope to the linkedin_posts
     * carousel_slides JSON column shape.
     *
     * @param array $carouselGenJson Decoded JSON matching CarouselGenOutputSchema.
     *                                Must carry a top-level `status` discriminator.
     *
     * @return array<int, array{
     *   slide_number: int,
     *   layout_hint: string,
     *   copy_id: string|null,
     *   copy_en: string|null,
     *   image_prompt: string,
     *   is_cover: bool,
     *   is_cta: bool,
     *   direct_answer_block: string|null,
     *   image_status: 'pending',
     *   image_url: null,
     *   image_job_uuid: null,
     *   image_error: null,
     * }>
     *
     * @throws CarouselGenAdapterException When status is 'failed' or any value
     *                                      other than 'complete'.
     */
    public function adapt(array $carouselGenJson): array
    {
        $status = $carouselGenJson['status'] ?? null;

        if ($status === 'failed') {
            $error = $carouselGenJson['error'] ?? null;
            $message = (is_string($error) && trim($error) !== '')
                ? $error
                : 'carousel-gen returned status=failed';
            throw new CarouselGenAdapterException($message);
        }

        if ($status !== 'complete') {
            throw new CarouselGenAdapterException(
                'carousel-gen returned unexpected status: ' . var_export($status, true)
            );
        }

        $bilingual = (bool) ($carouselGenJson['bilingual'] ?? false);
        $sourceSlides = $carouselGenJson['slides'] ?? [];

        // The plugin's Zod schema enforces slides.min(5) on complete envelopes,
        // but defense-in-depth at the adapter layer guards against payloads
        // that bypassed validation (e.g., manual operator-injected JSON during
        // debugging, or a future schema-drift bug). A complete carousel with
        // zero slides is definitionally malformed.
        if (count($sourceSlides) === 0) {
            throw new CarouselGenAdapterException(
                'carousel-gen returned status=complete with empty slides array'
            );
        }

        $adapted = [];
        foreach ($sourceSlides as $slide) {
            [$copyId, $copyEn] = $this->resolveCopy($slide, $bilingual);

            $adapted[] = [
                'slide_number' => (int) $slide['slide_number'],
                'layout_hint' => (string) $slide['layout_hint'],
                'copy_id' => $copyId,
                'copy_en' => $copyEn,
                'image_prompt' => (string) ($slide['image_prompt'] ?? ''),
                'is_cover' => (bool) ($slide['is_cover'] ?? false),
                'is_cta' => (bool) ($slide['is_cta'] ?? false),
                'direct_answer_block' => $this->resolveDirectAnswerBlock($slide),
                // people_spotlight contract (plugin v3.0.6+) — passed through so
                // the backend fulfilment layer (CarouselPersonPhotoEnricher) can
                // resolve + composite REAL photos of the named people. Defaults
                // are safe (false / [] / null) so legacy slides + pre-feature
                // plugin output never trip the downstream `needs_real_faces`
                // check. See plugin schema.ts CarouselSlideSchema.
                'needs_real_faces' => (bool) ($slide['needs_real_faces'] ?? false),
                'people' => $this->resolvePeople($slide),
                'face_layout' => $this->resolveFaceLayout($slide),
                // Backend-managed lifecycle fields — always initialized to
                // pending/null on first adapt. LinkedInCarouselImageService
                // flips these as GeminiGen returns slide PNGs.
                'image_status' => 'pending',
                'image_url' => null,
                'image_job_uuid' => null,
                'image_error' => null,
            ];
        }

        return $adapted;
    }

    /**
     * Resolve the bilingual copy pair for a single slide.
     *
     * Bilingual mode: pull copy_id + copy_en from the source slide as-is.
     * Single-language mode: source `copy` becomes copy_id; copy_en is null.
     *
     * @param array $slide
     * @param bool $bilingual
     * @return array{0: string|null, 1: string|null}
     */
    private function resolveCopy(array $slide, bool $bilingual): array
    {
        if ($bilingual) {
            return [
                $slide['copy_id'] ?? null,
                $slide['copy_en'] ?? null,
            ];
        }

        // Single-language: source `copy` → copy_id; copy_en stays null.
        return [
            $slide['copy'] ?? null,
            null,
        ];
    }

    /**
     * Sanitize the plugin's people_spotlight `people[]` into a clean list of
     * {name, role?} entries. Drops entries without a usable name (>= 2 chars),
     * coerces role to a trimmed string or omits it, and caps the list at 6
     * (mirrors the plugin schema's .max(6)). Returns [] when absent/malformed —
     * the downstream enricher treats an empty list as "nothing to fulfil".
     *
     * @return array<int, array{name: string, role?: string}>
     */
    private function resolvePeople(array $slide): array
    {
        $raw = $slide['people'] ?? null;
        if (! is_array($raw)) {
            return [];
        }

        $people = [];
        foreach ($raw as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $name = trim((string) ($entry['name'] ?? ''));
            if (mb_strlen($name) < 2) {
                continue;
            }
            $person = ['name' => $name];
            $role = trim((string) ($entry['role'] ?? ''));
            if ($role !== '') {
                $person['role'] = $role;
            }
            $people[] = $person;
            if (count($people) >= 6) {
                break;
            }
        }

        return $people;
    }

    /**
     * face_layout is one of the plugin enum values; anything else (or absent)
     * resolves to null. 'none' is normalized to null too — it means "no faces",
     * which is equivalent to the field being unset for the fulfilment layer.
     */
    private function resolveFaceLayout(array $slide): ?string
    {
        $value = $slide['face_layout'] ?? null;
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return in_array($value, ['photo_band_top', 'photo_band_inline'], true) ? $value : null;
    }

    /**
     * direct_answer_block is only valid on direct_answer slides per the
     * source schema's superRefine constraint. The adapter mirrors that
     * invariant — non-direct_answer slides always get null even if the
     * upstream JSON happened to include a value (defense-in-depth against
     * plugin schema drift).
     */
    private function resolveDirectAnswerBlock(array $slide): ?string
    {
        if (($slide['layout_hint'] ?? null) !== 'direct_answer') {
            return null;
        }

        $block = $slide['direct_answer_block'] ?? null;
        return is_string($block) ? $block : null;
    }
}
