<?php

namespace Tests\Feature;

use App\Models\ScheduledCommand;
use Database\Seeders\ScheduledCommandSeeder;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Console\Events\ScheduledTaskStarting;
use Illuminate\Console\Scheduling\CacheEventMutex;
use Illuminate\Console\Scheduling\Event as ScheduleEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Phase D — verifies the AppServiceProvider audit listeners mutate
 * the matching scheduled_commands row on the three ScheduledTask*
 * lifecycle events.
 *
 * Uses a real Illuminate\Console\Scheduling\Event as the `task`
 * payload (resolved via container so the EventMutex dependency
 * is satisfied) — same shape Laravel's scheduler emits in production.
 */
class ScheduledCommandAuditHookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Populate the canonical 18-row inventory so the listeners have
        // real signatures to match against.
        $this->seed(ScheduledCommandSeeder::class);
    }

    /**
     * Build a real schedule Event for the given full-command string.
     */
    private function buildScheduleEvent(string $command): ScheduleEvent
    {
        $mutex = $this->app->make(CacheEventMutex::class);

        return new ScheduleEvent($mutex, $command);
    }

    public function test_starting_event_marks_running(): void
    {
        $task = $this->buildScheduleEvent('php artisan linkedin:scan-blog --hours=24');

        // Sanity: row exists and starts at 'never'.
        $row = ScheduledCommand::where('signature', 'linkedin:scan-blog')->first();
        $this->assertNotNull($row, 'Seeder should have created linkedin:scan-blog row');
        $this->assertSame('never', $row->last_status);
        $this->assertNull($row->last_run_at);

        event(new ScheduledTaskStarting($task));

        $row->refresh();
        $this->assertSame('running', $row->last_status);
        $this->assertNotNull($row->last_run_at);
        $this->assertNull($row->last_error);
    }

    public function test_finished_event_marks_success_with_duration(): void
    {
        $task = $this->buildScheduleEvent('php artisan linkedin:scan-blog --hours=24');

        // Simulate the full lifecycle: Starting → Finished (1.842s runtime).
        event(new ScheduledTaskStarting($task));
        event(new ScheduledTaskFinished($task, 1.842));

        $row = ScheduledCommand::where('signature', 'linkedin:scan-blog')->first();
        $this->assertSame('success', $row->last_status);
        $this->assertNotNull($row->last_finished_at);
        $this->assertNull($row->last_error);
        // Runtime 1.842s → 1842ms.
        $this->assertSame(1842, $row->last_duration_ms);
    }

    public function test_failed_event_marks_failed_with_error_message(): void
    {
        $task = $this->buildScheduleEvent('php artisan content:auto-pipeline');

        $exception = new RuntimeException('Database connection refused at host 10.0.0.5');

        event(new ScheduledTaskStarting($task));
        event(new ScheduledTaskFailed($task, $exception));

        $row = ScheduledCommand::where('signature', 'content:auto-pipeline')->first();
        $this->assertSame('failed', $row->last_status);
        $this->assertNotNull($row->last_finished_at);
        $this->assertNotNull($row->last_error);
        $this->assertStringContainsString('Database connection refused', $row->last_error);
        // Truncation safety — must never exceed 2000 chars even on
        // multi-MB exception payloads.
        $this->assertLessThanOrEqual(2000, mb_strlen($row->last_error));
    }
}
