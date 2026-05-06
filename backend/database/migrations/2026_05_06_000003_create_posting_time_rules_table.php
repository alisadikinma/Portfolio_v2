<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per docs/plans/2026-05-06-linkedin-calendar-and-ai-time-rules.md §5.1.
 *
 * Stores AI-researched best posting times per platform × day-of-week × hour ×
 * audience. Seeded via `php artisan posting-rules:research --platform=linkedin`
 * which SSH-dispatches a Sonnet WebSearch call and upserts the resolved rules.
 *
 * V1 calendar UI consumes only the LinkedIn rows; schema is multi-platform
 * (linkedin/instagram/tiktok/youtube_shorts) so future surfaces can read
 * without a schema change.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('posting_time_rules', function (Blueprint $table) {
            $table->id();

            $table->enum('platform', ['linkedin', 'instagram', 'tiktok', 'youtube_shorts']);
            $table->unsignedTinyInteger('day_of_week')->comment('0=Sun..6=Sat (Carbon dayOfWeek)');
            $table->unsignedTinyInteger('hour')->comment('0..23 in `timezone` field');
            $table->string('timezone', 64)->default('Asia/Jakarta');
            $table->unsignedTinyInteger('score')->comment('0=avoid, 50=neutral, 100=optimal');
            $table->string('audience', 64)->default('b2b_tech');

            $table->text('source_url')->nullable();
            $table->string('source_title', 255)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('last_researched_at');

            $table->timestamps();

            // One rule per (platform, dow, hour, audience, timezone) so reseeding
            // upserts cleanly without dupes.
            $table->unique(
                ['platform', 'day_of_week', 'hour', 'audience', 'timezone'],
                'ux_platform_dow_hour_audience'
            );
            $table->index(['platform', 'score'], 'idx_platform_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posting_time_rules');
    }
};
