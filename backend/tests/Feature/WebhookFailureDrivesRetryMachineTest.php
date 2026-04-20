<?php

namespace Tests\Feature;

use App\Jobs\DispatchTelegramNotification;
use App\Jobs\RetryImageSegmentJob;
use App\Models\ContentIdea;
use App\Models\ImageGenerationJob;
use App\Services\ImageGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Regression: GeminiGen webhook failure callback must drive the segment
 * retry state machine (retry_count + failure_history + terminal_at +
 * auto-retry). Previously the webhook path only called
 * updateContentIdeaSegment which flipped variation status to 'failed'
 * but never bumped retry_count, so the UI stayed stuck on "Generating…"
 * and auto-retry never fired.
 */
class WebhookFailureDrivesRetryMachineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }
    }

    /** @test */
    public function webhook_failure_bumps_retry_count_and_schedules_retry_in_auto_mode(): void
    {
        Queue::fake();

        $idea = ContentIdea::create([
            'title' => 'Webhook retry idea',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'generating_images',
            'auto_mode' => true,
            'generated_article' => [
                'image_prompts' => [
                    [
                        'type' => 'cover',
                        'prompt_text' => 'Cover',
                        'status' => 'generating',
                        'retry_count' => 0,
                        'failure_history' => [],
                        'terminal_at' => null,
                        'variations' => [
                            ['url' => null, 'job_uuid' => 'webhook-boom', 'status' => 'generating'],
                        ],
                    ],
                ],
            ],
        ]);

        ImageGenerationJob::create([
            'uuid' => 'webhook-boom',
            'type' => 'hero',
            'prompt' => 'Cover prompt',
            'status' => 'processing',
        ]);

        // Simulate GeminiGen IMAGE_GENERATION_FAILED webhook.
        app(ImageGenerationService::class)->handleWebhook(
            'webhook-boom',
            'IMAGE_GENERATION_FAILED',
            ['error_message' => 'Model timed out']
        );

        $idea->refresh();
        $segment = $idea->generated_article['image_prompts'][0];
        $this->assertSame(1, $segment['retry_count'], 'retry_count must be bumped by the webhook path');
        $this->assertSame('failed', $segment['status']);
        $this->assertNotEmpty($segment['failure_history']);
        $this->assertSame('Model timed out', end($segment['failure_history'])['reason']);
        $this->assertNull($segment['terminal_at'], 'terminal_at stays null below cap');

        Queue::assertPushed(RetryImageSegmentJob::class, fn ($j) =>
            $j->contentIdeaId === $idea->id && $j->segmentIndex === 0
        );
    }

    /** @test */
    public function webhook_failure_at_cap_dispatches_telegram_exhausted_alert(): void
    {
        Queue::fake();

        $idea = ContentIdea::create([
            'title' => 'Webhook exhaust',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'generating_images',
            'auto_mode' => true,
            'generated_article' => [
                'image_prompts' => [
                    [
                        'type' => 'cover',
                        'prompt_text' => 'Cover',
                        'status' => 'generating',
                        'retry_count' => 2,
                        'failure_history' => [],
                        'terminal_at' => null,
                        'variations' => [
                            ['url' => null, 'job_uuid' => 'webhook-final', 'status' => 'generating'],
                        ],
                    ],
                ],
            ],
        ]);

        ImageGenerationJob::create([
            'uuid' => 'webhook-final',
            'type' => 'hero',
            'prompt' => 'Cover',
            'status' => 'processing',
        ]);

        app(ImageGenerationService::class)->handleWebhook(
            'webhook-final',
            'IMAGE_GENERATION_FAILED',
            ['error_message' => 'Final boom']
        );

        $idea->refresh();
        $segment = $idea->generated_article['image_prompts'][0];
        $this->assertSame(3, $segment['retry_count']);
        $this->assertNotNull($segment['terminal_at']);

        Queue::assertPushed(DispatchTelegramNotification::class, fn ($j) =>
            $j->notificationType === 'segment_retry_exhausted'
        );
        Queue::assertPushed(DispatchTelegramNotification::class, fn ($j) =>
            $j->notificationType === 'cover_critical'
        );
        Queue::assertNotPushed(RetryImageSegmentJob::class);
    }
}
