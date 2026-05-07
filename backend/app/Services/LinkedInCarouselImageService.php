<?php

namespace App\Services;

use App\Models\ImageGenerationJob;
use App\Models\LinkedInPost;
use App\Models\Setting;
use App\Support\LinkedInProgressEmitter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Loops a LinkedInPost's carousel_slides[] and dispatches one GeminiGen
 * image-generation job per slide.
 *
 * Lifecycle per slide:
 *   1. CarouselSlideEnhancer prepares prompt + face_refs + file_urls
 *   2. Service POSTs to GeminiGen /generate_image with carousel webhook URL
 *   3. ImageGenerationJob row created with type='carousel_slide',
 *      linkedin_post_id, slide_index, slide_image_role
 *   4. carousel_slides[i] is mutated in-place with image_status='generating'
 *      + image_job_uuid for frontend polling
 *   5. Webhook (LinkedInCarouselImageWebhookController) flips status to 'done'
 *      with image_url, or 'failed' with error_message
 *
 * Idempotency: skips slides that already have image_status='done' with a
 * non-empty image_url. Used by both the auto-dispatch (post-generation) path
 * and the manual retry-single-slide path.
 */
class LinkedInCarouselImageService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.geminigen.ai/uapi/v1';

    public function __construct(
        private readonly CarouselSlideEnhancer $enhancer
    ) {
        $this->apiKey = (string) config('services.geminigen.api_key', '');
    }

    /**
     * Dispatch all slides that don't yet have a 'done' image.
     * Returns count of jobs dispatched.
     */
    public function dispatchAllSlides(LinkedInPost $draft): int
    {
        $slides = $draft->carousel_slides ?? [];
        if (! is_array($slides) || count($slides) === 0) {
            Log::info('[LinkedInCarouselImage] no slides on draft', ['draft_id' => $draft->id]);
            return 0;
        }

        if (empty($this->apiKey)) {
            Log::error('[LinkedInCarouselImage] GEMINIGEN_API_KEY missing — cannot dispatch');
            return 0;
        }

        $totalSlides = count($slides);
        $dispatched = 0;

        foreach ($slides as $i => $slide) {
            // Idempotency: skip slides already done
            if (($slide['image_status'] ?? null) === 'done' && ! empty($slide['image_url'])) {
                continue;
            }

            $uuid = $this->dispatchOne($draft, $slide, $i, $totalSlides);
            if ($uuid !== null) {
                $slides[$i]['image_status'] = 'generating';
                $slides[$i]['image_job_uuid'] = $uuid;
                $slides[$i]['image_error'] = null;
                $dispatched++;
            } else {
                $slides[$i]['image_status'] = 'failed';
                $slides[$i]['image_error'] = 'GeminiGen dispatch failed (queue returned null)';
            }
        }

        $draft->update(['carousel_slides' => $slides]);

        Log::info('[LinkedInCarouselImage] dispatch summary', [
            'draft_id' => $draft->id,
            'total' => $totalSlides,
            'dispatched' => $dispatched,
        ]);

        return $dispatched;
    }

    /**
     * Dispatch a single slide. Used by retry endpoint + dispatchAllSlides loop.
     * Returns the GeminiGen job UUID, or null on failure.
     */
    public function dispatchSingleSlide(LinkedInPost $draft, int $slideIndex): ?string
    {
        $slides = $draft->carousel_slides ?? [];
        if (! isset($slides[$slideIndex])) {
            Log::warning('[LinkedInCarouselImage] dispatchSingleSlide: slide index out of range', [
                'draft_id' => $draft->id,
                'slide_index' => $slideIndex,
                'count' => count($slides),
            ]);
            return null;
        }

        $totalSlides = count($slides);
        $uuid = $this->dispatchOne($draft, $slides[$slideIndex], $slideIndex, $totalSlides);

        if ($uuid !== null) {
            $slides[$slideIndex]['image_status'] = 'generating';
            $slides[$slideIndex]['image_job_uuid'] = $uuid;
            $slides[$slideIndex]['image_error'] = null;
        } else {
            $slides[$slideIndex]['image_status'] = 'failed';
            $slides[$slideIndex]['image_error'] = 'GeminiGen dispatch failed (queue returned null)';
        }

        $draft->update(['carousel_slides' => $slides]);
        return $uuid;
    }

    /**
     * Single-slide dispatch core. Handles enhancement, GeminiGen API call,
     * ImageGenerationJob row creation. Returns UUID on success, null on
     * failure (caller updates carousel_slides[]).
     */
    private function dispatchOne(LinkedInPost $draft, array $slide, int $slideIndex, int $totalSlides): ?string
    {
        $enhanced = $this->enhancer->enhance($slide, $slideIndex, $totalSlides);
        $promptText = (string) ($enhanced['prompt_text'] ?? '');
        if ($promptText === '') {
            Log::warning('[LinkedInCarouselImage] empty prompt after enhance', [
                'draft_id' => $draft->id,
                'slide_index' => $slideIndex,
            ]);
            return null;
        }

        $layoutHint = (string) ($enhanced['layout_hint'] ?? 'body');
        $faceRefs = $enhanced['face_refs'] ?? [];
        $fileUrls = $enhanced['file_urls'] ?? [];

        $plannedFilename = $this->buildBrandedFilename($draft, $slideIndex, $layoutHint);

        // Build GeminiGen multipart payload (mirrors ImageGenerationService::queue).
        $apiUrl = (string) config('services.article_generation.api_url', config('app.url'));
        $webhookUrl = rtrim($apiUrl, '/') . '/automation/linkedin/carousel-image-webhook';

        // Append the "maintain facial identity" + "maintain visual consistency"
        // tail directives ourselves (ImageGenerationService::queue normally adds
        // them; we replicate here for parity since we're hitting GeminiGen
        // directly without that wrapper).
        $finalPrompt = $promptText;
        if (! empty($faceRefs)) {
            $finalPrompt .= '. Maintain exact facial identity, appearance, and features from the provided face reference image(s).';
        }
        if (! empty($fileUrls)) {
            $finalPrompt .= '. Reference the provided brand logo image for the centered watermark badge — use the exact logo, do not generate a new one.';
        }

        // GeminiGen / nano-banana-pro silently rejects non-standard aspect
        // ratios (verified — '4:5' fell back to '16:9' default in production
        // on draft #28's first render). Only Imagen-native ratios work:
        // 1:1, 16:9, 9:16, 4:3, 3:4. We use 3:4 (1080x1440 portrait) for
        // LinkedIn carousels — Imagen-native, taller than 1:1 so bilingual
        // headline + visual hook get more vertical real estate, and the
        // same image reuses cleanly on Instagram (will crop to 4:5 on Feed
        // but no letterbox) and TikTok photo carousel (centered with
        // minimal letterbox).
        $multipart = [
            ['name' => 'prompt', 'contents' => $finalPrompt],
            ['name' => 'model', 'contents' => $this->resolveModel()],
            ['name' => 'aspect_ratio', 'contents' => '3:4'],
            ['name' => 'style', 'contents' => 'Photorealistic'],
            ['name' => 'webhook', 'contents' => $webhookUrl],
            ['name' => 'webhook_url', 'contents' => $webhookUrl],
            ['name' => 'callback_url', 'contents' => $webhookUrl],
        ];

        // GeminiGen expects each ref URL as its own multipart entry, NOT
        // a JSON-encoded array. (Same gotcha as ImageGenerationService.)
        foreach (array_merge($faceRefs, $fileUrls) as $refUrl) {
            if (is_string($refUrl) && $refUrl !== '' && ! str_starts_with($refUrl, 'blob:')) {
                $multipart[] = ['name' => 'file_urls', 'contents' => $refUrl];
            }
        }

        try {
            $response = Http::timeout(30)
                ->withHeaders(['x-api-key' => $this->apiKey])
                ->asMultipart()
                ->post("{$this->baseUrl}/generate_image", $multipart);
        } catch (\Throwable $e) {
            Log::error('[LinkedInCarouselImage] HTTP exception', [
                'draft_id' => $draft->id,
                'slide_index' => $slideIndex,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if (! $response->successful()) {
            Log::error('[LinkedInCarouselImage] GeminiGen non-2xx', [
                'draft_id' => $draft->id,
                'slide_index' => $slideIndex,
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);
            return null;
        }

        $data = $response->json();
        $uuid = $data['uuid'] ?? null;
        $status = $data['status'] ?? 0;

        if (! $uuid) {
            Log::error('[LinkedInCarouselImage] no UUID in GeminiGen response', [
                'draft_id' => $draft->id,
                'slide_index' => $slideIndex,
                'body' => $data,
            ]);
            return null;
        }

        // Persist job row. type='carousel_slide' lets queries cleanly separate
        // these from article hero/inline jobs.
        $jobAttrs = [
            'post_id' => null,
            'linkedin_post_id' => $draft->id,
            'slide_index' => $slideIndex,
            'slide_image_role' => $layoutHint,
            'uuid' => $uuid,
            'type' => 'carousel_slide',
            'prompt' => Str::limit($finalPrompt, 4000), // text column safe cap
            'planned_filename' => $plannedFilename,
            'status' => 'processing',
        ];

        // If GeminiGen returned the image inline (status=2 means ready), shortcut.
        if ($status === 2 && isset($data['generate_result'])) {
            $localUrl = $this->downloadAndStore($data['generate_result'], $plannedFilename);
            $jobAttrs['status'] = 'completed';
            $jobAttrs['image_url'] = $localUrl;
            $jobAttrs['remote_url'] = $data['generate_result'];

            ImageGenerationJob::create($jobAttrs);

            // Mirror onto the slide directly (no need to wait for webhook)
            $this->mirrorSlideStatus($draft->id, $uuid, 'done', $localUrl);

            Log::info('[LinkedInCarouselImage] instant render', [
                'draft_id' => $draft->id,
                'slide_index' => $slideIndex,
                'uuid' => $uuid,
                'url' => $localUrl,
            ]);
            return $uuid;
        }

        ImageGenerationJob::create($jobAttrs);

        Log::info('[LinkedInCarouselImage] queued', [
            'draft_id' => $draft->id,
            'slide_index' => $slideIndex,
            'role' => $layoutHint,
            'uuid' => $uuid,
        ]);

        return $uuid;
    }

    /**
     * Webhook handler — called by GeminiGen via the carousel webhook endpoint.
     * Updates the matching ImageGenerationJob row + mirrors status onto the
     * LinkedInPost.carousel_slides[] entry.
     *
     * Returns true on success, false when UUID can't be matched.
     */
    public function handleWebhook(string $uuid, string $event, array $data): bool
    {
        $job = ImageGenerationJob::where('uuid', $uuid)
            ->where('type', 'carousel_slide')
            ->first();

        if (! $job) {
            Log::warning('[LinkedInCarouselImage] webhook: unknown carousel UUID', ['uuid' => $uuid]);
            return false;
        }

        if ($event === 'IMAGE_GENERATION_COMPLETED') {
            $remoteUrl = $data['media_url'] ?? null;
            if (! $remoteUrl) {
                $job->update(['status' => 'failed', 'error_message' => 'No media_url in webhook']);
                $this->mirrorSlideStatus($job->linkedin_post_id, $uuid, 'failed', null, 'No media_url in webhook');
                return false;
            }

            $localUrl = $this->downloadAndStore($remoteUrl, $job->planned_filename);

            $job->update([
                'status' => 'completed',
                'image_url' => $localUrl,
                'remote_url' => $remoteUrl,
            ]);

            $this->mirrorSlideStatus($job->linkedin_post_id, $uuid, 'done', $localUrl);
            $this->emitSlideProgress($job->linkedin_post_id, $job->slide_index, 'done');

            Log::info('[LinkedInCarouselImage] webhook completed', [
                'draft_id' => $job->linkedin_post_id,
                'slide_index' => $job->slide_index,
                'url' => $localUrl,
            ]);
            return true;
        }

        if ($event === 'IMAGE_GENERATION_FAILED') {
            $reason = $data['error_message'] ?? 'Unknown error';
            $job->update(['status' => 'failed', 'error_message' => $reason]);
            $this->mirrorSlideStatus($job->linkedin_post_id, $uuid, 'failed', null, $reason);
            $this->emitSlideProgress($job->linkedin_post_id, $job->slide_index, 'failed', $reason);

            Log::error('[LinkedInCarouselImage] webhook failed', [
                'draft_id' => $job->linkedin_post_id,
                'slide_index' => $job->slide_index,
                'reason' => $reason,
            ]);

            // Safety-aware auto-retry. GeminiGen refuses prompts naming public
            // figures, minors, brands, or unsafe content — retrying the same
            // prompt is futile. Detect, sanitize, redispatch. Idempotent via
            // image_prompt_pre_safety sentinel — only rewrites once per slide
            // so a sanitized-prompt-still-failing scenario doesn't loop.
            $this->maybeAutoRetryOnSafety($job, $reason);

            return false;
        }

        return false;
    }

    /**
     * If the failure looks like a GeminiGen safety policy refusal AND we
     * haven't already rewritten this slide, rewrite the image_prompt via
     * Sonnet (text-only sync, ~10-15s) and re-dispatch the slide.
     *
     * Drops face_refs implicitly because the redispatch goes through
     * dispatchSingleSlide → CarouselSlideEnhancer, which re-resolves face
     * URLs from layout_hint. For at-risk human-fingerprint/cover slides
     * the rewriter strips identifiable proper nouns from the prompt body
     * so the enhancer's face injection no longer collides with safety
     * policy.
     *
     * Gated by ARTICLE_GEN_USE_SAFETY_REWRITE (default true). Failures
     * are logged but never thrown — the slide stays in 'failed' state and
     * the operator can use the per-slide Retry button manually.
     */
    private function maybeAutoRetryOnSafety(ImageGenerationJob $job, string $reason): void
    {
        $draftId = $job->linkedin_post_id;
        $slideIndex = $job->slide_index;
        if ($draftId === null || $slideIndex === null) {
            return;
        }

        if (!$this->rewriteSlidePromptIfSafetyError($draftId, $slideIndex, $reason)) {
            return;
        }

        // Re-dispatch with sanitized prompt. dispatchSingleSlide re-runs
        // CarouselSlideEnhancer which adds chrome + (now appropriate) face
        // refs for the new layout_hint.
        $newDraft = LinkedInPost::find($draftId);
        if ($newDraft) {
            $newUuid = $this->dispatchSingleSlide($newDraft, $slideIndex);
            Log::info('[LinkedInCarouselImage] safety auto-retry dispatched', [
                'draft_id' => $draftId,
                'slide_index' => $slideIndex,
                'new_uuid' => $newUuid,
            ]);
        }
    }

    /**
     * Public entry for manual-retry path. Inspects the slide's existing
     * `image_error` and applies the safety rewrite if it matches the
     * safety-error class. Returns true when the prompt was rewritten so
     * the caller knows the next dispatchSingleSlide will use the
     * sanitized version.
     *
     * Idempotent — short-circuits on the `image_prompt_pre_safety` sentinel.
     */
    public function applySafetyRewriteIfNeeded(LinkedInPost $draft, int $slideIndex): bool
    {
        $slides = $draft->carousel_slides ?? [];
        if (!isset($slides[$slideIndex])) {
            return false;
        }
        $reason = (string) ($slides[$slideIndex]['image_error'] ?? '');
        if ($reason === '') {
            return false;
        }
        return $this->rewriteSlidePromptIfSafetyError($draft->id, $slideIndex, $reason);
    }

    /**
     * Tiered rewrite (no dispatch). Detects safety errors and progressively
     * sanitizes the slide prompt. Three tiers:
     *
     *   Tier 0 → Tier 1: per-class Sonnet rewrite (POLICY_PERSON keeps brands;
     *     POLICY_BRAND keeps persons; POLICY_NSFW softens; POLICY_MINOR
     *     forces adult; POLICY_GENERIC strips everything).
     *
     *   Tier 1 → Tier 2: in-process generic-stock fallback prompt (no Sonnet
     *     call). Uses layout_hint to pick a stock template that's known
     *     safe — no proper nouns, no scene specifics, no human focus.
     *
     *   Tier 2 → Permanent: mark `image_status='failed_permanent'`, dispatch
     *     `carousel_slide_tier2_failed` Telegram notify. No further auto-mutation.
     *
     * Returns true if the slide was mutated (prompt rewritten OR marked
     * failed_permanent — caller knows whether to redispatch).
     */
    private function rewriteSlidePromptIfSafetyError(int $draftId, int $slideIndex, string $reason): bool
    {
        $imgSvc = app(\App\Services\ImageGenerationService::class);
        $errorClass = $imgSvc->classifyError($reason);

        // Only POLICY_* errors trigger the tiered rewrite. Transient / permanent
        // / unknown errors are handled by the existing GeminiGen retry path or
        // surfaced to the operator unchanged.
        $isPolicyClass = in_array($errorClass, [
            \App\Enums\PipelineErrorClass::PolicyPerson,
            \App\Enums\PipelineErrorClass::PolicyMinor,
            \App\Enums\PipelineErrorClass::PolicyNsfw,
            \App\Enums\PipelineErrorClass::PolicyBrand,
            \App\Enums\PipelineErrorClass::PolicyGeneric,
        ], true);
        if (!$isPolicyClass) {
            return false;
        }

        if (!config('services.article_generation.use_safety_rewrite', true)) {
            return false;
        }

        $draft = LinkedInPost::find($draftId);
        if (!$draft) {
            return false;
        }

        $slides = $draft->carousel_slides ?? [];
        if (!isset($slides[$slideIndex])) {
            return false;
        }

        $slide = $slides[$slideIndex];
        $tier = (int) ($slide['image_rewrite_tier'] ?? 0);

        // ============== Tier 2 → Permanent failure ==============
        if ($tier >= 2) {
            $this->markSlideFailedPermanent($draftId, $slideIndex, $reason);
            return true;
        }

        // ============== Tier 1 → Tier 2 (generic-stock fallback) ==============
        if ($tier === 1) {
            $genericPrompt = $this->buildGenericStockPrompt(
                (string) ($slide['layout_hint'] ?? 'body'),
                (string) ($slide['copy'] ?? $slide['copy_id'] ?? '')
            );

            DB::transaction(function () use ($draftId, $slideIndex, $genericPrompt) {
                $locked = LinkedInPost::lockForUpdate()->find($draftId);
                if (!$locked) return;
                $s = $locked->carousel_slides ?? [];
                if (!isset($s[$slideIndex])) return;

                $s[$slideIndex]['image_prompt'] = $genericPrompt;
                $s[$slideIndex]['image_rewrite_tier'] = 2;
                $s[$slideIndex]['image_status'] = 'pending';
                $s[$slideIndex]['image_error'] = null;
                $s[$slideIndex]['image_job_uuid'] = null;
                $locked->update(['carousel_slides' => $s]);
            });

            Log::info('[LinkedInCarouselImage] tier-2 generic-stock fallback applied', [
                'draft_id' => $draftId,
                'slide_index' => $slideIndex,
                'error_class' => $errorClass->value,
            ]);
            return true;
        }

        // ============== Tier 0 → Tier 1 (per-class Sonnet rewrite) ==============
        $originalPrompt = (string) ($slide['image_prompt'] ?? '');
        if (trim($originalPrompt) === '') {
            return false;
        }

        try {
            $articleGen = app(\App\Services\ArticleGenerationService::class);
            $result = $articleGen->rewriteVisualDirectionForSafety(
                $originalPrompt,
                $reason,
                [
                    'label' => (string) ($slide['layout_hint'] ?? 'body'),
                    'concept' => (string) ($slide['copy'] ?? $slide['copy_id'] ?? ''),
                ],
                $errorClass
            );
        } catch (\Throwable $e) {
            Log::error('[LinkedInCarouselImage] safety rewrite threw', [
                'draft_id' => $draftId,
                'slide_index' => $slideIndex,
                'error' => $e->getMessage(),
            ]);
            return false;
        }

        if (!($result['success'] ?? false) || empty($result['rewritten_vd'])) {
            Log::warning('[LinkedInCarouselImage] safety rewrite returned no output', [
                'draft_id' => $draftId,
                'slide_index' => $slideIndex,
                'error' => $result['error'] ?? 'unknown',
            ]);
            return false;
        }

        $rewritten = (string) $result['rewritten_vd'];
        $errorClassValue = $errorClass->value;

        DB::transaction(function () use ($draftId, $slideIndex, $rewritten, $originalPrompt, $errorClassValue) {
            $locked = LinkedInPost::lockForUpdate()->find($draftId);
            if (!$locked) return;
            $s = $locked->carousel_slides ?? [];
            if (!isset($s[$slideIndex])) return;

            $s[$slideIndex]['image_prompt_pre_safety'] = $originalPrompt;
            $s[$slideIndex]['image_prompt'] = $rewritten;
            $s[$slideIndex]['image_rewrite_tier'] = 1;
            $s[$slideIndex]['image_status'] = 'pending';
            $s[$slideIndex]['image_error'] = null;
            $s[$slideIndex]['image_job_uuid'] = null;
            $s[$slideIndex]['last_classified_error_class'] = $errorClassValue;
            // Drop face_refs from human_fingerprint specifically — that
            // layout's whole purpose is "creator face holding a callout",
            // and when paired with named-entity copy GeminiGen refuses.
            // Cover/CTA still get the face injected by enhancer because
            // those are brand-defining slides where we want Ali shown.
            if (($s[$slideIndex]['layout_hint'] ?? null) === 'human_fingerprint') {
                $s[$slideIndex]['layout_hint'] = 'body';
            }
            $locked->update(['carousel_slides' => $s]);
        });

        return true;
    }

    /**
     * Mark a slide as permanently failed (tier 2 fallback also rejected).
     * Surfaces NB2 error verbatim to admin via `image_error` and dispatches
     * a Telegram notify so the operator can decide whether to skip + republish
     * the carousel without this slide.
     */
    private function markSlideFailedPermanent(int $draftId, int $slideIndex, string $reason): void
    {
        DB::transaction(function () use ($draftId, $slideIndex, $reason) {
            $locked = LinkedInPost::lockForUpdate()->find($draftId);
            if (!$locked) return;
            $s = $locked->carousel_slides ?? [];
            if (!isset($s[$slideIndex])) return;

            $s[$slideIndex]['image_status'] = 'failed_permanent';
            $s[$slideIndex]['image_error'] = '[Tier 2 fallback failed] ' . $reason;
            $s[$slideIndex]['image_job_uuid'] = null;
            $locked->update(['carousel_slides' => $s]);
        });

        try {
            \App\Jobs\DispatchPipelineTelegramEvent::dispatch(
                'carousel_slide_tier2_failed',
                [
                    'draft_id' => $draftId,
                    'slide_index' => $slideIndex,
                    'error' => $reason,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('[LinkedInCarouselImage] tier-2 failure telegram notify failed', [
                'draft_id' => $draftId,
                'slide_index' => $slideIndex,
                'error' => $e->getMessage(),
            ]);
        }

        Log::warning('[LinkedInCarouselImage] slide marked failed_permanent (tier 2 exhausted)', [
            'draft_id' => $draftId,
            'slide_index' => $slideIndex,
            'reason' => $reason,
        ]);
    }

    /**
     * Build a layout-aware generic-stock prompt that's known safe under
     * NB2 content policy. No proper nouns, no scene specifics, no human
     * focus. Brand chrome (logo, page indicator, swipe text) still gets
     * appended later via CarouselSlideEnhancer, so the slide stays
     * on-brand even though the scene itself is generic.
     */
    private function buildGenericStockPrompt(string $layoutHint, string $copy): string
    {
        $base = 'Professional studio photograph, photorealistic, soft natural lighting, neutral background, business context, no recognizable people, no brands, no logos, 4:5 aspect ratio.';

        // Truncate copy to a safe length and strip any obvious proper nouns
        // (uppercase tokens) so even the copy reference stays generic.
        $contextHint = trim(preg_replace('/\b[A-Z][a-z]+(?:\s+[A-Z][a-z]+)+\b/', '...', $copy) ?? '');
        $contextHint = mb_substr($contextHint, 0, 120);

        return match ($layoutHint) {
            'cover' => "{$base} Hero composition with subtle depth, rule of thirds, soft bokeh background. Subject context: {$contextHint}",
            'data_point' => "{$base} Clean infographic-style layout with abstract data visualization elements (subtle line graph, soft geometric shapes), no text overlay.",
            'human_fingerprint', 'body' => "{$base} Generic workspace scene, abstract human silhouette in soft focus, modern desk with laptop closed, ambient morning light.",
            'cta' => "{$base} Minimalist composition with breathing room for text overlay. Soft gradient background, single focal element off-center.",
            default => $base,
        };
    }

    /**
     * Update the matching slide entry in the LinkedInPost.carousel_slides JSON
     * column. Runs inside DB::transaction with row lock to keep concurrent
     * webhooks (multiple slides finishing at once) from clobbering each other.
     */
    /**
     * Compute and emit pipeline-level progress whenever a slide flips
     * status. Renders a linear ramp from 72% (post-dispatch) → 100% based
     * on done_count / total_slides. A slide failure flips current_step to
     * mark the pipeline as needing attention but doesn't halt — operators
     * can still publish a partial carousel via the per-slide retry button.
     */
    private function emitSlideProgress(?int $draftId, ?int $slideIndex, string $newStatus, ?string $reason = null): void
    {
        if ($draftId === null) {
            return;
        }

        $draft = LinkedInPost::find($draftId);
        if (! $draft || $draft->format !== 'carousel') {
            return;
        }

        $slides = is_array($draft->carousel_slides) ? $draft->carousel_slides : [];
        $total = count($slides);
        if ($total === 0) {
            return;
        }

        $done = 0;
        $failed = 0;
        foreach ($slides as $slide) {
            $st = $slide['image_status'] ?? null;
            if ($st === 'done') {
                $done++;
            } elseif ($st === 'failed') {
                $failed++;
            }
        }

        // Linear ramp 72 → 100 across done count.
        $pct = (int) round(72 + (($done / $total) * 28));
        $pct = max(72, min(100, $pct));

        $oneBased = $slideIndex !== null ? $slideIndex + 1 : null;
        if ($newStatus === 'done') {
            $message = $oneBased !== null
                ? "Slide {$oneBased}/{$total} rendered · {$done}/{$total} complete"
                : "{$done}/{$total} slides complete";
        } elseif ($newStatus === 'failed') {
            $reasonShort = $reason !== null ? ' · ' . mb_strimwidth($reason, 0, 80, '…') : '';
            $message = $oneBased !== null
                ? "Slide {$oneBased}/{$total} failed · {$done}/{$total} done · {$failed} failed{$reasonShort}"
                : "{$done}/{$total} done · {$failed} failed{$reasonShort}";
        } else {
            $message = "{$done}/{$total} slides complete";
        }

        $step = $done >= $total
            ? 'render_complete'
            : ($newStatus === 'failed' ? 'render_partial_failure' : 'render_progress');

        LinkedInProgressEmitter::emit($draft, $step, $pct, $message);
    }

    private function mirrorSlideStatus(?int $draftId, string $uuid, string $status, ?string $imageUrl = null, ?string $errorMessage = null): void
    {
        if ($draftId === null) {
            return;
        }

        DB::transaction(function () use ($draftId, $uuid, $status, $imageUrl, $errorMessage) {
            $draft = LinkedInPost::lockForUpdate()->find($draftId);
            if (! $draft) {
                return;
            }

            $slides = $draft->carousel_slides ?? [];
            $matched = false;

            foreach ($slides as $i => $slide) {
                if (($slide['image_job_uuid'] ?? null) === $uuid) {
                    $slides[$i]['image_status'] = $status;
                    if ($imageUrl !== null) {
                        $slides[$i]['image_url'] = $imageUrl;
                    }
                    if ($errorMessage !== null) {
                        $slides[$i]['image_error'] = $errorMessage;
                    } elseif ($status === 'done') {
                        $slides[$i]['image_error'] = null;
                    }
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                Log::warning('[LinkedInCarouselImage] mirrorSlideStatus: uuid not found on draft slides', [
                    'draft_id' => $draftId,
                    'uuid' => $uuid,
                ]);
                return;
            }

            $draft->update(['carousel_slides' => $slides]);
        });
    }

    /**
     * Filename pattern: {brand}-li-{draft_id}-slide-{N}-{role}.png
     * e.g., alisadikinma-li-28-slide-01-cover.png
     *       alisadikinma-li-28-slide-09-cta.png
     */
    private function buildBrandedFilename(LinkedInPost $draft, int $slideIndex, string $role): string
    {
        $brandSlug = Setting::where('group', 'creator_brand')
            ->where('key', 'creator_brand_slug')
            ->value('value') ?: 'alisadikinma';

        $padded = str_pad((string) ($slideIndex + 1), 2, '0', STR_PAD_LEFT);
        $roleSlug = Str::slug($role) ?: 'body';
        $base = "{$brandSlug}-li-{$draft->id}-slide-{$padded}-{$roleSlug}";

        $candidate = "{$base}.png";
        $suffix = 2;
        while (ImageGenerationJob::where('planned_filename', $candidate)->exists()) {
            $candidate = "{$base}-v{$suffix}.png";
            $suffix++;
        }

        return $candidate;
    }

    private function resolveModel(): string
    {
        return (string) (config('services.geminigen.linkedin_carousel_model')
            ?? config('content.default_image_model')
            ?? 'nano-banana-pro');
    }

    /**
     * Download a remote image to public storage and return the local URL.
     * Reuses the same path convention as ImageGenerationService — keeps
     * carousel slide PNGs alongside article images.
     */
    public function downloadAndStore(string $imageUrl, ?string $customFilename = null): ?string
    {
        try {
            $response = Http::timeout(30)->get($imageUrl);
            if (! $response->successful()) {
                Log::error('[LinkedInCarouselImage] download non-2xx', [
                    'url' => $imageUrl,
                    'status' => $response->status(),
                ]);
                return $imageUrl;
            }

            $body = $response->body();
            if (empty($body) || strlen($body) < 1024) {
                Log::error('[LinkedInCarouselImage] download too small', [
                    'url' => $imageUrl,
                    'bytes' => strlen($body),
                ]);
                return $imageUrl;
            }

            $filename = $customFilename
                ? 'linkedin-carousel/' . $customFilename
                : 'linkedin-carousel/' . time() . '_' . uniqid() . '.png';

            $written = Storage::disk('public')->put($filename, $body);
            if (! $written || ! Storage::disk('public')->exists($filename)) {
                Log::error('[LinkedInCarouselImage] storage put failed', ['file' => $filename]);
                return $imageUrl;
            }

            Log::info('[LinkedInCarouselImage] stored', [
                'file' => $filename,
                'bytes' => strlen($body),
            ]);
            return url('/storage/' . $filename);
        } catch (\Throwable $e) {
            Log::error('[LinkedInCarouselImage] download exception', [
                'url' => $imageUrl,
                'error' => $e->getMessage(),
            ]);
            return $imageUrl;
        }
    }
}
