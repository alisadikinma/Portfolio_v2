<?php

namespace Tests\Unit;

use App\Services\GeminigenStatusPoller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminigenStatusPollerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        config([
            'geminigen-circuit.firecrawl_api_key' => 'test-key',
            'geminigen-circuit.firecrawl_base_url' => 'https://api.firecrawl.dev/v2',
            'geminigen-circuit.status_page_url' => 'https://geminigen.ai/status',
            'geminigen-circuit.status_model_name' => 'Nano Banana Pro',
            'geminigen-circuit.status_cache_seconds' => 300,
        ]);
    }

    private function fakeFirecrawl(array $components, int $status = 200): void
    {
        Http::fake([
            'api.firecrawl.dev/*' => Http::response([
                'data' => ['json' => ['components' => $components]],
            ], $status),
        ]);
    }

    public function test_returns_true_when_our_model_status_contains_outage(): void
    {
        $this->fakeFirecrawl([
            ['name' => 'Nano Banana Pro', 'status' => 'partial outage'],
            ['name' => 'Meta AI Image', 'status' => 'operational'],
        ]);

        $this->assertTrue((new GeminigenStatusPoller())->isOurModelInOutage());
    }

    public function test_returns_false_when_our_model_status_is_operational(): void
    {
        $this->fakeFirecrawl([
            ['name' => 'Nano Banana Pro', 'status' => 'operational'],
        ]);

        $this->assertFalse((new GeminigenStatusPoller())->isOurModelInOutage());
    }

    public function test_returns_null_when_model_not_in_components(): void
    {
        $this->fakeFirecrawl([
            ['name' => 'Some Other Model', 'status' => 'operational'],
        ]);

        $this->assertNull((new GeminigenStatusPoller())->isOurModelInOutage());
    }

    public function test_returns_null_when_firecrawl_call_fails(): void
    {
        Http::fake([
            'api.firecrawl.dev/*' => Http::response('error', 500),
        ]);

        $this->assertNull((new GeminigenStatusPoller())->isOurModelInOutage());
    }

    public function test_uses_cached_result_within_ttl(): void
    {
        // Track per-call to verify cache short-circuit (Http::fake() may reset
        // request log between fake registrations, so use a callback counter
        // instead of Http::assertSentCount).
        $callCount = 0;
        Http::fake(function () use (&$callCount) {
            $callCount++;
            return Http::response([
                'data' => ['json' => ['components' => [
                    ['name' => 'Nano Banana Pro', 'status' => 'partial outage'],
                ]]],
            ], 200);
        });

        $poller = new GeminigenStatusPoller();
        $first = $poller->isOurModelInOutage();
        $this->assertTrue($first);
        $this->assertSame(1, $callCount);

        // Second call within TTL — must NOT hit Firecrawl again
        $second = $poller->isOurModelInOutage();
        $this->assertTrue($second);
        $this->assertSame(1, $callCount);  // still 1 — cached
    }
}
