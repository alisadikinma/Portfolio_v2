<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * content:flag-stale-posts must stamp stale_notified_at on un-notified stale
 * posts, leave fresh posts untouched, suppress re-alerts within 30 days, and
 * mutate nothing under --dry-run. It must NOT touch the freshness anchor.
 */
class FlagStalePostsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(array $overrides = []): Post
    {
        $category = Category::firstOrCreate(['slug' => 'ai'], ['name' => 'AI']);

        return Post::create(array_merge([
            'category_id' => $category->id,
            'slug' => 'post-' . uniqid(),
            'content' => '<p>body</p>',
            'published' => true,
            'published_at' => now()->subDays(120),
        ], $overrides));
    }

    public function test_flags_stale_posts_and_skips_fresh(): void
    {
        $stale1 = $this->makePost(['published_at' => now()->subDays(120)]);
        $stale2 = $this->makePost(['published_at' => now()->subDays(95)]);
        $fresh = $this->makePost(['published_at' => now()->subDays(10)]);

        $this->artisan('content:flag-stale-posts', ['--days' => 90])
            ->assertExitCode(0);

        $this->assertNotNull($stale1->fresh()->stale_notified_at);
        $this->assertNotNull($stale2->fresh()->stale_notified_at);
        $this->assertNull($fresh->fresh()->stale_notified_at);
    }

    public function test_dry_run_mutates_nothing(): void
    {
        $stale = $this->makePost(['published_at' => now()->subDays(120)]);

        $this->artisan('content:flag-stale-posts', ['--days' => 90, '--dry-run' => true])
            ->assertExitCode(0);

        $this->assertNull($stale->fresh()->stale_notified_at);
    }

    public function test_suppresses_realert_within_30_days(): void
    {
        $recentlyNotified = $this->makePost([
            'published_at' => now()->subDays(120),
            'stale_notified_at' => now()->subDays(5),
        ]);

        $this->artisan('content:flag-stale-posts', ['--days' => 90])
            ->assertExitCode(0);

        // Notification timestamp must NOT be refreshed (no re-spam).
        $this->assertEquals(
            $recentlyNotified->stale_notified_at->timestamp,
            $recentlyNotified->fresh()->stale_notified_at->timestamp
        );
    }

    public function test_flagging_does_not_alter_freshness_anchor(): void
    {
        $stale = $this->makePost(['published_at' => now()->subDays(120)]);

        $this->artisan('content:flag-stale-posts', ['--days' => 90])->assertExitCode(0);

        $fresh = $stale->fresh();
        $this->assertNull($fresh->content_reviewed_at, 'flagging must not stamp content_reviewed_at');
        // Still stale after flagging — anchor unchanged, only notified.
        $this->assertContains($stale->id, Post::stale(90)->pluck('id')->all());
    }
}
