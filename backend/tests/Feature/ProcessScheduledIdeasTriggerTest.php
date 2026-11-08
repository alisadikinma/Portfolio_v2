<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Services\ArticleGenerationService;
use App\Jobs\DispatchTelegramNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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

    public function test_skill_split_branch_uses_trigger_research_and_records_workflow(): void
    {
        config(['services.article_generation.skill_split_enabled' => true, 'services.article_generation.refs_prep' => null]);
        $idea = $this->dueIdea();

        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldReceive('resolveResearchTier')->once()->andReturn('quick');
        $mock->shouldReceive('triggerResearch')->once()
            ->with($idea->id, Mockery::type('array'), 'quick')
            ->andReturn(['success' => true, 'pid' => 11, 'error' => null]);
        $mock->shouldNotReceive('triggerGeneration');
        $this->app->instance(ArticleGenerationService::class, $mock);

        $this->artisan('content:process-scheduled')->assertExitCode(0);

        $fresh = $idea->fresh();
        $this->assertSame('researching', $fresh->status);
        $this->assertSame(['blog_article'], $fresh->output_types);
        $this->assertSame('research_split', $fresh->workflows[0]['pipeline']);
        $this->assertSame(11, $fresh->workflows[0]['pid']);
    }

    public function test_refs_prep_branch_uses_trigger_prep_and_records_workflow(): void
    {
        config(['services.article_generation.skill_split_enabled' => false, 'services.article_generation.refs_prep' => '/x/refs-prep.md']);
        $idea = $this->dueIdea();

        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldReceive('triggerPrep')->once()
            ->with($idea->id, Mockery::type('array'))
            ->andReturn(['success' => true, 'pid' => 22, 'error' => null]);
        $mock->shouldNotReceive('triggerGeneration');
        $this->app->instance(ArticleGenerationService::class, $mock);

        $this->artisan('content:process-scheduled')->assertExitCode(0);

        $fresh = $idea->fresh();
        $this->assertSame('split', $fresh->workflows[0]['pipeline']);
        $this->assertSame('blog_article', $fresh->workflows[0]['type']);
        $this->assertSame(['blog_article'], $fresh->output_types);
    }

    public function test_stale_schedule_over_24h_is_skipped(): void
    {
        config(['services.article_generation.skill_split_enabled' => false, 'services.article_generation.refs_prep' => null]);
        $stale = $this->dueIdea();
        $stale->update(['scheduled_at' => now()->subHours(25)]);

        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldNotReceive('triggerGeneration');
        $this->app->instance(ArticleGenerationService::class, $mock);

        $this->artisan('content:process-scheduled')->assertExitCode(0);

        $this->assertSame('draft', $stale->fresh()->status);
    }

    public function test_third_trigger_failure_marks_failed_alerts_and_stops_retrying(): void
    {
        Queue::fake();
        config(['services.article_generation.skill_split_enabled' => false, 'services.article_generation.refs_prep' => null]);
        $idea = $this->dueIdea();

        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldReceive('triggerGeneration')->times(3)->andReturn(['success' => false, 'pid' => null, 'error' => 'ssh down']);
        $this->app->instance(ArticleGenerationService::class, $mock);

        $this->artisan('content:process-scheduled')->assertExitCode(0);
        $this->assertSame('draft', $idea->fresh()->status);
        $this->artisan('content:process-scheduled')->assertExitCode(0);
        $this->assertSame('draft', $idea->fresh()->status);
        Queue::assertNothingPushed();

        $this->artisan('content:process-scheduled')->assertExitCode(0);
        $fresh = $idea->fresh();
        $this->assertSame('failed', $fresh->status);
        $this->assertSame('scheduled_trigger_failed', collect($fresh->pipeline_state_log)->last()['reason']);
        Queue::assertPushed(DispatchTelegramNotification::class, fn ($j) => $j->notificationType === 'scheduled_trigger_failed' && $j->contentIdeaId === $idea->id);

        // A fourth tick must not touch it (mock allows only 3 trigger calls).
        $this->artisan('content:process-scheduled')->assertExitCode(0);
    }
}
