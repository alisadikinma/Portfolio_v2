<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentIdea;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\RepurposeJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Social Studio: hide a SETTLED IG-repurpose job, mirroring the blog source's
 * scope=queue gate (LinkedInPostStatus::queueStatuses() shows only working-queue
 * drafts, so a draft leaves the moment it's scheduled into the Content Calendar
 * OR published). Opt-in via ?exclude_settled=1.
 *
 *   carousel mode     → linked LinkedInPost left queueStatuses
 *                       (awaiting_publish = calendar / published / cancelled) → hidden
 *   blog mode         → handed off to Content Engine (content_idea_id set) → hidden
 *                       immediately, not only once the article finally publishes
 *   video_rebrand     → hidden only when anchor is scheduled/published (in feed-states);
 *                       an unscheduled manual_review anchor stays visible in Social Studio
 *                       so the operator can reach RepurposeJobDetail → "Schedule for later"
 *   still-in-queue draft (incl. failed), in-flight / drafted-not-routed, no linkage → stays
 *
 * Default (no param) returns the full set — other consumers unaffected.
 */
class RepurposeJobListExcludeSettledTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    /** @return array<int> ids present in the response data */
    private function listIds(array $query = []): array
    {
        $qs = $query ? ('?' . http_build_query($query)) : '';
        $res = $this->actingAs($this->admin(), 'sanctum')->getJson('/api/admin/repurpose' . $qs);
        $res->assertOk();
        return array_map(fn ($r) => $r['id'], $res->json('data'));
    }

    private function carouselJob(string $draftStatus): RepurposeJob
    {
        $cat = Category::create(['name' => 'AI & Tech']);
        $post = Post::factory()->create(['category_id' => $cat->id, 'title' => 'Anchor', 'content' => '<p>b</p>']);
        $li = LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'status' => $draftStatus,
        ]);

        return RepurposeJob::factory()->create([
            'status' => 'drafted',
            'mode' => 'carousel',
            'linkedin_post_id' => $li->id,
        ]);
    }

    public function test_excludes_carousel_scheduled_into_calendar(): void
    {
        // awaiting_publish = placed in the Content Calendar.
        $job = $this->carouselJob('awaiting_publish');
        $this->assertNotContains($job->id, $this->listIds(['exclude_settled' => 1]));
    }

    public function test_excludes_carousel_that_is_published(): void
    {
        $job = $this->carouselJob('published');
        $this->assertNotContains($job->id, $this->listIds(['exclude_settled' => 1]));
    }

    public function test_keeps_carousel_still_in_working_queue(): void
    {
        // manual_review + pending_generation are queue statuses → still working.
        $review = $this->carouselJob('manual_review');
        $pending = $this->carouselJob('pending_generation');

        $ids = $this->listIds(['exclude_settled' => 1]);
        $this->assertContains($review->id, $ids);
        $this->assertContains($pending->id, $ids);
    }

    public function test_keeps_carousel_whose_draft_failed(): void
    {
        // failed is a queue status (Failed tab) — operator must still see/retry it.
        $job = $this->carouselJob('failed');
        $this->assertContains($job->id, $this->listIds(['exclude_settled' => 1]));
    }

    private function videoJob(?int $linkedinPostId): RepurposeJob
    {
        return RepurposeJob::factory()->create([
            'status' => 'drafted',
            'mode' => 'video_rebrand',
            'linkedin_post_id' => $linkedinPostId,
        ]);
    }

    public function test_keeps_video_rebrand_when_anchor_is_unscheduled_manual_review(): void
    {
        // Regression test for prod draft #168: FinalizeRepurpose creates the anchor
        // in manual_review with scheduled_at=NULL. The job must STAY in Social Studio
        // so the operator can reach RepurposeJobDetail → "Schedule for later".
        // A video_rebrand is only settled once the anchor is actually on the calendar
        // (has scheduled_at OR is in a feed status).
        $cat = Category::create(['name' => 'AI & Tech']);
        $post = Post::factory()->create(['category_id' => $cat->id, 'title' => 'Anchor', 'content' => '<p>b</p>']);
        $anchor = LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'format' => LinkedInPost::FORMAT_VIDEO_CAROUSEL,
            'status' => 'manual_review',
            'scheduled_at' => null,
        ]);
        $job = $this->videoJob($anchor->id);

        $this->assertContains($job->id, $this->listIds(['exclude_settled' => 1]));
    }

    public function test_excludes_video_rebrand_when_anchor_scheduled(): void
    {
        // Once the operator schedules via Zernio, mirrorAnchorScheduled sets
        // scheduled_at + advances anchor to awaiting_publish → job is on the
        // Content Calendar → settled → leaves Social Studio.
        $cat = Category::create(['name' => 'AI & Tech']);
        $post = Post::factory()->create(['category_id' => $cat->id, 'title' => 'Anchor', 'content' => '<p>b</p>']);
        $anchor = LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'format' => LinkedInPost::FORMAT_VIDEO_CAROUSEL,
            'status' => 'awaiting_publish',
            'scheduled_at' => now()->addHours(2),
        ]);
        $job = $this->videoJob($anchor->id);

        $this->assertNotContains($job->id, $this->listIds(['exclude_settled' => 1]));
    }

    public function test_excludes_video_rebrand_when_anchor_published(): void
    {
        $cat = Category::create(['name' => 'AI & Tech']);
        $post = Post::factory()->create(['category_id' => $cat->id, 'title' => 'Anchor', 'content' => '<p>b</p>']);
        $anchor = LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'format' => LinkedInPost::FORMAT_VIDEO_CAROUSEL,
            'status' => 'published',
            'scheduled_at' => now()->subHour(),
        ]);
        $job = $this->videoJob($anchor->id);

        $this->assertNotContains($job->id, $this->listIds(['exclude_settled' => 1]));
    }

    public function test_keeps_video_rebrand_before_anchor(): void
    {
        // Composited but not yet finalized → no anchor → still in Social Studio.
        $job = $this->videoJob(null);
        $this->assertContains($job->id, $this->listIds(['exclude_settled' => 1]));
    }

    public function test_excludes_blog_job_whose_content_idea_completed_with_result_post(): void
    {
        $cat = Category::create(['name' => 'AI & Tech']);
        $post = Post::factory()->create(['category_id' => $cat->id, 'title' => 'Done', 'content' => '<p>x</p>']);
        $idea = ContentIdea::create([
            'title' => 'Repurposed from IG',
            'status' => 'completed',
            'source' => 'instagram',
            'pillar' => 'general',
            'result_post_id' => $post->id,
        ]);
        $job = RepurposeJob::factory()->create([
            'status' => 'drafted',
            'mode' => 'blog',
            'content_idea_id' => $idea->id,
        ]);

        $this->assertNotContains($job->id, $this->listIds(['exclude_settled' => 1]));
    }

    public function test_excludes_blog_job_once_handed_off_to_content_engine(): void
    {
        // A blog job leaves Social Studio the moment finalizeBlog seeds a draft
        // ContentIdea (content_idea_id set) — the work now lives in Content
        // Engine, regardless of how far the article has progressed there.
        $idea = ContentIdea::create([
            'title' => 'Just handed off',
            'status' => 'draft',
            'source' => 'instagram',
            'pillar' => 'general',
        ]);
        $job = RepurposeJob::factory()->create([
            'status' => 'drafted',
            'mode' => 'blog',
            'content_idea_id' => $idea->id,
        ]);

        $this->assertNotContains($job->id, $this->listIds(['exclude_settled' => 1]));
    }

    public function test_keeps_inflight_and_unlinked_jobs(): void
    {
        $inFlight = RepurposeJob::factory()->create(['status' => 'capturing', 'linkedin_post_id' => null]);
        $failedJob = RepurposeJob::factory()->create(['status' => 'failed', 'linkedin_post_id' => null]);

        $ids = $this->listIds(['exclude_settled' => 1]);
        $this->assertContains($inFlight->id, $ids);
        $this->assertContains($failedJob->id, $ids);
    }

    public function test_default_without_param_returns_settled_jobs(): void
    {
        $job = $this->carouselJob('published');
        // No exclude_settled → full set, settled job still present (back-compat).
        $this->assertContains($job->id, $this->listIds());
    }
}
