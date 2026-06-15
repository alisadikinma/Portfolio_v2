<?php

/**
 * Cross-post pipeline configuration (May 8, 2026).
 *
 * Drives Facebook + Instagram + TikTok draft generation services that fan
 * LinkedIn posts (text + carousel) out to Publer. Slides are reused 1:1
 * from the existing LinkedIn pipeline; FB text-format reuses LinkedIn
 * content directly. Per-platform caption authoring via the new
 * `social-short-form-writer` plugin (separate repo at
 * D:\Projects\claude-plugin\social-short-form-writer).
 *
 * Two infrastructure sections:
 *   - generation: SSH bridge to claude CLI for /instagram-gen + /tiktok-gen
 *     skills (mirrors config/linkedin.php generation pattern)
 *   - publer: REST API integration (auth + base URL + rate limit awareness)
 *
 * Operator-facing flags live in DB `settings` table (group=publer) and are
 * read at runtime by PublerConfigProvider — encrypted api_key, account IDs,
 * master enable toggle. This file holds infrastructure-only knobs.
 */
return [

    /*
    |--------------------------------------------------------------------------
    | Plugin generation bridge (caption authoring)
    |--------------------------------------------------------------------------
    |
    | SSH into VPS claudesn user to run /instagram-gen + /tiktok-gen plugin
    | skills. Mirrors LinkedIn pipeline pattern (config/linkedin.php). Same
    | claudesn-owned SSH key (queue worker context, NOT www-data).
    |
    */
    'generation' => [
        'driver' => env('SOCIAL_GEN_DRIVER', 'ssh'), // 'ssh' or 'local'
        'ssh_host' => env('SOCIAL_GEN_SSH_HOST', 'localhost'),
        'ssh_user' => env('SOCIAL_GEN_SSH_USER', 'claudesn'),
        // Queue worker context — claudesn user — needs claudesn-owned key.
        // SSH key gotcha documented in CLAUDE.md April 29 entry.
        'ssh_key' => env('SOCIAL_GEN_SSH_KEY', '/home/claudesn/.ssh/id_ed25519'),
        'claude_path' => env('SOCIAL_GEN_CLAUDE_PATH', 'claude'),
        'model' => env('SOCIAL_GEN_MODEL', 'sonnet'),
        // 2 compiled reference bundles produced by the plugin's compile-refs.ts
        // and deployed to VPS at these paths. Plugin tracks 3 RAG dirs internally
        // (social-base + instagram-playbook + tiktok-playbook), bundles into 2
        // output files. FB carousel reuses /instagram-gen output (no separate
        // refs file).
        'refs_instagram' => env('SOCIAL_GEN_REFS_INSTAGRAM', '/home/claudesn/refs-instagram.md'),
        'refs_tiktok' => env('SOCIAL_GEN_REFS_TIKTOK', '/home/claudesn/refs-tiktok.md'),
        // refs_threads — May 10, 2026 Tier-1 upgrade. /threads-gen plugin
        // (~21KB compiled bundle) lives alongside IG + TikTok refs on VPS.
        'refs_threads' => env('SOCIAL_GEN_REFS_THREADS', '/home/claudesn/refs-threads.md'),
        // Empty MCP config — same protective override as LinkedIn pipeline
        // (CLAUDE.md April 29 entry). Without this, every `claude -p`
        // invocation spawns the operator's full MCP stack and obsidian-mcp
        // leaks its child node process. Set to empty string to disable
        // (dev-only fallback).
        'empty_mcp_config' => env('SOCIAL_GEN_EMPTY_MCP_CONFIG', '/home/claudesn/empty-mcp.json'),
        // Sync execution timeout. IG + TikTok plugins are text-only (no
        // carousel slide rendering, no /carousel-gen sub-dispatch) so they
        // run faster than LinkedIn — 300s is generous.
        'timeout_seconds' => (int) env('SOCIAL_GEN_TIMEOUT_SECONDS', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Publer REST API integration
    |--------------------------------------------------------------------------
    |
    | Operator-rotatable api_key + account IDs live in DB `settings` table
    | (group=publer) — see PublerSettingsSeeder. This config holds only
    | infrastructure-level knobs (base URL, rate limits, retry policy).
    |
    | Auth header format is `Authorization: Bearer-API {token}` — note the
    | non-standard `Bearer-API` prefix per Publer docs (NOT plain `Bearer`).
    |
    */
    'publer' => [
        // Master gate for the Publer publish path. Phase E Approve action
        // returns 503 when this is false so drafts don't get stuck in
        // `publishing` while Phase H+ (real Publer transport) is pending.
        // Flip to true once PublishViaPubler stub is replaced with the
        // real createPost + pollJob loop.
        'enabled' => env('PUBLER_PUBLISH_ENABLED', false),
        'base_url' => env('PUBLER_BASE_URL', 'https://app.publer.com'),
        'api_path' => env('PUBLER_API_PATH', '/api/v1'),
        // Documented rate limit: 100 requests / 2 minutes per user. Our
        // expected volume (~10-50 posts/month × 3 platforms = ~150 publish
        // calls/month) is well under, but PollPublerJobs running every
        // minute against many publishing rows could approach it.
        'rate_limit_per_2min' => 100,
        // HTTP retry policy for transient failures (5xx, network).
        // PublerClient applies these via Laravel HTTP retry().
        'max_retries' => (int) env('PUBLER_MAX_RETRIES', 3),
        'retry_backoff_ms' => (int) env('PUBLER_RETRY_BACKOFF_MS', 500),
        // Media ingest (POST /media/from-url) is async on Publer's side: we poll
        // /job_status until 'complete'. A multi-slide carousel can take >18s to
        // ingest the tail slides, so the old hardcoded 12-poll (~18s) window
        // failed IG on jobs that actually completed moments later. Tunable.
        'media_poll_tries' => (int) env('PUBLER_MEDIA_POLL_TRIES', 40),
        'media_poll_interval_ms' => (int) env('PUBLER_MEDIA_POLL_INTERVAL_MS', 1500),
        // Max time the synchronous PublerClient methods wait before bailing.
        // Most calls resolve in <2s; createPost can take longer (Publer
        // queues the job internally). Increase only after observing P99 latency.
        'http_timeout_seconds' => (int) env('PUBLER_HTTP_TIMEOUT_SECONDS', 30),
    ],

    // Zernio publisher (primary for IG/TikTok/Threads since 2026-06-15).
    // Unlike Publer, Zernio takes PUBLIC CDN media URLs directly in createPost
    // (mediaItems[]), so there is NO async media-ingest/poll step — hence no
    // media_poll_* keys here. Two workspace API keys live in the `zernio`
    // settings group (zernio_api_key_igtt for IG+TikTok, zernio_api_key_threads
    // for Threads), selected per-platform by ZernioClient::forPlatform().
    'zernio' => [
        // Master gate for the Zernio publish path (mirror of publer.enabled).
        'enabled' => env('ZERNIO_PUBLISH_ENABLED', false),
        'base_url' => env('ZERNIO_BASE_URL', 'https://zernio.com'),
        'api_path' => env('ZERNIO_API_PATH', '/api/v1'),
        // HTTP retry policy for transient failures (5xx, network), applied via
        // Laravel HTTP retry() in ZernioClient.
        'max_retries' => (int) env('ZERNIO_MAX_RETRIES', 3),
        'retry_backoff_ms' => (int) env('ZERNIO_RETRY_BACKOFF_MS', 500),
        // createPost with publishNow returns synchronously (no Publer-style
        // job poll), but a multi-slide carousel ingest server-side can take a
        // few seconds — generous ceiling, well under the 900s worker cap.
        'http_timeout_seconds' => (int) env('ZERNIO_HTTP_TIMEOUT_SECONDS', 60),
        // When true, a sibling with scheduled_at set publishes via Zernio
        // scheduledFor+timezone instead of publishNow. Off → always publishNow.
        'schedule_enabled' => env('ZERNIO_SCHEDULE_ENABLED', true),
    ],

];
