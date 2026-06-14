<?php

namespace App\Services;

use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Services\Concerns\RunsRepurposeClaudeCli;
use Illuminate\Support\Facades\Log;

/**
 * video_rebrand Phase C — vision-read each source tool slide's POSTER to recover
 * the header tool TITLE + DESCRIPTION (the text in the source slide's header
 * band), so they can be re-rendered in Ali's brand chrome (Phase D). One Claude
 * CLI vision call across all tool slides (same image-by-path path as
 * SlideVisionExtractor). The center 16:9 crop band is already set at capture
 * (luminance), so this step does NOT touch crop_y/crop_h.
 *
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md
 */
class VideoSlideExtractor
{
    use RunsRepurposeClaudeCli;

    /** Non-content slide kinds — the SOURCE creator's own bookends, dropped (#2). */
    private const NON_CONTENT_KINDS = ['source_hook', 'source_cta'];

    /**
     * @return array{success: bool, error: string|null, dropped: array<int,int>}
     */
    public function extract(RepurposeJob $job): array
    {
        $toolSlides = $job->videoSlides()->where('role', RepurposeVideoSlide::ROLE_TOOL)->get();
        if ($toolSlides->isEmpty()) {
            return ['success' => false, 'error' => 'no_tool_slides', 'dropped' => []];
        }

        // Map slide_index → absolute poster path for the vision prompt.
        $posters = [];
        foreach ($toolSlides as $slide) {
            $rel = (string) $slide->poster_path;
            $posters[$slide->slide_index] = $rel !== '' ? storage_path('app/' . $rel) : '';
        }

        $prompt = $this->buildPrompt($posters);

        try {
            $res = $this->runVisionParsed($prompt);
        } catch (\Throwable $e) {
            Log::error('[VideoSlideExtractor] exec threw', ['job' => $job->id, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => 'exec_error: ' . $e->getMessage()];
        }

        if (!($res['success'] ?? false)) {
            $error = ($res['error'] ?? '') === 'unparseable_after_repair' ? 'vision_unparseable' : ((string) ($res['error'] ?? 'vision_exec_failed'));
            Log::error('[VideoSlideExtractor] vision failed', [
                'job' => $job->id,
                'output_head' => mb_substr((string) ($res['output'] ?? ''), 0, 500),
            ]);
            return ['success' => false, 'error' => $error, 'dropped' => []];
        }

        // Map the parsed per-slide title/desc back onto rows by slide number.
        $bySlideNumber = [];
        foreach ((array) ($res['parsed']['slides'] ?? []) as $entry) {
            $n = (int) ($entry['n'] ?? 0);
            if ($n > 0) {
                $bySlideNumber[$n] = $entry;
            }
        }

        // slide_index values of the SOURCE creator's own hook/cta bookends — the
        // caller (ExtractVideoSlides) drops + renumbers these. A slide with no
        // vision entry (or an unknown kind) is treated as content (conservative —
        // never drop what we couldn't classify).
        $dropped = [];
        $sourceHookTitle = null;
        foreach ($toolSlides as $slide) {
            $entry = $bySlideNumber[$slide->slide_index] ?? null;
            if ($entry === null) {
                continue;
            }
            // Bilingual: header_desc = Indonesian primary, header_desc_en = English
            // companion. Fall back to the legacy single `desc` (source English) for
            // header_desc when the model didn't return a localized pair.
            $descId = trim((string) ($entry['desc_id'] ?? ''));
            $descEn = trim((string) ($entry['desc_en'] ?? ''));
            $legacyDesc = trim((string) ($entry['desc'] ?? ''));
            $slide->update([
                'header_title' => trim((string) ($entry['title'] ?? '')) ?: null,
                'header_desc' => ($descId !== '' ? $descId : $legacyDesc) ?: null,
                'header_desc_en' => $descEn ?: null,
            ]);

            $kind = strtolower(trim((string) ($entry['kind'] ?? 'content')));
            if (in_array($kind, self::NON_CONTENT_KINDS, true)) {
                $dropped[] = (int) $slide->slide_index;
                // Preserve the ORIGINAL creator's cover/hook headline before the
                // caller deletes this row — the rebrand hook overlay reuses it as
                // its title ("dari IG source asli"). First source_hook wins.
                if ($kind === 'source_hook' && $sourceHookTitle === null) {
                    $t = trim((string) ($entry['title'] ?? ''));
                    if ($t !== '') {
                        $sourceHookTitle = $t;
                    }
                }
            }
        }

        if ($sourceHookTitle !== null) {
            $extracted = (array) ($job->extracted ?? []);
            $extracted['source_hook_title'] = $sourceHookTitle;
            $job->update(['extracted' => $extracted]);
        }

        Log::info('[VideoSlideExtractor] extracted', [
            'job' => $job->id,
            'slides' => $toolSlides->count(),
            'dropped' => $dropped,
        ]);

        return ['success' => true, 'error' => null, 'dropped' => $dropped];
    }

    /**
     * Wrap the trait CLI call so tests can subclass + inject canned vision output.
     *
     * @return array{success: bool, parsed: array<string,mixed>|null, output: string, error: string|null, repaired: bool}
     */
    protected function runVisionParsed(string $prompt): array
    {
        return $this->runRepurposeParsed(
            $prompt,
            'video-vision',
            ['slides'],
            (string) config('services.repurpose.model_vision', 'sonnet')
        );
    }

    /** @param array<int,string> $posters slide_index => absolute poster path */
    private function buildPrompt(array $posters): string
    {
        $imageLines = '';
        foreach ($posters as $n => $path) {
            if ($path === '') {
                continue;
            }
            $imageLines .= "Slide {$n}: read the image file at this path: {$path}\n";
        }

        return <<<PROMPT
You are analyzing slides from an Instagram VIDEO carousel that recommends tools/tips. Each slide has a header band with a TOOL NAME (a short title) and a one-line DESCRIPTION, around a central demo video. Read EACH slide's header text AND classify what KIND of slide it is.

{$imageLines}
For each slide:
1. Extract the header TITLE (the tool/topic name, 1-4 words). Keep it VERBATIM — a tool name is a proper noun, do NOT translate it (e.g. "Opal", "Stitch", "Cursor").
2. Extract the one-line DESCRIPTION beneath it (faithful to the source, max ~14 words), and provide it in TWO languages:
   - "desc_id" → Indonesian (PRIMARY display line). Natural, fluent Indonesian — translate the meaning, keep tool/product names verbatim.
   - "desc_en" → English (companion line). The source English, lightly cleaned.
3. Classify the slide KIND as exactly one of:
   - "content"     → a real tool/tip slide (names a tool/topic and shows a demo). This is the default.
   - "source_hook" → the ORIGINAL creator's own intro/cover/title slide: a talking-head face, a "swipe for more" / "save this" / "follow for part 2" prompt, NO actual tool being demonstrated.
   - "source_cta"  → the ORIGINAL creator's own outro: a follow / like / subscribe / share / comment ask, NOT a tool.
   Only classify a slide as source_hook/source_cta when you are confident it is the creator's own bookend, not a real tool. When unsure, use "content".

STRICT JSON OUTPUT — parsed by a machine, not a human:
- Output ONE compact JSON object only. No markdown fences, no preamble, no trailing prose.
- "n" MUST be the EXACT integer slide label given above (the number after "Slide"), NOT a re-counted ordinal. Echo it back verbatim for every slide.
- Escape EVERY double-quote inside a string value as \".
- Do not truncate; always close the JSON object.

Return ONE JSON object with exactly this shape:
{
  "slides": [{"n": 1, "kind": "content", "title": "Stitch", "desc_id": "Studio desain AI yang menyusun layout dari teks", "desc_en": "AI design studio that builds layouts from text"}]
}
PROMPT;
    }
}
