<?php

namespace Tests\Feature;

use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Models\Setting;
use App\Services\TelegramNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Part A6 — every repurpose Telegram notification carries a "job #id · topic"
 * header so the operator knows WHICH job/topic is being processed (the old
 * progress bubble was a bare "🎬 Bikin klip…" with no identifier).
 */
class RepurposeNotifHeaderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['group' => 'telegram', 'key' => 'telegram_enabled', 'value' => 'true', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_chat_id', 'value' => '99', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_bot_token', 'value' => 'TOKEN', 'type' => 'text']);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    private function sentText(): string
    {
        $text = '';
        Http::recorded(function ($request) use (&$text) {
            if (str_contains($request->url(), '/sendMessage')) {
                $text = $request->data()['text'] ?? '';
            }
        });

        return $text;
    }

    public function test_video_rebrand_display_topic_is_tool_titles(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 1, 'role' => 'tool', 'header_title' => 'Cursor']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 2, 'role' => 'tool', 'header_title' => 'Stitch']);

        $this->assertSame('Cursor, Stitch', $job->displayTopic());
    }

    public function test_display_topic_falls_back_to_rewritten_title(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'carousel', 'rewritten' => ['title' => 'My Carousel Title']]);
        $this->assertSame('My Carousel Title', $job->displayTopic());
    }

    public function test_display_topic_falls_back_to_source_host(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'carousel', 'rewritten' => null, 'extracted' => null, 'source_url' => 'https://instagram.com/p/XYZ/']);
        $this->assertSame('instagram.com', $job->displayTopic());
    }

    public function test_progress_message_includes_job_id_and_topic(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 1, 'role' => 'tool', 'header_title' => 'Cursor']);

        app(TelegramNotificationService::class)->sendRepurposeProgress($job, '🎬 Bikin klip hook + CTA…');

        $text = $this->sentText();
        $this->assertStringContainsString('job #'.$job->id, $text);
        $this->assertStringContainsString('Cursor', $text);
        $this->assertStringContainsString('Bikin klip', $text);
    }

    public function test_assets_failed_message_includes_job_id_and_topic(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 1, 'role' => 'tool', 'header_title' => 'Veo']);

        app(TelegramNotificationService::class)->sendRepurposeAssetsFailed($job, 'PUBLIC_ERROR_AUDIO_FILTERED');

        $text = $this->sentText();
        $this->assertStringContainsString('job #'.$job->id, $text);
        $this->assertStringContainsString('Veo', $text);
    }
}
