<?php

namespace App\Jobs;

use App\Enums\RepurposeJobStatus;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Services\CarouselSlideEnhancer;
use App\Services\EntityReferenceService;
use App\Services\GeminiGenVideoService;
use App\Services\TelegramNotificationService;
use App\Services\VideoHookSceneAuthor;
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

    /**
     * Veo I2V motion prompts — follow the VEO 3.1 standard format (see
     * ai-video-promo-engine reference/image-video-gen/02-veo-production-guide.md
     * "I2V Prompt Template" + "Audio (NOT OPTIONAL)"). The explicit `Audio:` line
     * is MANDATORY: Veo 3.x always generates audio, and an UNspecified audio layer
     * makes the model guess → the guess trips `PUBLIC_ERROR_AUDIO_FILTERED` and the
     * whole render fails (verified live June 13, 2026 — 4/4 fails without it, pass
     * with it). We direct silent ambient (audio is stripped on download anyway).
     */
    public const VEO_PROMPT_HOOK = '6s, 720p, 9:16 vertical. Camera: locked-off static shot, no zoom or pan. '
        .'Subject: the creator holds the relaxed pose from the reference frame, subtle eye blinks every 2-3 seconds, '
        .'gentle micro-expressions, faint natural smile, mouth stays closed and not speaking. '
        .'Maintain visual continuity with reference frame character appearance throughout clip. '
        .'Ambient: floating side UI icons drift and bob gently with subtle parallax. '
        .'Audio: quiet ambient room tone only, no music, no dialogue, no subtitles, no audience sounds. '
        .'Maintain exact lighting, environment, appearance from reference frame. '
        .'Photorealistic, smooth natural motion, no morphing. 9:16 output.';

    public const VEO_PROMPT_CTA = '6s, 720p, 9:16 vertical. Camera: locked-off static shot, no zoom or pan. '
        .'Subject: the creator holds the warm inviting pose from the reference frame, a gentle welcoming hand gesture and soft nod, '
        .'subtle eye blinks, faint smile, mouth stays closed and not speaking. '
        .'Maintain visual continuity with reference frame character appearance throughout clip. '
        .'Ambient: soft gold glow, gentle light shift. '
        .'Audio: quiet ambient room tone only, no music, no dialogue, no subtitles, no audience sounds. '
        .'Maintain exact lighting, environment, appearance from reference frame. '
        .'Photorealistic, smooth natural motion, no morphing. 9:16 output.';

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

        // Hook keyframe is topic-aware (#1): authored from the surviving tool
        // titles, optionally with a public figure as a face reference. CTA stays
        // the static branded portrait this phase.
        [$hookRefs, $hookPrompt] = $this->buildHookKeyframe($job, $hook, $faceUrl);

        $dispatched = 0;
        $dispatched += $this->dispatchKeyframe($video, $hook, $hookPrompt, $hookRefs) ? 1 : 0;
        $dispatched += $this->dispatchKeyframe($video, $cta, self::KEYFRAME_PROMPT_CTA, [$faceUrl]) ? 1 : 0;

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

    /**
     * Build the topic-aware hook keyframe (#1): refs[] + scene prompt. Authored
     * via VideoHookSceneAuthor from the surviving tool titles. When a public
     * figure fits the topic (and the safety fallback hasn't dropped it), resolve
     * a license-clean photo via EntityReferenceService and add it as reference 2.
     * Degrades gracefully — empty topic / author failure / unresolved figure all
     * fall back to the creator-only ref + static prompt; the job never blocks here.
     *
     * @return array{0: array<int,string>, 1: string} [refs, prompt]
     */
    private function buildHookKeyframe(RepurposeJob $job, RepurposeVideoSlide $hook, string $faceUrl): array
    {
        $topic = $this->hookTopic($job);
        if ($topic === '') {
            return [[$faceUrl], self::KEYFRAME_PROMPT_HOOK];
        }

        // figure_dropped sentinel (set by the PollRebrandAssets safety fallback on
        // a PROMINENT_PEOPLE_UPLOAD refusal) forces a creator-only re-author.
        $allowFigure = ! (bool) $hook->figure_dropped;

        $authored = app(VideoHookSceneAuthor::class)->author($topic, $allowFigure);
        if (! ($authored['success'] ?? false)) {
            Log::warning('[GenerateRebrandAssets] hook author failed — static fallback', [
                'job' => $job->id,
                'error' => $authored['error'] ?? null,
            ]);

            return [[$faceUrl], self::KEYFRAME_PROMPT_HOOK];
        }

        $refs = [$faceUrl];
        $figureName = $allowFigure ? ($authored['figure_name'] ?? null) : null;
        if ($figureName) {
            $entity = app(EntityReferenceService::class)->findOrFetch($figureName, 'person');
            $figureUrl = is_array($entity) ? ($entity['url'] ?? null) : null;
            if (is_string($figureUrl) && $figureUrl !== '') {
                $refs[] = $figureUrl; // license-checked + downloaded to our storage
            }
        }

        return [$refs, (string) $authored['scene_prompt']];
    }

    /** Topic = surviving content tool titles, in slide order. */
    private function hookTopic(RepurposeJob $job): string
    {
        return (string) $job->videoSlides()
            ->where('role', RepurposeVideoSlide::ROLE_TOOL)
            ->orderBy('slide_index')
            ->pluck('header_title')
            ->filter()
            ->implode(', ');
    }

    /**
     * @param array<int,string> $refs face reference URL(s) for the keyframe
     */
    private function dispatchKeyframe(GeminiGenVideoService $video, RepurposeVideoSlide $slide, string $prompt, array $refs): bool
    {
        // Idempotent — a re-run (recovery) skips a keyframe already in flight/done.
        if (in_array($slide->keyframe_status, ['generating', 'done'], true)) {
            return true;
        }

        $refs = array_values(array_filter($refs, fn ($u) => is_string($u) && $u !== ''));
        $uuid = $refs !== [] ? $video->dispatchKeyframe($refs, $prompt, $slide->id) : null;
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
