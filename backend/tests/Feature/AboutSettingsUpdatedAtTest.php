<?php

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * The public about page shows "Diperbarui {date}" and ProfilePage.dateModified.
 * That date must come from the newest about-settings row, never from the render clock.
 */
class AboutSettingsUpdatedAtTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_newest_about_row_updated_at(): void
    {
        $old = Setting::create(['group' => 'about', 'key' => 'name', 'value' => 'Ali', 'type' => 'string']);
        $new = Setting::create(['group' => 'about', 'key' => 'bio', 'value' => '<p>x</p>', 'type' => 'string']);
        Setting::where('id', $old->id)->update(['updated_at' => Carbon::parse('2026-01-02 03:04:05')]);
        Setting::where('id', $new->id)->update(['updated_at' => Carbon::parse('2026-10-20 08:00:00')]);
        // A newer row in another group must not count.
        $other = Setting::create(['group' => 'site', 'key' => 'site_name', 'value' => 'x', 'type' => 'string']);
        Setting::where('id', $other->id)->update(['updated_at' => Carbon::parse('2026-11-01 00:00:00')]);

        $res = $this->getJson('/api/settings/about')->assertOk();

        $this->assertSame(
            Carbon::parse('2026-10-20 08:00:00')->toIso8601String(),
            $res->json('data.updated_at'),
        );
    }

    public function test_updated_at_is_null_when_no_about_rows(): void
    {
        $this->getJson('/api/settings/about')->assertOk()->assertJsonPath('data.updated_at', null);
    }
}
