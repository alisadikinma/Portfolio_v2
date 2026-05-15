<?php

namespace Tests\Feature;

use App\Enums\LinkedInPostStatus;
use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Services\GeminiGenCircuitBreaker;
use App\Services\LinkedInCarouselImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase E — circuit breaker integration into LinkedInCarouselImageService.
 *
 * Uses RefreshDatabase + SQLite in-memory (phpunit.xml) — clean DB per test,
 * no global state collisions. Cache::flush() resets breaker state.
 *
 * Contract tested:
 *  1. state=open → no HTTP sent for any slide; slide status stays 'pending'
 *  2. state=open → dispatchSingleSlide skips without status change
 *  3. webhook with server-class IMAGE_GENERATION_FAILED → breaker count++
 *  4. webhook with prompt-class safety code → breaker count unchanged
 */
class LinkedInCarouselImageServiceCircuitBreakerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        Config::set('services.geminigen.api_key', 'test-key');
        Config::set('content.default_image_model', 'nano-banana-pro');
        Config::set('services.geminigen.linkedin_carousel_model', 'nano-banana-pro');
        // Disable safety rewrite — irrelevant for breaker tests and avoids
        // synchronous Sonnet SSH dispatch on the webhook failure path.
        Config::set('services.article_generation.use_safety_rewrite', false);
    }

    private function makeDraft(array $slides = null): LinkedInPost
    {
        $category = Category::create([
            'name' => 'Test',
            'slug' => 'test-' . uniqid(),
        ]);
        $post = Post::create([
            'category_id' => $category->id,
            'slug' => 'test-post-' . uniqid(),
            'title' => 'Test Post',
            'content' => 'Test content body.',
        ]);

        $slides = $slides ?? [
            [
                'slide_number' => 1,
                'layout_hint' => 'cover',
                'copy' => 'Cover headline',
                'image_prompt' => 'A clean modern photograph.',
                'image_status' => 'pending',
            ],
            [
                'slide_number' => 2,
                'layout_hint' => 'body',
                'copy' => 'Body copy',
                'image_prompt' => 'A workspace photograph.',
                'image_status' => 'pending',
            ],
        ];

        return LinkedInPost::create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'content' => 'Test caption.',
            'carousel_slides' => $slides,
            'status' => LinkedInPostStatus::ManualReview->value,
            'pipeline_state_log' => [],
            'hashtags' => [],
        ]);
    }

    /** @test */
    public function dispatch_all_slides_skips_http_when_circuit_open(): void
    {
        Http::fake();

        $breaker = app(GeminiGenCircuitBreaker::class);
        for ($i = 0; $i < 5; $i++) {
            $breaker->recordFailure(503);
        }
        $this->assertSame('open', $breaker->state());

        $draft = $this->makeDraft();
        $service = app(LinkedInCarouselImageService::class);
        $dispatched = $service->dispatchAllSlides($draft);

        $this->assertSame(0, $dispatched);
        Http::assertNothingSent();

        // Slides must stay 'pending' — not flipped to 'failed', not 'generating'.
        $draft->refresh();
        foreach ($draft->carousel_slides as $slide) {
            $this->assertSame('pending', $slide['image_status']);
        }
    }

    /** @test */
    public function dispatch_single_slide_skips_when_circuit_open(): void
    {
        Http::fake();

        $breaker = app(GeminiGenCircuitBreaker::class);
        for ($i = 0; $i < 5; $i++) {
            $breaker->recordFailure(503);
        }
        $this->assertSame('open', $breaker->state());

        $draft = $this->makeDraft();
        $service = app(LinkedInCarouselImageService::class);
        $uuid = $service->dispatchSingleSlide($draft, 0);

        $this->assertNull($uuid);
        Http::assertNothingSent();

        $draft->refresh();
        $this->assertSame('pending', $draft->carousel_slides[0]['image_status']);
    }

    /** @test */
    public function webhook_records_failure_on_server_error_event(): void
    {
        $breaker = app(GeminiGenCircuitBreaker::class);
        $this->assertSame(0, $breaker->failureCountInWindow());

        // Webhook with INTERNAL_SERVER_ERROR (server-class — counts toward trip).
        // UUID is unknown so handleWebhook bails after recording breaker failure.
        $payload = [
            'error_message' => 'INTERNAL_SERVER_ERROR: GeminiGen down',
            'error_code' => 'INTERNAL_SERVER_ERROR',
        ];

        app(LinkedInCarouselImageService::class)->handleWebhook(
            'unknown-uuid-for-breaker-test',
            'IMAGE_GENERATION_FAILED',
            $payload
        );

        $this->assertSame(1, $breaker->failureCountInWindow());
    }

    /** @test */
    public function webhook_does_not_record_failure_on_safety_code(): void
    {
        $breaker = app(GeminiGenCircuitBreaker::class);

        // Prompt-class safety failure — should NOT count toward circuit trip.
        $payload = [
            'error_message' => 'PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD: refused',
            'error_code' => 'PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD',
        ];

        app(LinkedInCarouselImageService::class)->handleWebhook(
            'unknown-uuid-for-breaker-test',
            'IMAGE_GENERATION_FAILED',
            $payload
        );

        $this->assertSame(0, $breaker->failureCountInWindow());
        $this->assertSame('closed', $breaker->state());
    }
}
