<?php

namespace Tests\Feature;

use App\Services\DynamicScheduleRegistrar;
use Database\Seeders\ScheduledCommandSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Phase C — DynamicScheduleRegistrar tests.
 *
 * The registrar replaces the static Schedule::command(...) blocks in
 * routes/console.php with table-driven registration sourced from the
 * scheduled_commands table.
 */
class DynamicScheduleRegistrarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:seed', ['--class' => ScheduledCommandSeeder::class]);
    }

    private function freshSchedule(): Schedule
    {
        return $this->app->make(Schedule::class, ['eventMutex' => null]);
    }

    public function test_registers_only_enabled_non_placeholder_rows(): void
    {
        $schedule = new Schedule();

        app(DynamicScheduleRegistrar::class)->register($schedule);

        // Seeder yields 14 enabled real rows + 4 placeholder (disabled) rows.
        $this->assertCount(
            14,
            $schedule->events(),
            'Registrar must register exactly 14 events (enabled + non-placeholder).'
        );
    }

    public function test_skips_placeholder_rows(): void
    {
        $schedule = new Schedule();

        app(DynamicScheduleRegistrar::class)->register($schedule);

        foreach ($schedule->events() as $event) {
            $this->assertStringNotContainsString(
                'placeholder:',
                $event->command ?? '',
                'No placeholder commands should ever be registered.'
            );
        }
    }

    public function test_applies_arguments_to_signature(): void
    {
        $schedule = new Schedule();

        app(DynamicScheduleRegistrar::class)->register($schedule);

        $matched = null;
        foreach ($schedule->events() as $event) {
            if (str_contains($event->command ?? '', 'linkedin:scan-blog')) {
                $matched = $event;
                break;
            }
        }

        $this->assertNotNull($matched, 'linkedin:scan-blog event must be registered.');
        $this->assertStringContainsString(
            '--hours=24',
            $matched->command,
            'linkedin:scan-blog event must include arguments from the arguments column.'
        );
    }

    public function test_safe_when_table_missing(): void
    {
        Schema::drop('scheduled_commands');

        $schedule = new Schedule();

        // Must not throw — boot-safety guard returns silently.
        app(DynamicScheduleRegistrar::class)->register($schedule);

        $this->assertCount(0, $schedule->events());
    }
}
