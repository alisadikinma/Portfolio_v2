<?php

namespace Tests\Feature;

use App\Jobs\DispatchTelegramNotification;
use App\Jobs\RetryImageSegmentJob;
use App\Models\ContentIdea;
use App\Models\ImageGenerationJob;
use App\Models\Setting;
use App\Services\ImageGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Integration tests for ImageGenerationService::handleSegmentFailure.
 * Uses real DB (RefreshDatabase) + Queue::fake so we can assert exact
 * RetryImageSegmentJob + DispatchTelegramNotification dispatches.
 */
class SegmentFailureAutoRetryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // SQLite CHECK constraint on status only allows original enum values;
        // disable during these tests so we can use 'generating_images'.
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }
    }

    private function makeJob(string $uuid, string $error = 'boom'): ImageGenerationJob
    {
        $job = new ImageGenerationJob();
        $job->uuid = $uuid;
        $job->type = 'hero';
        $job->prompt = 'Cover';
        $job->status = 'failed';
        $job->error_message = $error;
        return $job;
    }

    /** @test */
    public function transient_failure_in_auto_mode_schedules_retry_job_with_delay(): void
    {
        Queue::fake();

        $idea = ContentIdea::create([
            'title' => 'Auto idea',
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
                            ['url' => null, 'job_uuid' => 'job-aaa', 'status' => 'generating'],
                        ],
                    ],
                ],
            ],
        ]);

        app(ImageGenerationService::class)->handleSegmentFailure($this->makeJob('job-aaa', 'timeout'), 'timeout');

        $idea->refresh();
        $segment = $idea->generated_article['image_prompts'][0];
        $this->assertSame(1, $segment['retry_count']);
        $this->assertNotEmpty($segment['failure_history']);
        $this->assertNull($segment['terminal_at'], 'terminal_at must be null before cap reached');

        Queue::assertPushed(RetryImageSegmentJob::class, function ($pushedJob) use ($idea) {
            return $pushedJob->contentIdeaId === $idea->id && $pushedJob->segmentIndex === 0;
        });
    }

    /** @test */
    public function exhaustion_sets_terminal_at_and_dispatches_segment_exhausted_alert(): void
    {
        Queue::fake();

        Setting::create(['group' => 'telegram', 'key' => 'telegram_enabled', 'value' => 'true', 'type' => 'text']);
        Setting::create(['group' => 'telegram', 'key' => 'telegram_notify_segment_failed', 'value' => 'true', 'type' => 'text']);

        $idea = ContentIdea::create([
            'title' => 'Exhaust idea',
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
                        'failure_history' => [
                            ['attempt' => 0, 'uuid' => 'old-1', 'reason' => 'boom', 'timestamp' => now()->subMinutes(3)->toIso8601String()],
                            ['attempt' => 1, 'uuid' => 'old-2', 'reason' => 'boom', 'timestamp' => now()->subMinutes(2)->toIso8601String()],
                        ],
                        'terminal_at' => null,
                        'variations' => [['url' => null, 'job_uuid' => 'job-final', 'status' => 'generating']],
                    ],
                ],
            ],
        ]);

        app(ImageGenerationService::class)->handleSegmentFailure($this->makeJob('job-final', 'final boom'), 'final boom');

        $idea->refresh();
        $segment = $idea->generated_article['image_prompts'][0];
        $this->assertSame(3, $segment['retry_count']);
        $this->assertNotNull($segment['terminal_at'], 'terminal_at must be set after cap');
        $this->assertSame('failed', $segment['status']);

        Queue::assertPushed(DispatchTelegramNotification::class, function ($pushed) use ($idea) {
            return $pushed->contentIdeaId === $idea->id && $pushed->notificationType === 'segment_retry_exhausted';
        });
        Queue::assertNotPushed(RetryImageSegmentJob::class);
    }

    /** @test */
    public function cover_segment_terminal_failure_also_dispatches_cover_critical(): void
    {
        Queue::fake();

        $idea = ContentIdea::create([
            'title' => 'Cover critical idea',
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
                        'variations' => [['url' => null, 'job_uuid' => 'cover-final', 'status' => 'generating']],
                    ],
                ],
            ],
        ]);

        app(ImageGenerationService::class)->handleSegmentFailure($this->makeJob('cover-final', 'final'), 'final');

        Queue::assertPushed(DispatchTelegramNotification::class, function ($pushed) {
            return $pushed->notificationType === 'cover_critical';
        });
        Queue::assertPushed(DispatchTelegramNotification::class, function ($pushed) {
            return $pushed->notificationType === 'segment_retry_exhausted';
        });
    }

    /** @test */
    public function non_auto_mode_does_not_schedule_retry_job(): void
    {
        Queue::fake();

        $idea = ContentIdea::create([
            'title' => 'Manual idea',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'generating_images',
            'auto_mode' => false,
            'generated_article' => [
                'image_prompts' => [
                    [
                        'type' => 'cover',
                        'prompt_text' => 'Cover',
                        'status' => 'generating',
                        'retry_count' => 0,
                        'failure_history' => [],
                        'terminal_at' => null,
                        'variations' => [['url' => null, 'job_uuid' => 'manual-boom', 'status' => 'generating']],
                    ],
                ],
            ],
        ]);

        app(ImageGenerationService::class)->handleSegmentFailure($this->makeJob('manual-boom', 'boom'), 'boom');

        $idea->refresh();
        $segment = $idea->generated_article['image_prompts'][0];
        $this->assertSame(1, $segment['retry_count']);
        $this->assertNull($segment['terminal_at']);

        Queue::assertNotPushed(RetryImageSegmentJob::class);
        Queue::assertNotPushed(DispatchTelegramNotification::class);
    }

    /** @test */
    public function retry_count_semantics_single_increment_per_failure(): void
    {
        // Regression: earlier code path double-counted — handleSegmentFailure
        // bumped retry_count + RetryImageSegmentJob -> retrySegment ALSO
        // bumped, effectively halving the 3-attempt budget. retrySegment
        // no longer increments; only handleSegmentFailure does.
        Queue::fake();

        $idea = ContentIdea::create([
            'title' => 'Single-increment',
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
                            ['url' => null, 'job_uuid' => 'attempt-1', 'status' => 'generating'],
                        ],
                    ],
                ],
            ],
        ]);

        // First failure: retry_count 0 → 1
        app(ImageGenerationService::class)->handleSegmentFailure($this->makeJob('attempt-1', 'boom'), 'boom');
        $idea->refresh();
        $this->assertSame(1, $idea->generated_article['image_prompts'][0]['retry_count']);
        $this->assertNull($idea->generated_article['image_prompts'][0]['terminal_at']);

        // Simulate auto-retry dispatch via retrySegment — must NOT bump counter
        $article = $idea->generated_article;
        $article['image_prompts'][0]['variations'] = [
            ['url' => null, 'job_uuid' => 'attempt-2', 'status' => 'generating'],
        ];
        $article['image_prompts'][0]['status'] = 'generating';
        $idea->update(['generated_article' => $article]);

        $idea->refresh();
        $this->assertSame(1, $idea->generated_article['image_prompts'][0]['retry_count'], 'retry_count must stay at 1 after dispatch');

        // Second failure: retry_count 1 → 2
        app(ImageGenerationService::class)->handleSegmentFailure($this->makeJob('attempt-2', 'boom2'), 'boom2');
        $idea->refresh();
        $this->assertSame(2, $idea->generated_article['image_prompts'][0]['retry_count']);
        $this->assertNull($idea->generated_article['image_prompts'][0]['terminal_at']);

        // Third failure: retry_count 2 → 3 (cap) → terminal
        $article = $idea->generated_article;
        $article['image_prompts'][0]['variations'] = [
            ['url' => null, 'job_uuid' => 'attempt-3', 'status' => 'generating'],
        ];
        $article['image_prompts'][0]['status'] = 'generating';
        $idea->update(['generated_article' => $article]);

        app(ImageGenerationService::class)->handleSegmentFailure($this->makeJob('attempt-3', 'boom3'), 'boom3');
        $idea->refresh();
        $this->assertSame(3, $idea->generated_article['image_prompts'][0]['retry_count']);
        $this->assertNotNull($idea->generated_article['image_prompts'][0]['terminal_at'], 'terminal_at set exactly at 3rd failure, confirming 3-attempt budget');
    }

    /** @test */
    public function no_match_silently_ignores(): void
    {
        Queue::fake();

        ContentIdea::create([
            'title' => 'No match',
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
                        'variations' => [['url' => null, 'job_uuid' => 'different-uuid', 'status' => 'generating']],
                    ],
                ],
            ],
        ]);

        app(ImageGenerationService::class)->handleSegmentFailure($this->makeJob('nonexistent-uuid', 'boom'), 'boom');

        Queue::assertNotPushed(RetryImageSegmentJob::class);
        Queue::assertNotPushed(DispatchTelegramNotification::class);
    }
}
