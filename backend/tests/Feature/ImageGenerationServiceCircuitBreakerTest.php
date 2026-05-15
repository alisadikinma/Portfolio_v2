<?php

namespace Tests\Feature;

use App\Enums\ContentIdeaStatus;
use App\Exceptions\GeminiGenCircuitOpenException;
use App\Models\ContentIdea;
use App\Models\Setting;
use App\Services\GeminiGenCircuitBreaker;
use App\Services\ImageGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase D — circuit breaker integration into ImageGenerationService.
 *
 * Uses RefreshDatabase + real DB rows (no alias mocks — alias mocks fight
 * Laravel's autoloader because Setting is referenced during framework boot,
 * making the alias mock unable to replace an already-loaded class). The
 * cost is ~1s of MySQL refresh per test; the benefit is honest assertions
 * about real service behavior end-to-end.
 *
 * Pattern mirrors GeminiGenRelayWebhookTest (May 14 ship).
 *
 * Process isolation: sibling ImageGenerationDefaultModelTest +
 * ImageGenerationTriggerForIdeaTest use alias-mocks on Setting +
 * ImageGenerationJob. When phpunit runs them in the same process before
 * us, those alias-mocks linger and corrupt our HTTP interaction (the
 * fake 503 doesn't propagate cleanly through the swapped-out classes).
 * @runTestsInSeparateProcesses + @preserveGlobalState disabled gives
 * each test method a fresh PHP interpreter so the breaker side-effects
 * are observable. Costs ~0.5s per method — acceptable for 4 tests.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ImageGenerationServiceCircuitBreakerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Reset breaker state — cached in Cache facade.
        Cache::flush();

        Config::set('services.geminigen.api_key', 'test-key');
        Config::set('content.default_image_model', 'nano-banana-pro');
        Config::set('content.cover_branding.enabled', false);  // Skip enhancer DB lookups
        Config::set('services.article_generation.use_safety_rewrite', false);

        // Seed minimal creator_brand row so buildBrandedFilename() resolves slug.
        Setting::create([
            'group' => 'creator_brand',
            'key' => 'creator_brand_slug',
            'value' => 'alisadikinma',
        ]);
    }

    private function makeIdea(array $imagePrompts = null): ContentIdea
    {
        $imagePrompts = $imagePrompts ?? [
            [
                'type' => 'cover',
                'prompt_text' => 'Modern dashboard view',
            ],
        ];

        return ContentIdea::create([
            'title' => 'Demo idea',
            'pillar' => 'ai_automation',
            // Use 'draft' (not 'article_ready') because the SQLite test DB keeps
            // the original CHECK constraint — the MySQL-only ALTER TABLE migrations
            // that expand the enum are no-ops on SQLite. triggerForIdea() only
            // transitions when status === 'article_ready'; with 'draft' it just
            // saves the idea without an FSM transition — fine for these breaker
            // assertions which are scoped to HTTP gate + recordOutcome behavior.
            'status' => 'draft',
            'languages' => ['id'],
            'output_types' => ['article'],
            'generated_article' => [
                'language' => 'id',
                'id' => ['title' => 'Demo Title'],
                'image_prompts' => $imagePrompts,
            ],
        ]);
    }

    /** @test */
    public function dispatch_proceeds_when_circuit_closed(): void
    {
        Http::fake([
            'api.geminigen.ai/*' => Http::response(['uuid' => 'uuid-1', 'status' => 1], 200),
        ]);

        $breaker = app(GeminiGenCircuitBreaker::class);
        $this->assertSame('closed', $breaker->state());

        $idea = $this->makeIdea();
        $service = app(ImageGenerationService::class);
        $count = $service->triggerForIdea($idea);

        $this->assertSame(1, $count);
        Http::assertSent(fn ($req) => str_contains($req->url(), 'geminigen'));
        // Success should have been recorded — failures stay at zero.
        $this->assertSame(0, $breaker->failureCountInWindow());
        $this->assertSame('closed', $breaker->state());
    }

    /** @test */
    public function throws_circuit_open_exception_when_state_is_open(): void
    {
        Http::fake();

        // Trip the breaker manually by recording 5 failures.
        $breaker = app(GeminiGenCircuitBreaker::class);
        for ($i = 0; $i < 5; $i++) {
            $breaker->recordFailure(503);
        }
        $this->assertSame('open', $breaker->state());

        $idea = $this->makeIdea();
        $caught = false;
        try {
            app(ImageGenerationService::class)->triggerForIdea($idea);
        } catch (GeminiGenCircuitOpenException $e) {
            $caught = true;
        }

        $this->assertTrue($caught, 'Expected GeminiGenCircuitOpenException to be thrown.');
        // Critical: no HTTP fired because the gate blocked.
        Http::assertNothingSent();
    }

    /** @test */
    public function records_failure_on_503_response(): void
    {
        Http::fake([
            'api.geminigen.ai/*' => Http::response('Service Unavailable', 503),
        ]);

        $breaker = app(GeminiGenCircuitBreaker::class);
        $this->assertSame(0, $breaker->failureCountInWindow());

        $idea = $this->makeIdea();
        try {
            app(ImageGenerationService::class)->triggerForIdea($idea);
        } catch (\Throwable $e) {
            // queue() returns null on non-2xx; no exception expected, but defensive.
        }

        $this->assertSame(1, $breaker->failureCountInWindow());
    }

    /** @test */
    public function does_not_record_failure_on_safety_4xx(): void
    {
        // GeminiGen returns 400 with PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD when
        // the prompt names a public figure. Handled by the April 28 safety
        // auto-rewrite — NOT an outage signal, MUST NOT trip the breaker.
        Http::fake([
            'api.geminigen.ai/*' => Http::response(
                ['error' => 'PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD'],
                400
            ),
        ]);

        $breaker = app(GeminiGenCircuitBreaker::class);

        $idea = $this->makeIdea();
        try {
            app(ImageGenerationService::class)->triggerForIdea($idea);
        } catch (\Throwable $e) {
            // queue() returns null on non-2xx.
        }

        $this->assertSame(0, $breaker->failureCountInWindow());
        $this->assertSame('closed', $breaker->state());
    }
}
