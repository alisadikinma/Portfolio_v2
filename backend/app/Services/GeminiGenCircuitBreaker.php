<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GeminiGenCircuitBreaker
{
    // Hardcoded for Phase A. Phase C will move these to config().
    private const FAILURE_THRESHOLD = 5;
    private const WINDOW_SECONDS = 600;          // 10 min sliding window
    private const PROBE_INTERVAL_SECONDS = 300;  // 5 min
    private const STATE_TTL_SECONDS = 3600;      // 1 hour

    private const KEY_STATE = 'geminigen:circuit:state';
    private const KEY_FAILURE_LOG = 'geminigen:circuit:failure_log';
    private const KEY_OPENED_AT = 'geminigen:circuit:opened_at';
    private const KEY_NEXT_PROBE_AT = 'geminigen:circuit:next_probe_at';
    private const KEY_LAST_PROBE_RESULT = 'geminigen:circuit:last_probe_result';

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
        Cache::put(self::KEY_FAILURE_LOG, $log, self::STATE_TTL_SECONDS);

        $currentState = $this->state();
        $countInWindow = $this->failureCountInWindow();

        if ($currentState === 'half_open') {
            // Real dispatch failed during half_open → back to open
            $this->setOpen('half_open_failure');
            return;
        }

        if ($currentState === 'open') {
            // Already open — bump next probe forward
            Cache::put(self::KEY_NEXT_PROBE_AT, $now->copy()->addSeconds(self::PROBE_INTERVAL_SECONDS)->toIso8601String(), self::STATE_TTL_SECONDS);
            return;
        }

        // closed → check trip threshold
        if ($countInWindow >= self::FAILURE_THRESHOLD) {
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
        Cache::put(self::KEY_STATE, 'half_open', self::STATE_TTL_SECONDS);
        Log::info('[GeminiGenCircuit] half_open — awaiting next real dispatch');
    }

    public function recordProbeResult(int $statusCode, ?string $error = null): void
    {
        Cache::put(self::KEY_LAST_PROBE_RESULT, [
            'at' => Carbon::now()->toIso8601String(),
            'status_code' => $statusCode,
            'error' => $error,
        ], self::STATE_TTL_SECONDS);
    }

    public static function classifyFailure(?int $httpStatus, ?string $errorCode = null, ?\Throwable $exception = null): string
    {
        // Phase B will own the full classifier. Phase A stub:
        if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
            return 'count';
        }
        if ($httpStatus === null) {
            return 'ignore';
        }
        if ($httpStatus >= 500) {
            return 'count';
        }
        if ($httpStatus === 429) {
            return 'count';
        }
        if ($httpStatus >= 400 && $httpStatus < 500) {
            return 'ignore';  // Phase B refines this
        }
        return 'ignore';
    }

    private function getFailuresInWindow(): array
    {
        $log = Cache::get(self::KEY_FAILURE_LOG, []);
        $cutoff = Carbon::now()->subSeconds(self::WINDOW_SECONDS);
        return array_filter($log, fn($iso) => Carbon::parse($iso)->greaterThanOrEqualTo($cutoff));
    }

    private function setOpen(string $reason): void
    {
        $now = Carbon::now();
        Cache::put(self::KEY_STATE, 'open', self::STATE_TTL_SECONDS);
        Cache::put(self::KEY_OPENED_AT, $now->toIso8601String(), self::STATE_TTL_SECONDS);
        Cache::put(self::KEY_NEXT_PROBE_AT, $now->copy()->addSeconds(self::PROBE_INTERVAL_SECONDS)->toIso8601String(), self::STATE_TTL_SECONDS);
        Log::warning('[GeminiGenCircuit] OPEN', ['reason' => $reason, 'failure_count' => $this->failureCountInWindow()]);
    }

    private function setClosed(string $reason): void
    {
        Cache::put(self::KEY_STATE, 'closed', self::STATE_TTL_SECONDS);
        Cache::forget(self::KEY_FAILURE_LOG);
        Cache::forget(self::KEY_OPENED_AT);
        Cache::forget(self::KEY_NEXT_PROBE_AT);
        Log::info('[GeminiGenCircuit] CLOSED', ['reason' => $reason]);
    }
}
