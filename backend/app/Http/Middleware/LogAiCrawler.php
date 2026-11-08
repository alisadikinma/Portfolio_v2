<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\CrawlerHitRecorder;
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
    public function handle(Request $request, Closure $next): Response
    {
        $bot = $this->matchBot($request->userAgent());

        if ($bot !== null) {
            app(CrawlerHitRecorder::class)->record($bot);
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

        foreach ((array) config('ai_bots.map', []) as $needle => $canonical) {
            if (stripos($userAgent, $needle) !== false) {
                return $canonical;
            }
        }

        return null;
    }
}
