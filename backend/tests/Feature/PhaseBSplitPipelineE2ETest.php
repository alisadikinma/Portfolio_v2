<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\User;
use App\Services\ArticleGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

/**
 * Phase B.14 — End-to-end backend split-pipeline dispatch test.
 *
 * Verifies the full chain once Phase B.1–B.13 have shipped:
 *
 *   Start Research (admin, POST /research)
 *       → triggerResearch(ideaId, config, 'deep')
 *
 *   Research callback (PUT /save-research) + progress=15
 *       → POST /continue-pipeline
 *       → triggerStrategyOutline(ideaId)
 *
 *   Strategy/outline callback (PUT /save-prep) + progress=35
 *       → POST /continue-pipeline
 *       → triggerWrite(ideaId)
 *
 * Only the SSH-edge service methods (triggerResearch / triggerStrategyOutline /
 * triggerWrite / triggerPrep) are mocked. Every other layer — routes,
 * controllers, resolveResearchTier/resolveResearchModel, Eloquent, JSON casts,
 * Sanctum — runs the real implementation.
 *
 * Test 2 locks down the legacy regression (split flag OFF) so a future
 * refactor cannot silently enable the split branch for everyone.
 */
class PhaseBSplitPipelineE2ETest extends TestCase
{
    use RefreshDatabase;
    use MockeryPHPUnitIntegration;

