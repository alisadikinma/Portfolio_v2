<?php

namespace Tests\Feature;

use App\Enums\ContentIdeaStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\ContentIdea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentIdeaStatusTransitionsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function enum_exposes_nine_cases(): void
    {
        $values = collect(ContentIdeaStatus::cases())->pluck('value')->all();

        $this->assertCount(9, $values);
        $this->assertEqualsCanonicalizing([
            'draft',
            'researching',
            'article_ready',
            'awaiting_manual_upload',
            'generating_images',
            'images_ready',
            'completed',
            'failed',
            'archived',
        ], $values);
    }

    /** @test */
    public function draft_can_transition_to_researching(): void
    {
        $this->assertTrue(
            ContentIdeaStatus::Draft->canTransitionTo(ContentIdeaStatus::Researching)
        );
    }

    /** @test */
    public function draft_cannot_transition_to_completed(): void
    {
        $this->assertFalse(
            ContentIdeaStatus::Draft->canTransitionTo(ContentIdeaStatus::Completed)
        );
    }

    /** @test */
    public function archived_can_restore_to_draft(): void
    {
        $this->assertTrue(
            ContentIdeaStatus::Archived->canTransitionTo(ContentIdeaStatus::Draft)
        );
    }

    /** @test */
    public function completed_can_revert_to_researching_for_regenerate(): void
    {
        // Admin-triggered Regenerate on a Completed idea must reopen the
        // pipeline. The live Post stays at /blog/{slug} during regen —
        // ContentPublishService uses Post::updateOrCreate keyed on id when
        // the user re-publishes.
        $this->assertTrue(
            ContentIdeaStatus::Completed->canTransitionTo(ContentIdeaStatus::Researching)
        );
    }

    /** @test */
    public function completed_can_revert_to_generating_images_for_image_regenerate(): void
    {
        // Same rationale as article regen, but only images change (article
        // stays locked). Admin clicks Regen on a Completed idea from the
        // Images step of the preview.
        $this->assertTrue(
            ContentIdeaStatus::Completed->canTransitionTo(ContentIdeaStatus::GeneratingImages)
        );
    }

    /** @test */
    public function failed_can_resume_to_researching_and_other_stages(): void
    {
        $this->assertTrue(ContentIdeaStatus::Failed->canTransitionTo(ContentIdeaStatus::Researching));
        $this->assertTrue(ContentIdeaStatus::Failed->canTransitionTo(ContentIdeaStatus::ArticleReady));
        $this->assertTrue(ContentIdeaStatus::Failed->canTransitionTo(ContentIdeaStatus::GeneratingImages));
        $this->assertTrue(ContentIdeaStatus::Failed->canTransitionTo(ContentIdeaStatus::Archived));
    }

    /** @test */
    public function transition_to_legal_target_updates_status_and_appends_log(): void
    {
        $idea = ContentIdea::create([
            'title' => 'Test idea',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'draft',
        ]);

        $idea->transitionTo(ContentIdeaStatus::Researching, 'unit_test');

        $idea->refresh();
        $this->assertSame('researching', $idea->status);
        $this->assertIsArray($idea->pipeline_state_log);
        $this->assertCount(1, $idea->pipeline_state_log);
        $this->assertSame('draft', $idea->pipeline_state_log[0]['from']);
        $this->assertSame('researching', $idea->pipeline_state_log[0]['to']);
        $this->assertSame('unit_test', $idea->pipeline_state_log[0]['reason']);
        $this->assertArrayHasKey('timestamp', $idea->pipeline_state_log[0]);
    }

    /** @test */
    public function transition_to_illegal_target_throws_exception(): void
    {
        $idea = ContentIdea::create([
            'title' => 'Test idea',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'draft',
        ]);

        $this->expectException(InvalidStateTransitionException::class);
        $this->expectExceptionMessageMatches('/Cannot transition draft.*completed/');

        $idea->transitionTo(ContentIdeaStatus::Completed);
    }

    /** @test */
    public function transition_accepts_string_status_argument(): void
    {
        $idea = ContentIdea::create([
            'title' => 'Test idea',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'draft',
        ]);

        $idea->transitionTo('archived', 'via_string');

        $this->assertSame('archived', $idea->fresh()->status);
    }

    /** @test */
    public function log_is_bounded_to_twenty_entries_fifo(): void
    {
        $idea = ContentIdea::create([
            'title' => 'Test idea',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'draft',
        ]);

        // Pre-seed 22 log entries directly
        $seed = [];
        for ($i = 0; $i < 22; $i++) {
            $seed[] = [
                'from' => 'draft',
                'to' => 'researching',
                'reason' => "seed_{$i}",
                'timestamp' => now()->subMinutes(22 - $i)->toIso8601String(),
            ];
        }
        $idea->pipeline_state_log = $seed;
        $idea->save();

        // Now trigger one legal transition — should keep last 20 only
        $idea->transitionTo(ContentIdeaStatus::Researching, 'trim_check');

        $idea->refresh();
        $this->assertCount(20, $idea->pipeline_state_log);
        // First surviving entry should be seed_3 (0..2 evicted, 3..21 retained + new one — wait, let's verify)
        // 22 seeds + 1 new = 23 -> slice(-20) = 3..22
        $this->assertSame('seed_3', $idea->pipeline_state_log[0]['reason']);
        $this->assertSame('trim_check', $idea->pipeline_state_log[19]['reason']);
    }
}
