<?php

namespace Tests\Feature;

use App\Enums\LinkedInPostStatus;
use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\PostTranslation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Coverage for `linkedin:reap-stuck` — the recovery cron that flips
 * generating/validating drafts past their SSH+job timeout budget into
 * Failed so admin can restart them.
 *
 * Pre-fix, any worker crash or SSH timeout that bypassed markFailed()
 * left the row hanging in `generating` forever (queue retry path
 * silent-skipped past PendingGeneration).
 */
class ReapStuckLinkedInPostsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * LinkedInPostFactory expects a real Post id — PostFactory is an empty
     * stub, so we mint a minimal one ourselves (mirrors LinkedInPostSeeder).
     */
    private function makePost(): Post
    {
        $category = Category::query()->first() ?? Category::create([
            'name' => 'Test Category',
            'slug' => 'test-category-' . Str::random(6),
        ]);

        $slug = 'reap-test-' . Str::random(8);
        // Posts table still carries legacy `title` + `content` NOT NULL columns
        // from the original 2025-10-02 migration even though production reads
        // these from post_translations. Test SQLite enforces strictly, so we
        // satisfy them with placeholder values.
        $post = Post::create([
            'category_id' => $category->id,
            'title' => 'Reap Stuck Test',
            'content' => 'Reaper test source.',
            'slug' => $slug,
            'published' => false,
            'published_at' => null,
        ]);

        PostTranslation::create([
            'post_id' => $post->id,
            'language' => 'id',
            'title' => 'Reap Stuck Test ' . Str::random(4),
            'slug' => $slug . '-id',
            'content' => '<p>Reaper test source.</p>',
            'excerpt' => 'Reaper test source.',
        ]);

        return $post;
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Pre-mint a handful of Posts so LinkedInPostFactory has fresh
        // post_ids to draw from across multiple factory()->create() calls
        // within a single test (the factory dedupes against existing
        // LinkedInPost rows).
        for ($i = 0; $i < 5; $i++) {
            $this->makePost();
        }
    }

    /** @test */
    public function reaps_generating_drafts_past_threshold(): void
    {
        $stuck = LinkedInPost::factory()->create([
            'status' => LinkedInPostStatus::Generating->value,
            'updated_at' => now()->subMinutes(25),
            'pipeline_state_log' => [
                ['from' => 'pending_generation', 'to' => 'generating', 'reason' => 'plugin_dispatch_start', 'timestamp' => now()->subMinutes(25)->toIso8601String()],
            ],
        ]);

        $this->artisan('linkedin:reap-stuck', ['--threshold' => 20])
            ->expectsOutputToContain('Found 1 stuck')
            ->expectsOutputToContain('Reaped 1')
            ->assertSuccessful();

        $stuck->refresh();
        $this->assertSame(LinkedInPostStatus::Failed->value, $stuck->status);
        $this->assertNotNull($stuck->last_error);
        $this->assertStringContainsString('Reaper', $stuck->last_error);
    }

    /** @test */
    public function reaps_validating_drafts_past_threshold(): void
    {
        $stuck = LinkedInPost::factory()->create([
            'status' => LinkedInPostStatus::Validating->value,
            'updated_at' => now()->subMinutes(30),
        ]);

        $this->artisan('linkedin:reap-stuck', ['--threshold' => 20])
            ->assertSuccessful();

        $stuck->refresh();
        $this->assertSame(LinkedInPostStatus::Failed->value, $stuck->status);
    }

    /** @test */
    public function does_not_reap_fresh_in_flight_drafts(): void
    {
        $fresh = LinkedInPost::factory()->create([
            'status' => LinkedInPostStatus::Generating->value,
            'updated_at' => now()->subMinutes(5), // well below 20m threshold
        ]);

        $this->artisan('linkedin:reap-stuck', ['--threshold' => 20])
            ->assertSuccessful();

        $fresh->refresh();
        $this->assertSame(LinkedInPostStatus::Generating->value, $fresh->status);
        $this->assertNull($fresh->last_error);
    }

    /** @test */
    public function ignores_non_in_flight_states(): void
    {
        $awaiting = LinkedInPost::factory()->awaitingPublish()->create([
            'updated_at' => now()->subHour(),
        ]);
        $manual = LinkedInPost::factory()->manualReview()->create([
            'updated_at' => now()->subHour(),
        ]);
        $failed = LinkedInPost::factory()->failed()->create([
            'updated_at' => now()->subHour(),
        ]);

        $this->artisan('linkedin:reap-stuck')->assertSuccessful();

        $this->assertSame(LinkedInPostStatus::AwaitingPublish->value, $awaiting->fresh()->status);
        $this->assertSame(LinkedInPostStatus::ManualReview->value, $manual->fresh()->status);
        $this->assertSame(LinkedInPostStatus::Failed->value, $failed->fresh()->status);
    }

    /** @test */
    public function dry_run_does_not_mutate(): void
    {
        $stuck = LinkedInPost::factory()->create([
            'status' => LinkedInPostStatus::Generating->value,
            'updated_at' => now()->subMinutes(60),
        ]);

        $this->artisan('linkedin:reap-stuck', ['--threshold' => 20, '--dry-run' => true])
            ->expectsOutputToContain('Dry run')
            ->assertSuccessful();

        $stuck->refresh();
        $this->assertSame(LinkedInPostStatus::Generating->value, $stuck->status);
        $this->assertNull($stuck->last_error);
    }

    /** @test */
    public function threshold_is_clamped_to_minimum_15_minutes(): void
    {
        // Row stuck for 12 min — would be reaped by threshold=10 (if accepted)
        // but should NOT be reaped because the command clamps min to 15.
        $borderline = LinkedInPost::factory()->create([
            'status' => LinkedInPostStatus::Generating->value,
            'updated_at' => now()->subMinutes(12),
        ]);

        $this->artisan('linkedin:reap-stuck', ['--threshold' => 5])
            ->assertSuccessful();

        $this->assertSame(
            LinkedInPostStatus::Generating->value,
            $borderline->fresh()->status,
            'Should not reap — threshold clamped to 15 min minimum'
        );
    }

    /** @test */
    public function reaper_persists_audit_log_entry(): void
    {
        $stuck = LinkedInPost::factory()->create([
            'status' => LinkedInPostStatus::Generating->value,
            'updated_at' => now()->subMinutes(40),
            'pipeline_state_log' => [
                ['from' => 'pending_generation', 'to' => 'generating', 'reason' => 'cron_scan_detected_new_post', 'timestamp' => now()->subMinutes(40)->toIso8601String()],
            ],
        ]);

        $this->artisan('linkedin:reap-stuck', ['--threshold' => 20])->assertSuccessful();

        $stuck->refresh();
        $log = $stuck->pipeline_state_log;
        $this->assertIsArray($log);
        $this->assertGreaterThanOrEqual(2, count($log));

        // Last entry should be the reaper transition
        $last = end($log);
        $this->assertSame('generating', $last['from']);
        $this->assertSame('failed', $last['to']);
        $this->assertSame('reaped_stuck', $last['reason']);
    }
}
