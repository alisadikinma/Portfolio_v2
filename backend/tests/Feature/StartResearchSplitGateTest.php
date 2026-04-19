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
 * Phase B.7 — gate ContentIdeaController::startResearch on the
 * services.article_generation.skill_split_enabled feature flag.
 *
 * - flag ON  → call ArticleGenerationService::triggerResearch with resolved tier
 * - flag OFF → legacy triggerPrep path, unchanged
 * - research_tier_override column persists the user's selection
 */
class StartResearchSplitGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Match Phase A.6 / Phase C test-env subpath workaround.
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');

        // SQLite enforces enum CHECK constraints from the original migration
        // that does not list some of the production statuses added later.
        // Disable for the transaction so we can exercise real status transitions.
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

    private function makeDraftIdea(array $overrides = []): ContentIdea
    {
        return ContentIdea::create(array_merge([
            'title' => 'AI tools for solopreneurs in 2026',
            'source' => 'manual',
            'status' => 'draft',
            'virality_score' => 0,
            'research_tier_override' => 'auto',
        ], $overrides));
    }

    public function test_split_flag_on_with_explicit_deep_tier_dispatches_trigger_research(): void
    {
        config(['services.article_generation.skill_split_enabled' => true]);
        // Ensure the legacy-refs-based split detector does not divert us to triggerPrep.
        config(['services.article_generation.refs_prep' => '']);
        $this->authenticate();

        $idea = $this->makeDraftIdea(['virality_score' => 85]);

        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldReceive('resolveResearchTier')
            ->andReturn('deep');
        $mock->shouldReceive('triggerResearch')
            ->once()
            ->with(
                Mockery::on(fn ($arg) => $arg === $idea->id),
                Mockery::on(function ($config) {
                    return is_array($config)
                        && ($config['topic'] ?? null) === 'AI tools for solopreneurs in 2026'
                        && ($config['languages'] ?? null) === ['en']
                        && ($config['instructions'] ?? null) === 'Focus on indie builders';
                }),
                'deep'
            )
            ->andReturn(['success' => true, 'pid' => 11001, 'error' => null]);
        $mock->shouldNotReceive('triggerPrep');
        $mock->shouldNotReceive('triggerGeneration');
        app()->instance(ArticleGenerationService::class, $mock);

        $response = $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/research", [
            'research_tier' => 'deep',
            'languages' => ['en'],
            'instructions' => 'Focus on indie builders',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $fresh = ContentIdea::find($idea->id);
        $this->assertSame('deep', $fresh->research_tier_override);
        $this->assertSame('researching', $fresh->status);
        $this->assertSame(11001, $fresh->process_pid);
    }

    public function test_split_flag_off_uses_legacy_trigger_prep_path(): void
    {
        config(['services.article_generation.skill_split_enabled' => false]);
        // Force split-by-refs detector on so legacy path exercises triggerPrep (not triggerGeneration).
        config(['services.article_generation.refs_prep' => '/tmp/refs-prep.md']);
        $this->authenticate();

        $idea = $this->makeDraftIdea(['virality_score' => 85]);

        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldReceive('triggerPrep')
            ->once()
            ->with(
                Mockery::on(fn ($arg) => $arg === $idea->id),
                Mockery::on(fn ($config) => is_array($config)
                    && ($config['topic'] ?? null) === 'AI tools for solopreneurs in 2026'
                    && ($config['languages'] ?? null) === ['en'])
            )
            ->andReturn(['success' => true, 'pid' => 22002, 'error' => null]);
        $mock->shouldNotReceive('triggerResearch');
        // Even if controller resolves tier eagerly, it must not be used to dispatch.
        $mock->shouldReceive('resolveResearchTier')->zeroOrMoreTimes()->andReturn('deep');
        app()->instance(ArticleGenerationService::class, $mock);

        $response = $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/research", [
            'research_tier' => 'deep',
            'languages' => ['en'],
            'instructions' => 'Legacy path test',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $fresh = ContentIdea::find($idea->id);
        $this->assertSame('researching', $fresh->status);
        $this->assertSame(22002, $fresh->process_pid);
    }

    public function test_split_flag_on_with_auto_tier_and_high_virality_resolves_deep(): void
    {
        config(['services.article_generation.skill_split_enabled' => true]);
        config(['services.article_generation.refs_prep' => '']);
        config(['services.article_generation.deep_research_enabled' => true]);
        $this->authenticate();

        $idea = $this->makeDraftIdea([
            'virality_score' => 85,
            'research_tier_override' => 'auto',
        ]);

        // Do NOT mock resolveResearchTier — we want the real resolver logic to run
        // against the auto override + 85 virality score and produce 'deep'.
        $mock = Mockery::mock(ArticleGenerationService::class)->makePartial();
        $mock->shouldReceive('triggerResearch')
            ->once()
            ->with(
                Mockery::on(fn ($arg) => $arg === $idea->id),
                Mockery::type('array'),
                'deep'
            )
            ->andReturn(['success' => true, 'pid' => 33003, 'error' => null]);
        $mock->shouldNotReceive('triggerPrep');
        $mock->shouldNotReceive('triggerGeneration');
        app()->instance(ArticleGenerationService::class, $mock);

        // Omit research_tier in body → should default to 'auto' and resolve to 'deep'.
        $response = $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/research", [
            'languages' => ['en'],
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $fresh = ContentIdea::find($idea->id);
        $this->assertSame('auto', $fresh->research_tier_override);
        $this->assertSame('researching', $fresh->status);
        $this->assertSame(33003, $fresh->process_pid);
    }
}
