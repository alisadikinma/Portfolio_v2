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

    // While the circuit is OPEN (GeminiGen overloaded / high traffic), a
    // segment auto-retry parks this many minutes instead of hammering — the
    // retry_count is NOT burned, it just holds until the server recovers.
    'open_retry_defer_minutes' => (int) env('GEMINIGEN_CIRCUIT_OPEN_RETRY_DEFER_MINUTES', 15),

    'state_ttl_seconds' => (int) env('GEMINIGEN_CIRCUIT_STATE_TTL_SECONDS', 3600),

    'canary_prompt' => env('GEMINIGEN_CIRCUIT_CANARY_PROMPT', 'circuit probe — solid color square'),

    // Phase M — Firecrawl status-page accelerator (early trip at failure #3)
    'firecrawl_api_key' => env('FIRECRAWL_API_KEY'),
    'firecrawl_base_url' => env('FIRECRAWL_BASE_URL', 'https://api.firecrawl.dev/v2'),
    'status_page_url' => env('GEMINIGEN_STATUS_PAGE_URL', 'https://geminigen.ai/status'),
    'status_model_name' => env('GEMINIGEN_STATUS_MODEL_NAME', 'Nano Banana Pro'),
    'status_cache_seconds' => (int) env('GEMINIGEN_STATUS_CACHE_SECONDS', 300),

];
