<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class TelegramSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'telegram_bot_token',                 'value' => null,    'type' => 'text'],
            ['key' => 'telegram_chat_id',                   'value' => null,    'type' => 'text'],
            ['key' => 'telegram_enabled',                   'value' => 'false', 'type' => 'text'],
            ['key' => 'telegram_notify_manifest_needed',    'value' => 'true',  'type' => 'text'],
            ['key' => 'telegram_notify_generation_failed',  'value' => 'true',  'type' => 'text'],
            ['key' => 'telegram_notify_publish_success',    'value' => 'false', 'type' => 'text'],
            ['key' => 'telegram_notify_segment_failed',     'value' => 'true',  'type' => 'text'],
            ['key' => 'telegram_notify_cover_critical',     'value' => 'true',  'type' => 'text'],
            ['key' => 'telegram_notify_translate_failed',   'value' => 'true',  'type' => 'text'],

            // Phase F additions — auto-retry exhaustion + carousel tier-2 failure
            // notify toggles. webhook_secret is the inbound Telegram webhook
            // shared secret (used in X-Telegram-Bot-Api-Secret-Token header for
            // server-to-server identity proof + as the HMAC key for inline
            // button callback_data signing).
            ['key' => 'telegram_notify_linkedin_auto_retry_exhausted', 'value' => 'true', 'type' => 'text'],
            ['key' => 'telegram_notify_idea_auto_retry_exhausted',     'value' => 'true', 'type' => 'text'],
            ['key' => 'telegram_notify_carousel_tier2_failed',          'value' => 'true', 'type' => 'text'],

            // Daily ack from content:pull-trending-daily so a silent cron
            // failure (worker crash, schedule:run removed) surfaces within 24h
            // instead of going unnoticed for days. Default 'true' on a fresh
            // install per operator request — flip to 'false' via UI to silence.
            ['key' => 'telegram_notify_trending_pulled',                'value' => 'true', 'type' => 'text'],

            // Phase I — GeminiGen circuit breaker state-transition alerts.
            // System-level (no ContentIdea context). When the breaker trips,
            // per-segment segment_retry_exhausted + cover_critical alerts are
            // suppressed in ImageGenerationService — operator gets exactly ONE
            // outage alert instead of N segment-spam messages.
            ['key' => 'telegram_notify_geminigen_circuit_open',         'value' => 'true', 'type' => 'text'],
            ['key' => 'telegram_notify_geminigen_circuit_close',        'value' => 'true', 'type' => 'text'],

            // Weekly stale-content freshness digest (GEO publish-and-forget fix).
            // System-level, NOT keyed to a ContentIdea. Default 'true' — flip via
            // UI to silence. Fires from content:flag-stale-posts (Mon 06:00 WIB).
            ['key' => 'telegram_notify_stale_content',                  'value' => 'true', 'type' => 'text'],

            // Image-generation HOLD escalation (GEO image-completion gate) — fires
            // once per stall when a segment exhausts its retry budget and the idea
            // is held at generating_images (never auto-published with a broken image).
            ['key' => 'telegram_notify_image_stalled',                  'value' => 'true', 'type' => 'text'],

            ['key' => 'telegram_webhook_secret',                        'value' => bin2hex(random_bytes(16)), 'type' => 'text'],

            // IG repurpose feature toggle (docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md).
            // When 'true', an Instagram URL sent to the bot from telegram_chat_id
            // triggers the capture → research → carousel pipeline. Default OFF.
            ['key' => 'telegram_repurpose_enabled',                     'value' => 'false', 'type' => 'text'],
        ];

        // firstOrCreate — NOT updateOrCreate — so running this seeder on every
        // deploy (see scripts/deploy.sh) only inserts missing rows and never
        // clobbers values the admin has saved through the UI. Mirrors the
        // CreatorBrandSettingsSeeder pattern — see that seeder's comment for
        // the regression that motivated firstOrCreate over updateOrCreate.
        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key'], 'group' => 'telegram'],
                ['value' => $setting['value'], 'type' => $setting['type']]
            );
        }

        if ($this->command) {
            $this->command->info('✅ Telegram notification settings seeded successfully!');
        }
    }
}
