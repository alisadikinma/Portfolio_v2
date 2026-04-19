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
 * Phase C end-to-end: pipeline skip path → admin reads snapshot →
 * user triggers deep score → snapshot survives.
 *
 * Mocks only the Sonnet SSH edge (ArticleGenerationService::triggerScore)
 * so the test runs offline. Every other layer — route, controller,
 * MechanicalSnapshotWriter, MechanicalScoringService, Eloquent, JSON
 * cast, auth middleware — is real.
 */
class PhaseCPipelineSkipAndDeepScoreE2ETest extends TestCase
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

    public function test_full_phase_c_flow_skip_path_through_deep_score_preserves_snapshot(): void
    {
        // --- Setup: authenticated admin + idea at write-done ---
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        config(['services.article_generation.use_score_phase' => false]);

        $idea = ContentIdea::create([
            'title' => 'Best AI Coding Tools Ranked for 2026',
            'source' => 'manual',
            'status' => 'researching',
            'progress_percentage' => 85,
            'current_step' => 'write_complete',
            'generated_article' => [
                'title' => 'Best AI Coding Tools Ranked for 2026',
                'content' => '<h2>Introduction to AI Coding Tools</h2><p>We test ai coding tools in 2026. '
                    . 'Claude Code stands out among ai coding tools. Developers in 2025 adopted ai coding tools quickly.</p>'
                    . '<h2>Recommendations</h2><p>Cursor, Windsurf, and Claude Code lead the ai coding tools category.</p>',
                'keyword' => 'ai coding tools',
                'language' => 'en',
            ],
        ]);

        // --- 1) Skip-path: continue-pipeline at progress 85 with flag OFF ---
        $strictScoreMock = Mockery::mock(ArticleGenerationService::class);
        $strictScoreMock->shouldNotReceive('triggerScore');
        app()->instance(ArticleGenerationService::class, $strictScoreMock);

        $response = $this->postJson("/api/automation/content-ideas/{$idea->id}/continue-pipeline");

        $response->assertStatus(200);
        $response->assertJsonPath('next_phase', 'score_skipped');
        $response->assertJsonPath('mechanical_snapshot_saved', true);

        $fresh = ContentIdea::find($idea->id);
        $this->assertSame('article_ready', $fresh->status);
        $this->assertSame(100, $fresh->progress_percentage);
        $this->assertSame('mechanical_snapshot', $fresh->current_step);

        // Snapshot shape: full MechanicalScoringService payload + captured_at
        $snap = $fresh->mechanical_scores_snapshot;
        $this->assertIsArray($snap);
        $this->assertArrayHasKey('captured_at', $snap);
        $this->assertArrayHasKey('seo', $snap);
        $this->assertArrayHasKey('ai_humanization', $snap);
        $this->assertArrayHasKey('faq_pair_count', $snap);
        $this->assertArrayHasKey('freshness_signals', $snap);
        $this->assertArrayHasKey('word_count', $snap);
        // All 6 SEO metrics present
        foreach (['title_length', 'keyword_in_title', 'title_word_count', 'body_keyword_density', 'keyword_in_first_100', 'keyword_in_headings'] as $metric) {
            $this->assertArrayHasKey($metric, $snap['seo'], "SEO.$metric missing from snapshot");
        }

        // --- 2) Admin preview load: idea endpoint returns snapshot as array ---
        $getResponse = $this->getJson("/api/admin/content-engine/ideas/{$idea->id}");
        $getResponse->assertStatus(200);
        $getResponse->assertJsonPath('data.mechanical_scores_snapshot.seo.keyword_in_title.value', true);
        $getResponse->assertJsonPath('data.status', 'article_ready');

        // --- 3) User clicks Run Deep Quality Analysis ---
        // Swap the strict mock out for one that allows triggerScore
        $permissiveScoreMock = Mockery::mock(ArticleGenerationService::class);
        $permissiveScoreMock->shouldReceive('triggerScore')
            ->once()
            ->with($idea->id)
            ->andReturn(['success' => true, 'pid' => 55123, 'error' => null]);
        app()->instance(ArticleGenerationService::class, $permissiveScoreMock);

        $deepResponse = $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/run-deep-score");
        $deepResponse->assertStatus(200);
        $deepResponse->assertJsonPath('data.pid', 55123);

        $duringDeep = ContentIdea::find($idea->id);
        $this->assertSame('researching', $duringDeep->status);
        $this->assertSame(85, $duringDeep->progress_percentage);
        $this->assertSame('deep_scoring', $duringDeep->current_step);

        // Snapshot MUST survive the deep-score trigger (Phase C contract)
        $this->assertIsArray($duringDeep->mechanical_scores_snapshot);
        $this->assertSame(
            $snap['captured_at'],
            $duringDeep->mechanical_scores_snapshot['captured_at'],
            'Mechanical snapshot captured_at should persist across deep-score run'
        );

        // --- 4) Simulate deep-score completion callback from the plugin ---
        // Mimic the shape written by /automation/content-ideas/{id}/complete:
        // combined_score on generated_article + status flipped back to article_ready.
        $article = $duringDeep->generated_article ?? [];
        $article['quality_gate'] = ['score' => 8];
        $article['virality_score'] = ['score' => 4];
        $article['seo_analysis'] = ['score' => 5];
        $article['ai_humanization'] = ['score' => 18];
        $article['geo_score'] = ['score' => 4];
        $article['combined_score'] = ['total' => 82, 'grade' => 'B+'];

        $duringDeep->update([
            'generated_article' => $article,
            'status' => 'article_ready',
            'progress_percentage' => 100,
            'current_step' => 'completed',
        ]);

        // --- 5) Post-completion progress endpoint returns 100 ---
        $progressResponse = $this->getJson("/api/admin/content-engine/ideas/{$idea->id}/progress");
        $progressResponse->assertStatus(200);
        $progressResponse->assertJsonPath('data.progress_percentage', 100);

        // --- 6) Snapshot STILL present alongside the new deep scores ---
        $final = ContentIdea::find($idea->id);
        $this->assertIsArray($final->mechanical_scores_snapshot);
        $this->assertSame($snap['captured_at'], $final->mechanical_scores_snapshot['captured_at']);
        $this->assertSame(82, $final->generated_article['combined_score']['total']);
        $this->assertSame('article_ready', $final->status);
    }
}
