<?php

namespace Tests\Feature;

use App\Enums\LinkedInPostStatus;
use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\ScheduledCommand;
use App\Models\Setting;
use Carbon\Carbon;
use Database\Seeders\ScheduledCommandSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase E — docs/plans/2026-06-12-social-studio-status-sync-telegram-scheduling.md
 *
 * linkedin:prompt-schedule — one Telegram prompt per genuinely-ready
 * unscheduled draft, idempotent + serialized + toggle-gated.
 */
class PromptScheduleCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $chatId = '12345';

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-18 00:00:00', 'Asia/Jakarta')); // Tuesday, no holiday

        $this->setSetting('linkedin', 'linkedin_telegram_schedule_enabled', 'true');
        $this->setSetting('telegram', 'telegram_enabled', 'true');
        $this->setSetting('telegram', 'telegram_bot_token', 'TESTTOKEN');
        $this->setSetting('telegram', 'telegram_chat_id', $this->chatId);
        $this->setSetting('telegram', 'telegram_webhook_secret', 'sekret');

        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200)]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function setSetting(string $group, string $key, ?string $value): void
    {
        Setting::updateOrCreate(['group' => $group, 'key' => $key], ['value' => $value, 'type' => 'text']);
    }

    private function makeReadyCarousel(): LinkedInPost
    {
        $category = Category::create(['name' => 'T', 'slug' => 't-' . uniqid()]);
        $post = Post::create([
            'category_id' => $category->id,
            'title' => 'Ready ' . uniqid(),
            'slug' => 'ready-' . uniqid(),
            'content' => 'x',
            'published' => true,
            'published_at' => now(),
        ]);

        return LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'status' => LinkedInPostStatus::ManualReview->value,
            'scheduled_at' => null,
            'schedule_prompt_sent_at' => null,
            'carousel_slides' => [
                ['slide_number' => 1, 'image_status' => 'done', 'image_url' => 'https://x/1.png'],
                ['slide_number' => 2, 'image_status' => 'done', 'image_url' => 'https://x/2.png'],
            ],
        ]);
    }

    public function test_prompts_ready_draft_stamps_flag_and_caches_state(): void
    {
        $draft = $this->makeReadyCarousel();

        $this->artisan('linkedin:prompt-schedule')->assertExitCode(0);

        $draft->refresh();
        $this->assertNotNull($draft->schedule_prompt_sent_at, 'one-shot flag stamped');

        $state = Cache::get("telegram_schedule_state:{$this->chatId}");
        $this->assertIsArray($state);
        $this->assertSame($draft->id, $state['draft_id']);
        $this->assertSame('awaiting_datetime', $state['step']);
        $this->assertCount(3, $state['candidate_slots']);
        Http::assertSentCount(1);
    }

    public function test_idempotent_second_run_does_not_reprompt(): void
    {
        $draft = $this->makeReadyCarousel();
        $this->artisan('linkedin:prompt-schedule');
        $firstStamp = $draft->fresh()->schedule_prompt_sent_at;

        $this->artisan('linkedin:prompt-schedule')->assertExitCode(0);

        $this->assertEquals(
            $firstStamp->toIso8601String(),
            $draft->fresh()->schedule_prompt_sent_at->toIso8601String()
        );
        Http::assertSentCount(1); // conversation already open → no second send
    }

    public function test_skips_unready_carousel_with_pending_slide(): void
    {
        $draft = $this->makeReadyCarousel();
        $slides = $draft->carousel_slides;
        $slides[0]['image_status'] = 'generating';
        $draft->update(['carousel_slides' => $slides]);

        $this->artisan('linkedin:prompt-schedule')->assertExitCode(0);

        $this->assertNull($draft->fresh()->schedule_prompt_sent_at);
        $this->assertNull(Cache::get("telegram_schedule_state:{$this->chatId}"));
    }

    public function test_noop_when_master_toggle_off(): void
    {
        $this->setSetting('linkedin', 'linkedin_telegram_schedule_enabled', 'false');
        $draft = $this->makeReadyCarousel();

        $this->artisan('linkedin:prompt-schedule')->assertExitCode(0);

        $this->assertNull($draft->fresh()->schedule_prompt_sent_at);
        Http::assertNothingSent();
    }

    public function test_does_not_consume_one_shot_when_send_fails(): void
    {
        // Simulate a failed Telegram send by clearing the bot token — send()
        // returns false at the token gate, so sendSchedulePrompt returns false.
        $this->setSetting('telegram', 'telegram_bot_token', '');
        $draft = $this->makeReadyCarousel();

        $this->artisan('linkedin:prompt-schedule')->assertExitCode(0);

        $this->assertNull($draft->fresh()->schedule_prompt_sent_at, 'flag not stamped on failed send');
        $this->assertNull(Cache::get("telegram_schedule_state:{$this->chatId}"));
    }

    public function test_seeder_registers_prompt_schedule_row_idempotently(): void
    {
        $this->seed(ScheduledCommandSeeder::class);
        $this->seed(ScheduledCommandSeeder::class);

        $this->assertSame(1, ScheduledCommand::where('signature', 'linkedin:prompt-schedule')->count());
    }
}
