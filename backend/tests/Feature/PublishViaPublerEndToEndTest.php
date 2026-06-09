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
        // Mock the full Publer flow: media upload → poll → publish → poll.
        // Each platform's publish returns a distinct job_id (pjob_1..4).
        $publishCount = 0;
        Http::fake(function ($request) use (&$publishCount) {
            $url = $request->url();
            if (str_contains($url, '/media/from-url')) {
                return Http::response(['job_id' => 'mjob'], 200);
            }
            if (str_contains($url, '/posts/schedule/publish')) {
                $publishCount++;
                return Http::response(['job_id' => "pjob_{$publishCount}"], 200);
            }
            if (str_contains($url, '/job_status/')) {
                return str_contains($url, 'mjob')
                    ? Http::response(['status' => 'complete', 'payload' => [['id' => 'media_x']]], 200)
                    : Http::response(['status' => 'complete', 'payload' => ['failures' => []]], 200);
            }
            return Http::response([], 200);
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

        // All 4 platforms ran their publish job.
        $this->assertSame(4, $publishCount, 'Expected exactly 4 Publer publish calls');

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
        // Capture ONLY the publish request bodies (the assembled bulk envelope);
        // media uploads + polls are mocked through.
        $publishBodies = [];

        Http::fake(function ($request) use (&$publishBodies) {
            $url = $request->url();
            if (str_contains($url, '/media/from-url')) {
                return Http::response(['job_id' => 'mjob'], 200);
            }
            if (str_contains($url, '/posts/schedule/publish')) {
                $publishBodies[] = $request->data();
                return Http::response(['job_id' => 'pjob_' . count($publishBodies)], 200);
            }
            if (str_contains($url, '/job_status/')) {
                return str_contains($url, 'mjob')
                    ? Http::response(['status' => 'complete', 'payload' => [['id' => 'media_x']]], 200)
                    : Http::response(['status' => 'complete', 'payload' => ['failures' => []]], 200);
            }
            return Http::response([], 200);
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

        $this->assertCount(4, $publishBodies);

        // Each body is { bulk: { state, posts: [ <post> ] } } — assert on the post.
        [$ig, $tt, $th, $fb] = array_map(fn ($b) => $b['bulk']['posts'][0], $publishBodies);

        // Instagram: comments[] at post level, with the blog link.
        $this->assertArrayHasKey('comments', $ig, 'Instagram post must include comments[]');
        $this->assertNotEmpty($ig['comments']);
        $this->assertStringContainsString('https://', $ig['comments'][0]['text'] ?? '');

        // TikTok: title under networks.tiktok, NO comments[].
        $this->assertArrayHasKey('title', $tt['networks']['tiktok'], 'TikTok network must include title');
        $this->assertArrayNotHasKey('comments', $tt, 'TikTok post must NOT include comments[]');

        // Threads: comments[] at post level.
        $this->assertArrayHasKey('comments', $th, 'Threads post must include comments[]');
        $this->assertNotEmpty($th['comments']);

        // Facebook: NO comments[], media[] under networks.facebook (carousel).
        $this->assertArrayNotHasKey('comments', $fb, 'Facebook post must NOT include comments[]');
        $this->assertArrayHasKey('media', $fb['networks']['facebook'], 'Facebook carousel must include media[]');
        $this->assertNotEmpty($fb['networks']['facebook']['media']);
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
