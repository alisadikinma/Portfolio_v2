<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Carousel Gen Plugin Bridge
    |--------------------------------------------------------------------------
    |
    | SSH bridge to invoke the `/carousel-gen` skill from the
    | `ai-image-carousel-prompt-gen` plugin on the VPS Claude CLI. Mirrors the
    | LINKEDIN_GEN_* and ARTICLE_GEN_* config patterns.
    |
    | Used by Phase A6's LinkedInGenerationService router when format=carousel
    | and feature flag LINKEDIN_USE_CAROUSEL_GEN_ENGINE=true.
    |
    */

    'driver' => env('CAROUSEL_GEN_DRIVER', 'ssh'), // 'ssh' or 'local'

    'ssh_host' => env('CAROUSEL_GEN_SSH_HOST', 'localhost'),
    'ssh_user' => env('CAROUSEL_GEN_SSH_USER', 'claudesn'),
    'ssh_key'  => env('CAROUSEL_GEN_SSH_KEY', '/var/www/.ssh/id_ed25519'),

    'claude_path' => env('CAROUSEL_GEN_CLAUDE_PATH', 'claude'),
    'model'       => env('CAROUSEL_GEN_MODEL', 'sonnet'),

    // Path on VPS to the compiled refs bundle for /carousel-gen pipeline mode,
    // produced by ai-image-carousel-prompt-gen/scripts/compile-refs.ts (Phase A3).
    'refs_pipeline' => env('CAROUSEL_GEN_REFS_PIPELINE', '/home/claudesn/refs-carousel-gen-pipeline.md'),

    // Sync execution timeout for the SSH→Sonnet→plugin chain. Default mirrors
    // the production-validated LINKEDIN_GEN_TIMEOUT_SECONDS=600 — the
    // /linkedin-carousel skill that this engine replaces measured 369s wall
    // on a real blog (post #24, ~8KB content, 4 system prompt refs, carousel
    // format), and an earlier 300s default caused job timeout, orphan SSH,
    // and runaway retries. /carousel-gen produces a comparable workload
    // (full carousel authoring + cinematic image_prompts), so we adopt the
    // same 600s headroom from day one rather than re-discover the same
    // production failure mode. Operators can override per-environment via
    // CAROUSEL_GEN_TIMEOUT_SECONDS env var if measured wall time differs.
    'timeout_seconds' => (int) env('CAROUSEL_GEN_TIMEOUT_SECONDS', 600),
];
