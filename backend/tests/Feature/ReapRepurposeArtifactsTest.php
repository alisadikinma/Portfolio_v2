<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Phase G — retention reaper. Dirs older than N days are purged; fresh dirs
 * kept; --dry-run purges nothing. Idempotent.
 */
class ReapRepurposeArtifactsTest extends TestCase
{
    use RefreshDatabase;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = storage_path('app/repurpose');
        File::ensureDirectoryExists($this->base);
    }

    protected function tearDown(): void
    {
        foreach (['old-9001', 'fresh-9002', 'old-9003'] as $d) {
            File::deleteDirectory($this->base . '/' . $d);
        }
        parent::tearDown();
    }

    public function test_old_dir_purged_fresh_kept(): void
    {
        $old = $this->base . '/old-9001';
        $fresh = $this->base . '/fresh-9002';
        File::ensureDirectoryExists($old);
        File::ensureDirectoryExists($fresh);
        File::put($old . '/slide-01.jpg', 'x');
        touch($old, now()->subDays(10)->getTimestamp());
        touch($fresh, now()->subDays(1)->getTimestamp());

        $this->artisan('repurpose:reap', ['--days' => 7])->assertExitCode(0);

        $this->assertDirectoryDoesNotExist($old);
        $this->assertDirectoryExists($fresh);
    }

    public function test_dry_run_purges_nothing(): void
    {
        $old = $this->base . '/old-9003';
        File::ensureDirectoryExists($old);
        touch($old, now()->subDays(30)->getTimestamp());

        $this->artisan('repurpose:reap', ['--days' => 7, '--dry-run' => true])->assertExitCode(0);

        $this->assertDirectoryExists($old);
    }
}
