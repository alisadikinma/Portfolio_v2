<?php

namespace App\Services;

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
     * Generate an image from a prompt using GeminiGen API.
     * Returns the local storage URL of the saved image, or null on failure.
     */
    public function generate(
        string $prompt,
        string $model = 'imagen-pro',
        string $aspectRatio = '16:9',
        string $style = 'Photorealistic'
    ): ?string {
        if (empty($this->apiKey)) {
            Log::error('[ImageGen] GEMINIGEN_API_KEY not configured.');
            return null;
        }

        try {
            // Call GeminiGen API
            $response = Http::timeout(30)
                ->withHeaders(['x-api-key' => $this->apiKey])
                ->asMultipart()
                ->post("{$this->baseUrl}/generate_image", [
                    ['name' => 'prompt', 'contents' => $prompt],
                    ['name' => 'model', 'contents' => $model],
                    ['name' => 'aspect_ratio', 'contents' => $aspectRatio],
                    ['name' => 'style', 'contents' => $style],
                ]);

            if (!$response->successful()) {
                Log::error("[ImageGen] API error: HTTP {$response->status()} — {$response->body()}");
                return null;
            }

            $data = $response->json();

            // Handle async processing (status=1 → poll)
            if (($data['status'] ?? 0) === 1 && isset($data['uuid'])) {
                $imageUrl = $this->pollForResult($data['uuid']);
            } elseif (($data['status'] ?? 0) === 2 && isset($data['generate_result'])) {
                $imageUrl = $data['generate_result'];
            } else {
                Log::error('[ImageGen] Unexpected response: ' . json_encode($data));
                return null;
            }

            if (!$imageUrl) {
                return null;
            }

            // Download and store locally
            return $this->downloadAndStore($imageUrl);
        } catch (\Exception $e) {
            Log::error("[ImageGen] Exception: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Poll GeminiGen API for async image result.
     */
    private function pollForResult(string $uuid, int $maxAttempts = 30): ?string
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            sleep(2);

            try {
                $response = Http::timeout(10)
                    ->withHeaders(['x-api-key' => $this->apiKey])
                    ->get("{$this->baseUrl}/history/{$uuid}");

                if (!$response->successful()) continue;

                $data = $response->json();

                if (($data['status'] ?? 0) === 2 && isset($data['generate_result'])) {
                    return $data['generate_result'];
                }

                if (($data['status'] ?? 0) === 3) {
                    Log::error("[ImageGen] Generation failed for UUID: {$uuid}");
                    return null;
                }
            } catch (\Exception $e) {
                Log::warning("[ImageGen] Poll attempt {$i} failed: {$e->getMessage()}");
            }
        }

        Log::error("[ImageGen] Polling timed out for UUID: {$uuid}");
        return null;
    }

    /**
     * Download image from URL and store in public storage.
     */
    private function downloadAndStore(string $imageUrl): ?string
    {
        try {
            $imageData = Http::timeout(15)->get($imageUrl)->body();
            $filename = 'blog-images/' . time() . '_' . uniqid() . '.jpg';

            // Store in public disk
            Storage::disk('public')->put($filename, $imageData);

            return '/storage/' . $filename;
        } catch (\Exception $e) {
            Log::error("[ImageGen] Download failed: {$e->getMessage()}");
            // Fallback: return the remote URL directly
            return $imageUrl;
        }
    }
}
