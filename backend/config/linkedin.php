<?php

/**
 * LinkedIn admin UI configuration. Per plugin Addendum 3 §13.4.
 *
 * Operator-facing flags live in the DB `settings` table (group=linkedin)
 * and are read via Setting::get(). Infrastructure-only knobs (SSH, API
 * endpoints, OAuth app credentials) stay in env vars.
 */
return [
    // OAuth app credentials — register at https://linkedin.com/developers
    'oauth' => [
        'client_id' => env('LINKEDIN_OAUTH_CLIENT_ID'),
        'client_secret' => env('LINKEDIN_OAUTH_CLIENT_SECRET'),
        'redirect_uri' => env(
            'LINKEDIN_OAUTH_REDIRECT_URI',
            env('APP_URL') . '/api/admin/linkedin/oauth/callback'
        ),
        'scopes' => env(
            'LINKEDIN_OAUTH_SCOPES',
            'w_member_social,r_liteprofile'
        ),
        'authorize_url' => 'https://www.linkedin.com/oauth/v2/authorization',
        'token_url' => 'https://www.linkedin.com/oauth/v2/accessToken',
    ],

    // LinkedIn API endpoints
    'api' => [
        'base_url' => env('LINKEDIN_API_BASE_URL', 'https://api.linkedin.com/v2'),
        'version' => env('LINKEDIN_API_VERSION', '202405'),
        'me_endpoint' => '/me',
        'ugc_posts_endpoint' => '/ugcPosts',
        'assets_endpoint' => '/assets',
    ],

    // Operator defaults — DB settings override these at runtime
    'depth_score_threshold' => env('LINKEDIN_DEPTH_SCORE_THRESHOLD', 80),
    'cancel_window_minutes' => env('LINKEDIN_CANCEL_WINDOW_MINUTES', 15),
    'auto_publish' => env('LINKEDIN_AUTO_PUBLISH', false),

    // Token refresh cron
    'token_refresh_cron' => env('LINKEDIN_TOKEN_REFRESH_CRON', '0 3 * * *'),
    'token_refresh_threshold_days' => env('LINKEDIN_TOKEN_REFRESH_THRESHOLD_DAYS', 7),

    // First-comment automation
    'first_comment_enabled' => env('LINKEDIN_FIRST_COMMENT_ENABLED', true),
    'first_comment_delay_seconds' => env('LINKEDIN_FIRST_COMMENT_DELAY_SECONDS', 30),

    // PDF temp dir for carousel composition
    'pdf_temp_dir' => env('LINKEDIN_PDF_TEMP_DIR', storage_path('app/tmp/linkedin-pdfs')),

    // Generation bridge — SSH to VPS `claudesn` user + run `claude -p "/linkedin-gen ..."`.
    // Same pattern as article generation (see config/services.php article_generation).
    // Plugin v0.5.0+ at https://github.com/alisadikinma/linkedin-post-writer.
    //
    // Post plugin v0.5.0 the orchestrator authors text only; carousel format
    // emits status=route_to_carousel_gen and the LinkedInGenerationService
    // dispatches /carousel-gen separately (see config/carousel-gen.php).
    // No feature flag — /carousel-gen is the only carousel path.
    'generation' => [
        'driver' => env('LINKEDIN_GEN_DRIVER', 'ssh'), // 'ssh' or 'local'
        'ssh_host' => env('LINKEDIN_GEN_SSH_HOST', 'localhost'),
        'ssh_user' => env('LINKEDIN_GEN_SSH_USER', 'claudesn'),
        'ssh_key' => env('LINKEDIN_GEN_SSH_KEY', '/var/www/.ssh/id_ed25519'),
        'claude_path' => env('LINKEDIN_GEN_CLAUDE_PATH', 'claude'),
        'model' => env('LINKEDIN_GEN_MODEL', 'sonnet'),
        // 3 compiled reference bundles for /linkedin-gen text path (post v0.5.0).
        // refs_carousel retired — carousel design specs live in /carousel-gen plugin.
        'refs_playbook' => env('LINKEDIN_GEN_REFS_PLAYBOOK', '/home/claudesn/refs-linkedin-playbook.md'),
        'refs_templates' => env('LINKEDIN_GEN_REFS_TEMPLATES', '/home/claudesn/refs-linkedin-templates.md'),
        'refs_formats' => env('LINKEDIN_GEN_REFS_FORMATS', '/home/claudesn/refs-linkedin-formats.md'),
        // Sync execution timeout for the SSH→Sonnet→plugin chain. Production
        // measurement on a real blog (post #24, ~8KB content, 4 system prompt
        // refs, carousel format) clocked at 369s wall — the prior 300s default
        // caused job timeout, orphan SSH, and runaway retries. 600s gives
        // ~62% headroom over observed worst case while staying well clear of
        // PHP-FPM and queue retry_after ceilings.
        'timeout_seconds' => (int) env('LINKEDIN_GEN_TIMEOUT_SECONDS', 600),
    ],
];
