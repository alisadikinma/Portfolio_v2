<?php

namespace App\Services;

use App\Models\RepurposeJob;
use App\Services\Concerns\RunsRepurposeClaudeCli;
use Illuminate\Support\Facades\Log;

/**
 * IG repurpose Phase E — rewrite the researched material into a powerful,
 * accurate article body in Ali's style (corrected claims from research, optional
 * operator angle). Output feeds the finalize step: blog mode → ContentIdea
 * article, carousel mode → /carousel-gen inline body.
 *
 * Appends the article style-guide refs (services.repurpose.refs_rewrite, default
 * = ARTICLE_GEN_REFS_WRITE) so voice/format match the rest of the blog.
 *
 * @see docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md
 */
class RepurposeRewriteService
{
    use RunsRepurposeClaudeCli;

    /**
     * @return array{success: bool, rewritten: array<string,mixed>|null, error: string|null}
     */
    public function rewrite(RepurposeJob $job): array
    {
        $extracted = (array) ($job->extracted ?? []);
        $research = (array) ($job->research ?? []);
        $verdicts = (array) ($research['verdicts'] ?? []);
        if (empty($verdicts) && empty($extracted['claims'])) {
            return ['success' => false, 'rewritten' => null, 'error' => 'nothing_to_rewrite'];
        }

        $prompt = $this->buildPrompt($extracted, $research, (string) ($job->angle ?? ''));
        $refs = (string) config('services.repurpose.refs_rewrite', '');

        try {
            $result = $this->runRepurposeSync($prompt, 'rewrite', (string) config('services.repurpose.model_rewrite', 'sonnet'), $refs);
        } catch (\Throwable $e) {
            Log::error('[RepurposeRewrite] exec threw', ['job' => $job->id, 'error' => $e->getMessage()]);
            return ['success' => false, 'rewritten' => null, 'error' => 'exec_error: ' . $e->getMessage()];
        }

        if (!$result['success']) {
            return ['success' => false, 'rewritten' => null, 'error' => $result['error'] ?? 'rewrite_exec_failed'];
        }

        $parsed = $this->parseJsonObject($result['output']);
        if ($parsed === null || empty($parsed['title']) || empty($parsed['body'])) {
            Log::error('[RepurposeRewrite] unparseable / missing title|body', [
                'job' => $job->id,
                'output_head' => mb_substr($result['output'], 0, 500),
            ]);
            return ['success' => false, 'rewritten' => null, 'error' => 'rewrite_unparseable'];
        }

        Log::info('[RepurposeRewrite] done', [
            'job' => $job->id,
            'title_len' => strlen((string) $parsed['title']),
            'body_len' => strlen((string) $parsed['body']),
        ]);

        return ['success' => true, 'rewritten' => $parsed, 'error' => null];
    }

    /**
     * @param array<string,mixed> $extracted
     * @param array<string,mixed> $research
     */
    private function buildPrompt(array $extracted, array $research, string $angle): string
    {
        $narrative = (string) ($extracted['narrative'] ?? '');
        $slidesText = collect((array) ($extracted['slides'] ?? []))
            ->map(fn ($s) => trim((string) ($s['text'] ?? '')))
            ->filter()
            ->implode("\n- ");
        $verdictsJson = json_encode(array_values((array) ($research['verdicts'] ?? [])), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $angleLine = trim($angle) !== '' ? "OPERATOR ANGLE (steer the piece toward this): {$angle}\n" : '';

        return <<<PROMPT
You are repurposing someone else's Instagram carousel into a sharper, MORE accurate, original article in the author's voice (Indonesian tech/AI blog, casual-professional). This is transformative rewriting, NOT a paraphrase — use the fact-check verdicts to correct and strengthen.

{$angleLine}
ORIGINAL POST NARRATIVE: {$narrative}

ORIGINAL SLIDE TEXT:
- {$slidesText}

FACT-CHECK VERDICTS (use corrected versions; cite sources):
{$verdictsJson}

REQUIREMENTS:
- Write a complete article body in clean semantic HTML (<h2>, <h3>, <p>, <ul>, <li>, <strong>, <em>, <blockquote>). NO <h1> (title is separate).
- Replace every wrong/outdated claim with its corrected version. Add the data/sources inline where natural.
- Keep the author's voice; do not credit or quote the original creator verbatim.
- End the body with a "Sumber" section listing the cited source URLs as an HTML <ul> of <a> links.
- meta_keywords: 5-7 short entity tokens (comma-separated), no long phrases.

Return ONE JSON object, no preamble, no markdown fence, starting with `{` ending with `}`:
{
  "title": "...",
  "body": "<h2>...</h2><p>...</p>...",
  "excerpt": "1-2 sentence summary",
  "meta_keywords": "tok1, tok2, tok3",
  "sources_appendix": ["https://...", "https://..."]
}
PROMPT;
    }
}
