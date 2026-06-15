<?php

namespace App\Services;

use App\Models\RepurposeJob;
use App\Services\Concerns\RunsRepurposeClaudeCli;
use Illuminate\Support\Facades\Log;

/**
 * video_rebrand bookend HOOK title → bilingual (Indonesian primary + English
 * companion), matching the tool slides' header_desc / header_desc_en pair.
 *
 * The captured `source_hook_title` is the ORIGINAL IG cover headline (usually
 * English, e.g. "AI Tools That Save Hours"). The hook overlay must lead with an
 * Indonesian headline, so this resolver produces { id, en } and caches the result
 * onto the job's `extracted` (source_hook_title_id / source_hook_title_en) so the
 * translation runs ONCE per job — every later re-skin is a cache hit (no CLI).
 *
 * Graceful: a translation miss returns the original as the Indonesian line with no
 * companion, so the hook still renders (never blocks the clip on a CLI flake).
 */
class RepurposeHookTitleResolver
{
    use RunsRepurposeClaudeCli;

    /**
     * Resolve the bilingual hook title for a job, populating the cache on first run.
     *
     * @return array{id: string, en: string} '' / '' when there is no source headline at all
     */
    public function resolve(RepurposeJob $job): array
    {
        $extracted = (array) ($job->extracted ?? []);

        $cachedId = trim((string) ($extracted['source_hook_title_id'] ?? ''));
        if ($cachedId !== '') {
            return ['id' => $cachedId, 'en' => trim((string) ($extracted['source_hook_title_en'] ?? ''))];
        }

        $original = trim($job->videoHookTitle());
        if ($original === '') {
            return ['id' => '', 'en' => ''];
        }

        $res = $this->translate($original);

        // Cache ONLY a real translation — never persist the un-translated fallback,
        // so a transient CLI miss is retried on the next re-skin instead of sticking
        // the hook in the source language forever.
        if ($res['ok']) {
            $extracted['source_hook_title_id'] = $res['id'];
            $extracted['source_hook_title_en'] = $res['en'];
            $job->update(['extracted' => $extracted]);
        }

        return ['id' => $res['id'], 'en' => $res['en']];
    }

    /**
     * One light CLI call: given the original headline (any language), return the
     * Indonesian-primary line + the English companion. On any failure, degrade to
     * the original as the Indonesian line with no companion (ok=false → not cached).
     *
     * @return array{id: string, en: string, ok: bool}
     */
    private function translate(string $original): array
    {
        try {
            $res = $this->runHookTitleTranslate($this->buildPrompt($original));
        } catch (\Throwable $e) {
            Log::warning('[RepurposeHookTitleResolver] translate exec threw', ['error' => $e->getMessage()]);

            return ['id' => $original, 'en' => '', 'ok' => false];
        }

        if (!($res['success'] ?? false)) {
            Log::warning('[RepurposeHookTitleResolver] translate failed — keeping original', ['error' => $res['error'] ?? null]);

            return ['id' => $original, 'en' => '', 'ok' => false];
        }

        $parsed = (array) ($res['parsed'] ?? []);
        $id = trim((string) ($parsed['title_id'] ?? ''));
        $en = trim((string) ($parsed['title_en'] ?? ''));

        // title_id is the contract (the required key); title_en may legitimately be
        // empty if the source was already Indonesian.
        return $id !== '' ? ['id' => $id, 'en' => $en, 'ok' => true] : ['id' => $original, 'en' => '', 'ok' => false];
    }

    /**
     * Test seam — wraps the trait CLI call so tests can subclass + inject a canned
     * translation without a real Claude CLI.
     *
     * @return array{success: bool, parsed: array<string,mixed>|null, output: string, error: string|null, repaired: bool}
     */
    protected function runHookTitleTranslate(string $prompt): array
    {
        return $this->runRepurposeParsed(
            $prompt,
            'repurpose-hook-title',
            ['title_id'],
            (string) config('services.repurpose.model_hook_translate', 'sonnet')
        );
    }

    private function buildPrompt(string $original): string
    {
        return <<<PROMPT
You are localizing the COVER HEADLINE of a short educational Instagram/LinkedIn video carousel about AI tools.

Source headline: "{$original}"

Produce a BILINGUAL pair for the hook:
- "title_id" → Indonesian, the PRIMARY scroll-stopping headline (natural, punchy, ≤ 9 words). Keep tool/product/brand names verbatim (do NOT translate proper nouns like "Google", "Cursor", "AI Tools"). Preserve any leading number ("7 ...").
- "title_en" → the English companion line (the cleaned source headline; if the source is already English, lightly polish it). May be empty only if the source is already Indonesian.

STRICT JSON OUTPUT — parsed by a machine: output ONE compact JSON object only, no markdown fences, no preamble, no trailing prose. Escape every double-quote inside a value as \". Return exactly: {"title_id": "...", "title_en": "..."}
PROMPT;
    }
}
