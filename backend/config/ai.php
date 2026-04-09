<?php

return [
    'api_key' => env('OPENROUTER_API_KEY'),
    'model' => env('AI_MODEL', 'google/gemini-2.5-flash-lite'),
    'max_tokens' => env('AI_MAX_TOKENS', 1024),
    'base_url' => env('AI_BASE_URL', 'https://openrouter.ai/api/v1'),
];
