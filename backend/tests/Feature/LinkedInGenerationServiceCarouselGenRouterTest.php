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
 * Phase A6 of LinkedIn Carousel Engine Decoupling. The applyCarouselGenAdapter
 * method is the bridge between the legacy /linkedin-gen orchestrator output
 * and the new /carousel-gen pipeline-mode output. Behavior depends on the
 * `linkedin.use_carousel_gen_engine` feature flag:
 *
 *   - flag OFF  → return parsed unchanged (legacy /linkedin-carousel inline path)
 *   - flag ON  + format='text'     → return parsed unchanged (no /carousel-gen)
 *   - flag ON  + format='carousel' → dispatch /carousel-gen, run adapter,
 *                                    REPLACE parsed.carousel.slides with
 *                                    adapter output. Brief + validation kept.
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

    private function makeServiceWithFlag(bool $flagEnabled): LinkedInGenerationService
    {
        config()->set('linkedin.use_carousel_gen_engine', $flagEnabled);
        config()->set('carousel-gen.driver', 'ssh');
        config()->set('carousel-gen.timeout_seconds', 600);

        $guard = Mockery::mock(PipelineGuard::class);
        $adapter = new CarouselGenOutputAdapter();
        return new LinkedInGenerationService($guard, $adapter);
    }

    public function test_returns_parsed_unchanged_when_feature_flag_off(): void
    {
        $svc = $this->makeServiceWithFlag(false);
        $parsed = [
            'status' => 'complete',
            'format' => 'carousel',
            'brief' => ['hook_framework' => 'PAS', 'pillar' => 'ai_generalist'],
            'carousel' => [
                'slides' => [
                    ['slide_number' => 1, 'layout_hint' => 'cover', 'copy' => 'legacy'],
                ],
            ],
            'validation' => ['depth_score' => 85, 'passed' => true],
            'generated_at' => '2026-04-28T10:00:00Z',
        ];

        $result = $svc->applyCarouselGenAdapter($parsed, 'https://example.com/blog/x', 42);

        // Flag OFF → no /carousel-gen call, no adapter run. Slides untouched.
        $this->assertSame($parsed, $result);
    }

    public function test_returns_parsed_unchanged_when_format_is_text(): void
    {
        $svc = $this->makeServiceWithFlag(true);
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

        // Flag ON but text format → /carousel-gen NOT called.
        $this->assertSame($parsed, $result);
    }

    public function test_replaces_slides_when_flag_on_and_carousel_format(): void
    {
        $svc = Mockery::mock(LinkedInGenerationService::class . '[dispatchCarouselGenEngine]', [
            Mockery::mock(PipelineGuard::class),
            new CarouselGenOutputAdapter(),
        ])->makePartial();
        config()->set('linkedin.use_carousel_gen_engine', true);

        // Mocked /carousel-gen output (matches CarouselGenOutputSchema).
        $carouselGenOutput = [
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

        $svc->shouldReceive('dispatchCarouselGenEngine')
            ->once()
            ->andReturn($carouselGenOutput);

        $parsed = [
            'status' => 'complete',
            'format' => 'carousel',
            'brief' => ['hook_framework' => 'PAS', 'pillar' => 'ai_generalist'],
            'carousel' => [
                'slides' => [
                    // Old /linkedin-gen output that should be REPLACED.
                    ['slide_number' => 1, 'layout_hint' => 'cover', 'copy' => 'old legacy slide'],
                ],
            ],
            'validation' => ['depth_score' => 85, 'passed' => true],
            'generated_at' => '2026-04-28T10:00:00Z',
        ];

        $result = $svc->applyCarouselGenAdapter($parsed, 'https://example.com/blog/x', 42);

        // Slides replaced with adapter output (5 slides, correctly shaped).
        $this->assertCount(5, $result['carousel']['slides']);
        $this->assertSame('cover', $result['carousel']['slides'][0]['layout_hint']);
        $this->assertSame('Cover ID', $result['carousel']['slides'][0]['copy_id']);
        $this->assertSame('Cover EN', $result['carousel']['slides'][0]['copy_en']);
        $this->assertSame('pending', $result['carousel']['slides'][0]['image_status']);
        // Validation + brief preserved.
        $this->assertSame(85, $result['validation']['depth_score']);
        $this->assertSame('PAS', $result['brief']['hook_framework']);
    }

    public function test_throws_carousel_gen_adapter_exception_when_carousel_gen_returns_failed(): void
    {
        $svc = Mockery::mock(LinkedInGenerationService::class . '[dispatchCarouselGenEngine]', [
            Mockery::mock(PipelineGuard::class),
            new CarouselGenOutputAdapter(),
        ])->makePartial();
        config()->set('linkedin.use_carousel_gen_engine', true);

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
            'status' => 'complete',
            'format' => 'carousel',
            'brief' => ['hook_framework' => 'PAS'],
            'carousel' => ['slides' => []],
            'validation' => ['depth_score' => 75, 'passed' => false],
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
        config()->set('linkedin.use_carousel_gen_engine', true);

        // SSH/parse failure surfaces as null.
        $svc->shouldReceive('dispatchCarouselGenEngine')
            ->once()
            ->andReturn(null);

        $parsed = [
            'status' => 'complete',
            'format' => 'carousel',
            'brief' => ['hook_framework' => 'PAS'],
            'carousel' => ['slides' => []],
            'validation' => ['depth_score' => 80, 'passed' => true],
            'generated_at' => '2026-04-28T10:00:00Z',
        ];

        $this->expectException(CarouselGenAdapterException::class);
        $this->expectExceptionMessageMatches('/dispatch failed|null|empty/i');

        $svc->applyCarouselGenAdapter($parsed, 'https://example.com/blog/x', 42);
    }
}
