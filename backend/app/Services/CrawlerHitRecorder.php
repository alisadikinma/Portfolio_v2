<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single implementation of the daily AI-crawler hit counter.
 * Used by LogAiCrawler (Laravel-served requests) and the internal
 * crawler-hit endpoint (Astro-served requests). Fully fail-open.
 */
class CrawlerHitRecorder
{
    /**
     * Atomic daily upsert increment for (today, bot). Swallows DB failure.
     */
    public function record(string $bot): void
    {
        try {
            $date = now()->toDateString();

            // Single atomic upsert keyed on unique(date,bot): inserts hits=1,
            // or `hits = hits + 1` on conflict. Race-free for concurrent
            // first-of-day crawls.
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
            // hitting us" measurement. Upgrade to (date,bot,path) if needed.
            Log::warning('Failed to record crawler hit', [
                'bot' => $bot,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
