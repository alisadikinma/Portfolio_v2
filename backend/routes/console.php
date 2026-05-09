<?php

use App\Services\DynamicScheduleRegistrar;
use Illuminate\Console\Scheduling\Schedule as ScheduleClass;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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
 * Static fallback schedule entries — registered alongside the dynamic
 * registry above. `withoutOverlapping(N)` makes them safe to run if the
 * DB-table-driven scheduler also picks them up (cron will simply no-op).
 *
 * social-cross-post:scan — every 2 minutes. Fans out finished LinkedIn
 * drafts to facebook_posts (always) + instagram_posts + tiktok_posts
 * (carousel format only). See ScanLinkedInForCrossPost for routing logic.
 */
Schedule::command('social-cross-post:scan')
    ->everyTwoMinutes()
    ->withoutOverlapping(5)
    ->runInBackground();
