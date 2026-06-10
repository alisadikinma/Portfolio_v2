<?php

namespace Tests\Feature;

use App\Enums\LinkedInPostStatus;
use App\Jobs\GenerateLinkedInPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Feature tests for the `--post-id` targeted mode of `linkedin:scan-blog`.
 *
 * Targeted mode bypasses the lookback window (so a post published long ago can
 * still be converted on demand) while KEEPING the published / one-live-draft /
 * virality gates. Uses raw DB::table inserts (matches ScanLinkedInForCrossPostTest)
 * so the test doesn't depend on factories that don't exist for LinkedInPost /
 * ContentIdea.
 */
class ScanBlogTargetedPostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_post_id_creates_draft_for_published_post_above_gate(): void
    {
        // Published 10 days ago — proves the lookback window is bypassed.
        // virality_score=75, default linkedin_virality_min_score gate = 60.
        $postId = $this->seedPost(
            published: true,
            publishedAt: now()->subDays(10),
            viralityScore: 75,
        );

        $this->artisan('linkedin:scan-blog', ['--post-id' => $postId])
            ->assertExitCode(0);

        $this->assertDatabaseHas('linkedin_posts', [
            'post_id' => $postId,
            'status' => LinkedInPostStatus::PendingGeneration->value,
        ]);

        Queue::assertPushed(GenerateLinkedInPost::class, 1);
    }

    public function test_post_id_skips_below_virality_gate(): void
    {
        // virality_score=40 < default gate 60 → no draft, no dispatch.
        $postId = $this->seedPost(
            published: true,
            publishedAt: now()->subDays(10),
            viralityScore: 40,
        );

        $this->artisan('linkedin:scan-blog', ['--post-id' => $postId])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('linkedin_posts', ['post_id' => $postId]);
        Queue::assertNotPushed(GenerateLinkedInPost::class);
    }

    public function test_post_id_skips_when_live_draft_exists(): void
    {
        $postId = $this->seedPost(
            published: true,
            publishedAt: now()->subDays(10),
            viralityScore: 75,
        );

        // Existing non-soft-deleted live draft → one-live-draft gate skips it.
        DB::table('linkedin_posts')->insert([
            'post_id' => $postId,
            'format' => 'text',
            'content' => 'existing live draft',
            'hashtags' => json_encode(['#test']),
            'status' => LinkedInPostStatus::AwaitingPublish->value,
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        $this->artisan('linkedin:scan-blog', ['--post-id' => $postId])
            ->assertExitCode(0);

        // Still exactly one row — no duplicate created.
        $this->assertDatabaseCount('linkedin_posts', 1);
        Queue::assertNotPushed(GenerateLinkedInPost::class);
    }

    public function test_post_id_skips_unpublished_post(): void
    {
        $postId = $this->seedPost(
            published: false,
            publishedAt: now()->subDays(10),
            viralityScore: 75,
        );

        $this->artisan('linkedin:scan-blog', ['--post-id' => $postId])
            ->assertExitCode(0);

        $this->assertDatabaseMissing('linkedin_posts', ['post_id' => $postId]);
        Queue::assertNotPushed(GenerateLinkedInPost::class);
    }

    public function test_non_targeted_scan_still_respects_hours_window(): void
    {
        // Published 10 days ago, virality 75 — passes every gate EXCEPT the
        // default 24h lookback window. Without --post-id it must be excluded.
        $postId = $this->seedPost(
            published: true,
            publishedAt: now()->subDays(10),
            viralityScore: 75,
        );

        $this->artisan('linkedin:scan-blog')
            ->assertExitCode(0);

        $this->assertDatabaseMissing('linkedin_posts', ['post_id' => $postId]);
        Queue::assertNotPushed(GenerateLinkedInPost::class);
    }

    /* -------------------- Test helpers -------------------- */

    /**
     * Seed a Post (+ PostTranslation + linked ContentIdea carrying
     * virality_score). Returns the posts.id. Raw DB::table to avoid factories.
     */
    private function seedPost(
        bool $published,
        \Illuminate\Support\Carbon $publishedAt,
        int $viralityScore,
    ): int {
        $categoryId = DB::table('categories')->value('id');
        if ($categoryId === null) {
            $categoryId = DB::table('categories')->insertGetId([
                'name' => 'General',
                'slug' => 'general',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $postId = DB::table('posts')->insertGetId([
            'category_id' => $categoryId,
            'title' => 'Targeted scan test post',
            'slug' => 'targeted-scan-' . uniqid(),
            'content' => 'Body for targeted scan test.',
            'published' => $published,
            'published_at' => $publishedAt,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('post_translations')->insert([
            'post_id' => $postId,
            'language' => 'id',
            'title' => 'Targeted scan test post',
            'slug' => 'targeted-scan-tr-' . uniqid(),
            'content' => 'Body for targeted scan test.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // ContentIdea linked via result_post_id carries the virality gate value.
        DB::table('content_ideas')->insert([
            'title' => 'Targeted scan idea',
            'pillar' => 'ai_agents',
            'priority' => 'medium',
            'niche' => 'AI & Tech',
            'result_post_id' => $postId,
            'virality_score' => $viralityScore,
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $postId;
    }
}
