<?php

namespace App\Services;

use App\Services\Concerns\RunsRepurposeClaudeCli;
use Illuminate\Support\Facades\Log;

/**
 * video_rebrand Phase C (#1 topic-aware hook) — authors a topic-evocative HOOK
 * keyframe scene prompt following the /carousel-gen hook standard (Spotlight
 * Portrait: creator + topic-evocative setting). The Veo clip merely animates this
 * keyframe, so hook quality is decided here at image-prompt authoring.
 *
 * When a public figure fits the topic (e.g. a Google tool → its CEO), the figure
 * enters the keyframe as a face REFERENCE (reference image 2), never by name —
 * the figure's NAME is returned separately and used ONLY to resolve a license-
 * clean photo via EntityReferenceService. The name must NEVER appear in the image
 * prompt (it would trip GeminiGen's named-public-figure text filter); sanitizeScene
 * strips it as defense-in-depth.
 *
 * Pure orchestration — no new plugin/skill. The carousel-gen hook standard is a
 * bundled VPS ref appended via --append-system-prompt-file (services.repurpose.refs_hook).
 *
 * @see docs/plans/2026-06-13-video-rebrand-quality-pass.md Phase C
 */
class VideoHookSceneAuthor
{
    use RunsRepurposeClaudeCli;

    /**
     * Author a hook scene for $topic. When $allowFigure is true the model may pick
     * one iconic public figure (returned as figure_name); when false it authors a
     * creator-only scene (figure_name null) — used by the safety fallback after a
     * PROMINENT_PEOPLE_UPLOAD refusal.
     *
     * @return array{success: bool, figure_name: string|null, scene_prompt: string, error: string|null}
     */
    public function author(string $topic, bool $allowFigure = true): array
    {
        $topic = trim($topic);
        if ($topic === '') {
            return ['success' => false, 'figure_name' => null, 'scene_prompt' => '', 'error' => 'empty_topic'];
        }

        try {
            $res = $this->runHookAuthor($this->buildPrompt($topic, $allowFigure));
        } catch (\Throwable $e) {
            Log::warning('[VideoHookSceneAuthor] exec threw', ['error' => $e->getMessage()]);

            return ['success' => false, 'figure_name' => null, 'scene_prompt' => '', 'error' => 'exec_error'];
        }

        if (!($res['success'] ?? false)) {
            return ['success' => false, 'figure_name' => null, 'scene_prompt' => '', 'error' => (string) ($res['error'] ?? 'author_failed')];
        }

        $parsed = (array) ($res['parsed'] ?? []);
        $scene = trim((string) ($parsed['scene_prompt'] ?? ''));
        if ($scene === '') {
            return ['success' => false, 'figure_name' => null, 'scene_prompt' => '', 'error' => 'no_scene'];
        }

        // figure_name only honored when figures are allowed; "null"/empty → none.
        $figure = null;
        if ($allowFigure) {
            $raw = trim((string) ($parsed['figure_name'] ?? ''));
            if ($raw !== '' && strtolower($raw) !== 'null' && strtolower($raw) !== 'none') {
                $figure = $raw;
            }
        }

        return [
            'success' => true,
            'figure_name' => $figure,
            // Strip the name even on success — the LLM occasionally leaks it into
            // the prose; it must never reach the image prompt.
            'scene_prompt' => $this->sanitizeScene($scene, $figure),
            'error' => null,
        ];
    }

