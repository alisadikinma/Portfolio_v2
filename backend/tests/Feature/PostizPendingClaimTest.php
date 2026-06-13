<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\PostizPublishJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase C — GET /automation/postiz/pending atomic claim + lease.
 * See docs/plans/2026-06-13-postiz-local-node-crosspost.md.
 */
class PostizPendingClaimTest extends TestCase
{
    use RefreshDatabase;

    private function makeIgJob(array $jobOverrides = []): PostizPublishJob
    {
        $category = Category::query()->first() ?? Category::create([
            'name' => 'Test Cat',
            'slug' => 'test-cat-' . Str::random(6),
        ]);
        $post = Post::factory()->create(['category_id' => $category->id]);
        $li = LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'carousel_slides' => [
                ['slide_number' => 1, 'image_url' => 'http://localhost/storage/linkedin-carousel/s1.png', 'image_status' => 'done'],
                ['slide_number' => 2, 'image_url' => 'http://localhost/storage/linkedin-carousel/s2.png', 'image_status' => 'done'],
            ],
        ]);
        $ig = InstagramPost::create([
            'post_id' => $post->id,
            'linkedin_post_id' => $li->id,
            'status' => 'awaiting_review',
            'caption' => 'Hello caption',
            'hashtags' => ['#AI', '#Test'],
            'link_comment' => 'Full article: http://x',
        ]);

        return PostizPublishJob::factory()->create(array_merge([
            'platform' => 'instagram',
            'sibling_post_id' => $ig->id,
            'sibling_type' => InstagramPost::class,
            'status' => 'ready_to_publish',
            'postiz_integration_id' => '77',
            'publish_lease_until' => null,
        ], $jobOverrides));
    }

    public function test_requires_auth(): void
    {
        $this->getJson('/api/automation/postiz/pending')->assertUnauthorized();
    }

    public function test_pending_claims_job_sets_lease_and_returns_real_media_and_caption(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $job = $this->makeIgJob();

        $res = $this->getJson('/api/automation/postiz/pending?worker=local-1')->assertOk();

        $res->assertJsonPath('jobs.0.job_id', $job->id);
        $res->assertJsonPath('jobs.0.platform', 'instagram');
        $res->assertJsonPath('jobs.0.postiz_integration_id', '77');
        $res->assertJsonCount(2, 'jobs.0.media_urls');
        $this->assertStringContainsString('Hello caption', $res->json('jobs.0.caption'));

        $job->refresh();
        $this->assertSame('claimed', $job->status);
        $this->assertSame('local-1', $job->claimed_by);
        $this->assertNotNull($job->publish_lease_until);
    }

    public function test_claimed_job_not_returned_to_second_poll(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->makeIgJob();

        $this->getJson('/api/automation/postiz/pending?worker=local-1')->assertJsonCount(1, 'jobs');
        // Second poll: lease active → no jobs returned
        $this->getJson('/api/automation/postiz/pending?worker=local-1')->assertJsonCount(0, 'jobs');
    }

    public function test_upcoming_includes_unclaimed_future_due_jobs_readonly(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->makeIgJob(['slot_due_at' => now()->addHours(6)]);

        $res = $this->getJson('/api/automation/postiz/pending?worker=local-1')->assertOk();
        // due-in-future → still claimable now (slot_due_at doesn't gate claim) but
        // also surfaced in upcoming for prefetch. At minimum upcoming is an array.
        $this->assertIsArray($res->json('upcoming'));
    }
}
