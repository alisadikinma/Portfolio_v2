<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GeminiGenCircuitBreaker
{
    // Read from config('geminigen-circuit.*') — env-overridable per deployment.

    private const KEY_STATE = 'geminigen:circuit:state';
    private const KEY_FAILURE_LOG = 'geminigen:circuit:failure_log';
    private const KEY_OPENED_AT = 'geminigen:circuit:opened_at';
    private const KEY_NEXT_PROBE_AT = 'geminigen:circuit:next_probe_at';
    private const KEY_LAST_PROBE_RESULT = 'geminigen:circuit:last_probe_result';

    // Phase M — status-page early-trip accelerator
    private const KEY_STATUS_CHECK_LOCK = 'geminigen:circuit:status_check_in_flight';
    private const STATUS_CHECK_LOCK_TTL = 300;  // 5 min — prevents concurrent dispatches
    private const STATUS_CHECK_TRIGGER_COUNT = 3;

    public function state(): string
    {
        return Cache::get(self::KEY_STATE, 'closed');
    }

    public function openedAt(): ?Carbon
    {
        $ts = Cache::get(self::KEY_OPENED_AT);
        return $ts ? Carbon::parse($ts) : null;
    }

    public function nextProbeAt(): ?Carbon
    {
        $ts = Cache::get(self::KEY_NEXT_PROBE_AT);
        return $ts ? Carbon::parse($ts) : null;
    }

    public function lastProbeResult(): ?array
    {
        return Cache::get(self::KEY_LAST_PROBE_RESULT);
    }

    public function failureCountInWindow(): int
    {
        return count($this->getFailuresInWindow());
    }

    public function recordFailure(?int $httpStatus, ?string $errorCode = null, ?\Throwable $exception = null): void
    {
        if (self::classifyFailure($httpStatus, $errorCode, $exception) !== 'count') {
            return;  // prompt_class or ignore — don't increment
        }

        // Append timestamp to failure_log
        $now = Carbon::now();
        $log = Cache::get(self::KEY_FAILURE_LOG, []);
        $log[] = $now->toIso8601String();
        // Trim to last 50 (defensive — should never need more than threshold)
        if (count($log) > 50) {
            $log = array_slice($log, -50);
        }
        Cache::put(self::KEY_FAILURE_LOG, $log, (int) config('geminigen-circuit.state_ttl_seconds', 3600));

        $currentState = $this->state();
        $countInWindow = $this->failureCountInWindow();

        if ($currentState === 'half_open') {
            // Real dispatch failed during half_open → back to open
            $this->setOpen('half_open_failure');
            return;
        }

        if ($currentState === 'open') {
            // Already open — bump next probe forward
            Cache::put(self::KEY_NEXT_PROBE_AT, $now->copy()->addSeconds((int) config('geminigen-circuit.probe_interval_seconds', 300))->toIso8601String(), (int) config('geminigen-circuit.state_ttl_seconds', 3600));
            return;
        }

        // Phase M — Early-trip accelerator. When 3 consec failures hit and breaker is
        // still closed, ask GeminiGen's status page via Firecrawl. If our model is
        // confirmed in outage, force-open immediately (skip waiting for failure #5).
        // Cache lock prevents thundering when many segments fail simultaneously.
        if (
            $currentState === 'closed'
            && $countInWindow === self::STATUS_CHECK_TRIGGER_COUNT
            && Cache::missing(self::KEY_STATUS_CHECK_LOCK)
        ) {
            Cache::put(self::KEY_STATUS_CHECK_LOCK, true, self::STATUS_CHECK_LOCK_TTL);
            \App\Jobs\CheckGeminiGenStatusJob::dispatch();
        }

        // closed → check trip threshold
        if ($countInWindow >= (int) config('geminigen-circuit.failure_threshold', 5)) {
            $this->setOpen('threshold_reached');
        }
    }

    public function recordSuccess(): void
    {
        $currentState = $this->state();
        if ($currentState === 'half_open') {
            $this->setClosed('half_open_recovery');
            return;
        }
        if ($currentState === 'closed') {
            // Clear stale failures so isolated 5xx doesn't accumulate indefinitely
            Cache::forget(self::KEY_FAILURE_LOG);
            return;
        }
        // state=open + recordSuccess shouldn't happen in normal flow (gate blocks dispatch)
        // — but if it does (e.g. test seeding), treat as recovery
        $this->setClosed('unexpected_open_success');
    }

    public function transitionToHalfOpen(): void
    {
        Cache::put(self::KEY_STATE, 'half_open', (int) config('geminigen-circuit.state_ttl_seconds', 3600));
        Log::info('[GeminiGenCircuit] half_open — awaiting next real dispatch');
    }

    /**
     * Force the circuit to OPEN state from external triggers (e.g. status-page
     * confirmation of outage). Does NOT touch the failure_log — this is an
     * out-of-band override, not a counter operation.
     *
     * @param  string  $reason  Telemetry label appearing in logs and Telegram alert.
     */
    public function forceOpen(string $reason): void
    {
        if ($this->state() === 'open') {
            return;  // idempotent — already open, no-op
        }
        $this->setOpen($reason);
    }

    public function recordProbeResult(int $statusCode, ?string $error = null): void
    {
        Cache::put(self::KEY_LAST_PROBE_RESULT, [
            'at' => Carbon::now()->toIso8601String(),
            'status_code' => $statusCode,
            'error' => $error,
        ], (int) config('geminigen-circuit.state_ttl_seconds', 3600));
    }

    public static function classifyFailure(?int $httpStatus, ?string $errorCode = null, ?\Throwable $exception = null): string
    {
        // Network-level failures count as outage signal
        if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
            return 'count';
        }

        // No status code at all — can't classify; treat as ignore
        if ($httpStatus === null) {
            return 'ignore';
        }

        // Server-side outage signals
        if ($httpStatus >= 500) {
            return 'count';
        }

        // Rate limit — backing off helps; count toward trip
        if ($httpStatus === 429) {
            return 'count';
        }

        // 4xx range — distinguish prompt-class safety refusals (handled by
        // the April 28 safety auto-rewrite, NOT an outage signal) from
        // generic 4xx (bad request shape, auth, etc.).
        if ($httpStatus >= 400 && $httpStatus < 500) {
            $promptClassCodes = [
                'PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD',
                'PUBLIC_ERROR_MINORS',
                'PUBLIC_ERROR_UNSAFE_CONTENT',
                'PUBLIC_ERROR_SEXUAL_CONTENT',
            ];
            if (in_array($errorCode, $promptClassCodes, true)) {
                return 'prompt_class';
            }
            return 'ignore';
        }

        // 2xx + 3xx → not a failure at all
        return 'ignore';
    }

    private function getFailuresInWindow(): array
    {
        $log = Cache::get(self::KEY_FAILURE_LOG, []);
        $cutoff = Carbon::now()->subSeconds((int) config('geminigen-circuit.window_seconds', 600));
        return array_filter($log, fn($iso) => Carbon::parse($iso)->greaterThanOrEqualTo($cutoff));
    }

    private function setOpen(string $reason): void
    {
        $now = Carbon::now();
        $nextProbeAt = $now->copy()->addSeconds((int) config('geminigen-circuit.probe_interval_seconds', 300));

        Cache::put(self::KEY_STATE, 'open', (int) config('geminigen-circuit.state_ttl_seconds', 3600));
        Cache::put(self::KEY_OPENED_AT, $now->toIso8601String(), (int) config('geminigen-circuit.state_ttl_seconds', 3600));
        Cache::put(self::KEY_NEXT_PROBE_AT, $nextProbeAt->toIso8601String(), (int) config('geminigen-circuit.state_ttl_seconds', 3600));
        Log::warning('[GeminiGenCircuit] OPEN', ['reason' => $reason, 'failure_count' => $this->failureCountInWindow()]);

        // Phase I — Telegram alert. Wrapped in try/catch so a Telegram outage
        // (HTTP timeout, missing token, etc.) never blocks the state transition.
        try {
            app(\App\Services\TelegramNotificationService::class)
                ->sendGeminigenCircuitOpen($now, $nextProbeAt, $reason);
        } catch (\Throwable $e) {
            Log::warning('[GeminiGenCircuit] Telegram OPEN dispatch failed', ['error' => $e->getMessage()]);
        }
    }

    private function setClosed(string $reason): void
    {
        // Capture opened_at BEFORE forgetting the cache key so the alert can
        // report outage duration.
        $openedAt = $this->openedAt();

        Cache::put(self::KEY_STATE, 'closed', (int) config('geminigen-circuit.state_ttl_seconds', 3600));
        Cache::forget(self::KEY_FAILURE_LOG);
        Cache::forget(self::KEY_OPENED_AT);
        Cache::forget(self::KEY_NEXT_PROBE_AT);
        Log::info('[GeminiGenCircuit] CLOSED', ['reason' => $reason]);

        // Phase I — Telegram alert. Wrapped in try/catch so a Telegram outage
        // never blocks recovery.
        try {
            app(\App\Services\TelegramNotificationService::class)
                ->sendGeminigenCircuitClose($openedAt);
        } catch (\Throwable $e) {
            Log::warning('[GeminiGenCircuit] Telegram CLOSE dispatch failed', ['error' => $e->getMessage()]);
        }
    }
}
