<?php

namespace App\Services;

use App\Models\RepurposeJob;
use App\Services\Concerns\RunsRepurposeClaudeCli;
use Illuminate\Support\Facades\Log;

/**
 * IG repurpose Phase D — deep-research / fact-check the extracted claims. Per
 * claim → verdict {claim, status: correct|wrong|outdated|unverified, corrected,
 * sources[]}. Corrections + sources are what make the repurposed version "more
 * powerful than the original" (D4) — used by the rewrite step and attached to
 * the draft.
 *
 * Uses Claude CLI's NATIVE WebSearch/WebFetch tools (gated by permissions, NOT
 * MCP) — so the empty-mcp leak guard still applies while live web access works.
 * No claim is silently dropped: an unverifiable claim is kept with
 * status=unverified.
 *
 * @see docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md
 */
class RepurposeResearchService
{
    use RunsRepurposeClaudeCli;

    /**
     * @return array{success: bool, research: array<string,mixed>|null, error: string|null}
     */
    public function research(RepurposeJob $job): array
    {
        $extracted = (array) ($job->extracted ?? []);
        $claims = array_values(array_filter((array) ($extracted['claims'] ?? []), fn ($c) => is_string($c) && trim($c) !== ''));
        if (empty($claims)) {
            return ['success' => false, 'research' => null, 'error' => 'no_claims_to_research'];
        }

        $prompt = $this->buildPrompt($claims, (string) ($extracted['narrative'] ?? ''), (string) $job->source_url);

        try {
            $result = $this->runRepurposeSync($prompt, 'research', (string) config('services.repurpose.model_research', 'sonnet'));
        } catch (\Throwable $e) {
            Log::error('[RepurposeResearch] exec threw', ['job' => $job->id, 'error' => $e->getMessage()]);
            return ['success' => false, 'research' => null, 'error' => 'exec_error: ' . $e->getMessage()];
        }

        if (!$result['success']) {
            return ['success' => false, 'research' => null, 'error' => $result['error'] ?? 'research_exec_failed'];
        }

        $parsed = $this->parseJsonObject($result['output']);
        if ($parsed === null || empty($parsed['verdicts'])) {
            Log::error('[RepurposeResearch] unparseable / empty verdicts', [
                'job' => $job->id,
                'output_head' => mb_substr($result['output'], 0, 500),
            ]);
            return ['success' => false, 'research' => null, 'error' => 'research_unparseable'];
        }

        $verdicts = array_values((array) $parsed['verdicts']);
        $corrected = count(array_filter($verdicts, fn ($v) => in_array(($v['status'] ?? ''), ['wrong', 'outdated'], true)));

        Log::info('[RepurposeResearch] done', [
            'job' => $job->id,
            'claims' => count($claims),
            'verdicts' => count($verdicts),
            'corrected' => $corrected,
        ]);

        $parsed['corrected_count'] = $corrected;

        return ['success' => true, 'research' => $parsed, 'error' => null];
    }

    /** @param array<int,string> $claims */
    private function buildPrompt(array $claims, string $narrative, string $sourceUrl): string
    {
        $claimsJson = json_encode(array_values($claims), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $narrativeLine = trim($narrative) !== '' ? "POST NARRATIVE: {$narrative}\n" : '';

        return <<<PROMPT
You are a rigorous fact-checker. Verify each claim below using web search (use your WebSearch/WebFetch tools). Source post: {$sourceUrl}

{$narrativeLine}CLAIMS (JSON array):
{$claimsJson}

For EACH claim return a verdict. Do NOT drop any claim — if you cannot verify, mark it unverified.
- status: "correct" | "wrong" | "outdated" | "unverified"
- corrected: the accurate statement (for wrong/outdated; else repeat the claim or note nuance)
- sources: array of 1-3 credible source URLs you actually consulted (empty for unverified)

Return ONE JSON object, no preamble, no markdown fence, starting with `{` ending with `}`:
{
  "verdicts": [
    {"claim": "...", "status": "wrong", "corrected": "...", "sources": ["https://..."]}
  ],
  "summary": "1-2 sentence overview of what was corrected/strengthened"
}
PROMPT;
    }
}
