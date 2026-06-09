<?php

namespace Tests\Unit;

use App\Services\CarouselGenOutputAdapter;
use App\Services\LinkedInGenerationService;
use App\Services\PipelineGuard;
use Mockery;
use Tests\TestCase;

/**
 * Phase B — default-carousel plan (2026-06-09).
 * Unit tests for the pure LinkedInGenerationService::forceCarouselEnvelope()
 * rewrite. No DB / no SSH — only array-shape assertions.
 */
class LinkedInForceCarouselEnvelopeTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeService(): LinkedInGenerationService
    {
        $guard = Mockery::mock(PipelineGuard::class);
        return new LinkedInGenerationService($guard, new CarouselGenOutputAdapter());
    }

    public function test_text_envelope_is_rewritten_to_carousel_route_when_force_on(): void
    {
        $svc = $this->makeService();
        $parsed = [
            'status' => 'complete',
            'format' => 'text',
            'post' => ['post_text' => 'hello', 'hashtags' => ['#ai']],
            'carousel' => null,
        ];

        $out = $svc->forceCarouselEnvelope($parsed, true);

        $this->assertSame('carousel', $out['format']);
        $this->assertSame('route_to_carousel_gen', $out['status']);
        // Non-routing fields preserved.
        $this->assertSame(['post_text' => 'hello', 'hashtags' => ['#ai']], $out['post']);
    }

    public function test_unchanged_when_force_disabled(): void
    {
        $svc = $this->makeService();
        $parsed = ['status' => 'complete', 'format' => 'text'];

        $this->assertSame($parsed, $svc->forceCarouselEnvelope($parsed, false));
    }

    public function test_never_masks_a_plugin_failure(): void
    {
        $svc = $this->makeService();
        $parsed = ['status' => 'failed', 'format' => 'text', 'error' => ['message' => 'boom']];

        // Force ON but status=failed must pass through untouched.
        $this->assertSame($parsed, $svc->forceCarouselEnvelope($parsed, true));
    }

    public function test_unchanged_when_already_routed_to_carousel_gen(): void
    {
        $svc = $this->makeService();
        $parsed = ['status' => 'route_to_carousel_gen', 'format' => 'carousel', 'brief' => ['pillar' => 'x']];

        $this->assertSame($parsed, $svc->forceCarouselEnvelope($parsed, true));
    }

    public function test_unchanged_when_already_carousel_format_legacy(): void
    {
        $svc = $this->makeService();
        // Legacy 'complete' + carousel envelope — left alone so the downstream
        // adapter rejects it (strict enforcement), not silently re-routed.
        $parsed = ['status' => 'complete', 'format' => 'carousel', 'carousel' => ['slides' => []]];

        $this->assertSame($parsed, $svc->forceCarouselEnvelope($parsed, true));
    }
}
