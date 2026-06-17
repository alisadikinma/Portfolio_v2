<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase H — Zernio settings API (auth:sanctum): masked keys, encrypted writes
 * with preserve-on-empty, selector validation, and live verify-connection.
 */
class ZernioSettingsApiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    public function test_get_masks_api_keys_and_exposes_configured_flags(): void
    {
        Setting::create(['group' => 'zernio', 'key' => 'zernio_api_key_igtt', 'value' => Crypt::encryptString('sk_secret'), 'type' => 'text']);
        Setting::create(['group' => 'zernio', 'key' => 'crosspost_publisher_instagram', 'value' => 'zernio', 'type' => 'text']);

        $res = $this->actingAs($this->admin(), 'sanctum')->getJson('/api/admin/settings/zernio');

        $res->assertOk();
        $res->assertJsonPath('data.zernio_api_key_igtt', '***SET***');
        $res->assertJsonPath('data.zernio_api_key_igtt_configured', true);
        $res->assertJsonPath('data.zernio_api_key_threads_configured', false);
        $res->assertJsonPath('data.crosspost_publisher_instagram', 'zernio');
        // Never leak the encrypted blob or plaintext.
        $this->assertStringNotContainsString('sk_secret', $res->getContent());
    }

    public function test_put_encrypts_key_and_validates_selector(): void
    {
        $res = $this->actingAs($this->admin(), 'sanctum')->putJson('/api/admin/settings/zernio', [
            'zernio_api_key_igtt' => 'sk_new_key',
            'zernio_instagram_account_id' => 'ig_acc_1',
            'crosspost_publisher_instagram' => 'zernio',
        ]);

        $res->assertOk();
        $stored = Setting::where('group', 'zernio')->where('key', 'zernio_api_key_igtt')->value('value');
        $this->assertNotSame('sk_new_key', $stored, 'key must be encrypted at rest');
        $this->assertSame('sk_new_key', Crypt::decryptString($stored));
        $this->assertSame('ig_acc_1', Setting::where('group', 'zernio')->where('key', 'zernio_instagram_account_id')->value('value'));
    }

    public function test_put_rejects_invalid_selector(): void
    {
        $res = $this->actingAs($this->admin(), 'sanctum')->putJson('/api/admin/settings/zernio', [
            'crosspost_publisher_instagram' => 'mastodon',
        ]);
        $res->assertStatus(422);
    }

    public function test_put_preserves_key_on_empty_submit(): void
    {
        Setting::create(['group' => 'zernio', 'key' => 'zernio_api_key_threads', 'value' => Crypt::encryptString('sk_keep'), 'type' => 'text']);

        $this->actingAs($this->admin(), 'sanctum')->putJson('/api/admin/settings/zernio', [
            'zernio_api_key_threads' => '***SET***',
            'zernio_threads_account_id' => 'th_acc',
        ])->assertOk();

        $stored = Setting::where('group', 'zernio')->where('key', 'zernio_api_key_threads')->value('value');
        $this->assertSame('sk_keep', Crypt::decryptString($stored), 'masked submit must not clobber stored key');
    }

    public function test_verify_returns_accounts_for_workspace(): void
    {
        Setting::create(['group' => 'zernio', 'key' => 'zernio_api_key_igtt', 'value' => Crypt::encryptString('sk_igtt'), 'type' => 'text']);
        Http::fake(['zernio.com/api/v1/accounts' => Http::response([
            'accounts' => [['_id' => 'acc_1', 'platform' => 'instagram', 'username' => '@me']],
        ], 200)]);

        $res = $this->actingAs($this->admin(), 'sanctum')->postJson('/api/admin/settings/zernio/verify', [
            'workspace' => 'igtt',
        ]);

        $res->assertOk();
        $res->assertJsonPath('success', true);
        $res->assertJsonPath('data.accounts.0._id', 'acc_1');
    }

    public function test_get_exposes_fbyt_key_and_new_platform_keys(): void
    {
        Setting::create(['group' => 'zernio', 'key' => 'zernio_api_key_fbyt', 'value' => Crypt::encryptString('sk_fbyt'), 'type' => 'text']);

        $res = $this->actingAs($this->admin(), 'sanctum')->getJson('/api/admin/settings/zernio');

        $res->assertOk();
        $res->assertJsonPath('data.zernio_api_key_fbyt', '***SET***');
        $res->assertJsonPath('data.zernio_api_key_fbyt_configured', true);
        // New platform selectors with their seeded defaults.
        $res->assertJsonPath('data.crosspost_publisher_reddit', 'off');
        $res->assertJsonPath('data.crosspost_publisher_facebook', 'zernio');
        $res->assertJsonPath('data.crosspost_publisher_youtube', 'zernio');
        $this->assertStringNotContainsString('sk_fbyt', $res->getContent());
    }

    public function test_put_persists_fbyt_key_and_reddit_off_selector(): void
    {
        $res = $this->actingAs($this->admin(), 'sanctum')->putJson('/api/admin/settings/zernio', [
            'zernio_api_key_fbyt' => 'sk_fbyt_new',
            'zernio_facebook_account_id' => 'fb_acc',
            'zernio_youtube_account_id' => 'yt_acc',
            'zernio_reddit_account_id' => 'rd_acc',
            'crosspost_publisher_reddit' => 'off',
        ]);

        $res->assertOk();
        $stored = Setting::where('group', 'zernio')->where('key', 'zernio_api_key_fbyt')->value('value');
        $this->assertSame('sk_fbyt_new', Crypt::decryptString($stored), 'fbyt key must be encrypted at rest');
        $this->assertSame('fb_acc', Setting::where('group', 'zernio')->where('key', 'zernio_facebook_account_id')->value('value'));
        $this->assertSame('off', Setting::where('group', 'zernio')->where('key', 'crosspost_publisher_reddit')->value('value'));
    }

    public function test_verify_fbyt_workspace_lists_accounts(): void
    {
        Setting::create(['group' => 'zernio', 'key' => 'zernio_api_key_fbyt', 'value' => Crypt::encryptString('sk_fbyt'), 'type' => 'text']);
        Http::fake(['zernio.com/api/v1/accounts' => Http::response([
            'accounts' => [['_id' => 'acc_fb', 'platform' => 'facebook'], ['_id' => 'acc_yt', 'platform' => 'youtube']],
        ], 200)]);

        $res = $this->actingAs($this->admin(), 'sanctum')->postJson('/api/admin/settings/zernio/verify', [
            'workspace' => 'fbyt',
        ]);

        $res->assertOk();
        $res->assertJsonPath('success', true);
        $res->assertJsonPath('data.accounts.0._id', 'acc_fb');
        // Must use the fbyt key, not the igtt/threads key.
        Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer sk_fbyt'));
    }

    public function test_endpoints_require_auth(): void
    {
        $this->getJson('/api/admin/settings/zernio')->assertUnauthorized();
    }
}
