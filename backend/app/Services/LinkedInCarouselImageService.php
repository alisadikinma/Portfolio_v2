<?php

namespace App\Services;

use App\Models\ImageGenerationJob;
use App\Models\LinkedInPost;
use App\Models\Setting;
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
        // 1:1, 16:9, 9:16, 4:3, 3:4. We use 1:1 (1080x1080 square) for
        // LinkedIn carousels — universally supported, document-share native,
        // mobile-safe, gives bilingual headline + visual hook enough room
        // without the ratio gamble.
        $multipart = [
            ['name' => 'prompt', 'contents' => $finalPrompt],
            ['name' => 'model', 'contents' => $this->resolveModel()],
            ['name' => 'aspect_ratio', 'contents' => '1:1'],
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

            Log::error('[LinkedInCarouselImage] webhook failed', [
                'draft_id' => $job->linkedin_post_id,
                'slide_index' => $job->slide_index,
                'reason' => $reason,
            ]);
            return false;
        }

        return false;
    }

    /**
     * Update the matching slide entry in the LinkedInPost.carousel_slides JSON
     * column. Runs inside DB::transaction with row lock to keep concurrent
     * webhooks (multiple slides finishing at once) from clobbering each other.
     */
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
