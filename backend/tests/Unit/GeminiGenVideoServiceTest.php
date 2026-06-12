<?php

namespace Tests\Unit;

use App\Models\InstagramPost;
use App\Services\GeminiGenCircuitBreaker;
use App\Services\GeminiGenVideoService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase B — GROK hook-video dispatch.
 *
 * Locked v7 recipe (Phase 0): model=grok-3, aspect_ratio=2:3, mode=custom,
 * duration=6, resolution=720p, image-to-video via file_urls=<padded JPG frame>,
 * static no-new-objects prompt (no LLM), poll-primary (no webhook field).
 * Circuit-open short-circuits (skip, never throw) — same convention as
 * LinkedInCarouselImageService.
 */
class GeminiGenVideoServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Config::set('services.geminigen.api_key', 'test-key');
        Config::set('services.geminigen.video_model', 'grok-3');
    }

    private function igStub(): InstagramPost
    {
        $ig = new InstagramPost;
        $ig->id = 158;

        return $ig;
    }

    /** Pull a multipart field's value out of a faked outbound request. */
    private function field(array $multipart, string $name): array
    {
        return array_values(array_filter(
            $multipart,
            fn ($p) => ($p['name'] ?? null) === $name
        ));
    }

    /** @test */
    public function dispatch_posts_the_locked_grok_contract(): void
    {
        Http::fake([
            '*/video-gen/grok' => Http::response([
                'id' => 1, 'uuid' => 'grok-uuid-xyz', 'type' => 'video',
                'model_name' => 'grok-video', 'status' => 1, 'estimated_credit' => 5,
            ], 200),
        ]);

        $service = app(GeminiGenVideoService::class);
        $uuid = $service->dispatchHookVideo($this->igStub(), 'https://alisadikinma.com/storage/linkedin-carousel/grok-frame-158.jpg');

        $this->assertSame('grok-uuid-xyz', $uuid);

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/video-gen/grok')) {
                return false;
            }
            if ($request->header('x-api-key')[0] !== 'test-key') {
                return false;
            }
            $mp = $request->data(); // multipart [{name,contents},...]
            $val = fn ($n) => ($this->field($mp, $n)[0]['contents'] ?? null);

            $this->assertSame('grok-3', $val('model'));
            $this->assertSame('2:3', $val('aspect_ratio'));
            $this->assertSame('custom', $val('mode'));
            $this->assertSame('6', (string) $val('duration'));
            $this->assertSame('720p', $val('resolution'));
            $this->assertSame('https://alisadikinma.com/storage/linkedin-carousel/grok-frame-158.jpg', $val('file_urls'));

            $prompt = (string) $val('prompt');
            $this->assertStringNotContainsString(';', $prompt);          // API truncates at ';'
            $this->assertStringNotContainsString('coffee', $prompt);     // non-hardcoded prop
            $this->assertStringContainsString('no new object', strtolower($prompt));

            return true;
        });
    }

    /** @test */
    public function circuit_open_short_circuits_without_http(): void
    {
        Http::fake();

        $breaker = app(GeminiGenCircuitBreaker::class);
        for ($i = 0; $i < 5; $i++) {
            $breaker->recordFailure(503);
        }
        $this->assertSame('open', $breaker->state());

        $uuid = app(GeminiGenVideoService::class)
            ->dispatchHookVideo($this->igStub(), 'https://alisadikinma.com/storage/linkedin-carousel/grok-frame-158.jpg');

        $this->assertNull($uuid);
        Http::assertNothingSent();
    }
}
