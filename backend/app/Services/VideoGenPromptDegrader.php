<?php

namespace App\Services;

/**
 * Part A4 — static, LLM-free per-retry prompt degradation for the video_rebrand
 * Veo bookend re-dispatch (PollRebrandAssets::recoverJob). Reads the classified
 * last_error_class (A1/A2) and tweaks the prompt so a recurring GeminiGen error
 * stops recurring within the 3-retry budget — WITHOUT an LLM call (the asset
 * pipeline is deliberately LLM-free; the durable rule-hardening loop is Part B).
 *
 * Degradation map:
 *   - audio_filtered → swap the multi-clause Audio: bed for a barer single
 *     positive-ambiance one + drop the Ambient: UI-drift clause (Veo's mandatory
 *     audio model over-reacts to a busy/over-negated bed → PUBLIC_ERROR_AUDIO_FILTERED).
 *   - content_policy / prominent_people → handled on the KEYFRAME side
 *     (figure_dropped / static-scene fallback in GenerateRebrandAssets), so the
 *     Veo prompt is left intact here.
 *   - transient → unchanged (infra/timeout, not a prompt fault).
 *   - unknown → escalate-simplify only on the LAST retry.
 * On retry 3 (any non-transient class) motion is reduced toward near-still — the
 * cheapest way to make a stubborn clip pass.
 */
class VideoGenPromptDegrader
{
    public function degradeVeo(string $base, string $errorClass, int $retryN): string
    {
        $out = $base;

        if ($errorClass === VideoGenErrorClassifier::AUDIO_FILTERED) {
            $out = $this->simplifyAudio($out);
        }

        // transient / content_policy / prominent_people: Veo prompt unchanged here.

        // Last-ditch on the final retry for any class that did mutate (i.e. not a
        // plain transient retry): calm the motion so the render is trivial to pass.
        if ($retryN >= 3 && $errorClass !== VideoGenErrorClassifier::TRANSIENT) {
            $out = $this->reduceMotion($out);
        }

        $learned = $this->activeLearnedConstraints('veo');

        return $learned === '' ? $out : $out.' '.$learned;
    }

    /**
     * Replace the busy Audio: clause with a barer single positive-ambiance bed and
     * drop the Ambient: line (one fewer thing for the audio model to react to).
     */
    private function simplifyAudio(string $prompt): string
    {
        // Drop the "Ambient: …" clause up to the next sentence boundary.
        $prompt = preg_replace('/Ambient:[^.]*\.\s*/', '', $prompt) ?? $prompt;
        // Swap the "Audio: …" clause for a minimal one.
        $prompt = preg_replace(
            '/Audio:[^.]*\./',
            'Audio: soft neutral room tone only, no music.',
            $prompt
        ) ?? $prompt;

        return $prompt;
    }

    private function reduceMotion(string $prompt): string
    {
        return rtrim($prompt).' Near-still: minimal motion, only faint breathing and a single slow blink, everything else static.';
    }

    /**
     * Part B append point — DB-backed learned-constraints overlay. Returns '' until
     * the auto-learning loop ships; documented as the follow-up phase. Scope is the
     * generation stage (e.g. 'veo' | 'scene' | 'all').
     */
    public function activeLearnedConstraints(string $scope): string
    {
        // LEARNED-CONSTRAINTS APPEND POINT (Part B) — no overlay yet.
        return '';
    }
}
