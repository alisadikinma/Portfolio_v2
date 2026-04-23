<?php

namespace Tests\Feature;

use App\Enums\ContentIdeaStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\ContentIdea;
use App\Services\PipelineGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class PipelineGuardTest extends TestCase
{
    use RefreshDatabase;

    private PipelineGuard $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new PipelineGuard();
    }

    /** @test */
    public function advance_applies_legal_transition_and_logs_success(): void
    {
        Log::spy();

        $idea = ContentIdea::create([
            'title' => 'Guard idea',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'draft',
        ]);

        $result = $this->guard->advance($idea, ContentIdeaStatus::Researching, 'auto_pipeline_start');

        $this->assertSame('researching', $result->status);
        $this->assertCount(1, $result->pipeline_state_log);

        Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context) use ($idea) {
                return str_contains($message, "ContentIdea #{$idea->id}")
                    && str_contains($message, 'auto_pipeline_start')
                    && str_contains($message, 'draft → researching');
            })
            ->once();
    }

    /** @test */
    public function advance_on_illegal_transition_logs_error_and_rethrows(): void
    {
        Log::spy();

        $idea = ContentIdea::create([
            'title' => 'Guard idea',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'draft',
        ]);

        $caught = false;
        try {
            $this->guard->advance($idea, ContentIdeaStatus::Completed, 'bad_jump');
        } catch (InvalidStateTransitionException $e) {
            $caught = true;
            $this->assertStringContainsString('Cannot transition draft', $e->getMessage());
        }

        $this->assertTrue($caught, 'Expected InvalidStateTransitionException to be thrown');
        Log::shouldHaveReceived('error')->once();
    }

    /** @test */
    public function advance_passes_context_to_log(): void
    {
        Log::spy();

        $idea = ContentIdea::create([
            'title' => 'Guard idea',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'draft',
        ]);

        $this->guard->advance(
            $idea,
            ContentIdeaStatus::Researching,
            'auto_pipeline_start',
            ['attempt' => 1, 'source' => 'cron']
        );

        Log::shouldHaveReceived('info')
            ->withArgs(function ($message, $context) {
                return ($context['attempt'] ?? null) === 1
                    && ($context['source'] ?? null) === 'cron';
            })
            ->once();
    }

    /** @test */
    public function advance_rejects_non_forward_reverse_transitions(): void
    {
        $idea = ContentIdea::create([
            'title' => 'Guard idea',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'completed',
        ]);

        $this->expectException(InvalidStateTransitionException::class);

        $this->guard->advance($idea, ContentIdeaStatus::Draft, 'reverse_attempt');
    }
}
