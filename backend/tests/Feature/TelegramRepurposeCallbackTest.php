<?php

namespace Tests\Feature;

use App\Jobs\CaptureInstagramPost;
use App\Jobs\CaptureVideoCarousel;
use App\Jobs\GenerateRebrandAssets;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Models\Setting;
use App\Services\TelegramNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase A — inbound Telegram callback branch: tapping a mode button sets
 * RepurposeJob.mode, advances FSM received → capturing, and dispatches
 * CaptureInstagramPost. HMAC-verified + idempotent.
 */
class TelegramRepurposeCallbackTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'cb-secret';

    protected function setUp(): void
    {
        parent::setUp();

        Setting::create(['group' => 'telegram', 'key' => 'telegram_webhook_secret', 'value' => $this->secret, 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_enabled', 'value' => 'true', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_chat_id', 'value' => '99', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_bot_token', 'value' => 'TOKEN', 'type' => 'text']);

        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    private function tap(string $callbackData)
    {
        return $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => $this->secret])
            ->postJson('/api/automation/telegram/webhook', [
                'callback_query' => ['id' => 'cbq1', 'data' => $callbackData],
            ]);
    }

    public function test_blog_button_sets_mode_advances_fsm_and_dispatches_capture(): void
    {
        Bus::fake();
        $job = RepurposeJob::factory()->create(['status' => 'received', 'mode' => null]);
        $data = TelegramNotificationService::signCallback('blog', 'repurpose', $job->id, $this->secret);

        $this->tap($data)->assertOk();

        $job->refresh();
        $this->assertSame('blog', $job->mode);
        $this->assertSame('capturing', $job->status);
        Bus::assertDispatched(CaptureInstagramPost::class, fn ($j) => $j->repurposeJobId === $job->id);
    }

    public function test_carousel_button_sets_mode(): void
    {
        Bus::fake();
        $job = RepurposeJob::factory()->create(['status' => 'received', 'mode' => null]);
        $data = TelegramNotificationService::signCallback('carousel', 'repurpose', $job->id, $this->secret);

        $this->tap($data)->assertOk();

        $this->assertSame('carousel', $job->refresh()->mode);
    }

    public function test_video_rebrand_button_dispatches_video_capture_not_image_capture(): void
    {
        Bus::fake();
        $job = RepurposeJob::factory()->create(['status' => 'received', 'mode' => null]);
        $data = TelegramNotificationService::signCallback('video_rebrand', 'repurpose', $job->id, $this->secret);

        $this->tap($data)->assertOk();

        $job->refresh();
        $this->assertSame('video_rebrand', $job->mode);
        $this->assertSame('capturing', $job->status);
        Bus::assertDispatched(CaptureVideoCarousel::class, fn ($j) => $j->repurposeJobId === $job->id);
        Bus::assertNotDispatched(CaptureInstagramPost::class);
    }

    public function test_double_tap_is_idempotent(): void
    {
        Bus::fake();
        // Already started (mode set, past received) — a late second tap is a no-op.
        $job = RepurposeJob::factory()->create(['status' => 'capturing', 'mode' => 'blog']);
        $data = TelegramNotificationService::signCallback('carousel', 'repurpose', $job->id, $this->secret);

        $this->tap($data)->assertOk();

        $this->assertSame('blog', $job->refresh()->mode);
        Bus::assertNotDispatched(CaptureInstagramPost::class);
    }

    public function test_retry_button_re_dispatches_failed_video_rebrand_job(): void
    {
        // A5: the exhaustion alert's inline Retry button re-runs a Failed
        // video_rebrand asset job through the shared RepurposeRetryService.
        Bus::fake();
        $job = RepurposeJob::factory()->create(['status' => 'failed', 'mode' => 'video_rebrand', 'asset_retry_count' => 3]);
        $job->update(['pipeline_state_log' => [['from' => 'generating_assets', 'to' => 'failed', 'reason' => 'video_assets_failed']]]);
        RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook',
            'keyframe_status' => 'done', 'keyframe_url' => 'https://cdn/kf.jpg', 'veo_status' => 'failed',
        ]);

        $data = TelegramNotificationService::signCallback('retry', 'repurpose', $job->id, $this->secret);
        $this->tap($data)->assertOk();

        $job->refresh();
        $this->assertSame('extracted', $job->status);   // resume guard for GenerateRebrandAssets
        $this->assertSame(0, $job->asset_retry_count);   // budget reset
        Bus::assertDispatched(GenerateRebrandAssets::class, fn ($j) => $j->repurposeJobId === $job->id);
    }

    public function test_retry_button_noop_when_job_not_failed(): void
    {
        Bus::fake();
        $job = RepurposeJob::factory()->create(['status' => 'generating_assets', 'mode' => 'video_rebrand']);
        $data = TelegramNotificationService::signCallback('retry', 'repurpose', $job->id, $this->secret);

        $this->tap($data)->assertOk();

        $this->assertSame('generating_assets', $job->refresh()->status);
        Bus::assertNotDispatched(GenerateRebrandAssets::class);
    }

    public function test_tampered_hmac_is_noop(): void
    {
        Bus::fake();
        $job = RepurposeJob::factory()->create(['status' => 'received', 'mode' => null]);

        $this->tap("blog:repurpose:{$job->id}:deadbeef0000")->assertOk();

        $this->assertNull($job->refresh()->mode);
        Bus::assertNotDispatched(CaptureInstagramPost::class);
    }
}
