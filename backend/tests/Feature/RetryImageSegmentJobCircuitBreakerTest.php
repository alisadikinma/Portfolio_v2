<?php

namespace Tests\Feature;

use App\Jobs\RetryImageSegmentJob;
use App\Models\ContentIdea;
use App\Services\GeminiGenCircuitBreaker;
use App\Services\ImageGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Phase F — RetryImageSegmentJob respects circuit breaker state.
 *
 * When the breaker is OPEN, the job MUST NOT call retrySegment() (which
 * would increment retry_count and burn the operator's auto-retry budget
 * for what is an outage-class failure, not a prompt-class one). Instead
 * it re-queues itself with a 5-minute delay so it can re-attempt after
 * the breaker has had a chance to half-open + close.
 *
 * When the breaker is CLOSED, the existing happy-path behavior is
 * preserved — service->retrySegment is called normally.
 */
class RetryImageSegmentJobCircuitBreakerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        // SQLite CHECK constraint on status only allows original enum
        // values; disable so we can use 'generating_images'.
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }
    }

    /** @test */
    public function re_queues_with_delay_when_circuit_open_and_does_not_increment_retry_count(): void
    {
        // Trip breaker via 5 server-side failures (≥ threshold).
        $breaker = app(GeminiGenCircuitBreaker::class);
        for ($i = 0; $i < 5; $i++) {
            $breaker->recordFailure(503);
        }
        $this->assertSame('open', $breaker->state(), 'precondition: breaker must be open');

        Queue::fake();

        $idea = ContentIdea::create([
            'title' => 'Circuit-open retry idea',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'generating_images',
            'auto_mode' => true,
            'generated_article' => [
                'image_prompts' => [
                    [
                        'type' => 'cover',
                        'prompt_text' => 'Cover',
                        'status' => 'failed',
                        'retry_count' => 1,
                        'failure_history' => [],
                        'terminal_at' => null,
                        'variations' => [
                            ['url' => null, 'job_uuid' => 'job-xxx', 'status' => 'failed'],
                        ],
                    ],
                ],
            ],
        ]);

        $initialRetryCount = $idea->generated_article['image_prompts'][0]['retry_count'];

        // Hard-fail if the service is called at all — gate must block first.
        $serviceMock = Mockery::mock(ImageGenerationService::class);
        $serviceMock->shouldNotReceive('retrySegment');

        $job = new RetryImageSegmentJob($idea->id, 0);
        $job->handle($serviceMock);

        // Assert: the job re-queued itself with delay.
        Queue::assertPushed(RetryImageSegmentJob::class, function ($pushed) use ($idea) {
            return $pushed->contentIdeaId === $idea->id
                && $pushed->segmentIndex === 0
                && $pushed->delay !== null;
        });

        // Assert: retry_count UNCHANGED — the operator's manual-retry
        // budget is preserved for genuine prompt-class failures.
        $idea->refresh();
        $this->assertSame(
            $initialRetryCount,
            $idea->generated_article['image_prompts'][0]['retry_count'],
            'retry_count must not increment while the breaker is open'
        );
    }

    /** @test */
    public function dispatches_normally_when_circuit_closed(): void
    {
        // Default state is closed.
        $breaker = app(GeminiGenCircuitBreaker::class);
        $this->assertSame('closed', $breaker->state(), 'precondition: breaker must be closed');

        Queue::fake();

        $idea = ContentIdea::create([
            'title' => 'Circuit-closed retry idea',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'generating_images',
            'auto_mode' => true,
            'generated_article' => [
                'image_prompts' => [
                    [
                        'type' => 'cover',
                        'prompt_text' => 'Cover',
                        'status' => 'failed',
                        'retry_count' => 1,
                        'failure_history' => [],
                        'terminal_at' => null,
                        'variations' => [
                            ['url' => null, 'job_uuid' => 'job-yyy', 'status' => 'failed'],
                        ],
                    ],
                ],
            ],
        ]);

        // Closed-circuit happy path MUST call retrySegment exactly once.
        $serviceMock = Mockery::mock(ImageGenerationService::class);
        $serviceMock->shouldReceive('retrySegment')
            ->once()
            ->with(Mockery::on(fn($arg) => $arg instanceof ContentIdea && $arg->id === $idea->id), 0);

        $job = new RetryImageSegmentJob($idea->id, 0);
        $job->handle($serviceMock);

        // And it MUST NOT re-queue itself on the closed path.
        Queue::assertNotPushed(RetryImageSegmentJob::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
