<?php

namespace Tests\Feature;

use App\Services\ImageGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Phase C: the blog image submit routes through GeminiGenClientBridge when
 * geminigen.use_indusia_images is ON, and keeps the HTTP path when OFF.
 * Tests the private queue() seam directly (avoids the enhancer/Setting path).
 */
class ImageGenIndusiaSubmitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // queue() early-guards on an empty app-side key; prod always sets it
        // (the poll cron's history GET needs it too). The indusia path ignores
        // it — the client resolves its own key on the VPS.
        config(['services.geminigen.api_key' => 'test-key']);
    }

    private function queue(ImageGenerationService $svc): ?string
    {
        $m = new ReflectionMethod($svc, 'queue');
        $m->setAccessible(true);

        // postId, prompt, type, insertAfterHeading, model, aspectRatio, style,
        // faceRefs, styleRefs, additionalNotes, plannedFilename
        return $m->invoke(
            $svc, null, 'a photorealistic cat', 'hero', null,
            'nano-banana-pro', '16:9', 'Photorealistic',
            ['https://x/face.png'], [], '', 'alisadikinma-test-cover.png'
        );
    }

    public function test_flag_on_routes_submit_through_bridge_not_http(): void
    {
        config(['geminigen.use_indusia_images' => true]);
        Process::fake(['*' => Process::result(output: 'submitted: uuid=indusia-uuid-1 status=1')]);
        Http::fake(); // any HTTP submit would be a bug

        $uuid = $this->queue(app(ImageGenerationService::class));

        $this->assertSame('indusia-uuid-1', $uuid);
        Process::assertRan(fn ($p) => str_contains(
            is_array($p->command) ? implode(' ', $p->command) : (string) $p->command,
            'scripts.geminigen_image'
        ));
        Http::assertNothingSent();
        $this->assertDatabaseHas('image_generation_jobs', ['uuid' => 'indusia-uuid-1', 'status' => 'processing']);
    }

    public function test_flag_off_keeps_http_path(): void
    {
        config(['geminigen.use_indusia_images' => false]);
        Http::fake(['*' => Http::response(['uuid' => 'http-uuid-2', 'status' => 1], 200)]);
        Process::fake(); // any Process run would be a bug

        $uuid = $this->queue(app(ImageGenerationService::class));

        $this->assertSame('http-uuid-2', $uuid);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/generate_image'));
        Process::assertNothingRan();
        $this->assertDatabaseHas('image_generation_jobs', ['uuid' => 'http-uuid-2', 'status' => 'processing']);
    }
}
