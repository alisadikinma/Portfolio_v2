<?php

namespace App\Services;

use App\Enums\ContentIdeaStatus;
use App\Models\ContentIdea;
use App\Models\ImageGenerationJob;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageGenerationService
{
    public const MAX_SEGMENT_ATTEMPTS = 3;

    private string $apiKey;
    private string $baseUrl = 'https://api.geminigen.ai/uapi/v1';

    public function __construct()
    {
        $this->apiKey = config('services.geminigen.api_key', '');
    }

    /**
     * Queue an image generation request (fire-and-forget).
     * GeminiGen will call our webhook when the image is ready.
     * Returns the job UUID for tracking.
     */
    public function queue(
        ?int $postId,
        string $prompt,
        string $type = 'hero',
        string $insertAfterHeading = null,
        ?string $model = null,
        string $aspectRatio = '16:9',
        string $style = 'Photorealistic',
        array $faceRefs = [],
        array $styleRefs = [],
        string $additionalNotes = '',
        ?string $plannedFilename = null
    ): ?string {
        $model = $model ?? config('content.default_image_model') ?? 'nano-banana-pro';

        if (empty($this->apiKey)) {
            Log::error('[ImageGen] GEMINIGEN_API_KEY not configured.');
            return null;
        }

        try {
            // Build enhanced prompt with additional notes and reference instructions
            $finalPrompt = $prompt;

            if ($additionalNotes) {
                $finalPrompt .= '. ' . trim($additionalNotes);
            }

            if (!empty($faceRefs)) {
                $finalPrompt .= '. Maintain exact facial identity, appearance, and features from the provided face reference image(s).';
            }

            if (!empty($styleRefs)) {
                $finalPrompt .= '. Maintain visual consistency with the provided reference image(s) for environment, style, and composition.';
            }

            $multipart = [
                ['name' => 'prompt', 'contents' => $finalPrompt],
                ['name' => 'model', 'contents' => $model],
                ['name' => 'aspect_ratio', 'contents' => $aspectRatio],
                ['name' => 'style', 'contents' => $style],
            ];

            // Webhook URL so GeminiGen can notify us on completion/failure.
            // Harmless if GeminiGen ignores the param (poll fallback still works).
            $apiUrl = config('services.article_generation.api_url', config('app.url'));
            $webhookUrl = rtrim($apiUrl, '/') . '/automation/blog/image-webhook';
            $multipart[] = ['name' => 'webhook', 'contents' => $webhookUrl];
            $multipart[] = ['name' => 'webhook_url', 'contents' => $webhookUrl];
            $multipart[] = ['name' => 'callback_url', 'contents' => $webhookUrl];

            // Merge all reference URLs and send as file_urls to GeminiGen.
            // GeminiGen expects EACH url as its own multipart entry (or single plain string),
            // NOT a JSON-encoded array. Sending json_encode(array) makes GeminiGen try to
            // fetch the literal '["https://..."]' string and fail with FILE_DOWNLOAD_FAILED.
            $allRefs = array_values(array_filter(
                array_merge($faceRefs, $styleRefs),
                fn ($u) => is_string($u) && $u !== '' && !str_starts_with($u, 'blob:')
            ));
            foreach ($allRefs as $refUrl) {
                $multipart[] = ['name' => 'file_urls', 'contents' => $refUrl];
            }

            $response = Http::timeout(30)
                ->withHeaders(['x-api-key' => $this->apiKey])
                ->asMultipart()
                ->post("{$this->baseUrl}/generate_image", $multipart);

            if (!$response->successful()) {
                Log::error("[ImageGen] API error: HTTP {$response->status()} — {$response->body()}");
                return null;
            }

            $data = $response->json();
            $uuid = $data['uuid'] ?? null;
            $status = $data['status'] ?? 0;

            if (!$uuid) {
                Log::error('[ImageGen] No UUID in response: ' . json_encode($data));
                return null;
            }

            // If image is ready immediately (status=2), handle it now
            if ($status === 2 && isset($data['generate_result'])) {
                $localUrl = $this->downloadAndStore($data['generate_result'], $plannedFilename);
                ImageGenerationJob::create([
                    'post_id' => $postId,
                    'uuid' => $uuid,
                    'type' => $type,
                    'prompt' => $prompt,
                    'insert_after_heading' => $insertAfterHeading,
                    'planned_filename' => $plannedFilename,
                    'status' => 'completed',
                    'image_url' => $localUrl,
                    'remote_url' => $data['generate_result'],
                ]);

                // If hero, update post immediately
                if ($type === 'hero' && $localUrl) {
                    \App\Models\Post::where('id', $postId)->update(['featured_image' => $localUrl]);
                }

                Log::info("[ImageGen] Instant: {$type} for post {$postId} — {$localUrl}");
                return $uuid;
            }

            // Otherwise, save as pending — webhook will complete it
            ImageGenerationJob::create([
                'post_id' => $postId,
                'uuid' => $uuid,
                'type' => $type,
                'prompt' => $prompt,
                'insert_after_heading' => $insertAfterHeading,
                'planned_filename' => $plannedFilename,
                'status' => 'processing',
            ]);

            Log::info("[ImageGen] Queued: {$type} for post {$postId}, UUID={$uuid}");
            return $uuid;

        } catch (\Exception $e) {
            Log::error("[ImageGen] Exception: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Dispatch GeminiGen jobs for every image_prompt on the given idea.
     * Used by AutoPipelineOrchestrator to advance article_ready → generating_images.
     *
     * For each prompt, applies CoverBrandingEnhancer (title overlay + creator
     * face inject on type='cover'), then calls queue(). Attaches returned
     * UUIDs back onto the variations[] slot + flat job_uuid for backward compat.
     *
     * Skips prompts that already have a 'done' variation (idempotency for
     * retry scenarios). Flips idea.status to 'generating_images' when at
     * least one job dispatched successfully.
     *
     * Returns count of jobs dispatched.
     */
    public function triggerForIdea(ContentIdea $idea): int
    {
        $article = $idea->generated_article ?? [];
        $prompts = $article['image_prompts'] ?? [];
        if (empty($prompts)) {
            Log::info("[ImageGen] triggerForIdea: idea #{$idea->id} has no image_prompts — skipping");
            return 0;
        }

        $enhancer = app(CoverBrandingEnhancer::class);
        $dispatched = 0;

        foreach ($prompts as $i => $prompt) {
            // Idempotency: skip if already done
            $hasDoneVariation = collect($prompt['variations'] ?? [])
                ->contains(fn ($v) => ($v['status'] ?? '') === 'done' && !empty($v['url']));
            if ($hasDoneVariation) {
                continue;
            }

            // Apply cover branding enhancement (no-op for inline prompts)
            $forEnhance = array_merge($prompt, [
                'prompt_text' => $prompt['prompt_text'] ?? ($prompt['prompt'] ?? ''),
                'face_refs' => $prompt['face_refs'] ?? [],
                'model' => $prompt['model'] ?? null,
            ]);
            $enhanced = $enhancer->enhance($forEnhance, $idea);

            $promptText = $enhanced['prompt_text'] ?? ($prompt['prompt_text'] ?? $prompt['prompt'] ?? '');
            $model = $enhanced['model'] ?? null;
            $faceRefs = $enhanced['face_refs'] ?? [];

            // Normalize brand_refs → flat URL array
            $brandRefUrls = [];
            foreach ($prompt['brand_refs'] ?? [] as $ref) {
                if (is_array($ref) && !empty($ref['url'])) $brandRefUrls[] = $ref['url'];
                elseif (is_string($ref) && $ref !== '') $brandRefUrls[] = $ref;
            }

            // Watermark logo URL the enhancer pushed into file_urls. Merge into
            // styleRefs (not faceRefs) so queue() doesn't append a "maintain
            // facial identity" instruction that would misapply to a brand logo.
            // The more specific watermark instruction already in $promptText
            // dominates. Without this merge the logo URL is dropped and the
            // model fabricates a letterform watermark from scratch.
            $enhancerFileUrls = array_values(array_filter(
                $enhanced['file_urls'] ?? [],
                fn ($u) => is_string($u) && $u !== ''
            ));

            $uuid = $this->queue(
                postId: null,
                prompt: $promptText,
                type: $i === 0 ? 'hero' : 'inline',
                insertAfterHeading: $prompt['insert_after_heading'] ?? null,
                model: $model,
                aspectRatio: $prompt['aspect_ratio'] ?? '16:9',
                style: $prompt['style'] ?? 'Photorealistic',
                faceRefs: array_merge($faceRefs, $brandRefUrls),
                styleRefs: array_merge($prompt['style_refs'] ?? [], $enhancerFileUrls),
                additionalNotes: $prompt['additional_notes'] ?? '',
                plannedFilename: $this->buildBrandedFilename($idea, $i)
            );

            if (!$uuid) {
                Log::warning("[ImageGen] triggerForIdea: queue failed for idea #{$idea->id} segment {$i}");
                continue;
            }

            // Append a generating variation slot with the returned UUID
            $variations = $prompts[$i]['variations'] ?? [];
            $variations[] = ['url' => null, 'job_uuid' => $uuid, 'status' => 'generating'];
            $prompts[$i]['variations'] = $variations;
            $prompts[$i]['job_uuid'] = $uuid;
            $prompts[$i]['status'] = 'generating';
            $dispatched++;
        }

        if ($dispatched > 0) {
            $article['image_prompts'] = $prompts;
            $idea->generated_article = $article;
            if ($idea->status === 'article_ready') {
                // Use transitionTo so the state change + article_prompts mutation
                // persist atomically and the FSM log records the reason.
                $idea->save(); // flush generated_article first
                $idea->transitionTo(ContentIdeaStatus::GeneratingImages, 'trigger_for_idea');
            } else {
                $idea->save();
            }
            Log::info("[ImageGen] triggerForIdea: dispatched {$dispatched} jobs for idea #{$idea->id}");
        }

        return $dispatched;
    }

    /**
     * Handle GeminiGen webhook callback.
     * Called when image generation completes or fails.
     *
     * Routing: GeminiGen ignores per-request webhook URLs and posts every
     * callback to the globally-configured endpoint (/automation/blog/image-webhook).
     * To keep all GeminiGen webhooks landing in one place, this method routes
     * carousel_slide jobs over to LinkedInCarouselImageService::handleWebhook —
     * which knows how to download to linkedin-carousel/{planned_filename}.png
     * and mirror status onto LinkedInPost.carousel_slides JSON. Article hero/
     * inline jobs continue through the existing flow.
     */
    public function handleWebhook(string $uuid, string $event, array $data): bool
    {
        $job = ImageGenerationJob::where('uuid', $uuid)->first();

        if (!$job) {
            Log::warning("[ImageGen] Webhook: unknown UUID {$uuid}");
            return false;
        }

        // LinkedIn carousel jobs route to their dedicated handler — keeps article
        // pipeline isolated from carousel-specific mirror logic + storage path.
        if ($job->type === 'carousel_slide') {
            return app(\App\Services\LinkedInCarouselImageService::class)
                ->handleWebhook($uuid, $event, $data);
        }

        if ($event === 'IMAGE_GENERATION_COMPLETED') {
            $remoteUrl = $data['media_url'] ?? null;
            if (!$remoteUrl) {
                Log::error("[ImageGen] Webhook: no media_url for UUID {$uuid}");
                $job->update(['status' => 'failed', 'error_message' => 'No media_url in webhook']);
                return false;
            }

            // Download and store locally (uses branded filename when planned at dispatch time)
            $localUrl = $this->downloadAndStore($remoteUrl, $job->planned_filename);

            $job->update([
                'status' => 'completed',
                'image_url' => $localUrl,
                'remote_url' => $remoteUrl,
            ]);

            // Apply to post (legacy blog pipeline — only when job.post_id is set)
            if ($job->post_id && $job->type === 'hero' && $localUrl) {
                $job->post->update(['featured_image' => $localUrl]);
                Log::info("[ImageGen] Webhook: hero image set for post {$job->post_id}");
            } elseif ($job->post_id && $job->type === 'inline' && $localUrl) {
                $this->insertInlineImage($job);
                Log::info("[ImageGen] Webhook: inline image inserted for post {$job->post_id}");
            }

            // Content Engine: update matching image_prompts[] segment in content_ideas
            $this->updateContentIdeaSegment($uuid, 'done', $localUrl);

            return true;

        } elseif ($event === 'IMAGE_GENERATION_FAILED') {
            $reason = $data['error_message'] ?? 'Unknown error';
            $job->update([
                'status' => 'failed',
                'error_message' => $reason,
            ]);
            Log::error("[ImageGen] Webhook: failed for UUID {$uuid} — {$reason}");

            // Mark the variation + segment status 'failed'.
            $this->updateContentIdeaSegment($uuid, 'failed', null, $reason);
            // Drive the segment retry state machine: bump retry_count,
            // append failure_history, auto-schedule retry or mark terminal.
            // Previously only the poller path (ProcessPendingImages) called
            // this — webhooks bypassed it, so an idea whose GeminiGen failed
            // via webhook got stuck in 'generating' status (variation was
            // flipped to 'failed' but without the state-machine semantics
            // the UI kept showing "Generating…" until the user refreshed).
            $this->handleSegmentFailure($job, $reason);

            return false;
        }

        return false;
    }

    /**
     * Find the content_idea whose image_prompts[] contains the given job UUID
     * and update that segment's status + generated_url.
     * No-op if no matching idea (legacy blog pipeline).
     */
    private function updateContentIdeaSegment(string $uuid, string $status, ?string $imageUrl = null, ?string $errorMessage = null): void
    {
        // Step 1: find which idea owns this uuid (lightweight scan, no lock).
        // Matches if any image_prompts[].job_uuid OR any variations[].job_uuid equals $uuid.
        $ideaId = $this->findIdeaIdForJobUuid($uuid);
        if ($ideaId === null) {
            Log::info("[ImageGen] Webhook: no matching content_idea segment for UUID {$uuid} (ok if legacy blog post)");
            return;
        }

        // Step 2: re-read the idea under a row-level lock inside a transaction
        // so concurrent GeminiGen webhooks can't stomp each other's variation
        // updates. Race scenario before this fix: two webhooks for segments
        // 0 and 1 arrive in parallel, both read the same stale snapshot of
        // generated_article, each mark their own variation done, then the
        // second update() wipes the first's write.
        DB::transaction(function () use ($ideaId, $uuid, $status, $imageUrl, $errorMessage) {
            $idea = ContentIdea::lockForUpdate()->find($ideaId);
            if (!$idea) {
                return;
            }

            $article = $idea->generated_article ?? [];
            $prompts = $article['image_prompts'] ?? [];
            $matched = false;

            foreach ($prompts as $i => $p) {
                $variations = $p['variations'] ?? [];
                foreach ($variations as $vi => $v) {
                    if (($v['job_uuid'] ?? null) === $uuid) {
                        $prompts[$i]['variations'][$vi]['status'] = $status;
                        if ($imageUrl !== null) {
                            $prompts[$i]['variations'][$vi]['url'] = $imageUrl;
                        }
                        if ($errorMessage !== null) {
                            $prompts[$i]['variations'][$vi]['error'] = $errorMessage;
                        }

                        $prompts[$i]['job_uuid'] = $uuid;
                        if ($imageUrl !== null) {
                            $prompts[$i]['generated_url'] = $imageUrl;
                        }

                        $anyGenerating = collect($prompts[$i]['variations'])->contains(fn ($v) => ($v['status'] ?? '') === 'generating');
                        $prompts[$i]['status'] = $anyGenerating ? 'generating' : $status;

                        $matched = true;
                        break 2;
                    }
                }

                if (($p['job_uuid'] ?? null) === $uuid) {
                    $prompts[$i]['status'] = $status;
                    if ($imageUrl !== null) {
                        $prompts[$i]['generated_url'] = $imageUrl;
                    }
                    if ($errorMessage !== null) {
                        $prompts[$i]['error'] = $errorMessage;
                    }
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                Log::warning("[ImageGen] Webhook: uuid {$uuid} disappeared from idea #{$idea->id} between scan and lock");
                return;
            }

            $article['image_prompts'] = $prompts;
            $idea->update(['generated_article' => $article]);

            $allDone = collect($prompts)->every(fn ($p) => in_array($p['status'] ?? '', ['done', 'failed']));
            $anyDone = collect($prompts)->contains(fn ($p) => ($p['status'] ?? '') === 'done');
            if ($allDone && $anyDone && $idea->status === 'generating_images') {
                $idea->transitionTo(ContentIdeaStatus::ImagesReady, 'webhook_all_done');
                Log::info("[ImageGen] Content idea {$idea->id} → images_ready (all segments done)");
            }

            Log::info("[ImageGen] Webhook: updated segment for idea {$idea->id} uuid={$uuid} status={$status}");
        });
    }

    /**
     * Locate the ContentIdea whose generated_article.image_prompts[] contains
     * the given job UUID. Returns null when no match (legacy blog pipeline).
     *
     * Split out so both updateContentIdeaSegment and handleSegmentFailure can
     * re-fetch the idea under a row-level lock inside DB::transaction without
     * each doing their own scan.
     */
    private function findIdeaIdForJobUuid(string $uuid): ?int
    {
        // Includes 'completed' so late webhook deliveries from operator-triggered
        // retries (after the first variation already auto-advanced the idea)
        // can still mirror status/url back into image_prompts[].variations[].
        // Without this, retried variations stay 'generating' in the admin UI
        // forever even though the underlying ImageGenerationJob is done/failed.
        $ideas = ContentIdea::whereIn('status', ['article_ready', 'generating_images', 'images_ready', 'completed'])
            ->whereNotNull('generated_article')
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get(['id', 'generated_article']);

        foreach ($ideas as $idea) {
            $prompts = $idea->generated_article['image_prompts'] ?? [];
            foreach ($prompts as $p) {
                foreach ($p['variations'] ?? [] as $v) {
                    if (($v['job_uuid'] ?? null) === $uuid) {
                        return (int) $idea->id;
                    }
                }
                if (($p['job_uuid'] ?? null) === $uuid) {
                    return (int) $idea->id;
                }
            }
        }

        return null;
    }

    /**
     * Insert an inline image into the post's content after the specified heading.
     * Figcaption sources in priority order:
     *   1. image_prompts[].caption from the matching content_idea segment (plugin-authored)
     *   2. image_prompts[].concept (creative brief fallback)
     *   3. $job->insert_after_heading (legacy fallback)
     */
    private function insertInlineImage(ImageGenerationJob $job): void
    {
        if (!$job->image_url || !$job->insert_after_heading) return;

        $captionText = $this->resolveFigcaption($job);

        $imgTag = '<figure class="my-8"><img src="' . e($job->image_url) . '" alt="' . e(\Illuminate\Support\Str::limit($job->prompt, 100)) . '" class="w-full rounded-xl" loading="lazy" /><figcaption class="text-sm text-gray-500 mt-2 text-center">' . e(\Illuminate\Support\Str::limit($captionText, 160)) . '</figcaption></figure>';

        // Insert into all translations for this post
        foreach ($job->post->translations as $translation) {
            $content = $translation->content;
            $headingPos = stripos($content, $job->insert_after_heading);
            if ($headingPos !== false) {
                $closeTagPos = strpos($content, '</h2>', $headingPos);
                if ($closeTagPos !== false) {
                    $insertPos = $closeTagPos + 5;
                    $translation->content = substr($content, 0, $insertPos) . "\n" . $imgTag . "\n" . substr($content, $insertPos);
                    $translation->save();
                }
            }
        }
    }

    /**
     * Resolve figcaption text for a completed job by looking up the matching
     * content_idea segment. Falls back to concept, then insert_after_heading.
     */
    private function resolveFigcaption(ImageGenerationJob $job): string
    {
        $ideas = ContentIdea::whereIn('status', ['article_ready', 'generating_images', 'images_ready', 'completed'])
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        foreach ($ideas as $idea) {
            $prompts = $idea->generated_article['image_prompts'] ?? [];
            foreach ($prompts as $p) {
                $variations = $p['variations'] ?? [];
                foreach ($variations as $v) {
                    if (($v['job_uuid'] ?? null) === $job->uuid) {
                        return $p['caption'] ?? $p['concept'] ?? $job->insert_after_heading;
                    }
                }
                if (($p['job_uuid'] ?? null) === $job->uuid) {
                    return $p['caption'] ?? $p['concept'] ?? $job->insert_after_heading;
                }
            }
        }

        return $job->insert_after_heading;
    }

    /**
     * Build a branded filename for an idea's segment image.
     * Pattern: {creator_brand_slug}-{seo-keyword-slug}-{segment-label}.png
     * Collision: appends -v2, -v3, ... if an ImageGenerationJob already
     * has the candidate filename (user regenerated the same segment).
     */
    public function buildBrandedFilename(ContentIdea $idea, int $segmentIndex): string
    {
        $brandSlug = Setting::where('group', 'creator_brand')
            ->where('key', 'creator_brand_slug')
            ->value('value') ?: 'alisadikinma';

        $article = $idea->generated_article ?? [];
        $keywordSource = $article['prep_data']['research']['keyword']
            ?? $idea->title
            ?? 'image';

        $keywordSlug = Str::slug($keywordSource) ?: 'image';
        $label = $segmentIndex === 0 ? 'cover' : "body-{$segmentIndex}";
        $base = "{$brandSlug}-{$keywordSlug}-{$label}";

        $candidate = "{$base}.png";
        $suffix = 2;
        while (ImageGenerationJob::where('planned_filename', $candidate)->exists()) {
            $candidate = "{$base}-v{$suffix}.png";
            $suffix++;
        }

        return $candidate;
    }

    /**
     * Download image from URL and store in public storage.
     * If $customFilename provided, stores as blog-images/{$customFilename}
     * instead of the legacy timestamp pattern. Extension is NOT changed —
     * caller is responsible for the full filename (including .png/.jpg).
     */
    public function downloadAndStore(string $imageUrl, ?string $customFilename = null): ?string
    {
        try {
            $response = Http::timeout(30)->get($imageUrl);

            if (!$response->successful()) {
                Log::error("[ImageGen] Download HTTP {$response->status()} from {$imageUrl}");
                return $imageUrl;
            }

            $imageData = $response->body();
            if (empty($imageData) || strlen($imageData) < 1024) {
                Log::error("[ImageGen] Download empty/tiny body ({" . strlen($imageData) . "} bytes) from {$imageUrl}");
                return $imageUrl;
            }

            $filename = $customFilename
                ? 'blog-images/' . $customFilename
                : 'blog-images/' . time() . '_' . uniqid() . '.jpg';
            $written = Storage::disk('public')->put($filename, $imageData);

            if (!$written) {
                Log::error("[ImageGen] Storage::put() returned false for {$filename} — check perms on storage/app/public/blog-images");
                return $imageUrl;
            }

            // Double-check file actually landed on disk
            if (!Storage::disk('public')->exists($filename)) {
                Log::error("[ImageGen] File missing after put() — {$filename}");
                return $imageUrl;
            }

            Log::info("[ImageGen] Stored {$filename} (" . strlen($imageData) . " bytes)");
            return url('/storage/' . $filename);
        } catch (\Exception $e) {
            Log::error("[ImageGen] Download exception: {$e->getMessage()}");
            return $imageUrl;
        }
    }

    /**
     * Retry a single failed image segment without re-running plugin NER.
     * Resets variations[] to a fresh generating slot and re-queues to
     * GeminiGen using the existing prompt + refs.
     *
     * Does NOT increment retry_count — that counter is owned by
     * handleSegmentFailure and incremented only on actual failure
     * callbacks. If this method also bumped the counter, attempts 1
     * through 2 would effectively consume the 3-attempt budget (double-
     * counting on each cycle: failure increment + dispatch increment).
     *
     * Returns the new job UUID, or null if:
     *   - segment index out of range
     *   - retry_count already >= MAX_SEGMENT_ATTEMPTS
     *   - GeminiGen queue() fails
     */
    public function retrySegment(ContentIdea $idea, int $i): ?string
    {
        $article = $idea->generated_article ?? [];
        $prompts = $article['image_prompts'] ?? [];

        if (!isset($prompts[$i])) {
            Log::warning("[ImageGen] retrySegment: index {$i} out of range for idea #{$idea->id}");
            return null;
        }

        $segment = $prompts[$i];
        $currentCount = (int) ($segment['retry_count'] ?? 0);

        if ($currentCount >= self::MAX_SEGMENT_ATTEMPTS) {
            Log::warning("[ImageGen] retrySegment: idea #{$idea->id} segment {$i} at cap ({$currentCount})");
            return null;
        }

        // Reset variations (clear stale UUIDs) so the new slot is the only one.
        // retry_count stays the same — handleSegmentFailure already bumped it
        // on the failure that triggered this retry. terminal_at cleared so a
        // previously-exhausted segment can be manually retried after the cap
        // is relaxed via admin action (e.g., ContentIdeaController::retrySegment).
        $segment['variations'] = [];
        $segment['status'] = 'generating';
        $segment['terminal_at'] = null;

        // Prepare enhancer input using already-cached refs (no plugin NER re-run).
        $enhancer = app(CoverBrandingEnhancer::class);
        $forEnhance = array_merge($segment, [
            'prompt_text' => $segment['prompt_text'] ?? ($segment['prompt'] ?? ''),
            'face_refs' => $segment['face_refs'] ?? [],
            'model' => $segment['model'] ?? null,
        ]);
        $enhanced = $enhancer->enhance($forEnhance, $idea);

        $promptText = $enhanced['prompt_text'] ?? ($segment['prompt_text'] ?? $segment['prompt'] ?? '');
        $model = $enhanced['model'] ?? null;
        $faceRefs = $enhanced['face_refs'] ?? [];

        $brandRefUrls = [];
        foreach ($segment['brand_refs'] ?? [] as $ref) {
            if (is_array($ref) && !empty($ref['url'])) $brandRefUrls[] = $ref['url'];
            elseif (is_string($ref) && $ref !== '') $brandRefUrls[] = $ref;
        }

        $enhancerFileUrls = array_values(array_filter(
            $enhanced['file_urls'] ?? [],
            fn ($u) => is_string($u) && $u !== ''
        ));

        $uuid = $this->queue(
            postId: null,
            prompt: $promptText,
            type: $i === 0 ? 'hero' : 'inline',
            insertAfterHeading: $segment['insert_after_heading'] ?? null,
            model: $model,
            aspectRatio: $segment['aspect_ratio'] ?? '16:9',
            style: $segment['style'] ?? 'Photorealistic',
            faceRefs: array_merge($faceRefs, $brandRefUrls),
            styleRefs: array_merge($segment['style_refs'] ?? [], $enhancerFileUrls),
            additionalNotes: $segment['additional_notes'] ?? '',
            plannedFilename: $this->buildBrandedFilename($idea, $i)
        );

        if (!$uuid) {
            Log::warning("[ImageGen] retrySegment: queue failed for idea #{$idea->id} segment {$i}");
            return null;
        }

        $segment['variations'][] = ['url' => null, 'job_uuid' => $uuid, 'status' => 'generating'];
        $segment['job_uuid'] = $uuid;

        $prompts[$i] = $segment;
        $article['image_prompts'] = $prompts;
        $idea->generated_article = $article;
        $idea->save();

        Log::info("[ImageGen] retrySegment: idea #{$idea->id} segment {$i} attempt {$segment['retry_count']} uuid={$uuid}");
        return $uuid;
    }

    /**
     * Update segment state on a GeminiGen failure and drive the retry
     * state machine: bump retry_count, append failure_history, decide
     * whether to auto-dispatch a RetryImageSegmentJob or mark terminal.
     *
     * Runs inside DB::transaction + lockForUpdate so concurrent failure
     * webhooks can't stomp each other's retry_count increments.
     *
     * Called from the webhook/polling failure path. Idempotent when the
     * job UUID matches a segment in an idea currently in an image-gen
     * status. If no matching idea found, silently returns (legacy posts).
     *
     * NOTE: retry_count is incremented HERE on failure. It is NOT
     * incremented in retrySegment() — that method only dispatches the
     * next attempt. This avoids double-counting that would effectively
     * halve the retry budget. Flow: attempt 1 fails → count=1, dispatch
     * retry → attempt 2 fails → count=2, dispatch retry → attempt 3
     * fails → count=3 (terminal).
     */
    public function handleSegmentFailure(\App\Models\ImageGenerationJob $job, ?string $reason = null): void
    {
        $ideaId = $this->findIdeaIdForJobUuid($job->uuid);
        if ($ideaId === null) {
            return;
        }

        DB::transaction(function () use ($ideaId, $job, $reason) {
            $idea = ContentIdea::lockForUpdate()->find($ideaId);
            if (!$idea) {
                return;
            }

            $article = $idea->generated_article ?? [];
            $prompts = $article['image_prompts'] ?? [];
            $matchIndex = null;

            foreach ($prompts as $i => $p) {
                $variations = $p['variations'] ?? [];
                foreach ($variations as $v) {
                    if (($v['job_uuid'] ?? null) === $job->uuid) {
                        $matchIndex = $i;
                        break 2;
                    }
                }
                if (($p['job_uuid'] ?? null) === $job->uuid) {
                    $matchIndex = $i;
                    break;
                }
            }

            if ($matchIndex === null) {
                Log::warning("[ImageGen] handleSegmentFailure: uuid {$job->uuid} disappeared from idea #{$idea->id} between scan and lock");
                return;
            }

            $segment = $prompts[$matchIndex];
            $priorCount = (int) ($segment['retry_count'] ?? 0);
            $newCount = $priorCount + 1;

            $effectiveReason = $reason ?? $job->error_message ?? 'unknown';
            $isSafety = $this->isSafetyError($effectiveReason);
            $rewriteApplied = false;

            // Safety-aware prompt rewrite. GeminiGen refuses prompts naming
            // public figures, minors, or unsafe content — retrying the same
            // prompt is deterministically futile. Mutate the segment so the
            // next attempt (auto or manual) uses a sanitized version. We
            // also drop face_refs because a public-figure entity_ref is the
            // most common trigger and the new generic VD won't match it.
            //
            // Runs even past MAX_SEGMENT_ATTEMPTS so segments stuck at the
            // cap can still be unstuck by a manual Retry click — the rewrite
            // is idempotent (preserves visual_direction_pre_safety once) and
            // cheap (~10s sync Sonnet call). Skipped if already rewritten on
            // a prior failure to avoid re-running on every webhook.
            $alreadyRewritten = !empty($segment['visual_direction_pre_safety']);
            if (
                $isSafety
                && !$alreadyRewritten
                && config('services.article_generation.use_safety_rewrite', true)
            ) {
                $rewriteResult = $this->rewriteSegmentForSafety($segment, $effectiveReason, $idea->id, $matchIndex);
                if ($rewriteResult !== null) {
                    $segment = $rewriteResult;
                    $rewriteApplied = true;
                }
            }

            $segment['retry_count'] = $newCount;
            $segment['failure_history'] = $segment['failure_history'] ?? [];
            $segment['failure_history'][] = [
                'attempt' => $priorCount,
                'uuid' => $job->uuid,
                'reason' => $effectiveReason,
                'timestamp' => now()->toIso8601String(),
                'safety_detected' => $isSafety,
                'rewritten_for_safety' => $rewriteApplied,
            ];

            foreach ($segment['variations'] ?? [] as $vi => $v) {
                if (($v['job_uuid'] ?? null) === $job->uuid) {
                    $segment['variations'][$vi]['status'] = 'failed';
                    $segment['variations'][$vi]['error'] = $effectiveReason;
                }
            }
            $segment['status'] = 'failed';

            if ($newCount >= self::MAX_SEGMENT_ATTEMPTS) {
                $segment['terminal_at'] = now()->toIso8601String();

                \App\Jobs\DispatchTelegramNotification::dispatch($idea->id, 'segment_retry_exhausted');

                if ($matchIndex === 0) {
                    // Runtime guard: cover-critical dispatch assumes index 0
                    // is the cover. Plugin contract says image_prompts[0] is
                    // always the cover, but log a warning if the type field
                    // disagrees so we catch schema drift early.
                    if (($segment['type'] ?? null) !== 'cover') {
                        Log::warning("[ImageGen] cover_critical dispatched for segment 0 but type={$segment['type']} on idea #{$idea->id}");
                    }
                    \App\Jobs\DispatchTelegramNotification::dispatch($idea->id, 'cover_critical');
                }

                Log::warning("[ImageGen] Segment {$matchIndex} on idea #{$idea->id} reached terminal failure after {$newCount} attempts");
            } elseif ($idea->auto_mode) {
                \App\Jobs\RetryImageSegmentJob::dispatch($idea->id, $matchIndex)
                    ->delay(now()->addSeconds(60));

                Log::info("[ImageGen] Scheduled auto-retry for idea #{$idea->id} segment {$matchIndex} (attempt {$newCount}/" . self::MAX_SEGMENT_ATTEMPTS . ')' . ($rewriteApplied ? ' [safety-rewritten]' : ''));
            }

            $prompts[$matchIndex] = $segment;
            $article['image_prompts'] = $prompts;
            $idea->generated_article = $article;
            $idea->save();
        });
    }

    /**
     * Detect GeminiGen safety-policy refusals from the error string.
     *
     * Matches against known error codes (PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD,
     * etc.) plus free-text refusal language ("prominent people", "minors",
     * "unsafe content", "sexual content"). Case-insensitive substring match
     * — defensive against minor wording drift in the API's error messages.
     */
    public function isSafetyError(?string $reason): bool
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
            'public_error_sexual',
            'prominent people',
            'prominent person',
            'minors',
            'unsafe content',
            'sexual content',
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

    /**
     * Public on-demand safety rewrite for a single segment. Persists the
     * result back to the idea. Used by the manual retry endpoint when an
     * operator clicks Retry on a segment stuck at the cap with a safety
     * refusal as its last failure.
     *
     * Returns true if the segment was rewritten and saved, false otherwise.
     */
    public function applySafetyRewrite(ContentIdea $idea, int $segmentIndex, string $safetyReason): bool
    {
        return DB::transaction(function () use ($idea, $segmentIndex, $safetyReason) {
            $locked = ContentIdea::lockForUpdate()->find($idea->id);
            if (!$locked) return false;

            $article = $locked->generated_article ?? [];
            $prompts = $article['image_prompts'] ?? [];
            if (!isset($prompts[$segmentIndex])) return false;

            $rewritten = $this->rewriteSegmentForSafety(
                $prompts[$segmentIndex],
                $safetyReason,
                $locked->id,
                $segmentIndex
            );
            if ($rewritten === null) return false;

            $prompts[$segmentIndex] = $rewritten;
            $article['image_prompts'] = $prompts;
            $locked->generated_article = $article;
            $locked->save();
            return true;
        });
    }

    /**
     * Reset retry_count + terminal_at for a single segment so a sanitized
     * prompt can run with a fresh 3-attempt budget. Preserves failure_history
     * for audit. Manual escape hatch — only called from the controller's
     * safety-rewrite-and-restart path; never from the auto-retry pipeline.
     */
    public function resetSegmentRetryBudget(ContentIdea $idea, int $segmentIndex): bool
    {
        return DB::transaction(function () use ($idea, $segmentIndex) {
            $locked = ContentIdea::lockForUpdate()->find($idea->id);
            if (!$locked) return false;

            $article = $locked->generated_article ?? [];
            $prompts = $article['image_prompts'] ?? [];
            if (!isset($prompts[$segmentIndex])) return false;

            $prompts[$segmentIndex]['retry_count'] = 0;
            $prompts[$segmentIndex]['terminal_at'] = null;
            $prompts[$segmentIndex]['status'] = 'pending';

            $article['image_prompts'] = $prompts;
            $locked->generated_article = $article;
            $locked->save();
            return true;
        });
    }

    /**
     * Apply a safety rewrite to one segment in-place. Returns the mutated
     * segment array on success, or null if the rewrite couldn't be done
     * (no source VD, ArticleGenerationService failure). On null the caller
     * proceeds with the unrewritten segment so the retry path still runs
     * — the next attempt will fail the same way and either rewrite again
     * or hit the terminal cap. We never throw from here because failure
     * to rewrite must not break the FSM.
     *
     * Drops face_refs because the most common safety trigger is a public-
     * figure entity_ref, and after the rewrite the generic VD won't match
     * a specific person's face anyway. Keeps style_refs intact (they don't
     * trigger person filters).
     */
    private function rewriteSegmentForSafety(array $segment, string $safetyReason, int $ideaId, int $segmentIndex): ?array
    {
        $originalVd = $segment['visual_direction'] ?? $segment['prompt_text'] ?? $segment['prompt'] ?? '';
        if (trim($originalVd) === '') {
            Log::warning("[ImageGen] Safety rewrite skipped — no source VD on idea #{$ideaId} segment {$segmentIndex}");
            return null;
        }

        $context = [
            'label' => $segment['label'] ?? $segment['type'] ?? '',
            'concept' => $segment['concept'] ?? $segment['image_concept'] ?? '',
        ];

        try {
            $articleGen = app(\App\Services\ArticleGenerationService::class);
            $result = $articleGen->rewriteVisualDirectionForSafety($originalVd, $safetyReason, $context);
        } catch (\Throwable $e) {
            Log::error("[ImageGen] Safety rewrite threw on idea #{$ideaId} segment {$segmentIndex}: {$e->getMessage()}");
            return null;
        }

        if (!($result['success'] ?? false) || empty($result['rewritten_vd'])) {
            Log::warning("[ImageGen] Safety rewrite returned no output on idea #{$ideaId} segment {$segmentIndex}: " . ($result['error'] ?? 'unknown'));
            return null;
        }

        $rewritten = $result['rewritten_vd'];

        // Preserve original VD once (idempotent) so the operator can see
        // what was rewritten and so we don't lose authored content if
        // safety-rewrite gets re-applied on a later attempt.
        if (empty($segment['visual_direction_pre_safety'])) {
            $segment['visual_direction_pre_safety'] = $originalVd;
        }

        $segment['visual_direction'] = $rewritten;
        $segment['prompt_text'] = $rewritten;
        // Legacy field — some seeds use 'prompt' instead of 'prompt_text'.
        $segment['prompt'] = $rewritten;

        // Drop face refs — the most common GeminiGen safety trigger is a
        // public-figure entity_ref. Keep style_refs (environment/style)
        // since those don't trigger person-policy filters.
        $segment['face_refs'] = [];
        $segment['entity_refs'] = [];

        Log::info("[ImageGen] Safety rewrite applied on idea #{$ideaId} segment {$segmentIndex} (reason: {$safetyReason})");

        return $segment;
    }
}
