<?php

namespace Tests\Feature;

use App\Models\ScheduledCommand;
use Database\Seeders\ScheduledCommandSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase F — default-carousel plan (2026-06-09).
 * social-cross-post:scan must be a real, enabled, DB-driven scheduled row
 * (promoted from the static routes/console.php fallback). Idempotent on re-seed.
 */
class ScheduledCommandSeederCrossPostTest extends TestCase
{
    use RefreshDatabase;

    public function test_cross_post_scan_is_seeded_as_enabled_db_row(): void
    {
        $this->seed(ScheduledCommandSeeder::class);

        $row = ScheduledCommand::where('signature', 'social-cross-post:scan')->first();

        $this->assertNotNull($row, 'social-cross-post:scan scheduled row should exist');
        $this->assertSame('*/2 * * * *', $row->cron_expression);
        $this->assertSame('linkedin', $row->category);
        $this->assertTrue((bool) $row->enabled);
        $this->assertFalse((bool) $row->is_placeholder);
    }

    public function test_seeder_is_idempotent_for_cross_post_scan(): void
    {
        $this->seed(ScheduledCommandSeeder::class);
        $this->seed(ScheduledCommandSeeder::class);

        $count = ScheduledCommand::where('signature', 'social-cross-post:scan')->count();
        $this->assertSame(1, $count);
    }
}
