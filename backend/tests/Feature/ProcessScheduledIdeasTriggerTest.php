<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Services\ArticleGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * PHPStan baseline method.notFound: the command called a non-existent
 * ArticleGenerationService::trigger(), so every due scheduled idea was moved to
 * researching, threw, and bounced back to draft on each tick (never started).
 */
class ProcessScheduledIdeasTriggerTest extends TestCase
{
    use RefreshDatabase;

    private function dueIdea(): ContentIdea
    {
        return ContentIdea::create([
            'title' => 'Scheduled topic', 'pillar' => 'general', 'priority' => 'low',
            'status' => 'draft', 'languages' => ['id'], 'scheduled_at' => now()->subMinute(),
        ]);
    }

    public function test_due_idea_starts_research_through_a_real_service_method(): void
    {
        config(['services.article_generation.skill_split_enabled' => false, 'services.article_generation.refs_prep' => null]);
        $idea = $this->dueIdea();

        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldReceive('triggerGeneration')->once()
            ->with($idea->id, Mockery::on(fn ($c) => $c['topic'] === 'Scheduled topic' && $c['languages'] === ['id']))
            ->andReturn(['success' => true, 'pid' => 4242, 'error' => null]);
        $this->app->instance(ArticleGenerationService::class, $mock);

        $this->artisan('content:process-scheduled')->assertExitCode(0);

        $fresh = $idea->fresh();
        $this->assertSame('researching', $fresh->status);
        $this->assertSame(4242, $fresh->process_pid);
    }

    public function test_failed_trigger_rolls_the_idea_back_to_draft(): void
    {
        config(['services.article_generation.skill_split_enabled' => false, 'services.article_generation.refs_prep' => null]);
        $idea = $this->dueIdea();

        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldReceive('triggerGeneration')->once()->andReturn(['success' => false, 'pid' => null, 'error' => 'ssh down']);
        $this->app->instance(ArticleGenerationService::class, $mock);

        $this->artisan('content:process-scheduled')->assertExitCode(0);

        $this->assertSame('draft', $idea->fresh()->status);
    }
}
