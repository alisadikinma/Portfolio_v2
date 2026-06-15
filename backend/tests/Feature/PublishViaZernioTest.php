<?php

namespace Tests\Feature;

use App\Jobs\PublishViaZernio;
use App\Models\Category;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase F — PublishViaZernio: publishNow + scheduledFor, stable x-request-id,
 * 409-as-published, 4xx-fail, 5xx-retry, idempotency.
 */
class PublishViaZernioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://alisadikinma.com']);
        config(['social-cross-post.zernio.enabled' => true]); // master switch on for publish tests
        Setting::create(['group' => 'zernio', 'key' => 'zernio_api_key_igtt', 'value' => Crypt::encryptString('sk_igtt')]);
        Setting::create(['group' => 'zernio', 'key' => 'zernio_instagram_account_id', 'value' => 'ig_acc']);
    }

    private function igSibling(array $attrs = []): InstagramPost
    {
        $category = Category::create(['name' => 'AI', 'slug' => 'ai-'.uniqid()]);
        $post = Post::create(['category_id' => $category->id, 'slug' => 'p-'.uniqid(), 'title' => 'T', 'content' => 'B']);
        $li = LinkedInPost::create([
            'post_id' => $post->id, 'format' => 'carousel', 'content' => 'c',
            'carousel_slides' => [
                ['slide_number' => 1, 'image_url' => 'https://alisadikinma.com/storage/linkedin-carousel/s1.png'],
                ['slide_number' => 2, 'image_url' => 'https://alisadikinma.com/storage/linkedin-carousel/s2.png'],
            ],
            'hashtags' => [], 'status' => 'awaiting_publish', 'pipeline_state_log' => [],
        ]);

        return InstagramPost::create(array_merge([
            'linkedin_post_id' => $li->id, 'post_id' => $post->id,
            'status' => 'awaiting_review', 'caption' => 'cap', 'hashtags' => [],
        ], $attrs));
    }

    public function test_publishes_now_and_stores_post_id_and_url(): void
    {
        Http::fake(['zernio.com/api/v1/posts' => Http::response([
            'post' => ['_id' => 'z-1', 'platforms' => [['platformPostUrl' => 'https://instagram.com/p/abc']]],
        ], 201)]);

        $ig = $this->igSibling();
        PublishViaZernio::dispatchSync('instagram', $ig->id);

        $this->assertDatabaseHas('instagram_posts', [
            'id' => $ig->id, 'status' => 'published',
            'zernio_post_id' => 'z-1', 'external_url' => 'https://instagram.com/p/abc',
        ]);
        Http::assertSent(fn ($r) => isset($r['publishNow']) && $r['publishNow'] === true);
    }

    public function test_scheduled_uses_scheduled_for_not_publish_now(): void
    {
        config(['social-cross-post.zernio.schedule_enabled' => true]);
        Http::fake(['zernio.com/api/v1/posts' => Http::response(['post' => ['_id' => 'z-2']], 201)]);

        $ig = $this->igSibling(['scheduled_at' => now()->addDay()]);
        PublishViaZernio::dispatchSync('instagram', $ig->id);

        Http::assertSent(fn ($r) => isset($r['scheduledFor']) && ! ($r['publishNow'] ?? false));
    }

    public function test_past_scheduled_at_publishes_now_not_scheduled(): void
    {
        // Slot-orchestrator model: the local cron holds the post until its slot,
        // then fires this job — by which point scheduled_at is in the PAST. A
        // past scheduledFor would be rejected by Zernio, so applyScheduling must
        // treat a non-future scheduled_at as publishNow.
        config(['social-cross-post.zernio.schedule_enabled' => true]);
        Http::fake(['zernio.com/api/v1/posts' => Http::response(['post' => ['_id' => 'z-3']], 201)]);

        $ig = $this->igSibling(['scheduled_at' => now()->subMinutes(5)]);
        PublishViaZernio::dispatchSync('instagram', $ig->id);

        Http::assertSent(fn ($r) => ($r['publishNow'] ?? false) === true && ! isset($r['scheduledFor']));
    }

    public function test_409_duplicate_marks_published_with_existing_id(): void
    {
        Http::fake(['zernio.com/api/v1/posts' => Http::response([
            'error' => 'duplicate', 'details' => ['existingPostId' => 'z-existing'],
        ], 409)]);

        $ig = $this->igSibling();
        PublishViaZernio::dispatchSync('instagram', $ig->id);

        $this->assertDatabaseHas('instagram_posts', [
            'id' => $ig->id, 'status' => 'published', 'zernio_post_id' => 'z-existing',
        ]);
    }

    public function test_4xx_marks_failed(): void
    {
        Http::fake(['zernio.com/api/v1/posts' => Http::response(['error' => 'bad request'], 400)]);

        $ig = $this->igSibling();
        PublishViaZernio::dispatchSync('instagram', $ig->id);

        $this->assertDatabaseHas('instagram_posts', [
            'id' => $ig->id, 'status' => 'failed', 'zernio_post_id' => null,
        ]);
        $this->assertNotNull(
            DB::table('instagram_posts')->where('id', $ig->id)->value('last_error')
        );
    }

    public function test_5xx_rethrows_for_retry(): void
    {
        Http::fake(['zernio.com/api/v1/posts' => Http::response(['error' => 'server'], 500)]);

        $ig = $this->igSibling();
        $this->expectException(\App\Exceptions\ZernioApiException::class);
        PublishViaZernio::dispatchSync('instagram', $ig->id);
    }

    public function test_idempotent_skip_when_already_published(): void
    {
        Http::fake();
        $ig = $this->igSibling(['zernio_post_id' => 'already-z']);

        PublishViaZernio::dispatchSync('instagram', $ig->id);

        Http::assertNothingSent();
        $this->assertSame('already-z', InstagramPost::find($ig->id)->zernio_post_id);
    }
}
