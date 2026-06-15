<?php

namespace Tests\Feature;

use App\Exceptions\CarouselGenAdapterException;
use App\Services\CarouselGenOutputAdapter;
use App\Services\LinkedInGenerationService;
use App\Services\PipelineGuard;
use Mockery;
use Tests\TestCase;

/**
 * Unit tests for LinkedInGenerationService::applyCarouselGenAdapter.
 *
 * Plugin v0.5.0 retired /linkedin-carousel + the
 * `linkedin.use_carousel_gen_engine` feature flag. May 2026 follow-up
 * removed the legacy envelope fallback entirely — for carousel format,
 * the orchestrator MUST emit `status='route_to_carousel_gen'`. Anything
 * else (including the pre-v0.5.0 inline `complete` envelope) gets rejected
 * via CarouselGenAdapterException.
 *
 *   format='text'                                       → return parsed unchanged
 *   format='carousel' + status='route_to_carousel_gen'  → dispatch /carousel-gen,
 *                                                          build carousel slot,
 *                                                          promote to 'complete'
 *   format='carousel' + ANY OTHER status                → throw (legacy rejected)
 *
 * Adapter exceptions (CarouselGenAdapterException) are thrown to caller —
 * generate() catches and routes draft to FSM Failed.
 */
class LinkedInGenerationServiceCarouselGenRouterTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeService(): LinkedInGenerationService
    {
        config()->set('carousel-gen.driver', 'ssh');
        config()->set('carousel-gen.timeout_seconds', 600);

        $guard = Mockery::mock(PipelineGuard::class);
        $adapter = new CarouselGenOutputAdapter();
        return new LinkedInGenerationService($guard, $adapter);
    }

    public function test_returns_parsed_unchanged_when_format_is_text(): void
    {
        $svc = $this->makeService();
        $parsed = [
            'status' => 'complete',
            'format' => 'text',
            'brief' => ['hook_id' => 'pas', 'pillar' => 'ai_generalist'],
            'post' => ['post_text' => 'a text post', 'hashtags' => ['#ai']],
            'carousel' => null,
            'validation' => ['depth_score' => 80, 'passed' => true],
            'generated_at' => '2026-04-28T10:00:00Z',
        ];

        $result = $svc->applyCarouselGenAdapter($parsed, 'https://example.com/blog/x', 42);

        // Text format → /carousel-gen NOT called. Parsed returned unchanged.
        $this->assertSame($parsed, $result);
    }

    public function test_route_to_carousel_gen_dispatches_and_promotes_status_to_complete(): void
    {
        $svc = Mockery::mock(LinkedInGenerationService::class . '[dispatchCarouselGenEngine]', [
            Mockery::mock(PipelineGuard::class),
            new CarouselGenOutputAdapter(),
        ])->makePartial();

        // Mocked /carousel-gen output (matches CarouselGenOutputSchema).
        $carouselGenOutput = $this->fakeCarouselGenOutput();

        $svc->shouldReceive('dispatchCarouselGenEngine')
            ->once()
            ->andReturn($carouselGenOutput);

        // Plugin v0.5.0 envelope shape — brief only, all other slots null.
        $parsed = [
            'status' => 'route_to_carousel_gen',
            'format' => 'carousel',
            'brief' => ['hook_framework' => 'PAS', 'pillar' => 'ai_generalist'],
            'post' => null,
            'carousel' => null,
            'validation' => null,
            'generated_at' => '2026-04-28T10:00:00Z',
        ];

        $result = $svc->applyCarouselGenAdapter($parsed, 'https://example.com/blog/x', 42);

        // Status promoted from route_to_carousel_gen to complete.
        $this->assertSame('complete', $result['status']);
        // Carousel built from adapter output (5 slides).
        $this->assertCount(5, $result['carousel']['slides']);
        $this->assertSame('cover', $result['carousel']['slides'][0]['layout_hint']);
        $this->assertSame('pending', $result['carousel']['slides'][0]['image_status']);
        // Brief preserved.
        $this->assertSame('PAS', $result['brief']['hook_framework']);
    }

    public function test_rejects_legacy_complete_carousel_envelope(): void
    {
        // Legacy fallback removal (May 2026): pre-v0.5.0 envelopes with
        // status='complete' + format='carousel' + inline slides MUST be
        // rejected. Operator-side regenerate / queue retry surfaces this
        // as FSM Failed instead of silently honoring stale plugin output.
        $svc = Mockery::mock(LinkedInGenerationService::class . '[dispatchCarouselGenEngine]', [
            Mockery::mock(PipelineGuard::class),
            new CarouselGenOutputAdapter(),
        ])->makePartial();

        // dispatchCarouselGenEngine MUST NOT be called for legacy envelopes —
        // we throw before reaching dispatch.
        $svc->shouldNotReceive('dispatchCarouselGenEngine');

        $parsed = [
            'status' => 'complete',
            'format' => 'carousel',
            'brief' => ['hook_framework' => 'PAS', 'pillar' => 'ai_generalist'],
            'carousel' => [
                'slides' => [
                    ['slide_number' => 1, 'layout_hint' => 'cover', 'copy' => 'old legacy slide'],
                ],
            ],
            'validation' => ['depth_score' => 85, 'passed' => true],
            'generated_at' => '2026-04-28T10:00:00Z',
        ];

        $this->expectException(CarouselGenAdapterException::class);
        $this->expectExceptionMessageMatches("/route_to_carousel_gen|Legacy envelopes/i");

        $svc->applyCarouselGenAdapter($parsed, 'https://example.com/blog/x', 42);
    }

    public function test_throws_carousel_gen_adapter_exception_when_carousel_gen_returns_failed(): void
    {
        $svc = Mockery::mock(LinkedInGenerationService::class . '[dispatchCarouselGenEngine]', [
            Mockery::mock(PipelineGuard::class),
            new CarouselGenOutputAdapter(),
        ])->makePartial();

        // /carousel-gen reports a failure.
        $svc->shouldReceive('dispatchCarouselGenEngine')
            ->once()
            ->andReturn([
                'status' => 'failed',
                'format' => 'carousel',
                'error' => 'LLM quota exhausted mid-generation',
                'generated_at' => '2026-04-28T10:00:00Z',
            ]);

        $parsed = [
            'status' => 'route_to_carousel_gen',
            'format' => 'carousel',
            'brief' => ['hook_framework' => 'PAS'],
            'post' => null,
            'carousel' => null,
            'validation' => null,
            'generated_at' => '2026-04-28T10:00:00Z',
        ];

        $this->expectException(CarouselGenAdapterException::class);
        $this->expectExceptionMessageMatches('/quota exhausted/');

        $svc->applyCarouselGenAdapter($parsed, 'https://example.com/blog/x', 42);
    }

    public function test_throws_when_carousel_gen_dispatch_returns_null(): void
    {
        $svc = Mockery::mock(LinkedInGenerationService::class . '[dispatchCarouselGenEngine]', [
            Mockery::mock(PipelineGuard::class),
            new CarouselGenOutputAdapter(),
        ])->makePartial();

        // SSH/parse failure surfaces as null.
        $svc->shouldReceive('dispatchCarouselGenEngine')
            ->once()
            ->andReturn(null);

        $parsed = [
            'status' => 'route_to_carousel_gen',
            'format' => 'carousel',
            'brief' => ['hook_framework' => 'PAS'],
            'post' => null,
            'carousel' => null,
            'validation' => null,
            'generated_at' => '2026-04-28T10:00:00Z',
        ];

        $this->expectException(CarouselGenAdapterException::class);
        $this->expectExceptionMessageMatches('/dispatch failed|null|empty/i');

        $svc->applyCarouselGenAdapter($parsed, 'https://example.com/blog/x', 42);
    }

    /**
     * Canonical /carousel-gen output for testing the adapter pipeline.
     */
    private function fakeCarouselGenOutput(): array
    {
        return [
            'status' => 'complete',
            'format' => 'carousel',
            'total_slides' => 5,
            'aspect_ratio' => '4:5',
            'bilingual' => true,
            'narrative' => '5act',
            'slides' => [
                [
                    'slide_number' => 1,
                    'layout_hint' => 'cover',
                    'copy_id' => 'Cover ID',
                    'copy_en' => 'Cover EN',
                    'image_prompt' => str_repeat('cinematic prose with {{CREATOR_FACE}} and {{BRAND_LOGO}} and {{HANDLE}} and {{PORTFOLIO_URL}} and {{PAGE_INDICATOR}} and {{SWIPE_TEXT}}. ', 5),
                    'is_cover' => true,
                    'is_cta' => false,
                ],
                [
                    'slide_number' => 2,
                    'layout_hint' => 'human_fingerprint',
                    'copy_id' => 'HF ID',
                    'copy_en' => 'HF EN',
                    'image_prompt' => str_repeat('hf prose. ', 40),
                    'is_cover' => false,
                    'is_cta' => false,
                ],
                [
                    'slide_number' => 3,
                    'layout_hint' => 'body',
                    'copy_id' => 'Body ID',
                    'copy_en' => 'Body EN',
                    'image_prompt' => str_repeat('body prose. ', 40),
                    'is_cover' => false,
                    'is_cta' => false,
                ],
                [
                    'slide_number' => 4,
                    'layout_hint' => 'direct_answer',
                    'copy_id' => 'DA ID',
                    'copy_en' => 'DA EN',
                    'image_prompt' => str_repeat('da prose. ', 40),
                    'is_cover' => false,
                    'is_cta' => false,
                    'direct_answer_block' => str_repeat('Direct answer paragraph optimized for AI search crawlers. ', 4),
                ],
                [
                    'slide_number' => 5,
                    'layout_hint' => 'cta',
                    'copy_id' => 'CTA ID',
                    'copy_en' => 'CTA EN',
                    'image_prompt' => str_repeat('cta prose. ', 40),
                    'is_cover' => false,
                    'is_cta' => true,
                ],
            ],
            'generated_at' => '2026-04-28T10:00:00Z',
        ];
    }

    public function test_repurpose_uses_source_mirror_slides_and_skips_carousel_gen(): void
    {
        config()->set('linkedin.repurpose_source_mirror', true);
        // Source-mirror builder returns canned slides → /carousel-gen is NOT called.
        $fakeBuilder = Mockery::mock(\App\Services\RepurposeCarouselBuilder::class);
        $fakeBuilder->shouldReceive('buildForDraftId')->with(77)->once()->andReturn([
            ['slide_number' => 1, 'layout_hint' => 'cover', 'copy_id' => 'ID', 'copy_en' => 'EN', 'image_prompt' => 'p', 'image_status' => 'pending', 'is_cover' => true],
            ['slide_number' => 2, 'layout_hint' => 'body', 'copy_id' => 'ID2', 'copy_en' => 'EN2', 'image_prompt' => 'p2', 'image_status' => 'pending'],
            ['slide_number' => 3, 'layout_hint' => 'cta', 'copy_id' => 'ID3', 'copy_en' => 'EN3', 'image_prompt' => 'p3', 'image_status' => 'pending', 'is_cta' => true],
        ]);
        app()->instance(\App\Services\RepurposeCarouselBuilder::class, $fakeBuilder);

        $svc = Mockery::mock(LinkedInGenerationService::class . '[dispatchCarouselGenEngine]', [
            Mockery::mock(PipelineGuard::class), new CarouselGenOutputAdapter(),
        ])->makePartial();
        $svc->shouldReceive('dispatchCarouselGenEngine')->never();

        $parsed = ['status' => 'route_to_carousel_gen', 'format' => 'carousel', 'brief' => [], 'carousel' => null];
        $result = $svc->applyCarouselGenAdapter($parsed, 'https://x/blog', 77, null, true, 'sketchnote');

        $this->assertSame('complete', $result['status']);
        $this->assertCount(3, $result['carousel']['slides']);
        $this->assertSame('cover', $result['carousel']['slides'][0]['layout_hint']);
        $this->assertTrue($result['carousel']['bilingual']);
    }

    public function test_repurpose_falls_back_to_carousel_gen_when_no_source_slides(): void
    {
        config()->set('linkedin.repurpose_source_mirror', true);
        // No parseable tool list → builder returns [] → normal /carousel-gen path runs.
        $fakeBuilder = Mockery::mock(\App\Services\RepurposeCarouselBuilder::class);
        $fakeBuilder->shouldReceive('buildForDraftId')->andReturn([]);
        app()->instance(\App\Services\RepurposeCarouselBuilder::class, $fakeBuilder);

        $svc = Mockery::mock(LinkedInGenerationService::class . '[dispatchCarouselGenEngine]', [
            Mockery::mock(PipelineGuard::class), new CarouselGenOutputAdapter(),
        ])->makePartial();
        $svc->shouldReceive('dispatchCarouselGenEngine')->once()->andReturn($this->fakeCarouselGenOutput());

        $parsed = ['status' => 'route_to_carousel_gen', 'format' => 'carousel', 'brief' => [], 'carousel' => null];
        $result = $svc->applyCarouselGenAdapter($parsed, 'https://x/blog', 88, null, true, 'sketchnote');

        $this->assertSame('complete', $result['status']);
        $this->assertCount(5, $result['carousel']['slides']); // from /carousel-gen, not the mirror
    }

    public function test_repurpose_with_flag_off_uses_carousel_gen_not_mirror(): void
    {
        // Default OFF → even a repurpose carousel skips the (slow) source-mirror
        // builder and uses /carousel-gen. Safety gate pending parallelization.
        config()->set('linkedin.repurpose_source_mirror', false);
        $fakeBuilder = Mockery::mock(\App\Services\RepurposeCarouselBuilder::class);
        $fakeBuilder->shouldReceive('buildForDraftId')->never();
        app()->instance(\App\Services\RepurposeCarouselBuilder::class, $fakeBuilder);

        $svc = Mockery::mock(LinkedInGenerationService::class . '[dispatchCarouselGenEngine]', [
            Mockery::mock(PipelineGuard::class), new CarouselGenOutputAdapter(),
        ])->makePartial();
        $svc->shouldReceive('dispatchCarouselGenEngine')->once()->andReturn($this->fakeCarouselGenOutput());

        $parsed = ['status' => 'route_to_carousel_gen', 'format' => 'carousel', 'brief' => [], 'carousel' => null];
        $result = $svc->applyCarouselGenAdapter($parsed, 'https://x/blog', 66, null, true, 'sketchnote');

        $this->assertSame('complete', $result['status']);
    }

    public function test_non_repurpose_carousel_always_uses_carousel_gen(): void
    {
        // Original blog→carousel must NOT touch the source-mirror builder.
        $fakeBuilder = Mockery::mock(\App\Services\RepurposeCarouselBuilder::class);
        $fakeBuilder->shouldReceive('buildForDraftId')->never();
        app()->instance(\App\Services\RepurposeCarouselBuilder::class, $fakeBuilder);

        $svc = Mockery::mock(LinkedInGenerationService::class . '[dispatchCarouselGenEngine]', [
            Mockery::mock(PipelineGuard::class), new CarouselGenOutputAdapter(),
        ])->makePartial();
        $svc->shouldReceive('dispatchCarouselGenEngine')->once()->andReturn($this->fakeCarouselGenOutput());

        $parsed = ['status' => 'route_to_carousel_gen', 'format' => 'carousel', 'brief' => [], 'carousel' => null];
        $result = $svc->applyCarouselGenAdapter($parsed, 'https://x/blog', 99, null, false, 'sketchnote');

        $this->assertSame('complete', $result['status']);
    }
}
