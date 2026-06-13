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
 * Phase D — POST /automation/postiz/{job}/result callback.
 * See docs/plans/2026-06-13-postiz-local-node-crosspost.md.
 */
class PostizResultCallbackTest extends TestCase
{
    use RefreshDatabase;

    private function makeJobWithSibling(array $jobOverrides = []): array
    {
        $category = Category::query()->first() ?? Category::create([
            'name' => 'Test Cat',
            'slug' => 'test-cat-' . Str::random(6),
        ]);
        $post = Post::factory()->create(['category_id' => $category->id]);
        $li = LinkedInPost::factory()->create(['post_id' => $post->id, 'format' => 'carousel']);
        $ig = InstagramPost::create([
            'post_id' => $post->id,
            'linkedin_post_id' => $li->id,
            'status' => 'awaiting_review',
            'caption' => 'cap',
        ]);
        $job = PostizPublishJob::factory()->claimed()->create(array_merge([
            'platform' => 'instagram',
            'sibling_post_id' => $ig->id,
            'sibling_type' => InstagramPost::class,
        ], $jobOverrides));

        return [$job, $ig];
    }

    public function test_accepted_stores_post_id_and_hands_off(): void
    {
        Sanctum::actingAs(User::factory()->create());
        [$job] = $this->makeJobWithSibling();

        $this->postJson("/api/automation/postiz/{$job->id}/result", [
            'status' => 'accepted',
            'postiz_post_id' => 'pz-123',
        ])->assertOk();

        $job->refresh();
        $this->assertSame('accepted', $job->status);
        $this->assertSame('pz-123', $job->postiz_post_id);
        $this->assertNotNull($job->publish_lease_until);
    }

    public function test_published_flips_job_and_mirrors_sibling(): void
    {
        Sanctum::actingAs(User::factory()->create());
        [$job, $ig] = $this->makeJobWithSibling(['postiz_post_id' => 'pz-1', 'status' => 'accepted']);

        $this->postJson("/api/automation/postiz/{$job->id}/result", [
            'status' => 'published',
            'permalink' => 'https://instagram.com/p/abc',
        ])->assertOk();

        $job->refresh();
        $this->assertSame('published', $job->status);
        $this->assertSame('https://instagram.com/p/abc', $job->permalink);
        $this->assertNull($job->publish_lease_until);

        $ig->refresh();
        $this->assertSame('published', $ig->status);
        $this->assertNotNull($ig->published_at);
    }

    public function test_failed_pre_accepted_clears_lease_for_fallback(): void
    {
        Sanctum::actingAs(User::factory()->create());
        [$job] = $this->makeJobWithSibling(['postiz_post_id' => null, 'status' => 'claimed']);

        $this->postJson("/api/automation/postiz/{$job->id}/result", [
            'status' => 'failed',
            'error' => 'enqueue blew up',
        ])->assertOk();

        $job->refresh();
        $this->assertSame('failed', $job->status);
        $this->assertStringContainsString('enqueue', $job->last_error);
        $this->assertNull($job->publish_lease_until);
    }

    public function test_double_published_is_idempotent_noop(): void
    {
        Sanctum::actingAs(User::factory()->create());
        [$job] = $this->makeJobWithSibling(['postiz_post_id' => 'pz-1', 'status' => 'published', 'permalink' => 'https://x/p/1']);

        $this->postJson("/api/automation/postiz/{$job->id}/result", [
            'status' => 'published',
            'permalink' => 'https://x/p/SHOULD-NOT-OVERWRITE',
        ])->assertOk();

        $job->refresh();
        $this->assertSame('https://x/p/1', $job->permalink);
    }
}
