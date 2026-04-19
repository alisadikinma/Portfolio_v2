<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\User;
use App\Services\ArticleGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class ContinuePipelineScoreGatingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Same APP_URL subpath workaround used in Phase A.6.
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');

        // The initial content_ideas migration declared status as an enum with
        // a restricted vocabulary. Subsequent ALTER migrations that add
        // 'article_ready' + 'generating_images' are MySQL-only, so SQLite
        // test DB rejects them. This PRAGMA neutralizes SQLite's CHECK
        // constraint for the transaction so the production-correct status
        // transitions can be exercised in tests.
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        // Reset the PRAGMA so sibling tests in the same artisan process
        // (notably ResearchTierOverrideMigrationTest's enum-rejection
        // assertion) see a clean SQLite session with CHECK constraints
        // re-enabled. PRAGMAs are session-local and persist across tests
        // otherwise.
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = OFF');
        }
        parent::tearDown();
    }

    private function authenticate(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
    }

    private function makeWriteDoneIdea(): ContentIdea
    {
        return ContentIdea::create([
            'title' => 'Best AI Coding Tools 2026',
            'source' => 'manual',
            'status' => 'researching',
            'progress_percentage' => 85,
            'current_step' => 'write_complete',
            'generated_article' => [
                'title' => 'Best AI Coding Tools 2026',
                'content' => '<h2>Intro</h2><p>Discussing ai coding tools in 2026.</p><h2>Picks</h2><p>Cursor leads.</p>',
                'keyword' => 'ai coding tools',
                'language' => 'en',
            ],
        ]);
    }

    public function test_flag_off_skips_score_and_snapshots_mechanical_metrics(): void
    {
        config(['services.article_generation.use_score_phase' => false]);
        $this->authenticate();

        // If triggerScore were called, the skip is broken — bind a strict mock
        // that forbids it entirely. Mockery fails the test if it fires.
        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldNotReceive('triggerScore');
        app()->instance(ArticleGenerationService::class, $mock);

        $idea = $this->makeWriteDoneIdea();

        $response = $this->postJson("/api/automation/content-ideas/{$idea->id}/continue-pipeline");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('next_phase', 'score_skipped');
        $response->assertJsonPath('mechanical_snapshot_saved', true);

        $fresh = ContentIdea::find($idea->id);
        $this->assertSame('article_ready', $fresh->status);
        $this->assertSame(100, $fresh->progress_percentage);
        $this->assertSame('mechanical_snapshot', $fresh->current_step);
        $this->assertNull($fresh->process_pid);

        $this->assertIsArray($fresh->mechanical_scores_snapshot);
        $this->assertArrayHasKey('captured_at', $fresh->mechanical_scores_snapshot);
        $this->assertArrayHasKey('seo', $fresh->mechanical_scores_snapshot);
        $this->assertArrayHasKey('ai_humanization', $fresh->mechanical_scores_snapshot);
    }

    public function test_flag_on_dispatches_trigger_score_legacy_path(): void
    {
        config(['services.article_generation.use_score_phase' => true]);
        $this->authenticate();

        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldReceive('triggerScore')
            ->once()
            ->andReturn(['success' => true, 'pid' => 99201, 'error' => null]);
        app()->instance(ArticleGenerationService::class, $mock);

        $idea = $this->makeWriteDoneIdea();

        $response = $this->postJson("/api/automation/content-ideas/{$idea->id}/continue-pipeline");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('next_phase', 'score');
        $response->assertJsonPath('pid', 99201);

        $fresh = ContentIdea::find($idea->id);
        // Legacy path leaves status at researching — the score callback
        // is what flips it to article_ready downstream.
        $this->assertSame('researching', $fresh->status);
        $this->assertSame(99201, $fresh->process_pid);
        // Skip path DID NOT write a snapshot in this branch.
        $this->assertNull($fresh->mechanical_scores_snapshot);
    }

    public function test_skip_path_reports_false_when_article_content_missing(): void
    {
        config(['services.article_generation.use_score_phase' => false]);
        $this->authenticate();

        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldNotReceive('triggerScore');
        app()->instance(ArticleGenerationService::class, $mock);

        // Idea at progress 85 but generated_article is empty — snapshot cannot compute.
        $idea = ContentIdea::create([
            'title' => 'Edge case: write done but content missing',
            'source' => 'manual',
            'status' => 'researching',
            'progress_percentage' => 85,
            'current_step' => 'write_complete',
            'generated_article' => null,
        ]);

        $response = $this->postJson("/api/automation/content-ideas/{$idea->id}/continue-pipeline");

        $response->assertStatus(200);
        $response->assertJsonPath('next_phase', 'score_skipped');
        $response->assertJsonPath('mechanical_snapshot_saved', false);

        $fresh = ContentIdea::find($idea->id);
        // Still flips to article_ready so downstream (image gen etc) can proceed
        $this->assertSame('article_ready', $fresh->status);
        $this->assertNull($fresh->mechanical_scores_snapshot);
    }
}
