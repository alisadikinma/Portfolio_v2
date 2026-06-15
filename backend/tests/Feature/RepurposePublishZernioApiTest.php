<?php

namespace Tests\Feature;

use App\Jobs\PublishRepurposeViaZernio;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase D — POST /admin/repurpose/{id}/publish-zernio (Approve + Schedule).
 */
class RepurposePublishZernioApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        config(['social-cross-post.zernio.enabled' => true]);
        Setting::create(['group' => 'zernio', 'key' => 'zernio_instagram_account_id', 'value' => 'ig_acc']);
        Setting::create(['group' => 'zernio', 'key' => 'zernio_threads_account_id', 'value' => 'th_acc']);
    }

    private function videoJob(bool $composited = true): RepurposeJob
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'drafted', 'extracted' => ['caption' => 'cap']]);
        foreach ([0, 1, 2] as $i) {
            RepurposeVideoSlide::create([
                'repurpose_job_id' => $job->id, 'slide_index' => $i, 'role' => $i === 0 ? 'hook' : 'tool',
                'composited_status' => $composited ? 'done' : 'pending',
                'composited_path' => $composited ? "https://alisadikinma.com/storage/repurpose/{$job->id}/composited/s{$i}.mp4" : null,
            ]);
        }

        return $job->fresh();
    }

    private function actor(): User
    {
        return User::factory()->create();
    }

    public function test_requires_auth(): void
    {
        $this->postJson('/api/admin/repurpose/1/publish-zernio')->assertUnauthorized();
    }

    public function test_publishes_now_dispatches_both_platforms(): void
    {
        $job = $this->videoJob();

        $this->actingAs($this->actor(), 'sanctum')
            ->postJson("/api/admin/repurpose/{$job->id}/publish-zernio")
            ->assertStatus(202)
            ->assertJsonPath('data.dispatched', ['instagram', 'threads']);

        Queue::assertPushed(PublishRepurposeViaZernio::class, fn ($j) => $j->platform === 'instagram' && $j->scheduledForIso === null);
        Queue::assertPushed(PublishRepurposeViaZernio::class, fn ($j) => $j->platform === 'threads');
    }

    public function test_schedule_for_later_passes_future_iso(): void
    {
        $job = $this->videoJob();
        $when = now()->addDay()->toIso8601String();

        $this->actingAs($this->actor(), 'sanctum')
            ->postJson("/api/admin/repurpose/{$job->id}/publish-zernio", ['platforms' => ['instagram'], 'scheduled_at' => $when])
            ->assertStatus(202);

        Queue::assertPushed(PublishRepurposeViaZernio::class, fn ($j) => $j->platform === 'instagram' && $j->scheduledForIso !== null);
    }

    public function test_past_schedule_is_rejected(): void
    {
        $job = $this->videoJob();

        $this->actingAs($this->actor(), 'sanctum')
            ->postJson("/api/admin/repurpose/{$job->id}/publish-zernio", ['scheduled_at' => now()->subHour()->toIso8601String()])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'SCHEDULE_IN_PAST');

        Queue::assertNothingPushed();
    }

    public function test_not_composited_is_rejected(): void
    {
        $job = $this->videoJob(composited: false);

        $this->actingAs($this->actor(), 'sanctum')
            ->postJson("/api/admin/repurpose/{$job->id}/publish-zernio")
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'NOT_COMPOSITED');

        Queue::assertNothingPushed();
    }

    public function test_disabled_master_switch_returns_503(): void
    {
        config(['social-cross-post.zernio.enabled' => false]);
        $job = $this->videoJob();

        $this->actingAs($this->actor(), 'sanctum')
            ->postJson("/api/admin/repurpose/{$job->id}/publish-zernio")
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'ZERNIO_DISABLED');
    }

    public function test_skips_platform_without_account(): void
    {
        Setting::where('group', 'zernio')->where('key', 'zernio_threads_account_id')->delete();
        $job = $this->videoJob();

        $this->actingAs($this->actor(), 'sanctum')
            ->postJson("/api/admin/repurpose/{$job->id}/publish-zernio")
            ->assertStatus(202)
            ->assertJsonPath('data.dispatched', ['instagram'])
            ->assertJsonPath('data.skipped', ['threads']);

        Queue::assertPushed(PublishRepurposeViaZernio::class, 1);
    }
}
