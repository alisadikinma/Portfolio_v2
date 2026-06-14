<?php

namespace App\Services;

use App\Enums\RepurposeJobStatus;
use App\Jobs\CaptureInstagramPost;
use App\Jobs\ComposeVideoCarousel;
use App\Jobs\ExtractSlideContent;
use App\Jobs\FinalizeRepurpose;
use App\Jobs\GenerateRebrandAssets;
use App\Jobs\ResearchRepurposeClaims;
use App\Jobs\RewriteRepurposeContent;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;

/**
 * Part A5 — single retry path for a Failed RepurposeJob, shared by the admin
 * HTTP endpoint (RepurposeJobController::retry) AND the Telegram inline Retry
 * button (TelegramWebhookController). Resumes the exact failed step at its FSM
 * guard rather than restarting from capture, and (for video_rebrand) zeroes the
 * auto-recover budget + resets the failed/orphaned bookends so the bounded
 * PollRebrandAssets safety-net is live again.
 */
class RepurposeRetryService
{
    /**
     * Map a failed-from FSM state → [resume guard state, step job class].
     */
    private const RETRY_MAP = [
        'received'    => ['capturing', CaptureInstagramPost::class],
        'capturing'   => ['capturing', CaptureInstagramPost::class],
        'extracting'  => ['captured', ExtractSlideContent::class],
        'researching' => ['extracted', ResearchRepurposeClaims::class],
        'rewriting'   => ['researched', RewriteRepurposeContent::class],
        'finalizing'  => ['rewritten', FinalizeRepurpose::class],
        // video_rebrand asset branch — resume the exact failed step at its guard.
        'generating_assets' => ['extracted', GenerateRebrandAssets::class],
        'assets_ready'      => ['assets_ready', ComposeVideoCarousel::class],
        'compositing'       => ['assets_ready', ComposeVideoCarousel::class],
        'composed'          => ['composed', FinalizeRepurpose::class],
    ];

    /**
     * @return array{ok: bool, message: string, status: string}
     */
    public function retry(RepurposeJob $job): array
    {
        if ($job->status !== RepurposeJobStatus::Failed->value) {
            return ['ok' => false, 'message' => 'Only a failed job can be retried.', 'status' => $job->status];
        }

        $failedFrom = $this->failedFromStep($job);
        [$guardState, $jobClass] = self::RETRY_MAP[$failedFrom] ?? self::RETRY_MAP['capturing'];

        // Blog mode skips research+rewrite and enters FinalizeRepurpose at
        // `extracted` (not `rewritten`). Resume the failed finalize there.
        if ($job->mode === 'blog' && $failedFrom === 'finalizing') {
            $guardState = 'extracted';
        }

        // Re-running video_rebrand asset generation: zero the auto-recover budget so
        // the bounded PollRebrandAssets retry safety-net is live again, and reset the
        // failed/orphaned bookends so GenerateRebrandAssets re-runs them immediately
        // (its dispatchKeyframe skips kf='done', so a veo-failed bookend would
        // otherwise wait for the 5-min recover() pass). Done bookends are preserved.
        $extra = ['last_error' => null];
        if ($job->mode === 'video_rebrand' && $guardState === 'extracted') {
            $extra['asset_retry_count'] = 0;
            $job->videoSlides()
                ->whereIn('role', [RepurposeVideoSlide::ROLE_HOOK, RepurposeVideoSlide::ROLE_CTA])
                ->where(fn ($q) => $q->where('keyframe_status', 'failed')->orWhereNull('keyframe_status')->orWhere('veo_status', 'failed'))
                ->update(['keyframe_status' => null, 'veo_status' => null, 'composited_status' => 'pending', 'last_error' => null]);
        }

        $job->transitionTo(RepurposeJobStatus::from($guardState), 'job_retry', $extra);
        $jobClass::dispatch($job->id);

        return ['ok' => true, 'message' => 'Retry dispatched.', 'status' => $job->status];
    }

    /** Last pipeline_state_log entry whose target was `failed` → its `from`. */
    private function failedFromStep(RepurposeJob $job): string
    {
        $log = $job->pipeline_state_log ?? [];
        for ($i = count($log) - 1; $i >= 0; $i--) {
            if (($log[$i]['to'] ?? null) === RepurposeJobStatus::Failed->value) {
                return (string) ($log[$i]['from'] ?? 'capturing');
            }
        }

        return 'capturing';
    }
}
