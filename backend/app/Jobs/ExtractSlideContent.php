<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Phase C dispatch target for the IG repurpose pipeline. Dispatched by
 * CaptureInstagramPost once slides land (status captured).
 *
 * NOTE (intentional, plan-tracked): the real vision-extract body —
 * SlideVisionExtractor reading slide images + caption into claims/narrative —
 * lands in Phase C (docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md).
 * Until then this is a no-op so the capture → extract dispatch wiring is
 * testable in isolation. Phase C fills handle() and chains ResearchRepurposeClaims.
 */
class ExtractSlideContent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $repurposeJobId)
    {
    }

    public function handle(): void
    {
        // Phase C: app(SlideVisionExtractor::class)->extract(
        //     RepurposeJob::findOrFail($this->repurposeJobId)
        // );
    }
}
