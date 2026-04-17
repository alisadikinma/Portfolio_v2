<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Services\TelegramNotifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class TelegramNotifierTest extends TestCase
{
    private TelegramNotifier $notifier;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('services.telegram.bot_token', 'fake-token-123');
        Config::set('services.telegram.chat_id', '999888');
        Config::set('services.telegram.enabled', true);
        Config::set('app.frontend_url', 'https://alisadikinma.com');

        $this->notifier = new TelegramNotifier();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makePost(string $slug, ?string $title, ?string $excerpt = null, ?int $sourceIdeaId = null): Post
    {
        $translation = new PostTranslation();
        $translation->title = $title;
        $translation->excerpt = $excerpt;

        $post = Mockery::mock(Post::class)->makePartial();
        $post->id = 1;
        $post->slug = $slug;
        $post->source_idea_id = $sourceIdeaId;

        $relation = Mockery::mock('Illuminate\Database\Eloquent\Relations\HasMany');
        $relation->shouldReceive('first')->andReturn($translation);
        $post->shouldReceive('translations')->andReturn($relation);

        return $post;
    }

    private function stubIdeaFind(?ContentIdea $idea): void
    {
        $mock = Mockery::mock('alias:' . ContentIdea::class);
        $mock->shouldReceive('find')->andReturn($idea);
    }

    /** @test */
    public function notify_publish_success_sends_correct_message_to_telegram_api()
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $post = $this->makePost(
            slug: 'test-post-slug',
            title: 'How AI Changes Everything',
            excerpt: 'A short summary of the article.',
            sourceIdeaId: null
        );

        $this->notifier->notifyPublishSuccess($post);

        Http::assertSent(function ($request) {
            $url = $request->url();
            $body = $request->data();

            if (!str_contains($url, 'api.telegram.org/botfake-token-123/sendMessage')) {
                return false;
            }
            if (($body['chat_id'] ?? null) !== '999888') {
                return false;
            }
            $text = $body['text'] ?? '';
            return str_contains($text, '🚀')
                && str_contains($text, 'New blog published')
                && str_contains($text, 'How AI Changes Everything')
                && str_contains($text, 'https://alisadikinma.com/blog/test-post-slug')
                && str_contains($text, 'A short summary of the article.')
                && ($body['parse_mode'] ?? null) === 'Markdown';
        });
    }

    /** @test */
    public function notify_final_failure_sends_failure_template_to_telegram_api()
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $idea = new ContentIdea();
        $idea->id = 42;
        $idea->title = 'Failed Topic Draft';
        $idea->pipeline_failed_stage = 'research';
        $idea->pipeline_attempts = 3;
        $idea->progress_log = [
            ['timestamp' => '2026-04-17T22:00:00Z', 'step' => 'prep', 'message' => 'Started'],
            ['timestamp' => '2026-04-17T22:05:00Z', 'step' => 'failed', 'message' => 'SSH timeout after 180s'],
        ];

        $this->notifier->notifyFinalFailure($idea);

        Http::assertSent(function ($request) {
            $text = $request->data()['text'] ?? '';
            return str_contains($text, '⚠️')
                && str_contains($text, 'Content pipeline failed')
                && str_contains($text, 'Failed Topic Draft')
                && str_contains($text, 'Failed stage: research')
                && str_contains($text, 'Attempts: 3')
                && str_contains($text, 'SSH timeout after 180s')
                && str_contains($text, '/admin/content-engine');
        });
    }

    /** @test */
    public function when_enabled_is_false_no_http_call_is_made()
    {
        Config::set('services.telegram.enabled', false);
        Http::fake();

        $post = $this->makePost('slug', 'Title');
        $this->notifier->notifyPublishSuccess($post);

        Http::assertNothingSent();
    }

    /** @test */
    public function missing_bot_token_logs_warning_and_sends_nothing()
    {
        Config::set('services.telegram.bot_token', null);
        Http::fake();

        Log::shouldReceive('warning')
            ->once()
            ->with(Mockery::pattern('/bot_token or chat_id not configured/'));

        $post = $this->makePost('slug', 'Title');
        $this->notifier->notifyPublishSuccess($post);

        Http::assertNothingSent();
    }

    /** @test */
    public function api_failure_logs_warning_but_does_not_throw()
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => false, 'description' => 'Bad Request'], 400),
        ]);

        Log::shouldReceive('warning')
            ->once()
            ->with('[Telegram] sendMessage failed', Mockery::type('array'));

        $post = $this->makePost('slug', 'Title');

        // Must not throw
        $this->notifier->notifyPublishSuccess($post);

        // HTTP call WAS attempted
        Http::assertSentCount(1);
    }

    /** @test */
    public function markdown_special_chars_in_title_are_escaped()
    {
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true], 200),
        ]);

        $post = $this->makePost('slug', 'Weird_title*with[brackets');
        $this->notifier->notifyPublishSuccess($post);

        Http::assertSent(function ($request) {
            $text = $request->data()['text'] ?? '';
            // Original special chars must be escaped with backslash
            return str_contains($text, 'Weird\\_title\\*with\\[brackets');
        });
    }
}
