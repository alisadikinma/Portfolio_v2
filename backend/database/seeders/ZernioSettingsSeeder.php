<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seed operator-facing Zernio integration settings (2026-06-15).
 *
 * 16 keys live in the `settings` table (group=zernio). Operator manages all via
 * the "Zernio Publishing" card on /admin/about.
 *
 * Three workspace API keys (Zernio keys are workspace-scoped):
 *   zernio_api_key_igtt     IG + TikTok workspace, encrypted via Crypt::encryptString
 *   zernio_api_key_threads  Threads + Reddit workspace, encrypted
 *   zernio_api_key_fbyt     Facebook + YouTube workspace (2026-06-16), encrypted
 *   (all masked ***SET*** in the API)
 *
 * Per-platform account ids (manual entry — operator pastes from the Verify
 * Connection account list):
 *   zernio_instagram_account_id / zernio_tiktok_account_id / zernio_threads_account_id
 *
 * Per-platform publisher selector — which adapter publishes each platform.
 * Defaults to 'zernio' (primary); flip to 'publer' to fall back per platform.
 *   crosspost_publisher_instagram / _tiktok / _threads
 *
 * firstOrCreate (NOT updateOrCreate) so re-running on every deploy never
 * clobbers operator-edited values. Same pattern as PublerSettingsSeeder.
 */
class ZernioSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            ['key' => 'zernio_api_key_igtt',           'value' => null,     'type' => 'text'],
            ['key' => 'zernio_api_key_threads',        'value' => null,     'type' => 'text'],
            ['key' => 'zernio_instagram_account_id',   'value' => null,     'type' => 'text'],
            ['key' => 'zernio_tiktok_account_id',      'value' => null,     'type' => 'text'],
            ['key' => 'zernio_threads_account_id',     'value' => null,     'type' => 'text'],
            ['key' => 'crosspost_publisher_instagram', 'value' => 'zernio', 'type' => 'text'],
            ['key' => 'crosspost_publisher_tiktok',    'value' => 'zernio', 'type' => 'text'],
            ['key' => 'crosspost_publisher_threads',   'value' => 'zernio', 'type' => 'text'],

            // 2026-06-16 — Reddit (4th platform) + Facebook & YouTube (3rd workspace key).
            // Reddit reuses the Threads workspace key (ZernioClient::forPlatform);
            // Facebook + YouTube share a new workspace key zernio_api_key_fbyt.
            ['key' => 'zernio_api_key_fbyt',           'value' => null,             'type' => 'text'],
            ['key' => 'zernio_reddit_account_id',      'value' => null,             'type' => 'text'],
            ['key' => 'zernio_facebook_account_id',    'value' => null,             'type' => 'text'],
            ['key' => 'zernio_youtube_account_id',     'value' => null,             'type' => 'text'],
            // Reddit posts to a subreddit per post — own profile = zero moderation,
            // no flair/karma gate, ~100% success. No per-draft picker.
            ['key' => 'zernio_reddit_subreddit',       'value' => 'u_alisadikinma', 'type' => 'text'],
            // Reddit defaults OFF (never publishes blind — 53.9% Reddit failure rate);
            // operator flips to 'zernio' after the deploy live-probe. FB + YT cut to
            // Zernio on deploy (Publer-FB retired) so they default 'zernio'.
            ['key' => 'crosspost_publisher_reddit',    'value' => 'off',            'type' => 'text'],
            ['key' => 'crosspost_publisher_facebook',  'value' => 'zernio',         'type' => 'text'],
            ['key' => 'crosspost_publisher_youtube',   'value' => 'zernio',         'type' => 'text'],
        ];

        foreach ($settings as $setting) {
            Setting::firstOrCreate(
                ['key' => $setting['key'], 'group' => 'zernio'],
                ['value' => $setting['value'], 'type' => $setting['type']]
            );
        }

        if ($this->command) {
            $this->command->info('✅ Zernio integration settings seeded.');
        }
    }
}
