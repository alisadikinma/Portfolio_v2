<?php

namespace App\Services;

use App\Models\ContentIdea;
use App\Models\ImageGenerationJob;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ImageGenerationService
{
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
        string $model = 'nano-banana-2',
        string $aspectRatio = '16:9',
        string $style = 'Photorealistic',
        array $faceRefs = [],
        array $styleRefs = [],
        string $additionalNotes = ''
    ): ?string {
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

            // Merge all reference URLs and send as file_urls to GeminiGen
            $allRefs = array_merge($faceRefs, $styleRefs);
            if (!empty($allRefs)) {
                $multipart[] = ['name' => 'file_urls', 'contents' => json_encode($allRefs)];
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
                $localUrl = $this->downloadAndStore($data['generate_result']);
                ImageGenerationJob::create([
                    'post_id' => $postId,
                    'uuid' => $uuid,
                    'type' => $type,
                    'prompt' => $prompt,
                    'insert_after_heading' => $insertAfterHeading,
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
     * Handle GeminiGen webhook callback.
     * Called when image generation completes or fails.
     */
    public function handleWebhook(string $uuid, string $event, array $data): bool
    {
        $job = ImageGenerationJob::where('uuid', $uuid)->first();

        if (!$job) {
            Log::warning("[ImageGen] Webhook: unknown UUID {$uuid}");
            return false;
        }

        if ($event === 'IMAGE_GENERATION_COMPLETED') {
            $remoteUrl = $data['media_url'] ?? null;
            if (!$remoteUrl) {
                Log::error("[ImageGen] Webhook: no media_url for UUID {$uuid}");
                $job->update(['status' => 'failed', 'error_message' => 'No media_url in webhook']);
                return false;
            }

            // Download and store locally
            $localUrl = $this->downloadAndStore($remoteUrl);

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
            $job->update([
                'status' => 'failed',
                'error_message' => $data['error_message'] ?? 'Unknown error',
            ]);
            Log::error("[ImageGen] Webhook: failed for UUID {$uuid} — " . ($data['error_message'] ?? ''));

            // Content Engine: mark the segment as failed
            $this->updateContentIdeaSegment($uuid, 'failed', null, $data['error_message'] ?? 'Unknown error');

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
        // Narrow scan: only ideas currently in image-gen-relevant statuses
        $ideas = ContentIdea::whereIn('status', ['article_ready', 'generating_images', 'images_ready'])
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get();

        foreach ($ideas as $idea) {
            $article = $idea->generated_article ?? [];
            $prompts = $article['image_prompts'] ?? [];
            $matched = false;

            foreach ($prompts as $i => $p) {
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

            if ($matched) {
                $article['image_prompts'] = $prompts;
                $idea->update(['generated_article' => $article]);

                // Auto-advance status: all segments done → images_ready
                $allDone = collect($prompts)->every(fn ($p) => in_array($p['status'] ?? '', ['done', 'failed']));
                $anyDone = collect($prompts)->contains(fn ($p) => ($p['status'] ?? '') === 'done');
                if ($allDone && $anyDone && $idea->status === 'generating_images') {
                    $idea->update(['status' => 'images_ready']);
                    Log::info("[ImageGen] Content idea {$idea->id} → images_ready (all segments done)");
                }

                Log::info("[ImageGen] Webhook: updated segment for idea {$idea->id} uuid={$uuid} status={$status}");
                return;
            }
        }

        Log::info("[ImageGen] Webhook: no matching content_idea segment for UUID {$uuid} (ok if legacy blog post)");
    }

    /**
     * Insert an inline image into the post's content after the specified heading.
     */
    private function insertInlineImage(ImageGenerationJob $job): void
    {
        if (!$job->image_url || !$job->insert_after_heading) return;

        $imgTag = '<figure class="my-8"><img src="' . e($job->image_url) . '" alt="' . e(\Illuminate\Support\Str::limit($job->prompt, 100)) . '" class="w-full rounded-xl" loading="lazy" /><figcaption class="text-sm text-gray-500 mt-2 text-center">' . e(\Illuminate\Support\Str::limit($job->insert_after_heading, 80)) . '</figcaption></figure>';

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
     * Download image from URL and store in public storage.
     */
    public function downloadAndStore(string $imageUrl): ?string
    {
        try {
            $imageData = Http::timeout(30)->get($imageUrl)->body();
            $filename = 'blog-images/' . time() . '_' . uniqid() . '.jpg';
            Storage::disk('public')->put($filename, $imageData);

            // Return full URL so images work from both backend and frontend
            return url('/storage/' . $filename);
        } catch (\Exception $e) {
            Log::error("[ImageGen] Download failed: {$e->getMessage()}");
            return $imageUrl; // Fallback: use remote URL
        }
    }
}
