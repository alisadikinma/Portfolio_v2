<?php

namespace Tests\Feature;

use App\Models\Setting;
use Database\Seeders\ZernioSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase C — Zernio settings group (2 workspace API keys + 3 account ids +
 * 3 per-platform publisher selectors).
 */
class ZernioSettingsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_eight_keys_with_selector_defaults(): void
    {
        $this->seed(ZernioSettingsSeeder::class);

        $expectedNull = [
            'zernio_api_key_igtt',
            'zernio_api_key_threads',
            'zernio_instagram_account_id',
            'zernio_tiktok_account_id',
            'zernio_threads_account_id',
        ];
        foreach ($expectedNull as $key) {
            $row = Setting::where('group', 'zernio')->where('key', $key)->first();
            $this->assertNotNull($row, "missing setting {$key}");
            $this->assertNull($row->value, "{$key} should default null");
        }

        foreach (['instagram', 'tiktok', 'threads'] as $platform) {
            $row = Setting::where('group', 'zernio')
                ->where('key', "crosspost_publisher_{$platform}")
                ->first();
            $this->assertNotNull($row, "missing selector for {$platform}");
            $this->assertSame('zernio', $row->value, "{$platform} should default to zernio");
        }

        $this->assertSame(16, Setting::where('group', 'zernio')->count());
    }

    public function test_seeds_reddit_facebook_youtube_keys(): void
    {
        $this->seed(ZernioSettingsSeeder::class);

        // 3rd workspace key (FB+YouTube) + 3 account ids default null.
        foreach ([
            'zernio_api_key_fbyt',
            'zernio_reddit_account_id',
            'zernio_facebook_account_id',
            'zernio_youtube_account_id',
        ] as $key) {
            $row = Setting::where('group', 'zernio')->where('key', $key)->first();
            $this->assertNotNull($row, "missing setting {$key}");
            $this->assertNull($row->value, "{$key} should default null");
        }

        // Reddit subreddit target — own profile (safest automated target).
        $this->assertSame(
            'u_alisadikinma',
            Setting::where('group', 'zernio')->where('key', 'zernio_reddit_subreddit')->value('value')
        );

        // Reddit defaults OFF (never publishes blind); FB + YT cut to Zernio on deploy.
        $this->assertSame(
            'off',
            Setting::where('group', 'zernio')->where('key', 'crosspost_publisher_reddit')->value('value')
        );
        foreach (['facebook', 'youtube'] as $platform) {
            $this->assertSame(
                'zernio',
                Setting::where('group', 'zernio')->where('key', "crosspost_publisher_{$platform}")->value('value'),
                "{$platform} should default to zernio"
            );
        }
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(ZernioSettingsSeeder::class);
        // Operator edits a selector — re-seed must NOT clobber it.
        Setting::where('group', 'zernio')
            ->where('key', 'crosspost_publisher_instagram')
            ->update(['value' => 'publer']);

        $this->seed(ZernioSettingsSeeder::class);

        $this->assertSame(16, Setting::where('group', 'zernio')->count());
        $this->assertSame(
            'publer',
            Setting::where('group', 'zernio')->where('key', 'crosspost_publisher_instagram')->value('value'),
            'firstOrCreate must not overwrite operator-edited value'
        );
    }
}
