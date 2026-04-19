<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\User;
use App\Services\ArticleGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class RunDeepScoreEndpointTest extends TestCase
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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function authenticate(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
    }

    private function makeArticleReadyIdea(array $override = []): ContentIdea
    {
        return ContentIdea::create(array_merge([
            'title' => 'Article ready for deep score',
            'source' => 'manual',
            'status' => 'article_ready',
            'progress_percentage' => 100,
            'current_step' => 'mechanical_snapshot',
            'generated_article' => [
                'title' => 'Article ready for deep score',
                'content' => '<h2>Body</h2><p>Content.</p>',
                'keyword' => 'keyword',
                'language' => 'en',
            ],
        ], $override));
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $idea = $this->makeArticleReadyIdea();

        $response = $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/run-deep-score");

        $response->assertStatus(401);
    }

    public function test_happy_path_triggers_score_and_flips_status(): void
    {
        $this->authenticate();

        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldReceive('triggerScore')
            ->once()
            ->andReturn(['success' => true, 'pid' => 77214, 'error' => null]);
        app()->instance(ArticleGenerationService::class, $mock);

        $idea = $this->makeArticleReadyIdea();

        $response = $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/run-deep-score");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.pid', 77214);

        $fresh = ContentIdea::find($idea->id);
        $this->assertSame('researching', $fresh->status);
        $this->assertSame(85, $fresh->progress_percentage);
        $this->assertSame('deep_scoring', $fresh->current_step);
        $this->assertSame(77214, $fresh->process_pid);
    }

    public function test_rejects_when_idea_not_in_article_ready_status(): void
    {
        $this->authenticate();

        $idea = $this->makeArticleReadyIdea(['status' => 'draft']);

        $response = $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/run-deep-score");

        $response->assertStatus(409);
        $response->assertJsonPath('success', false);
        $this->assertStringContainsString('article_ready', $response->json('message'));
    }

    public function test_returns_500_when_sonnet_dispatch_fails(): void
    {
        $this->authenticate();

        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldReceive('triggerScore')
            ->once()
            ->andReturn(['success' => false, 'pid' => null, 'error' => 'ssh timeout after 30s']);
        app()->instance(ArticleGenerationService::class, $mock);

        $idea = $this->makeArticleReadyIdea();

        $response = $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/run-deep-score");

        $response->assertStatus(500);
        $response->assertJsonPath('success', false);
        $this->assertStringContainsString('ssh timeout', $response->json('message'));

        $fresh = ContentIdea::find($idea->id);
        $this->assertSame('article_ready', $fresh->status);
        $this->assertSame(100, $fresh->progress_percentage);
    }

    public function test_returns_404_when_idea_not_found(): void
    {
        $this->authenticate();

        $response = $this->postJson('/api/admin/content-engine/ideas/99999/run-deep-score');

        $response->assertStatus(404);
    }

    public function test_rejects_article_ready_idea_with_empty_content(): void
    {
        // Phase C skip path can leave status=article_ready with no content
        // if write callback lost the body. Deep score would then dispatch
        // Sonnet against an empty article, which silently scores garbage.
        // The endpoint must reject that case.
        $this->authenticate();

        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldNotReceive('triggerScore');
        app()->instance(ArticleGenerationService::class, $mock);

        $idea = ContentIdea::create([
            'title' => 'Stub with no body',
            'source' => 'manual',
            'status' => 'article_ready',
            'progress_percentage' => 100,
            'current_step' => 'mechanical_snapshot',
            'generated_article' => ['title' => 'Stub', 'content' => '', 'language' => 'en'],
        ]);

        $response = $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/run-deep-score");

        $response->assertStatus(409);
        $response->assertJsonPath('success', false);
        $this->assertStringContainsString('empty', $response->json('message'));
    }

    public function test_rejects_when_generated_article_entirely_missing(): void
    {
        $this->authenticate();

        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldNotReceive('triggerScore');
        app()->instance(ArticleGenerationService::class, $mock);

        $idea = ContentIdea::create([
            'title' => 'No article yet',
            'source' => 'manual',
            'status' => 'article_ready',
            'progress_percentage' => 100,
            'current_step' => 'mechanical_snapshot',
            'generated_article' => null,
        ]);

        $response = $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/run-deep-score");

        $response->assertStatus(409);
    }
}
