<?php

namespace Tests\Feature;

use App\Services\GeminiGenVideoService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Phase E — Veo image-to-video dispatch + finalize for video_rebrand hook/CTA
 * clips. Contract verified live on the prod VPS (2026-06-12):
 *   POST /uapi/v1/video-gen/veo  (required prompt+model; aspect_ratio/duration/
 *   resolution/mode_image optional; keyframe via ref_images) → {uuid, ...}.
 *
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md Phase E
 */
class GeminiGenVeoDispatchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.geminigen.api_key', 'test-key');
        config()->set('services.geminigen.veo_model', 'veo-3.1-fast');
    }

    public function test_dispatch_veo_posts_to_veo_endpoint_and_returns_uuid(): void
    {
        Http::fake([
            '*/video-gen/veo' => Http::response(['uuid' => 'veo-abc-123', 'status' => 0], 200),
        ]);

        $uuid = app(GeminiGenVideoService::class)->dispatchVeoClip('https://cdn/keyframe.jpg', 'cinematic brand hook', '9:16');

        $this->assertSame('veo-abc-123', $uuid);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/video-gen/veo')
                && collect($request->data())->contains(fn ($p) => ($p['name'] ?? '') === 'model' && $p['contents'] === 'veo-3.1-fast')
                && collect($request->data())->contains(fn ($p) => ($p['name'] ?? '') === 'aspect_ratio' && $p['contents'] === '9:16')
                && collect($request->data())->contains(fn ($p) => ($p['name'] ?? '') === 'ref_images' && $p['contents'] === 'https://cdn/keyframe.jpg')
                && collect($request->data())->contains(fn ($p) => ($p['name'] ?? '') === 'mode_image' && $p['contents'] === 'frame');
        });
    }

    public function test_dispatch_veo_returns_null_on_non_2xx(): void
    {
        Http::fake(['*/video-gen/veo' => Http::response(['error_message' => 'bad'], 422)]);

        $uuid = app(GeminiGenVideoService::class)->dispatchVeoClip('https://cdn/keyframe.jpg', 'x', '9:16');

        $this->assertNull($uuid);
    }

    public function test_dispatch_veo_returns_null_when_no_api_key(): void
    {
        config()->set('services.geminigen.api_key', '');

        $uuid = app(GeminiGenVideoService::class)->dispatchVeoClip('https://cdn/keyframe.jpg', 'x', '9:16');

        $this->assertNull($uuid);
    }

    public function test_finalize_veo_issues_4x5_crop_scale_command(): void
    {
        // Process::fake means ffmpeg never actually writes the output file, so
        // finalizeVeoClip returns null (its is_file guard). The value under test
        // is the ffmpeg command it constructs (9:16 → 4:5 crop + 1080×1350 scale).
        Http::fake(['*' => Http::response('FAKEVIDEOBYTES', 200)]);
        Process::fake(['*' => Process::result(output: '', exitCode: 0)]);

        app(GeminiGenVideoService::class)->finalizeVeoClip('https://cdn/out.mp4', 'repurpose/9/veo/hook.mp4');

        Process::assertRan(function ($process) {
            $cmd = is_array($process->command) ? implode(' ', $process->command) : $process->command;
            return str_contains($cmd, 'crop=iw:floor(iw*5/4/2)*2')
                && str_contains($cmd, 'scale=1080:1350')
                && str_contains($cmd, 'libx264');
        });
    }
}
