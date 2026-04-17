<?php

namespace Tests\Feature;

use App\Models\ImageGenerationJob;
use App\Services\ImageGenerationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class ImageGenerationDefaultModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.geminigen.api_key', 'test-key');

        Http::fake([
            'api.geminigen.ai/*' => Http::response([
                'uuid' => 'fake-uuid-123',
                'status' => 1,
            ], 200),
        ]);

        // Avoid hitting DB in tests (test env uses SQLite in-memory which some
        // migrations don't support). We only care about the HTTP multipart body.
        $mock = Mockery::mock('alias:' . ImageGenerationJob::class);
        $mock->shouldReceive('create')->andReturn(new ImageGenerationJob());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function modelFromMultipart($request): ?string
    {
        foreach ($request->data() as $part) {
            if (($part['name'] ?? null) === 'model') {
                return $part['contents'] ?? null;
            }
        }
        return null;
    }

    /** @test */
    public function queue_without_explicit_model_uses_config_default()
    {
        Config::set('content.default_image_model', 'nano-banana-pro');

        $service = app(ImageGenerationService::class);
        $uuid = $service->queue(null, 'test prompt');

        $this->assertSame('fake-uuid-123', $uuid);

        $last = Http::recorded()->last()[0];
        $this->assertSame('nano-banana-pro', $this->modelFromMultipart($last));
    }

    /** @test */
    public function queue_with_explicit_model_respects_override()
    {
        Config::set('content.default_image_model', 'nano-banana-pro');

        $service = app(ImageGenerationService::class);
        $service->queue(null, 'test prompt', 'hero', null, 'imagen-4');

        $last = Http::recorded()->last()[0];
        $this->assertSame('imagen-4', $this->modelFromMultipart($last));
    }

    /** @test */
    public function queue_without_config_falls_back_to_hardcoded_default()
    {
        Config::set('content.default_image_model', null);

        $service = app(ImageGenerationService::class);
        $service->queue(null, 'test prompt');

        $last = Http::recorded()->last()[0];
        $this->assertSame('nano-banana-pro', $this->modelFromMultipart($last));
    }
}
