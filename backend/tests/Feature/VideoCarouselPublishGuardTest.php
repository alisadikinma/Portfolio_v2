<?php

namespace Tests\Feature;

use App\Console\Commands\AutoScheduleManualReviewLinkedInPosts;
use App\Jobs\GenerateLinkedInPost;
use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Services\LinkedInGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Phase B — a video_carousel anchor must be invisible to EVERY LinkedIn
 * publish/schedule path (it publishes to IG + Threads via Zernio, never LinkedIn).
 * The single guard is LinkedInPost::scopeExcludeVideoCarousel() composed into each
 * selector, plus a defensive bail in GenerateLinkedInPost.
 */
class VideoCarouselPublishGuardTest extends TestCase
{
    use RefreshDatabase;

    private function anchorPost(): Post
    {
        $cat = Category::firstOrCreate(['name' => 'AI & Tech']);

        return Post::factory()->create(['category_id' => $cat->id, 'title' => 'Anchor', 'content' => '<p>b</p>']);
    }

    public function test_auto_schedule_candidate_query_excludes_video_carousel(): void
    {
        // The real selector query (private loadCandidates) must omit the video anchor
        // while still picking up a normal manual_review carousel draft.
        $video = LinkedInPost::factory()->manualReview()->create([
            'post_id' => $this->anchorPost()->id,
            'format' => LinkedInPost::FORMAT_VIDEO_CAROUSEL,
        ]);
        $control = LinkedInPost::factory()->manualReview()->create([
            'post_id' => $this->anchorPost()->id,
            'format' => 'carousel',
        ]);

        $cmd = app(AutoScheduleManualReviewLinkedInPosts::class);
        $ref = new \ReflectionMethod($cmd, 'loadCandidates');
        $ref->setAccessible(true);
        $ids = collect($ref->invoke($cmd))->pluck('id')->all();

        $this->assertContains($control->id, $ids, 'normal manual_review draft should be a candidate');
        $this->assertNotContains($video->id, $ids, 'video_carousel anchor must NOT be a candidate');
    }

    public function test_process_scheduled_does_not_publish_video_carousel(): void
    {
        // awaiting_publish + cancel window passed = the exact shape social:publish-slot
        // fires on. The guard keeps the video anchor in awaiting_publish (Zernio owns it).
        $video = LinkedInPost::factory()->awaitingPublish()->create([
            'post_id' => $this->anchorPost()->id,
            'format' => LinkedInPost::FORMAT_VIDEO_CAROUSEL,
            'cancel_window_ends_at' => now()->subMinute(),
        ]);

        $this->artisan('social:publish-slot')->assertExitCode(0);

        $this->assertSame(
            'awaiting_publish',
            $video->fresh()->status,
            'video_carousel must never be published/failed by the LinkedIn slot publisher'
        );
    }

    public function test_prompt_schedule_does_not_touch_video_carousel(): void
    {
        $video = LinkedInPost::factory()->manualReview()->create([
            'post_id' => $this->anchorPost()->id,
            'format' => LinkedInPost::FORMAT_VIDEO_CAROUSEL,
            'scheduled_at' => null,
            'schedule_prompt_sent_at' => null,
        ]);

        $this->artisan('linkedin:prompt-schedule')->assertExitCode(0);

        $this->assertNull(
            $video->fresh()->schedule_prompt_sent_at,
            'video_carousel must never receive a Telegram schedule prompt'
        );
    }

    public function test_generate_linkedin_post_bails_on_video_carousel(): void
    {
        $video = LinkedInPost::factory()->create([
            'post_id' => $this->anchorPost()->id,
            'format' => LinkedInPost::FORMAT_VIDEO_CAROUSEL,
            'status' => 'pending_generation',
        ]);

        // The service must never be invoked for a video anchor — if the bail is
        // missing, handle() would call generate() and this mock would fail the test.
        $service = Mockery::mock(LinkedInGenerationService::class);
        $service->shouldNotReceive('generate');

        (new GenerateLinkedInPost($video->id))->handle($service);

        $this->assertSame('pending_generation', $video->fresh()->status);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
