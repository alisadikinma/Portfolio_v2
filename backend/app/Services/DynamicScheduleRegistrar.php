<?php

namespace App\Services;

use App\Models\ScheduledCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Phase C — admin scheduler tab dynamic registrar.
 *
 * Reads the `scheduled_commands` table and registers each enabled,
 * non-placeholder row with Laravel's Schedule facade. Replaces the
 * previously-hardcoded Schedule::command(...) blocks in routes/console.php
 * so operators can toggle, retime, and run-now via /admin/scheduler.
 *
 * Boot safety: silently no-ops when the table doesn't exist yet (fresh
 * install pre-migration). Without this guard, `php artisan schedule:run`
 * crashes during early bootstrap on a brand-new clone.
 */
class DynamicScheduleRegistrar
{
    public function register(Schedule $schedule): void
    {
        try {
            if (! Schema::hasTable('scheduled_commands')) {
                return;
            }

            $commands = ScheduledCommand::query()
                ->enabled()
                ->notPlaceholder()
                ->get();
        } catch (\Throwable $e) {
            // Boot safety: pre-migration boots (fresh CI install, MySQL down
            // during local artisan boot) must not crash schedule:run. Log so
            // production failures still surface in laravel.log without crashing
            // the kernel — silent return alone would hide ALL cron registration
            // failures behind an empty schedule list.
            Log::warning('DynamicScheduleRegistrar: skipped schedule registration', [
                'error' => $e->getMessage(),
                'class' => $e::class,
            ]);
            return;
        }

        $commands->each(function (ScheduledCommand $cmd) use ($schedule) {
            $argString = empty($cmd->arguments)
                ? ''
                : ' ' . implode(' ', $cmd->arguments);
            $fullSignature = $cmd->signature . $argString;

            $event = $schedule->command($fullSignature)
                ->cron($cmd->cron_expression)
                ->timezone($cmd->timezone);

            if ($cmd->without_overlapping_minutes) {
                $event->withoutOverlapping($cmd->without_overlapping_minutes);
            }

            if ($cmd->run_in_background) {
                $event->runInBackground();
            }
        });
    }
}
