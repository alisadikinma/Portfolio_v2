<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Content Engine: process scheduled ideas every minute
Schedule::command('content:process-scheduled')->everyMinute();

// Content Engine: poll GeminiGen for completed images every minute
Schedule::command('blog:process-images')->everyMinute();

// Content Engine: retry failed ID→EN translations every 5 minutes (max 3 attempts)
Schedule::command('content:process-pending-translations')->everyFiveMinutes()->withoutOverlapping();

// Content Engine: advance auto_mode ideas one stage per tick (strict sequential
// gating in orchestrator — operating hours + in-flight check). 10-min lock TTL
// covers longest single-stage SSH call. Fires 8x/day at fixed Jakarta times so
// the blog feed gets a controlled cadence instead of per-minute spam.
foreach (['05:30', '06:00', '12:00', '17:00', '18:00', '19:00', '20:00', '21:00'] as $autoPipelineTime) {
    Schedule::command('content:auto-pipeline')
        ->dailyAt($autoPipelineTime)
        ->timezone('Asia/Jakarta')
        ->withoutOverlapping(10);
}

// Content Engine: daily 05:00 Asia/Jakarta — pull trending topics, fuzzy-dedup
// against last 30 days, auto-import as draft ideas with auto_mode=true
Schedule::command('content:pull-trending-daily')
    ->dailyAt('05:00')
    ->timezone('Asia/Jakarta');
