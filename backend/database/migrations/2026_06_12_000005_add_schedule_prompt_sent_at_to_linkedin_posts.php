<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Telegram scheduling conversation (June 12, 2026, Phase A).
 *
 * `schedule_prompt_sent_at` is the one-prompt idempotency guard for the
 * linkedin:prompt-schedule cron — once a "kapan posting?" Telegram prompt
 * has been sent for a ready draft, the cron never re-prompts (operator chose
 * one-prompt, no reminder). NULL = not yet prompted. Cleared back to NULL by
 * LinkedInSchedulingService::scheduleAt once the draft is actually scheduled.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('linkedin_posts')) {
            return;
        }
        Schema::table('linkedin_posts', function (Blueprint $table) {
            if (! Schema::hasColumn('linkedin_posts', 'schedule_prompt_sent_at')) {
                $table->timestamp('schedule_prompt_sent_at')
                    ->nullable()
                    ->after('cancel_window_ends_at')
                    ->comment('When the Telegram "kapan posting?" prompt was sent (one-prompt idempotency)');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('linkedin_posts')) {
            return;
        }
        Schema::table('linkedin_posts', function (Blueprint $table) {
            if (Schema::hasColumn('linkedin_posts', 'schedule_prompt_sent_at')) {
                $table->dropColumn('schedule_prompt_sent_at');
            }
        });
    }
};
