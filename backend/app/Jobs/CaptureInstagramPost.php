<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Phase A dispatch target for the IG repurpose pipeline. Dispatched by
 * TelegramWebhookController::resolveRepurposeAction once the operator taps a
 * mode button (blog | carousel).
 *
 * NOTE (intentional, plan-tracked): the real capture body — Playwright headless
 * Chromium via InstagramCaptureService — lands in Phase B
 * (docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md). Until then this is
 * a no-op so the Telegram-button → capture-dispatch wiring is testable in
 * isolation. Phase B fills handle() and chains ExtractSlideContent.
 */
class CaptureInstagramPost implements ShouldQueue
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
        // Phase B: app(InstagramCaptureService::class)->capture(
        //     RepurposeJob::findOrFail($this->repurposeJobId)
        // );
    }
}
