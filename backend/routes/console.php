<?php

use App\Services\DynamicScheduleRegistrar;
use Illuminate\Console\Scheduling\Schedule as ScheduleClass;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Scheduled jobs are now driven by the scheduled_commands table — operators
 * toggle, retime, and run-now via /admin/settings → Scheduler tab. See
 * App\Services\DynamicScheduleRegistrar and docs/plans/2026-05-09-
 * admin-scheduler-tab.md.
 */
app(DynamicScheduleRegistrar::class)->register(app(ScheduleClass::class));

/*
 * social-cross-post:scan was the last static fallback here — promoted to a
 * DB-driven row in ScheduledCommandSeeder (June 9, 2026) so it's operator-
 * tunable from /admin/settings?tab=scheduler like every other schedule.
 * All schedules are now DB-driven via the registrar above.
 */
