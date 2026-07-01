<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seeds the `linkedin` settings group with operator-facing publishing flags.
 *
 * Uses firstOrCreate (not updateOrCreate) so running on deploy never
 * clobbers admin-edited values. Same pattern as TelegramSettingsSeeder
 * and CreatorBrandSettingsSeeder.
 */
class LinkedInSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'linkedin_auto_publish',                 'value' => 'false', 'type' => 'text'],
            ['key' => 'linkedin_depth_score_threshold',        'value' => '80',    'type' => 'text'],
            ['key' => 'linkedin_cancel_window_minutes',        'value' => '15',    'type' => 'text'],
            ['key' => 'linkedin_first_comment_enabled',        'value' => 'true',  'type' => 'text'],
            ['key' => 'linkedin_first_comment_delay_seconds',  'value' => '30',    'type' => 'text'],
            ['key' => 'linkedin_last_test_connection_at',      'value' => null,    'type' => 'text'],
            ['key' => 'linkedin_last_test_connection_result',  'value' => null,    'type' => 'text'],
            // Virality gate (April 29, 2026):
            // - Scan only ingests blog posts whose ContentIdea.virality_score
            //   is >= linkedin_virality_min_score. Manual posts (no idea
            //   linkage) are skipped — operators can hand-create LinkedIn
            //   drafts for those if needed.
            // - Daily purge command soft-deletes non-terminal drafts whose
            //   source idea slipped below linkedin_virality_purge_below
            //   (e.g., re-scored after social signals decayed).
            // Both thresholds are operator-tunable from AboutSettings.
            ['key' => 'linkedin_virality_min_score',           'value' => '60',    'type' => 'text'],
            ['key' => 'linkedin_virality_purge_below',         'value' => '50',    'type' => 'text'],
            // Auto-approve cron (May 7, 2026):
            // When 'true', linkedin:auto-schedule (daily 04:30 WIB) promotes
            // manual_review drafts to awaiting_publish, assigning
            // cancel_window_ends_at to the next posting_time_rules.score >= 85
            // slot (14-day lookahead). Default OFF — feature dormant until
            // operator opts in via AboutSettings UI.
            ['key' => 'linkedin_auto_approve_enabled',         'value' => 'false', 'type' => 'text'],
            // Fixed-slot scheduler (May 12, 2026):
            // - linkedin_publish_slots: JSON array of int hours (0-23 WIB).
            //   LinkedInFixedSlotScheduler iterates these in order, 1-post-
            //   per-slot FIFO. Replaces the AI-researched posting_time_rules
            //   score>=85 lookup of LinkedInAutoSchedulerService.
            // - linkedin_slot_lead_time_minutes: minimum minutes between now()
            //   and a slot for it to be eligible. Guards against approving at
            //   04:59 and trying to publish at 05:00 (cancel window collapses).
            // - linkedin_publish_weekdays_only: when 'true', the scheduler skips
            //   Saturday + Sunday entirely (WIB) so posts only land Mon-Fri.
            ['key' => 'linkedin_publish_slots',                'value' => '[5,6,7,12,17,18,19,20]', 'type' => 'json'],
            ['key' => 'linkedin_slot_lead_time_minutes',       'value' => '5',     'type' => 'text'],
            ['key' => 'linkedin_publish_weekdays_only',        'value' => 'false', 'type' => 'text'],
            // Format-mix governor (May 12, 2026):
            // - linkedin_format_carousel_target_ratio: fraction 0.0-1.0,
            //   default 0.8 (80% carousel). LinkedInFormatMixGovernor
            //   re-dispatches plugin with format_preference=carousel when
            //   recent ratio drifts below this.
            // - linkedin_format_lookback_window: how many recent drafts to
            //   sample for ratio computation (default 10, bootstrap when <).
            // - linkedin_format_governor_enabled: master kill-switch. When
            //   'false', plugin's natural format decision is always honored.
            ['key' => 'linkedin_format_carousel_target_ratio', 'value' => '0.8',   'type' => 'text'],
            ['key' => 'linkedin_format_lookback_window',       'value' => '10',    'type' => 'text'],
            ['key' => 'linkedin_format_governor_enabled',      'value' => 'true',  'type' => 'text'],
            // Atomic slot orchestrator (May 12) — max times a carousel draft
            // can be postponed when siblings aren't ready, before LinkedIn
            // ships solo + siblings drop to manual_review.
            ['key' => 'linkedin_max_postpones',                 'value' => '2',     'type' => 'text'],
            // Default-carousel (June 9, 2026):
            // When 'true', LinkedInGenerationService ALWAYS routes carousel
            // format to /carousel-gen regardless of what /linkedin-gen decides
            // — bypasses the unreliable format_preference governor entirely
            // (plugin ignores that field). /carousel-gen failure → manual_review,
            // never a silent text downgrade. This makes every blog post produce
            // a carousel for all 4 platforms (LinkedIn/IG/TikTok/Threads), since
            // the cross-post fan-out only creates IG/TikTok/Threads-carousel
            // siblings when LinkedIn format=carousel. Operator-tunable.
            ['key' => 'linkedin_force_carousel',                'value' => 'true',  'type' => 'text'],

            // - linkedin_carousel_style: visual execution preset for /carousel-gen.
            //   'sketchnote' (default) = flat hand-drawn educational infographic on
            //   cream paper (Granola/Obsidian look, knowledge-first). 'cinematic'
            //   restores the photorealistic creator-face carousel. No-redeploy
            //   revert lever read by LinkedInGenerationService::buildCarouselGenPrompt.
            ['key' => 'linkedin_carousel_style',               'value' => 'sketchnote', 'type' => 'text'],

            // - linkedin_carousel_image_model: which GeminiGen model renders the
            //   carousel slide PNGs. 'nano-banana-pro' (default) = photorealistic;
            //   'gpt-image-2' = literal typography (best for sketchnote infographic
            //   text — Premium plan only). Read by LinkedInCarouselImageService::resolveModel.
            ['key' => 'linkedin_carousel_image_model',         'value' => 'nano-banana-pro', 'type' => 'text'],

            // Telegram scheduling conversation (June 12, 2026):
            // When 'true', a draft that becomes genuinely ready (carousel:
            // slides rendered + captions ready; text: validation passed) fires
            // ONE Telegram "kapan posting?" prompt with weekday/holiday-aware
            // slot buttons + free-text override. Enabling this SUPERSEDES
            // linkedin_auto_approve_enabled: linkedin:auto-schedule defers
            // (no-ops) so the human-in-the-loop Telegram flow owns scheduling.
            // Default OFF — zero behavior change until the operator opts in.
            ['key' => 'linkedin_telegram_schedule_enabled',    'value' => 'false', 'type' => 'text'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key'], 'group' => 'linkedin'],
                ['value' => $setting['value'], 'type' => $setting['type']]
            );
        }

        // Per Decision #9: LinkedIn-related telegram_notify_* flags live
        // in the EXISTING `telegram` group (owned by TelegramNotificationService).
        // Add the 3 new LinkedIn notification toggles here so they share
        // the group + are editable from the existing Telegram card.
        $telegramExtensions = [
            ['key' => 'telegram_notify_linkedin_preview',           'value' => 'true',  'type' => 'text'],
            ['key' => 'telegram_notify_linkedin_depth_failed',      'value' => 'true',  'type' => 'text'],
            ['key' => 'telegram_notify_linkedin_published',         'value' => 'true',  'type' => 'text'],
            // Fired when linkedin:auto-schedule walks the full 14-day
            // lookahead window without finding a free slot for any pending
            // manual_review draft. Default OFF — opt in if backlog growth
            // becomes a real signal worth waking up to.
            ['key' => 'telegram_notify_linkedin_backlog_exhausted', 'value' => 'false', 'type' => 'text'],
        ];

        foreach ($telegramExtensions as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key'], 'group' => 'telegram'],
                ['value' => $setting['value'], 'type' => $setting['type']]
            );
        }

        if ($this->command) {
            $this->command->info('✅ LinkedIn settings seeded (linkedin group + 4 telegram_notify_linkedin_* keys)');
        }
    }
}
