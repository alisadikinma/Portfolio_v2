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

        $this->assertSame(8, Setting::where('group', 'zernio')->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(ZernioSettingsSeeder::class);
        // Operator edits a selector — re-seed must NOT clobber it.
        Setting::where('group', 'zernio')
            ->where('key', 'crosspost_publisher_instagram')
            ->update(['value' => 'publer']);

        $this->seed(ZernioSettingsSeeder::class);

        $this->assertSame(8, Setting::where('group', 'zernio')->count());
        $this->assertSame(
            'publer',
            Setting::where('group', 'zernio')->where('key', 'crosspost_publisher_instagram')->value('value'),
            'firstOrCreate must not overwrite operator-edited value'
        );
    }
}
