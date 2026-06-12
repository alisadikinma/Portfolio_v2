<?php

namespace Tests\Feature;

use App\Jobs\CaptureInstagramPost;
use App\Jobs\CaptureVideoCarousel;
use App\Models\RepurposeJob;
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

    public function test_tampered_hmac_is_noop(): void
    {
        Bus::fake();
        $job = RepurposeJob::factory()->create(['status' => 'received', 'mode' => null]);

        $this->tap("blog:repurpose:{$job->id}:deadbeef0000")->assertOk();

        $this->assertNull($job->refresh()->mode);
        Bus::assertNotDispatched(CaptureInstagramPost::class);
    }
}
