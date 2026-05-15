<?php

namespace Tests\Unit;

use App\Services\GeminiGenCircuitBreaker;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Tests\TestCase;

class GeminiGenCircuitBreakerTest extends TestCase
{
    private GeminiGenCircuitBreaker $breaker;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();  // start fresh
        $this->breaker = new GeminiGenCircuitBreaker();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_default_state_is_closed(): void
    {
        $this->assertSame('closed', $this->breaker->state());
    }

    public function test_records_single_5xx_failure_state_stays_closed(): void
    {
        $this->breaker->recordFailure(503, null);
        $this->assertSame('closed', $this->breaker->state());
        $this->assertSame(1, $this->breaker->failureCountInWindow());
    }

    public function test_trips_to_open_after_5_consecutive_failures_in_window(): void
    {
        Carbon::setTestNow('2026-05-15 10:00:00');
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure(503, null);
        }
        $this->assertSame('open', $this->breaker->state());
    }

    public function test_failures_outside_window_dont_trip(): void
    {
        // 3 failures 11 min ago
        Carbon::setTestNow('2026-05-15 09:49:00');
        $this->breaker->recordFailure(503, null);
        $this->breaker->recordFailure(503, null);
        $this->breaker->recordFailure(503, null);

        // 4 failures now (window = 10 min, so the 3 above are out)
        Carbon::setTestNow('2026-05-15 10:00:00');
        $this->breaker->recordFailure(503, null);
        $this->breaker->recordFailure(503, null);
        $this->breaker->recordFailure(503, null);
        $this->breaker->recordFailure(503, null);

        // Only 4 in window → stays closed
        $this->assertSame('closed', $this->breaker->state());
        $this->assertSame(4, $this->breaker->failureCountInWindow());
    }

    public function test_4xx_prompt_errors_do_not_count(): void
    {
        $this->breaker->recordFailure(400, 'PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD');
        $this->breaker->recordFailure(400, 'PUBLIC_ERROR_MINORS');
        $this->breaker->recordFailure(422, null);

        $this->assertSame('closed', $this->breaker->state());
        $this->assertSame(0, $this->breaker->failureCountInWindow());
    }

    public function test_429_does_count(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure(429, null);
        }
        $this->assertSame('open', $this->breaker->state());
    }

    public function test_record_success_during_closed_clears_failure_log(): void
    {
        $this->breaker->recordFailure(503, null);
        $this->breaker->recordFailure(503, null);
        $this->assertSame(2, $this->breaker->failureCountInWindow());

        $this->breaker->recordSuccess();
        $this->assertSame('closed', $this->breaker->state());
        $this->assertSame(0, $this->breaker->failureCountInWindow());
    }

    public function test_half_open_then_success_transitions_to_closed(): void
    {
        // Trip to open
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure(503, null);
        }
        $this->assertSame('open', $this->breaker->state());

        // Manually transition to half_open (simulates canary probe success)
        $this->breaker->transitionToHalfOpen();
        $this->assertSame('half_open', $this->breaker->state());

        // Real dispatch succeeds
        $this->breaker->recordSuccess();
        $this->assertSame('closed', $this->breaker->state());
    }

    public function test_half_open_then_failure_transitions_back_to_open(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure(503, null);
        }
        $this->breaker->transitionToHalfOpen();
        $this->assertSame('half_open', $this->breaker->state());

        // Real dispatch fails
        $this->breaker->recordFailure(503, null);
        $this->assertSame('open', $this->breaker->state());
    }

    public function test_record_failure_during_open_bumps_next_probe_at(): void
    {
        Carbon::setTestNow('2026-05-15 10:00:00');
        for ($i = 0; $i < 5; $i++) {
            $this->breaker->recordFailure(503, null);
        }
        $firstProbeAt = $this->breaker->nextProbeAt();
        $this->assertNotNull($firstProbeAt);

        // 1 minute later, another failure
        Carbon::setTestNow('2026-05-15 10:01:00');
        $this->breaker->recordFailure(503, null);
        $secondProbeAt = $this->breaker->nextProbeAt();

        // next probe pushed forward by 1 min (recordFailure resets next_probe_at = now + 5min)
        $this->assertTrue($secondProbeAt->greaterThan($firstProbeAt));
    }

    // =================================================================
    // Phase B — Failure classifier tests
    // =================================================================

    public function test_classifier_5xx_returns_count(): void
    {
        $this->assertSame('count', GeminiGenCircuitBreaker::classifyFailure(500, null));
        $this->assertSame('count', GeminiGenCircuitBreaker::classifyFailure(502, null));
        $this->assertSame('count', GeminiGenCircuitBreaker::classifyFailure(503, null));
        $this->assertSame('count', GeminiGenCircuitBreaker::classifyFailure(504, null));
    }

    public function test_classifier_429_returns_count(): void
    {
        $this->assertSame('count', GeminiGenCircuitBreaker::classifyFailure(429, null));
    }

    public function test_classifier_connection_exception_returns_count(): void
    {
        $exception = new \Illuminate\Http\Client\ConnectionException('Connection timed out');
        $this->assertSame('count', GeminiGenCircuitBreaker::classifyFailure(null, null, $exception));
    }

    public function test_classifier_prompt_class_4xx_returns_prompt_class(): void
    {
        $promptCodes = [
            'PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD',
            'PUBLIC_ERROR_MINORS',
            'PUBLIC_ERROR_UNSAFE_CONTENT',
            'PUBLIC_ERROR_SEXUAL_CONTENT',
        ];

        foreach ($promptCodes as $code) {
            $this->assertSame(
                'prompt_class',
                GeminiGenCircuitBreaker::classifyFailure(400, $code),
                "Expected '$code' to classify as prompt_class"
            );
        }
    }

    public function test_classifier_generic_4xx_returns_ignore(): void
    {
        $this->assertSame('ignore', GeminiGenCircuitBreaker::classifyFailure(400, null));
        $this->assertSame('ignore', GeminiGenCircuitBreaker::classifyFailure(401, null));
        $this->assertSame('ignore', GeminiGenCircuitBreaker::classifyFailure(404, null));
        $this->assertSame('ignore', GeminiGenCircuitBreaker::classifyFailure(422, null));
        // 4xx with unrecognized errorCode also ignored
        $this->assertSame('ignore', GeminiGenCircuitBreaker::classifyFailure(400, 'SOME_OTHER_ERROR_CODE'));
    }

    public function test_classifier_2xx_returns_ignore(): void
    {
        $this->assertSame('ignore', GeminiGenCircuitBreaker::classifyFailure(200, null));
        $this->assertSame('ignore', GeminiGenCircuitBreaker::classifyFailure(204, null));
        $this->assertSame('ignore', GeminiGenCircuitBreaker::classifyFailure(null, null));
    }
}
