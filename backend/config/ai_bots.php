<?php

/*
|--------------------------------------------------------------------------
| AI crawler bot list (single source)
|--------------------------------------------------------------------------
|
| Keys are User-Agent substrings (case-insensitive match); values are the
| canonical names stored in geo_crawler_hits.bot. Astro must send the
| canonical VALUE to POST /api/internal/crawler-hit.
|
| Read by App\Http\Middleware\LogAiCrawler and the internal crawler-hit
| endpoint. Keep order such that no key is a substring of another.
|
| Google-Extended / Applebot-Extended are robots.txt tokens only; they never
| appear in a User-Agent, so they are deliberately absent.
*/
return [
    'map' => [
        'GPTBot' => 'GPTBot',
        'OAI-SearchBot' => 'OAI-SearchBot',
        'ChatGPT-User' => 'ChatGPT-User',
        'ClaudeBot' => 'ClaudeBot',
        'Claude-Web' => 'Claude-Web',
        'Claude-User' => 'Claude-User',
        'Claude-SearchBot' => 'Claude-SearchBot',
        'PerplexityBot' => 'PerplexityBot',
        'Perplexity-User' => 'Perplexity-User',
        'Meta-ExternalAgent' => 'Meta-ExternalAgent',
        'Amazonbot' => 'Amazonbot',
        'Bytespider' => 'Bytespider',
        'CCBot' => 'CCBot',
        'DuckAssistBot' => 'DuckAssistBot',
        'MistralAI-User' => 'MistralAI-User',
        'GoogleOther' => 'GoogleOther',
    ],
];
