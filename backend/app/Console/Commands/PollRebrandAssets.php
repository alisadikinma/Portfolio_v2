<?php

namespace App\Console\Commands;

use App\Enums\RepurposeJobStatus;
use App\Jobs\ComposeVideoCarousel;
use App\Jobs\GenerateRebrandAssets;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Services\GeminiGenVideoService;
use App\Services\VideoChromeRenderer;
use App\Services\VideoGenErrorClassifier;
use App\Services\VideoGenPromptDegrader;
use App\Services\VideoRebrandComposer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * video_rebrand Phase E — face-gen keyframe → Veo completion driver (2-poll).
 *
 * GeminiGen never fires webhooks (known incident — poll is the sole completion
 * path, see PollHookVideos). Per minute this command:
 *
 *   Pass A — keyframe poll: hook/CTA slides at keyframe_status='generating' →
 *     GET /history/{keyframe_job_uuid}. Image ready → store keyframe_url,
 *     dispatch the Veo clip (mode_image=frame), flip to veo_status='generating'.
 *   Pass B — veo poll: slides at veo_status='generating' → GET /history/{veo_job_uuid}.
 *     Video ready → finalizeVeoClip (9:16 → 4:5 1080×1350) → composited_status='done'.
 *   Pass C — completion: a GeneratingAssets job whose hook+CTA bookends are both
 *     composited → AssetsReady + dispatch ComposeVideoCarousel. Any bookend that
 *     hard-failed → the whole job fails (surfaces to the operator, never wedges).
 *   Pass D — recovery: bounded re-dispatch of stuck/failed bookends.
 */
class PollRebrandAssets extends Command
{
    protected $signature = 'repurpose:poll-rebrand-assets {--dry-run}';

    protected $description = 'Poll GeminiGen for video_rebrand keyframe→Veo completion + bounded recovery';

    /** Face-gen images land in ~30-60s, Veo clips in ~60-120s; past this a row is wedged. */
    private const STUCK_MINUTES = 15;

    /**
     * Bounded re-dispatch of a failed bookend (keyframe ≈1 credit, Veo ≈5). Set to
     * 3 (was 2) to ride out the transient Veo 3.1 audio-filter trips that the
     * positive-ambient prompt fix can't fully eliminate (the filter is partly
     * nondeterministic — see GenerateRebrandAssets::VEO_PROMPT_* + Reddit r/Bard
     * reports of recent Veo 3.1 audio regressions).
     */
    private const MAX_RETRIES = 3;

    private const FAILED_RETRY_COOLDOWN_MINUTES = 5;

    public function __construct(private readonly VideoGenErrorClassifier $classifier)
    {
        parent::__construct();
    }

    public function handle(GeminiGenVideoService $video): int
    {
        $dry = (bool) $this->option('dry-run');

        $this->pollKeyframes($video, $dry);
        $this->pollVeo($video, $dry);
        $this->checkCompletion($dry);
        $this->recover($dry);

        return self::SUCCESS;
    }

    private function apiKey(): string
    {
        return (string) config('services.geminigen.api_key');
    }

