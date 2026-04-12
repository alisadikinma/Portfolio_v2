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

    'content_engine' => [
        'url' => env('CONTENT_ENGINE_URL', 'http://127.0.0.1:8100'),
        'api_key' => env('CONTENT_ENGINE_API_KEY', ''),
    ],

    'article_generation' => [
        'driver' => env('ARTICLE_GEN_DRIVER', 'ssh'),
        'ssh_host' => env('ARTICLE_GEN_SSH_HOST', ''),
        'ssh_user' => env('ARTICLE_GEN_SSH_USER', 'root'),
        'ssh_key' => env('ARTICLE_GEN_SSH_KEY', ''),
        'claude_path' => env('ARTICLE_GEN_CLAUDE_PATH', 'claude'),
        'api_url' => env('ARTICLE_GEN_API_URL', 'https://alisadikinma.com/api'),
        'api_token' => env('ARTICLE_GEN_API_TOKEN', ''),
    ],

];
