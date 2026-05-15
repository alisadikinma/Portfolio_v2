<?php

namespace App\Jobs;

use App\Models\ContentIdea;
use App\Services\GeminiGenCircuitBreaker;
use App\Services\ImageGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued retry for a single failed image segment. Dispatched by
 * ImageGenerationService::handleSegmentFailure when retry_count < MAX
 * and the parent idea is in auto_mode. Uses a 60-second delay so the
 * GeminiGen side has time to recover from transient errors.
 */
class RetryImageSegmentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $contentIdeaId;
    public int $segmentIndex;

    public function __construct(int $contentIdeaId, int $segmentIndex)
    {
        $this->contentIdeaId = $contentIdeaId;
        $this->segmentIndex = $segmentIndex;
    }

    public function handle(ImageGenerationService $service): void
    {
        // Phase F gate: if the GeminiGen circuit is open, this is an
        // outage-class failure — re-queue with a 5-minute delay rather
        // than burning the operator's auto-retry budget (max 2 auto
        // attempts per segment per CLAUDE.md April 21 entry). The
        // retry_count is NOT incremented; it stays reserved for genuine
        // prompt-class failures.
        $breaker = app(GeminiGenCircuitBreaker::class);
        if ($breaker->state() === 'open') {
            Log::info('[GeminiGenCircuit] retry job deferred — circuit open', [
                'idea_id' => $this->contentIdeaId,
                'segment_index' => $this->segmentIndex,
            ]);
            self::dispatch($this->contentIdeaId, $this->segmentIndex)
                ->delay(now()->addMinutes(5));
            return;
        }

        $idea = ContentIdea::find($this->contentIdeaId);
        if ($idea === null) {
            Log::info('RetryImageSegmentJob: idea missing, skipping', [
                'content_idea_id' => $this->contentIdeaId,
            ]);
            return;
        }

        $service->retrySegment($idea, $this->segmentIndex);
    }

    public function failed(\Throwable $exception): void
    {
        Log::warning('RetryImageSegmentJob: permanently failed', [
            'idea_id' => $this->contentIdeaId,
            'segment_index' => $this->segmentIndex,
            'error' => $exception->getMessage(),
        ]);
    }
}