    /** Pass A — keyframe image ready → store url + dispatch the Veo clip. */
    private function pollKeyframes(GeminiGenVideoService $video, bool $dry): void
    {
        $slides = RepurposeVideoSlide::where('keyframe_status', 'generating')
            ->whereNotNull('keyframe_job_uuid')
            ->get();

        foreach ($slides as $slide) {
            $data = $this->poll($slide->keyframe_job_uuid);
            if ($data === null) {
                $this->markStuck($slide, 'keyframe', 'poll failed', $dry);

                continue;
            }

            $imageUrl = $data['image_url']
                ?? $data['media_url']
                ?? ($data['generated_image'][0]['image_url'] ?? null);

            if ($imageUrl) {
                if ($dry) {
                    $this->line("  [dry] slide {$slide->id} keyframe ready → would dispatch Veo");

                    continue;
                }
                // Persist the keyframe FIRST so a dispatch failure never loses the
                // rendered image — recover() re-dispatches the clip from keyframe_url
                // without re-rendering. Provider is Veo by default; a figure hook is
                // already flipped to GROK in GenerateRebrandAssets::buildHookKeyframe.
                $slide->update(['keyframe_status' => 'done', 'keyframe_url' => $imageUrl]);

                $uuid = $this->dispatchClipForProvider($video, $slide, $imageUrl);
                if ($uuid === null) {
                    // Circuit open / dispatch rejected — keyframe stays done so the
                    // recovery pass can re-dispatch the clip without re-rendering.
                    $slide->update(['veo_status' => 'failed', 'last_error' => 'Clip dispatch failed (service unavailable)', 'last_error_class' => VideoGenErrorClassifier::TRANSIENT]);
                    $this->warn("  slide {$slide->id} clip dispatch failed");

                    continue;
                }
                $slide->update([
                    'veo_status' => 'generating', 'veo_job_uuid' => $uuid, 'last_error' => null, 'last_error_class' => null,
                ]);
                $this->info("  slide {$slide->id} keyframe done → {$slide->video_provider} clip {$uuid}");

                continue;
            }

            if ($this->hasError($data)) {
                $reason = $this->errorReason($data);
                // Detect + classify against code+message: GeminiGen often carries the
                // figure/safety signal ONLY in error_code (e.g. PROMINENT_PEOPLE_*)
                // while error_message is a generic sentence.
                $signature = $this->errorSignature($data);
                if (! $dry) {
                    $update = ['keyframe_status' => 'failed', 'last_error' => $reason, 'last_error_class' => $this->classifier->classify($signature)];
                    // Safety fallback (#1): a hook keyframe refused by GeminiGen's
                    // named-public-figure upload filter → drop the figure ref so
                    // the recovery pass re-authors a CREATOR-ONLY scene. The
                    // sentinel is durable (recover() blanks last_error, not this).
                    if ($slide->role === RepurposeVideoSlide::ROLE_HOOK
                        && ! $slide->figure_dropped
                        && $this->isSafetyError($signature)) {
                        $update['figure_dropped'] = true;
                        $this->warn("  slide {$slide->id} hook keyframe refused (figure) → dropping figure ref for retry");
                    }
                    $slide->update($update);
                }
                $this->warn("  slide {$slide->id} keyframe failed");

                continue;
            }

            $this->markStuck($slide, 'keyframe', 'render exceeded stuck window', $dry);
        }
    }

    /**
     * GeminiGen's named-public-figure / unsafe-upload refusal class. Mirrors
     * ImageGenerationService::isSafetyError (same deterministic substring gate) —
     * a figure photo would fail the same way on every retry, so we drop the figure
     * ref instead of grinding the retry budget.
     */
    private function isSafetyError(?string $reason): bool
    {
        if ($reason === null || trim($reason) === '') {
            return false;
        }
        $needle = strtolower($reason);
        $patterns = [
            'public_error_prominent_people_upload',
            'public_error_prominent_people',
            'public_error_minor',
            'public_error_unsafe',
            'prominent people',
            'prominent person',
            'do not allow uploading images',
            'safety filter',
            'content policy',
        ];
        foreach ($patterns as $p) {
            if (str_contains($needle, $p)) {
                return true;
            }
        }

        return false;
    }

