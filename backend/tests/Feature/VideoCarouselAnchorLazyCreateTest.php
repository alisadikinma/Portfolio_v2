<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Services\VideoCarouselAnchorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression: a video_rebrand job finalized BEFORE the calendar-anchor feature
 * shipped has linkedin_post_id NULL. Scheduling it via Zernio called
 * mirrorAnchorScheduled(), which early-returned on the missing anchor — so the
 * schedule reached Zernio but never created a Content Calendar row (production:
 * job 26). The schedule/publish path now lazily materializes the anchor.
 */
class VideoCarouselAnchorLazyCreateTest extends TestCase
{
    use RefreshDatabase;

    private function orphanVideoJob(): RepurposeJob
    {
        Category::firstOrCreate(['name' => 'AI & Tech']);
        $job = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand',
            'status' => 'drafted',
            'linkedin_post_id' => null,
            'rewritten' => ['caption' => 'Repurposed tools roundup.'],
        ]);
        RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'slide_index' => 1, 'role' => 'tool',
            'header_title' => 'Opal', 'composited_status' => 'done', 'composited_path' => 'https://x/1.mp4',
        ]);

        return $job;
    }

    public function test_schedule_lazily_creates_anchor_for_pre_feature_job(): void
    {
        $job = $this->orphanVideoJob();
        $when = now()->addDay()->startOfMinute();

        $job->mirrorAnchorScheduled($when);

        $job->refresh();
        $this->assertNotNull($job->linkedin_post_id, 'anchor materialized + linked');
        $anchor = LinkedInPost::find($job->linkedin_post_id);
        $this->assertSame(LinkedInPost::FORMAT_VIDEO_CAROUSEL, $anchor->format);
        $this->assertSame('awaiting_publish', $anchor->status);
        $this->assertSame($when->toIso8601String(), $anchor->scheduled_at->toIso8601String());
        // A real title comes through (post_translations), not "Untitled".
        $this->assertSame('Opal', $anchor->post?->translations?->first()?->title);
    }

    public function test_ensure_for_is_idempotent(): void
    {
        $job = $this->orphanVideoJob();
        $svc = app(VideoCarouselAnchorService::class);

        $first = $svc->ensureFor($job);
        $second = $svc->ensureFor($job->fresh());

        $this->assertSame($first->id, $second->id, 'no duplicate anchor on re-ensure');
        $this->assertSame(1, LinkedInPost::where('format', LinkedInPost::FORMAT_VIDEO_CAROUSEL)->count());
    }

    public function test_mirror_is_a_noop_for_non_video_rebrand_jobs(): void
    {
        // A carousel-mode job must NOT get a phantom video anchor.
        $job = RepurposeJob::factory()->create([
            'mode' => 'carousel',
            'status' => 'drafted',
            'linkedin_post_id' => null,
        ]);

        $job->mirrorAnchorScheduled(now()->addDay());

        $this->assertNull($job->fresh()->linkedin_post_id);
        $this->assertSame(0, LinkedInPost::count());
    }
}
