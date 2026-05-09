<?php

namespace Tests\Feature;

use App\Enums\LinkedInPostStatus;
use App\Jobs\GenerateFacebookPost;
use App\Jobs\GenerateInstagramPost;
use App\Jobs\GenerateTiktokPost;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Feature tests for `social-cross-post:scan` cron command.
 *
 * Uses raw DB::table inserts (matches CrossPostSchemaTest pattern) so the
 * test doesn't depend on factories that don't exist yet for FacebookPost /
 * InstagramPost / TiktokPost.
 */
class ScanLinkedInForCrossPostTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        // Disable virality gate by default so tests don't need ContentIdea
        // setup. Individual tests can re-enable it.
        Setting::create([
            'key' => 'linkedin_virality_min_score',
            'group' => 'linkedin',
            'value' => '0',
            'type' => 'text',
        ]);
    }

    public function test_text_format_creates_only_facebook_row(): void
    {
        $linkedinPostId = $this->seedLinkedInPost(format: 'text');

        $this->artisan('social-cross-post:scan', ['--min-virality' => 0])
            ->assertExitCode(0);

        $this->assertDatabaseHas('facebook_posts', [
            'linkedin_post_id' => $linkedinPostId,
            'format' => 'text',
            'status' => 'pending_generation',
        ]);
        $this->assertDatabaseCount('instagram_posts', 0);
        $this->assertDatabaseCount('tiktok_posts', 0);

        Queue::assertPushed(GenerateFacebookPost::class, 1);
        Queue::assertNotPushed(GenerateInstagramPost::class);
        Queue::assertNotPushed(GenerateTiktokPost::class);
    }

    public function test_carousel_format_with_all_slides_done_creates_three_rows(): void
    {
        $linkedinPostId = $this->seedLinkedInPost(
            format: 'carousel',
            carouselSlides: [
                ['slide_number' => 1, 'image_status' => 'done', 'image_url' => 'https://x/1.png'],
                ['slide_number' => 2, 'image_status' => 'done', 'image_url' => 'https://x/2.png'],
                ['slide_number' => 3, 'image_status' => 'done', 'image_url' => 'https://x/3.png'],
            ]
        );

        $this->artisan('social-cross-post:scan', ['--min-virality' => 0])
            ->assertExitCode(0);

        $this->assertDatabaseHas('facebook_posts', [
            'linkedin_post_id' => $linkedinPostId,
            'format' => 'carousel',
        ]);
        $this->assertDatabaseHas('instagram_posts', ['linkedin_post_id' => $linkedinPostId]);
        $this->assertDatabaseHas('tiktok_posts', ['linkedin_post_id' => $linkedinPostId]);

        Queue::assertPushed(GenerateFacebookPost::class, 1);
        Queue::assertPushed(GenerateInstagramPost::class, 1);
        Queue::assertPushed(GenerateTiktokPost::class, 1);
    }

    public function test_carousel_with_pending_slide_is_skipped(): void
    {
        $this->seedLinkedInPost(
            format: 'carousel',
            carouselSlides: [
                ['slide_number' => 1, 'image_status' => 'done'],
                ['slide_number' => 2, 'image_status' => 'generating'], // not ready
                ['slide_number' => 3, 'image_status' => 'done'],
            ]
        );

        $this->artisan('social-cross-post:scan', ['--min-virality' => 0])
            ->assertExitCode(0);

        $this->assertDatabaseCount('facebook_posts', 0);
        $this->assertDatabaseCount('instagram_posts', 0);
        $this->assertDatabaseCount('tiktok_posts', 0);
        Queue::assertNothingPushed();
    }

    public function test_is_idempotent_running_twice_creates_no_duplicates(): void
    {
        $this->seedLinkedInPost(format: 'text');

        $this->artisan('social-cross-post:scan', ['--min-virality' => 0])->assertExitCode(0);
        $this->assertDatabaseCount('facebook_posts', 1);

        // Second run should detect existing FB row and skip.
        $this->artisan('social-cross-post:scan', ['--min-virality' => 0])->assertExitCode(0);
        $this->assertDatabaseCount('facebook_posts', 1);

        Queue::assertPushed(GenerateFacebookPost::class, 1); // not 2
    }

    public function test_dry_run_produces_no_db_writes_or_jobs(): void
    {
        $this->seedLinkedInPost(format: 'text');

        $this->artisan('social-cross-post:scan', [
            '--dry-run' => true,
            '--min-virality' => 0,
        ])->assertExitCode(0);

        $this->assertDatabaseCount('facebook_posts', 0);
        $this->assertDatabaseCount('instagram_posts', 0);
        $this->assertDatabaseCount('tiktok_posts', 0);
        Queue::assertNothingPushed();
    }

    public function test_virality_gate_skips_post_below_threshold(): void
    {
        $this->seedLinkedInPost(format: 'text', viralityScore: 50);

        $this->artisan('social-cross-post:scan', ['--min-virality' => 70])->assertExitCode(0);

        $this->assertDatabaseCount('facebook_posts', 0);
        Queue::assertNothingPushed();
    }

    public function test_virality_gate_admits_post_at_or_above_threshold(): void
    {
        $this->seedLinkedInPost(format: 'text', viralityScore: 75);

        $this->artisan('social-cross-post:scan', ['--min-virality' => 70])->assertExitCode(0);

        $this->assertDatabaseCount('facebook_posts', 1);
        Queue::assertPushed(GenerateFacebookPost::class, 1);
    }

    public function test_skips_linkedin_drafts_in_non_eligible_status(): void
    {
        // Status = generating (in-flight), not awaiting_publish or published
        $this->seedLinkedInPost(format: 'text', status: 'generating');

        $this->artisan('social-cross-post:scan', ['--min-virality' => 0])->assertExitCode(0);

        $this->assertDatabaseCount('facebook_posts', 0);
        Queue::assertNothingPushed();
    }

    /* -------------------- Test helpers -------------------- */

    /**
     * Seed a minimal LinkedIn post + parent Post + (optional) ContentIdea,
     * returning the linkedin_posts.id. Uses raw DB::table to avoid pulling
     * in factories that don't exist yet.
     *
     * @return int linkedin_posts.id
     */
    private function seedLinkedInPost(
        string $format,
        array $carouselSlides = [],
        string $status = LinkedInPostStatus::AwaitingPublish->value,
        ?int $viralityScore = null,
    ): int {
        $userId = DB::table('users')->insertGetId([
            'name' => 'Test',
            'email' => 'test+' . uniqid() . '@example.com',
            'password' => bcrypt('test'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Try existing category, else create
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
            'title' => 'Scanner test post',
            'slug' => 'scan-test-' . uniqid(),
            'content' => 'Body for scanner test.',
            'published' => true,
            'published_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($viralityScore !== null) {
            DB::table('content_ideas')->insert([
                'title' => 'Test scan idea',
                'pillar' => 'ai_agents',
                'priority' => 'medium',
                'niche' => 'AI & Tech',
                'result_post_id' => $postId,
                'virality_score' => $viralityScore,
                'status' => 'completed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $linkedinPostId = DB::table('linkedin_posts')->insertGetId([
            'post_id' => $postId,
            'format' => $format,
            'content' => $format === 'text' ? 'LinkedIn body content for fanout test' : '',
            'hashtags' => json_encode(['#test']),
            'carousel_slides' => $format === 'carousel' ? json_encode($carouselSlides) : null,
            'status' => $status,
            'created_at' => now()->subMinutes(5),
            'updated_at' => now()->subMinutes(5),
        ]);

        return $linkedinPostId;
    }
}
