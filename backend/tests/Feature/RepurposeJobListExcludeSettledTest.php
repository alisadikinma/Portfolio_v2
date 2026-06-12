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
 *   carousel mode → linked LinkedInPost left queueStatuses
 *                   (awaiting_publish = calendar / published / cancelled) → hidden
 *   blog mode     → linked ContentIdea completed with a result Post → hidden
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

    public function test_keeps_blog_job_whose_content_idea_still_in_progress(): void
    {
        $idea = ContentIdea::create([
            'title' => 'In progress',
            'status' => 'article_ready',
            'source' => 'instagram',
            'pillar' => 'general',
        ]);
        $job = RepurposeJob::factory()->create([
            'status' => 'drafted',
            'mode' => 'blog',
            'content_idea_id' => $idea->id,
        ]);

        $this->assertContains($job->id, $this->listIds(['exclude_settled' => 1]));
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
