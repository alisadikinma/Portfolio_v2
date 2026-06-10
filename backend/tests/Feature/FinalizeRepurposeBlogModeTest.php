<?php

namespace Tests\Feature;

use App\Jobs\FinalizeRepurpose;
use App\Jobs\GenerateLinkedInPost;
use App\Models\ContentIdea;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\RepurposeJob;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase F (blog mode) — finalize creates a ContentIdea(article_ready) only and
 * enters the existing Content Engine pipeline. NO Post / LinkedInPost / carousel
 * dispatch. Success purges the artifact dir + sends a Telegram drafted notice.
 */
class FinalizeRepurposeBlogModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['group' => 'telegram', 'key' => 'telegram_enabled', 'value' => 'true', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_bot_token', 'value' => 'TOKEN', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_chat_id', 'value' => '99', 'type' => 'text']);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    private function rewritten(): array
    {
        return [
            'title' => 'The Real Story Behind AI Job Claims',
            'body' => '<h2>Intro</h2><p>...</p><h2>Sumber</h2><ul><li><a href="https://x">x</a></li></ul>',
            'excerpt' => 'A sharper, fact-checked take.',
            'meta_keywords' => 'AI, jobs, automation',
            'sources_appendix' => ['https://example.org/study'],
        ];
    }

    public function test_blog_mode_creates_content_idea_and_drafts(): void
    {
        Bus::fake([GenerateLinkedInPost::class]);
        $dir = storage_path('app/repurpose/777');
        @mkdir($dir, 0775, true);

        $job = RepurposeJob::factory()->create([
            'status' => 'rewritten',
            'mode' => 'blog',
            'slides_path' => 'repurpose/777',
            'research' => ['corrected_count' => 2],
            'rewritten' => $this->rewritten(),
        ]);

        (new FinalizeRepurpose($job->id))->handle();

        $job->refresh();
        $this->assertSame('drafted', $job->status);
        $this->assertNotNull($job->content_idea_id);

        $idea = ContentIdea::find($job->content_idea_id);
        $this->assertNotNull($idea);
        $this->assertSame('article_ready', $idea->status);
        $this->assertSame('instagram', $idea->source);
        $this->assertSame('ig_repurpose', $idea->source_data['source']);
        $this->assertStringContainsString('Intro', $idea->generated_article['content']);

        // No carousel-side artifacts in blog mode.
        $this->assertSame(0, Post::count());
        $this->assertSame(0, LinkedInPost::count());
        Bus::assertNotDispatched(GenerateLinkedInPost::class);

        // Artifact dir purged on success.
        $this->assertDirectoryDoesNotExist($dir);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/sendMessage'));
    }

    public function test_missing_body_routes_to_failed(): void
    {
        Bus::fake([GenerateLinkedInPost::class]);
        $job = RepurposeJob::factory()->create([
            'status' => 'rewritten',
            'mode' => 'blog',
            'rewritten' => ['title' => 'x'], // no body
        ]);

        (new FinalizeRepurpose($job->id))->handle();

        $this->assertSame('failed', $job->refresh()->status);
        $this->assertSame(0, ContentIdea::count());
    }
}
