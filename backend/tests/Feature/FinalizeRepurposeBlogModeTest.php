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
 * Phase F (blog mode, June 13, 2026) — finalize enters at `extracted` and seeds a
 * DRAFT ContentIdea from the IG material (no pre-written article, no scoring shortcut).
 * The operator runs the proper Content Engine pipeline. NO Post / LinkedInPost /
 * carousel dispatch here. Success purges the artifact dir + sends a Telegram notice.
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

    private function extracted(): array
    {
        return [
            'caption' => 'BREAKING: The US government ordered Anthropic to disable two models. This is a watershed moment for AI policy.',
            'narrative' => 'The post breaks the news that a government, not a company, pulled a top AI model.',
            'slides' => [
                'Claude logo with red X overlay — no headline text on image',
                'THE US GOVERNMENT ORDERED ANTHROPIC TO BLOCK ACCESS — THIS IS A WATERSHED MOMENT',
                'This moved from a San Francisco problem to a Washington D.C. problem in one night.',
            ],
            'claims' => [
                ['claim' => 'A government suspended a frontier AI model for the first time.'],
                ['claim' => 'All users lost access, not only foreign nationals.'],
            ],
        ];
    }

    public function test_blog_mode_seeds_draft_idea_from_extracted(): void
    {
        Bus::fake([GenerateLinkedInPost::class]);
        $dir = storage_path('app/repurpose/777');
        @mkdir($dir, 0775, true);

        $job = RepurposeJob::factory()->create([
            'status' => 'extracted',
            'mode' => 'blog',
            'slides_path' => 'repurpose/777',
            'source_url' => 'https://www.instagram.com/p/ABC123/',
            'extracted' => $this->extracted(),
        ]);

        (new FinalizeRepurpose($job->id))->handle();

        $job->refresh();
        $this->assertSame('drafted', $job->status);
        $this->assertNotNull($job->content_idea_id);

        $idea = ContentIdea::find($job->content_idea_id);
        $this->assertNotNull($idea);
        $this->assertSame('draft', $idea->status);
        $this->assertSame('instagram', $idea->source);
        $this->assertSame('ig_repurpose', $idea->source_data['source']);
        $this->assertSame('https://www.instagram.com/p/ABC123/', $idea->source_data['url']);

        // No pre-written article — the proper pipeline produces it.
        $this->assertNull($idea->generated_article);

        // Brief carries the IG source material for article-prep.
        $this->assertNotEmpty($idea->instructions);
        $this->assertStringContainsString('watershed moment', $idea->instructions);
        $this->assertStringContainsString('fact-check ulang', $idea->instructions);

        // Provisional title derived from the caption (BREAKING prefix stripped).
        $this->assertStringContainsString('US government', $idea->title);

        // No carousel-side artifacts in blog mode.
        $this->assertSame(0, Post::count());
        $this->assertSame(0, LinkedInPost::count());
        Bus::assertNotDispatched(GenerateLinkedInPost::class);

        // Artifact dir purged on success.
        $this->assertDirectoryDoesNotExist($dir);
        Http::assertSent(fn ($req) => str_contains($req->url(), '/sendMessage'));
    }

    public function test_empty_extracted_routes_to_failed(): void
    {
        $job = RepurposeJob::factory()->create([
            'status' => 'extracted',
            'mode' => 'blog',
            'extracted' => ['caption' => '', 'slides' => []],
        ]);

        (new FinalizeRepurpose($job->id))->handle();

        $this->assertSame('failed', $job->refresh()->status);
        $this->assertSame(0, ContentIdea::count());
    }
}
