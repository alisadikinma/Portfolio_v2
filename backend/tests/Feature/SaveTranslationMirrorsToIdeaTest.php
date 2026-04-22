<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentIdea;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression: saveTranslation (the automation endpoint hit by the
 * /article-translate plugin after publish) must mirror the translated
 * fields back into content_ideas.generated_article.{locale}.
 *
 * Without the mirror, the admin Finalize tab — which reads the JSON blob
 * rather than post_translations — shows "Belum diterjemahkan" for blog
 * posts that ARE already translated and live. Idea #213 (April 23, 2026)
 * hit this exact dual-storage split.
 */
class SaveTranslationMirrorsToIdeaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }
    }

    private function makePublishedIdeaAndPost(): array
    {
        $category = Category::create(['name' => 'Cat', 'slug' => 'cat']);

        $post = Post::create([
            'title' => 'Mirror test',
            'slug' => 'mirror-test',
            'content' => 'placeholder',
            'excerpt' => 'placeholder',
            'category_id' => $category->id,
            'published' => true,
            'published_at' => now(),
        ]);

        PostTranslation::create([
            'post_id' => $post->id,
            'language' => 'id',
            'title' => 'Judul ID',
            'slug' => 'judul-id',
            'content' => '<p>Isi bahasa Indonesia</p>',
        ]);

        $idea = ContentIdea::create([
            'title' => 'Mirror test',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'images_ready',
            'languages' => ['id', 'en'],
            'result_post_id' => $post->id,
            'generated_article' => [
                'language' => 'id',
                'id' => [
                    'title' => 'Judul ID',
                    'content' => '<p>Isi bahasa Indonesia</p>',
                ],
            ],
        ]);

        return [$idea, $post];
    }

    /** @test */
    public function save_translation_mirrors_to_idea_generated_article(): void
    {
        [$idea, $post] = $this->makePublishedIdeaAndPost();
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $token = $user->createToken('auto', ['*'])->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->putJson("/api/automation/posts/{$post->id}/save-translation", [
                'target_locale' => 'en',
                'translation' => [
                    'title' => 'English Title',
                    'slug' => 'english-title',
                    'content' => '<p>English body content</p>',
                    'excerpt' => 'English excerpt',
                    'meta_title' => 'EN meta',
                    'meta_description' => 'EN desc',
                    'og_title' => 'EN og',
                    'og_description' => 'EN og desc',
                    'ai_summary' => 'EN summary',
                ],
            ]);

        $response->assertStatus(200);

        // post_translations row — pre-existing behavior
        $pt = PostTranslation::where('post_id', $post->id)->where('language', 'en')->first();
        $this->assertNotNull($pt);
        $this->assertSame('<p>English body content</p>', $pt->content);

        // Mirror into generated_article.en — new behavior
        $idea->refresh();
        $en = $idea->generated_article['en'] ?? [];
        $this->assertSame('English Title', $en['title'] ?? null);
        $this->assertSame('<p>English body content</p>', $en['content'] ?? null);
        $this->assertSame('EN meta', $en['meta_title'] ?? null);
        $this->assertSame('EN og', $en['og_title'] ?? null);
        $this->assertSame('done', $idea->generated_article['translation_status'] ?? null);
        $this->assertArrayNotHasKey('translation_error', $idea->generated_article);
    }

    /** @test */
    public function save_translation_is_a_noop_mirror_when_post_has_no_idea(): void
    {
        // Posts created outside the Content Engine (e.g. manual blog posts)
        // have no matching ContentIdea. Mirror lookup must not break the
        // endpoint.
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $post = Post::create([
            'title' => 'Orphan post',
            'slug' => 'orphan-post',
            'content' => 'placeholder',
            'excerpt' => 'placeholder',
            'category_id' => Category::create(['name' => 'Cat', 'slug' => 'cat'])->id,
            'published' => true,
            'published_at' => now(),
        ]);

        $token = $user->createToken('auto', ['*'])->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->putJson("/api/automation/posts/{$post->id}/save-translation", [
                'target_locale' => 'en',
                'translation' => [
                    'title' => 'EN',
                    'slug' => 'en',
                    'content' => '<p>en</p>',
                ],
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('post_translations', [
            'post_id' => $post->id,
            'language' => 'en',
        ]);
    }

    /** @test */
    public function backfill_command_mirrors_historical_translations(): void
    {
        // Simulate an idea published before the mirror-write fix shipped:
        // post_translations has EN, generated_article.en is empty.
        [$idea, $post] = $this->makePublishedIdeaAndPost();
        PostTranslation::create([
            'post_id' => $post->id,
            'language' => 'en',
            'title' => 'Historic EN',
            'slug' => 'historic-en',
            'content' => '<p>Historic English body</p>',
        ]);

        // Sanity check: pre-backfill, idea's generated_article has no EN blob.
        $this->assertArrayNotHasKey('en', $idea->generated_article);

        $this->artisan('content-engine:sync-translation-mirrors')
            ->assertExitCode(0);

        $idea->refresh();
        $en = $idea->generated_article['en'] ?? [];
        $this->assertSame('Historic EN', $en['title'] ?? null);
        $this->assertSame('<p>Historic English body</p>', $en['content'] ?? null);
        $this->assertSame('done', $idea->generated_article['translation_status'] ?? null);
    }

    /** @test */
    public function backfill_command_dry_run_writes_nothing(): void
    {
        [$idea, $post] = $this->makePublishedIdeaAndPost();
        PostTranslation::create([
            'post_id' => $post->id,
            'language' => 'en',
            'title' => 'Historic EN',
            'slug' => 'historic-en',
            'content' => '<p>Historic English body</p>',
        ]);

        $this->artisan('content-engine:sync-translation-mirrors', ['--dry-run' => true])
            ->assertExitCode(0);

        $idea->refresh();
        $this->assertArrayNotHasKey('en', $idea->generated_article);
    }
}
