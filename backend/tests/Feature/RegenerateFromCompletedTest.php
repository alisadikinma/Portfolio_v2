<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\User;
use App\Services\ArticleGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class RegenerateFromCompletedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');

        // SQLite test DB inherits the initial migration's narrow status
        // whitelist; later ALTER TABLE MODIFY ENUM migrations are MySQL-only
        // and get no-op'd on SQLite. PRAGMA neutralizes the CHECK constraints
        // for this test case. Pattern lifted from AutoPipelineTranslateGateTest.
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }

        $this->actingAs(User::factory()->create(), 'sanctum');
    }

    protected function tearDown(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = OFF');
        }
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function regenerate_article_from_completed_reverts_status_to_researching(): void
    {
        $idea = ContentIdea::create([
            'title' => 'Completed Idea',
            'status' => 'completed',
            'source' => 'manual',
            'pillar' => 'general',
            'languages' => ['id'],
        ]);

        // Mock out the SSH call — we only care about the FSM transition here.
        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldReceive('triggerGeneration')
            ->once()
            ->andReturn(['success' => true, 'method' => 'mock', 'pid' => 1234]);
        $this->app->instance(ArticleGenerationService::class, $mock);

        $response = $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/regenerate", [
            'instructions' => 'make it punchier',
        ]);

        $response->assertOk();
        $this->assertEquals('researching', $idea->fresh()->status, 'Regenerate must flip completed → researching');
    }

    /** @test */
    public function regenerate_image_prompts_from_completed_reverts_to_generating_images(): void
    {
        $idea = ContentIdea::create([
            'title' => 'Completed Idea',
            'status' => 'completed',
            'source' => 'manual',
            'pillar' => 'general',
            'generated_article' => ['image_prompts' => [['heading' => 'Intro', 'concept' => 'X']]],
        ]);

        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldReceive('triggerImages')
            ->once()
            ->andReturn(['success' => true, 'pid' => 4321]);
        $this->app->instance(ArticleGenerationService::class, $mock);

        $response = $this->postJson(
            "/api/admin/content-engine/ideas/{$idea->id}/regenerate-image-prompts",
            []
        );

        $response->assertOk();
        $this->assertEquals(
            'generating_images',
            $idea->fresh()->status,
            'Image-prompt regen must flip completed → generating_images'
        );
    }
}
