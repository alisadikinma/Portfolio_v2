<?php

namespace Tests\Feature;

use App\Models\ScheduledCommand;
use Database\Seeders\ScheduledCommandSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase E — GROK hook-video poll/recovery cron. crosspost:poll-hook-videos must
 * be a real, enabled, every-minute DB-driven scheduled row (the SOLE completion
 * driver since GeminiGen never fires webhooks). Idempotent on re-seed.
 */
class ScheduledCommandSeederHookVideoTest extends TestCase
{
    use RefreshDatabase;

    public function test_poll_hook_videos_is_seeded_enabled_every_minute(): void
    {
        $this->seed(ScheduledCommandSeeder::class);

        $row = ScheduledCommand::where('signature', 'crosspost:poll-hook-videos')->first();

        $this->assertNotNull($row, 'crosspost:poll-hook-videos scheduled row should exist');
        $this->assertSame('* * * * *', $row->cron_expression);
        $this->assertSame('linkedin', $row->category);
        $this->assertTrue((bool) $row->enabled);
        $this->assertFalse((bool) $row->is_placeholder);
    }

    public function test_seeder_is_idempotent_for_poll_hook_videos(): void
    {
        $this->seed(ScheduledCommandSeeder::class);
        $this->seed(ScheduledCommandSeeder::class);

        $count = ScheduledCommand::where('signature', 'crosspost:poll-hook-videos')->count();
        $this->assertSame(1, $count);
    }
}
