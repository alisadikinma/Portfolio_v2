<?php

namespace Tests\Feature;

use App\Models\PostizChannel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase E — POST /automation/postiz/channels/sync mapping upsert.
 * See docs/plans/2026-06-13-postiz-local-node-crosspost.md.
 */
class PostizChannelsSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_requires_auth(): void
    {
        $this->postJson('/api/automation/postiz/channels/sync', ['channels' => []])->assertUnauthorized();
    }

    public function test_sync_upserts_new_and_disables_missing(): void
    {
        Sanctum::actingAs(User::factory()->create());

        // Pre-existing channel that will be ABSENT from the payload → disabled.
        PostizChannel::factory()->create([
            'platform' => 'tiktok',
            'handle' => 'oldhandle',
            'postiz_integration_id' => '9',
            'enabled' => true,
        ]);

        $this->postJson('/api/automation/postiz/channels/sync', [
            'channels' => [
                ['platform' => 'instagram', 'handle' => 'alisadikinma', 'integration_id' => '77', 'enabled' => true],
            ],
        ])->assertOk();

        // New mapping inserted + enabled.
        $this->assertDatabaseHas('postiz_channels', [
            'platform' => 'instagram',
            'handle' => 'alisadikinma',
            'postiz_integration_id' => '77',
            'enabled' => true,
        ]);

        // Missing one disabled (not deleted).
        $this->assertDatabaseHas('postiz_channels', [
            'platform' => 'tiktok',
            'handle' => 'oldhandle',
            'enabled' => false,
        ]);
    }

    public function test_sync_updates_existing_integration_id(): void
    {
        Sanctum::actingAs(User::factory()->create());
        PostizChannel::factory()->create(['platform' => 'instagram', 'handle' => 'ali', 'postiz_integration_id' => '1']);

        $this->postJson('/api/automation/postiz/channels/sync', [
            'channels' => [['platform' => 'instagram', 'handle' => 'ali', 'integration_id' => '99', 'enabled' => true]],
        ])->assertOk();

        $this->assertDatabaseHas('postiz_channels', ['platform' => 'instagram', 'handle' => 'ali', 'postiz_integration_id' => '99']);
        $this->assertSame(1, PostizChannel::where('platform', 'instagram')->where('handle', 'ali')->count());
    }
}
