<?php

namespace Tests\Feature;

use App\Services\GeminiGenVideoService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase C (#1 topic-aware hook) — dispatchKeyframe must accept MULTIPLE face
 * references (creator + a public figure as reference image 2) and emit one
 * file_urls multipart entry per URL, while staying back-compatible with the
 * single-string callers (CTA bookend + existing tests).
 *
 * See docs/plans/2026-06-13-video-rebrand-quality-pass.md Phase C.
 */
class KeyframeMultiRefTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.geminigen.api_key', 'test-key');
        config()->set('content.default_image_model', 'nano-banana-pro');
    }

    public function test_array_of_refs_emits_one_file_urls_entry_each(): void
    {
        Http::fake(['*/generate_image' => Http::response(['uuid' => 'kf-multi', 'status' => 0], 200)]);

        $uuid = app(GeminiGenVideoService::class)->dispatchKeyframe(
            ['https://cdn/ali.jpg', 'https://cdn/figure.jpg'],
            'creator on the left, the person matching reference image 2 on the right'
        );

        $this->assertSame('kf-multi', $uuid);

        Http::assertSent(function ($request) {
            $fileUrls = collect($request->data())
                ->filter(fn ($p) => ($p['name'] ?? '') === 'file_urls')
                ->pluck('contents')
                ->all();

            return str_contains($request->url(), '/generate_image')
                && $fileUrls === ['https://cdn/ali.jpg', 'https://cdn/figure.jpg'];
        });
    }

    public function test_single_string_ref_still_works_back_compat(): void
    {
        Http::fake(['*/generate_image' => Http::response(['uuid' => 'kf-single', 'status' => 0], 200)]);

        $uuid = app(GeminiGenVideoService::class)->dispatchKeyframe('https://cdn/ali.jpg', 'portrait');

        $this->assertSame('kf-single', $uuid);

        Http::assertSent(function ($request) {
            $fileUrls = collect($request->data())
                ->filter(fn ($p) => ($p['name'] ?? '') === 'file_urls')
                ->pluck('contents')
                ->all();

            return $fileUrls === ['https://cdn/ali.jpg'];
        });
    }
}
