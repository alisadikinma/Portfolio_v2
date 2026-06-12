<?php

namespace App\Jobs;

use App\Enums\RepurposeJobStatus;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Services\CarouselSlideEnhancer;
use App\Services\GeminiGenVideoService;
use App\Services\TelegramNotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * video_rebrand Phase E — fork to brand-asset generation. Dispatched by
 * ExtractVideoSlides once the source tool slides have header titles. Forks
 * Extracted → GeneratingAssets, synthesizes the bookend slides:
 *
 *   - hook : slide_index 0       (opens the carousel)
 *   - tool : slide_index 1..N    (the source videos, already captured)
 *   - cta  : slide_index N+1     (closes the carousel)
 *
 * and dispatches a face-gen keyframe (9:16 creator portrait) per hook/CTA. The
 * keyframe → Veo handoff + the per-slide completion are driven by the
 * PollRebrandAssets cron (geminigen never fires webhooks — poll is the sole
 * completion path, mirroring PollHookVideos).
 *
 * Static prompts (NO LLM step) — same de-risk as the GROK hook video.
 *
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md Phase E
 */
class GenerateRebrandAssets implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 360;

    public int $tries = 1;

    /** Face-gen keyframe (image) prompts — a calm branded vertical portrait. */
    public const KEYFRAME_PROMPT_HOOK = 'Cinematic vertical 9:16 portrait of the creator, confident and approachable, '
        .'smart-casual outfit (tee with unstructured blazer), looking directly at camera with a subtle welcoming expression, '
        .'clean solid brand-blue (#0F59B6) studio background, soft key light with gentle gold rim light, '
        .'modern tech-founder energy, sharp focus, photorealistic, no text, no logos, no UI elements';

    public const KEYFRAME_PROMPT_CTA = 'Cinematic vertical 9:16 portrait of the creator, warm and inviting, '
        .'smart-casual outfit (tee with unstructured blazer), gesturing softly toward the viewer as if inviting them to follow, '
        .'clean solid navy-blue studio background with a subtle gold glow, soft key light with gold rim light, '
        .'friendly closing energy, sharp focus, photorealistic, no text, no logos, no UI elements';

    /** Veo motion prompts — gentle, frame-respecting, no new objects (GROK-style). */
    public const VEO_PROMPT_HOOK = 'The creator holds the relaxed pose already shown in the source image, '
        .'animate only what already exists and introduce no new object, subtle natural micro-motion in the eyes and a faint smile, '
        .'the camera stays completely static with no zoom or pan, the mouth stays mostly closed, '
        .'photorealistic with smooth natural motion and no morphing';

    public const VEO_PROMPT_CTA = 'The creator holds the warm inviting pose already shown in the source image, '
        .'animate only what already exists and introduce no new object, a gentle welcoming hand gesture and soft nod, '
        .'the camera stays completely static with no zoom or pan, '
        .'photorealistic with smooth natural motion and no morphing';

    public function __construct(public readonly int $repurposeJobId)
    {
    }

    public function handle(GeminiGenVideoService $video): void
    {
        $job = RepurposeJob::find($this->repurposeJobId);
        if (! $job || $job->status !== RepurposeJobStatus::Extracted->value) {
            return;
        }

        $faceUrl = app(CarouselSlideEnhancer::class)->getCreatorFaceUrl();
        if (empty($faceUrl)) {
            $this->failJob($job, 'rebrand_assets_no_face: profile_photo setting missing — cannot face-gen keyframes');

            return;
        }

        $job->transitionTo(RepurposeJobStatus::GeneratingAssets, 'video_assets_start', ['last_error' => null]);

        $maxToolIndex = (int) $job->videoSlides()
            ->where('role', RepurposeVideoSlide::ROLE_TOOL)
            ->max('slide_index');

        // hook at index 0, cta after the last tool slide.
        $hook = $this->ensureBookend($job, RepurposeVideoSlide::ROLE_HOOK, 0);
        $cta = $this->ensureBookend($job, RepurposeVideoSlide::ROLE_CTA, $maxToolIndex + 1);

        $dispatched = 0;
        $dispatched += $this->dispatchKeyframe($video, $hook, self::KEYFRAME_PROMPT_HOOK) ? 1 : 0;
        $dispatched += $this->dispatchKeyframe($video, $cta, self::KEYFRAME_PROMPT_CTA) ? 1 : 0;

        if ($dispatched === 0) {
            // Both keyframe dispatches failed (circuit open / key missing / HTTP) —
            // the PollRebrandAssets recovery pass re-dispatches once the circuit closes.
            Log::warning('[GenerateRebrandAssets] both keyframe dispatches failed', ['job' => $job->id]);
        }

        try {
            app(TelegramNotificationService::class)
                ->sendRepurposeProgress($job, '🎬 Bikin klip hook + CTA (face-gen → Veo)…');
        } catch (\Throwable $e) {
            Log::warning('[GenerateRebrandAssets] progress notify failed', ['job' => $job->id, 'error' => $e->getMessage()]);
        }
    }

    private function ensureBookend(RepurposeJob $job, string $role, int $index): RepurposeVideoSlide
    {
        return RepurposeVideoSlide::firstOrCreate(
            ['repurpose_job_id' => $job->id, 'role' => $role],
            ['slide_index' => $index, 'composited_status' => 'pending'],
        );
    }

    private function dispatchKeyframe(GeminiGenVideoService $video, RepurposeVideoSlide $slide, string $prompt): bool
    {
        // Idempotent — a re-run (recovery) skips a keyframe already in flight/done.
        if (in_array($slide->keyframe_status, ['generating', 'done'], true)) {
            return true;
        }

        $faceUrl = app(CarouselSlideEnhancer::class)->getCreatorFaceUrl();
        $uuid = $faceUrl ? $video->dispatchKeyframe($faceUrl, $prompt, $slide->id) : null;
        if ($uuid === null) {
            $slide->update(['keyframe_status' => 'failed', 'last_error' => 'keyframe dispatch failed (service unavailable or rejected)']);

            return false;
        }

        $slide->update(['keyframe_status' => 'generating', 'keyframe_job_uuid' => $uuid, 'last_error' => null]);

        return true;
    }

    private function failJob(RepurposeJob $job, string $reason): void
    {
        $job->transitionTo(RepurposeJobStatus::Failed, $reason, ['last_error' => $reason]);
        try {
            app(TelegramNotificationService::class)->sendRepurposeFailed($job, $reason);
        } catch (\Throwable $e) {
            Log::warning('[GenerateRebrandAssets] fail notify failed', ['job' => $job->id, 'error' => $e->getMessage()]);
        }
    }
}
