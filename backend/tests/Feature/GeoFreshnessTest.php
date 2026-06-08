<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase E — GEO freshness signals on the llms.txt endpoints: a "Last updated"
 * timestamp plus per-post publish dates and AI summaries, so LLM crawlers can
 * gauge recency and quote the optimized summary instead of a raw content slice.
 */
class GeoFreshnessTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(): Post
    {
        $category = Category::firstOrCreate(['slug' => 'ai-agents'], ['name' => 'AI Agents']);

        $post = Post::create([
            'category_id' => $category->id,
            'title' => 'Shipping AI Agents in Production',
            'slug' => 'shipping-ai-agents',
            'excerpt' => 'A field note on what actually ships.',
            'content' => '<p>Real agents need guardrails.</p>',
            'published' => true,
            'published_at' => now()->subDays(2),
        ]);

        $post->translations()->create([
            'language' => 'en',
            'title' => 'Shipping AI Agents in Production',
            'slug' => 'shipping-ai-agents',
            'content' => '<p>Real agents need guardrails.</p>',
            'ai_summary' => 'AI agents in production require guardrails, evals, and observability.',
        ]);

        return $post;
    }

    public function test_llms_txt_includes_freshness_and_post_summary(): void
    {
        $this->makePost();

        $res = $this->get('/api/llms.txt');

        $res->assertStatus(200);
        $res->assertHeader('Content-Type', 'text/plain; charset=utf-8');
        $res->assertSee('Last updated:');
        $res->assertSee('Shipping AI Agents in Production');
        // ai_summary surfaced inline with the post title.
        $res->assertSee('require guardrails, evals, and observability');
    }

    public function test_llms_full_txt_includes_published_date_and_ai_summary(): void
    {
        $this->makePost();

        $res = $this->get('/api/llms-full.txt');

        $res->assertStatus(200);
        $res->assertSee('Last updated:');
        $res->assertSee('_Published:');
        $res->assertSee('AI agents in production require guardrails');
    }

    public function test_llms_txt_renders_without_posts(): void
    {
        // No posts — endpoint must still return 200 (freshness line simply absent).
        $this->get('/api/llms.txt')->assertStatus(200);
    }
}
