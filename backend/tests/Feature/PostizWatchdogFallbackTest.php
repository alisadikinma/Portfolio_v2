<?php

namespace Tests\Feature;

use App\Jobs\PublishViaPubler;
use App\Models\Category;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\PostizPublishJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase F — claim-aware deadline watchdog fires Publer fallback when the local
 * node is offline. See docs/plans/2026-06-13-postiz-local-node-crosspost.md.
 */
class PostizWatchdogFallbackTest extends TestCase
{
    use RefreshDatabase;

    private function makeIgSibling(bool $withVideo = false): InstagramPost
    {
        $category = Category::query()->first() ?? Category::create([
            'name' => 'Test Cat',
            'slug' => 'test-cat-' . Str::random(6),
        ]);
        $post = Post::factory()->create(['category_id' => $category->id]);
        $li = LinkedInPost::factory()->create(['post_id' => $post->id, 'format' => 'carousel']);

        return InstagramPost::create([
            'post_id' => $post->id,
            'linkedin_post_id' => $li->id,
            'status' => 'awaiting_review',
            'caption' => 'cap',
            'hook_video_status' => $withVideo ? 'done' : null,
            'hook_video_url' => $withVideo ? 'http://localhost/storage/ig-hook/v.mp4' : null,
        ]);
    }

    private function makeJob(InstagramPost $ig, array $overrides = []): PostizPublishJob
    {
        return PostizPublishJob::factory()->create(array_merge([
            'platform' => 'instagram',
            'sibling_post_id' => $ig->id,
            'sibling_type' => InstagramPost::class,
            'status' => 'ready_to_publish',
            'publish_lease_until' => null,
            'postiz_post_id' => null,
            'fallback_fired_at' => null,
            'slot_due_at' => now()->subMinutes(10),
        ], $overrides));
    }

    public function test_unclaimed_past_deadline_ig_image_dispatches_publer(): void
    {
        Queue::fake();
        $ig = $this->makeIgSibling(false);
        $job = $this->makeJob($ig);

        $this->artisan('postiz:reap-unclaimed')->assertExitCode(0);

        Queue::assertPushed(PublishViaPubler::class, fn ($j) => $j->platform === 'instagram' && $j->siblingPostId === $ig->id);
        $job->refresh();
        $this->assertNotNull($job->fallback_fired_at);
        $this->assertSame('failed', $job->status);
        $this->assertSame('postiz_offline_publer_fallback', $job->last_error);
    }

    public function test_active_lease_not_faulted(): void
    {
        Queue::fake();
        $ig = $this->makeIgSibling(false);
        $this->makeJob($ig, ['status' => 'claimed', 'publish_lease_until' => now()->addMinutes(5)]);

        $this->artisan('postiz:reap-unclaimed')->assertExitCode(0);

        Queue::assertNotPushed(PublishViaPubler::class);
    }

    public function test_ig_video_carousel_no_fallback(): void
    {
        Queue::fake();
        $ig = $this->makeIgSibling(true);
        $job = $this->makeJob($ig);

        $this->artisan('postiz:reap-unclaimed')->assertExitCode(0);

        Queue::assertNotPushed(PublishViaPubler::class);
        $job->refresh();
        $this->assertNull($job->fallback_fired_at);
        $this->assertSame('ready_to_publish', $job->status);
    }

    public function test_already_fallback_fired_skipped(): void
    {
        Queue::fake();
        $ig = $this->makeIgSibling(false);
        $this->makeJob($ig, ['fallback_fired_at' => now()->subMinute(), 'status' => 'failed']);

        $this->artisan('postiz:reap-unclaimed')->assertExitCode(0);

        Queue::assertNotPushed(PublishViaPubler::class);
    }

    public function test_accepted_job_never_fallback(): void
    {
        Queue::fake();
        $ig = $this->makeIgSibling(false);
        // Reached Postiz (postiz_post_id set) then ERRORed → must NEVER fallback.
        $this->makeJob($ig, ['postiz_post_id' => 'pz-9', 'status' => 'failed']);

        $this->artisan('postiz:reap-unclaimed')->assertExitCode(0);

        Queue::assertNotPushed(PublishViaPubler::class);
    }

    public function test_claimed_lease_expired_parked_for_review_not_publer(): void
    {
        // Crash-after-accept window: poller claimed it, went silent, lease expired,
        // postiz_post_id still NULL. Could already be live on Postiz → NEVER Publer.
        Queue::fake();
        $ig = $this->makeIgSibling(false);
        $job = $this->makeJob($ig, [
            'status' => 'claimed',
            'claimed_at' => now()->subMinutes(11),
            'publish_lease_until' => now()->subMinute(),
        ]);

        $this->artisan('postiz:reap-unclaimed')->assertExitCode(0);

        Queue::assertNotPushed(PublishViaPubler::class);
        $job->refresh();
        $this->assertSame('needs_review', $job->status);
        $this->assertNull($job->fallback_fired_at);
    }

    public function test_poller_reported_failed_parked_for_review_not_publer(): void
    {
        // Poller reported a pre-accept failure that may STILL have reached Postiz
        // (network timeout after commit). Ambiguous → review, never auto-Publer.
        Queue::fake();
        $ig = $this->makeIgSibling(false);
        $job = $this->makeJob($ig, [
            'status' => 'failed',
            'claimed_at' => now()->subMinutes(11),
            'publish_lease_until' => null,
        ]);

        $this->artisan('postiz:reap-unclaimed')->assertExitCode(0);

        Queue::assertNotPushed(PublishViaPubler::class);
        $job->refresh();
        $this->assertSame('needs_review', $job->status);
    }
}
