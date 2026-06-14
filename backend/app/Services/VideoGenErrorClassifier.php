<?php

namespace App\Services;

/**
 * Part A1 — deterministic, LLM-free classifier for GeminiGen asset-generation
 * failures (keyframe image + Veo I2V). The class drives error-aware retry
 * degradation in PollRebrandAssets::recover (audio_filtered → barer audio bed,
 * prominent_people → drop the figure ref, content_policy → static scene fallback,
 * transient → retry the SAME prompt, unknown → escalate-simplify).
 *
 * Pure substring matching on the lowercased GeminiGen error_message / error_code
 * — same gate philosophy as ImageGenerationService::isSafetyError. NO HTTP, NO DB,
 * NO model call: this runs in the hot retry path which is deliberately LLM-free.
 */
class VideoGenErrorClassifier
{
    public const AUDIO_FILTERED = 'audio_filtered';
    public const PROMINENT_PEOPLE = 'prominent_people';
    public const CONTENT_POLICY = 'content_policy';
    public const TRANSIENT = 'transient';
    public const UNKNOWN = 'unknown';

    /** Named-public-figure / unsafe-upload refusal (has a dedicated figure-drop recovery). */
    private const PROMINENT_PEOPLE_PATTERNS = [
        'public_error_prominent_people_upload',
        'public_error_prominent_people',
        'public_error_minor',
        'prominent people',
        'prominent person',
        'do not allow uploading images',
    ];

    /** Veo audio model rejected the track (PUBLIC_ERROR_AUDIO_FILTERED). */
    private const AUDIO_PATTERNS = [
        'public_error_audio_filtered',
        'audio_filtered',
        'audio generation failed',
        'audio filtered',
    ];

    /** Generic safety / content-policy refusal on the scene (not the figure). */
    private const CONTENT_POLICY_PATTERNS = [
        'public_error_unsafe',
        'unsafe content',
        'sexual content',
        'safety filter',
        'content policy',
        'violates',
        'blocked by safety',
    ];

    /**
     * Infrastructure / timeout / poll failures — NOT a prompt fault, so the
     * retry should reuse the same prompt rather than degrade it.
     */
    private const TRANSIENT_PATTERNS = [
        'poll failed',
        'stuck',
        'timed out',
        'timeout',
        'connection',
        'download/crop',
        'download failed',
        'service unavailable',
        'curl error',
    ];

    public function classify(?string $reason): string
    {
        $needle = strtolower(trim((string) $reason));
        if ($needle === '') {
            // No error string = nothing prompt-specific to fix → treat as transient.
            return self::TRANSIENT;
        }

        // Order matters: prominent_people is the most specific (dedicated recovery)
        // and must win even when the message also says "content policy".
        if ($this->matchesAny($needle, self::PROMINENT_PEOPLE_PATTERNS)) {
            return self::PROMINENT_PEOPLE;
        }
        if ($this->matchesAny($needle, self::AUDIO_PATTERNS)) {
            return self::AUDIO_FILTERED;
        }
        if ($this->matchesAny($needle, self::CONTENT_POLICY_PATTERNS)) {
            return self::CONTENT_POLICY;
        }
        if ($this->matchesAny($needle, self::TRANSIENT_PATTERNS)) {
            return self::TRANSIENT;
        }

        return self::UNKNOWN;
    }

    /**
     * @param  array<int,string>  $patterns
     */
    private function matchesAny(string $needle, array $patterns): bool
    {
        foreach ($patterns as $p) {
            if (str_contains($needle, $p)) {
                return true;
            }
        }

        return false;
    }
}
