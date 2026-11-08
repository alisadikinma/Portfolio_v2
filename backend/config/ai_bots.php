<?php

/*
|--------------------------------------------------------------------------
| AI crawler bot list (single source)
|--------------------------------------------------------------------------
|
| User-Agent substring => canonical bot name (case-insensitive match).
| Read by App\Http\Middleware\LogAiCrawler and the internal crawler-hit
| endpoint. The Astro copy (web/src/lib/aiBots.ts) is parity-checked against
| the keys of this file by a test — keep them in sync.
*/
return [
    'map' => [
        'GPTBot' => 'GPTBot',
        'OAI-SearchBot' => 'OAI-SearchBot',
        'ChatGPT-User' => 'ChatGPT-User',
        'ClaudeBot' => 'ClaudeBot',
        'Claude-Web' => 'Claude-Web',
        'PerplexityBot' => 'PerplexityBot',
        'Google-Extended' => 'Google-Extended',
        'Applebot-Extended' => 'Applebot-Extended',
    ],
];