    /** Pass B — Veo video ready → finalize 4:5 clip → composited done. */
    private function pollVeo(GeminiGenVideoService $video, bool $dry): void
    {
        $slides = RepurposeVideoSlide::where('veo_status', 'generating')
            ->whereNotNull('veo_job_uuid')
            ->get();

        foreach ($slides as $slide) {
            $data = $this->poll($slide->veo_job_uuid);
            if ($data === null) {
                $this->markStuck($slide, 'veo', 'poll failed', $dry);

                continue;
            }

            $videoUrl = $data['generated_video'][0]['video_url'] ?? $data['media_url'] ?? null;

            if ($videoUrl) {
                if ($dry) {
                    $this->line("  [dry] slide {$slide->id} veo ready → would finalize");

                    continue;
                }
                $relOut = "repurpose/{$slide->repurpose_job_id}/composited/slide_{$slide->slide_index}.mp4";
                $final = $video->finalizeVeoClip($videoUrl, $relOut);
                if ($final !== null) {
                    // CTA gets the Follow/Save/Comment ask overlay (#3) baked on;
                    // the hook bookend stays plain. Overlay failure is non-fatal —
                    // the plain clip ships (the ask still lands in the caption).
                    $composited = $final;
                    if ($slide->role === RepurposeVideoSlide::ROLE_CTA) {
                        $overlaid = $this->applyCtaOverlay($slide);
                        if ($overlaid !== null) {
                            $composited = $overlaid;
                        }
                    } elseif ($slide->role === RepurposeVideoSlide::ROLE_HOOK) {
                        // Overlay the cover headline (from the original IG hook) onto
                        // the hook clip. Non-fatal — a missing title or render miss
                        // ships the plain clip.
                        $titled = $this->applyHookTitle($slide);
                        if ($titled !== null) {
                            $composited = $titled;
                        }
                    }
                    $slide->update(['veo_status' => 'done', 'veo_url' => $videoUrl, 'composited_path' => $composited, 'composited_status' => 'done', 'last_error' => null]);
                    $this->info("  slide {$slide->id} veo done");
                } else {
                    $slide->update(['veo_status' => 'failed', 'last_error' => 'Download/crop of finished Veo clip failed — see logs.', 'last_error_class' => VideoGenErrorClassifier::TRANSIENT]);
                    $this->warn("  slide {$slide->id} finalize failed");
                }

                continue;
            }

            if ($this->hasError($data)) {
                $reason = $this->errorReason($data);
                $signature = $this->errorSignature($data);
                if (! $dry) {
                    $class = $this->classifier->classify($signature);
                    $update = ['veo_status' => 'failed', 'last_error' => $reason, 'last_error_class' => $class];
                    // IMMEDIATE failover (prominent_people ONLY): Veo (Google) will
                    // NEVER animate a recognizable public figure, so retrying Veo is
                    // pointless — switch this clip to GROK (xAI allows figures) now,
                    // KEEPING the figure keyframe. Every OTHER failure class
                    // (audio_filtered / transient / …) stays on Veo and burns the
                    // 3-retry budget first; recover() escapes to GROK only after that
                    // budget is spent (Ali's policy 2026-06-14 — preserve Veo quality).
                    if ($slide->video_provider === RepurposeVideoSlide::PROVIDER_VEO
                        && $class === VideoGenErrorClassifier::PROMINENT_PEOPLE) {
                        $update['video_provider'] = RepurposeVideoSlide::PROVIDER_GROK;
                        $this->warn("  slide {$slide->id} Veo prominent-people → immediate GROK failover (figure kept)");
                    }
                    $slide->update($update);
                }
                $this->warn("  slide {$slide->id} veo clip failed");

                continue;
            }

            $this->markStuck($slide, 'veo', 'render exceeded stuck window', $dry);
        }
    }

