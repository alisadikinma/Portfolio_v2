<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\ImageGenerationJob;
use App\Models\Setting;
use App\Services\ImageGenerationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

/**
 * Regression guard: the watermark logo URL that CoverBrandingEnhancer pushes
 * into $prompt['file_urls'] must actually ship as a multipart file_urls part
 * in the outbound GeminiGen request. Before the fix, both dispatch paths
 * (triggerForIdea + generateSegmentImage) ignored $enhanced['file_urls'],
 * so the model received the "apply a centered semi-transparent watermark
 * using the provided brand logo reference image" instruction with NO
 * reference image — it fabricated an "AD" letterform from the brand slug.
 *
 * This test lives in its own class so its alias-mocked Setting + enabled
 * watermark settings don't collide with the broader ImageGenerationTriggerForIdeaTest
 * setUp (which aliases Setting with watermark disabled).
 */
class WatermarkLogoDispatchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.geminigen.api_key', 'test-key');
        Config::set('content.default_image_model', 'nano-banana-pro');
        Config::set('content.cover_branding.enabled', true);
        Config::set('content.cover_branding.model', 'nano-banana-pro');
        Config::set('content.cover_branding.title_max_len', 70);

        Http::fake([
            'api.snapgen.ai/*' => Http::response(['uuid' => 'uuid-watermark', 'status' => 1], 200),
        ]);

        Storage::fake('public');
        Storage::disk('public')->put('branding/logo.png', 'fake-logo-bytes');

        $jobMock = Mockery::mock('alias:' . ImageGenerationJob::class);
        $jobMock->shouldReceive('create')->andReturn(new ImageGenerationJob());
        $collisionQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $collisionQuery->shouldReceive('exists')->andReturn(false);
        $jobMock->shouldReceive('where')->with('planned_filename', Mockery::any())->andReturn($collisionQuery);

        $aboutQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $aboutQuery->shouldReceive('where')->with('key', 'profile_photo')->andReturnSelf();
        $aboutQuery->shouldReceive('value')->with('value')->andReturn(null);

        // Brand settings: watermark ENABLED + logo present on disk.
        $brandValues = [
            'watermark_enabled' => 'true',
            'creator_brand_logo' => 'branding/logo.png',
            'creator_brand_tagline' => 'alisadikinma.com',
            'watermark_opacity' => '0.30',
            'creator_brand_slug' => 'alisadikinma',
        ];
        $brandQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $brandQuery->shouldReceive('where')->andReturnUsing(function ($col, $key) use ($brandValues) {
            $stub = Mockery::mock('Illuminate\Database\Eloquent\Builder');
            $stub->shouldReceive('value')->with('value')->andReturn($brandValues[$key] ?? null);
            return $stub;
        });

        $settingMock = Mockery::mock('alias:' . Setting::class);
        $settingMock->shouldReceive('where')->with('group', 'about')->andReturn($aboutQuery);
        $settingMock->shouldReceive('where')->with('group', 'creator_brand')->andReturn($brandQuery);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeIdea(array $imagePrompts): ContentIdea
    {
        $idea = Mockery::mock(ContentIdea::class)->makePartial();
        $idea->id = 101;
        $idea->title = 'Demo';
        $idea->status = 'article_ready';
        $idea->generated_article = [
            'language' => 'id',
            'id' => ['title' => 'Demo Title'],
            'image_prompts' => $imagePrompts,
        ];
        $idea->shouldReceive('save')->andReturn(true);
        return $idea;
    }

    /** @test */
    public function trigger_for_idea_ships_watermark_logo_url_as_multipart_file_url(): void
    {
        $idea = $this->makeIdea([
            ['type' => 'cover', 'prompt_text' => 'A dashboard cover'],
        ]);

        app(ImageGenerationService::class)->triggerForIdea($idea);

        Http::assertSent(function ($request) {
            $body = (string) $request->body();

            // Parse all parts with name="file_urls" and capture their values.
            // Naive str_contains on the whole body would pass even if the logo
            // path leaked into the prompt text (false-pass on regression) —
            // this targets file_urls parts specifically. Laravel's multipart
            // emits Content-Disposition + Content-Length headers before the
            // blank line separating headers from the part body, so we skip
            // any number of intermediate headers with the non-greedy block.
            preg_match_all(
                '/name="file_urls"[\s\S]*?\r\n\r\n([^\r\n]+)/',
                $body,
                $matches
            );
            $fileUrlValues = $matches[1] ?? [];

            return collect($fileUrlValues)
                ->contains(fn ($u) => str_contains($u, 'branding/logo.png'));
        });
    }
}
