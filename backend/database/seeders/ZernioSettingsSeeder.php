<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Seed operator-facing Zernio integration settings (2026-06-15).
 *
 * 8 keys live in the `settings` table (group=zernio). Operator manages all via
 * the "Zernio Publishing" card on /admin/about.
 *
 * Two workspace API keys (Zernio keys are workspace-scoped):
 *   zernio_api_key_igtt     IG + TikTok workspace, encrypted via Crypt::encryptString
 *   zernio_api_key_threads  Threads workspace, encrypted; both masked ***SET*** in API
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
