<?php

namespace Tests\Unit;

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
 * PublerPayloadBuilder normalized-spec construction.
 *
 * REWRITTEN June 10, 2026 — the builder no longer emits the final flat Publer
 * payload. It emits a normalized "spec" the PublishViaPubler job turns into the
 * live bulk envelope after pre-uploading media:
 *   [ platform, network, account_id, network_fields{type,text,...}, media_urls[], comments[] ]
 *
 * Verifies: account_id from settings, caption+hashtags in network_fields.text,
 * media_urls from carousel_slides, comments only for IG+Threads (delay 1 min),
 * TikTok title passthrough, per-platform enabled gate, missing-account throw.
 */
class PublerPayloadBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(): Post
    {
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

    public function test_instagram_spec_carries_caption_hashtags_link_comment_and_media_urls(): void
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

        $spec = (new PublerPayloadBuilder())->buildInstagram($sibling);

        $this->assertSame('instagram', $spec['platform']);
        $this->assertSame('instagram', $spec['network']);
        $this->assertSame('ig_acc_123', $spec['account_id']);

        // Multi-image → type photo; caption carries body + hashtags
        $this->assertSame('photo', $spec['network_fields']['type']);
        $this->assertStringContainsString('Ini caption Instagram.', $spec['network_fields']['text']);
        $this->assertStringContainsString('#AI', $spec['network_fields']['text']);
        $this->assertStringContainsString('#VibeCoding', $spec['network_fields']['text']);

        // media_urls from carousel_slides (ordered, raw URLs — uploaded later)
        $this->assertCount(3, $spec['media_urls']);
        $this->assertSame('https://cdn.test/slide-01.png', $spec['media_urls'][0]);
        $this->assertSame('https://cdn.test/slide-03.png', $spec['media_urls'][2]);

        // comments[] with link_comment + 1-minute delay
        $this->assertCount(1, $spec['comments']);
        $this->assertStringContainsString('https://alisadikinma.com/blog/test-post', $spec['comments'][0]['text']);
        $this->assertSame(1, $spec['comments'][0]['delay']['duration']);
        $this->assertSame('minute', $spec['comments'][0]['delay']['unit']);
    }

    // ─── TikTok ────────────────────────────────────────────────────────────────

    public function test_tiktok_spec_has_url_in_caption_title_and_no_comments(): void
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

        $spec = (new PublerPayloadBuilder())->buildTiktok($sibling);

        $this->assertSame('tt_acc_456', $spec['account_id']);
        $this->assertStringContainsString('https://ali.me/r/abc1234', $spec['network_fields']['text']);
        $this->assertSame('TikTok title here', $spec['network_fields']['title']);
        $this->assertCount(3, $spec['media_urls']);

        // NO comments[] for TikTok (no first-comment API)
        $this->assertEmpty($spec['comments']);
    }

    // ─── Threads ───────────────────────────────────────────────────────────────

    public function test_threads_spec_carries_caption_hashtags_link_comment_and_media(): void
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

        $spec = (new PublerPayloadBuilder())->buildThreads($sibling);

        $this->assertSame('th_acc_789', $spec['account_id']);
        $this->assertStringContainsString('Ini caption Threads singkat.', $spec['network_fields']['text']);
        $this->assertStringContainsString('#Automation', $spec['network_fields']['text']);
        $this->assertCount(3, $spec['media_urls']);

        $this->assertCount(1, $spec['comments']);
        $this->assertStringContainsString('alisadikinma.com', $spec['comments'][0]['text']);
        $this->assertSame(1, $spec['comments'][0]['delay']['duration']);
    }

    // ─── Facebook ──────────────────────────────────────────────────────────────

    public function test_facebook_carousel_spec_has_media_and_no_comments(): void
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

        $spec = (new PublerPayloadBuilder())->buildFacebook($sibling);

        $this->assertSame('fb_acc_101', $spec['account_id']);
        $this->assertStringContainsString('Caption Facebook carousel.', $spec['network_fields']['text']);
        $this->assertCount(3, $spec['media_urls']);
        $this->assertEmpty($spec['comments']);
    }

    // ─── Per-platform enabled gate ──────────────────────────────────────────────

    public function test_is_platform_enabled_reflects_account_setting(): void
    {
        $this->assertFalse(PublerPayloadBuilder::isPlatformEnabled('threads'));

        $this->setSetting('publer_threads_account_id', 'th_acc_789');
        $this->assertTrue(PublerPayloadBuilder::isPlatformEnabled('threads'));

        // Whitespace-only setting still counts as disabled
        $this->setSetting('publer_instagram_account_id', '   ');
        $this->assertFalse(PublerPayloadBuilder::isPlatformEnabled('instagram'));
    }

    // ─── Missing account ID ────────────────────────────────────────────────────

    public function test_throws_runtime_exception_when_account_id_not_configured(): void
    {
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

        (new PublerPayloadBuilder())->buildInstagram($sibling);
    }
}
