<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Phase E dispatch target for the IG repurpose pipeline. Dispatched by
 * ResearchRepurposeClaims once claims are verified (status researched).
 *
 * NOTE (intentional, plan-tracked): the real rewrite body —
 * RepurposeRewriteService producing a powerful, accurate, style-Ali article
 * body (corrected claims + sources appendix) — lands in Phase E
 * (docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md). Until then this is
 * a no-op so the research → rewrite dispatch wiring is testable in isolation.
 * Phase E fills handle() and chains FinalizeRepurpose.
 */
class RewriteRepurposeContent implements ShouldQueue
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
        // Phase E: app(RepurposeRewriteService::class)->rewrite(
        //     RepurposeJob::findOrFail($this->repurposeJobId)
        // );
    }
}
