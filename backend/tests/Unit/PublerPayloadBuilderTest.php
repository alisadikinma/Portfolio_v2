<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\FacebookPost;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\ThreadsPost;
use App\Models\TiktokPost;
use App\Services\PublerPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TDD — PublerPayloadBuilder payload construction (P4, 2026-05-13).
 *
 * Verifies that each platform's payload builder emits the correct shape for
 * the Publer POST /posts/schedule endpoint, covering:
 *   - accounts[] with the right platform account_id from settings
 *   - caption body construction (caption + hashtags for IG/TH/FB, URL-in-body for TikTok)
 *   - media[] pulled from linkedinPost->carousel_slides[].image_url
 *   - comments[] (IG + Threads only, NOT TikTok, NOT Facebook per May 10 cleanup)
 *   - scheduled_at ISO 8601
 *   - RuntimeException when account_id not configured in settings
 */
class PublerPayloadBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(): Post
    {
        // category_id is NOT NULL in posts table
        $category = \App\Models\Category::create(['name' => 'AI', 'slug' => 'ai-' . uniqid()]);
        return Post::create([
            'category_id' => $category->id,
            'slug' => 'test-post-' . uniqid(),
            'title' => 'Test Post',
            'content' => 'Test content',
        ]);
    }

    private function makeLinkedInPost(array $overrides = []): LinkedInPost
    {
        $post = $this->makePost();

        $slides = [
            ['slide_number' => 1, 'image_url' => 'https://cdn.test/slide-01.png', 'is_cover' => true, 'is_cta' => false],
            ['slide_number' => 2, 'image_url' => 'https://cdn.test/slide-02.png', 'is_cover' => false, 'is_cta' => false],
            ['slide_number' => 3, 'image_url' => 'https://cdn.test/slide-03.png', 'is_cover' => false, 'is_cta' => true],
        ];

        return LinkedInPost::create(array_merge([
            'post_id' => $post->id,
            'format' => 'carousel',
            'content' => 'Some LinkedIn content',
            'carousel_slides' => $slides,
            'hashtags' => ['#AI', '#Automation'],
            'status' => 'awaiting_publish',
            'pipeline_state_log' => [],
        ], $overrides));
    }

    private function setSetting(string $key, string $value): void
    {
        \App\Models\Setting::create([
            'group' => 'publer',
            'key' => $key,
            'value' => $value,
        ]);
    }

    // ─── Instagram ────────────────────────────────────────────────────────────

    public function test_instagram_payload_carries_caption_hashtags_link_comment_and_slides(): void
    {
        $this->setSetting('publer_instagram_account_id', 'ig_acc_123');

        $linkedinPost = $this->makeLinkedInPost();

        $sibling = InstagramPost::create([
            'linkedin_post_id' => $linkedinPost->id,
            'post_id' => $linkedinPost->post_id,
            'status' => 'awaiting_review',
            'caption' => 'Ini caption Instagram.',
            'hashtags' => ['#AI', '#VibeCoding'],
            'link_comment' => 'Full article: https://alisadikinma.com/blog/test-post',
            'scheduled_at' => '2026-05-15 17:00:00',
        ]);
        $sibling->load('linkedinPost');

        $builder = new PublerPayloadBuilder();
        $payload = $builder->buildInstagram($sibling);

        // accounts[]
        $this->assertArrayHasKey('accounts', $payload);
        $this->assertCount(1, $payload['accounts']);
        $this->assertSame('ig_acc_123', $payload['accounts'][0]['id']);

        // caption includes both body and hashtags
        $this->assertStringContainsString('Ini caption Instagram.', $payload['caption']);
        $this->assertStringContainsString('#AI', $payload['caption']);
        $this->assertStringContainsString('#VibeCoding', $payload['caption']);

        // media[] sourced from linkedinPost->carousel_slides[].image_url
        $this->assertArrayHasKey('media', $payload);
        $this->assertCount(3, $payload['media']);
        $this->assertSame('https://cdn.test/slide-01.png', $payload['media'][0]['url']);
        $this->assertSame('https://cdn.test/slide-03.png', $payload['media'][2]['url']);

        // comments[] with link_comment + 30s delay
        $this->assertArrayHasKey('comments', $payload);
        $this->assertCount(1, $payload['comments']);
        $this->assertStringContainsString('https://alisadikinma.com/blog/test-post', $payload['comments'][0]['text']);
        $this->assertSame(30, $payload['comments'][0]['delay']['duration']);
        $this->assertSame('seconds', $payload['comments'][0]['delay']['unit']);

        // scheduled_at ISO 8601
        $this->assertArrayHasKey('scheduled_at', $payload);
        $this->assertStringContainsString('2026-05-15', $payload['scheduled_at']);
    }

    // ─── TikTok ────────────────────────────────────────────────────────────────

    public function test_tiktok_payload_has_url_in_caption_body_and_no_comments(): void
    {
        $this->setSetting('publer_tiktok_account_id', 'tt_acc_456');

        $linkedinPost = $this->makeLinkedInPost();

        $sibling = TiktokPost::create([
            'linkedin_post_id' => $linkedinPost->id,
            'post_id' => $linkedinPost->post_id,
            'status' => 'awaiting_review',
            'title' => 'TikTok title here',
            'caption' => 'Caption TikTok dengan URL. https://ali.me/r/abc1234',
            'hashtags' => ['#AI', '#TechTok'],
            'link_comment' => 'Full article: https://alisadikinma.com/blog/test-post',
            'scheduled_at' => '2026-05-15 17:00:00',
        ]);
        $sibling->load('linkedinPost');

        $builder = new PublerPayloadBuilder();
        $payload = $builder->buildTiktok($sibling);

        // accounts[]
        $this->assertSame('tt_acc_456', $payload['accounts'][0]['id']);

        // caption carries URL (TikTok has no first-comment API)
        $this->assertStringContainsString('https://ali.me/r/abc1234', $payload['caption']);

        // NO comments[] for TikTok
        $this->assertArrayNotHasKey('comments', $payload);

        // title field included
        $this->assertArrayHasKey('title', $payload);
        $this->assertSame('TikTok title here', $payload['title']);

        // media[] from carousel_slides
        $this->assertArrayHasKey('media', $payload);
        $this->assertCount(3, $payload['media']);
    }

    // ─── Threads ───────────────────────────────────────────────────────────────

    public function test_threads_payload_carries_caption_hashtags_link_comment_and_slides(): void
    {
        $this->setSetting('publer_threads_account_id', 'th_acc_789');

        $linkedinPost = $this->makeLinkedInPost();

        $sibling = ThreadsPost::create([
            'linkedin_post_id' => $linkedinPost->id,
            'post_id' => $linkedinPost->post_id,
            'status' => 'awaiting_review',
            'caption' => 'Ini caption Threads singkat.',
            'hashtags' => ['#Automation'],
            'link_comment' => 'Full article: https://alisadikinma.com/blog/test-post',
            'scheduled_at' => '2026-05-15 17:00:00',
        ]);
        $sibling->load('linkedinPost');

        $builder = new PublerPayloadBuilder();
        $payload = $builder->buildThreads($sibling);

        // accounts[]
        $this->assertSame('th_acc_789', $payload['accounts'][0]['id']);

        // caption + hashtags
        $this->assertStringContainsString('Ini caption Threads singkat.', $payload['caption']);
        $this->assertStringContainsString('#Automation', $payload['caption']);

        // media[] from carousel_slides
        $this->assertCount(3, $payload['media']);

        // comments[] with link_comment + 30s delay
        $this->assertArrayHasKey('comments', $payload);
        $this->assertStringContainsString('alisadikinma.com', $payload['comments'][0]['text']);
        $this->assertSame(30, $payload['comments'][0]['delay']['duration']);
    }

    // ─── Facebook ──────────────────────────────────────────────────────────────

    public function test_facebook_carousel_payload_has_slides_and_no_comments(): void
    {
        $this->setSetting('publer_facebook_account_id', 'fb_acc_101');

        $linkedinPost = $this->makeLinkedInPost(['format' => 'carousel']);

        $sibling = FacebookPost::create([
            'linkedin_post_id' => $linkedinPost->id,
            'post_id' => $linkedinPost->post_id,
            'status' => 'awaiting_review',
            'format' => 'carousel',
            'caption' => 'Caption Facebook carousel.',
            'hashtags' => ['#AI', '#Productivity'],
            'scheduled_at' => '2026-05-15 17:00:00',
        ]);
        $sibling->load('linkedinPost');

        $builder = new PublerPayloadBuilder();
        $payload = $builder->buildFacebook($sibling);

        // accounts[]
        $this->assertSame('fb_acc_101', $payload['accounts'][0]['id']);

        // caption
        $this->assertStringContainsString('Caption Facebook carousel.', $payload['caption']);

        // media[] from carousel_slides (carousel format)
        $this->assertArrayHasKey('media', $payload);
        $this->assertCount(3, $payload['media']);

        // NO comments[] for Facebook (May 10 cleanup decision)
        $this->assertArrayNotHasKey('comments', $payload);
    }

    // ─── Missing account ID ────────────────────────────────────────────────────

    public function test_throws_runtime_exception_when_account_id_not_configured(): void
    {
        // No setting seeded for instagram
        $linkedinPost = $this->makeLinkedInPost();
        $sibling = InstagramPost::create([
            'linkedin_post_id' => $linkedinPost->id,
            'post_id' => $linkedinPost->post_id,
            'status' => 'awaiting_review',
            'caption' => 'Test caption.',
        ]);
        $sibling->load('linkedinPost');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/instagram/i');

        $builder = new PublerPayloadBuilder();
        $builder->buildInstagram($sibling);
    }
}
