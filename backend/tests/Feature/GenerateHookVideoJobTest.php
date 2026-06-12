<?php

namespace Tests\Feature;

use App\Jobs\GenerateHookVideo;
use App\Models\Category;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Services\GeminiGenVideoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Phase C — GenerateHookVideo job.
 *
 * Orchestrates: resolve the parent LinkedInPost hook slide (carousel_slides[0]),
 * prepareFrame → dispatchHookVideo, then persist hook_video_status=generating +
 * the GROK job uuid. Idempotent (skips when already generating/done); bails
 * cleanly when the slide isn't rendered or the parent isn't a carousel.
 */
class GenerateHookVideoJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeSibling(array $hookSlide = null, string $igStatus = 'awaiting_review'): InstagramPost
    {
        $category = Category::create(['name' => 'T', 'slug' => 'c-'.uniqid()]);
        $post = Post::create([
            'category_id' => $category->id,
            'slug' => 'p-'.uniqid(),
            'title' => 'T',
            'content' => 'Body.',
        ]);

        $hookSlide = $hookSlide ?? [
            'slide_number' => 1,
            'layout_hint' => 'cover',
            'image_status' => 'done',
            'image_url' => 'https://alisadikinma.com/storage/linkedin-carousel/li-1-slide-01.png',
        ];

        $li = LinkedInPost::create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'content' => 'Caption.',
            'carousel_slides' => [$hookSlide, ['slide_number' => 2, 'image_status' => 'done', 'image_url' => 'x']],
            'status' => \App\Enums\LinkedInPostStatus::ManualReview->value,
            'pipeline_state_log' => [],
            'hashtags' => [],
        ]);

        return InstagramPost::create([
            'post_id' => $post->id,
            'linkedin_post_id' => $li->id,
            'status' => $igStatus,
            'caption' => 'IG caption',
            'hashtags' => [],
        ]);
    }

    public function test_dispatches_grok_and_marks_generating(): void
    {
        $ig = $this->makeSibling();

        $mock = Mockery::mock(GeminiGenVideoService::class);
        $mock->shouldReceive('prepareFrame')
            ->once()
            ->andReturn('https://alisadikinma.com/storage/linkedin-carousel/grok-frame-'.$ig->id.'.jpg');
        $mock->shouldReceive('dispatchHookVideo')
            ->once()
            ->andReturn('grok-job-uuid-1');

        (new GenerateHookVideo($ig->id))->handle($mock);

        $ig->refresh();
        $this->assertSame('generating', $ig->hook_video_status);
        $this->assertSame('grok-job-uuid-1', $ig->hook_video_job_uuid);
        $this->assertNull($ig->hook_video_error);
    }

    public function test_idempotent_skips_when_already_generating(): void
    {
        $ig = $this->makeSibling();
        $ig->update(['hook_video_status' => 'generating', 'hook_video_job_uuid' => 'existing-uuid']);

        $mock = Mockery::mock(GeminiGenVideoService::class);
        $mock->shouldReceive('prepareFrame')->never();
        $mock->shouldReceive('dispatchHookVideo')->never();

        (new GenerateHookVideo($ig->id))->handle($mock);

        $ig->refresh();
        $this->assertSame('existing-uuid', $ig->hook_video_job_uuid);
    }

    public function test_bails_when_hook_slide_not_rendered(): void
    {
        $ig = $this->makeSibling([
            'slide_number' => 1,
            'layout_hint' => 'cover',
            'image_status' => 'generating',
            'image_url' => null,
        ]);

        $mock = Mockery::mock(GeminiGenVideoService::class);
        $mock->shouldReceive('prepareFrame')->never();
        $mock->shouldReceive('dispatchHookVideo')->never();

        (new GenerateHookVideo($ig->id))->handle($mock);

        $ig->refresh();
        $this->assertNull($ig->hook_video_status);
    }

    public function test_marks_failed_when_dispatch_returns_null(): void
    {
        $ig = $this->makeSibling();

        $mock = Mockery::mock(GeminiGenVideoService::class);
        $mock->shouldReceive('prepareFrame')->once()->andReturn('https://x/frame.jpg');
        $mock->shouldReceive('dispatchHookVideo')->once()->andReturn(null);

        (new GenerateHookVideo($ig->id))->handle($mock);

        $ig->refresh();
        $this->assertSame('failed', $ig->hook_video_status);
        $this->assertNotNull($ig->hook_video_error);
    }
}
