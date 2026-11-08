<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Services\ArticleGenerationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostFreshnessAndVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(array $overrides = [], array $langs = ['en']): Post
    {
        $category = Category::firstOrCreate(['slug' => 'ai'], ['name' => 'AI']);
        $post = Post::create(array_merge([
            'category_id' => $category->id,
            'slug' => 'post-'.uniqid(),
            'published' => true,
            'published_at' => now()->subDays(10),
        ], $overrides));
        foreach ($langs as $lang) {
            $post->translations()->create([
                'language' => $lang,
                'title' => strtoupper($lang).' title '.$post->slug,
                'slug' => $post->slug.'-'.$lang,
                'content' => '<p>body</p>',
            ]);
        }

        return $post;
    }

    private function setUpdatedAt(Post $post, Carbon $at): void
    {
        \DB::table('posts')->where('id', $post->id)->update(['updated_at' => $at]);
    }

    public function test_view_does_not_touch_updated_at(): void
    {
        $post = $this->makePost();
        $old = now()->subDays(5)->startOfSecond();
        $this->setUpdatedAt($post, $old);

        $this->getJson('/api/posts/'.$post->slug)->assertOk();

        $fresh = $post->fresh();
        $this->assertSame(1, (int) $fresh->views);
        $this->assertTrue($fresh->updated_at->equalTo($old), 'updated_at bumped by view');
    }

    public function test_translation_bookkeeping_does_not_touch_updated_at(): void
    {
        $post = $this->makePost(['translation_pending' => true, 'translation_attempts' => 0]);
        $old = now()->subDays(5)->startOfSecond();
        $this->setUpdatedAt($post, $old);

        $svc = \Mockery::mock(ArticleGenerationService::class);
        $svc->shouldReceive('triggerTranslate')->andReturn(['pid' => 1]);
        $this->app->instance(ArticleGenerationService::class, $svc);

        $this->artisan('content:process-pending-translations')->assertSuccessful();

        $fresh = $post->fresh();
        $this->assertSame(1, (int) $fresh->translation_attempts);
        $this->assertTrue($fresh->updated_at->equalTo($old), 'updated_at bumped by translation retry');
    }

    public function test_content_updated_at_uses_latest_translation_update(): void
    {
        $post = $this->makePost(['published_at' => now()->subDays(30)]);
        $t = $post->translations()->first();
        $when = now()->subDays(3)->startOfSecond();
        \DB::table('post_translations')->where('id', $t->id)->update(['updated_at' => $when]);

        $data = $this->getJson('/api/posts/'.$post->slug.'?track=0')->assertOk()->json('data');
        $this->assertSame($when->toISOString(), Carbon::parse($data['content_updated_at'])->toISOString());
        $this->assertArrayHasKey('updated_at', $data);
    }

    public function test_content_updated_at_uses_reviewed_at_when_newer(): void
    {
        $reviewed = now()->subDays(1)->startOfSecond();
        $post = $this->makePost(['published_at' => now()->subDays(30), 'content_reviewed_at' => $reviewed]);
        \DB::table('post_translations')->where('post_id', $post->id)
            ->update(['updated_at' => now()->subDays(20)]);

        $data = $this->getJson('/api/posts/'.$post->slug.'?track=0')->json('data');
        $this->assertSame($reviewed->toISOString(), Carbon::parse($data['content_updated_at'])->toISOString());
    }

    public function test_content_updated_at_never_before_published_at(): void
    {
        $pub = now()->subDays(2)->startOfSecond();
        $post = $this->makePost(['published_at' => $pub]);
        \DB::table('post_translations')->where('post_id', $post->id)
            ->update(['updated_at' => now()->subDays(40)]);

        $data = $this->getJson('/api/posts/'.$post->slug.'?track=0')->json('data');
        $this->assertSame($pub->toISOString(), Carbon::parse($data['content_updated_at'])->toISOString());
    }

    public function test_content_updated_at_never_in_future(): void
    {
        $post = $this->makePost(['published_at' => now()->subDays(2), 'content_reviewed_at' => now()->addDays(9)]);

        $data = $this->getJson('/api/posts/'.$post->slug.'?track=0')->json('data');
        $this->assertTrue(Carbon::parse($data['content_updated_at'])->lte(now()->addSecond()));
    }

    public function test_related_posts_use_requested_language_with_fallback(): void
    {
        $main = $this->makePost([], ['en', 'id']);
        $both = $this->makePost([], ['en', 'id']);
        $enOnly = $this->makePost([], ['en']);
        $main->relatedPosts()->sync([
            $both->id => ['sort_order' => 1],
            $enOnly->id => ['sort_order' => 2],
        ]);

        $data = $this->getJson('/api/posts/'.$main->slug.'?lang=id&track=0')->assertOk()->json('data.related_posts');
        $byId = collect($data)->keyBy('id');

        $this->assertStringStartsWith('ID title', $byId[$both->id]['title']);
        $this->assertEqualsCanonicalizing(['en', 'id'], $byId[$both->id]['available_translations']);
        $this->assertStringStartsWith('EN title', $byId[$enOnly->id]['title']);
        $this->assertSame(['en'], $byId[$enOnly->id]['available_translations']);
    }

    public function test_scheduled_post_is_404_on_show_and_absent_from_index(): void
    {
        $future = $this->makePost(['published_at' => now()->addDays(3)]);
        $live = $this->makePost();

        $this->getJson('/api/posts/'.$future->slug)->assertNotFound();

        $slugs = collect($this->getJson('/api/posts')->assertOk()->json('data'))->pluck('slug');
        $this->assertTrue($slugs->contains($live->slug));
        $this->assertFalse($slugs->contains($future->slug));
    }

    public function test_published_post_without_published_at_is_404(): void
    {
        $p = $this->makePost(['published_at' => null]);
        $this->getJson('/api/posts/'.$p->slug)->assertNotFound();
    }

    public function test_related_posts_exclude_scheduled_and_unpublished(): void
    {
        $main = $this->makePost();
        $ok = $this->makePost();
        $future = $this->makePost(['published_at' => now()->addDays(3)]);
        $draft = $this->makePost(['published' => false]);
        $main->relatedPosts()->sync([
            $ok->id => ['sort_order' => 1],
            $future->id => ['sort_order' => 2],
            $draft->id => ['sort_order' => 3],
        ]);

        $related = $this->getJson('/api/posts/' . $main->slug . '?track=0')->assertOk()->json('data.related_posts');
        $this->assertSame([$ok->id], array_column($related, 'id'));
    }

    public function test_content_publish_translation_trigger_keeps_updated_at(): void
    {
        $post = $this->makePost();
        $old = now()->subDays(5)->startOfSecond();
        $this->setUpdatedAt($post, $old);

        config(['services.article_generation.use_translate_phase' => true]);
        $svc = \Mockery::mock(ArticleGenerationService::class);
        $svc->shouldReceive('triggerTranslate')->andReturn(['pid' => 1]);
        $this->app->instance(ArticleGenerationService::class, $svc);

        $idea = new \App\Models\ContentIdea(['languages' => ['id', 'en']]);
        $m = new \ReflectionMethod(\App\Services\ContentPublishService::class, 'triggerTranslationIfEnabled');
        $m->setAccessible(true);
        $this->assertTrue($m->invoke(app(\App\Services\ContentPublishService::class), $idea, $post, 'id'));

        $fresh = $post->fresh();
        $this->assertTrue((bool) $fresh->translation_pending);
        $this->assertTrue($fresh->updated_at->equalTo($old), 'updated_at bumped by translation trigger');
    }

    public function test_mark_translation_complete_keeps_updated_at(): void
    {
        $post = $this->makePost(['translation_pending' => true]);
        $old = now()->subDays(5)->startOfSecond();
        $this->setUpdatedAt($post, $old);

        app(\App\Http\Controllers\Api\Admin\ContentIdeaController::class)
            ->markTranslationComplete($post->id, \Illuminate\Http\Request::create('/'));

        $fresh = $post->fresh();
        $this->assertFalse((bool) $fresh->translation_pending);
        $this->assertTrue($fresh->updated_at->equalTo($old), 'updated_at bumped by markTranslationComplete');
    }

    public function test_sitemap_lastmod_uses_content_freshness_not_view_bumped_updated_at(): void
    {
        $post = $this->makePost(['published_at' => now()->subDays(30)]);
        $edit = now()->subDays(4)->startOfSecond();
        \DB::table('post_translations')->where('post_id', $post->id)->update(['updated_at' => $edit]);
        $this->setUpdatedAt($post, now()->subHour());

        // Stub the XML view: its `<?xml` prolog trips short_open_tag in the test PHP.
        $dir = sys_get_temp_dir() . '/pf2-views-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/sitemap-posts.blade.php', '{!! json_encode($urls) !!}');
        app('view')->getFinder()->prependLocation($dir);

        $urls = json_decode($this->get('/api/sitemap-posts.xml')->assertOk()->getContent(), true);
        $this->assertSame($edit->toAtomString(), $urls[0]['lastmod']);
    }

    public function test_post_content_updated_at_method(): void
    {
        $post = $this->makePost(['published_at' => now()->subDays(30)], ['en', 'id']);
        $en = now()->subDays(6)->startOfSecond();
        $id = now()->subDays(2)->startOfSecond();
        \DB::table('post_translations')->where('post_id', $post->id)->where('language', 'en')->update(['updated_at' => $en]);
        \DB::table('post_translations')->where('post_id', $post->id)->where('language', 'id')->update(['updated_at' => $id]);
        $post = $post->fresh('translations');

        $this->assertTrue($post->contentUpdatedAt('id')->equalTo($id));
        $this->assertTrue($post->contentUpdatedAt('en')->equalTo($en));
        $this->assertTrue($post->contentUpdatedAt('xx')->equalTo($en), 'falls back to en');
    }
}
