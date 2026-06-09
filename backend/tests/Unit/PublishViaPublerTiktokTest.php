<?php

namespace Tests\Unit;

use App\Jobs\PublishViaPubler;
use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\Setting;
use App\Models\TiktokPost;
use App\Services\PublerClient;
use App\Services\PublerPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * TDD — PublishViaPubler real implementation for TikTok (P4, 2026-05-13).
 */
class PublishViaPublerTiktokTest extends TestCase
{
    use RefreshDatabase;

    private function makeSibling(): TiktokPost
    {
        $category = Category::create(['name' => 'AI', 'slug' => 'ai-tt-' . uniqid()]);
        $post = Post::create([
            'category_id' => $category->id,
            'slug' => 'test-post-tt-' . uniqid(),
            'title' => 'Test Post TikTok',
            'content' => 'content',
        ]);

        $slides = [
            ['slide_number' => 1, 'image_url' => 'https://cdn.test/tt-s1.png', 'is_cover' => true, 'is_cta' => false],
            ['slide_number' => 2, 'image_url' => 'https://cdn.test/tt-s2.png', 'is_cover' => false, 'is_cta' => true],
        ];

        $linkedinPost = LinkedInPost::create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'content' => 'LinkedIn content',
            'carousel_slides' => $slides,
            'hashtags' => ['#AI'],
            'status' => 'published',
            'pipeline_state_log' => [],
        ]);

        return TiktokPost::create([
            'linkedin_post_id' => $linkedinPost->id,
            'post_id' => $post->id,
            'status' => 'publishing',
            'title' => 'TikTok title',
            'caption' => 'Caption TikTok with URL https://ali.me/r/xyz',
            'hashtags' => ['#AI', '#TechTok'],
            'link_comment' => 'Full article: https://alisadikinma.com/blog/test-post',
            'scheduled_at' => now()->addHour(),
        ]);
    }

    private function setSetting(string $key, string $value): void
    {
        Setting::create(['group' => 'publer', 'key' => $key, 'value' => $value]);
    }

    private function setPublerApiKey(): void
    {
        Setting::create([
            'group' => 'publer',
            'key' => 'publer_api_key',
            'value' => Crypt::encryptString('test-api-key-tiktok'),
        ]);
    }

    // ─── Happy path ────────────────────────────────────────────────────────────

    public function test_happy_path_persists_publer_post_id_and_advances_to_published(): void
    {
        $this->setSetting('publer_tiktok_account_id', 'tt_acc_test');
        $this->setPublerApiKey();

        Http::fake([
            '*/media/from-url' => Http::response(['job_id' => 'mjob'], 200),
            '*/job_status/mjob' => Http::response(['status' => 'complete', 'payload' => [['id' => 'media_1']]], 200),
            '*/posts/schedule/publish' => Http::response(['job_id' => 'pjob'], 200),
            '*/job_status/pjob' => Http::response(['status' => 'complete', 'payload' => ['failures' => []]], 200),
        ]);

        $sibling = $this->makeSibling();

        $job = new PublishViaPubler('tiktok', $sibling->id);
        $job->handle(app(PublerClient::class), app(PublerPayloadBuilder::class));

        $fresh = $sibling->fresh();
        $this->assertSame('pjob', $fresh->publer_post_id);
        $this->assertSame('published', $fresh->status);
        $this->assertNotNull($fresh->published_at);
    }

    // ─── 4xx → failed ─────────────────────────────────────────────────────────

    public function test_4xx_response_marks_draft_as_failed(): void
    {
        $this->setSetting('publer_tiktok_account_id', 'tt_acc_test');
        $this->setPublerApiKey();

        Http::fake([
            '*/media/from-url' => Http::response(['job_id' => 'mjob'], 200),
            '*/job_status/mjob' => Http::response(['status' => 'complete', 'payload' => [['id' => 'media_1']]], 200),
            '*/posts/schedule/publish' => Http::response([
                'success' => false,
                'error' => 'TikTok account disconnected',
            ], 400),
        ]);

        $sibling = $this->makeSibling();

        $job = new PublishViaPubler('tiktok', $sibling->id);
        $job->handle(app(PublerClient::class), app(PublerPayloadBuilder::class));

        $fresh = $sibling->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertNotEmpty($fresh->last_error);
    }

    // ─── Idempotency ──────────────────────────────────────────────────────────

    public function test_skips_when_publer_post_id_already_set(): void
    {
        $this->setSetting('publer_tiktok_account_id', 'tt_acc_test');
        $this->setPublerApiKey();

        Http::fake([]);  // No real calls expected

        $sibling = $this->makeSibling();
        $sibling->update(['publer_post_id' => 'tiktok_already_published', 'status' => 'published']);

        $job = new PublishViaPubler('tiktok', $sibling->id);
        $job->handle(app(PublerClient::class), app(PublerPayloadBuilder::class));

        $fresh = $sibling->fresh();
        $this->assertSame('tiktok_already_published', $fresh->publer_post_id);
        $this->assertSame('published', $fresh->status);
    }
}
