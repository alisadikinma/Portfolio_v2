<?php

namespace Tests\Feature;

use App\Jobs\PublishViaPubler;
use App\Models\Category;
use App\Models\FacebookPost;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\Setting;
use App\Models\ThreadsPost;
use App\Models\TiktokPost;
use App\Services\PublerClient;
use App\Services\PublerPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * End-to-end integration test for PublishViaPubler (P4, 2026-05-13).
 *
 * Verifies that dispatching the job for all 4 platforms with a faked
 * Publer HTTP response results in every cross-post sibling updated
 * with a publer_post_id and status='published'.
 *
 * This is a Feature test (rather than Unit) because it exercises the
 * full app container resolution path (PublerClient + PublerPayloadBuilder
 * bound via app(), migration state via RefreshDatabase, Http::fake for
 * real HTTP mock, etc.).
 */
class PublishViaPublerEndToEndTest extends TestCase
{
    use RefreshDatabase;

    // ─── Fixtures ─────────────────────────────────────────────────────────────

    private Post $post;
    private LinkedInPost $linkedinPost;

    protected function setUp(): void
    {
        parent::setUp();

        $category = Category::create(['name' => 'AI', 'slug' => 'ai-e2e-' . uniqid()]);

        $this->post = Post::create([
            'category_id' => $category->id,
            'slug' => 'test-e2e-' . uniqid(),
            'title' => 'End-to-End Test Post',
            'content' => 'test content',
        ]);

        $slides = [
            ['slide_number' => 1, 'image_url' => 'https://cdn.test/e2e-s1.png', 'is_cover' => true, 'is_cta' => false],
            ['slide_number' => 2, 'image_url' => 'https://cdn.test/e2e-s2.png', 'is_cover' => false, 'is_cta' => false],
            ['slide_number' => 3, 'image_url' => 'https://cdn.test/e2e-s3.png', 'is_cover' => false, 'is_cta' => true],
        ];

        $this->linkedinPost = LinkedInPost::create([
            'post_id' => $this->post->id,
            'format' => 'carousel',
            'content' => 'LinkedIn caption text',
            'carousel_slides' => $slides,
            'hashtags' => ['#AI', '#VibeCoding'],
            'status' => 'awaiting_publish',
            'pipeline_state_log' => [],
        ]);

        // Seed Publer settings for all 4 platforms.
        $this->seedPublerSettings();
    }

    private function seedPublerSettings(): void
    {
        $apiKey = Crypt::encryptString('test-publer-key-e2e');

        $settings = [
            ['group' => 'publer', 'key' => 'publer_api_key',               'value' => $apiKey],
            ['group' => 'publer', 'key' => 'publer_instagram_account_id',  'value' => 'ig_e2e_acc'],
            ['group' => 'publer', 'key' => 'publer_tiktok_account_id',     'value' => 'tt_e2e_acc'],
            ['group' => 'publer', 'key' => 'publer_threads_account_id',    'value' => 'th_e2e_acc'],
            ['group' => 'publer', 'key' => 'publer_facebook_account_id',   'value' => 'fb_e2e_acc'],
        ];

        foreach ($settings as $row) {
            Setting::create($row);
        }
    }

    private function makeInstagram(): InstagramPost
    {
        return InstagramPost::create([
            'linkedin_post_id' => $this->linkedinPost->id,
            'post_id' => $this->post->id,
            'status' => 'awaiting_review',
            'caption' => 'Instagram caption for E2E test.',
            'hashtags' => ['#AI', '#VibeCoding'],
            'link_comment' => 'Full article: https://alisadikinma.com/blog/e2e-test',
            'scheduled_at' => now()->addHour(),
        ]);
    }

    private function makeTiktok(): TiktokPost
    {
        return TiktokPost::create([
            'linkedin_post_id' => $this->linkedinPost->id,
            'post_id' => $this->post->id,
            'status' => 'awaiting_review',
            'title' => 'E2E TikTok Title',
            'caption' => 'TikTok caption with URL https://alisadikinma.com/r/abc123',
            'hashtags' => ['#AI', '#TechTok'],
            'link_comment' => 'Full article: https://alisadikinma.com/blog/e2e-test',
            'scheduled_at' => now()->addHour(),
        ]);
    }

    private function makeThreads(): ThreadsPost
    {
        return ThreadsPost::create([
            'linkedin_post_id' => $this->linkedinPost->id,
            'post_id' => $this->post->id,
            'status' => 'awaiting_review',
            'caption' => 'Threads caption for E2E test.',
            'hashtags' => ['#AI'],
            'link_comment' => 'Full article: https://alisadikinma.com/blog/e2e-test',
            'scheduled_at' => now()->addHour(),
        ]);
    }

    private function makeFacebook(): FacebookPost
    {
        return FacebookPost::create([
            'linkedin_post_id' => $this->linkedinPost->id,
            'post_id' => $this->post->id,
            'format' => 'carousel',
            'status' => 'publishing',
            'caption' => 'Facebook caption for E2E test.',
            'hashtags' => ['#AI'],
            'scheduled_at' => now()->addHour(),
        ]);
    }

