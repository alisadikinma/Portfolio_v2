<?php

namespace Tests\Feature;

use App\Jobs\PublishRepurposeViaZernio;
use App\Models\RepurposeJob;
use App\Models\Setting;
use App\Models\User;
use App\Services\ZernioPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase I — Zernio publish for video_full (single MP4 → LI/IG/TikTok/Threads).
 *
 * @see docs/plans/2026-06-16-video-full-rebrand.md Phase I
 */
class VideoFullZernioPublishTest extends TestCase
{
    use RefreshDatabase;

    private function job(array $attrs = []): RepurposeJob
    {
        return RepurposeJob::create(array_merge([
            'source_url' => 'https://www.instagram.com/p/DZmqSoRKOQ9/',
            'mode' => RepurposeJob::MODE_VIDEO_FULL,
            'status' => 'uploaded',
            'final_video_url' => 'https://alisadikinma.com/storage/video-full/1/final.mp4',
        ], $attrs));
    }

    public function test_build_video_full_emits_single_video_media_item(): void
    {
        Setting::create(['group' => 'zernio', 'key' => 'zernio_instagram_account_id', 'value' => 'acc_ig', 'type' => 'text']);
        $job = $this->job();

        $payload = app(ZernioPayloadBuilder::class)->buildVideoFull($job, 'instagram', 'Halo dunia');

        $this->assertSame('instagram', $payload['platforms'][0]['platform']);
        $this->assertCount(1, $payload['mediaItems']);
        $this->assertSame('video', $payload['mediaItems'][0]['type']);
        $this->assertStringContainsString('final.mp4', $payload['mediaItems'][0]['url']);
        $this->assertSame('Halo dunia', $payload['content']);
    }

    public function test_build_video_full_throws_without_final_video(): void
    {
        $this->expectException(\RuntimeException::class);
        app(ZernioPayloadBuilder::class)->buildVideoFull($this->job(['final_video_url' => null]), 'instagram');
    }

    public function test_publish_endpoint_503_when_zernio_disabled(): void
    {
        config()->set('social-cross-post.zernio.enabled', false);
        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/admin/video-full/{$this->job()->id}/publish-zernio")->assertStatus(503);
    }

    public function test_publish_endpoint_422_when_no_final_video(): void
    {
        config()->set('social-cross-post.zernio.enabled', true);
        Sanctum::actingAs(User::factory()->create());
        $job = $this->job(['final_video_url' => null]);
        $this->postJson("/api/admin/video-full/{$job->id}/publish-zernio")->assertStatus(422);
    }

    public function test_publish_endpoint_dispatches_per_configured_platform(): void
    {
        Queue::fake();
        config()->set('social-cross-post.zernio.enabled', true);
        Setting::create(['group' => 'zernio', 'key' => 'zernio_instagram_account_id', 'value' => 'acc_ig', 'type' => 'text']);
        Setting::create(['group' => 'zernio', 'key' => 'zernio_threads_account_id', 'value' => 'acc_th', 'type' => 'text']);
        // tiktok intentionally NOT configured → must be skipped
        Sanctum::actingAs(User::factory()->create());
        $job = $this->job();

        $res = $this->postJson("/api/admin/video-full/{$job->id}/publish-zernio", [
            'platforms' => ['instagram', 'threads', 'tiktok'],
        ]);

        $res->assertStatus(202)
            ->assertJsonPath('dispatched', ['instagram', 'threads'])
            ->assertJsonPath('skipped', ['tiktok']);
        Queue::assertPushed(PublishRepurposeViaZernio::class, 2);
    }
}
