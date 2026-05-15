<?php

namespace Tests\Feature;

use App\Services\GeminiGenCircuitBreaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class BlogPipelineImageWebhookCircuitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Workaround for documented APP_URL subpath issue in local env
        config(['app.url' => 'http://localhost']);
        URL::forceRootUrl('http://localhost');

        Cache::flush();
    }

    public function test_webhook_records_failure_on_server_error(): void
    {
        $breaker = app(GeminiGenCircuitBreaker::class);
        $this->assertSame(0, $breaker->failureCountInWindow());

        // POST the public webhook endpoint with a server-class failure.
        // UUID won't match any job — that's fine, breaker recording happens
        // before delegation to ImageGenerationService::handleWebhook.
        $payload = [
            'event' => 'IMAGE_GENERATION_FAILED',
            'uuid' => 'test-uuid-not-matching-any-job',
            'data' => [
                'error_code' => 'INTERNAL_SERVER_ERROR',
                'message' => 'Generation backend unavailable',
            ],
        ];

        $response = $this->postJson('/api/automation/blog/image-webhook', $payload);
        $this->assertContains($response->status(), [200, 202, 204]);

        // KEY ASSERTION: breaker recorded the failure
        $this->assertSame(1, $breaker->failureCountInWindow());
    }

    public function test_webhook_does_not_record_failure_on_safety_code(): void
    {
        $breaker = app(GeminiGenCircuitBreaker::class);

        $payload = [
            'event' => 'IMAGE_GENERATION_FAILED',
            'uuid' => 'test-uuid',
            'data' => [
                'error_code' => 'PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD',
                'message' => 'safety refused',
            ],
        ];

        $this->postJson('/api/automation/blog/image-webhook', $payload);

        // Safety errors should NOT touch breaker — they're handled by the
        // April 28 safety-rewrite auto-retry path in ImageGenerationService.
        $this->assertSame(0, $breaker->failureCountInWindow());
    }

    public function test_webhook_success_event_records_breaker_success(): void
    {
        $breaker = app(GeminiGenCircuitBreaker::class);

        // Pre-seed breaker with 1 failure
        $breaker->recordFailure(503);
        $this->assertSame(1, $breaker->failureCountInWindow());

        $payload = [
            'event' => 'IMAGE_GENERATION_COMPLETED',
            'uuid' => 'test-uuid',
            'data' => [
                'generated_image' => [
                    ['image_url' => 'https://example.com/img.png'],
                ],
            ],
        ];

        $this->postJson('/api/automation/blog/image-webhook', $payload);

        // recordSuccess should clear the failure log
        $this->assertSame(0, $breaker->failureCountInWindow());
    }
}
