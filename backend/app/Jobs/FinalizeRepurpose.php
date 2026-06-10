<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Phase F dispatch target for the IG repurpose pipeline. Dispatched by
 * RewriteRepurposeContent once the article body is ready (status rewritten).
 *
 * NOTE (intentional, plan-tracked): the real finalize body — branch on
 * $job->mode (blog → ContentIdea article_ready; carousel → anchor Post +
 * LinkedInPost carousel via applyCarouselGenAdapter + render dispatch) + purge
 * + Telegram drafted notice — lands in Phase F
 * (docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md). Until then this is
 * a no-op so the rewrite → finalize dispatch wiring is testable in isolation.
 */
class FinalizeRepurpose implements ShouldQueue
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
        // Phase F: branch on RepurposeJob::find($this->repurposeJobId)->mode.
    }
}
