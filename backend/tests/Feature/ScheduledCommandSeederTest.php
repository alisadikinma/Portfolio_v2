<?php

namespace Tests\Feature;

use App\Models\ScheduledCommand;
use Database\Seeders\ScheduledCommandSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Phase B — admin scheduler tab seeder tests.
 *
 * Asserts the seeded inventory matches the Final command inventory in
 * docs/plans/2026-05-09-admin-scheduler-tab.md (14 real + 4 placeholder = 18).
 */
class ScheduledCommandSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_18_rows_total(): void
    {
        Artisan::call('db:seed', ['--class' => ScheduledCommandSeeder::class]);

        $this->assertSame(18, ScheduledCommand::count(), 'Seeder must produce exactly 18 rows.');
        $this->assertSame(4, ScheduledCommand::where('is_placeholder', true)->count(), 'Exactly 4 placeholder rows expected.');
        $this->assertSame(14, ScheduledCommand::enabled()->count(), 'Exactly 14 enabled real rows expected (placeholders are disabled).');
    }

    public function test_seeder_is_idempotent(): void
    {
        Artisan::call('db:seed', ['--class' => ScheduledCommandSeeder::class]);
        Artisan::call('db:seed', ['--class' => ScheduledCommandSeeder::class]);

        $this->assertSame(18, ScheduledCommand::count(), 'Re-seeding must not duplicate rows.');
    }

    public function test_seeded_signatures_match_console_php_inventory(): void
    {
        Artisan::call('db:seed', ['--class' => ScheduledCommandSeeder::class]);

        $expected = [
            // Content Engine (5)
            'content:process-scheduled',
            'blog:process-images',
            'content:process-pending-translations',
            'content:auto-pipeline',
            'content:pull-trending-daily',
            // LinkedIn (7)
            'linkedin:process-scheduled',
            'linkedin:scan-blog',
            'linkedin:reap-stuck',
            'linkedin:retry-failed',
            'linkedin:reap-stuck-carousel-images',
            'linkedin:purge-low-virality',
            'linkedin:auto-schedule',
            // Newsletter (1)
            'newsletter:send-weekly',
            // System (1)
            'posting-rules:research',
        ];

        $this->assertCount(14, $expected, 'Sanity check on hardcoded list.');

        foreach ($expected as $signature) {
            $this->assertTrue(
                ScheduledCommand::where('signature', $signature)->exists(),
                "Expected signature '{$signature}' to exist after seed."
            );
        }
    }
}
