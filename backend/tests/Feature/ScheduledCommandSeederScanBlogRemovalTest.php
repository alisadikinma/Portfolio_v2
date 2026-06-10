<?php

namespace Tests\Feature;

use App\Models\ScheduledCommand;
use Database\Seeders\ScheduledCommandSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase C — scan-blog removal (2026-06-10).
 * Publishing now auto-creates cross-post drafts (event-driven), so the daily
 * linkedin:scan-blog cron is retired. The seeder must drop the row on re-seed
 * for already-deployed DBs (ghost-delete), and never re-create it.
 */
class ScheduledCommandSeederScanBlogRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_removes_scan_blog_row(): void
    {
        // Simulate an already-deployed DB that still has the legacy cron row.
        ScheduledCommand::factory()->create([
            'signature' => 'linkedin:scan-blog',
            'display_name' => 'LinkedIn — Scan Blog for Conversion',
            'category' => 'linkedin',
            'cron_expression' => '0 3 * * *',
            'without_overlapping_minutes' => 30,
            'arguments' => ['--hours=24'],
            'sort_order' => 20,
        ]);

        $this->assertTrue(
            ScheduledCommand::where('signature', 'linkedin:scan-blog')->exists(),
            'Pre-condition: legacy scan-blog row should exist before seeding.'
        );

        $this->seed(ScheduledCommandSeeder::class);

        $this->assertTrue(
            ScheduledCommand::where('signature', 'linkedin:scan-blog')->doesntExist(),
            'Seeder should ghost-delete the linkedin:scan-blog scheduled row.'
        );
    }
}
