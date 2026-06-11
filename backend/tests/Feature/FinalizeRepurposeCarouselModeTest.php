<?php

namespace Tests\Feature;

use App\Jobs\FinalizeRepurpose;
use App\Jobs\GenerateLinkedInPost;
use App\Models\Category;
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
 * Phase F (carousel mode) — finalize creates an anchor blog Post(draft) +
 * primary PostTranslation + LinkedInPost(carousel, pending_generation) and
 * dispatches GenerateLinkedInPost (existing force-carousel path). NO ContentIdea.
 * Success RETAINS the artifact dir (source↔generated comparison in Social Studio;
 * the 7-day repurpose:reap reaper cleans up) + sends a Telegram drafted notice.
 */
class FinalizeRepurposeCarouselModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Setting::create(['group' => 'telegram', 'key' => 'telegram_enabled', 'value' => 'true', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_bot_token', 'value' => 'TOKEN', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_chat_id', 'value' => '99', 'type' => 'text']);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        Category::create(['name' => 'AI & Tech']);
    }

    private function rewritten(): array
    {
        return [
            'title' => 'Vibe Coding Is Not What You Think',
            'body' => '<h2>Intro</h2><p>...</p>',
            'excerpt' => 'A sharper take.',
            'meta_keywords' => 'vibe coding, AI',
            'sources_appendix' => ['https://example.org'],
        ];
    }

    public function test_carousel_mode_creates_anchor_post_linkedin_draft_and_dispatches(): void
    {
        Bus::fake([GenerateLinkedInPost::class]);

        $job = RepurposeJob::factory()->create([
            'status' => 'rewritten',
            'mode' => 'carousel',
            'research' => ['corrected_count' => 1],
            'rewritten' => $this->rewritten(),
        ]);

        (new FinalizeRepurpose($job->id))->handle();

        $job->refresh();
        $this->assertSame('drafted', $job->status);
        $this->assertNotNull($job->anchor_post_id);
        $this->assertNotNull($job->linkedin_post_id);

        $post = Post::find($job->anchor_post_id);
        $this->assertNotNull($post);
        $this->assertFalse((bool) $post->published);
        $this->assertSame(1, $post->translations()->where('language', 'id')->count());

        $draft = LinkedInPost::find($job->linkedin_post_id);
        $this->assertNotNull($draft);
        $this->assertSame('carousel', $draft->format);
        $this->assertSame('pending_generation', $draft->status);
        $this->assertSame($post->id, $draft->post_id);

        Bus::assertDispatched(GenerateLinkedInPost::class, fn ($j) => $j->draftId === $draft->id);

        // No blog-mode artifact in carousel mode.
        $this->assertSame(0, ContentIdea::count());
        Http::assertSent(fn ($req) => str_contains($req->url(), '/sendMessage'));
    }
}
