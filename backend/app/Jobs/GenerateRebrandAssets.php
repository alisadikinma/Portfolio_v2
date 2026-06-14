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

    /**
     * STATIC FALLBACK ONLY — used when VideoHookSceneAuthor (which carries the full
     * /carousel-gen hook knowledge as its system prompt) can't run (empty topic /
     * CLI failure / figure dropped). The authored hook is the real path; this is a
     * safe, self-contained default on the v3 Spotlight Portrait visual base. It
     * deliberately holds a CURIOSITY GAP — NO floating tool/list cards (revealing
     * the carousel's items in the hook kills the scroll-through).
     */
    public const KEYFRAME_PROMPT_HOOK = 'Cinematic vertical 9:16 Spotlight Portrait of the creator on a solid signature-blue (#0F59B6) studio background. '
        .'An intriguing, scroll-stopping expression — a knowing half-smile, eyebrow slightly raised as if about to reveal a secret, subject slightly off-center (rule of thirds). '
        .'Signature outfit: dark tee or henley under an unstructured blazer in a neutral slate-charcoal tone. '
        .'A single soft glowing element (an abstract orb / undefined holographic glow) hints that something is coming WITHOUT showing what — no readable UI, no tool logos, no list. Curiosity gap. '
        .'Lighting: cool-neutral ~5200K key + soft blue ambient bounce + a warm gold rim light separating him from the background (no warm-amber wash). '
        .'Hyperrealistic, anti-AI-look: visible skin pores, a few stray hairs catching light, natural fabric creases, subtle lens vignetting, slight asymmetry. '
        .'Sharp focus, 4K, no on-image text, no watermark.';

    public const KEYFRAME_PROMPT_CTA = 'Cinematic vertical 9:16 Spotlight Portrait of the creator on a deepened-navy variant of signature blue (#0F59B6) with a warm gold glow. '
        .'Warm, inviting, confident expression with an open-hand "join me" gesture toward the viewer. '
        .'Signature outfit: dark tee or henley under an unstructured blazer in a neutral slate-charcoal tone. '
        .'A few floating topic UI cards hover softly behind him as a mini value-recap, with gentle glow and depth blur. '
        .'Lighting: cool-neutral key + warm gold rim and a soft gold ambient glow for a friendly closing energy. '
        .'Hyperrealistic, anti-AI-look: visible skin pores, stray hairs, natural fabric creases, subtle lens vignetting, slight asymmetry. '
        .'Sharp focus, 4K, no on-image text, no watermark.';

    /**
     * Veo I2V motion prompts — follow the VEO 3.1 standard format (see
     * ai-video-promo-engine reference/image-video-gen/02-veo-production-guide.md
     * "I2V Prompt Template" + "Audio (NOT OPTIONAL)"). The explicit `Audio:` line
     * is MANDATORY: Veo 3.x ALWAYS generates audio and there is NO API param to
     * disable it (confirmed in the GeminiGen video-gen/veo docs + Google dev forum).
     *
     * The directive must give a CONCRETE, POSITIVE ambient bed — NOT a stack of
     * negations. An over-negated near-silence command ("quiet room tone ONLY, no
     * music, no dialogue, no subtitles, no audience sounds") leaves Veo's mandatory
     * audio model with no valid target → it emits a degenerate track → the whole
     * render fails with `PUBLIC_ERROR_AUDIO_FILTERED` (observed live June 13, 2026 —
     * hook + CTA both failed identically with that phrasing). The fix (per the
     * snubroot Veo-3 Prompting Guide v4.0 "Audio Hallucination Fixes": match audio
     * complexity to visual complexity, give one positive ambiance + at most one
     * negation) is a soft room-tone bed. Audio is stripped on download anyway — we
     * only need it to PASS, not to be heard.
     */
    public const VEO_PROMPT_HOOK = '6s, 720p, 9:16 vertical. Camera: locked-off static shot, no zoom or pan. '
        .'Subject: the creator holds the relaxed pose from the reference frame, subtle eye blinks every 2-3 seconds, '
        .'gentle micro-expressions, faint natural smile, mouth stays closed and not speaking. '
        .'Maintain visual continuity with reference frame character appearance throughout clip. '
        .'Ambient: floating side UI icons drift and bob gently with subtle parallax. '
        .'Audio: soft neutral room tone, gentle ambient hum, calm and quiet atmosphere, no music, no spoken words. '
        .'Maintain exact lighting, environment, appearance from reference frame. '
        .'Photorealistic, smooth natural motion, no morphing. 9:16 output.';

    public const VEO_PROMPT_CTA = '6s, 720p, 9:16 vertical. Camera: locked-off static shot, no zoom or pan. '
        .'Subject: the creator holds the warm inviting pose from the reference frame, a gentle welcoming hand gesture and soft nod, '
        .'subtle eye blinks, faint smile, mouth stays closed and not speaking. '
        .'Maintain visual continuity with reference frame character appearance throughout clip. '
        .'Ambient: soft gold glow, gentle light shift. '
        .'Audio: warm soft room tone, gentle ambient hum, calm inviting atmosphere, no music, no spoken words. '
        .'Maintain exact lighting, environment, appearance from reference frame. '
        .'Photorealistic, smooth natural motion, no morphing. 9:16 output.';

    /**
     * GROK (xAI) motion prompts — used when a bookend clip runs on GROK instead of
     * Veo (figure on the keyframe, or Veo audio-filter failover). GROK has NO audio
     * model to satisfy (audio is stripped on download), so — unlike VEO_PROMPT_* —
     * these carry NO `Audio:` clause. Motion-only, frame-respecting ("animate only
     * what exists"), and figure-safe ("everyone in the frame" covers the 2-subject
     * Ali+figure hook as well as a solo CTA).
     */
    public const GROK_PROMPT_HOOK = 'Everyone in the frame holds their relaxed pose and shares a warm easy smile with a gentle natural laugh, '
        .'light natural head movement and subtle eye blinks, '
        .'animate only the elements that already exist and introduce no new object or element, '
        .'both hands and anything being held stay exactly as in the frame with no duplicated or extra hand, '
        .'the camera stays completely static with no zoom or pan, '
        .'mouths stay closed and no one is speaking, '
        .'photorealistic with natural micro-motion and no morphing';

    public const GROK_PROMPT_CTA = 'The creator holds the warm inviting pose from the frame with a gentle welcoming hand gesture and a soft nod, '
        .'an easy friendly smile and subtle eye blinks, '
        .'animate only the elements that already exist and introduce no new object or element, '
        .'the camera stays completely static with no zoom or pan, '
        .'the mouth stays closed and the person is not speaking, '
        .'photorealistic with natural micro-motion and no morphing';

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
        // the static branded portrait this phase. Skip the costly SSH author when
        // the hook keyframe is already `done` (single-CTA / re-run regen where
        // dispatchKeyframe will skip the hook anyway) — saves a ~1-3min author call.
        [$hookRefs, $hookPrompt] = $hook->keyframe_status === 'done'
            ? [[$faceUrl], self::KEYFRAME_PROMPT_HOOK]
            : $this->buildHookKeyframe($job, $hook, $faceUrl);

        $dispatched = 0;
        $dispatched += $this->dispatchKeyframe($video, $hook, $hookPrompt, $hookRefs) ? 1 : 0;
        $dispatched += $this->dispatchKeyframe($video, $cta, self::KEYFRAME_PROMPT_CTA, [$faceUrl]) ? 1 : 0;

        if ($dispatched === 0) {
            // Both keyframe dispatches failed (circuit open / key missing / HTTP) —
            // the PollRebrandAssets recovery pass re-dispatches once the circuit closes.
            Log::warning('[GenerateRebrandAssets] both keyframe dispatches failed', ['job' => $job->id]);
        }

        // Re-skin the source tool slides in PARALLEL with the Veo bookend render.
        // Tool slides have no dependency on the hook/CTA clips, so a slow or failed
        // bookend must not block them — each becomes downloadable the moment it
        // composites. ComposeVideoCarousel (the assets_ready gate) still mops up
        // any straggler + owns the FSM advance to finalize.
        ComposeToolSlides::dispatch($job->id);

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
        // A4: a prior keyframe refused for content policy would be refused the same
        // way on a re-author — force the static safe scene instead of re-running the
        // SSH author. (recover() preserves last_error_class through the reset; it's
        // cleared on the next successful keyframe poll.)
        if ($hook->last_error_class === \App\Services\VideoGenErrorClassifier::CONTENT_POLICY) {
            return [[$faceUrl], self::KEYFRAME_PROMPT_HOOK];
        }

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

        // Auto-detect the topic's dominant brand → resolve a license-clean logo for
        // the hook overlay (e.g. "Google"). Best-effort: no brand, or a corporate
        // logo that fails the CC-license gate → no logo (hook ships with just the
        // title). Stored on the job; PollRebrandAssets' hook overlay reads it.
        $this->resolveHookBrandLogo($job, (string) ($authored['brand_name'] ?? ''));

        $refs = [$faceUrl];
        $figureName = $allowFigure ? ($authored['figure_name'] ?? null) : null;
        if ($figureName) {
            $entity = app(EntityReferenceService::class)->findOrFetch($figureName, 'person');
            $figureUrl = is_array($entity) ? ($entity['url'] ?? null) : null;
            if (is_string($figureUrl) && $figureUrl !== '') {
                $refs[] = $figureUrl; // license-checked + downloaded to our storage
                // Figure present → Veo (Google) will refuse to animate the celebrity
                // face (PROMINENT_PEOPLE). Route this bookend straight to GROK (xAI,
                // allows figures) so we never waste a Veo attempt. See PollRebrandAssets.
                $hook->forceFill(['video_provider' => RepurposeVideoSlide::PROVIDER_GROK])->save();
            } else {
                // Figure photo unresolvable (Wikidata/notability/license miss, or a
                // storage-write conflict). The authored scene was built AROUND the
                // figure — it references "image 2" — so shipping it with no ref-2
                // leaves a dangling celebrity reference that trips GeminiGen's
                // prominent-people filter at keyframe/Veo. Persist the drop sentinel
                // (so recovery never retries the same failing fetch) and RE-AUTHOR a
                // clean creator-only scene instead of returning the figure scene.
                $hook->forceFill(['figure_dropped' => true])->save();
                Log::info('[GenerateRebrandAssets] hook figure unresolved — re-authoring creator-only', [
                    'job' => $job->id,
                    'figure' => $figureName,
                ]);

                $creatorOnly = app(VideoHookSceneAuthor::class)->author($topic, false);
                $creatorScene = trim((string) ($creatorOnly['scene_prompt'] ?? ''));
                if (($creatorOnly['success'] ?? false) && $creatorScene !== '') {
                    return [[$faceUrl], $creatorScene];
                }

                // Re-author failed too → static creator-only keyframe (never the
                // figure scene). The hook still renders; it just isn't topic-bespoke.
                return [[$faceUrl], self::KEYFRAME_PROMPT_HOOK];
            }
        }

        return [$refs, (string) $authored['scene_prompt']];
    }

    /**
     * Resolve a license-clean logo for the topic's dominant brand and stash it on
     * the job (`extracted.hook_brand_logo`) for the hook title overlay. Best-effort
     * + idempotent — never blocks keyframe generation.
     */
    private function resolveHookBrandLogo(RepurposeJob $job, string $brand): void
    {
        $brand = trim($brand);
        if ($brand === '') {
            return;
        }
        try {
            $entity = app(EntityReferenceService::class)->findOrFetch($brand, 'logo');
            $url = is_array($entity) ? ($entity['url'] ?? null) : null;
            if (is_string($url) && $url !== '') {
                $extracted = (array) ($job->extracted ?? []);
                $extracted['hook_brand_logo'] = $url;
                $extracted['hook_brand_name'] = $brand;
                $job->update(['extracted' => $extracted]);
            } else {
                Log::info('[GenerateRebrandAssets] hook brand logo unresolved (license/notability)', ['job' => $job->id, 'brand' => $brand]);
            }
        } catch (\Throwable $e) {
            Log::info('[GenerateRebrandAssets] hook brand logo resolve failed', ['job' => $job->id, 'brand' => $brand, 'error' => $e->getMessage()]);
        }
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
            $slide->update(['keyframe_status' => 'failed', 'last_error' => 'keyframe dispatch failed (service unavailable or rejected)', 'last_error_class' => \App\Services\VideoGenErrorClassifier::TRANSIENT]);

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
