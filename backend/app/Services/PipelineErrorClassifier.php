<?php

namespace App\Services;

use App\Enums\PipelineErrorClass;

/**
 * Pure-function classifier mapping a pipeline error string into a
 * `PipelineErrorClass` value. No DB, no logging, no static state — safe
 * to instantiate freely; safe under concurrent calls.
 *
 * Matching is ordered substring-based with priority:
 *   1. Specific GeminiGen PUBLIC_ERROR_* codes (most specific wins)
 *   2. Brand-specific signals (logo / trademark / copyright tokens)
 *   3. Free-text safety phrases (prominent people, minors, etc.)
 *   4. Deterministic LLM patterns (parse fail, schema, truncation)
 *   5. Transient infra patterns (network, timeout, queue worker)
 *   6. Permanent validation patterns (depth score, threshold)
 *   7. Unknown (fallback)
 *
 * Empty / null / whitespace input → Unknown (operator review).
 */
class PipelineErrorClassifier
{
    public function classify(?string $error): PipelineErrorClass
    {
        if ($error === null || trim($error) === '') {
            return PipelineErrorClass::Unknown;
        }

        $needle = strtolower($error);

        // 1. Specific GeminiGen PUBLIC_ERROR_* codes — most specific signal.
        if (str_contains($needle, 'public_error_prominent_people_upload')
            || str_contains($needle, 'public_error_prominent_people')) {
            return PipelineErrorClass::PolicyPerson;
        }
        if (str_contains($needle, 'public_error_minor')) {
            return PipelineErrorClass::PolicyMinor;
        }
        if (str_contains($needle, 'public_error_unsafe')
            || str_contains($needle, 'public_error_sexual')) {
            return PipelineErrorClass::PolicyNsfw;
        }

        // 2. Brand-specific tokens. Co-occurrence requirement (logo / trademark /
        //    copyright) avoids false positives on the bare word "brand".
        if (str_contains($needle, 'logo')
            || str_contains($needle, 'trademark')
            || str_contains($needle, 'copyright')) {
            return PipelineErrorClass::PolicyBrand;
        }

        // 3. Free-text safety phrases.
        if (str_contains($needle, 'prominent people')
            || str_contains($needle, 'prominent person')) {
            return PipelineErrorClass::PolicyPerson;
        }
        if (str_contains($needle, 'minors')) {
            return PipelineErrorClass::PolicyMinor;
        }
        if (str_contains($needle, 'unsafe content')
            || str_contains($needle, 'sexual content')) {
            return PipelineErrorClass::PolicyNsfw;
        }
        if (str_contains($needle, 'safety filter')
            || str_contains($needle, 'content policy')
            || str_contains($needle, 'do not allow uploading images')) {
            return PipelineErrorClass::PolicyGeneric;
        }

        // 4. Deterministic LLM failures — output truncation, JSON / schema fails.
        if (str_contains($needle, 'could not parse orchestrator json')
            || str_contains($needle, 'json parse failed')
            || str_contains($needle, 'schema validation failed')
            || str_contains($needle, 'sonnet output truncation')
            || str_contains($needle, 'output cap exceeded')
            || str_contains($needle, 'zod')) {
            return PipelineErrorClass::DeterministicLlm;
        }

        // 5. Transient infra failures — worth retrying automatically.
        //    Includes reaper-stuck signals (linkedin:reap-stuck cron marks
        //    drafts that exceeded the SSH+job timeout budget — usually means
        //    queue worker died mid-job or claude CLI hung; same prompt may
        //    succeed on a fresh worker).
        if (str_contains($needle, 'connection timed out')
            || str_contains($needle, 'connect to host')
            || str_contains($needle, 'ssh timeout')
            || str_contains($needle, 'network error')
            || str_contains($needle, 'connection refused')
            || str_contains($needle, '502 bad gateway')
            || str_contains($needle, '503 service unavailable')
            || str_contains($needle, '504 gateway timeout')
            || str_contains($needle, 'maxattemptsexceededexception')
            || str_contains($needle, 'queue worker')
            || str_contains($needle, 'reaper:')
            || str_contains($needle, 'stuck in ')
            || str_contains($needle, 'process timed out')
            || str_contains($needle, 'broken pipe')) {
            return PipelineErrorClass::Transient;
        }

        // 6. Permanent failures — won't succeed on retry, surface to operator.
        if (str_contains($needle, 'validation rejected')
            || str_contains($needle, 'depth score')
            || str_contains($needle, 'below threshold')) {
            return PipelineErrorClass::Permanent;
        }

        return PipelineErrorClass::Unknown;
    }
}
