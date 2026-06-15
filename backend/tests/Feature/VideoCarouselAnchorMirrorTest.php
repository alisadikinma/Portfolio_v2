<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase E — the video_carousel anchor mirrors the Zernio (IG + Threads) publish
 * lifecycle so the LinkedIn-tab calendar reflects reality:
 *   schedule  → anchor awaiting_publish + scheduled_at  (lands on the grid date)
 *   all done  → anchor published + published_at         (shows as shipped)
 * Partial publish (one platform still pending) must NOT prematurely mark published.
 */
class VideoCarouselAnchorMirrorTest extends TestCase
{
    use RefreshDatabase;

    private function anchoredVideoJob(string $anchorStatus = 'manual_review'): array
    {
        $cat = Category::firstOrCreate(['name' => 'AI & Tech']);
        $post = Post::factory()->create(['category_id' => $cat->id, 'title' => 'Anchor', 'content' => '<p>b</p>']);
        $anchor = LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'format' => LinkedInPost::FORMAT_VIDEO_CAROUSEL,
            'status' => $anchorStatus,
            'scheduled_at' => null,
            'published_at' => null,
        ]);
        $job = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand',
            'status' => 'drafted',
            'linkedin_post_id' => $anchor->id,
        ]);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook', 'composited_status' => 'done', 'composited_path' => 'https://x/0.mp4']);

        return [$job, $anchor];
    }

    public function test_publish_zernio_schedule_mirrors_awaiting_publish_onto_anchor(): void
    {
        Queue::fake();
        config(['social-cross-post.zernio.enabled' => true]);
        Setting::create(['group' => 'zernio', 'key' => 'zernio_instagram_account_id', 'value' => 'acct_ig']);

        [$job, $anchor] = $this->anchoredVideoJob();
        $when = now()->addDay()->startOfMinute();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->postJson("/api/admin/repurpose/{$job->id}/publish-zernio", [
                'platforms' => ['instagram'],
                'scheduled_at' => $when->toIso8601String(),
            ])
            ->assertStatus(202);

        $anchor->refresh();
        $this->assertSame('awaiting_publish', $anchor->status);
        $this->assertNotNull($anchor->scheduled_at);
        $this->assertSame($when->toIso8601String(), $anchor->scheduled_at->toIso8601String());
    }

    public function test_anchor_flips_published_only_when_all_platforms_done(): void
    {
        [$job, $anchor] = $this->anchoredVideoJob('awaiting_publish');

        // Partial: IG published, Threads still scheduled → anchor stays awaiting_publish.
        $job->update(['zernio_publish' => [
            'instagram' => ['status' => 'published', 'post_id' => 'ig1'],
            'threads' => ['status' => 'scheduled', 'post_id' => 'th1'],
        ]]);
        $job->fresh()->mirrorAnchorPublishedIfComplete();
        $this->assertSame('awaiting_publish', $anchor->fresh()->status);

        // All published → anchor flips to published with a published_at.
        $job->update(['zernio_publish' => [
            'instagram' => ['status' => 'published', 'post_id' => 'ig1'],
            'threads' => ['status' => 'published', 'post_id' => 'th1'],
        ]]);
        $job->fresh()->mirrorAnchorPublishedIfComplete();

        $anchor->refresh();
        $this->assertSame('published', $anchor->status);
        $this->assertNotNull($anchor->published_at);
    }
}
