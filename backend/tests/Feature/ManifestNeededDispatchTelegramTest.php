<?php

namespace Tests\Feature;

use App\Jobs\DispatchTelegramNotification;
use App\Models\ContentIdea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

/**
 * Phase F: when the plugin posts step=manifest_needed to the progress endpoint
 * with a manifest that includes entity[] slots, the backend must:
 *   1. Persist manifest to content_ideas.pending_manifest
 *   2. Flip status (when not in auto_mode) to awaiting_manual_upload
 *   3. Dispatch DispatchTelegramNotification so the admin gets a phone alert
 */
class ManifestNeededDispatchTelegramTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // APP_URL subpath workaround (XAMPP dev URLs include
        // /Portfolio_v2/backend/public). Same pattern as
        // ContinuePipelineResearchSplitTest etc.
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');

        // SQLite rejects the later MySQL-only status enum ALTERs (e.g.
        // awaiting_manual_upload). Disable check constraints for the test.
        if (\DB::connection()->getDriverName() === 'sqlite') {
            \DB::statement('PRAGMA ignore_check_constraints = ON');
        }

        // Automation routes are behind auth:sanctum + throttle:60,1
        $this->actingAs(User::factory()->create(), 'sanctum');
    }

    /** @test */
    public function progress_with_manifest_needed_persists_pending_manifest_and_dispatches_telegram_job(): void
    {
        Bus::fake();

        $idea = ContentIdea::create([
            'pillar' => 'ai_agents',
            'title' => 'Anthropic CEO Visits the White House',
            'status' => 'draft',
            'priority' => 'medium',
            'auto_mode' => false,
        ]);

        $manifest = [
            'brand' => [],
            'entity' => [
                [
                    'entity_name' => 'Dario Amodei',
                    'entity_type' => 'person',
                    'qid' => 'Q115468560',
                    'used_in' => ['Cover'],
                    'status' => 'missing',
                    'reason' => 'CC-BY-SA license not allowed',
                    'required' => true,
                ],
                [
                    'entity_name' => 'White House',
                    'entity_type' => 'landmark',
                    'qid' => 'Q35525',
                    'used_in' => ['Cover'],
                    'status' => 'fetched',
                    'fetched_url' => 'https://alisadikinma.com/storage/entity-refs/landmark/Q35525_white-house.jpg',
                    'license' => 'PD-USGov',
                    'required' => false,
                ],
            ],
        ];

        $response = $this->putJson("/api/automation/content-ideas/{$idea->id}/progress", [
            'step' => 'manifest_needed',
            'percentage' => 20,
            'message' => '2 entities detected — 1 missing, 1 fetched',
            'manifest' => $manifest,
        ]);

        $response->assertOk();

        $idea->refresh();

        $this->assertSame($manifest, $idea->pending_manifest);
        // Backward-compat: legacy brand_manifest location still populated
        $this->assertSame($manifest, $idea->generated_article['brand_manifest'] ?? null);

        Bus::assertDispatched(DispatchTelegramNotification::class, function ($job) use ($idea) {
            return $job->contentIdeaId === $idea->id
                && $job->notificationType === 'manifest_needed';
        });
    }

    /** @test */
    public function progress_without_manifest_key_does_not_dispatch_telegram(): void
    {
        Bus::fake();

        $idea = ContentIdea::create([
            'pillar' => 'ai_agents',
            'title' => 'Routine progress',
            'status' => 'draft',
            'priority' => 'medium',
        ]);

        $response = $this->putJson("/api/automation/content-ideas/{$idea->id}/progress", [
            'step' => 'context_extracted',
            'percentage' => 15,
            'message' => 'entities extracted',
        ]);

        $response->assertOk();

        Bus::assertNotDispatched(DispatchTelegramNotification::class);
    }

    /** @test */
    public function progress_manifest_in_auto_mode_does_not_change_status(): void
    {
        Bus::fake();

        $idea = ContentIdea::create([
            'pillar' => 'ai_agents',
            'title' => 'Auto mode test',
            'status' => 'draft',
            'priority' => 'medium',
            'auto_mode' => true,
        ]);

        $this->putJson("/api/automation/content-ideas/{$idea->id}/progress", [
            'step' => 'manifest_needed',
            'percentage' => 20,
            'message' => 'entities detected',
            'manifest' => ['brand' => [], 'entity' => []],
        ])->assertOk();

        $idea->refresh();
        // In auto mode, status must NOT flip to awaiting_manual_upload
        $this->assertSame('draft', $idea->status);
    }
}
