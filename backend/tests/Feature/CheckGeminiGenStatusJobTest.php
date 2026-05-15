<?php

namespace Tests\Feature;

use App\Jobs\CheckGeminiGenStatusJob;
use App\Services\GeminiGenCircuitBreaker;
use App\Services\GeminigenStatusPoller;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class CheckGeminiGenStatusJobTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_force_opens_breaker_when_poller_returns_true(): void
    {
        $poller = Mockery::mock(GeminigenStatusPoller::class);
        $poller->shouldReceive('isOurModelInOutage')->once()->andReturn(true);

        $breaker = app(GeminiGenCircuitBreaker::class);
        $this->assertSame('closed', $breaker->state());

        (new CheckGeminiGenStatusJob())->handle($poller, $breaker);

        $this->assertSame('open', $breaker->state());
    }

    public function test_does_not_force_open_when_poller_returns_false(): void
    {
        $poller = Mockery::mock(GeminigenStatusPoller::class);
        $poller->shouldReceive('isOurModelInOutage')->once()->andReturn(false);

        $breaker = app(GeminiGenCircuitBreaker::class);
        (new CheckGeminiGenStatusJob())->handle($poller, $breaker);

        $this->assertSame('closed', $breaker->state());
    }

    public function test_does_not_force_open_when_poller_returns_null(): void
    {
        $poller = Mockery::mock(GeminigenStatusPoller::class);
        $poller->shouldReceive('isOurModelInOutage')->once()->andReturn(null);

        $breaker = app(GeminiGenCircuitBreaker::class);
        (new CheckGeminiGenStatusJob())->handle($poller, $breaker);

        $this->assertSame('closed', $breaker->state());
    }
}
