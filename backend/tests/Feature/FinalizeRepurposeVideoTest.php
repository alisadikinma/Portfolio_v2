<?php

namespace Tests\Feature;

use App\Jobs\FinalizeRepurpose;
use App\Jobs\GenerateLinkedInPost;
use App\Models\LinkedInPost;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Services\TelegramNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase F — FinalizeRepurpose video branch. Unlike blog/carousel (which create a
 * ContentIdea / anchor Post + LinkedIn draft), video_rebrand v1 ships as a set of
 * composited 4:5 MP4s the operator downloads + posts manually. Finalize only flips
 * Composed → Drafted, RETAINS the composited files, and fires a Telegram notice.
 *
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md Phase F/G
 */
class FinalizeRepurposeVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_video_rebrand_finalize_drafts_and_retains_slides(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'composed']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook', 'composited_status' => 'done', 'composited_path' => 'https://x/0.mp4']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 1, 'role' => 'tool', 'composited_status' => 'done', 'composited_path' => 'https://x/1.mp4']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 2, 'role' => 'cta', 'composited_status' => 'done', 'composited_path' => 'https://x/2.mp4']);

        $this->mock(TelegramNotificationService::class, function ($m) {
            $m->shouldReceive('sendRepurposeDrafted')->once();
        });

        (new FinalizeRepurpose($job->id))->handle();

        $job->refresh();
        $this->assertSame('drafted', $job->status);
        // Composited slides retained for the manual-download UI (no purge).
        $this->assertSame(3, $job->videoSlides()->count());
    }

    public function test_video_finalize_creates_video_carousel_anchor(): void
    {
        Queue::fake();

        $job = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand',
            'status' => 'composed',
            'rewritten' => null,
            'extracted' => ['source_hook_title' => 'AI Tools That Save Hours'],
        ]);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook', 'composited_status' => 'done', 'composited_path' => 'https://x/0.mp4']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 1, 'role' => 'tool', 'header_title' => 'Opal', 'composited_status' => 'done', 'composited_path' => 'https://x/1.mp4']);

        $this->mock(TelegramNotificationService::class, function ($m) {
            $m->shouldReceive('sendRepurposeDrafted')->once();
        });

        (new FinalizeRepurpose($job->id))->handle();

        $job->refresh();
        $this->assertSame('drafted', $job->status);
        $this->assertNotNull($job->linkedin_post_id, 'finalize must link a calendar anchor');

        $anchor = LinkedInPost::find($job->linkedin_post_id);
        $this->assertNotNull($anchor);
        $this->assertSame(LinkedInPost::FORMAT_VIDEO_CAROUSEL, $anchor->format);
        $this->assertSame('manual_review', $anchor->status);
        $this->assertNotSame('', trim((string) $anchor->content), 'caption mirrored onto the anchor');
        // Caption is still stored on the job too (manual-download UI reads it).
        $this->assertNotSame('', trim((string) ($job->rewritten['caption'] ?? '')));

        // The anchor is display-only — it must NEVER run /linkedin-gen.
        Queue::assertNotPushed(GenerateLinkedInPost::class);
    }

    public function test_video_rebrand_finalize_noop_when_not_composed(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'compositing']);

        $this->mock(TelegramNotificationService::class, function ($m) {
            $m->shouldReceive('sendRepurposeDrafted')->never();
        });

        (new FinalizeRepurpose($job->id))->handle();

        $job->refresh();
        $this->assertSame('compositing', $job->status);
    }
}
