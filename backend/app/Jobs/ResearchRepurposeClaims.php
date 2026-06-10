<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Phase D dispatch target for the IG repurpose pipeline. Dispatched by
 * ExtractSlideContent once claims are extracted (status extracted).
 *
 * NOTE (intentional, plan-tracked): the real fact-check body —
 * RepurposeResearchService verifying each claim (firecrawl + Claude CLI) and
 * attaching corrections + sources — lands in Phase D
 * (docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md). Until then this is
 * a no-op so the extract → research dispatch wiring is testable in isolation.
 * Phase D fills handle() and chains RewriteRepurposeContent.
 */
class ResearchRepurposeClaims implements ShouldQueue
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
        // Phase D: app(RepurposeResearchService::class)->research(
        //     RepurposeJob::findOrFail($this->repurposeJobId)
        // );
    }
}
