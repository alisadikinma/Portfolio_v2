<?php

namespace Tests\Feature;

use App\Models\Setting;
use Database\Seeders\PostizSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase G — postiz settings group seeder (default OFF).
 * See docs/plans/2026-06-13-postiz-local-node-crosspost.md.
 */
class PostizSettingsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_expected_keys_default_off(): void
    {
        (new PostizSettingsSeeder())->run();

        $this->assertSame('false', Setting::where('group', 'postiz')->where('key', 'postiz_enabled')->value('value'));
        $this->assertSame('10', Setting::where('group', 'postiz')->where('key', 'postiz_lease_minutes')->value('value'));
        $this->assertSame('6', Setting::where('group', 'postiz')->where('key', 'postiz_fallback_deadline_minutes')->value('value'));
        $this->assertSame('20', Setting::where('group', 'postiz')->where('key', 'postiz_worker_alert_minutes')->value('value'));
        $this->assertTrue(Setting::where('group', 'postiz')->where('key', 'postiz_api_base_url')->exists());

        $this->assertSame('false', Setting::where('group', 'postiz')->where('key', 'postiz_medium_enabled')->value('value'));
        $this->assertSame(6, Setting::where('group', 'postiz')->count());
    }

    public function test_idempotent_does_not_clobber_edited_values(): void
    {
        (new PostizSettingsSeeder())->run();
        Setting::where('group', 'postiz')->where('key', 'postiz_enabled')->update(['value' => 'true']);

        (new PostizSettingsSeeder())->run();

        $this->assertSame('true', Setting::where('group', 'postiz')->where('key', 'postiz_enabled')->value('value'));
        $this->assertSame('false', Setting::where('group', 'postiz')->where('key', 'postiz_medium_enabled')->value('value'));
        $this->assertSame(6, Setting::where('group', 'postiz')->count());
    }
}
