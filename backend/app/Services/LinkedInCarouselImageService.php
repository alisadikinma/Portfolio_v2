<?php

namespace App\Services;

use App\Models\ImageGenerationJob;
use App\Models\LinkedInPost;
use App\Models\Setting;
use App\Support\LinkedInProgressEmitter;
use Illuminate\Support\Facades\Artisan;
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
    /**
     * Max bounded auto-retries for a TRANSIENT/UNKNOWN slide render failure
     * before the slide is marked failed_permanent. Per-slide counter lives in
     * carousel_slides[i].image_retry_count.
     */
    private const MAX_TRANSIENT_RETRIES = 3;

    private string $apiKey;
    private string $baseUrl = 'https://api.geminigen.ai/uapi/v1';

    public function __construct(
        private readonly CarouselSlideEnhancer $enhancer,
        private readonly GeminiGenCircuitBreaker $breaker
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

        // Circuit breaker gate — when GeminiGen is in an outage window, skip
        // dispatch entirely. Slides stay in their current status (typically
        // 'pending'), so the operator sees "awaiting service recovery" rather
        // than N slides flipped to 'failed'. The probe + ReapStuck cron will
        // re-attempt once the breaker closes.
        if ($this->breaker->state() === 'open') {
            Log::warning('[LinkedInCarouselImage] dispatchAllSlides skipped — circuit OPEN', [
                'draft_id' => $draft->id,
                'slide_count' => count($slides),
            ]);
            return 0;
        }

        // Topic-aware public-figure cover (original blog→carousel only; IG-source
        // skipped). Idempotent + fail-safe — authors at most once, any failure
        // leaves the plugin's creator cover untouched. Re-read slides after.
        $slides = $this->enrichCoverFigure($draft, $slides);

        $totalSlides = count($slides);
        $dispatched = 0;

        foreach ($slides as $i => $slide) {
            $st = $slide['image_status'] ?? null;
            // Idempotency: skip slides already done
            if ($st === 'done' && ! empty($slide['image_url'])) {
                continue;
            }
            // Terminal: never re-render a slide whose tiered safety rewrite OR
            // bounded transient retries are exhausted (failed_permanent). The
            // operator skips + republishes manually.
            if ($st === 'failed_permanent') {
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
        // Circuit breaker gate — same rationale as dispatchAllSlides. Slide
        // status stays unchanged so the operator can retry once the breaker
        // recovers.
        if ($this->breaker->state() === 'open') {
            Log::warning('[LinkedInCarouselImage] dispatchSingleSlide skipped — circuit OPEN', [
                'draft_id' => $draft->id,
                'slide_index' => $slideIndex,
            ]);
            return null;
        }

        // Re-rendering the cover alone must still pick up the topic-aware figure.
        // The enricher is idempotent on carousel_slides[cover].figure_enriched, so
        // an EXPLICIT operator re-render of the cover clears that lock first —
        // otherwise a cover resolved as creator-only before a topic/figure fix
        // (e.g. empty_topic / figure_unresolved) could only gain its figure via a
        // full, token-heavy "Regenerate All Images". This makes single-slide
        // re-render the cheap equivalent of the fresh slides regenerate produces.
        $this->resetCoverFigureLockIfTargetingCover($draft, $slideIndex);
        $this->enrichCoverFigure($draft, $draft->carousel_slides ?? []);

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
     * Apply topic-aware public-figure cover enrichment, returning the (possibly
     * mutated) slides array. Idempotent + fully non-fatal — any failure logs and
     * returns the input slides unchanged so image dispatch always proceeds with
     * the plugin's creator-fronted cover.
     *
     * @param  array<int,array<string,mixed>>  $slides
     * @return array<int,array<string,mixed>>
     */
    private function enrichCoverFigure(LinkedInPost $draft, array $slides): array
    {
        try {
            app(CarouselCoverFigureEnricher::class)->enrich($draft);

            return $draft->carousel_slides ?? $slides;
        } catch (\Throwable $e) {
            Log::warning('[LinkedInCarouselImage] cover figure enrich failed (non-fatal)', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);

            return $slides;
        }
    }

    /**
     * When the operator re-renders the COVER slide alone, clear the
     * figure_enriched idempotency flag so CarouselCoverFigureEnricher runs again
     * (re-resolving the topic-aware public figure). No-op for body/CTA slides,
     * when the target index isn't the cover, and when the cover was never locked.
     * Only an explicit operator action reaches here, so a one-off re-run of the
     * (cheap, text-only) SSH author is intended — no image tokens are spent until
     * the slide actually renders.
     */
    private function resetCoverFigureLockIfTargetingCover(LinkedInPost $draft, int $slideIndex): void
    {
        $slides = $draft->carousel_slides ?? [];
        if (! isset($slides[$slideIndex]) || ! $this->isCoverSlide($slides, $slideIndex)) {
            return;
        }
        if (empty($slides[$slideIndex]['figure_enriched'])) {
            return; // not locked — nothing to reset
        }
        // Already carries a resolved figure → re-render the existing interaction
        // prompt directly (the enricher would short-circuit anyway). Only a
        // creator-only lock (figure_enriched but NO figure attached) is worth
        // clearing to give the enricher another attempt — that's the stale
        // empty_topic / figure_unresolved case this gate exists for.
        if (! empty($slides[$slideIndex]['entity_face_ref'])) {
            return;
        }

        unset($slides[$slideIndex]['figure_enriched']);
        $draft->update(['carousel_slides' => $slides]);

        Log::info('[LinkedInCarouselImage] cleared cover figure_enriched lock for explicit single re-render', [
            'draft_id' => $draft->id,
            'slide_index' => $slideIndex,
        ]);
    }

    /**
     * Mirror of CarouselCoverFigureEnricher::coverIndex resolution so the targeted
     * index and the enricher's cover index agree: first is_cover===true slide,
     * else first layout_hint==='cover', else index 0.
     *
     * @param  array<int,array<string,mixed>>  $slides
     */
    private function isCoverSlide(array $slides, int $slideIndex): bool
    {
        foreach ($slides as $i => $s) {
            if (! empty($s['is_cover'])) {
                return $i === $slideIndex;
            }
        }
        foreach ($slides as $i => $s) {
            if (($s['layout_hint'] ?? null) === 'cover') {
                return $i === $slideIndex;
            }
        }

        return $slideIndex === 0;
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

        // Two-subject figure cover: re-host each face under the NEUTRAL basename
        // the prompt anchors to (subject-1.png / subject-2.jpg) so GeminiGen binds
        // each face by filename — the original figure file (e.g. "Q…_sam-altman.jpg")
        // both leaks the name and gives the model only an ordinal anchor, which
        // rendered the 2nd subject as a random person.
        $aliases = $enhanced['face_ref_aliases'] ?? [];
        if (! empty($aliases)) {
            $hosted = $this->hostNeutralRefs($draft, $slideIndex, $aliases);
            if (empty($hosted)) {
                // The prompt already anchors to subject-N.<ext>. Dispatching the
                // original refs would BOTH mismatch the handle the prompt names
                // AND leak the figure's name to GeminiGen — fail the slide so the
                // reaper retries (re-hosting is idempotent), never send a mismatch.
                Log::warning('[LinkedInCarouselImage] neutral ref hosting failed on figure cover — failing slide for retry', [
                    'draft_id' => $draft->id,
                    'slide_index' => $slideIndex,
                ]);

                return null;
            }
            $faceRefs = $hosted;
        }

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
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            // Network-level failure — counts toward circuit trip.
            $this->breaker->recordFailure(null, null, $e);
            Log::error('[LinkedInCarouselImage] HTTP connection exception', [
                'draft_id' => $draft->id,
                'slide_index' => $slideIndex,
                'error' => $e->getMessage(),
            ]);
            return null;
        } catch (\Throwable $e) {
            Log::error('[LinkedInCarouselImage] HTTP exception', [
                'draft_id' => $draft->id,
                'slide_index' => $slideIndex,
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if (! $response->successful()) {
            // Classify failure for the breaker — 5xx + 429 count, prompt-class
            // 4xx (PUBLIC_ERROR_*) are ignored because the April 28 safety
            // auto-rewrite handles them.
            $errorCode = $response->json('error_code') ?? null;
            $this->breaker->recordFailure($response->status(), $errorCode);

            Log::error('[LinkedInCarouselImage] GeminiGen non-2xx', [
                'draft_id' => $draft->id,
                'slide_index' => $slideIndex,
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 500),
            ]);
            return null;
        }

        // 2xx — record success so isolated 5xx don't accumulate.
        $this->breaker->recordSuccess();

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
            $jobAttrs['remote_url'] = $data['generate_result'];

            if (! $this->isLocalStorageUrl($localUrl)) {
                // downloadAndStore fell back to the remote URL — never mark the
                // slide 'done' carrying it (see isLocalStorageUrl). Hold for the
                // reaper to re-dispatch.
                $jobAttrs['status'] = 'failed';
                $jobAttrs['error_message'] = 'Image download/store failed — remote URL not usable';
                ImageGenerationJob::create($jobAttrs);
                $this->mirrorSlideStatus($draft->id, $uuid, 'failed', null, 'Image download failed — will retry');
                Log::warning('[LinkedInCarouselImage] instant render download did not yield a local URL — slide held for retry', [
                    'draft_id' => $draft->id,
                    'slide_index' => $slideIndex,
                    'uuid' => $uuid,
                    'remote_url' => $data['generate_result'],
                ]);
                return $uuid;
            }

            $jobAttrs['status'] = 'completed';
            $jobAttrs['image_url'] = $localUrl;

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
     * Re-host each face reference under its neutral, per-render basename
     * (subject-1.png / subject-2.jpg) so the file handle GeminiGen sees matches
     * the filename the prompt anchors to. Downloads each source ref (on our own
     * domain) and republishes it to the public disk under the requested basename.
     *
     * Returns the hosted public URLs IN ORDER, or [] if ANY ref fails (caller
     * then keeps the original refs — degraded, never broken). Idempotent: the
     * per-draft/slide path is overwritten on re-render.
     *
     * @param  array<int,array{url?:string,as?:string}>  $aliases
     * @return array<int,string>
     */
    private function hostNeutralRefs(LinkedInPost $draft, int $slideIndex, array $aliases): array
    {
        $out = [];
        foreach ($aliases as $alias) {
            $src = trim((string) ($alias['url'] ?? ''));
            // basename last — guard path traversal AND re-check empty AFTER it
            // (basename('/') === '' would otherwise slip an empty name through).
            $as = basename(trim((string) ($alias['as'] ?? '')));
            if ($src === '' || $as === '') {
                return [];
            }

            try {
                $resp = Http::timeout(20)->get($src);
            } catch (\Throwable $e) {
                Log::warning('[LinkedInCarouselImage] neutral ref host download threw', [
                    'draft_id' => $draft->id,
                    'slide_index' => $slideIndex,
                    'error' => $e->getMessage(),
                ]);

                return [];
            }
            if (! $resp->successful()) {
                Log::warning('[LinkedInCarouselImage] neutral ref host download non-2xx', [
                    'draft_id' => $draft->id,
                    'slide_index' => $slideIndex,
                    'status' => $resp->status(),
                ]);

                return [];
            }
            // Guard against a redirect/error body (HTML/XML) being stored as an
            // image and confusing GeminiGen. The refs are our own image files.
            $contentType = (string) $resp->header('Content-Type');
            if ($contentType !== '' && ! str_starts_with($contentType, 'image/')) {
                Log::warning('[LinkedInCarouselImage] neutral ref returned non-image content-type', [
                    'draft_id' => $draft->id,
                    'slide_index' => $slideIndex,
                    'content_type' => $contentType,
                ]);

                return [];
            }

            $rel = "linkedin-carousel/refs/{$draft->id}/{$slideIndex}/{$as}";
            Storage::disk('public')->put($rel, $resp->body());
            $out[] = url('/storage/' . $rel);
        }

        return $out;
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
        // Record failures against the breaker BEFORE looking up the job row —
        // even orphan webhooks (job row deleted, race condition) should count
        // toward the outage signal if they carry a server-class error code.
        // Safety-class refusals (PUBLIC_ERROR_*) are filtered out by
        // GeminiGenCircuitBreaker::classifyFailure — handled by the April 28
        // safety auto-rewrite, not an outage indicator.
        if ($event === 'IMAGE_GENERATION_FAILED') {
            $errorCode = $data['error_code'] ?? null;
            $errorCodeStr = is_string($errorCode) ? $errorCode : null;

            // GeminiGenCircuitBreaker::classifyFailure inspects error_code only
            // within the 4xx range — but webhook failures carry no real HTTP
            // status. Classify under 400 first so prompt-class codes
            // (PUBLIC_ERROR_*) are filtered out (handled by April 28 safety
            // auto-rewrite, NOT an outage signal). If the 400-range path
            // returns 'ignore', fall back to 500 to treat unrecognized
            // failures as server-side outage signal.
            $classification = GeminiGenCircuitBreaker::classifyFailure(400, $errorCodeStr);
            if ($classification === 'ignore') {
                $classification = GeminiGenCircuitBreaker::classifyFailure(500, $errorCodeStr);
            }
            if ($classification === 'count') {
                $this->breaker->recordFailure(500, $errorCodeStr);
            }
        }

        $job = ImageGenerationJob::where('uuid', $uuid)
            ->where('type', 'carousel_slide')
            ->first();

        if (! $job) {
            Log::warning('[LinkedInCarouselImage] webhook: unknown carousel UUID', ['uuid' => $uuid]);
            return false;
        }

        if ($event === 'IMAGE_GENERATION_COMPLETED') {
            // Successful render — counts as healthy service signal for the breaker.
            $this->breaker->recordSuccess();

            $remoteUrl = $data['media_url'] ?? null;
            if (! $remoteUrl) {
                $job->update(['status' => 'failed', 'error_message' => 'No media_url in webhook']);
                $this->mirrorSlideStatus($job->linkedin_post_id, $uuid, 'failed', null, 'No media_url in webhook');
                return false;
            }

            $localUrl = $this->downloadAndStore($remoteUrl, $job->planned_filename);

            if (! $this->isLocalStorageUrl($localUrl)) {
                // downloadAndStore fell back to the remote URL (non-2xx, empty
                // body, or storage failure). Marking the slide 'done' with it
                // would leak a short-lived octet-stream GeminiGen URL into
                // carousel_slides[].image_url — which LinkedIn tolerates (it
                // fetches bytes) but Publer cannot ingest (the draft-149
                // cross-post failure). Mark failed so the reaper re-dispatches.
                $job->update([
                    'status' => 'failed',
                    'remote_url' => $remoteUrl,
                    'error_message' => 'Image download/store failed — remote URL not usable',
                ]);
                $this->mirrorSlideStatus($job->linkedin_post_id, $uuid, 'failed', null, 'Image download failed — will retry');
                $this->emitSlideProgress($job->linkedin_post_id, $job->slide_index, 'failed');
                Log::warning('[LinkedInCarouselImage] webhook download did not yield a local URL — slide held for retry', [
                    'draft_id' => $job->linkedin_post_id,
                    'slide_index' => $job->slide_index,
                    'remote_url' => $remoteUrl,
                ]);
                return false;
            }

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

            // Early parallel fan-out (June 9, 2026): the moment THIS completion
            // makes every slide 'done', kick off the cross-post fan-out
            // immediately instead of waiting up to 2 min for the next
            // social-cross-post:scan tick. Dispatches the targeted scan
            // (bypasses window + virality gates per the operator opt-in).
            $this->maybeDispatchCrossPostFanout($job->linkedin_post_id);

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

            // Bounded transient retry. The safety path above only acts on
            // POLICY_* refusals; a plain network blip / 5xx / timeout used to
            // stick in terminal 'failed' with no retry. This re-renders the
            // slide up to MAX_TRANSIENT_RETRIES times (no-op for policy classes,
            // which the safety path owns).
            $this->maybeTransientRetry($job, $reason);

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
     * Bounded auto-retry for TRANSIENT / DETERMINISTIC_LLM / UNKNOWN GeminiGen
     * failures (June 12, 2026). The safety path (maybeAutoRetryOnSafety) only
     * handles POLICY_* refusals; a plain network blip / 5xx / timeout used to
     * land the slide in terminal 'failed' with zero retry — stuck until the
     * operator manually hit "Re-render image".
     *
     * Resets the slide to 'pending' (so dispatchSingleSlide re-renders it) up to
     * MAX_TRANSIENT_RETRIES times, tracked per-slide via image_retry_count. On
     * exhaustion the slide is marked failed_permanent + a Telegram notify fires.
     * POLICY_* (safety path owns) and PERMANENT (won't succeed on retry) classes
     * are skipped.
     *
     * Idempotent against the safety path: for a POLICY_* class the early class
     * check returns before any mutation, so the slide isn't double-dispatched.
     */
    private function maybeTransientRetry(ImageGenerationJob $job, string $reason): void
    {
        $draftId = $job->linkedin_post_id;
        $slideIndex = $job->slide_index;
        if ($draftId === null || $slideIndex === null) {
            return;
        }

        $errorClass = app(\App\Services\ImageGenerationService::class)->classifyError($reason);

        $retryable = in_array($errorClass, [
            \App\Enums\PipelineErrorClass::Transient,
            \App\Enums\PipelineErrorClass::DeterministicLlm,
            \App\Enums\PipelineErrorClass::Unknown,
        ], true);
        if (! $retryable) {
            return;
        }

        $draft = LinkedInPost::find($draftId);
        if (! $draft) {
            return;
        }
        $slides = $draft->carousel_slides ?? [];
        if (! isset($slides[$slideIndex])) {
            return;
        }

        $count = (int) ($slides[$slideIndex]['image_retry_count'] ?? 0);

        // Exhausted — mark permanent so dispatchAllSlides + the reaper stop
        // re-rendering it, and alert the operator to skip + republish.
        if ($count >= self::MAX_TRANSIENT_RETRIES) {
            $this->markSlideFailedPermanent($draftId, $slideIndex, $reason);
            return;
        }

        // Reset to pending + bump the counter, then re-dispatch the single slide.
        DB::transaction(function () use ($draftId, $slideIndex, $count) {
            $locked = LinkedInPost::lockForUpdate()->find($draftId);
            if (! $locked) {
                return;
            }
            $s = $locked->carousel_slides ?? [];
            if (! isset($s[$slideIndex])) {
                return;
            }
            $s[$slideIndex]['image_status'] = 'pending';
            $s[$slideIndex]['image_error'] = null;
            $s[$slideIndex]['image_job_uuid'] = null;
            $s[$slideIndex]['image_retry_count'] = $count + 1;
            $locked->update(['carousel_slides' => $s]);
        });

        $fresh = LinkedInPost::find($draftId);
        if ($fresh) {
            $newUuid = $this->dispatchSingleSlide($fresh, $slideIndex);
            Log::info('[LinkedInCarouselImage] transient auto-retry dispatched', [
                'draft_id' => $draftId,
                'slide_index' => $slideIndex,
                'attempt' => $count + 1,
                'error_class' => $errorClass->value,
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
     * Early parallel fan-out trigger (Phase C, 2026-06-09). When THIS slide
     * completion makes every carousel slide 'done', enqueue the targeted
     * social-cross-post:scan so IG/TikTok/Threads/FB siblings generate right
     * away instead of waiting up to 2 min for the next reaper tick.
     *
     * Idempotent — skips when: format != carousel, any slide not yet 'done',
     * or a sibling row already exists (a later per-slide retry re-completing
     * won't re-fan-out). The scan command also does its own idempotency check;
     * this is belt-and-suspenders. Dispatch failure is non-fatal — the
     * every-2-min social-cross-post:scan reaper (Phase D widened gate) still
     * catches the draft.
     */
    private function maybeDispatchCrossPostFanout(?int $draftId): void
    {
        if ($draftId === null) {
            return;
        }

        $draft = LinkedInPost::find($draftId);
        if (! $draft || $draft->format !== 'carousel') {
            return;
        }

        $slides = is_array($draft->carousel_slides) ? $draft->carousel_slides : [];
        if ($slides === []) {
            return;
        }
        foreach ($slides as $slide) {
            if (($slide['image_status'] ?? null) !== 'done') {
                return; // not all rendered yet
            }
        }

        // Already fanned out? skip (avoids re-dispatch when a per-slide retry
        // re-completes after siblings were created).
        if ($draft->instagramPost()->exists()
            || $draft->tiktokPost()->exists()
            || $draft->threadsPost()->exists()
            || $draft->facebookPost()->exists()) {
            return;
        }

        try {
            $this->dispatchCrossPostScan($draftId);
            Log::info('[LinkedInCarouselImage] all slides done — dispatched targeted cross-post fan-out', [
                'draft_id' => $draftId,
                'slide_count' => count($slides),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[LinkedInCarouselImage] cross-post fan-out dispatch failed (reaper will retry)', [
                'draft_id' => $draftId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Enqueue the targeted cross-post scan. Extracted as a protected seam so
     * tests can assert the fan-out decision without depending on Laravel's
     * queued-command internals.
     */
    protected function dispatchCrossPostScan(int $draftId): void
    {
        Artisan::queue('social-cross-post:scan', ['--draft-id' => $draftId]);
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
    /**
     * True when the URL is an app-hosted /storage/ asset (i.e. downloadAndStore
     * succeeded and mirrored the PNG locally), as opposed to a remote URL it
     * fell back to on failure. Used to gate slide completion: a slide must never
     * reach 'done' carrying a remote URL (octet-stream / expiring) that Publer
     * cannot ingest.
     */
    private function isLocalStorageUrl(?string $url): bool
    {
        if (! is_string($url) || $url === '') {
            return false;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        $urlHost = parse_url($url, PHP_URL_HOST);

        if (is_string($appHost) && $appHost !== '' && is_string($urlHost) && $urlHost !== '') {
            return strcasecmp($appHost, $urlHost) === 0 && str_contains($url, '/storage/');
        }

        // Host-less relative path is local by definition.
        return $urlHost === null && str_contains($url, '/storage/');
    }

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
