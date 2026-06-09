<?php

namespace Tests\Unit;

use App\Jobs\PublishViaPubler;
use App\Models\Category;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\Setting;
use App\Services\PublerClient;
use App\Services\PublerPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * TDD — PublishViaPubler real implementation for Instagram (P4, 2026-05-13).
 *
 * Tests the job's handle() method directly (dispatchSync pattern — avoids
 * queue infrastructure for unit tests). Mocks HTTP via Http::fake() so
 * no real Publer calls are made.
 */
class PublishViaPublerInstagramTest extends TestCase
{
    use RefreshDatabase;

    private function makeSibling(): InstagramPost
    {
        $category = Category::create(['name' => 'AI', 'slug' => 'ai-ig-' . uniqid()]);
        $post = Post::create([
            'category_id' => $category->id,
            'slug' => 'test-post-ig-' . uniqid(),
            'title' => 'Test Post IG',
            'content' => 'content',
        ]);

        $slides = [
            ['slide_number' => 1, 'image_url' => 'https://cdn.test/s1.png', 'is_cover' => true, 'is_cta' => false],
            ['slide_number' => 2, 'image_url' => 'https://cdn.test/s2.png', 'is_cover' => false, 'is_cta' => true],
        ];

        $linkedinPost = LinkedInPost::create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'content' => 'LinkedIn content',
            'carousel_slides' => $slides,
            'hashtags' => ['#AI'],
            'status' => 'awaiting_publish',
            'pipeline_state_log' => [],
        ]);

        // Use 'awaiting_review' — valid in SQLite CHECK for instagram_posts.
        // (The ENUM rename migration is MySQL-only; SQLite keeps old CHECK values.)
        return InstagramPost::create([
            'linkedin_post_id' => $linkedinPost->id,
            'post_id' => $post->id,
            'status' => 'awaiting_review',
            'caption' => 'Instagram caption here.',
            'hashtags' => ['#AI', '#Tech'],
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
        // PublerClient resolves api_key from settings group=publer, key=publer_api_key
        // For tests we inject directly via constructor override — but we test via
        // handle() with app-resolved instances. Use Http::fake() to bypass real HTTP.
        // We also need the api_key setting to exist so PublerClient::resolveApiKey passes.
        Setting::create([
            'group' => 'publer',
            'key' => 'publer_api_key',
            'value' => \Illuminate\Support\Facades\Crypt::encryptString('test-api-key-123'),
        ]);
    }

    // ─── Happy path ────────────────────────────────────────────────────────────

    public function test_happy_path_persists_publer_post_id_and_advances_to_published(): void
    {
        $this->setSetting('publer_instagram_account_id', 'ig_acc_test');
        $this->setPublerApiKey();

        // New flow: pre-upload each media URL → poll media job → publish → poll
        // publish job. Distinct job ids let us fake distinct /job_status URLs.
        Http::fake([
            '*/media/from-url' => Http::response(['job_id' => 'mjob'], 200),
            '*/job_status/mjob' => Http::response(['status' => 'complete', 'payload' => [['id' => 'media_1']]], 200),
            '*/posts/schedule/publish' => Http::response(['job_id' => 'pjob'], 200),
            '*/job_status/pjob' => Http::response(['status' => 'complete', 'payload' => ['failures' => []]], 200),
        ]);

        $sibling = $this->makeSibling();

        $job = new PublishViaPubler('instagram', $sibling->id);
        $job->handle(app(PublerClient::class), app(PublerPayloadBuilder::class));

        $fresh = $sibling->fresh();
        // No post id in the success payload → publer_post_id falls back to job id
        $this->assertSame('pjob', $fresh->publer_post_id);
        $this->assertSame('published', $fresh->status);
        $this->assertNotNull($fresh->published_at);
    }

    // ─── 4xx → status=failed ──────────────────────────────────────────────────

    public function test_4xx_response_from_publer_marks_draft_as_failed(): void
    {
        $this->setSetting('publer_instagram_account_id', 'ig_acc_test');
        $this->setPublerApiKey();

        // Media uploads succeed; the publish endpoint 4xx's → permanent failure.
        Http::fake([
            '*/media/from-url' => Http::response(['job_id' => 'mjob'], 200),
            '*/job_status/mjob' => Http::response(['status' => 'complete', 'payload' => [['id' => 'media_1']]], 200),
            '*/posts/schedule/publish' => Http::response([
                'success' => false,
                'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid caption length'],
            ], 422),
        ]);

        $sibling = $this->makeSibling();

        $job = new PublishViaPubler('instagram', $sibling->id);
        $job->handle(app(PublerClient::class), app(PublerPayloadBuilder::class));

        $fresh = $sibling->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertNotEmpty($fresh->last_error);
    }

    // ─── Idempotency ──────────────────────────────────────────────────────────

    public function test_skips_when_publer_post_id_already_set(): void
    {
        $this->setSetting('publer_instagram_account_id', 'ig_acc_test');
        $this->setPublerApiKey();

        // No HTTP fake — if handle() makes an HTTP call when already published,
        // Http will throw (no matching fake). Proving idempotency = no HTTP call.
        Http::fake([]);

        $sibling = $this->makeSibling();
        // Simulate already-published state
        $sibling->update(['publer_post_id' => 'publer_existing_id', 'status' => 'published']);

        $job = new PublishViaPubler('instagram', $sibling->id);
        $job->handle(app(PublerClient::class), app(PublerPayloadBuilder::class));

        // Should still be published with the original publer_post_id
        $fresh = $sibling->fresh();
        $this->assertSame('publer_existing_id', $fresh->publer_post_id);
        $this->assertSame('published', $fresh->status);
    }
}
