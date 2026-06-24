<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * LogAiCrawler — records AI bot crawls that GA4 can never see.
 *
 * GA4 only fires for JS-executing browsers with a referrer; AI crawlers
 * (GPTBot, ClaudeBot, PerplexityBot, …) do neither. This middleware matches
 * the request User-Agent against a fixed AI-bot map and increments a per-bot
 * daily counter in `geo_crawler_hits`. Read back via GET /api/admin/geo/crawler-hits.
 *
 * The DB work is fully swallowed (Log::warning only) — a logging failure must
 * never break the crawl response.
 */
class LogAiCrawler
{
    /**
     * User-Agent substring => canonical bot name. Matching is case-insensitive.
     */
    private const BOT_MAP = [
        'GPTBot' => 'GPTBot',
        'OAI-SearchBot' => 'OAI-SearchBot',
        'ChatGPT-User' => 'ChatGPT-User',
        'ClaudeBot' => 'ClaudeBot',
        'Claude-Web' => 'Claude-Web',
        'PerplexityBot' => 'PerplexityBot',
        'Google-Extended' => 'Google-Extended',
        'Applebot-Extended' => 'Applebot-Extended',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $bot = $this->matchBot($request->userAgent());

        if ($bot !== null) {
            $this->record($bot);
        }

        return $next($request);
    }

    /**
     * Return the canonical bot name when the UA matches, else null.
     */
    private function matchBot(?string $userAgent): ?string
    {
        if ($userAgent === null || $userAgent === '') {
            return null;
        }

        foreach (self::BOT_MAP as $needle => $canonical) {
            if (stripos($userAgent, $needle) !== false) {
                return $canonical;
            }
        }

        return null;
    }

    /**
     * Atomic daily upsert increment for (today, bot). Fully fail-open.
     */
    private function record(string $bot): void
    {
        try {
            $date = now()->toDateString();

            // Single atomic upsert keyed on unique(date,bot): inserts the row
            // with hits=1, or `hits = hits + 1` on conflict. Race-free even for
            // two concurrent first-of-day crawls (the increment+insert pattern
            // could drop one hit in that window).
            DB::table('geo_crawler_hits')->upsert(
                [
                    'date' => $date,
                    'bot' => $bot,
                    'hits' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                ['date', 'bot'],
                ['hits' => DB::raw('hits + 1'), 'updated_at' => now()],
            );
        } catch (\Throwable $e) {
            // ponytail: daily granularity is enough for "are AI crawlers
            // hitting us" measurement. If per-path attribution is ever needed,
            // upgrade the table+key to (date,bot,path) and key the increment on it.
            Log::warning('LogAiCrawler failed to record crawler hit', [
                'bot' => $bot,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
