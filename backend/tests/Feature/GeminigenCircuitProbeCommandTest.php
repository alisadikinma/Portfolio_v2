<?php

namespace Tests\Feature;

use App\Services\GeminiGenCircuitBreaker;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase H — canary probe command feature tests.
 *
 * Covers the four operational paths:
 *   1. no-op when closed
 *   2. no-op when open but probe not yet due
 *   3. successful probe → half_open transition
 *   4. failed probe → stays open + bumps next_probe_at forward
 */
class GeminigenCircuitProbeCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_no_op_when_circuit_closed(): void
    {
        Http::fake();

        $this->artisan('geminigen:circuit-probe')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_no_op_when_circuit_open_but_probe_not_due(): void
    {
        Http::fake();

        $breaker = app(GeminiGenCircuitBreaker::class);
        for ($i = 0; $i < 5; $i++) {
            $breaker->recordFailure(503);
        }
        $this->assertSame('open', $breaker->state());

        // next_probe_at is `now + probe_interval_seconds` after trip,
        // so an immediate probe call is too early.
        $this->artisan('geminigen:circuit-probe')->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_canary_success_transitions_to_half_open(): void
    {
        Http::fake([
            'geminigen.ai/status*' => Http::response('OK', 200),
        ]);

        $breaker = app(GeminiGenCircuitBreaker::class);
        for ($i = 0; $i < 5; $i++) {
            $breaker->recordFailure(503);
        }

        // Advance past next_probe_at (default probe_interval_seconds=300 → 5 min)
        Carbon::setTestNow(Carbon::now()->addMinutes(6));

        $this->artisan('geminigen:circuit-probe')->assertSuccessful();

        $this->assertSame('half_open', $breaker->state());
        Http::assertSent(fn ($req) => str_contains($req->url(), 'geminigen.ai/status'));
    }

    public function test_canary_failure_stays_open_bumps_next_probe(): void
    {
        Http::fake([
            'geminigen.ai/status*' => Http::response('Down', 503),
        ]);

        $breaker = app(GeminiGenCircuitBreaker::class);
        for ($i = 0; $i < 5; $i++) {
            $breaker->recordFailure(503);
        }

        $firstNextProbe = $breaker->nextProbeAt();
        $this->assertNotNull($firstNextProbe);

        Carbon::setTestNow(Carbon::now()->addMinutes(6));

        $this->artisan('geminigen:circuit-probe')->assertSuccessful();

        $this->assertSame('open', $breaker->state());

        $newNextProbe = $breaker->nextProbeAt();
        $this->assertNotNull($newNextProbe);
        $this->assertTrue(
            $newNextProbe->greaterThan($firstNextProbe),
            'next_probe_at should be pushed forward by the failed canary probe'
        );
    }

    public function test_dry_run_skips_http_when_probe_due(): void
    {
        Http::fake();

        $breaker = app(GeminiGenCircuitBreaker::class);
        for ($i = 0; $i < 5; $i++) {
            $breaker->recordFailure(503);
        }

        Carbon::setTestNow(Carbon::now()->addMinutes(6));

        $this->artisan('geminigen:circuit-probe', ['--dry-run' => true])
            ->expectsOutputToContain('[dry-run]')
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame('open', $breaker->state());
    }
}
