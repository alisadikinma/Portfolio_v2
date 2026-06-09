<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hard image-completion gate (GEO publish-and-forget fix): the publish endpoint
 * must refuse to compile/publish a blog while ANY image segment is unresolved
 * (failed / needs_operator), and must NOT short-circuit when every segment is
 * done or operator-skipped.
 */
class ImageCompletionGateTest extends TestCase
{
    use RefreshDatabase;

    private function ideaWithPrompts(array $prompts, string $status = 'images_ready'): ContentIdea
    {
        return ContentIdea::create([
            'title' => 'Gate idea',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => $status,
            'auto_mode' => false,
            'generated_article' => ['image_prompts' => $prompts],
        ]);
    }

    public function test_publish_blocked_when_a_segment_failed(): void
    {
        $user = User::factory()->create();
        $idea = $this->ideaWithPrompts([
            ['type' => 'cover', 'status' => 'done'],
            ['type' => 'inline', 'status' => 'failed', 'terminal_at' => now()->toIso8601String(), 'needs_operator' => true],
        ]);

        $res = $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/content-engine/ideas/{$idea->id}/publish");

        $res->assertStatus(422);
        $res->assertJsonFragment(['success' => false]);
        $this->assertStringContainsString('not finished generating', $res->json('message'));

        // The idea must NOT have been published.
        $this->assertNull($idea->fresh()->result_post_id);
    }

    public function test_publish_gate_does_not_fire_when_all_done(): void
    {
        $user = User::factory()->create();
        $idea = $this->ideaWithPrompts([
            ['type' => 'cover', 'status' => 'done'],
            ['type' => 'inline', 'status' => 'skipped'],
            ['type' => 'inline', 'status' => 'done'],
        ]);

        $res = $this->actingAs($user, 'sanctum')
            ->postJson("/api/admin/content-engine/ideas/{$idea->id}/publish");

        // Publish may still fail downstream (e.g. missing category) — but it must
        // NOT be blocked by the image-completion gate. Assert the gate message is
        // absent regardless of the final status.
        $message = (string) $res->json('message');
        $this->assertStringNotContainsString('not finished generating', $message);
    }
}
