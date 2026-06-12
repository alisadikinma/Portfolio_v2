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

    /**
     * @return array{success: bool, error: string|null}
     */
    public function extract(RepurposeJob $job): array
    {
        $toolSlides = $job->videoSlides()->where('role', RepurposeVideoSlide::ROLE_TOOL)->get();
        if ($toolSlides->isEmpty()) {
            return ['success' => false, 'error' => 'no_tool_slides'];
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
            return ['success' => false, 'error' => $error];
        }

        // Map the parsed per-slide title/desc back onto rows by slide number.
        $bySlideNumber = [];
        foreach ((array) ($res['parsed']['slides'] ?? []) as $entry) {
            $n = (int) ($entry['n'] ?? 0);
            if ($n > 0) {
                $bySlideNumber[$n] = $entry;
            }
        }

        foreach ($toolSlides as $slide) {
            $entry = $bySlideNumber[$slide->slide_index] ?? null;
            if ($entry === null) {
                continue;
            }
            $slide->update([
                'header_title' => trim((string) ($entry['title'] ?? '')) ?: null,
                'header_desc' => trim((string) ($entry['desc'] ?? '')) ?: null,
            ]);
        }

        Log::info('[VideoSlideExtractor] extracted', ['job' => $job->id, 'slides' => $toolSlides->count()]);

        return ['success' => true, 'error' => null];
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
You are analyzing slides from an Instagram VIDEO carousel that recommends tools/tips. Each slide has a header band with a TOOL NAME (a short title) and a one-line DESCRIPTION, around a central demo video. Read EACH slide's header text.

{$imageLines}
For each slide, extract the header TITLE (the tool/topic name, 1-4 words) and the one-line DESCRIPTION beneath it. Keep the description faithful to the source (you may lightly clean it), max ~14 words.

STRICT JSON OUTPUT — parsed by a machine, not a human:
- Output ONE compact JSON object only. No markdown fences, no preamble, no trailing prose.
- Escape EVERY double-quote inside a string value as \".
- Do not truncate; always close the JSON object.

Return ONE JSON object with exactly this shape:
{
  "slides": [{"n": 1, "title": "Stitch", "desc": "AI design studio that builds layouts from text"}]
}
PROMPT;
    }
}
