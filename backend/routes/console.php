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
