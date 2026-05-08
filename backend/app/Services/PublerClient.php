<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Thin REST wrapper around Publer's API (https://app.publer.com/api/v1).
 *
 * Auth header format per Publer docs:
 *   Authorization: Bearer-API {token}
 * (NOT plain `Bearer` — note the `-API` suffix)
 *
 * API key resolution chain (highest precedence first):
 *   1. Constructor-injected key (testing override)
 *   2. settings(group=publer, key=publer_api_key) decrypted via Crypt
 *   3. Throws RuntimeException if neither present (operator hasn't configured yet)
 *
 * Methods are stateless — each call resolves api_key fresh so operator
 * rotation in admin UI takes effect on the next call without restart.
 *
 * Error mapping:
 *   401 → RuntimeException with message hinting "rotate or re-enter api_key in admin"
 *   403 → RuntimeException ("Publer plan/scope insufficient")
 *   429 → RuntimeException with message hinting rate limit (operator-actionable)
 *   5xx → retried up to max_retries (Laravel HTTP retry()), then throws
 *   404 from deletePost() → treated as success (idempotent — post already gone)
 *
 * NEVER logs the api_key value. Redacts api_key from any log entries via
 * `redactKey()` helper.
 */
class PublerClient
{
    private ?string $apiKeyOverride = null;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKeyOverride = $apiKey;
    }

    /**
     * GET /users/me — used to validate api_key (Test Connection button).
     * Returns user profile array on success.
     */
    public function me(): array
    {
        $response = $this->client()->get($this->url('/users/me'));
        return $this->extractData($response, 'me');
    }

    /**
     * GET /accounts — list all social accounts connected to the Publer
     * workspace. Used by admin Settings UI to populate per-platform
     * dropdowns (operator picks default FB Page + IG account + TikTok account).
     *
     * Returns array of account objects with at least: id, name, type
     * (facebook|instagram|tiktok|twitter|...), provider, picture_url.
     */
    public function listAccounts(): array
    {
        $response = $this->client()->get($this->url('/accounts'));
        return $this->extractData($response, 'listAccounts');
    }

    /**
     * POST /media/from-url — async-ingest a public PNG/JPG URL into Publer's
     * media library. Returns job_id; poll via pollMediaJob() until status
     * resolves to 'complete' (with media_id) or 'failed'.
     *
     * @param  string  $url  Publicly-accessible image URL (Publer downloads it)
     * @return string Publer job_id (use with pollMediaJob)
     */
    public function uploadMediaFromUrl(string $url): string
    {
        $response = $this->client()->post(
            $this->url('/media/from-url'),
            ['url' => $url]
        );

        $data = $this->extractData($response, 'uploadMediaFromUrl');
        $jobId = $data['job_id'] ?? null;

        if (!is_string($jobId) || $jobId === '') {
            throw new RuntimeException(
                'Publer /media/from-url returned no job_id. Response: ' . json_encode($data)
            );
        }

        return $jobId;
    }

    /**
     * GET /job_status/{job_id} — poll a media-upload OR post-publish job
     * (same endpoint covers both job types).
     *
     * Response shape (per Publer docs):
     *   { success: bool, data: { status: 'working'|'complete'|'failed', result: {...} } }
     *
     * On 'complete' for a media job, the inner payload includes the media_id
     * to embed in subsequent createPost() calls. Caller is responsible for
     * extracting platform-specific fields from result.
     *
     * @return array {status: string, result?: array, error?: string}
     */
    public function pollJob(string $jobId): array
    {
        $response = $this->client()->get($this->url("/job_status/{$jobId}"));
        $data = $this->extractData($response, 'pollJob');

        return [
            'status' => $data['status'] ?? 'unknown',
            'result' => $data['result'] ?? null,
            'error' => $data['error'] ?? null,
        ];
    }

    /**
     * Convenience alias for pollJob() when the caller knows it's a media job.
     * Returns array with shape {status, media_id?, error?}.
     */
    public function pollMediaJob(string $jobId): array
    {
        $job = $this->pollJob($jobId);

        // On complete, Publer's job result.payload contains media metadata.
        // Shape may evolve — defensive extraction.
        $mediaId = null;
        if (($job['status'] ?? null) === 'complete' && is_array($job['result'] ?? null)) {
            $payload = $job['result']['payload'] ?? $job['result'];
            $mediaId = $payload['id'] ?? $payload['media_id'] ?? null;
        }

        return [
            'status' => $job['status'],
            'media_id' => $mediaId,
            'error' => $job['error'],
        ];
    }

    /**
     * POST /posts/schedule — submit a batch of posts (one or more) for
     * scheduled publishing. Returns job_id; poll via pollJob() until
     * 'complete' (with publish details + URN) or 'failed' (with error).
     *
     * Request body shape (per Publer docs):
     *   {
     *     bulk: {
     *       state: 'scheduled' | 'draft' | 'recurring',
     *       posts: [
     *         {
     *           networks: { facebook|instagram|tiktok: { type, text, media[], ... } },
     *           accounts: [{ id, scheduled_at }]
     *         }
     *       ]
     *     }
     *   }
     *
     * @param  array  $payload  Pre-assembled bulk request body
     * @return string Publer job_id
     */
    public function createPost(array $payload): string
    {
        $response = $this->client()->post($this->url('/posts/schedule'), $payload);
        $data = $this->extractData($response, 'createPost');

        $jobId = $data['job_id'] ?? null;
        if (!is_string($jobId) || $jobId === '') {
            throw new RuntimeException(
                'Publer /posts/schedule returned no job_id. Response: ' . json_encode($data)
            );
        }

        return $jobId;
    }

    /**
     * DELETE /posts/{post_id} — remove a scheduled or draft post from
     * Publer's queue. Idempotent: 404 (already deleted or never existed)
     * is treated as success — same as our cancel-cascade semantics.
     *
     * Cannot delete already-published posts (Publer returns 422 or 4xx);
     * caller should check FSM state before invoking (FSM rule:
     * publishing → cancelled allowed, published → cancelled forbidden).
     */
    public function deletePost(string $postId): bool
    {
        $response = $this->client()->delete($this->url("/posts/{$postId}"));

        // 404 = already gone — idempotent success
        if ($response->status() === 404) {
            return true;
        }

        $this->extractData($response, 'deletePost');
        return true;
    }

    /**
     * Build a configured Laravel HTTP client with auth header + retry policy.
     * Returns a fresh PendingRequest each call so api_key is re-resolved
     * (operator can rotate in admin UI without restart).
     */
    private function client(): PendingRequest
    {
        $apiKey = $this->resolveApiKey();
        $timeout = (int) config('social-cross-post.publer.http_timeout_seconds', 30);
        $maxRetries = (int) config('social-cross-post.publer.max_retries', 3);
        $backoffMs = (int) config('social-cross-post.publer.retry_backoff_ms', 500);

        return Http::withHeaders([
            'Authorization' => "Bearer-API {$apiKey}",
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
            ->timeout($timeout)
            ->retry($maxRetries, $backoffMs, function ($exception, $request) {
                // Retry only on network errors + 5xx, not auth/validation failures
                if ($exception instanceof \Illuminate\Http\Client\ConnectionException) {
                    return true;
                }
                $status = method_exists($exception, 'response') && $exception->response()
                    ? $exception->response()->status()
                    : 0;
                return $status >= 500 && $status < 600;
            }, throw: false);
    }

    /**
     * Compose a full URL: base_url + api_path + relative path.
     * Example: url('/users') → 'https://app.publer.com/api/v1/users'
     */
    private function url(string $path): string
    {
        $base = rtrim((string) config('social-cross-post.publer.base_url'), '/');
        $apiPath = rtrim((string) config('social-cross-post.publer.api_path'), '/');
        $path = '/' . ltrim($path, '/');
        return $base . $apiPath . $path;
    }

    /**
     * Resolve api_key from constructor override OR settings table (decrypted).
     * Throws if neither path yields a non-empty key.
     */
    private function resolveApiKey(): string
    {
        if ($this->apiKeyOverride !== null && $this->apiKeyOverride !== '') {
            return $this->apiKeyOverride;
        }

        $encrypted = Setting::where('group', 'publer')
            ->where('key', 'publer_api_key')
            ->value('value');

        if (!is_string($encrypted) || $encrypted === '') {
            throw new RuntimeException(
                'Publer api_key not configured. Set it in admin /admin/about → Publer Integration card.'
            );
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable $e) {
            // Decryption failure usually means APP_KEY rotated after the
            // setting was encrypted — operator must re-enter the key.
            throw new RuntimeException(
                'Publer api_key decryption failed (possibly due to APP_KEY rotation). '
                . 'Re-enter the key in admin Settings.'
            );
        }
    }

    /**
     * Validate response + extract `data` field. Standard Publer envelope:
     *   Success: { success: true, data: ... }
     *   Failure: { success: false, error: { code, message } }
     *
     * Throws RuntimeException with context on auth/validation/server errors.
     * Logs failures (api_key redacted) before throwing.
     */
    private function extractData(Response $response, string $methodName): array
    {
        $status = $response->status();
        $body = $response->json();

        if ($status === 401) {
            $this->logFailure($methodName, $status, $body);
            throw new RuntimeException(
                'Publer auth failed (401). Rotate or re-enter api_key in admin Settings.'
            );
        }

        if ($status === 403) {
            $this->logFailure($methodName, $status, $body);
            throw new RuntimeException(
                'Publer access denied (403). Plan/scope may be insufficient — verify Publer subscription tier.'
            );
        }

        if ($status === 429) {
            $this->logFailure($methodName, $status, $body);
            throw new RuntimeException(
                'Publer rate limit hit (429). Limit is 100 req / 2 min per user — back off and retry.'
            );
        }

        if ($status >= 400) {
            $this->logFailure($methodName, $status, $body);
            $errorMessage = is_array($body) && isset($body['error'])
                ? (is_array($body['error']) ? ($body['error']['message'] ?? json_encode($body['error'])) : $body['error'])
                : "HTTP {$status}";
            throw new RuntimeException(
                "Publer {$methodName} failed: {$errorMessage}"
            );
        }

        // Success path. Some endpoints return data directly at top level
        // (e.g., listAccounts may return array), others wrap in {success, data}.
        if (is_array($body) && array_key_exists('data', $body)) {
            return is_array($body['data']) ? $body['data'] : ['value' => $body['data']];
        }

        // Top-level array (e.g., accounts list). Wrap for consistent return type.
        if (is_array($body)) {
            return $body;
        }

        return [];
    }

    /**
     * Log a failed Publer call without leaking the api_key.
     */
    private function logFailure(string $methodName, int $status, mixed $body): void
    {
        Log::warning('[PublerClient] API call failed', [
            'method' => $methodName,
            'status' => $status,
            'body' => $body,
            // api_key is INTENTIONALLY OMITTED from logs.
        ]);
    }
}