    // ─── Tests ────────────────────────────────────────────────────────────────

    /**
     * Dispatching PublishViaPubler for all 4 platforms with a mocked Publer
     * response updates every sibling row with a unique publer_post_id and
     * advances status to 'published'.
     */
    public function test_dispatching_4_platforms_at_slot_publishes_all_via_publer_mock(): void
    {
        // Mock Publer HTTP — each call returns a distinct job_id.
        $callCount = 0;
        Http::fake(function () use (&$callCount) {
            $callCount++;
            return Http::response([
                'success' => true,
                'data' => ['job_id' => "job_e2e_{$callCount}"],
            ], 200);
        });

        $instagram = $this->makeInstagram();
        $tiktok    = $this->makeTiktok();
        $threads   = $this->makeThreads();
        $facebook  = $this->makeFacebook();

        // Dispatch all 4 jobs synchronously.
        $client  = app(PublerClient::class);
        $builder = app(PublerPayloadBuilder::class);

        foreach ([
            ['instagram', $instagram],
            ['tiktok',    $tiktok],
            ['threads',   $threads],
            ['facebook',  $facebook],
        ] as [$platform, $sibling]) {
            $job = new PublishViaPubler($platform, $sibling->id);
            $job->handle($client, $builder);
        }

        // All 4 Publer API calls were made.
        $this->assertSame(4, $callCount, 'Expected exactly 4 Publer API calls');

        // All siblings now have publer_post_id set and status='published'.
        foreach ([$instagram, $tiktok, $threads, $facebook] as $sibling) {
            $fresh = $sibling->fresh();
            $this->assertNotNull($fresh->publer_post_id, "publer_post_id must be set for {$sibling->getTable()}");
            $this->assertSame('published', $fresh->status, "status must be 'published' for {$sibling->getTable()}");
            $this->assertNotNull($fresh->published_at, "published_at must be set for {$sibling->getTable()}");
        }

        // publer_post_ids are all distinct (unique job_ids per platform).
        $ids = array_map(
            fn ($s) => $s->fresh()->publer_post_id,
            [$instagram, $tiktok, $threads, $facebook]
        );
        $this->assertCount(4, array_unique($ids), 'Each platform must have a distinct publer_post_id');
    }

    /**
     * Verify that each platform's payload was built correctly:
     * - Instagram: has comments[] with link_comment
     * - TikTok: has title field, NO comments[]
     * - Threads: has comments[] with link_comment
     * - Facebook: NO comments[], has media[] for carousel
     */
    public function test_publer_requests_carry_correct_per_platform_payload(): void
    {
        $capturedRequests = [];

        Http::fake(function ($request) use (&$capturedRequests) {
            $capturedRequests[] = $request->data();
            return Http::response([
                'success' => true,
                'data' => ['job_id' => 'job_payload_check_' . count($capturedRequests)],
            ], 200);
        });

        $instagram = $this->makeInstagram();
        $tiktok    = $this->makeTiktok();
        $threads   = $this->makeThreads();
        $facebook  = $this->makeFacebook();

        $client  = app(PublerClient::class);
        $builder = app(PublerPayloadBuilder::class);

        foreach ([
            ['instagram', $instagram],
            ['tiktok',    $tiktok],
            ['threads',   $threads],
            ['facebook',  $facebook],
        ] as [$platform, $sibling]) {
            (new PublishViaPubler($platform, $sibling->id))->handle($client, $builder);
        }

        $this->assertCount(4, $capturedRequests);

        [$igPayload, $ttPayload, $thPayload, $fbPayload] = $capturedRequests;

        // Instagram: must have comments[].
        $this->assertArrayHasKey('comments', $igPayload, 'Instagram payload must include comments[]');
        $this->assertNotEmpty($igPayload['comments']);
        $this->assertStringContainsString('https://', $igPayload['comments'][0]['text'] ?? '');

        // TikTok: must have title, must NOT have comments[].
        $this->assertArrayHasKey('title', $ttPayload, 'TikTok payload must include title');
        $this->assertArrayNotHasKey('comments', $ttPayload, 'TikTok payload must NOT include comments[]');

        // Threads: must have comments[].
        $this->assertArrayHasKey('comments', $thPayload, 'Threads payload must include comments[]');
        $this->assertNotEmpty($thPayload['comments']);

        // Facebook: must NOT have comments[], must have media[] (carousel).
        $this->assertArrayNotHasKey('comments', $fbPayload, 'Facebook payload must NOT include comments[]');
        $this->assertArrayHasKey('media', $fbPayload, 'Facebook carousel payload must include media[]');
        $this->assertNotEmpty($fbPayload['media']);
    }

    /**
     * When a sibling is not found (e.g., deleted between dispatch and execution),
     * the job skips gracefully without throwing.
     */
    public function test_skips_gracefully_when_sibling_not_found(): void
    {
        Http::fake([]); // No HTTP calls expected.

        $job = new PublishViaPubler('instagram', 99999);
        // Should not throw:
        $job->handle(app(PublerClient::class), app(PublerPayloadBuilder::class));

        $this->assertTrue(true, 'handle() completed without exception for missing sibling');
    }
}
