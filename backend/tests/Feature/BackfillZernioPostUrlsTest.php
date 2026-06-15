<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\Setting;
use App\Services\ZernioClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * crosspost:backfill-zernio-urls — Zernio publishes asynchronously, so IG/TikTok
 * return a null platformPostUrl at createPost time. This cron polls GET /posts/{id}
 * for published siblings missing external_url and backfills it so the admin
 * "Open on <platform>" links light up.
 */
class BackfillZernioPostUrlsTest extends TestCase
{
    use RefreshDatabase;

    private function ig(array $attrs = []): InstagramPost
    {
        $category = Category::create(['name' => 'AI', 'slug' => 'ai-'.uniqid()]);
        $post = Post::create(['category_id' => $category->id, 'slug' => 'p-'.uniqid(), 'title' => 'T', 'content' => 'B']);
        $li = LinkedInPost::create([
            'post_id' => $post->id, 'format' => 'carousel', 'content' => 'c',
            'carousel_slides' => [], 'hashtags' => [], 'status' => 'published', 'pipeline_state_log' => [],
        ]);

        return InstagramPost::create(array_merge([
            'linkedin_post_id' => $li->id, 'post_id' => $li->post_id,
            'status' => 'published', 'caption' => 'cap', 'hashtags' => [],
        ], $attrs));
    }

    private function fakeClientReturning(?string $url): void
    {
        $stub = new class($url) extends ZernioClient
        {
            public function __construct(private ?string $url) {}

            public function forPlatform(string $platform): self
            {
                return $this;
            }

            public function getPost(string $id): array
            {
                return ['platforms' => [['platformPostUrl' => $this->url]]];
            }
        };
        $this->app->instance(ZernioClient::class, $stub);
    }

    public function test_backfills_external_url_for_published_sibling(): void
    {
        $ig = $this->ig(['zernio_post_id' => 'zpost123', 'external_url' => null]);
        $this->fakeClientReturning('https://instagram.com/p/ABC123/');

        $this->artisan('crosspost:backfill-zernio-urls')->assertExitCode(0);

        $this->assertSame('https://instagram.com/p/ABC123/', $ig->fresh()->external_url);
    }

    public function test_skips_sibling_that_already_has_external_url(): void
    {
        $ig = $this->ig(['zernio_post_id' => 'zpost123', 'external_url' => 'https://instagram.com/p/OLD/']);
        $this->fakeClientReturning('https://instagram.com/p/NEW/');

        $this->artisan('crosspost:backfill-zernio-urls')->assertExitCode(0);

        $this->assertSame('https://instagram.com/p/OLD/', $ig->fresh()->external_url);
    }

    public function test_skips_sibling_without_zernio_post_id(): void
    {
        $ig = $this->ig(['zernio_post_id' => null, 'external_url' => null]);
        $this->fakeClientReturning('https://instagram.com/p/ABC123/');

        $this->artisan('crosspost:backfill-zernio-urls')->assertExitCode(0);

        $this->assertNull($ig->fresh()->external_url);
    }

    public function test_leaves_url_null_when_zernio_still_processing(): void
    {
        $ig = $this->ig(['zernio_post_id' => 'zpost123', 'external_url' => null]);
        $this->fakeClientReturning(null); // platformPostUrl not ready yet

        $this->artisan('crosspost:backfill-zernio-urls')->assertExitCode(0);

        $this->assertNull($ig->fresh()->external_url);
    }
}
