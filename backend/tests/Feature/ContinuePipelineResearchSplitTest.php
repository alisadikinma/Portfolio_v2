<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\User;
use App\Services\ArticleGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

/**
 * Phase B.6 — continue-pipeline branch for research→strategy-outline handoff.
 *
 * Exercises the `progress=15 → strategy_outline` dispatch branch added to
 * the continue-pipeline closure in routes/api.php. Covers:
 *  - split flag ON + research saved → triggerStrategyOutline dispatch
 *  - split flag ON + research missing → 409
 *  - split flag OFF + progress=15 → falls through (legacy unchanged)
 *  - split flag ON + progress=35 → triggerWrite (no double-fire)
 *  - split flag OFF + progress=35 → triggerWrite (legacy regression)
 */
class ContinuePipelineResearchSplitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // APP_URL subpath workaround (same pattern as other Phase B/C tests).
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');

        // SQLite in tests rejects the later MySQL-only status enum ALTERs.
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

    private function sampleResearchData(): array
    {
        return [
            'data_points' => [
                [
                    'stat' => '47% of 2,400 devs use AI coding assistants',
                    'source' => 'Stack Overflow Survey 2026',
                    'url' => 'https://example.com/survey',
                    'year' => 2026,
                ],
            ],
            'quotes' => [],
            'entities' => [],
            'personas' => [],
            'written_guides' => [],
        ];
    }

    private function makeIdeaAtProgress(int $progress, ?array $researchData = null): ContentIdea
    {
        return ContentIdea::create([
            'title' => 'Claude Design vs ChatGPT',
            'source' => 'manual',
            'status' => 'researching',
            'progress_percentage' => $progress,
            'current_step' => 'research_complete',
            'research_data' => $researchData,
        ]);
    }

    public function test_split_flag_on_at_progress_15_with_research_saved_dispatches_strategy_outline(): void
    {
        config(['services.article_generation.skill_split_enabled' => true]);
        $this->authenticate();

        $idea = $this->makeIdeaAtProgress(15, $this->sampleResearchData());

        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldReceive('triggerStrategyOutline')
            ->once()
            ->with($idea->id)
            ->andReturn(['success' => true, 'pid' => 999, 'error' => null]);
        $mock->shouldNotReceive('triggerWrite');
        $mock->shouldNotReceive('triggerScore');
        app()->instance(ArticleGenerationService::class, $mock);

        $response = $this->postJson("/api/automation/content-ideas/{$idea->id}/continue-pipeline");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('next_phase', 'strategy_outline');
        $response->assertJsonPath('pid', 999);

        $fresh = ContentIdea::find($idea->id);
        $this->assertSame(999, $fresh->process_pid);
        $this->assertEquals('strategy_outline_dispatched', $fresh->current_step);
    }

    public function test_split_flag_off_at_progress_15_falls_through_to_default_400(): void
    {
        config(['services.article_generation.skill_split_enabled' => false]);
        $this->authenticate();

        $idea = $this->makeIdeaAtProgress(15, $this->sampleResearchData());

        // Strict mock forbids any trigger method — split branch must not fire
        // AND legacy branches (progress=35+) must not fire at progress=15.
        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldNotReceive('triggerStrategyOutline');
        $mock->shouldNotReceive('triggerWrite');
        $mock->shouldNotReceive('triggerScore');
        app()->instance(ArticleGenerationService::class, $mock);

        $response = $this->postJson("/api/automation/content-ideas/{$idea->id}/continue-pipeline");

        // Falls through to default "No next phase to trigger" response.
        $response->assertStatus(200); // default response uses 200 + success:false
        $response->assertJsonPath('success', false);
        $this->assertNotSame('strategy_outline', $response->json('next_phase'));
        $response->assertJsonPath('message', 'No next phase to trigger.');
    }

    public function test_split_flag_on_at_progress_35_dispatches_write_not_strategy_outline(): void
    {
        config(['services.article_generation.skill_split_enabled' => true]);
        $this->authenticate();

        $idea = $this->makeIdeaAtProgress(35, $this->sampleResearchData());

        $mock = Mockery::mock(ArticleGenerationService::class);
        // Boundary guard: at progress=35, ONLY triggerWrite must fire.
        $mock->shouldNotReceive('triggerStrategyOutline');
        $mock->shouldReceive('triggerWrite')
            ->once()
            ->with($idea->id)
            ->andReturn(['success' => true, 'pid' => 31415, 'error' => null]);
        $mock->shouldNotReceive('triggerScore');
        app()->instance(ArticleGenerationService::class, $mock);

        $response = $this->postJson("/api/automation/content-ideas/{$idea->id}/continue-pipeline");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('next_phase', 'write');
        $response->assertJsonPath('pid', 31415);

        $fresh = ContentIdea::find($idea->id);
        $this->assertSame(31415, $fresh->process_pid);
    }

    public function test_split_flag_on_at_progress_15_without_research_data_returns_409(): void
    {
        config(['services.article_generation.skill_split_enabled' => true]);
        $this->authenticate();

        // research_data is null — strategy-outline cannot proceed.
        $idea = $this->makeIdeaAtProgress(15, null);

        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldNotReceive('triggerStrategyOutline');
        $mock->shouldNotReceive('triggerWrite');
        app()->instance(ArticleGenerationService::class, $mock);

        $response = $this->postJson("/api/automation/content-ideas/{$idea->id}/continue-pipeline");

        $response->assertStatus(409);
        $response->assertJsonPath('success', false);
        $this->assertStringContainsString('research_data', strtolower((string) $response->json('message')));
    }

    public function test_legacy_regression_split_flag_off_at_progress_35_still_dispatches_write(): void
    {
        config(['services.article_generation.skill_split_enabled' => false]);
        $this->authenticate();

        // Legacy path: research_data may be empty (legacy single-session prep
        // stores everything inside generated_article.prep_data). The write
        // dispatch must still fire unconditionally at the 35 boundary.
        $idea = $this->makeIdeaAtProgress(35, null);

        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldNotReceive('triggerStrategyOutline');
        $mock->shouldReceive('triggerWrite')
            ->once()
            ->with($idea->id)
            ->andReturn(['success' => true, 'pid' => 27182, 'error' => null]);
        app()->instance(ArticleGenerationService::class, $mock);

        $response = $this->postJson("/api/automation/content-ideas/{$idea->id}/continue-pipeline");

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('next_phase', 'write');
        $response->assertJsonPath('pid', 27182);

        $fresh = ContentIdea::find($idea->id);
        $this->assertSame(27182, $fresh->process_pid);
    }
}
