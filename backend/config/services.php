<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'geminigen' => [
        'api_key' => env('GEMINIGEN_API_KEY', ''),
        // GROK hook-video (IG mixed carousel). Same key + base URL as the
        // image endpoint; grok-3 maps server-side to model_name=grok-video.
        'video_model' => env('GEMINIGEN_VIDEO_MODEL', 'grok-3'),
        // Veo image-to-video for video_rebrand hook/CTA clips (9:16, face-gen
        // keyframe via mode_image=frame). veo-3.1-fast = speed tier. Endpoint
        // /video-gen/veo verified live 2026-06-12.
        'veo_model' => env('GEMINIGEN_VEO_MODEL', 'veo-3.1-fast'),
        // ffmpeg binary on the queue-worker host (flatten PNG→JPG + pad 4:5→2:3
        // before dispatch, crop 2:3→4:5 + strip audio on download).
        'ffmpeg_path' => env('FFMPEG_PATH', 'ffmpeg'),
    ],

    'geoip' => [
        // Provider used by SetLocaleByGeoIP when CF-IPCountry header is absent.
        // Currently 'ip-api' (only one supported). Set to anything else to
        // disable the fallback entirely (locale defaults to 'en').
        'fallback' => env('GEOIP_FALLBACK_PROVIDER', 'ip-api'),
        // HTTP timeout in seconds for the fallback lookup. Kept short — the
        // middleware fails-open on timeout so latency on the first request
        // is bounded.
        'timeout' => (int) env('GEOIP_FALLBACK_TIMEOUT', 2),
    ],

    'pexels' => [
        'api_key' => env('PEXELS_API_KEY', ''),
    ],

    'unsplash' => [
        'access_key' => env('UNSPLASH_ACCESS_KEY', ''),
    ],

    'content_engine' => [
        'url' => env('CONTENT_ENGINE_URL', 'http://127.0.0.1:8100'),
        'api_key' => env('CONTENT_ENGINE_API_KEY', ''),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        'enabled' => env('TELEGRAM_ENABLED', true),
    ],

    'instagram_capture' => [
        // IG repurpose Phase B — Playwright headless capture of a source post's
        // slides + caption. 'ssh' runs the Node script on the VPS (queue-worker
        // context = claudesn, mirroring CAROUSEL_GEN_* key path); 'local' runs
        // it on the dev box. See docs/runbooks/repurpose-ig-carousel-deploy.md.
        'driver' => env('IG_CAPTURE_DRIVER', 'ssh'),
        'ssh_host' => env('IG_CAPTURE_SSH_HOST', 'localhost'),
        'ssh_user' => env('IG_CAPTURE_SSH_USER', 'claudesn'),
        'ssh_key' => env('IG_CAPTURE_SSH_KEY', '/home/claudesn/.ssh/id_ed25519'),
        'node_path' => env('IG_CAPTURE_NODE_PATH', 'node'),
        // Repo-root scripts/ (one level above the Laravel base_path = backend/).
        // Override on the VPS with an absolute path if the layout differs.
        'script_path' => env('IG_CAPTURE_SCRIPT_PATH', dirname(base_path()) . '/scripts/playwright/ig-capture.cjs'),
        // Optional authenticated Playwright storageState JSON (cookies) to get
        // past the IG login wall on full carousels. Empty = anonymous capture
        // (public posts mostly work; private/wall → job fails with clear reason).
        'storage_state_path' => env('IG_CAPTURE_STORAGE_STATE', ''),
        'timeout' => (int) env('IG_CAPTURE_TIMEOUT', 120),

        // video_rebrand Phase B — yt-dlp video carousel capture (separate node
        // wrapper, NOT the Playwright images path). yt-dlp downloads each video
        // slide headless (no login); ffmpeg extracts a poster; ffprobe probes
        // dimensions/audio. See docs/plans/2026-06-12-ig-video-carousel-rebrand.md.
        'video_script_path' => env('REPURPOSE_VIDEO_CAPTURE_SCRIPT', dirname(base_path()) . '/scripts/repurpose/ig-video-capture.cjs'),
        // Phase D — Playwright HTML→PNG brand chrome (header/footer) for tool slides.
        'chrome_script_path' => env('REPURPOSE_VIDEO_CHROME_SCRIPT', dirname(base_path()) . '/scripts/repurpose/video-chrome.cjs'),
        // people_spotlight — Playwright composite of real face cut-outs into a
        // carousel slide's reserved photo band.
        'person_strip_script_path' => env('CAROUSEL_PERSON_STRIP_SCRIPT', dirname(base_path()) . '/scripts/repurpose/carousel-person-strip.cjs'),
        'ytdlp_path' => env('REPURPOSE_YTDLP_PATH', 'yt-dlp'),
        'ffmpeg_path' => env('REPURPOSE_FFMPEG_PATH', 'ffmpeg'),
        'ffprobe_path' => env('REPURPOSE_FFPROBE_PATH', 'ffprobe'),
        // Netscape cookies.txt (exported from a logged-in IG browser session) so
        // yt-dlp can download carousel media — IG now returns "login required /
        // rate-limit" for anonymous media fetches. Empty = anonymous (metadata
        // only). On the VPS: /home/claudesn/ig-cookies.txt (mode 600, claudesn).
        'ytdlp_cookies_path' => env('REPURPOSE_YTDLP_COOKIES', ''),
        // Video downloads + poster extraction run longer than image scraping.
        'video_timeout' => (int) env('REPURPOSE_VIDEO_CAPTURE_TIMEOUT', 300),
    ],

    'repurpose' => [
        // IG repurpose Claude-CLI exec (vision extract / fact-check research /
        // rewrite). Defaults mirror article_generation so one VPS auth + key
        // path serves both. All claude calls MUST carry the empty-mcp flags
        // (anti MCP-leak — see ArticleGenerationService::buildMcpFlags).
        'driver' => env('REPURPOSE_DRIVER', env('ARTICLE_GEN_DRIVER', 'ssh')),
        'ssh_host' => env('REPURPOSE_SSH_HOST', env('ARTICLE_GEN_SSH_HOST', 'localhost')),
        'ssh_user' => env('REPURPOSE_SSH_USER', env('ARTICLE_GEN_SSH_USER', 'claudesn')),
        'ssh_key' => env('REPURPOSE_SSH_KEY', env('ARTICLE_GEN_SSH_KEY', '')),
        'claude_path' => env('REPURPOSE_CLAUDE_PATH', env('ARTICLE_GEN_CLAUDE_PATH', 'claude')),
        'empty_mcp_config' => env('REPURPOSE_EMPTY_MCP_CONFIG', env('ARTICLE_GEN_EMPTY_MCP_CONFIG', '/home/claudesn/empty-mcp.json')),
        'model_vision' => env('REPURPOSE_MODEL_VISION', 'sonnet'),
        'model_research' => env('REPURPOSE_MODEL_RESEARCH', 'sonnet'),
        'model_rewrite' => env('REPURPOSE_MODEL_REWRITE', 'sonnet'),
        // video_rebrand topic-aware HOOK/CTA keyframe author (#1). refs_hook is an
        // OPTIONAL override — when empty it falls back at runtime to the
        // /carousel-gen bundle (carousel-gen.refs_pipeline) so the author always
        // has the plugin's hook + creator + visual knowledge and stays one source
        // of truth with /carousel-gen. See VideoHookSceneAuthor::refsBundle().
        'model_hook_author' => env('REPURPOSE_MODEL_HOOK_AUTHOR', 'sonnet'),
        'refs_hook' => env('REPURPOSE_REFS_HOOK', ''),
        // video_rebrand bookend HOOK title bilingual localizer (ID primary + EN
        // companion) — RepurposeHookTitleResolver. Light text-only call, cached
        // onto the job after first run.
        'model_hook_translate' => env('REPURPOSE_MODEL_HOOK_TRANSLATE', 'sonnet'),
        // Style guide refs appended to the rewrite prompt (reuse article refs).
        'refs_rewrite' => env('REPURPOSE_REFS_REWRITE', env('ARTICLE_GEN_REFS_WRITE', '')),
        // CLI budget per attempt — matches carousel-gen (900s). Big IG carousels
        // (16-slide vision / full-article rewrite) overran the old 300s budget.
        // The repair-retry (RunsRepurposeClaudeCli::runRepurposeParsed) can fire a
        // 2nd attempt, so step-job $timeouts are sized at 1920s to cover both.
        'timeout' => (int) env('REPURPOSE_TIMEOUT', 900),
    ],

    'article_generation' => [
        'driver' => env('ARTICLE_GEN_DRIVER', 'ssh'),
        'ssh_host' => env('ARTICLE_GEN_SSH_HOST', ''),
        'ssh_user' => env('ARTICLE_GEN_SSH_USER', 'root'),
        'ssh_key' => env('ARTICLE_GEN_SSH_KEY', ''),
        'claude_path' => env('ARTICLE_GEN_CLAUDE_PATH', 'claude'),
        'api_url' => env('ARTICLE_GEN_API_URL', 'https://alisadikinma.com/api'),
        'api_token' => env('ARTICLE_GEN_API_TOKEN', ''),
        'refs_prep' => env('ARTICLE_GEN_REFS_PREP', ''),
        'refs_write' => env('ARTICLE_GEN_REFS_WRITE', ''),
        'refs_score' => env('ARTICLE_GEN_REFS_SCORE', ''),
        'refs_images' => env('ARTICLE_GEN_REFS_IMAGES', ''),
        'refs_translate' => env('ARTICLE_GEN_REFS_TRANSLATE', ''),
        'model_prep' => env('ARTICLE_GEN_MODEL_PREP', 'sonnet'),
        'model_write' => env('ARTICLE_GEN_MODEL_WRITE', 'sonnet'),
        'model_score' => env('ARTICLE_GEN_MODEL_SCORE', 'sonnet'),
        'model_images' => env('ARTICLE_GEN_MODEL_IMAGES', 'sonnet'),
        'model_translate' => env('ARTICLE_GEN_MODEL_TRANSLATE', 'sonnet'),
        'model_vd_rewrite' => env('ARTICLE_GEN_MODEL_VD_REWRITE', 'sonnet'),
        'model_research_deep' => env('ARTICLE_GEN_MODEL_RESEARCH_DEEP', 'opus'),
        'model_research_quick' => env('ARTICLE_GEN_MODEL_RESEARCH_QUICK', 'sonnet'),
        'model_strategy_outline' => env('ARTICLE_GEN_MODEL_STRATEGY_OUTLINE', 'sonnet'),
        'refs_research' => env('ARTICLE_GEN_REFS_RESEARCH', ''),
        'refs_strategy_outline' => env('ARTICLE_GEN_REFS_STRATEGY_OUTLINE', ''),
        'skill_split_enabled' => env('ARTICLE_GEN_SKILL_SPLIT_ENABLED', false),
        'deep_research_enabled' => env('ARTICLE_GEN_DEEP_RESEARCH_ENABLED', true),
        'use_images_phase' => env('ARTICLE_GEN_USE_IMAGES_PHASE', false),
        'use_translate_phase' => env('ARTICLE_GEN_USE_TRANSLATE_PHASE', false),
        'use_score_phase' => env('ARTICLE_GEN_USE_SCORE_PHASE', false),
        'use_safety_rewrite' => env('ARTICLE_GEN_USE_SAFETY_REWRITE', true),
        // Max GeminiGen attempts per image segment before the idea is HELD at
        // generating_images and escalated to the operator (never auto-advanced
        // with a broken image). GEO image-completion gate. 3 = align with the
        // admin UI "Attempt N/3" badge; stop auto-retrying past 3 (esp. under
        // high traffic) rather than grinding to 6.
        'image_segment_max_attempts' => env('IMAGE_SEGMENT_MAX_ATTEMPTS', 3),
        // Empty MCP config — passed via `--mcp-config <path> --strict-mcp-config`
        // so pipeline runs of `claude` skip MCP server boot entirely. Without
        // this, every `claude -p` invocation spawns the user's full MCP stack
        // (obsidian-mcp, firecrawl, playwright, etc.) and obsidian-mcp leaks
        // its node child whenever the parent claude exits — production saw
        // 140 leaked processes consuming 8.7GB RSS in 4 days. Set to empty
        // string to disable the override (dev-only fallback).
        'empty_mcp_config' => env('ARTICLE_GEN_EMPTY_MCP_CONFIG', '/home/claudesn/empty-mcp.json'),
    ],

];
