<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\User;
use App\Services\ImageGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * variable.undefined regression: segment_index with no matching image_prompts row
 * left $variationIndex unset, so the controller dispatched a paid GeminiGen job
 * and then crashed with "Undefined variable $variationIndex" (500).
 */
class GenerateSegmentImageUnknownSegmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_segment_index_returns_422_and_does_not_queue_image(): void
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }
        Sanctum::actingAs(User::factory()->create());

        $idea = ContentIdea::create([
            'title' => 'No such segment',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'article_ready',
            'generated_article' => ['image_prompts' => [['type' => 'cover', 'prompt_text' => 'x']]],
        ]);

        $service = \Mockery::mock(ImageGenerationService::class);
        $service->shouldNotReceive('queue');
        $this->app->instance(ImageGenerationService::class, $service);

        $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/generate-segment-image", [
            'segment_index' => 7,
            'prompt' => 'a prompt',
            'style' => 'photoreal',
        ])->assertStatus(422);
    }
}
