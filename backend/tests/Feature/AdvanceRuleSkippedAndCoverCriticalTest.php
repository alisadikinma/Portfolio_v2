<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\ImageGenerationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Verifies the ProcessPendingImages::syncToContentIdea advance rule correctly
 * accepts 'skipped' + terminally-'failed' segments AND blocks on cover-critical
 * failure (segment 0 terminal).
 */
class AdvanceRuleSkippedAndCoverCriticalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }
    }

    private function runPollerWithSuccessFor(string $uuid, string $mediaUrl): void
    {
        Http::fake([
            "api.snapgen.ai/uapi/v1/history/{$uuid}" => Http::response([
                'uuid' => $uuid,
                'status' => 2,
                'generate_result' => $mediaUrl,
            ], 200),
            // Image bytes
            $mediaUrl => Http::response(str_repeat('A', 2048), 200),
        ]);

        $this->artisan('blog:process-images')->assertExitCode(0);
    }

    /** @test */
    public function advances_when_one_done_one_skipped_non_cover(): void
    {
        Queue::fake();

        ImageGenerationJob::create([
            'uuid' => 'inline-ok',
            'type' => 'inline',
            'prompt' => 'body',
            'status' => 'processing',
        ]);

        $idea = ContentIdea::create([
            'title' => 'Skipped advance',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'generating_images',
            'auto_mode' => false,
            'generated_article' => [
                'image_prompts' => [
                    [
                        'type' => 'cover',
                        'prompt_text' => 'Cover',
                        'status' => 'done',
                        'variations' => [['url' => 'https://example.com/c.jpg', 'job_uuid' => 'cover-ok', 'status' => 'done']],
                        'generated_url' => 'https://example.com/c.jpg',
                    ],
                    [
                        'type' => 'inline',
                        'prompt_text' => 'Body',
                        'status' => 'skipped',
                        'terminal_at' => now()->toIso8601String(),
                        'variations' => [],
                    ],
                    [
                        'type' => 'inline',
                        'prompt_text' => 'Body 2',
                        'status' => 'generating',
                        'variations' => [['url' => null, 'job_uuid' => 'inline-ok', 'status' => 'generating']],
                    ],
                ],
            ],
        ]);

        $this->runPollerWithSuccessFor('inline-ok', 'https://example.com/body2.jpg');

        $idea->refresh();
        $this->assertSame('images_ready', $idea->status, 'idea should advance when skipped is non-cover and others resolve');
    }

    /** @test */
    public function blocks_advance_when_cover_is_terminally_failed(): void
    {
        Queue::fake();

        ImageGenerationJob::create([
            'uuid' => 'inline-ok-2',
            'type' => 'inline',
            'prompt' => 'body',
            'status' => 'processing',
        ]);

        $idea = ContentIdea::create([
            'title' => 'Cover critical block',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'generating_images',
            'auto_mode' => false,
            'generated_article' => [
                'image_prompts' => [
                    [
                        'type' => 'cover',
                        'prompt_text' => 'Cover',
                        'status' => 'failed',
                        'retry_count' => 3,
                        'terminal_at' => now()->toIso8601String(),
                        'variations' => [['url' => null, 'job_uuid' => 'cover-dead', 'status' => 'failed']],
                    ],
                    [
                        'type' => 'inline',
                        'prompt_text' => 'Body',
                        'status' => 'generating',
                        'variations' => [['url' => null, 'job_uuid' => 'inline-ok-2', 'status' => 'generating']],
                    ],
                ],
            ],
        ]);

        $this->runPollerWithSuccessFor('inline-ok-2', 'https://example.com/body.jpg');

        $idea->refresh();
        $this->assertSame('generating_images', $idea->status, 'idea must stay blocked when cover is cover-critical');
    }
}
