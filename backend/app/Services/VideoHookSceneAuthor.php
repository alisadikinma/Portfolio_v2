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
            (string) config('services.repurpose.refs_hook', '')
        );
    }

    private function buildPrompt(string $topic, bool $allowFigure): string
    {
        $figureBlock = $allowFigure
            ? <<<'FIG'
2. Decide whether ONE iconic public figure strongly fits this topic (e.g. a Google product → Google's CEO; an OpenAI product → OpenAI's CEO; a specific company's tool → that company's well-known leader). If yes, set "figure_name" to that person's full real name. If no single figure clearly fits, set "figure_name" to null.
3. Author "scene_prompt" on the Spotlight Portrait standard below. If a figure was chosen, place the CREATOR on the LEFT and "the person matching reference image 2" on the RIGHT, both on the signature-blue base, with the floating topic UI elements between/around them. NEVER write the figure's name in scene_prompt — refer to them ONLY as "the person matching reference image 2". If no figure, author a creator-only Spotlight Portrait.
FIG
            : <<<'NOFIG'
2. Set "figure_name" to null (no second person).
3. Author "scene_prompt" on the Spotlight Portrait standard below, featuring ONLY the creator. Do NOT include any other person.
NOFIG;

        return <<<PROMPT
You are authoring the cover/HOOK keyframe image prompt for a short branded vertical video that opens an Instagram carousel about: "{$topic}". The image is animated by a video model, so describe ONE held moment, not motion.

This hook MUST stop the scroll — it is the single most important frame. The drama comes from a bold, striking composition + topic-evocative floating UI, NOT from a costume change.

Follow the v3 "Spotlight Portrait" standard EXACTLY (the /carousel-gen cover look, at 9:16 vertical):
- Solid signature-blue (#0F59B6) studio background — one clean solid base, no busy environment.
- The creator is a calm, confident, credible subject, slightly off-center (rule of thirds), a scroll-stopping composition.
- Signature outfit on EVERY topic: a dark tee or henley under an unstructured blazer, neutral slate/charcoal/muted-navy tone. NEVER change the outfit to suit the topic.
- THREE OR MORE floating topic UI elements (sleek app cards, real tool logos, product screenshots, holographic dashboards relevant to "{$topic}") hover around the subject with soft glow and gentle depth blur. The TOPIC is conveyed by these floating elements, never by costume or a literal location.
- Lighting/grade: cool-neutral ~5200K key + soft blue ambient bounce + a warm gold rim light. No warm-amber wash.
- Hyperrealistic, anti-AI-look: visible skin pores, a few stray hairs catching light, natural fabric creases, subtle lens vignetting, slight asymmetry.

Steps:
1. Read the topic.
{$figureBlock}

Rules for scene_prompt:
- 9:16 vertical, photorealistic, sharp focus, 4K. No on-image text, no logos baked as captions, no watermark.
- Keep it ~70-130 words, one descriptive paragraph.
- Name 2-4 CONCRETE floating UI elements that evoke "{$topic}" specifically (e.g. for an AI design tool: a floating layout canvas, a component library card, a generated-mockup screenshot).
- Spatially separate the two people when a figure is used ("creator on the left ... the person matching reference image 2 on the right") so their faces do not blend.

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
