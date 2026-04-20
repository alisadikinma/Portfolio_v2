<?php

namespace Tests\Feature;

use App\Enums\ContentIdeaStatus;
use App\Exceptions\InvalidStateTransitionException;
use App\Models\ContentIdea;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Phase D regression suite. Validates the TRANSITIONS allow-map against
 * real transition paths the codebase actually uses. Illegal transitions
 * throw InvalidStateTransitionException (mapped to HTTP 409 by the
 * bootstrap/app.php handler).
 */
class FsmEnforcementRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }
    }

    private function makeIdea(string $status): ContentIdea
    {
        return ContentIdea::create([
            'title' => 'FSM idea',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => $status,
        ]);
    }

    private function assertLegal(string $from, string $to): void
    {
        $idea = $this->makeIdea($from);
        $idea->transitionTo(ContentIdeaStatus::from($to), "legal_{$from}_{$to}");
        $this->assertSame($to, $idea->fresh()->status, "Legal transition {$from} → {$to} should succeed");
    }

    private function assertIllegal(string $from, string $to): void
    {
        $idea = $this->makeIdea($from);
        try {
            $idea->transitionTo(ContentIdeaStatus::from($to), 'illegal_probe');
            $this->fail("Expected InvalidStateTransitionException for {$from} → {$to}");
        } catch (InvalidStateTransitionException $e) {
            $this->assertStringContainsString("Cannot transition {$from}", $e->getMessage());
        }
    }

    /** @test */
    public function legal_draft_to_researching(): void
    {
        $this->assertLegal('draft', 'researching');
    }

    /** @test */
    public function legal_researching_to_article_ready(): void
    {
        $this->assertLegal('researching', 'article_ready');
    }

    /** @test */
    public function legal_article_ready_to_generating_images(): void
    {
        $this->assertLegal('article_ready', 'generating_images');
    }

    /** @test */
    public function legal_generating_images_to_images_ready(): void
    {
        $this->assertLegal('generating_images', 'images_ready');
    }

    /** @test */
    public function legal_generating_images_to_completed_auto_mode_path(): void
    {
        $this->assertLegal('generating_images', 'completed');
    }

    /** @test */
    public function legal_generating_images_to_awaiting_manual_upload(): void
    {
        $this->assertLegal('generating_images', 'awaiting_manual_upload');
    }

    /** @test */
    public function legal_awaiting_manual_upload_to_generating_images(): void
    {
        $this->assertLegal('awaiting_manual_upload', 'generating_images');
    }

    /** @test */
    public function legal_images_ready_to_completed(): void
    {
        $this->assertLegal('images_ready', 'completed');
    }

    /** @test */
    public function legal_completed_to_archived(): void
    {
        $this->assertLegal('completed', 'archived');
    }

    /** @test */
    public function legal_failed_to_researching_resume_path(): void
    {
        $this->assertLegal('failed', 'researching');
    }

    /** @test */
    public function legal_archived_to_draft_restore_path(): void
    {
        $this->assertLegal('archived', 'draft');
    }

    /** @test */
    public function illegal_draft_to_completed(): void
    {
        $this->assertIllegal('draft', 'completed');
    }

    /** @test */
    public function illegal_researching_to_images_ready(): void
    {
        $this->assertIllegal('researching', 'images_ready');
    }

    /** @test */
    public function illegal_archived_to_generating_images(): void
    {
        $this->assertIllegal('archived', 'generating_images');
    }

    /** @test */
    public function illegal_completed_to_draft(): void
    {
        // completed is terminal-ish — only archive allowed. No direct
        // revert-to-draft escape hatch from a published idea.
        $this->assertIllegal('completed', 'draft');
    }

    /** @test */
    public function legal_images_ready_to_draft_revert_path(): void
    {
        // Admin "Revert to Draft" is allowed from images_ready for
        // operator intervention — wipe generated_article, restart.
        $this->assertLegal('images_ready', 'draft');
    }

    /** @test */
    public function transition_can_batch_extra_fields(): void
    {
        $idea = $this->makeIdea('draft');
        $idea->transitionTo(
            ContentIdeaStatus::Researching,
            'batch_check',
            ['progress_percentage' => 15, 'current_step' => 'prep_start']
        );

        $fresh = $idea->fresh();
        $this->assertSame('researching', $fresh->status);
        $this->assertSame(15, $fresh->progress_percentage);
        $this->assertSame('prep_start', $fresh->current_step);
        $this->assertNotEmpty($fresh->pipeline_state_log);
    }
}
