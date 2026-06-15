<?php

namespace App\Jobs;

use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Services\GeminiGenVideoService;
use App\Services\VideoBookendOverlayApplier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * video_rebrand — CREDIT-FREE re-skin of an already-rendered hook/CTA bookend.
 *
 * The expensive step (face-gen keyframe + Veo/GROK i2v render) is NOT repeated.
 * We re-download the bookend's stored `veo_url` (the raw provider output, already
 * paid for) and re-crop it to the plain 4:5 base, then re-apply the brand overlay
 * (hook → cover headline + topic logo; CTA → single-command Follow ask card). This
 * is how the operator picks up a new title/logo/CTA-overlay treatment WITHOUT
 * burning another ~5-credit Veo clip.
 *
 * FSM-neutral (like ComposeToolSlides): never transitions the job — it only mutates
 * the one slide's composited_status + composited_path. The widened detail poll
 * tracks composited_status to refresh the download link.
 */
class ReskinBookendSlide implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 600;

    public int $tries = 1;

    public function __construct(
        public readonly int $repurposeJobId,
        public readonly int $slideIndex,
    ) {
    }

    public function handle(GeminiGenVideoService $video, VideoBookendOverlayApplier $overlay): void
    {
        $job = RepurposeJob::find($this->repurposeJobId);
        if (! $job || $job->mode !== 'video_rebrand') {
            return;
        }

        $slide = $job->videoSlides()->where('slide_index', $this->slideIndex)->first();
        if (! $slide || ! in_array($slide->role, [RepurposeVideoSlide::ROLE_HOOK, RepurposeVideoSlide::ROLE_CTA], true)) {
            return;
        }

        // No rendered clip → nothing to re-skin (operator must Re-render instead).
        if (trim((string) $slide->veo_url) === '') {
            $slide->update(['composited_status' => 'failed', 'last_error' => 'No rendered clip to re-skin — use Re-render.']);

            return;
        }

        $slide->update(['composited_status' => 'compositing', 'last_error' => null]);

        // Re-create the plain 4:5 base from the already-paid Veo/GROK output. Same
        // path overlayClip reads + supersedes — zero credits, just a re-download+crop.
        $relBase = "repurpose/{$job->id}/composited/slide_{$slide->slide_index}.mp4";
        $base = $video->finalizeVeoClip($slide->veo_url, $relBase);
        if ($base === null) {
            // finalizeVeoClip null = download OR ffmpeg failure (incl. a write
            // "Permission denied" when the composited dir isn't group-writable —
            // see App\Support\SharedDir). Stay neutral so the operator isn't
            // pushed to a credit-burning Re-render when the clip is actually fine.
            $slide->update(['composited_status' => 'failed', 'last_error' => 'Re-skin failed while processing the rendered clip — check the worker logs. If the source clip has expired, use Re-render.']);
            Log::warning('[ReskinBookendSlide] base re-finalize failed', ['slide' => $slide->id, 'job' => $job->id]);

            return;
        }

        // Re-apply the brand overlay; a null (no title/logo, or render miss) keeps
        // the plain base — same graceful-degrade contract as the first finalize.
        $composited = $overlay->apply($slide) ?? $base;

        $slide->update(['composited_status' => 'done', 'composited_path' => $composited, 'last_error' => null]);
        Log::info('[ReskinBookendSlide] re-skin done', ['slide' => $slide->id, 'job' => $job->id, 'role' => $slide->role]);
    }
}
