<?php

namespace Tests\Feature;

use App\Services\GeminiGenVideoService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase E-orchestration — face-gen keyframe dispatch for video_rebrand hook/CTA.
 * The keyframe is a 9:16 photoreal portrait built from the creator face ref via
 * GeminiGen /generate_image (poll-based, NO webhook — geminigen never fires one).
 * Its finished URL becomes the Veo start frame (mode_image=frame, ref_images).
 *
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md Phase E
 */
class GeminiGenKeyframeDispatchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.geminigen.api_key', 'test-key');
        config()->set('content.default_image_model', 'nano-banana-pro');
    }

    public function test_dispatch_keyframe_posts_to_generate_image_with_face_ref_at_9_16(): void
    {
        Http::fake([
            '*/generate_image' => Http::response(['uuid' => 'kf-abc-123', 'status' => 0], 200),
        ]);

        $uuid = app(GeminiGenVideoService::class)->dispatchKeyframe('https://cdn/face.jpg', 'cinematic brand portrait');

        $this->assertSame('kf-abc-123', $uuid);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/generate_image')
                && collect($request->data())->contains(fn ($p) => ($p['name'] ?? '') === 'aspect_ratio' && $p['contents'] === '9:16')
                && collect($request->data())->contains(fn ($p) => ($p['name'] ?? '') === 'file_urls' && $p['contents'] === 'https://cdn/face.jpg')
                // Poll-based: must NOT register a webhook (geminigen never fires it).
                && ! collect($request->data())->contains(fn ($p) => ($p['name'] ?? '') === 'webhook');
        });
    }

    public function test_dispatch_keyframe_returns_null_on_non_2xx(): void
    {
        Http::fake(['*/generate_image' => Http::response(['error_message' => 'bad'], 422)]);

        $uuid = app(GeminiGenVideoService::class)->dispatchKeyframe('https://cdn/face.jpg', 'x');

        $this->assertNull($uuid);
    }

    public function test_dispatch_keyframe_returns_null_when_no_api_key(): void
    {
        config()->set('services.geminigen.api_key', '');

        $uuid = app(GeminiGenVideoService::class)->dispatchKeyframe('https://cdn/face.jpg', 'x');

        $this->assertNull($uuid);
    }
}
