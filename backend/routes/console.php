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
