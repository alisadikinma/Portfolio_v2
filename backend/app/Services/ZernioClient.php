<?php

namespace App\Services;

use App\Exceptions\ZernioApiException;
use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * REST wrapper for the Zernio social-publishing API (https://zernio.com/api/v1).
 *
 * Mirrors PublerClient, with two structural differences:
 *   1. Auth is plain `Authorization: Bearer sk_...` (Publer uses `Bearer-API`).
 *   2. Zernio API keys are WORKSPACE-scoped and we run two workspaces, so the
 *      key is selected per-platform via forPlatform():
 *        instagram, tiktok  → zernio_api_key_igtt
 *        threads            → zernio_api_key_threads
 *
 * There is NO async media-ingest step (unlike Publer): createPost takes public
 * CDN media URLs directly in `mediaItems`, so this client has no upload/poll.
 *
 * Construct fresh per publish (the publish job resolves it from the container);
 * forPlatform() returns $this so calls chain: ->forPlatform('instagram')->createPost(...).
 */
class ZernioClient
{
    /** Resolved decrypted API key for the selected workspace. */
    private ?string $apiKey = null;

    /** Platform currently bound (for error context). */
    private ?string $platform = null;

    /**
     * Bind the client to a platform's workspace key. Three workspaces:
     *   instagram, tiktok   → zernio_api_key_igtt
     *   threads, reddit     → zernio_api_key_threads  (Reddit shares the Threads workspace)
     *   facebook, youtube   → zernio_api_key_fbyt     (2026-06-16)
     */
    public function forPlatform(string $platform): self
    {
        $this->platform = $platform;
        $settingKey = match ($platform) {
            'threads', 'reddit' => 'zernio_api_key_threads',
            'facebook', 'youtube' => 'zernio_api_key_fbyt',
            default => 'zernio_api_key_igtt', // instagram, tiktok (preserves prior fallback)
        };

        $this->apiKey = $this->resolveKey($settingKey);

        return $this;
    }

    /**
     * Create (and optionally publish/schedule) a post.
     *
     * @param  array  $payload  Zernio create-post body (content, mediaItems, platforms, publishNow|scheduledFor, …)
     * @param  string|null  $requestId  stable UUID sent as x-request-id for idempotent retries
     * @return array  decoded `post` on success, or {duplicate:true, existingPostId} on HTTP 409
     */
    public function createPost(array $payload, ?string $requestId = null): array
    {
        $headers = $requestId ? ['x-request-id' => $requestId] : [];

        $response = $this->client($headers)->post('/posts', $payload);

        if ($response->status() === 409) {
            $body = $response->json() ?? [];

            return [
                'duplicate' => true,
                'existingPostId' => data_get($body, 'details.existingPostId'),
            ];
        }

        if ($response->status() === 401 || $response->status() === 403) {
            throw new ZernioApiException(
                "Zernio auth failed ({$response->status()}) for {$this->platform}: ".$response->body()
            );
        }

        if ($response->failed()) {
            // 5xx/network already retried by HTTP retry(); a 4xx here is a real
            // validation/permission error the caller must surface (job → failed).
            throw new ZernioApiException(
                "Zernio createPost failed ({$response->status()}) for {$this->platform}: ".$response->body()
            );
        }

        return $response->json('post') ?? $response->json() ?? [];
    }

    /**
     * List connected accounts for the bound workspace. Used by the admin
     * "Verify Connection" button so the operator can copy the accountId.
     */
    public function listAccounts(): array
    {
        $response = $this->client()->get('/accounts');

        if ($response->failed()) {
            throw new ZernioApiException(
                "Zernio listAccounts failed ({$response->status()}): ".$response->body()
            );
        }

        return $response->json('accounts') ?? [];
    }

    /** Fetch a single post (e.g. to read platformPostUrl after a scheduled publish). */
    public function getPost(string $id): array
    {
        $response = $this->client()->get("/posts/{$id}");

        if ($response->failed()) {
            throw new ZernioApiException(
                "Zernio getPost failed ({$response->status()}): ".$response->body()
            );
        }

        return $response->json('post') ?? $response->json() ?? [];
    }

    /**
     * Build a configured HTTP client. 5xx/connection failures are retried per
     * config; the bearer key is the per-platform workspace key.
     */
    private function client(array $extraHeaders = []): PendingRequest
    {
        if (empty($this->apiKey)) {
            throw new ZernioApiException(
                'Zernio API key not configured for platform: '.($this->platform ?? 'unknown')
            );
        }

        $base = rtrim((string) config('social-cross-post.zernio.base_url'), '/')
            .config('social-cross-post.zernio.api_path');

        return Http::withHeaders(array_merge([
            'Authorization' => 'Bearer '.$this->apiKey,
            'Accept' => 'application/json',
        ], $extraHeaders))
            ->baseUrl($base)
            ->timeout((int) config('social-cross-post.zernio.http_timeout_seconds', 60))
            ->retry(
                (int) config('social-cross-post.zernio.max_retries', 3),
                (int) config('social-cross-post.zernio.retry_backoff_ms', 500),
                throw: false
            );
    }

    /** Decrypt a workspace key from the settings table. */
    private function resolveKey(string $settingKey): ?string
    {
        $encrypted = Setting::where('group', 'zernio')->where('key', $settingKey)->value('value');

        if (empty($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable $e) {
            Log::warning("Zernio key {$settingKey} failed to decrypt: ".$e->getMessage());

            return null;
        }
    }
}
