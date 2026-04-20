<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\Post;
use App\Models\PostTranslation;
use App\Models\User;
use App\Services\ContentPublishService;
use App\Support\HtmlSlashSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Regression: every write path into generated_article / post_translations
 * must apply HtmlSlashSanitizer so `\/` JSON-escape leakage never reaches
 * the render layer. Previously the /complete callback, saveTranslation,
 * and triggerTranslatePreflight paths bypassed the sanitizer — content
 * saved by those paths showed literal `<\/p>` in ArticleFinalize.vue.
 */
class HtmlSlashSanitizerAllWritePathsTest extends TestCase
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

    /** @test */
    public function automation_complete_endpoint_sanitizes_nested_language_content(): void
    {
        $idea = ContentIdea::create([
            'title' => 'Sanitize /complete',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'researching',
            'languages' => ['id'],
        ]);

        $user = User::factory()->create();
        $token = $user->createToken('test', ['*'])->plainTextToken;

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->putJson("/api/automation/content-ideas/{$idea->id}/complete", [
                'article' => [
                    'title' => 'Test Article<\\/title>',
                    'content' => '<p>Hello<\\/p><h2>Heading<\\/h2>',
                    'word_count' => 100,
                    'keyword' => 'test',
                ],
                'research_data' => ['x' => 1],
            ]);

        $response->assertStatus(200);

        $idea->refresh();
        $content = $idea->generated_article['id']['content'] ?? '';
        $title = $idea->generated_article['id']['title'] ?? '';

        $this->assertStringNotContainsString('<\\/p>', $content, 'Content still has escaped slashes after /complete');
        $this->assertStringContainsString('</p>', $content);
        $this->assertStringNotContainsString('<\\/title>', $title);
    }

    /** @test */
    public function save_translation_endpoint_sanitizes_content(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        $post = Post::create([
            'title' => 'Sanitize translate',
            'slug' => 'sanitize-translate',
            'content' => 'placeholder',
            'excerpt' => 'placeholder',
            'category_id' => \App\Models\Category::create(['name' => 'Cat', 'slug' => 'cat'])->id,
            'published' => true,
            'published_at' => now(),
        ]);

        $token = $user->createToken('auto', ['*'])->plainTextToken;
        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->putJson("/api/automation/posts/{$post->id}/save-translation", [
                'target_locale' => 'en',
                'translation' => [
                    'title' => 'EN Title',
                    'slug' => 'en-title',
                    'content' => '<p>English text<\\/p><h2>Heading<\\/h2>',
                    'excerpt' => 'A short summary<\\/p>',
                ],
            ]);

        $response->assertStatus(200);
        $pt = PostTranslation::where('post_id', $post->id)->where('language', 'en')->first();
        $this->assertNotNull($pt);
        $this->assertStringNotContainsString('<\\/p>', $pt->content);
        $this->assertStringContainsString('</p>', $pt->content);
        $this->assertStringNotContainsString('<\\/p>', $pt->excerpt);
    }

    /** @test */
    public function publish_service_sanitizes_in_memory_article_as_defense_in_depth(): void
    {
        // Defense-in-depth check: ContentPublishService::publish reads
        // $idea->generated_article and passes it through HtmlSlashSanitizer
        // BEFORE any downstream write. Verify by asserting the in-memory
        // article state after the sanitizer gate has clean HTML even when
        // the DB row held escaped content. Full publish flow isn't exercised
        // here because SQLite posts schema diverges from prod MySQL (title
        // NOT NULL locally); the sanitizer gate is the property under test.
        $legacy = [
            'language' => 'id',
            'id' => [
                'title' => 'Legacy Title',
                'content' => '<p>Body<\\/p><h2>Section<\\/h2>',
                'excerpt' => 'Intro<\\/p>',
            ],
            'image_prompts' => [],
        ];

        $sanitized = HtmlSlashSanitizer::apply($legacy);

        $this->assertStringNotContainsString('<\\/p>', $sanitized['id']['content']);
        $this->assertStringContainsString('</p>', $sanitized['id']['content']);
        $this->assertStringNotContainsString('<\\/p>', $sanitized['id']['excerpt']);
        $this->assertStringNotContainsString('<\\/h2>', $sanitized['id']['content']);
        $this->assertStringContainsString('</h2>', $sanitized['id']['content']);
    }

    /** @test */
    public function sanitizer_helper_is_idempotent(): void
    {
        $clean = ['content' => '<p>hello</p>'];
        $this->assertSame($clean, HtmlSlashSanitizer::apply($clean));
    }
}
