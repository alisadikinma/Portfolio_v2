<?php

namespace Tests\Feature;

use App\Services\GeminiGenVideoService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Phase F: GeminiGenVideoService submit seams route through the bridge when
 * geminigen.use_indusia_video is ON, hitting the right per-family video CLI
 * subcommand (veo/grok) + --mode mapping. Flag OFF keeps the HTTP path.
 */
class VideoIndusiaSubmitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.geminigen.api_key' => 'test-key']);
    }

    private function cmd($p): string
    {
        return is_array($p->command) ? implode(' ', $p->command) : (string) $p->command;
    }

    public function test_flag_on_keyframe_routes_to_image_cli(): void
    {
        config(['geminigen.use_indusia_video' => true]);
        Process::fake(['*' => Process::result(output: 'submitted: uuid=kf-1 status=1')]);

        $uuid = app(GeminiGenVideoService::class)->dispatchKeyframe(['https://x/face.png'], 'a portrait');

        $this->assertSame('kf-1', $uuid);
        Process::assertRan(fn ($p) => str_contains($this->cmd($p), 'scripts.geminigen_image')
            && str_contains($this->cmd($p), '--aspect 9:16'));
    }

    public function test_flag_on_veo_routes_to_veo_subcommand_frame_mode(): void
    {
        config(['geminigen.use_indusia_video' => true]);
        Process::fake(['*' => Process::result(output: 'submitted: uuid=veo-1 status=2 estimated_credit=30')]);

        $uuid = app(GeminiGenVideoService::class)->dispatchVeoClip('https://x/kf.jpg', 'motion prompt', '9:16');

        $this->assertSame('veo-1', $uuid);
        Process::assertRan(fn ($p) => str_contains($this->cmd($p), 'scripts.geminigen_video')
            && str_contains($this->cmd($p), ' veo ')
            && str_contains($this->cmd($p), '--mode frame')
            && str_contains($this->cmd($p), '--aspect 9:16'));
    }

    public function test_flag_on_grok_routes_to_grok_subcommand_custom_mode(): void
    {
        config(['geminigen.use_indusia_video' => true]);
        Process::fake(['*' => Process::result(output: 'submitted: uuid=grok-1 status=1')]);

        $uuid = app(GeminiGenVideoService::class)->dispatchGrokClip('https://x/kf.jpg', 'hook prompt');

        $this->assertSame('grok-1', $uuid);
        Process::assertRan(fn ($p) => str_contains($this->cmd($p), ' grok ')
            && str_contains($this->cmd($p), '--aspect 2:3')
            && str_contains($this->cmd($p), '--mode custom'));
    }

    public function test_flag_off_veo_keeps_http_path(): void
    {
        config(['geminigen.use_indusia_video' => false]);
        Http::fake(['*' => Http::response(['uuid' => 'veo-http', 'status' => 1], 200)]);
        Process::fake();

        $uuid = app(GeminiGenVideoService::class)->dispatchVeoClip('https://x/kf.jpg', 'motion', '9:16');

        $this->assertSame('veo-http', $uuid);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/video-gen/veo'));
        Process::assertNothingRan();
    }
}
