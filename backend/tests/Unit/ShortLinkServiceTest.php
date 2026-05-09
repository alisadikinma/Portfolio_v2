<?php

namespace Tests\Unit;

use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\ShortLink;
use App\Services\ShortLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShortLinkServiceTest extends TestCase
{
    use RefreshDatabase;

    private ShortLinkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://alisadikinma.com']);
        $this->service = app(ShortLinkService::class);
    }

    private function makePost(string $slug = 'test-blog-post'): Post
    {
        return Post::create([
            'slug' => $slug,
            'category_id' => null,
            'published' => true,
            'published_at' => now(),
        ]);
    }

    public function test_forBlogPost_creates_short_link_with_utm_for_known_platform(): void
    {
        $post = $this->makePost('google-gemini-3-benchmark');

        $shortUrl = $this->service->forBlogPost($post, 'tiktok');

        $this->assertMatchesRegularExpression(
            '#^https://alisadikinma\.com/r/[A-Za-z0-9]{6,9}$#',
            $shortUrl
        );
        $row = ShortLink::firstWhere('post_id', $post->id);
        $this->assertNotNull($row);
        $this->assertSame('tiktok', $row->source_platform);
        $this->assertStringContainsString('utm_source=tiktok', $row->target_url);
        $this->assertStringContainsString('utm_medium=social', $row->target_url);
        $this->assertStringContainsString('utm_campaign=cross-post', $row->target_url);
        $this->assertStringContainsString('/blog/google-gemini-3-benchmark', $row->target_url);
    }

    public function test_forBlogPost_is_idempotent_per_post_platform_pair(): void
    {
        $post = $this->makePost();

        $first = $this->service->forBlogPost($post, 'linkedin');
        $second = $this->service->forBlogPost($post, 'linkedin');

        $this->assertSame($first, $second);
        $this->assertSame(1, ShortLink::where('post_id', $post->id)->count());
    }

    public function test_forBlogPost_creates_separate_rows_per_platform(): void
    {
        $post = $this->makePost();

        $linkedinUrl = $this->service->forBlogPost($post, 'linkedin');
        $tiktokUrl = $this->service->forBlogPost($post, 'tiktok');
        $threadsUrl = $this->service->forBlogPost($post, 'threads');

        $this->assertNotSame($linkedinUrl, $tiktokUrl);
        $this->assertNotSame($linkedinUrl, $threadsUrl);
        $this->assertNotSame($tiktokUrl, $threadsUrl);
        $this->assertSame(3, ShortLink::where('post_id', $post->id)->count());
    }

    public function test_forBlogPost_throws_for_missing_post(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->forBlogPost(99999, 'tiktok');
    }

    public function test_forBlogPost_with_unknown_platform_skips_utm_and_attribution(): void
    {
        $post = $this->makePost();

        $this->service->forBlogPost($post, 'pinterest'); // unknown
        $row = ShortLink::firstWhere('post_id', $post->id);

        $this->assertNull($row->source_platform); // not in known platforms
        $this->assertStringNotContainsString('utm_source', $row->target_url);
    }

    public function test_recordHit_increments_hits_and_sets_last_hit_at(): void
    {
        $post = $this->makePost();
        $this->service->forBlogPost($post, 'linkedin');
        $row = ShortLink::firstWhere('post_id', $post->id);

        $this->assertSame(0, $row->hits);
        $this->assertNull($row->last_hit_at);

        $this->service->recordHit($row);
        $row->refresh();

        $this->assertSame(1, $row->hits);
        $this->assertNotNull($row->last_hit_at);
    }

    public function test_short_url_length_is_significantly_shorter_than_full_blog_url(): void
    {
        $post = $this->makePost('google-gemini-3-nyamai-chatgpt-di-semua-benchmark-dan-ini-baru-awal');

        $shortUrl = $this->service->forBlogPost($post, 'tiktok');
        $fullUrl = 'https://alisadikinma.com/blog/' . $post->slug;

        $this->assertLessThanOrEqual(40, strlen($shortUrl));
        $this->assertGreaterThanOrEqual(60, strlen($fullUrl) - strlen($shortUrl)); // saves at least 60 chars
    }
}
