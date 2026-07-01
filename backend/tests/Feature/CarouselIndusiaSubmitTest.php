<?php

namespace Tests\Feature;

use App\Models\LinkedInPost;
use App\Models\Setting;
use App\Services\CarouselSlideEnhancer;
use App\Services\LinkedInCarouselImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Phase D: carousel slide submit routes through the bridge when
 * geminigen.use_indusia_images is ON, using the operator-configured model
 * (linkedin_carousel_image_model). gpt-image-2 gets --mode. Flag OFF = HTTP.
 */
class CarouselIndusiaSubmitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.geminigen.api_key' => 'test-key']);

        // Instance mock (NOT alias) — enhancer returns a ready enhanced slide.
        $enh = Mockery::mock(CarouselSlideEnhancer::class);
        $enh->shouldReceive('enhance')->andReturn([
            'prompt_text' => 'a knowledge-first infographic slide',
            'layout_hint' => 'body',
            'face_refs' => ['https://x/face.png'],
            'file_urls' => [],
        ]);
        $this->app->instance(CarouselSlideEnhancer::class, $enh);
    }

    private function dispatchOne(): ?string
    {
        $draft = LinkedInPost::factory()->create();
        $svc = app(LinkedInCarouselImageService::class);
        $m = new ReflectionMethod($svc, 'dispatchOne');
        $m->setAccessible(true);

        return $m->invoke($svc, $draft, ['slide_number' => 1], 0, 5);
    }

    private function cmd($p): string
    {
        return is_array($p->command) ? implode(' ', $p->command) : (string) $p->command;
    }

    public function test_resolve_model_reads_the_operator_setting(): void
    {
        Setting::create(['group' => 'linkedin', 'key' => 'linkedin_carousel_image_model', 'value' => 'gpt-image-2', 'type' => 'text']);

        $svc = app(LinkedInCarouselImageService::class);
        $rm = new ReflectionMethod($svc, 'resolveModel');
        $rm->setAccessible(true);

        $this->assertSame('gpt-image-2', $rm->invoke($svc));
    }

    public function test_flag_on_gpt_image_2_routes_to_bridge_with_mode(): void
    {
        config(['geminigen.use_indusia_images' => true]);
        Setting::create(['group' => 'linkedin', 'key' => 'linkedin_carousel_image_model', 'value' => 'gpt-image-2', 'type' => 'text']);
        Http::fake(['*' => Http::response('PNGBYTES', 200)]); // materializeRef fetch
        Process::fake(['*' => Process::result(output: 'submitted: uuid=carousel-1 status=1')]);

        $uuid = $this->dispatchOne();

        $this->assertSame('carousel-1', $uuid);
        Process::assertRan(fn ($p) => str_contains($this->cmd($p), '--model gpt-image-2')
            && str_contains($this->cmd($p), '--mode medium'));
        $this->assertDatabaseHas('image_generation_jobs', ['uuid' => 'carousel-1', 'type' => 'carousel_slide']);
    }

    public function test_flag_off_default_model_keeps_http(): void
    {
        config(['geminigen.use_indusia_images' => false]);
        Http::fake(['*' => Http::response(['uuid' => 'carousel-http', 'status' => 1], 200)]);
        Process::fake();

        $uuid = $this->dispatchOne();

        $this->assertSame('carousel-http', $uuid);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/generate_image'));
        Process::assertNothingRan();
    }
}
