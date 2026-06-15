<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\Setting;
use App\Models\ThreadsPost;
use App\Models\TiktokPost;
use App\Services\ZernioPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase E — ZernioPayloadBuilder per-platform payloads.
 *
 *   IG      → mixed video+image carousel (hook video item 0 when ready) +
 *             platformSpecificData.firstComment for the blog link.
 *   TikTok  → images only (no mixing), capped 35.
 *   Threads → images only (no video carousel), caption capped 500 chars.
 */
class ZernioPayloadBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://alisadikinma.com']);
        Setting::create(['group' => 'zernio', 'key' => 'zernio_instagram_account_id', 'value' => 'ig_acc']);
        Setting::create(['group' => 'zernio', 'key' => 'zernio_tiktok_account_id', 'value' => 'tt_acc']);
        Setting::create(['group' => 'zernio', 'key' => 'zernio_threads_account_id', 'value' => 'th_acc']);
    }

    private function makeLinkedInPost(int $slides = 3): LinkedInPost
    {
        $category = Category::create(['name' => 'AI', 'slug' => 'ai-'.uniqid()]);
        $post = Post::create(['category_id' => $category->id, 'slug' => 'p-'.uniqid(), 'title' => 'T', 'content' => 'B']);

        $slideRows = [];
        for ($i = 1; $i <= $slides; $i++) {
            $slideRows[] = ['slide_number' => $i, 'image_url' => "https://alisadikinma.com/storage/linkedin-carousel/slide-0{$i}.png"];
        }

        return LinkedInPost::create([
            'post_id' => $post->id,
            'format' => 'carousel',
            'content' => 'c',
            'carousel_slides' => $slideRows,
            'hashtags' => [],
            'status' => 'awaiting_publish',
            'pipeline_state_log' => [],
        ]);
    }

    private function ig(array $attrs = [], int $slides = 3): InstagramPost
    {
        $li = $this->makeLinkedInPost($slides);
        $ig = InstagramPost::create(array_merge([
            'linkedin_post_id' => $li->id,
            'post_id' => $li->post_id,
            'status' => 'awaiting_review',
            'caption' => 'cap',
            'hashtags' => [],
        ], $attrs));
        $ig->load('linkedinPost');

        return $ig;
    }

    public function test_instagram_all_image_with_first_comment(): void
    {
        $ig = $this->ig(['link_comment' => 'https://alisadikinma.com/blog/x']);

        $payload = (new ZernioPayloadBuilder)->buildInstagram($ig);

        $this->assertSame('instagram', $payload['platforms'][0]['platform']);
        $this->assertSame('ig_acc', $payload['platforms'][0]['accountId']);
        $this->assertCount(3, $payload['mediaItems']);
        $this->assertSame('image', $payload['mediaItems'][0]['type']);
        $this->assertSame('https://alisadikinma.com/blog/x', $payload['platforms'][0]['platformSpecificData']['firstComment']);
    }

    public function test_instagram_hook_video_replaces_the_cover_image(): void
    {
        // When the hook video is ready it IS the animated cover, so the static
        // cover (slide 1) is dropped: [video, slide2, slide3] — NOT [video, cover, …].
        $ig = $this->ig([
            'hook_video_status' => 'done',
            'hook_video_url' => 'https://alisadikinma.com/storage/linkedin-carousel/grok-hook.mp4',
        ], 3);

        $payload = (new ZernioPayloadBuilder)->buildInstagram($ig);

        $this->assertCount(3, $payload['mediaItems']); // video + 2 body slides (cover dropped)
        $this->assertSame('video', $payload['mediaItems'][0]['type']);
        $this->assertSame('https://alisadikinma.com/storage/linkedin-carousel/grok-hook.mp4', $payload['mediaItems'][0]['url']);
        $this->assertSame('image', $payload['mediaItems'][1]['type']);
        // First image after the video is slide 2, not the cover (slide 1).
        $this->assertStringContainsString('slide-02', $payload['mediaItems'][1]['url']);
    }

    public function test_instagram_keeps_all_slides_when_no_hook_video(): void
    {
        $ig = $this->ig([], 3);

        $payload = (new ZernioPayloadBuilder)->buildInstagram($ig);

        $this->assertCount(3, $payload['mediaItems']); // cover + 2 body slides, all images
        $this->assertStringContainsString('slide-01', $payload['mediaItems'][0]['url']);
    }

    public function test_instagram_suppresses_first_comment_for_repurpose_carousel(): void
    {
        // Carousel-only (IG-repurpose) posts have no public blog article → no
        // "Full article" first comment, even if a stale link_comment lingers.
        $ig = $this->ig(['link_comment' => 'https://alisadikinma.com/r/fXnVmsq']);
        \App\Models\RepurposeJob::factory()->create(['linkedin_post_id' => $ig->linkedin_post_id]);
        $ig->load('linkedinPost');

        $payload = (new ZernioPayloadBuilder)->buildInstagram($ig);

        $this->assertArrayNotHasKey('platformSpecificData', $payload['platforms'][0]);
    }

    public function test_tiktok_is_image_only_even_capped(): void
    {
        $li = $this->makeLinkedInPost(3);
        $tt = TiktokPost::create([
            'linkedin_post_id' => $li->id,
            'post_id' => $li->post_id,
            'status' => 'awaiting_review',
            'caption' => 'cap',
            'hashtags' => [],
        ]);
        $tt->load('linkedinPost');

        $payload = (new ZernioPayloadBuilder)->buildTiktok($tt);

        $this->assertSame('tiktok', $payload['platforms'][0]['platform']);
        foreach ($payload['mediaItems'] as $item) {
            $this->assertSame('image', $item['type'], 'TikTok must be image-only (no mixing)');
        }
        $this->assertArrayNotHasKey('firstComment', $payload['platforms'][0]['platformSpecificData'] ?? []);
    }

    public function test_threads_image_only_and_caption_capped_500(): void
    {
        $li = $this->makeLinkedInPost(3);
        $th = ThreadsPost::create([
            'linkedin_post_id' => $li->id,
            'post_id' => $li->post_id,
            'status' => 'awaiting_review',
            'caption' => str_repeat('a', 700),
            'hashtags' => [],
        ]);
        $th->load('linkedinPost');

        $payload = (new ZernioPayloadBuilder)->buildThreads($th);

        $this->assertSame('threads', $payload['platforms'][0]['platform']);
        $this->assertLessThanOrEqual(500, mb_strlen($payload['content']), 'Threads content must be <=500 chars');
        foreach ($payload['mediaItems'] as $item) {
            $this->assertSame('image', $item['type'], 'Threads has no video carousel');
        }
    }

    public function test_missing_account_id_throws(): void
    {
        Setting::where('group', 'zernio')->where('key', 'zernio_instagram_account_id')->delete();
        $ig = $this->ig();

        $this->expectException(\RuntimeException::class);
        (new ZernioPayloadBuilder)->buildInstagram($ig);
    }

    public function test_tiktok_title_capped_at_90(): void
    {
        // TikTok photo posts use the content as the slideshow title (≤90 chars).
        $li = $this->makeLinkedInPost(3);
        $tt = TiktokPost::create([
            'linkedin_post_id' => $li->id,
            'post_id' => $li->post_id,
            'status' => 'awaiting_review',
            'caption' => str_repeat('x', 241),
            'hashtags' => [],
        ]);
        $tt->load('linkedinPost');

        $payload = (new ZernioPayloadBuilder)->buildTiktok($tt);

        $this->assertLessThanOrEqual(90, mb_strlen($payload['content']));
    }

    public function test_instagram_slides_run_through_ratio_normalizer(): void
    {
        // Every IG image slide must pass through the aspect-ratio normalizer.
        $stub = new class extends \App\Services\ZernioImageNormalizer
        {
            public function normalizeForInstagram(string $url): string
            {
                return $url.'?normalized';
            }
        };

        $payload = (new ZernioPayloadBuilder($stub))->buildInstagram($this->ig());

        foreach ($payload['mediaItems'] as $item) {
            if ($item['type'] === 'image') {
                $this->assertStringEndsWith('?normalized', $item['url']);
            }
        }
    }
}
