<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SkipSegmentEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }
        $this->actingAs(User::factory()->create(), 'sanctum');
    }

    private function makeIdea(int $segments = 2): ContentIdea
    {
        $prompts = [];
        for ($i = 0; $i < $segments; $i++) {
            $prompts[] = [
                'type' => $i === 0 ? 'cover' : 'inline',
                'prompt_text' => "Segment {$i}",
                'status' => 'failed',
                'retry_count' => 1,
                'failure_history' => [
                    ['attempt' => 0, 'uuid' => 'old-0', 'reason' => 'boom', 'timestamp' => now()->subMinute()->toIso8601String()],
                ],
            ];
        }

        return ContentIdea::create([
            'title' => 'Skip endpoint idea',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'generating_images',
            'auto_mode' => false,
            'generated_article' => ['image_prompts' => $prompts],
        ]);
    }

    /** @test */
    public function non_cover_skip_marks_terminal_and_appends_history(): void
    {
        $idea = $this->makeIdea(segments: 2);

        $response = $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/skip-segment/1");

        $response->assertOk();
        $response->assertJsonPath('data.status', 'skipped');

        $idea->refresh();
        $seg = $idea->generated_article['image_prompts'][1];
        $this->assertSame('skipped', $seg['status']);
        $this->assertNotNull($seg['terminal_at']);
        $last = end($seg['failure_history']);
        $this->assertSame('user_skip', $last['reason']);
    }

    /** @test */
    public function cover_skip_without_force_returns_409(): void
    {
        $idea = $this->makeIdea();

        $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/skip-segment/0")
            ->assertStatus(409)
            ->assertJsonPath('code', 'COVER_SKIP_REQUIRES_FORCE');
    }

    /** @test */
    public function cover_skip_with_force_true_succeeds(): void
    {
        $idea = $this->makeIdea();

        $this->postJson(
            "/api/admin/content-engine/ideas/{$idea->id}/skip-segment/0",
            ['force' => true]
        )->assertOk();

        $idea->refresh();
        $this->assertSame('skipped', $idea->generated_article['image_prompts'][0]['status']);
    }

    /** @test */
    public function skip_refuses_when_status_is_done(): void
    {
        $idea = ContentIdea::create([
            'title' => 'Already done',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'generating_images',
            'auto_mode' => false,
            'generated_article' => [
                'image_prompts' => [
                    ['type' => 'cover', 'prompt_text' => 'c', 'status' => 'done'],
                    ['type' => 'inline', 'prompt_text' => 'b', 'status' => 'done'],
                ],
            ],
        ]);

        $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/skip-segment/1")
            ->assertStatus(409);
    }

    /** @test */
    public function skip_returns_404_for_missing_idea_or_segment(): void
    {
        $this->postJson('/api/admin/content-engine/ideas/99999/skip-segment/0')
            ->assertStatus(404);

        $idea = $this->makeIdea(segments: 1);
        $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/skip-segment/9", ['force' => true])
            ->assertStatus(404);
    }
}
