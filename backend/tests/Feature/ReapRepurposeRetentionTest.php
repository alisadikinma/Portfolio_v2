<?php

namespace Tests\Feature;

use App\Models\LinkedInPost;
use App\Models\RepurposeJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Retention reaper publish-anchored policy (June 2026): source slides are
 * deleted N days AFTER the linked carousel is published, and kept while the
 * carousel is still unpublished (in review).
 */
class ReapRepurposeRetentionTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int,string> */
    private array $dirs = [];

    protected function tearDown(): void
    {
        foreach ($this->dirs as $d) {
            File::deleteDirectory($d);
        }
        parent::tearDown();
    }

    private function dirFor(int $jobId): string
    {
        $dir = storage_path('app/repurpose/' . $jobId);
        File::ensureDirectoryExists($dir);
        File::put($dir . '/slide-01.jpg', 'x');
        $this->dirs[] = $dir;
        return $dir;
    }

    private function jobPublishedDaysAgo(int $days): RepurposeJob
    {
        $li = LinkedInPost::factory()->create([
            'status' => 'published',
            'published_at' => now()->subDays($days),
        ]);

        return RepurposeJob::factory()->create([
            'mode' => 'carousel',
            'status' => 'drafted',
            'linkedin_post_id' => $li->id,
        ]);
    }

    public function test_purges_source_seven_days_after_publish(): void
    {
        $job = $this->jobPublishedDaysAgo(10);
        $dir = $this->dirFor($job->id);

        $this->artisan('repurpose:reap', ['--days' => 7])->assertExitCode(0);

        $this->assertDirectoryDoesNotExist($dir);
    }

    public function test_keeps_source_within_seven_days_of_publish(): void
    {
        $job = $this->jobPublishedDaysAgo(2);
        $dir = $this->dirFor($job->id);

        $this->artisan('repurpose:reap', ['--days' => 7])->assertExitCode(0);

        $this->assertDirectoryExists($dir);
    }

    public function test_keeps_unpublished_draft_source_past_publish_cutoff(): void
    {
        $li = LinkedInPost::factory()->create(['status' => 'pending_generation', 'published_at' => null]);
        $job = RepurposeJob::factory()->create([
            'mode' => 'carousel',
            'status' => 'drafted',
            'linkedin_post_id' => $li->id,
        ]);
        $dir = $this->dirFor($job->id);
        // Old mtime, but not yet published → kept (well within the 30d abandon cap).
        touch($dir, now()->subDays(20)->getTimestamp());

        $this->artisan('repurpose:reap', ['--days' => 7])->assertExitCode(0);

        $this->assertDirectoryExists($dir);
    }
}
