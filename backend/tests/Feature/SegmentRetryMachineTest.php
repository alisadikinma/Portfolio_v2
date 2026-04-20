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

/**
 * Unit-style tests for ImageGenerationService::retrySegment.
 * Uses alias mocks for Setting + ImageGenerationJob so queue() doesn't
 * touch the DB. Does NOT use RefreshDatabase.
 */
class SegmentRetryMachineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.geminigen.api_key', 'test-key');
        Config::set('content.default_image_model', 'nano-banana-pro');
        Config::set('content.cover_branding.enabled', false);

        Http::fake([
            'api.geminigen.ai/*' => Http::sequence()
                ->push(['uuid' => 'retry-uuid-1', 'status' => 1], 200)
                ->push(['uuid' => 'retry-uuid-2', 'status' => 1], 200)
                ->push(['uuid' => 'retry-uuid-3', 'status' => 1], 200),
        ]);

        $jobMock = Mockery::mock('alias:' . ImageGenerationJob::class);
        $jobMock->shouldReceive('create')->andReturn(new ImageGenerationJob());
        $collisionQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $collisionQuery->shouldReceive('exists')->andReturn(false);
        $jobMock->shouldReceive('where')->with('planned_filename', Mockery::any())->andReturn($collisionQuery);

        $aboutQuery = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $aboutQuery->shouldReceive('where')->with('key', 'profile_photo')->andReturnSelf();
        $aboutQuery->shouldReceive('value')->with('value')->andReturn(null);

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

    private function makeIdeaWithFailedSegment(int $retryCount): ContentIdea
    {
        $idea = Mockery::mock(ContentIdea::class)->makePartial();
        $idea->id = 77;
        $idea->title = 'Demo';
        $idea->status = 'generating_images';
        $idea->auto_mode = false;
        $idea->generated_article = [
            'language' => 'id',
            'id' => ['title' => 'Demo Title'],
            'image_prompts' => [
                [
                    'type' => 'cover',
                    'prompt_text' => 'Cover prompt',
                    'status' => 'failed',
                    'retry_count' => $retryCount,
                    'failure_history' => [],
                    'terminal_at' => null,
                    'variations' => [
                        ['url' => null, 'job_uuid' => 'old-uuid', 'status' => 'failed', 'error' => 'GeminiGen boom'],
                    ],
                    'face_refs' => ['https://example.com/face.jpg'],
                    'style_refs' => [],
                    'brand_refs' => [],
                    'entity_refs' => [],
                ],
            ],
        ];
        $idea->shouldReceive('save')->andReturn(true);
        return $idea;
    }

    /** @test */
    public function retry_segment_resets_variations_without_bumping_retry_count(): void
    {
        // retry_count is owned by handleSegmentFailure and bumped only on
        // actual failures. retrySegment() is a dispatch — it must NOT
        // increment, otherwise each attempt consumes 2 budget units
        // (failure + dispatch) instead of 1.
        $idea = $this->makeIdeaWithFailedSegment(retryCount: 1);

        $service = app(ImageGenerationService::class);
        $uuid = $service->retrySegment($idea, 0);

        $this->assertSame('retry-uuid-1', $uuid);

        $segment = $idea->generated_article['image_prompts'][0];
        $this->assertSame(1, $segment['retry_count'], 'retrySegment must NOT mutate retry_count');
        $this->assertSame('generating', $segment['status']);
        $this->assertCount(1, $segment['variations']);
        $this->assertSame('generating', $segment['variations'][0]['status']);
        $this->assertSame('retry-uuid-1', $segment['variations'][0]['job_uuid']);
    }

    /** @test */
    public function retry_segment_clears_terminal_at_on_manual_override(): void
    {
        // Admin force-retry of a terminal segment: terminal_at is cleared
        // so the advance rule stops seeing this segment as resolved-failed.
        $idea = $this->makeIdeaWithFailedSegment(retryCount: 1);
        $article = $idea->generated_article;
        $article['image_prompts'][0]['terminal_at'] = now()->toIso8601String();
        $idea->generated_article = $article;

        app(ImageGenerationService::class)->retrySegment($idea, 0);

        $this->assertNull($idea->generated_article['image_prompts'][0]['terminal_at']);
    }

    /** @test */
    public function retry_segment_refuses_when_max_attempts_exceeded(): void
    {
        $idea = $this->makeIdeaWithFailedSegment(retryCount: ImageGenerationService::MAX_SEGMENT_ATTEMPTS);

        $service = app(ImageGenerationService::class);
        $uuid = $service->retrySegment($idea, 0);

        $this->assertNull($uuid, 'retrySegment must return null when retry cap reached');
    }

    /** @test */
    public function retry_segment_returns_null_for_out_of_range_index(): void
    {
        $idea = $this->makeIdeaWithFailedSegment(retryCount: 0);

        $service = app(ImageGenerationService::class);
        $this->assertNull($service->retrySegment($idea, 99));
    }

    /** @test */
    public function max_segment_attempts_constant_equals_three(): void
    {
        $this->assertSame(3, ImageGenerationService::MAX_SEGMENT_ATTEMPTS);
    }
}
