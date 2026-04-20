<?php

namespace Tests\Feature;

use App\Models\Setting;
use Database\Seeders\TelegramSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TelegramSettingsSeederTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function seeder_creates_six_telegram_settings(): void
    {
        $this->seed(TelegramSettingsSeeder::class);

        $this->assertSame(6, Setting::where('group', 'telegram')->count());

        $keys = Setting::where('group', 'telegram')->pluck('key')->sort()->values()->toArray();

        $this->assertSame([
            'telegram_bot_token',
            'telegram_chat_id',
            'telegram_enabled',
            'telegram_notify_generation_failed',
            'telegram_notify_manifest_needed',
            'telegram_notify_publish_success',
        ], $keys);
    }

    /** @test */
    public function seeder_uses_expected_defaults(): void
    {
        $this->seed(TelegramSettingsSeeder::class);

        $this->assertNull(Setting::where('group', 'telegram')->where('key', 'telegram_bot_token')->value('value'));
        $this->assertNull(Setting::where('group', 'telegram')->where('key', 'telegram_chat_id')->value('value'));
        $this->assertSame('false', Setting::where('group', 'telegram')->where('key', 'telegram_enabled')->value('value'));
        $this->assertSame('true', Setting::where('group', 'telegram')->where('key', 'telegram_notify_manifest_needed')->value('value'));
        $this->assertSame('true', Setting::where('group', 'telegram')->where('key', 'telegram_notify_generation_failed')->value('value'));
        $this->assertSame('false', Setting::where('group', 'telegram')->where('key', 'telegram_notify_publish_success')->value('value'));
    }

    /** @test */
    public function seeder_is_idempotent(): void
    {
        $this->seed(TelegramSettingsSeeder::class);
        $this->seed(TelegramSettingsSeeder::class);

        $this->assertSame(6, Setting::where('group', 'telegram')->count());
    }

    /** @test */
    public function seeder_preserves_user_saved_values_on_rerun(): void
    {
        // Regression guard: same pattern as CreatorBrandSettingsSeeder — firstOrCreate
        // must NOT clobber values the admin has saved through the UI.
        $this->seed(TelegramSettingsSeeder::class);

        Setting::where('group', 'telegram')->where('key', 'telegram_bot_token')->update(['value' => '123456:ABC-DEF']);
        Setting::where('group', 'telegram')->where('key', 'telegram_chat_id')->update(['value' => '987654321']);
        Setting::where('group', 'telegram')->where('key', 'telegram_enabled')->update(['value' => 'true']);
        Setting::where('group', 'telegram')->where('key', 'telegram_notify_publish_success')->update(['value' => 'true']);

        $this->seed(TelegramSettingsSeeder::class);

        $this->assertSame('123456:ABC-DEF', Setting::where('group', 'telegram')->where('key', 'telegram_bot_token')->value('value'));
        $this->assertSame('987654321', Setting::where('group', 'telegram')->where('key', 'telegram_chat_id')->value('value'));
        $this->assertSame('true', Setting::where('group', 'telegram')->where('key', 'telegram_enabled')->value('value'));
        $this->assertSame('true', Setting::where('group', 'telegram')->where('key', 'telegram_notify_publish_success')->value('value'));
    }
}
