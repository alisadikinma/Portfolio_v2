<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\ImageGenerationJob;
use App\Models\Setting;
use App\Services\ImageGenerationService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\TestCase;

class ImageGenerationTriggerForIdeaTest extends TestCase
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
            'api.snapgen.ai/*' => Http::sequence()
                ->push(['uuid' => 'uuid-1', 'status' => 1], 200)
                ->push(['uuid' => 'uuid-2', 'status' => 1], 200)
                ->push(['uuid' => 'uuid-3', 'status' => 1], 200)
                ->push(['uuid' => 'uuid-4', 'status' => 1], 200),
        ]);

        // Avoid DB writes for ImageGenerationJob::create + stub collision lookup for buildBrandedFilename
        $jobMock = Mockery::mock('alias:' . ImageGenerationJob::class);
        $jobMock->shouldReceive('create')->andReturn(new ImageGenerationJob());
        $collisionQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $collisionQuery->shouldReceive('exists')->andReturn(false);
        $jobMock->shouldReceive('where')->with('planned_filename', Mockery::any())->andReturn($collisionQuery);

        // Mock Setting::where(...)->value(...) for CoverBrandingEnhancer (profile_photo)
        $aboutQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $aboutQuery->shouldReceive('where')->with('key', 'profile_photo')->andReturnSelf();
        $aboutQuery->shouldReceive('value')->with('value')->andReturn(null);

        // creator_brand group — slug resolves to 'alisadikinma' for filename helper,
        // all other keys (watermark_enabled, logo, tagline, opacity) resolve to null
        // so watermark append is a no-op in these tests.
        $brandQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $brandQuery->shouldReceive('where')->andReturnUsing(function ($col, $key) {
            $stub = Mockery::mock('Illuminate\Database\Eloquent\Builder');
            $stub->shouldReceive('value')->with('value')->andReturn(
                $key === 'creator_brand_slug' ? 'alisadikinma' : null
            );
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
        $idea->id = 42;
        $idea->title = 'Demo';
        $idea->status = 'article_ready';
        $idea->generated_article = [
            'language' => 'id',
            'id' => ['title' => 'Demo Title'],
            'image_prompts' => $imagePrompts,
        ];
        // save() is called when dispatched>0 — stub to succeed without DB
        $idea->shouldReceive('save')->andReturn(true);
        return $idea;
    }

    /** @test */
    public function returns_zero_when_idea_has_no_image_prompts()
    {
        $idea = $this->makeIdea([]);

        $service = app(ImageGenerationService::class);
        $count = $service->triggerForIdea($idea);

        $this->assertSame(0, $count);
    }

    /** @test */
    public function dispatches_jobs_for_each_prompt_and_attaches_uuids()
    {
        $idea = $this->makeIdea([
            [
                'type' => 'cover',
                'visual_direction' => 'abstract dashboard',
                'prompt_text' => 'Modern dashboard view',
            ],
            [
                'type' => 'inline',
                'prompt_text' => 'Code snippet illustration',
            ],
        ]);

        $service = app(ImageGenerationService::class);
        $count = $service->triggerForIdea($idea);

        $this->assertSame(2, $count);

        $prompts = $idea->generated_article['image_prompts'];
        $this->assertSame('uuid-1', $prompts[0]['job_uuid']);
        $this->assertSame('uuid-1', $prompts[0]['variations'][0]['job_uuid']);
        $this->assertSame('generating', $prompts[0]['variations'][0]['status']);
        $this->assertSame('uuid-2', $prompts[1]['job_uuid']);
    }

    /** @test */
    public function skips_prompts_that_already_have_a_done_variation()
    {
        $idea = $this->makeIdea([
            [
                'type' => 'cover',
                'prompt_text' => 'Already done cover',
                'variations' => [
                    ['url' => 'https://example.com/x.jpg', 'job_uuid' => 'old', 'status' => 'done'],
                ],
            ],
            [
                'type' => 'inline',
                'prompt_text' => 'Still needs gen',
            ],
        ]);

        $service = app(ImageGenerationService::class);
        $count = $service->triggerForIdea($idea);

        // Only segment 1 (inline) should be dispatched
        $this->assertSame(1, $count);
        $prompts = $idea->generated_article['image_prompts'];
        // First prompt untouched (still 'done')
        $this->assertSame('done', $prompts[0]['variations'][0]['status']);
        $this->assertSame('old', $prompts[0]['variations'][0]['job_uuid']);
        // Second prompt got a new uuid
        $this->assertSame('uuid-1', $prompts[1]['job_uuid']);
    }

    /** @test */
    public function flips_status_to_generating_images_when_dispatched()
    {
        $idea = $this->makeIdea([
            ['type' => 'cover', 'prompt_text' => 'test'],
        ]);
        $this->assertSame('article_ready', $idea->status);

        $service = app(ImageGenerationService::class);
        $service->triggerForIdea($idea);

        $this->assertSame('generating_images', $idea->status);
    }

    /** @test */
    public function does_not_flip_status_when_zero_dispatched()
    {
        $idea = $this->makeIdea([
            [
                'type' => 'cover',
                'variations' => [['url' => 'x', 'status' => 'done']],
            ],
        ]);

        $service = app(ImageGenerationService::class);
        $count = $service->triggerForIdea($idea);

        $this->assertSame(0, $count);
        $this->assertSame('article_ready', $idea->status);
    }

}
