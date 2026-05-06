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
// 05:00 follows the trending-topic pull (also 05:00) so freshly-imported
// drafts get an immediate first advance. 15:00 fills the long afternoon gap
// between the 12:00 lunchtime tick and the evening cluster (17-20:00).
foreach (['05:00', '06:00', '12:00', '15:00', '17:00', '18:00', '19:00', '20:00'] as $autoPipelineTime) {
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

// LinkedIn: publish drafts whose cancel window has elapsed. Every minute so
// scheduling resolution matches the user's expectation that approving at HH:MM
// publishes at HH:(MM+cancel_window). withoutOverlapping prevents stacking if
// LinkedIn API latency causes a tick to run long.
Schedule::command('linkedin:process-scheduled')
    ->everyMinute()
    ->withoutOverlapping(5);

// LinkedIn: scan blog for un-converted posts, dispatch plugin conversion jobs.
// Daily 03:00 WIB — matches plugin design (plugin expects 24h lookback).
Schedule::command('linkedin:scan-blog --hours=24')
    ->dailyAt('03:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(30);

// LinkedIn: reap drafts stuck in generating/validating past the SSH+job
// timeout budget. Without this, any worker crash or SSH timeout that bypassed
// markFailed() leaves rows hanging in `generating` forever (the queue retry
// path silent-skips rows past PendingGeneration). Every 5 min so a 20-min
// stuck row gets cleared within ~25 min of going dark.
Schedule::command('linkedin:reap-stuck')
    ->everyFiveMinutes()
    ->withoutOverlapping(5);

// LinkedIn: reap carousel slides stuck mid-render. One level deeper than
// reap-stuck above — handles per-slide `image_status='pending'` (>30m) and
// 'generating' (>15m) by re-dispatching GenerateLinkedInCarouselImages
// (idempotent, skips slides already done). Catches GeminiGen webhook drops
// and queue-worker crashes between persistAndRoute and the image job.
Schedule::command('linkedin:reap-stuck-carousel-images')
    ->everyFiveMinutes()
    ->withoutOverlapping(5);

// LinkedIn: daily 04:00 WIB — soft-delete drafts whose backing ContentIdea
// has decayed below `linkedin_virality_purge_below` (default 50). Runs after
// scan-blog (03:00) so any fresh ingest from the same morning gets evaluated
// against current virality scores. Idempotent + safety-railed (never touches
// published / cancelled / awaiting_publish).
Schedule::command('linkedin:purge-low-virality')
    ->dailyAt('04:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(15);

// Newsletter: weekly digest every Friday 09:00 WIB. Skip-if-empty handled
// inside the command (logs to newsletter_sends.status='skipped'). Reuses
// existing portfolio-queue.service systemd worker for actual Resend sends +
// portfolio-scheduler crontab for the trigger.
Schedule::command('newsletter:send-weekly')
    ->fridays()
    ->at('09:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(60);

// Posting Time Rules: quarterly research refresh — 03:00 WIB on the 1st
// day of every 3rd month (Jan/Apr/Jul/Oct). Sonnet WebSearches industry
// sources (Hootsuite/Buffer/Sprout/etc.) and upserts ~168 (dow,hour) rules
// per platform. Operator can also force-refresh via the calendar toolbar
// button (POST /admin/posting-rules/refresh) which dispatches the same
// command via Artisan::queue. Per docs/plans/2026-05-06-linkedin-calendar-
// and-ai-time-rules.md §5.3.
Schedule::command('posting-rules:research --platform=linkedin')
    ->cron('0 3 1 */3 *')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(15);
