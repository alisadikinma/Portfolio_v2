<?php

namespace App\Jobs;

use App\Enums\RepurposeJobStatus;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Services\TelegramNotificationService;
use App\Services\VideoSlideExtractor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * video_rebrand Phase C — vision-extract step. Dispatched by CaptureVideoCarousel
 * once the source video slides land (status captured). Recovers each tool slide's
 * header title + description, then forks to asset generation (Veo hook/CTA).
 *
 *   - success → status extracted + dispatch GenerateRebrandAssets
 *   - failure → status failed + Telegram reply (no half-state)
 *
 * @see docs/plans/2026-06-12-ig-video-carousel-rebrand.md
 */
class ExtractVideoSlides implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    // Covers one CLI attempt + one repair retry (2x services.repurpose.timeout).
    public int $timeout = 1920;

    public function __construct(public readonly int $repurposeJobId)
    {
    }

    public function handle(): void
    {
        $job = RepurposeJob::find($this->repurposeJobId);
        if (!$job || $job->status !== RepurposeJobStatus::Captured->value) {
            return;
        }

        $job->transitionTo(RepurposeJobStatus::Extracting, 'video_extract_start');

        try {
            $result = app(VideoSlideExtractor::class)->extract($job);
        } catch (\Throwable $e) {
            $this->failJob($job, 'extract_exception: ' . $e->getMessage());
            return;
        }

        if (!($result['success'] ?? false)) {
            $this->failJob($job, 'extract_failed: ' . ($result['error'] ?? 'no extraction'));
            return;
        }

        // Drop the SOURCE creator's own hook/cta slides (#2) + renumber survivors.
        $this->applySlideDrops($job, (array) ($result['dropped'] ?? []));

        try {
            app(TelegramNotificationService::class)
                ->sendRepurposeProgress($job, '🔎 Judul tiap slide ke-ekstrak');
        } catch (\Throwable $e) {
            Log::warning('[ExtractVideoSlides] progress notify failed', ['job' => $job->id, 'error' => $e->getMessage()]);
        }

        // End at the shared `extracted` state; GenerateRebrandAssets guards on it
        // and forks Extracted → GeneratingAssets (the video_rebrand branch).
        $job->transitionTo(RepurposeJobStatus::Extracted, 'video_extract_ok', ['last_error' => null]);
        GenerateRebrandAssets::dispatch($job->id);
    }

    /**
     * Delete the source creator's own hook/cta tool rows (files retained on disk
     * for audit) and renumber the surviving tool rows to a contiguous 1..K so
     * GenerateRebrandAssets places the cta bookend at K+1 and the stepper total is
     * correct. Renumbering ascending is collision-safe: a survivor's new index is
     * always <= its old index (we only ever remove slides), so even were a
     * UNIQUE(repurpose_job_id, slide_index) added later, each target slot is freed
     * before it's claimed. Bookends are created later (hook=0, cta=K+1) and never
     * exist among role=tool rows here.
     *
     * All-dropped guard: if EVERY tool slide classified non-content (vision was
     * over-aggressive), keep them all + warn — never ship an empty carousel.
     *
     * @param array<int,int> $dropped slide_index values to drop
     */
    private function applySlideDrops(RepurposeJob $job, array $dropped): void
    {
        $dropped = array_values(array_unique(array_map('intval', $dropped)));
        if ($dropped === []) {
            return;
        }

        $toolSlides = $job->videoSlides()
            ->where('role', RepurposeVideoSlide::ROLE_TOOL)
            ->orderBy('slide_index')
            ->get();

        $survivors = $toolSlides->reject(fn ($s) => in_array((int) $s->slide_index, $dropped, true))->values();

        if ($survivors->isEmpty()) {
            Log::warning('[ExtractVideoSlides] all tool slides classified non-content — keeping all (no empty carousel)', [
                'job' => $job->id,
                'tool_count' => $toolSlides->count(),
            ]);
            return;
        }

        foreach ($toolSlides as $slide) {
            if (in_array((int) $slide->slide_index, $dropped, true)) {
                $slide->delete(); // file retained on disk (audit) — no Storage::delete
            }
        }

        $next = 1;
        foreach ($survivors as $slide) {
            if ((int) $slide->slide_index !== $next) {
                $slide->update(['slide_index' => $next]);
            }
            $next++;
        }

        Log::info('[ExtractVideoSlides] dropped source bookend slides', [
            'job' => $job->id,
            'dropped' => $dropped,
            'survivors' => $survivors->count(),
        ]);
    }

    private function failJob(RepurposeJob $job, string $reason): void
    {
        $job->transitionTo(RepurposeJobStatus::Failed, $reason, ['last_error' => $reason]);
        app(TelegramNotificationService::class)->sendRepurposeFailed($job, $reason);
    }
}
