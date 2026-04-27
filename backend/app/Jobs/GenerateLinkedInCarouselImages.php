<?php

namespace App\Jobs;

use App\Models\LinkedInPost;
use App\Services\LinkedInCarouselImageService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued wrapper around LinkedInCarouselImageService::dispatchAllSlides.
 *
 * Dispatched from LinkedInGenerationService::persistAndRoute when a draft
 * advances to AwaitingPublish or ManualReview with format=carousel. Also
 * invokable directly from the admin "Regenerate all images" action.
 *
 * GeminiGen calls are HTTP-bound (~5-30s each). With 7-10 slides, the worst
 * case is ~5 minutes serial, but each slide is fire-and-forget — we POST
 * and let the webhook complete asynchronously. Total job runtime is dominated
 * by the GeminiGen multipart POST handshakes (one per slide).
 */
class GenerateLinkedInCarouselImages implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 360;

    public int $tries = 1;

    public function __construct(public readonly int $draftId)
    {
    }

    public function handle(LinkedInCarouselImageService $service): void
    {
        $draft = LinkedInPost::find($this->draftId);
        if (! $draft) {
            Log::warning('[GenerateLinkedInCarouselImages] draft not found', ['draft_id' => $this->draftId]);
            return;
        }

        if ($draft->format !== 'carousel') {
            Log::info('[GenerateLinkedInCarouselImages] not a carousel — skipping', [
                'draft_id' => $this->draftId,
                'format' => $draft->format,
            ]);
            return;
        }

        $count = $service->dispatchAllSlides($draft);

        Log::info('[GenerateLinkedInCarouselImages] dispatched', [
            'draft_id' => $this->draftId,
            'slides_dispatched' => $count,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[GenerateLinkedInCarouselImages] job failed', [
            'draft_id' => $this->draftId,
            'error' => $exception->getMessage(),
        ]);
    }
}
