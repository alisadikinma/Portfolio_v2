<?php

/*
|--------------------------------------------------------------------------
| GeminiGen Circuit Breaker
|--------------------------------------------------------------------------
|
| Service-level outage detection for the original GeminiGen.ai account
| (blog images + LinkedIn carousel slides path). Sits in front of
| Http::post() calls in:
|   - App\Services\ImageGenerationService::triggerForIdea()
|   - App\Services\LinkedInCarouselImageService::dispatch*()
|   - App\Jobs\RetryImageSegmentJob::handle()
|
| State machine: closed → open (after N consec failures in window)
| → half_open (after canary probe success) → closed (after real dispatch
| success). State stored in Laravel Cache facade (file driver — no Redis
| dependency).
|
| Design + plan: docs/plans/2026-05-15-geminigen-circuit-breaker.md
|
*/

return [

    'failure_threshold' => (int) env('GEMINIGEN_CIRCUIT_FAILURE_THRESHOLD', 5),

    'window_seconds' => (int) env('GEMINIGEN_CIRCUIT_WINDOW_SECONDS', 600),

    'probe_interval_seconds' => (int) env('GEMINIGEN_CIRCUIT_PROBE_INTERVAL_SECONDS', 300),

    'state_ttl_seconds' => (int) env('GEMINIGEN_CIRCUIT_STATE_TTL_SECONDS', 3600),

    'canary_prompt' => env('GEMINIGEN_CIRCUIT_CANARY_PROMPT', 'circuit probe — solid color square'),

];
