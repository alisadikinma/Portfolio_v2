<?php

namespace Tests\Unit;

use App\Models\PostizChannel;
use App\Models\PostizPublishJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase A — postiz_publish_jobs + postiz_channels schema + models.
 * See docs/plans/2026-06-13-postiz-local-node-crosspost.md.
 */
class PostizPublishJobModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_factory_persists_with_expected_casts(): void
    {
        $job = PostizPublishJob::factory()->create([
            'platform' => 'instagram',
            'status' => 'ready_to_publish',
        ]);

        $this->assertDatabaseHas('postiz_publish_jobs', [
            'id' => $job->id,
            'platform' => 'instagram',
            'status' => 'ready_to_publish',
        ]);

        // datetime casts
        $fresh = $job->fresh();
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $fresh->slot_due_at);
    }

    public function test_scope_claimable_returns_only_unclaimed_or_lease_expired_ready_rows(): void
    {
        // Ready + never claimed → claimable
        $claimable = PostizPublishJob::factory()->create([
            'status' => 'ready_to_publish',
            'publish_lease_until' => null,
        ]);
        // Ready + active lease → NOT claimable
        $leased = PostizPublishJob::factory()->create([
            'status' => 'ready_to_publish',
            'publish_lease_until' => now()->addMinutes(10),
        ]);
        // Ready + expired lease → claimable
        $expired = PostizPublishJob::factory()->create([
            'status' => 'ready_to_publish',
            'publish_lease_until' => now()->subMinute(),
        ]);
        // Already claimed (different status) → NOT claimable
        $claimed = PostizPublishJob::factory()->create([
            'status' => 'claimed',
            'publish_lease_until' => null,
        ]);

        $ids = PostizPublishJob::query()->claimable()->pluck('id')->all();

        $this->assertContains($claimable->id, $ids);
        $this->assertContains($expired->id, $ids);
        $this->assertNotContains($leased->id, $ids);
        $this->assertNotContains($claimed->id, $ids);
    }

    public function test_unique_platform_sibling_constraint_enforced(): void
    {
        PostizPublishJob::factory()->create([
            'platform' => 'instagram',
            'sibling_post_id' => 42,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        PostizPublishJob::factory()->create([
            'platform' => 'instagram',
            'sibling_post_id' => 42,
        ]);
    }

    public function test_postiz_channel_factory_and_unique_platform_handle(): void
    {
        PostizChannel::factory()->create([
            'platform' => 'instagram',
            'handle' => 'alisadikinma',
        ]);

        $this->assertDatabaseHas('postiz_channels', [
            'platform' => 'instagram',
            'handle' => 'alisadikinma',
            'enabled' => true,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        PostizChannel::factory()->create([
            'platform' => 'instagram',
            'handle' => 'alisadikinma',
        ]);
    }
}
