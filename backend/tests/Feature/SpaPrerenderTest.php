<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Feature coverage for the SSR-enrichment layer (Phase C + D):
 * SpaPrerenderController + routes/web.php. Each test points the controller at a
 * fixture SPA shell via config('seo.spa_shell_path') so it never depends on a
 * real frontend build, and pins config('app.url') for deterministic URLs.
 */
class SpaPrerenderTest extends TestCase
{
    use RefreshDatabase;

    private string $shellPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Deterministic absolute URLs in canonical/og/jsonld assertions.
        config(['app.url' => 'https://alisadikinma.com']);

        // Minimal SPA shell carrying every anchor SeoHtmlComposer rewrites.
        $this->shellPath = sys_get_temp_dir() . '/seo-shell-' . uniqid() . '.html';
        file_put_contents($this->shellPath, $this->fixtureShell());
        config(['seo.spa_shell_path' => $this->shellPath]);
    }

    protected function tearDown(): void
    {
        @unlink($this->shellPath);
        parent::tearDown();
    }

    private function fixtureShell(): string
    {
        return <<<'HTML'
<!doctype html>
<html lang="en">
<head>
  <title>Ali Sadikin Ma</title>
  <meta name="title" content="Ali Sadikin Ma">
  <meta name="description" content="default">
  <meta property="og:type" content="website">
  <meta property="og:url" content="https://alisadikinma.com/">
  <meta property="og:title" content="Ali Sadikin Ma">
  <meta property="og:description" content="default">
  <meta property="og:image" content="https://alisadikinma.com/og.jpg">
  <meta property="og:locale" content="en_US">
  <meta name="twitter:url" content="https://alisadikinma.com/">
  <meta name="twitter:title" content="Ali Sadikin Ma">
  <meta name="twitter:description" content="default">
  <meta name="twitter:image" content="https://alisadikinma.com/og.jpg">
  <link rel="canonical" href="https://alisadikinma.com/">
</head>
<body>
  <div id="app"></div>
  <script type="module" src="/assets/app.js"></script>
</body>
</html>
HTML;
    }

    private function makePost(array $overrides = [], array $translation = []): Post
    {
        $category = Category::firstOrCreate(
            ['slug' => 'ai-agents'],
            ['name' => 'AI Agents']
        );

        $post = Post::create(array_merge([
            'category_id' => $category->id,
            'title' => 'Shipping AI Agents in Production',
            'slug' => 'shipping-ai-agents',
            'excerpt' => 'A field note on what actually ships.',
            'content' => '<p>Real agents need real guardrails and observability.</p>',
            'featured_image' => 'https://alisadikinma.com/storage/posts/hero.jpg',
            'og_image' => 'https://alisadikinma.com/storage/posts/hero.jpg',
            'published' => true,
            'published_at' => now()->subDay(),
        ], $overrides));

        $post->translations()->create(array_merge([
            'language' => 'en',
            'title' => 'Shipping AI Agents in Production',
            'slug' => 'shipping-ai-agents',
            'excerpt' => 'A field note on what actually ships.',
            'content' => '<p>Real agents need real guardrails and observability.</p>',
            'meta_title' => 'Shipping AI Agents in Production | Ali Sadikin Ma',
            'meta_description' => 'How to ship AI agents that survive contact with real users.',
            'ai_summary' => 'AI agents in production require guardrails, evals, and observability.',
        ], $translation));

        return $post->fresh(['translations', 'category']);
    }

    public function test_blog_detail_injects_crawlable_article_body_and_jsonld(): void
    {
        $this->makePost();

        $res = $this->get('/blog/shipping-ai-agents');

        $res->assertStatus(200);
        $res->assertHeader('Content-Type', 'text/html; charset=UTF-8');
        // Crawlable article body spliced into #app.
        $res->assertSee('<article>', false);
        $res->assertSee('Real agents need real guardrails', false);
        // Per-page head.
        $res->assertSee('<link rel="canonical" href="https://alisadikinma.com/blog/shipping-ai-agents">', false);
        // JSON-LD entity graph.
        $res->assertSee('"@type":"BlogPosting"', false);
        $res->assertSee('"@type":"BreadcrumbList"', false);
        // SPA app div no longer empty.
        $res->assertDontSee('<div id="app"></div>', false);
    }

    public function test_blog_detail_returns_404_for_unpublished_post(): void
    {
        $this->makePost(['published' => false]);

        $this->get('/blog/shipping-ai-agents')->assertStatus(404);
    }

    public function test_lang_prefixed_detail_sets_locale_and_hreflang(): void
    {
        $this->makePost();

        $res = $this->get('/en/blog/shipping-ai-agents');

        $res->assertStatus(200);
        $res->assertSee('hreflang="id"', false);
        $res->assertSee('https://alisadikinma.com/id/blog/shipping-ai-agents', false);
        $res->assertSee('hreflang="x-default"', false);
    }

    public function test_blog_index_injects_itemlist_and_summary(): void
    {
        $this->makePost();

        $res = $this->get('/blog');

        $res->assertStatus(200);
        $res->assertSee('"@type":"ItemList"', false);
        $res->assertSee('Shipping AI Agents in Production', false);
        $res->assertSee('<link rel="canonical" href="https://alisadikinma.com/blog">', false);
    }

    public function test_blog_category_injects_collectionpage(): void
    {
        $this->makePost();

        $res = $this->get('/blog/category/ai-agents');

        $res->assertStatus(200);
        $res->assertSee('"@type":"CollectionPage"', false);
        $res->assertSee('Shipping AI Agents in Production', false);
    }

    public function test_blog_category_404_for_unknown_slug(): void
    {
        $this->get('/blog/category/does-not-exist')->assertStatus(404);
    }

    public function test_home_injects_website_schema_and_summary(): void
    {
        $this->makePost();

        $res = $this->get('/');

        $res->assertStatus(200);
        $res->assertSee('"@type":"WebSite"', false);
        $res->assertSee('AI Generalist', false);
        // Recent-posts summary surfaces the post link.
        $res->assertSee('shipping-ai-agents', false);
    }

    public function test_html_is_cached_and_purged_on_post_update(): void
    {
        $post = $this->makePost();

        // Prime the cache.
        $this->get('/blog/shipping-ai-agents')->assertStatus(200);
        $this->assertTrue(Cache::has('seo_html:blog_detail::shipping-ai-agents'));

        // A post save must purge the entry (Post::boot saved hook).
        $post->update(['excerpt' => 'Updated excerpt']);
        $this->assertFalse(Cache::has('seo_html:blog_detail::shipping-ai-agents'));
        $this->assertFalse(Cache::has('seo_html:home'));
        $this->assertFalse(Cache::has('seo_html:blog_index:'));
    }

    public function test_slug_rename_purges_the_old_slug_detail_cache(): void
    {
        $post = $this->makePost();

        $this->get('/blog/shipping-ai-agents')->assertStatus(200);
        $this->assertTrue(Cache::has('seo_html:blog_detail::shipping-ai-agents'));

        // Renaming the slug must purge the OLD slug's cache (getOriginal), not
        // just the new one — otherwise the dead URL serves a stale 200 for 1h.
        $post->update(['slug' => 'shipping-ai-agents-v2']);
        $this->assertFalse(Cache::has('seo_html:blog_detail::shipping-ai-agents'));
    }

    public function test_category_save_purges_category_and_member_detail_cache(): void
    {
        $post = $this->makePost();
        $category = $post->category;

        $this->get('/blog/category/ai-agents')->assertStatus(200);
        $this->get('/blog/shipping-ai-agents')->assertStatus(200);
        $this->assertTrue(Cache::has('seo_html:blog_category::ai-agents'));
        $this->assertTrue(Cache::has('seo_html:blog_detail::shipping-ai-agents'));

        // Category rename must purge its page (under the OLD slug) + member
        // post detail pages whose breadcrumb embeds the category name.
        $category->update(['name' => 'AI Agents Renamed']);
        $this->assertFalse(Cache::has('seo_html:blog_category::ai-agents'));
        $this->assertFalse(Cache::has('seo_html:blog_detail::shipping-ai-agents'));
    }
}
