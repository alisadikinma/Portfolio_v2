<?php

namespace Tests\Unit;

use App\Jobs\PublishViaPubler;
use App\Models\Category;
use App\Models\FacebookPost;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\Setting;
use App\Services\PublerClient;
use App\Services\PublerPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * TDD — PublishViaPubler real implementation for Facebook (P4, 2026-05-13).
 */
class PublishViaPublerFacebookTest extends TestCase
{
    use RefreshDatabase;

    private function makeSibling(): FacebookPost
    {
        $category = Category::create(['name' => 'AI', 'slug' => 'ai-fb-' . uniqid()]);
        $post = Post::create([
            'category_id' => $category->id,
            'slug' => 'test-post-fb-' . uniqid(),
            'title' => 'Test Post Facebook',
            'content' => 'content',
        ]);

        $slides = [
            ['slide_number' => 1, 'image_url' => 'https://cdn.test/fb-s1.png', 'is_cover' => true, 'is_cta' => false],
            ['slide_number' => 2, 'image_url' => 'https://cdn.test/fb-s2.png', 'is_cover' => false, 'is_cta' => true],
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

        return FacebookPost::create([
            'linkedin_post_id' => $linkedinPost->id,
            'post_id' => $post->id,
            'format' => 'carousel',
            'status' => 'publishing',
            'caption' => 'Facebook caption here.',
            'hashtags' => ['#AI', '#Tech'],
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
            'value' => Crypt::encryptString('test-api-key-facebook'),
        ]);
    }

    // ─── Happy path ────────────────────────────────────────────────────────────

    public function test_happy_path_persists_publer_post_id_and_advances_to_published(): void
    {
        $this->setSetting('publer_facebook_account_id', 'fb_acc_test');
        $this->setPublerApiKey();

        Http::fake([
            '*/posts/schedule' => Http::response([
                'success' => true,
                'data' => ['job_id' => 'job_facebook_101'],
            ], 200),
        ]);

        $sibling = $this->makeSibling();

        $job = new PublishViaPubler('facebook', $sibling->id);
        $job->handle(app(PublerClient::class), app(PublerPayloadBuilder::class));

        $fresh = $sibling->fresh();
        $this->assertSame('job_facebook_101', $fresh->publer_post_id);
        $this->assertSame('published', $fresh->status);
        $this->assertNotNull($fresh->published_at);
    }

    // ─── 4xx → failed ─────────────────────────────────────────────────────────

    public function test_4xx_response_marks_draft_as_failed(): void
    {
        $this->setSetting('publer_facebook_account_id', 'fb_acc_test');
        $this->setPublerApiKey();

        Http::fake([
            '*/posts/schedule' => Http::response([
                'success' => false,
                'error' => 'Facebook page disconnected',
            ], 400),
        ]);

        $sibling = $this->makeSibling();

        $job = new PublishViaPubler('facebook', $sibling->id);
        $job->handle(app(PublerClient::class), app(PublerPayloadBuilder::class));

        $fresh = $sibling->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertNotEmpty($fresh->last_error);
    }

    // ─── Idempotency ──────────────────────────────────────────────────────────

    public function test_skips_when_publer_post_id_already_set(): void
    {
        $this->setSetting('publer_facebook_account_id', 'fb_acc_test');
        $this->setPublerApiKey();

        Http::fake([]);  // No real calls expected

        $sibling = $this->makeSibling();
        // Use DB::table to bypass SQLite's legacy CHECK constraint
        \Illuminate\Support\Facades\DB::table('facebook_posts')
            ->where('id', $sibling->id)
            ->update(['publer_post_id' => 'facebook_already_published', 'status' => 'published']);

        $job = new PublishViaPubler('facebook', $sibling->id);
        $job->handle(app(PublerClient::class), app(PublerPayloadBuilder::class));

        $fresh = $sibling->fresh();
        $this->assertSame('facebook_already_published', $fresh->publer_post_id);
        $this->assertSame('published', $fresh->status);
    }
}