    /** Pass C — promote the job once both bookends are composited (or fail it). */
    private function checkCompletion(bool $dry): void
    {
        $jobs = RepurposeJob::where('mode', 'video_rebrand')
            ->where('status', RepurposeJobStatus::GeneratingAssets->value)
            ->get();

        foreach ($jobs as $job) {
            $bookends = $job->videoSlides()
                ->whereIn('role', [RepurposeVideoSlide::ROLE_HOOK, RepurposeVideoSlide::ROLE_CTA])
                ->get();

            if ($bookends->isEmpty()) {
                continue;
            }

            // A hard-failed keyframe/veo that exhausted retries fails the job.
            $hardFailed = $bookends->first(fn ($s) => in_array($s->keyframe_status, ['failed'], true) || in_array($s->veo_status, ['failed'], true));
            $allDone = $bookends->every(fn ($s) => $s->composited_status === 'done');

            if ($allDone) {
                if ($dry) {
                    $this->line("  [dry] job {$job->id} bookends done → would compose");

                    continue;
                }
                $job->transitionTo(RepurposeJobStatus::AssetsReady, 'video_assets_ready', ['last_error' => null]);
                ComposeVideoCarousel::dispatch($job->id);
                $this->info("  job {$job->id} → assets_ready, composing");

                continue;
            }

            if ($hardFailed && ! $dry) {
                // Only fail the job once retries are exhausted (recovery pass owns the budget).
                // Scope the error check to bookends (like $hardFailed/$allDone) —
                // a stale tool-slide last_error must not prematurely fail the job.
                // A Veo bookend that still has its keyframe can fail over to GROK
                // (recover() owns that escape) — don't fail the job while that's
                // still possible, or we'd kill it one tick before the GROK attempt.
                $canFailover = $bookends->contains(fn ($s) => $s->video_provider === RepurposeVideoSlide::PROVIDER_VEO
                    && $s->veo_status === 'failed'
                    && filled($s->keyframe_url));
                $exhausted = $bookends->every(fn ($s) => $s->keyframe_status !== 'generating' && $s->veo_status !== 'generating')
                    && $bookends->contains(fn ($s) => $s->last_error !== null)
                    && (int) ($job->asset_retry_count ?? 0) >= self::MAX_RETRIES
                    && ! $canFailover;
                if ($exhausted) {
                    $lastSlideError = (string) ($bookends->first(fn ($s) => $s->last_error !== null)?->last_error ?? '');
                    $job->transitionTo(RepurposeJobStatus::Failed, 'video_assets_failed', ['last_error' => 'A hook/CTA clip failed to generate after retries — see slide errors.']);
                    $this->warn("  job {$job->id} → failed (asset generation exhausted)");
                    // A5: surface the terminal failure to the operator with a one-tap
                    // Retry — was a silent FSM transition before. Best-effort.
                    try {
                        app(\App\Services\TelegramNotificationService::class)->sendRepurposeAssetsFailed($job, $lastSlideError);
                    } catch (\Throwable $e) {
                        Log::warning('[PollRebrandAssets] exhaustion notify failed', ['job' => $job->id, 'error' => $e->getMessage()]);
                    }
                }
            }
        }
    }

