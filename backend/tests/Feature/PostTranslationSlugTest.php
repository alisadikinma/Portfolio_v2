<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PF-2 SEO audit 2026-11-09: each translation has its own slug (sitemap lists them), but
 * GET /api/posts/{slug} only matched posts.slug, so 105 sitemap URLs 404ed on the new site.
 */
class PostTranslationSlugTest extends TestCase
{
    use RefreshDatabase;

    private function makeBilingualPost(): Post
    {
        $category = Category::firstOrCreate(['slug' => 'ai'], ['name' => 'AI']);
        $post = Post::create([
            'category_id' => $category->id,
            'slug' => 'model-ai-china-prediksi',
            'published' => true,
            'published_at' => now()->subDay(),
        ]);
        $post->translations()->create(['language' => 'id', 'title' => 'Judul', 'slug' => 'model-ai-china-prediksi', 'content' => '<p>id</p>']);
        $post->translations()->create(['language' => 'en', 'title' => 'Title', 'slug' => 'chinese-ai-models-prediction', 'content' => '<p>en</p>']);

        return $post;
    }

    public function test_show_finds_a_post_by_its_translation_slug(): void
    {
        $post = $this->makeBilingualPost();
        $this->getJson('/api/posts/chinese-ai-models-prediction?lang=en&track=0')
            ->assertOk()
            ->assertJsonPath('data.id', $post->id);
    }

    public function test_show_by_primary_slug_still_works(): void
    {
        $this->makeBilingualPost();
        $this->getJson('/api/posts/model-ai-china-prediksi?lang=id&track=0')->assertOk();
    }

    public function test_unknown_slug_and_unpublished_translation_slug_404(): void
    {
        $post = $this->makeBilingualPost();
        $this->getJson('/api/posts/nope?track=0')->assertNotFound();
        $post->update(['published' => false]);
        $this->getJson('/api/posts/chinese-ai-models-prediction?track=0')->assertNotFound();
    }

    public function test_resource_exposes_per_language_slugs_and_the_localized_slug(): void
    {
        $this->makeBilingualPost();
        $this->getJson('/api/posts/model-ai-china-prediksi?lang=en&track=0')
            ->assertJsonPath('data.slugs.id', 'model-ai-china-prediksi')
            ->assertJsonPath('data.slugs.en', 'chinese-ai-models-prediction')
            ->assertJsonPath('data.localized_slug', 'chinese-ai-models-prediction');
        $list = $this->getJson('/api/posts?lang=en')->json('data');
        $this->assertSame('chinese-ai-models-prediction', $list[0]['localized_slug']);
    }

    public function test_localized_slug_falls_back_to_the_post_slug_when_the_translation_has_none(): void
    {
        $post = $this->makeBilingualPost();
        $post->translations()->where('language', 'en')->update(['slug' => '']);
        $this->getJson('/api/posts/model-ai-china-prediksi?lang=en&track=0')
            ->assertJsonPath('data.localized_slug', 'model-ai-china-prediksi')
            ->assertJsonPath('data.slugs.en', 'model-ai-china-prediksi');
    }
}
