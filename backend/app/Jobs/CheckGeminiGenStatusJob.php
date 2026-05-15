<?php

namespace App\Jobs;

use App\Services\GeminiGenCircuitBreaker;
use App\Services\GeminigenStatusPoller;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Phase M — Early-trip accelerator for the GeminiGen circuit breaker.
 *
 * Dispatched ONLY by GeminiGenCircuitBreaker::recordFailure() when failure
 * count hits 3 in window. Scrapes geminigen.ai/status via Firecrawl; if our
 * primary image model is confirmed in outage, force-opens the breaker
 * immediately (saves 2 wasted API calls vs waiting for threshold=5).
 */
class CheckGeminiGenStatusJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 1;  // one shot — don't retry; cache lock prevents thundering

    public function handle(GeminigenStatusPoller $poller, GeminiGenCircuitBreaker $breaker): void
    {
        // Idempotent — if breaker is already open, no-op
        if ($breaker->state() !== 'closed') {
            Log::info('[CheckGeminiGenStatusJob] breaker not closed, skipping', ['state' => $breaker->state()]);
            return;
        }

        $isOutage = $poller->isOurModelInOutage();

        if ($isOutage === true) {
            $breaker->forceOpen('status_page_confirmed_outage');
            Log::warning('[CheckGeminiGenStatusJob] status page confirms outage — breaker force-opened');
        } else {
            Log::info('[CheckGeminiGenStatusJob] no outage confirmation', ['poller_result' => $isOutage]);
        }
    }
}
