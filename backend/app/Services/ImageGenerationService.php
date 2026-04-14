<?php

namespace App\Services;

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

            // Apply to post
            if ($job->type === 'hero' && $localUrl) {
                $job->post->update(['featured_image' => $localUrl]);
                Log::info("[ImageGen] Webhook: hero image set for post {$job->post_id}");
            } elseif ($job->type === 'inline' && $localUrl) {
                $this->insertInlineImage($job);
                Log::info("[ImageGen] Webhook: inline image inserted for post {$job->post_id}");
            }

            return true;

        } elseif ($event === 'IMAGE_GENERATION_FAILED') {
            $job->update([
                'status' => 'failed',
                'error_message' => $data['error_message'] ?? 'Unknown error',
            ]);
            Log::error("[ImageGen] Webhook: failed for UUID {$uuid} — " . ($data['error_message'] ?? ''));
            return false;
        }

        return false;
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