    /**
     * Remove the figure's name (and bare first/last tokens) from the scene prompt
     * so the public-figure NAME never reaches GeminiGen's text filter — the figure
     * is conveyed only via "reference image 2".
     */
    public function sanitizeScene(string $scene, ?string $figureName): string
    {
        $name = trim((string) $figureName);
        if ($name === '') {
            return $scene;
        }
        $needles = array_unique(array_filter(array_merge([$name], preg_split('/\s+/', $name) ?: [])));
        // Longest first so the full name is removed before its parts.
        usort($needles, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        foreach ($needles as $needle) {
            if (mb_strlen($needle) < 3) {
                continue; // skip initials/short tokens to avoid mangling unrelated words
            }
            $scene = preg_replace('/\b' . preg_quote($needle, '/') . "(?:'s)?\\b/i", 'the person matching reference image 2', $scene) ?? $scene;
        }

        return trim(preg_replace('/\s{2,}/', ' ', $scene) ?? $scene);
    }

    /**
     * Test seam — wraps the trait CLI call so tests can subclass + inject canned
     * author JSON without a real Claude CLI. figure_name is optional (the model
     * may decline to pick a figure); only scene_prompt is required.
     *
     * @return array{success: bool, parsed: array<string,mixed>|null, output: string, error: string|null, repaired: bool}
     */
    protected function runHookAuthor(string $prompt): array
    {
        return $this->runRepurposeParsed(
            $prompt,
            'video-hook-author',
            ['scene_prompt'],
            (string) config('services.repurpose.model_hook_author', 'sonnet'),
            $this->refsBundle()
        );
    }

    /**
     * The hook/CTA author shares the SAME compiled knowledge bundle as
     * /carousel-gen (hook-visual-library + hook-science + creator-bible + the v3
     * visual standard) so both stay one source of truth — the author applies the
     * plugin's dynamic hook rules per topic instead of any hardcoded rule here.
     *
     * Defaults to the carousel-gen bundle (`carousel-gen.refs_pipeline`, already
     * required + deployed for the carousel pipeline) so it can NEVER be silently
     * empty. `REPURPOSE_REFS_HOOK` is now only an optional override — a missing
     * env used to leave this knowledge-less (the "rules seem gone" bug).
     */
    protected function refsBundle(): string
    {
        $refs = (string) config('services.repurpose.refs_hook', '');

        return $refs !== '' ? $refs : (string) config('carousel-gen.refs_pipeline', '');
    }

    private function buildPrompt(string $topic, bool $allowFigure): string
    {
        // The full hook/cover + creator + visual standard lives in the SYSTEM
        // PROMPT (the /carousel-gen knowledge bundle via refsBundle) — the single
        // source of truth. Do NOT restate visual rules here (no hardcoded base
        // colour / outfit / floating-UI / grade — that would duplicate + drift).
        // This prompt only frames the task + the video-specific deltas and defers
        // every creative/visual decision to the bundle. Reuse, don't recreate.
        $figureBlock = $allowFigure
            ? <<<'FIG'
2. Decide whether ONE iconic public figure strongly fits this topic (e.g. a Google product → Google's CEO; an OpenAI product → OpenAI's CEO; a company's tool → that company's well-known leader). If yes, set "figure_name" to that person's full real name AND make the hook an INTERACTION with them (creator on the LEFT, "the person matching reference image 2" on the RIGHT, spatially separated so faces don't blend). If no single figure clearly fits, set "figure_name" to null. NEVER write the figure's real name in scene_prompt — refer to them ONLY as "the person matching reference image 2".
FIG
            : <<<'NOFIG'
2. Set "figure_name" to null — creator only, no second person.
NOFIG;

        return <<<PROMPT
You are authoring the cover/HOOK keyframe image prompt for a short branded vertical video that OPENS an Instagram carousel about: "{$topic}". The image is animated by a video model, so describe ONE held moment, not motion.

Apply the hook + cover + creator + visual standards from your system prompt (the /carousel-gen knowledge) and choose the STRONGEST hook approach for THIS specific topic. Think creatively per topic — do not fall back on one fixed formula.

Hard constraints for THIS medium (these override nothing in the standard, they just scope it):
- 9:16 vertical, photorealistic, 4K, sharp focus. No on-image text, no captions, no watermark.
- CURIOSITY GAP — this carousel reveals a LIST of items across the later slides. The hook MUST NOT reveal, enumerate, or display those specific items (no row of tool cards / logos spelling out the list). Showing everything up front kills the reason to keep swiping — tease and withhold instead.
- Scroll-stopping and a little eccentric — a genuine pattern interrupt.
- ~70-130 words, one descriptive paragraph.

Steps:
1. Read the topic.
{$figureBlock}
3. Author "scene_prompt" per the standard + the constraints above.

STRICT JSON OUTPUT — parsed by a machine, not a human:
- Output ONE compact JSON object only. No markdown fences, no preamble, no trailing prose.
- Escape EVERY double-quote inside a string value as \".
- The figure's real NAME must appear ONLY in "figure_name", NEVER inside "scene_prompt".

Return ONE JSON object with exactly this shape:
{
  "figure_name": "Full Name or null",
  "scene_prompt": "..."
}
PROMPT;
    }
}
