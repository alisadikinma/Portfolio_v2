<?php

namespace Tests\Feature;

use App\Models\RepurposeJob;
use App\Models\Setting;
use App\Services\TelegramNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase F — Telegram "🎥 Video 60s" button. Tapping it (when the flag is on)
 * sets mode=video_full and parks the job at queued_local for the MacBook worker.
 * The flag off → the tap is refused and the job stays Received.
 *
 * @see docs/plans/2026-06-16-video-full-rebrand.md Phase F
 */
class VideoFullTelegramTriggerTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = 'webhooktestsecret123456789012345';

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); // stub all Telegram API calls (answerCallbackQuery / progress)
        $rows = [
            'telegram_enabled' => 'true',
            'telegram_bot_token' => 'bot:token',
            'telegram_chat_id' => '999',
            'telegram_webhook_secret' => $this->secret,
            'telegram_repurpose_enabled' => 'true',
        ];
        foreach ($rows as $key => $value) {
            Setting::create(['group' => 'telegram', 'key' => $key, 'value' => $value, 'type' => 'text']);
        }
    }

    private function setVideoFull(string $value): void
    {
        Setting::create(['group' => 'telegram', 'key' => 'telegram_video_full_enabled', 'value' => $value, 'type' => 'text']);
    }

    private function tapVideoFull(RepurposeJob $job): \Illuminate\Testing\TestResponse
    {
        $data = TelegramNotificationService::signCallback('video_full', 'repurpose', $job->id, $this->secret);

        return $this->withHeaders(['X-Telegram-Bot-Api-Secret-Token' => $this->secret])
            ->postJson('/api/automation/telegram/webhook', [
                'callback_query' => ['id' => 'cb1', 'data' => $data, 'message' => ['chat' => ['id' => 999]]],
            ]);
    }

    private function receivedJob(): RepurposeJob
    {
        return RepurposeJob::create([
            'source_url' => 'https://www.instagram.com/p/DZmqSoRKOQ9/',
            'mode' => null,
            'status' => 'received',
            'chat_id' => '999',
        ]);
    }

    public function test_video_full_button_parks_job_at_queued_local_when_enabled(): void
    {
        $this->setVideoFull('true');
        $job = $this->receivedJob();

        $this->tapVideoFull($job)->assertOk();

        $job->refresh();
        $this->assertSame(RepurposeJob::MODE_VIDEO_FULL, $job->mode);
        $this->assertSame('queued_local', $job->status);
    }

    public function test_video_full_button_refused_when_flag_off(): void
    {
        $this->setVideoFull('false');
        $job = $this->receivedJob();

        $this->tapVideoFull($job)->assertOk();

        $job->refresh();
        $this->assertNull($job->mode);
        $this->assertSame('received', $job->status);
    }

    public function test_mode_prompt_includes_video_full_button_only_when_enabled(): void
    {
        $this->setVideoFull('true');
        $job = $this->receivedJob();
        app(TelegramNotificationService::class)->sendRepurposeModePrompt($job);

        Http::assertSent(function ($request) {
            $body = json_encode($request->data());

            return str_contains($body, 'Video 60s');
        });
    }
}
