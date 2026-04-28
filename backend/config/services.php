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
    ],

];