    /** Pass D — bounded, STAGE-AWARE re-dispatch of failed bookends after a cooldown. */
    private function recover(bool $dry): void
    {
        $jobs = RepurposeJob::where('mode', 'video_rebrand')
            ->where('status', RepurposeJobStatus::GeneratingAssets->value)
            ->where('updated_at', '<', now()->subMinutes(self::FAILED_RETRY_COOLDOWN_MINUTES))
            ->get();

        foreach ($jobs as $job) {
            // Per-job isolation: a single job's recovery error (illegal transition,
            // DB hiccup) must never crash the whole cron — that would exit 1 every
            // minute AND starve the keyframe/Veo polling of OTHER in-flight jobs.
            try {
                $this->recoverJob($job, $dry);
            } catch (\Throwable $e) {
                Log::warning('[PollRebrandAssets] recover failed for job', ['job' => $job->id, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Route each failed bookend by which STAGE broke (request #2):
     *   - Veo-only (keyframe 'done' + keyframe_url present + veo 'failed') → keep the
     *     keyframe, reset Veo only, re-dispatch the Veo clip DIRECTLY from the stored
     *     keyframe_url with an error-degraded prompt. No keyframe re-render, no SSH
     *     hook re-author, no Extracted bounce.
     *   - Keyframe-broken (keyframe 'failed'/NULL, or veo-failed without a usable
     *     keyframe) → full reset + bounce to Extracted so GenerateRebrandAssets
     *     re-renders the keyframe (its idempotent dispatch skips 'done' bookends).
     * asset_retry_count is incremented ONCE per recover tick (job-level budget);
     * MAX_RETRIES + the 5-min cooldown are unchanged.
     */
    private function recoverJob(RepurposeJob $job, bool $dry): void
    {
        $bookends = $job->videoSlides()
            ->whereIn('role', [RepurposeVideoSlide::ROLE_HOOK, RepurposeVideoSlide::ROLE_CTA])
            ->get();

        $failed = $bookends->filter(fn ($s) => $s->keyframe_status === 'failed'
            || $s->keyframe_status === null
            || $s->veo_status === 'failed');

        if ($failed->isEmpty()) {
            return;
        }

        // Current-provider retry budget spent → before giving up, fail over any
        // still-Veo failed bookend to GROK with a FRESH budget. (Ali's policy
        // 2026-06-14: a NON-figure Veo failure — audio/timeout — retries 3x on Veo
        // FIRST, then escapes to GROK; a figure bookend is on GROK from the start.)
        // A bookend already on GROK that's exhausted is genuinely stuck → leave it
        // for checkCompletion to fail the job.
        if ((int) ($job->asset_retry_count ?? 0) >= self::MAX_RETRIES) {
            $veoFailover = $failed->filter(fn ($s) => $s->video_provider === RepurposeVideoSlide::PROVIDER_VEO
                && $s->veo_status === 'failed'
                && filled($s->keyframe_url));
            if ($veoFailover->isEmpty()) {
                return; // nothing left to escape to → checkCompletion fails the job
            }
            if ($dry) {
                $this->line("  [dry] job {$job->id} Veo budget spent → would fail over ".$veoFailover->count().' bookend to GROK');

                return;
            }
            $job->videoSlides()
                ->whereIn('id', $veoFailover->pluck('id')->all())
                ->update(['video_provider' => RepurposeVideoSlide::PROVIDER_GROK, 'last_error' => null, 'last_error_class' => null]);
            $job->update(['asset_retry_count' => 0]); // fresh budget for GROK
            $this->warn("  job {$job->id} Veo retries exhausted → GROK failover (".$veoFailover->count().' bookend)');
            // Reload so the dispatch below sees provider=grok + cleared errors.
            $bookends = $job->videoSlides()
                ->whereIn('role', [RepurposeVideoSlide::ROLE_HOOK, RepurposeVideoSlide::ROLE_CTA])
                ->get();
            $failed = $bookends->filter(fn ($s) => $s->keyframe_status === 'failed'
                || $s->keyframe_status === null
                || $s->veo_status === 'failed');
        }

        $veoOnly = $failed->filter(fn ($s) => $s->keyframe_status === 'done'
            && $s->veo_status === 'failed'
            && filled($s->keyframe_url));
        $keyframeBroken = $failed->reject(fn ($s) => $veoOnly->contains('id', $s->id));

        if ($dry) {
            $this->line("  [dry] job {$job->id} recover → ".$veoOnly->count().' clip-only + '.$keyframeBroken->count().' keyframe-broken');

            return;
        }

        $video = app(GeminiGenVideoService::class);
        $acted = false;

        // Clip-only (keyframe survived): re-dispatch straight from the preserved
        // keyframe via the slide's provider — Veo with a degraded motion prompt, or
        // GROK (figure / post-budget failover) with its static audio-free prompt.
        foreach ($veoOnly as $slide) {
            $uuid = $this->dispatchClipForProvider($video, $slide, $slide->keyframe_url, $this->veoRetryPrompt($slide));
            if ($uuid !== null) {
                $slide->update(['veo_status' => 'generating', 'veo_job_uuid' => $uuid, 'last_error' => null, 'last_error_class' => null]);
                $this->info("  slide {$slide->id} {$slide->video_provider}-only retry → {$uuid}");
            } else {
                // Circuit open / rejected — stay failed, next eligible tick retries.
                $slide->update(['last_error' => 'Clip re-dispatch failed (service unavailable)', 'last_error_class' => VideoGenErrorClassifier::TRANSIENT]);
                $this->warn("  slide {$slide->id} clip-only re-dispatch failed");
            }
            $acted = true;
        }

        // Keyframe-broken: full reset + GenerateRebrandAssets re-render path.
        if ($keyframeBroken->isNotEmpty()) {
            // Keep last_error_class through the reset — buildHookKeyframe reads it to
            // force a static safe scene on a content_policy refusal (cleared on the
            // next successful keyframe poll). Only blank the human last_error.
            $job->videoSlides()
                ->whereIn('id', $keyframeBroken->pluck('id')->all())
                ->update(['keyframe_status' => null, 'veo_status' => null, 'last_error' => null]);
            // Bounce to extracted so GenerateRebrandAssets' Extracted guard passes
            // (the generating_assets → extracted recovery edge — see RepurposeJobStatus).
            $job->transitionTo(RepurposeJobStatus::Extracted, 'video_assets_retry');
            GenerateRebrandAssets::dispatch($job->id);
            $this->info("  job {$job->id} keyframe re-render dispatched (".$keyframeBroken->count().' bookend)');
            $acted = true;
        }

        if ($acted) {
            $job->increment('asset_retry_count');
            $this->info("  job {$job->id} recover tick (retry #{$job->asset_retry_count})");
        }
    }

    /**
     * Dispatch a bookend's i2v clip via its provider: GROK (figure / Veo-failover)
     * or Veo (default). $veoPromptOverride lets the recovery pass supply a degraded
     * Veo motion prompt; it's IGNORED on the GROK path (GROK has its own static,
     * audio-free motion prompt). Returns the job uuid or null on dispatch failure.
     */
    private function dispatchClipForProvider(GeminiGenVideoService $video, RepurposeVideoSlide $slide, string $imageUrl, ?string $veoPromptOverride = null): ?string
    {
        if ($slide->video_provider === RepurposeVideoSlide::PROVIDER_GROK) {
            $prompt = $slide->role === RepurposeVideoSlide::ROLE_CTA
                ? GenerateRebrandAssets::GROK_PROMPT_CTA
                : GenerateRebrandAssets::GROK_PROMPT_HOOK;

            return $video->dispatchGrokClip($imageUrl, $prompt, $slide->id);
        }

        $prompt = $veoPromptOverride ?? ($slide->role === RepurposeVideoSlide::ROLE_CTA
            ? GenerateRebrandAssets::VEO_PROMPT_CTA
            : GenerateRebrandAssets::VEO_PROMPT_HOOK);

        return $video->dispatchVeoClip($imageUrl, $prompt, '9:16', $slide->id);
    }

    /**
     * The Veo motion prompt for a bookend re-dispatch, error-degraded per the
     * slide's last_error_class (A4). Base = the role's static VEO_PROMPT_*.
     */
    private function veoRetryPrompt(RepurposeVideoSlide $slide): string
    {
        $base = $slide->role === RepurposeVideoSlide::ROLE_CTA
            ? GenerateRebrandAssets::VEO_PROMPT_CTA
            : GenerateRebrandAssets::VEO_PROMPT_HOOK;

        return app(VideoGenPromptDegrader::class)->degradeVeo(
            $base,
            (string) ($slide->last_error_class ?? VideoGenErrorClassifier::TRANSIENT),
            (int) (($slide->repurposeJob->asset_retry_count ?? 0) + 1),
        );
    }

    /**
     * Render + composite the CTA ask overlay onto the finalized CTA clip. Returns
     * the new public URL, or null if either step fails (caller keeps the plain
     * clip — graceful degrade, the ask still ships in the caption).
     */
    private function applyCtaOverlay(RepurposeVideoSlide $slide): ?string
    {
        $overlayPng = app(VideoChromeRenderer::class)->renderCtaOverlay($slide);
        if ($overlayPng === null) {
            return null;
        }

        return app(VideoRebrandComposer::class)->overlayCta($slide, $overlayPng);
    }

    /**
     * Render + composite the hook TITLE overlay (cover headline from the original
     * IG hook) onto the finalized hook clip. Returns the new public URL, or null if
     * there's no title or either step fails (caller keeps the plain hook clip).
     */
    private function applyHookTitle(RepurposeVideoSlide $slide): ?string
    {
        $job = $slide->repurposeJob;
        $title = (string) (optional($job)->videoHookTitle() ?? '');
        if (trim($title) === '') {
            return null;
        }

        // Topic brand logo (e.g. Google), auto-resolved at keyframe-build time.
        $logo = $job ? ($job->extracted['hook_brand_logo'] ?? null) : null;

        $overlayPng = app(VideoChromeRenderer::class)->renderHookTitle($slide, $title, is_string($logo) ? $logo : null);
        if ($overlayPng === null) {
            return null;
        }

        return app(VideoRebrandComposer::class)->overlayClip($slide, $overlayPng, 'title');
    }

    private function poll(?string $uuid): ?array
    {
        if (empty($uuid)) {
            return null;
        }
        try {
            $resp = Http::timeout(15)
                ->withHeaders(['x-api-key' => $this->apiKey()])
                ->get("https://api.geminigen.ai/uapi/v1/history/{$uuid}");
        } catch (\Throwable $e) {
            Log::warning('[PollRebrandAssets] poll exception', ['uuid' => $uuid, 'error' => $e->getMessage()]);

            return null;
        }

        return $resp->successful() ? ($resp->json() ?? []) : null;
    }

    /**
     * GeminiGen ALWAYS returns error_code/error_message keys ("" when healthy),
     * so a real failure is status===3 OR a non-empty error string (see PollHookVideos).
     */
    private function hasError(array $data): bool
    {
        $errCode = is_string($data['error_code'] ?? null) ? trim($data['error_code']) : '';
        $errMsg = is_string($data['error_message'] ?? null) ? trim($data['error_message']) : '';

        return (int) ($data['status'] ?? 0) === 3 || $errCode !== '' || $errMsg !== '';
    }

    private function errorReason(array $data): string
    {
        $errMsg = is_string($data['error_message'] ?? null) ? trim($data['error_message']) : '';
        $errCode = is_string($data['error_code'] ?? null) ? trim($data['error_code']) : '';

        return $errMsg !== '' ? $errMsg : ($errCode !== '' ? $errCode : 'GeminiGen reported a render failure.');
    }

    /**
     * Combined error_code + error_message — the input for safety detection +
     * classification. GeminiGen frequently puts the actionable signal (figure /
     * audio / policy code) ONLY in error_code while error_message is generic prose.
     */
    private function errorSignature(array $data): string
    {
        $errCode = is_string($data['error_code'] ?? null) ? trim($data['error_code']) : '';
        $errMsg = is_string($data['error_message'] ?? null) ? trim($data['error_message']) : '';

        return trim($errCode.' '.$errMsg);
    }

    private function markStuck(RepurposeVideoSlide $slide, string $stage, string $reason, bool $dry): void
    {
        $age = $slide->updated_at ? (int) $slide->updated_at->diffInMinutes(now()) : 0;
        if ($age < self::STUCK_MINUTES) {
            return;
        }
        $col = $stage === 'veo' ? 'veo_status' : 'keyframe_status';
        if (! $dry) {
            // A stuck/poll-failed slide is an infra timeout, not a prompt fault →
            // transient (recover() retries the SAME prompt rather than degrading it).
            $slide->update([$col => 'failed', 'last_error' => "{$reason} (stuck {$age}min)", 'last_error_class' => VideoGenErrorClassifier::TRANSIENT]);
        }
        $this->warn("  slide {$slide->id} {$stage} stuck {$age}min → failed");
    }
}
