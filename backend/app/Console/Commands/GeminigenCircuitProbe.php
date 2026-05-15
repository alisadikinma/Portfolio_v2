<?php

namespace App\Console\Commands;

use App\Services\GeminiGenCircuitBreaker;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Phase H — GeminiGen circuit breaker canary probe.
 *
 * Runs every 5 min via DynamicScheduleRegistrar (see ScheduledCommandSeeder).
 * No-ops unless the breaker is OPEN and the current moment is past
 * next_probe_at. Fires a single cheap GET to the GeminiGen status page;
 * 2xx response is treated as "service reachable, time to test real flow" and
 * transitions the breaker to half_open. Failure stays OPEN and bumps
 * next_probe_at forward via recordFailure().
 *
 * Choice of canary target: status page (HTTP reachability) rather than the
 * real /generate_image POST. Rationale: avoids consuming GeminiGen quota on
 * every probe; half_open will validate the real path on the next operator
 * dispatch anyway.
 *
 * Design + plan: docs/plans/2026-05-15-geminigen-circuit-breaker.md
 */
class GeminigenCircuitProbe extends Command
{
    protected $signature = 'geminigen:circuit-probe {--dry-run : Skip the HTTP request, only report intent}';

    protected $description = 'Canary probe — when GeminiGen circuit is OPEN past next_probe_at, fire a tiny request to test recovery. Transitions to half_open on success.';

    public function handle(GeminiGenCircuitBreaker $breaker): int
    {
        $state = $breaker->state();

        if ($state !== 'open') {
            $this->line("[skip] state={$state} — only probes when open");
            return self::SUCCESS;
        }

        $nextProbe = $breaker->nextProbeAt();
        if ($nextProbe !== null && Carbon::now()->lessThan($nextProbe)) {
            $this->line("[skip] next probe at {$nextProbe->toIso8601String()}");
            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info('[dry-run] would fire canary now');
            return self::SUCCESS;
        }

        // Use the same status page URL configured for Phase M Firecrawl
        // accelerator — service reachability is sufficient signal here.
        $statusUrl = (string) config('geminigen-circuit.status_page_url', 'https://geminigen.ai/status');

        try {
            $response = Http::timeout(20)->get($statusUrl);

            if ($response->successful()) {
                $breaker->recordProbeResult($response->status(), null);
                $breaker->transitionToHalfOpen();
                $this->info('[probe-success] state=half_open');
                Log::info('[GeminigenCircuitProbe] success, half_open', ['status' => $response->status()]);
            } else {
                $breaker->recordProbeResult($response->status(), 'http_non_2xx');
                $breaker->recordFailure($response->status());
                $this->warn("[probe-fail] status={$response->status()}, stays open");
                Log::warning('[GeminigenCircuitProbe] non-2xx', ['status' => $response->status()]);
            }
        } catch (\Throwable $e) {
            $breaker->recordProbeResult(0, $e->getMessage());
            $breaker->recordFailure(null, null, $e);
            $this->warn('[probe-fail] exception: ' . $e->getMessage());
            Log::warning('[GeminigenCircuitProbe] exception', ['error' => $e->getMessage()]);
        }

        return self::SUCCESS;
    }
}
