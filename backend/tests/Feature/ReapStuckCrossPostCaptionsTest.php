<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LinkedInPostStatus;
use App\Jobs\GenerateInstagramPost;
use App\Jobs\GenerateThreadsPost;
use App\Jobs\GenerateTiktokPost;
use App\Models\Category;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\ThreadsPost;
use App\Models\TiktokPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * crosspost:reap — caption parity reaper (June 12, 2026, Req 3).
 *
 * Re-dispatches IG/TikTok/Threads caption siblings stuck in
 * pending_generation (worker missed) / generating (stale) / failed (bounded
 * transient retry). Skips cancelled-parent + settled + capped siblings.
 */
class ReapStuckCrossPostCaptionsTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->category = Category::create(['name' => 'Test', 'slug' => 'test-' . Str::random(4)]);
        Bus::fake();
    }

    private function carouselDraft(string $status = null): LinkedInPost
    {
        $post = Post::create([
            'category_id' => $this->category->id,
            'title' => 'P-' . Str::random(6),
            'content' => 'Body',
            'slug' => 'p-' . Str::random(8),
            'published' => true,
            'published_at' => now(),
        ]);

        return LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'status' => $status ?? LinkedInPostStatus::AwaitingPublish->value,
            'carousel_slides' => [['slide_number' => 1, 'image_status' => 'done', 'image_url' => 'https://e/1.png']],
        ]);
    }

    private function ageSibling(string $table, int $id, int $minutes): void
    {
        DB::table($table)->where('id', $id)->update(['updated_at' => now()->subMinutes($minutes)]);
    }

    public function test_stuck_pending_sibling_is_redispatched(): void
    {
        $draft = $this->carouselDraft();
        $ig = InstagramPost::create([
            'linkedin_post_id' => $draft->id,
            'post_id' => $draft->post_id,
            'status' => 'pending_generation',
            'caption' => '',
        ]);
        $this->ageSibling('instagram_posts', $ig->id, 30);

        $this->artisan('crosspost:reap')->assertExitCode(0);

        Bus::assertDispatched(GenerateInstagramPost::class, fn ($job) => $job->draftId === $ig->id);
    }

    public function test_stale_generating_sibling_is_redispatched(): void
    {
        $draft = $this->carouselDraft();
        $tt = TiktokPost::create([
            'linkedin_post_id' => $draft->id,
            'post_id' => $draft->post_id,
            'status' => 'generating',
            'caption' => '',
            'title' => 'x',
        ]);
        $this->ageSibling('tiktok_posts', $tt->id, 30);

        $this->artisan('crosspost:reap')->assertExitCode(0);

        Bus::assertDispatched(GenerateTiktokPost::class, fn ($job) => $job->draftId === $tt->id);
    }

    public function test_transient_failed_sibling_is_retried_and_count_bumped(): void
    {
        $draft = $this->carouselDraft();
        $th = ThreadsPost::create([
            'linkedin_post_id' => $draft->id,
            'post_id' => $draft->post_id,
            'status' => 'failed',
            'caption' => 'cap',
            'last_error' => 'network error while authoring',
            'auto_retry_count' => 0,
        ]);
        $this->ageSibling('threads_posts', $th->id, 30);

        $this->artisan('crosspost:reap')->assertExitCode(0);

        Bus::assertDispatched(GenerateThreadsPost::class, fn ($job) => $job->draftId === $th->id);
        $this->assertSame(1, (int) $th->fresh()->auto_retry_count);
    }

    public function test_failed_sibling_at_cap_is_not_retried(): void
    {
        $draft = $this->carouselDraft();
        $ig = InstagramPost::create([
            'linkedin_post_id' => $draft->id,
            'post_id' => $draft->post_id,
            'status' => 'failed',
            'caption' => 'cap',
            'last_error' => 'network error',
            'auto_retry_count' => 2,
        ]);
        $this->ageSibling('instagram_posts', $ig->id, 30);

        $this->artisan('crosspost:reap')->assertExitCode(0);

        Bus::assertNotDispatched(GenerateInstagramPost::class);
    }

    public function test_cancelled_parent_sibling_is_skipped(): void
    {
        $draft = $this->carouselDraft(LinkedInPostStatus::Cancelled->value);
        $ig = InstagramPost::create([
            'linkedin_post_id' => $draft->id,
            'post_id' => $draft->post_id,
            'status' => 'pending_generation',
            'caption' => '',
        ]);
        $this->ageSibling('instagram_posts', $ig->id, 30);

        $this->artisan('crosspost:reap')->assertExitCode(0);

        Bus::assertNotDispatched(GenerateInstagramPost::class);
    }

    public function test_fresh_pending_below_threshold_is_not_retried(): void
    {
        $draft = $this->carouselDraft();
        $ig = InstagramPost::create([
            'linkedin_post_id' => $draft->id,
            'post_id' => $draft->post_id,
            'status' => 'pending_generation',
            'caption' => '',
        ]);
        // updated_at = now() (fresh) → below the 10m pending threshold.

        $this->artisan('crosspost:reap')->assertExitCode(0);

        Bus::assertNotDispatched(GenerateInstagramPost::class);
    }

    public function test_settled_awaiting_review_sibling_is_not_retried(): void
    {
        $draft = $this->carouselDraft();
        $ig = InstagramPost::create([
            'linkedin_post_id' => $draft->id,
            'post_id' => $draft->post_id,
            'status' => 'awaiting_review',
            'caption' => 'done caption',
        ]);
        $this->ageSibling('instagram_posts', $ig->id, 60);

        $this->artisan('crosspost:reap')->assertExitCode(0);

        Bus::assertNotDispatched(GenerateInstagramPost::class);
    }
}
