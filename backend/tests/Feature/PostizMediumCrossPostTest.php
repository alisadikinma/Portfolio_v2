<?php

namespace Tests\Feature;

use App\Jobs\PublishViaPubler;
use App\Models\Category;
use App\Models\Post;
use App\Models\PostizChannel;
use App\Models\PostizPublishJob;
use App\Models\PostTranslation;
use App\Models\Setting;
use App\Models\User;
use App\Services\PostizPublishDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase K — blog→Medium cross-post with canonical backlink.
 * See docs/plans/2026-06-13-postiz-local-node-crosspost.md.
 */
class PostizMediumCrossPostTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(string $slug = 'my-essay'): Post
    {
        $category = Category::query()->first() ?? Category::create([
            'name' => 'Test Cat',
            'slug' => 'test-cat-' . Str::random(6),
        ]);
        $post = Post::factory()->create(['category_id' => $category->id, 'slug' => $slug]);
        PostTranslation::create([
            'post_id' => $post->id,
            'language' => 'id',
            'title' => 'Judul Esai',
            'slug' => $slug,
            'content' => '<p>Isi artikel.</p>',
        ]);

        return $post;
    }

    private function enableFlags(bool $postiz = true, bool $medium = true): void
    {
        Setting::updateOrCreate(['group' => 'postiz', 'key' => 'postiz_enabled'], ['value' => $postiz ? 'true' : 'false', 'type' => 'text']);
        Setting::updateOrCreate(['group' => 'postiz', 'key' => 'postiz_medium_enabled'], ['value' => $medium ? 'true' : 'false', 'type' => 'text']);
    }

    public function test_flags_on_and_medium_mapped_creates_medium_job(): void
    {
        $this->enableFlags();
        PostizChannel::factory()->create(['platform' => 'medium', 'handle' => 'ali', 'postiz_integration_id' => '12', 'enabled' => true]);
        $post = $this->makePost();

        app(PostizPublishDispatcher::class)->dispatchBlogToMedium($post);

        $this->assertDatabaseHas('postiz_publish_jobs', [
            'platform' => 'medium',
            'sibling_post_id' => $post->id,
            'sibling_type' => Post::class,
            'status' => 'ready_to_publish',
            'postiz_integration_id' => '12',
        ]);
    }

    public function test_medium_flag_off_creates_no_job(): void
    {
        $this->enableFlags(postiz: true, medium: false);
        PostizChannel::factory()->create(['platform' => 'medium', 'handle' => 'ali', 'postiz_integration_id' => '12']);
        $post = $this->makePost();

        app(PostizPublishDispatcher::class)->dispatchBlogToMedium($post);

        $this->assertDatabaseMissing('postiz_publish_jobs', ['platform' => 'medium', 'sibling_post_id' => $post->id]);
    }

    public function test_medium_unmapped_creates_no_job(): void
    {
        $this->enableFlags();
        // No medium channel mapped.
        $post = $this->makePost();

        app(PostizPublishDispatcher::class)->dispatchBlogToMedium($post);

        $this->assertDatabaseMissing('postiz_publish_jobs', ['platform' => 'medium', 'sibling_post_id' => $post->id]);
    }

    public function test_pending_payload_for_medium_carries_canonical_and_backlink_footer(): void
    {
        $this->enableFlags();
        PostizChannel::factory()->create(['platform' => 'medium', 'handle' => 'ali', 'postiz_integration_id' => '12', 'enabled' => true]);
        $post = $this->makePost('canonical-test');
        app(PostizPublishDispatcher::class)->dispatchBlogToMedium($post);

        Sanctum::actingAs(User::factory()->create());
        $res = $this->getJson('/api/automation/postiz/pending?worker=local-1')->assertOk();

        $canonical = config('app.frontend_url', 'https://alisadikinma.com') . '/blog/canonical-test';
        $res->assertJsonPath('jobs.0.platform', 'medium');
        $res->assertJsonPath('jobs.0.canonical', $canonical);
        $this->assertStringContainsString('Judul Esai', $res->json('jobs.0.title'));
        // body + server-built attribution footer with a real clickable backlink
        $this->assertStringContainsString('Isi artikel', $res->json('jobs.0.content'));
        $this->assertStringContainsString('href="' . $canonical . '"', $res->json('jobs.0.content'));
    }

    public function test_watchdog_never_publer_fallbacks_medium(): void
    {
        Queue::fake();
        $this->enableFlags();
        PostizChannel::factory()->create(['platform' => 'medium', 'handle' => 'ali', 'postiz_integration_id' => '12', 'enabled' => true]);
        $post = $this->makePost();
        $job = PostizPublishJob::factory()->create([
            'platform' => 'medium',
            'sibling_post_id' => $post->id,
            'sibling_type' => Post::class,
            'status' => 'ready_to_publish',
            'postiz_post_id' => null,
            'fallback_fired_at' => null,
            'slot_due_at' => now()->subMinutes(30),
        ]);

        $this->artisan('postiz:reap-unclaimed')->assertExitCode(0);

        Queue::assertNotPushed(PublishViaPubler::class);
        $this->assertNull($job->fresh()->fallback_fired_at);
    }
}