    protected function setUp(): void
    {
        parent::setUp();
        // APP_URL subpath workaround — matches Phase A.6 / Phase B.6–B.7 /
        // Phase C precedent so postJson()/putJson() hit the test router cleanly.
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');

        // SQLite CHECK constraints from the initial content_ideas migration
        // do not include the later-added statuses. Disable so the route
        // handlers can run real transitions (draft → researching, etc.).
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }
    }

    protected function tearDown(): void
    {
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

    private function authenticate(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        return $user;
    }

    private function makeDeepTierEligibleIdea(): ContentIdea
    {
        // virality_score=85 is the critical piece: with research_tier_override='auto'
        // the real resolveResearchTier() resolves to 'deep' (>=70 threshold).
        return ContentIdea::create([
            'title' => 'AI tools for solopreneurs in 2026',
            'source' => 'manual',
            'status' => 'draft',
            'virality_score' => 85,
            'research_tier_override' => 'auto',
        ]);
    }

    public function test_happy_path_split_flow_dispatches_research_strategy_write_in_order(): void
    {
        config(['services.article_generation.skill_split_enabled' => true]);
        // Ensure deep tier is globally enabled so auto+85 resolves to 'deep'
        // (the resolver has a kill-switch short-circuit we must keep off).
        config(['services.article_generation.deep_research_enabled' => true]);
        // Keep refs_prep empty so the legacy-refs detector cannot interfere.
        config(['services.article_generation.refs_prep' => '']);

        $this->authenticate();
        $idea = $this->makeDeepTierEligibleIdea();
        $ideaId = $idea->id;

        // ---- Mockery: partial so real resolveResearchTier()/resolveResearchModel() run,
        // only the SSH dispatch methods are stubbed. makePartial preserves the real
        // public methods not explicitly overridden.
        $mock = Mockery::mock(ArticleGenerationService::class)->makePartial();

        $mock->shouldReceive('triggerResearch')
            ->once()
            ->with(
                Mockery::on(fn ($arg) => $arg === $ideaId),
                Mockery::on(function ($config) {
                    return is_array($config)
                        && ($config['topic'] ?? null) === 'AI tools for solopreneurs in 2026'
                        && ($config['languages'] ?? null) === ['en']
                        && ($config['instructions'] ?? null) === 'AI tools';
                }),
                'deep'
            )
            ->andReturn(['success' => true, 'pid' => 101, 'error' => null]);

        $mock->shouldReceive('triggerStrategyOutline')
            ->once()
            ->with($ideaId)
            ->andReturn(['success' => true, 'pid' => 102, 'error' => null]);

        $mock->shouldReceive('triggerWrite')
            ->once()
            ->with($ideaId)
            ->andReturn(['success' => true, 'pid' => 103, 'error' => null]);

        // Negative expectations — these MUST NOT fire anywhere in this test.
        $mock->shouldNotReceive('triggerPrep');
        $mock->shouldNotReceive('triggerGeneration');
        $mock->shouldNotReceive('triggerScore');
        $mock->shouldNotReceive('triggerImages');
        $mock->shouldNotReceive('triggerTranslate');

        $this->app->instance(ArticleGenerationService::class, $mock);

        // ==== STEP 1: Admin POST Start Research ====
        $startRes = $this->postJson("/api/admin/content-engine/ideas/{$ideaId}/research", [
            'research_tier' => 'auto',
            'languages' => ['en'],
            'instructions' => 'AI tools',
        ]);

        $startRes->assertStatus(200);
        $startRes->assertJsonPath('success', true);

        $afterStart = ContentIdea::find($ideaId);
        $this->assertSame('auto', $afterStart->research_tier_override, 'research_tier_override should persist as auto');
        $this->assertSame('researching', $afterStart->status);
        $this->assertSame(101, $afterStart->process_pid, 'process_pid should be 101 from triggerResearch stub');

        // ==== STEP 2: /article-research callback — persist lean schema v2 research_data ====
        $researchPayload = [
            'research_data' => [
                'data_points' => [
                    [
                        'stat' => '47% of 2,400 devs use AI coding assistants daily',
                        'source' => 'Stack Overflow Survey 2026',
                        'url' => 'https://example.com/survey',
                        'year' => 2026,
                    ],
                ],
                'quotes' => [],
                'entities' => [],
                'personas' => [],
                'written_guides' => [],
            ],
        ];

        $saveResearchRes = $this->putJson(
            "/api/automation/content-ideas/{$ideaId}/save-research",
            $researchPayload
        );
        $saveResearchRes->assertStatus(200);
        $saveResearchRes->assertJsonPath('success', true);

        $afterResearch = ContentIdea::find($ideaId);
        $this->assertIsArray($afterResearch->research_data);
        $this->assertSame(
            '47% of 2,400 devs use AI coding assistants daily',
            $afterResearch->research_data['data_points'][0]['stat']
        );
        // All 5 lean-schema-v2 top-level keys must be present after round-trip.
        foreach (['data_points', 'quotes', 'entities', 'personas', 'written_guides'] as $key) {
            $this->assertArrayHasKey($key, $afterResearch->research_data, "research_data.$key missing");
        }

        // ==== STEP 3: Progress tick to 15 ====
        $progress15Res = $this->putJson(
            "/api/automation/content-ideas/{$ideaId}/progress",
            [
                'step' => 'research',
                'percentage' => 15,
                'message' => 'Research complete',
            ]
        );
        $progress15Res->assertStatus(200);
        $this->assertSame(15, ContentIdea::find($ideaId)->progress_percentage);

        // ==== STEP 4: continue-pipeline → strategy-outline ====
        $cont1Res = $this->postJson("/api/automation/content-ideas/{$ideaId}/continue-pipeline");
        $cont1Res->assertStatus(200);
        $cont1Res->assertJsonPath('success', true);
        $cont1Res->assertJsonPath('next_phase', 'strategy_outline');
        $cont1Res->assertJsonPath('pid', 102);

        $afterCont1 = ContentIdea::find($ideaId);
        $this->assertSame(102, $afterCont1->process_pid, 'process_pid should be 102 from triggerStrategyOutline stub');

        // ==== STEP 5: /article-strategy-outline callback — persist prep_data (outline) ====
        $prepPayload = [
            'prep_data' => [
                'outline' => [
                    'sections' => [
                        [
                            'position' => 1,
                            'title' => 'Intro',
                            'word_target' => 200,
                        ],
                    ],
                ],
            ],
        ];
        $savePrepRes = $this->putJson(
            "/api/automation/content-ideas/{$ideaId}/save-prep",
            $prepPayload
        );
        $savePrepRes->assertStatus(200);
        $savePrepRes->assertJsonPath('success', true);

        // ==== STEP 6: Progress tick to 35 ====
        $progress35Res = $this->putJson(
            "/api/automation/content-ideas/{$ideaId}/progress",
            [
                'step' => 'outline',
                'percentage' => 35,
                'message' => 'Outline complete',
            ]
        );
        $progress35Res->assertStatus(200);
        $this->assertSame(35, ContentIdea::find($ideaId)->progress_percentage);

        // ==== STEP 7: continue-pipeline → write ====
        $cont2Res = $this->postJson("/api/automation/content-ideas/{$ideaId}/continue-pipeline");
        $cont2Res->assertStatus(200);
        $cont2Res->assertJsonPath('success', true);
        $cont2Res->assertJsonPath('next_phase', 'write');
        $cont2Res->assertJsonPath('pid', 103);

        $afterCont2 = ContentIdea::find($ideaId);
        $this->assertSame(103, $afterCont2->process_pid, 'process_pid should be 103 from triggerWrite stub');

        // ==== FINAL: both storage locations populated independently ====
        $final = ContentIdea::find($ideaId);

        // Top-level research_data column populated by /save-research.
        $this->assertIsArray($final->research_data);
        $this->assertArrayHasKey('data_points', $final->research_data);

        // generated_article.prep_data populated by /save-prep.
        $this->assertIsArray($final->generated_article);
        $this->assertArrayHasKey('prep_data', $final->generated_article);
        $this->assertSame(
            'Intro',
            $final->generated_article['prep_data']['outline']['sections'][0]['title']
        );
        $this->assertSame(
            200,
            $final->generated_article['prep_data']['outline']['sections'][0]['word_target']
        );
    }

    public function test_legacy_regression_split_flag_off_uses_trigger_prep_never_trigger_research(): void
    {
        config(['services.article_generation.skill_split_enabled' => false]);
        // Force the legacy-refs-based split detector ON so the controller
        // prefers triggerPrep over triggerGeneration (matches Phase B.7 test).
        config(['services.article_generation.refs_prep' => '/tmp/refs-prep.md']);

        $this->authenticate();
        $idea = $this->makeDeepTierEligibleIdea();
        $ideaId = $idea->id;

        $mock = Mockery::mock(ArticleGenerationService::class);

        $mock->shouldReceive('triggerPrep')
            ->once()
            ->with(
                Mockery::on(fn ($arg) => $arg === $ideaId),
                Mockery::on(function ($config) {
                    return is_array($config)
                        && ($config['topic'] ?? null) === 'AI tools for solopreneurs in 2026'
                        && ($config['languages'] ?? null) === ['en'];
                })
            )
            ->andReturn(['success' => true, 'pid' => 999, 'error' => null]);

        // Explicit blockers — split-flow methods MUST NOT fire when flag OFF.
        $mock->shouldNotReceive('triggerResearch');
        $mock->shouldNotReceive('triggerStrategyOutline');
        $mock->shouldNotReceive('triggerGeneration');
        // When split flag is OFF the controller must not resolve the research
        // tier — doing so signals the split branch fired by accident.
        $mock->shouldNotReceive('resolveResearchTier');

        $this->app->instance(ArticleGenerationService::class, $mock);

        $startRes = $this->postJson("/api/admin/content-engine/ideas/{$ideaId}/research", [
            'research_tier' => 'auto',
            'languages' => ['en'],
            'instructions' => 'Legacy path regression test',
        ]);

        $startRes->assertStatus(200);
        $startRes->assertJsonPath('success', true);

        $fresh = ContentIdea::find($ideaId);
        $this->assertSame('researching', $fresh->status);
        $this->assertSame(999, $fresh->process_pid, 'process_pid should be 999 from triggerPrep stub');
    }
}
