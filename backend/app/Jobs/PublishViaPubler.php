<?php

namespace App\Jobs;

use App\Models\FacebookPost;
use App\Models\InstagramPost;
use App\Models\ThreadsPost;
use App\Models\TiktokPost;
use App\Services\PublerClient;
use App\Services\PublerPayloadBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Real Publer publish dispatcher (P4, 2026-05-13).
 *
 * Replaced the Phase E stub (which only logged and returned) with a real
 * implementation that calls PublerClient::createPost() and persists the
 * resulting Publer job_id as publer_post_id.
 *
 * Error routing:
 *   4xx (client error) → permanent failure — mark status='failed', persist
 *                         last_error, return (don't rethrow — retrying won't
 *                         help; operator must fix the draft content).
 *   5xx / network      → transient — rethrow so the queue retries up to
 *                         $tries times (60s / 300s / 900s backoff).
 *
 * Idempotency:
 *   publer_post_id already set → skip silently. Safe to dispatch multiple
 *   times (e.g., from both process-slot cron and admin publish-now).
 *
 * NOTE: We use DB::table()->update() for status/publer_post_id writes to
 * bypass SQLite's legacy CHECK constraint (from the original create-table
 * migration which predates the ENUM rename). MySQL enforces the real ENUM
 * column constraint at the DB level; SQLite enforces a string CHECK which
 * was never updated because the ALTER COLUMN migration is MySQL-only.
 * DB::table() skips both Eloquent model casting AND SQLite's CHECK guard
 * while being semantically equivalent on MySQL.
 */
class PublishViaPubler implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [60, 300, 900];
    public int $timeout = 60;

    /**
     * @param  string  $platform       'instagram'|'tiktok'|'threads'|'facebook'
     * @param  int     $siblingPostId  PK of the platform-specific draft row
     */
    public function __construct(
        public string $platform,
        public int $siblingPostId
    ) {
    }

    public function handle(PublerClient $client, PublerPayloadBuilder $builder): void
    {
        $sibling = $this->loadSibling();

        if ($sibling === null) {
            Log::info('[PublishViaPubler] Sibling not found — skipping', [
                'platform' => $this->platform,
                'sibling_id' => $this->siblingPostId,
            ]);
            return;
        }

        // Idempotency guard: already published via a prior attempt
        if ($sibling->publer_post_id !== null) {
            Log::info('[PublishViaPubler] Already published — skipping (idempotent)', [
                'platform' => $this->platform,
                'sibling_id' => $this->siblingPostId,
                'publer_post_id' => $sibling->publer_post_id,
            ]);
            return;
        }

        $method = 'build' . ucfirst($this->platform);
        $payload = $builder->$method($sibling);

        Log::info('[PublishViaPubler] Dispatching to Publer', [
            'platform' => $this->platform,
            'sibling_id' => $this->siblingPostId,
        ]);

        try {
            // createPost() returns a Publer job_id string on success,
            // throws RuntimeException on 4xx/5xx (already mapped by PublerClient).
            $jobId = $client->createPost($payload);

            DB::table($sibling->getTable())
                ->where('id', $sibling->id)
                ->update([
                    'publer_post_id' => $jobId,
                    'status' => 'published',
                    'published_at' => now(),
                    'updated_at' => now(),
                ]);

            Log::info('[PublishViaPubler] Published successfully', [
                'platform' => $this->platform,
                'sibling_id' => $this->siblingPostId,
                'publer_post_id' => $jobId,
            ]);
        } catch (\RuntimeException $e) {
            // PublerClient maps 4xx to RuntimeException with message describing
            // the error. These are permanent failures (validation, auth, quota)
            // — retrying won't help. Mark failed and return.
            $errorMessage = $e->getMessage();
            $isTransient = $this->isTransientError($errorMessage);

            if ($isTransient) {
                // 5xx or network error — let queue retry
                Log::warning('[PublishViaPubler] Transient error — will retry', [
                    'platform' => $this->platform,
                    'sibling_id' => $this->siblingPostId,
                    'error' => $errorMessage,
                ]);
                throw $e;
            }

            // Permanent 4xx — mark failed
            DB::table($sibling->getTable())
                ->where('id', $sibling->id)
                ->update([
                    'status' => 'failed',
                    'last_error' => mb_substr($errorMessage, 0, 1000),
                    'updated_at' => now(),
                ]);

            Log::error('[PublishViaPubler] Permanent failure — marked as failed', [
                'platform' => $this->platform,
                'sibling_id' => $this->siblingPostId,
                'error' => $errorMessage,
            ]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[PublishViaPubler] Job exhausted all retries', [
            'platform' => $this->platform,
            'sibling_id' => $this->siblingPostId,
            'error' => $e->getMessage(),
        ]);

        // Mark the sibling as failed so admin sees it in the queue
        try {
            $sibling = $this->loadSibling();
            if ($sibling !== null && $sibling->publer_post_id === null) {
                DB::table($sibling->getTable())
                    ->where('id', $sibling->id)
                    ->update([
                        'status' => 'failed',
                        'last_error' => mb_substr($e->getMessage(), 0, 1000),
                        'updated_at' => now(),
                    ]);
            }
        } catch (\Throwable $inner) {
            Log::error('[PublishViaPubler] Could not mark as failed in failed()', [
                'inner_error' => $inner->getMessage(),
            ]);
        }
    }

    /**
     * Load the platform-specific sibling model by ID.
     * Returns null when not found (allows graceful skip in handle()).
     *
     * @return FacebookPost|InstagramPost|TiktokPost|ThreadsPost|null
     */
    private function loadSibling(): FacebookPost|InstagramPost|TiktokPost|ThreadsPost|null
    {
        return match ($this->platform) {
            'instagram' => InstagramPost::find($this->siblingPostId),
            'tiktok'    => TiktokPost::find($this->siblingPostId),
            'threads'   => ThreadsPost::find($this->siblingPostId),
            'facebook'  => FacebookPost::find($this->siblingPostId),
            default     => throw new \InvalidArgumentException(
                "Unknown platform: {$this->platform}. Expected instagram|tiktok|threads|facebook."
            ),
        };
    }

    /**
     * Determine whether an error from PublerClient is transient (5xx/network)
     * or permanent (4xx/auth/validation).
     *
     * PublerClient's error messages include the HTTP status code in the string.
     * Pattern: "Publer {method} failed: HTTP 4xx" OR the mapped specific codes
     * (401, 403, 429) which all map to RuntimeException with "401"/"403"/"429"
     * in the message.
     */
    private function isTransientError(string $message): bool
    {
        // 5xx server errors are transient
        if (preg_match('/HTTP [5][0-9]{2}/', $message)) {
            return true;
        }
        // Network/connection errors
        if (stripos($message, 'connection') !== false || stripos($message, 'timeout') !== false) {
            return true;
        }
        // 401/403/429 and other 4xx are permanent failures
        return false;
    }
}
