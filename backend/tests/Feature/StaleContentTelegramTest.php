<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * content:flag-stale-posts must send exactly ONE Telegram digest covering all
 * stale posts (no per-post spam) when telegram_notify_stale_content is on, and
 * zero sends when the toggle is off.
 */
class StaleContentTelegramTest extends TestCase
{
    use RefreshDatabase;

    private function telegramConfigured(bool $notifyStale): void
    {
        $rows = [
            'telegram_bot_token' => '123456:test-token',
            'telegram_chat_id' => '99999',
            'telegram_enabled' => 'true',
            'telegram_notify_stale_content' => $notifyStale ? 'true' : 'false',
        ];
        foreach ($rows as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key, 'group' => 'telegram'],
                ['value' => $value, 'type' => 'text']
            );
        }
    }

    private function makeStalePost(string $title): Post
    {
        $category = Category::firstOrCreate(['slug' => 'ai'], ['name' => 'AI']);
        $post = Post::create([
            'category_id' => $category->id,
            'slug' => 'post-' . uniqid(),
            'content' => '<p>body</p>',
            'published' => true,
            'published_at' => now()->subDays(120),
        ]);
        $post->translations()->create([
            'language' => 'en',
            'title' => $title,
            'slug' => $post->slug,
            'content' => '<p>body</p>',
        ]);

        return $post;
    }

    public function test_sends_single_digest_with_all_titles(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        $this->telegramConfigured(notifyStale: true);

        $this->makeStalePost('First Stale Post');
        $this->makeStalePost('Second Stale Post');

        $this->artisan('content:flag-stale-posts', ['--days' => 90])->assertExitCode(0);

        // Exactly one sendMessage call carrying BOTH titles.
        Http::assertSentCount(1);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/sendMessage')
                && str_contains($request['text'], 'First Stale Post')
                && str_contains($request['text'], 'Second Stale Post');
        });
    }

    public function test_no_send_when_toggle_off(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        $this->telegramConfigured(notifyStale: false);

        $this->makeStalePost('Stale But Silent');

        $this->artisan('content:flag-stale-posts', ['--days' => 90])->assertExitCode(0);

        Http::assertNothingSent();
    }
}
