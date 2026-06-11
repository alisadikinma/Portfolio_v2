<?php

namespace App\Services;

use App\Models\RepurposeJob;
use App\Services\Concerns\RunsRepurposeClaudeCli;
use Illuminate\Support\Facades\Log;

/**
 * IG repurpose Phase C — vision-read the captured slide images + caption into
 * structured claims + narrative. Uses Claude CLI image input (the same path
 * ArticleGenerationService::buildVdRewritePrompt reads a local image file).
 *
 * Feasibility (Phase I runbook): if CLI image-input proves unreliable on the
 * VPS, the fallback is Anthropic API image blocks (needs API key) — flagged,
 * not built here. The service returns a clear error so the job fails loudly.
 *
 * @see docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md
 */
class SlideVisionExtractor
{
    use RunsRepurposeClaudeCli;

    /**
     * @return array{success: bool, extracted: array<string,mixed>|null, error: string|null}
     */
    public function extract(RepurposeJob $job): array
    {
        $relDir = (string) $job->slides_path;
        if ($relDir === '') {
            return ['success' => false, 'extracted' => null, 'error' => 'no_slides_path'];
        }

        $absDir = storage_path('app/' . $relDir);
        $slidePaths = $this->resolveSlidePaths($absDir);
        if (empty($slidePaths)) {
            return ['success' => false, 'extracted' => null, 'error' => 'no_slide_files'];
        }

        $caption = $this->readCaption($absDir);
        $prompt = $this->buildPrompt($slidePaths, $caption);

        try {
            $res = $this->runRepurposeParsed($prompt, 'vision', ['claims'], (string) config('services.repurpose.model_vision', 'sonnet'));
        } catch (\Throwable $e) {
            Log::error('[SlideVisionExtractor] exec threw', ['job' => $job->id, 'error' => $e->getMessage()]);
            return ['success' => false, 'extracted' => null, 'error' => 'exec_error: ' . $e->getMessage()];
        }

        if (!$res['success']) {
            $error = $res['error'] === 'unparseable_after_repair' ? 'vision_unparseable' : ($res['error'] ?? 'vision_exec_failed');
            Log::error('[SlideVisionExtractor] unparseable / empty claims', [
                'job' => $job->id,
                'output_head' => mb_substr((string) $res['output'], 0, 500),
                'repaired' => $res['repaired'],
            ]);
            return ['success' => false, 'extracted' => null, 'error' => $error];
        }

        $parsed = $res['parsed'];

        Log::info('[SlideVisionExtractor] extracted', [
            'job' => $job->id,
            'slides' => count($slidePaths),
            'claims' => count((array) ($parsed['claims'] ?? [])),
        ]);

        return ['success' => true, 'extracted' => $parsed, 'error' => null];
    }

    /** @return array<int,string> absolute slide image paths, sorted */
    private function resolveSlidePaths(string $absDir): array
    {
        if (!is_dir($absDir)) {
            return [];
        }
        $files = glob($absDir . '/slide-*.jpg') ?: [];
        sort($files);
        return $files;
    }

    private function readCaption(string $absDir): string
    {
        $path = $absDir . '/caption.txt';
        return is_file($path) ? (string) file_get_contents($path) : '';
    }

    /** @param array<int,string> $slidePaths */
    private function buildPrompt(array $slidePaths, string $caption): string
    {
        $imageLines = '';
        foreach ($slidePaths as $i => $path) {
            $n = $i + 1;
            $imageLines .= "Slide {$n}: read the image file at this path: {$path}\n";
        }
        $captionBlock = trim($caption) !== '' ? "ORIGINAL CAPTION:\n{$caption}\n" : "ORIGINAL CAPTION: (none captured)\n";

        return <<<PROMPT
You are analyzing an Instagram carousel post to repurpose it. Read EACH slide image carefully and the caption, then extract a structured breakdown.

{$imageLines}
{$captionBlock}
For each slide note the on-image text and its narrative role (hook | context | point | data | cta | other).
Then list every factual/claimed statement made across the post — each as a short standalone sentence.

STRICT JSON OUTPUT — your output is parsed by a machine, not a human:
- Output ONE compact JSON object only. No markdown fences, no preamble, no trailing prose.
- Escape EVERY double-quote inside a string value as \" (a raw " inside a value breaks parsing).
- Do not truncate. If content runs long, be more concise but ALWAYS close the JSON object.

Return ONE JSON object, no preamble, no markdown fence, starting with `{` ending with `}`, with exactly these keys:
{
  "slides": [{"n": 1, "text": "...", "role": "hook"}],
  "caption": "the original caption text (cleaned)",
  "claims": ["claim 1", "claim 2"],
  "narrative": "1-3 sentence summary of the post's argument/structure"
}
PROMPT;
    }
}
