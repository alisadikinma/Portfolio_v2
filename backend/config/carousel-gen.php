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

    'driver' => env('CAROUSEL_GEN_DRIVER', 'ssh'),

    'ssh_host' => env('CAROUSEL_GEN_SSH_HOST', 'localhost'),
    'ssh_user' => env('CAROUSEL_GEN_SSH_USER', 'claudesn'),
    'ssh_key'  => env('CAROUSEL_GEN_SSH_KEY', '/var/www/.ssh/id_ed25519'),

    'claude_path' => env('CAROUSEL_GEN_CLAUDE_PATH', 'claude'),
    'model'       => env('CAROUSEL_GEN_MODEL', 'sonnet'),

    'refs_pipeline' => env('CAROUSEL_GEN_REFS_PIPELINE', '/home/claudesn/refs-carousel-gen-pipeline.md'),

    'timeout_seconds' => (int) env('CAROUSEL_GEN_TIMEOUT_SECONDS', 300),
];
