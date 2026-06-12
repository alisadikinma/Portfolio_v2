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
    /** Generous: N media uploads (each upload+poll) + publish-job poll. */
    public int $timeout = 240;

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

        // Defense-in-depth gate: never publish to a platform the operator hasn't
        // selected a Publer account for (dispatch sites also gate, but a stale
        // queued job could outlive a settings change). Skip silently — leave the
        // sibling untouched so re-selecting the account + re-dispatching works.
        if (!PublerPayloadBuilder::isPlatformEnabled($this->platform)) {
            Log::info('[PublishViaPubler] Platform not configured in Publer settings — skipping', [
                'platform' => $this->platform,
                'sibling_id' => $this->siblingPostId,
            ]);
            return;
        }

        try {
            // 0. Build the normalized spec. The builder throws a RuntimeException
            //    if any slide lacks an app-hosted (Publer-ingestible) media URL —
            //    classified below as a permanent failure (no wasted retries).
            $method = 'build' . ucfirst($this->platform);
            $spec = $builder->$method($sibling);

            Log::info('[PublishViaPubler] Dispatching to Publer', [
                'platform' => $this->platform,
                'sibling_id' => $this->siblingPostId,
                'media_count' => count($spec['media_urls'] ?? []),
            ]);

            // 1. Pre-upload every media URL → media_ids (Publer rejects raw URLs).
            //    media_types[i] tags each item (image|video) so the IG mixed
            //    carousel uploads the hook video with the right extension.
            $mediaIds = [];
            $mediaTypes = $spec['media_types'] ?? [];
            foreach ($spec['media_urls'] as $i => $url) {
                $ext = ($mediaTypes[$i] ?? 'image') === 'video' ? 'mp4' : 'png';
                $name = sprintf('%s-%d-slide-%02d.%s', $this->platform, $sibling->id, $i + 1, $ext);
                $mediaIds[] = $client->uploadAndAwaitMedia($url, $name);
            }

            // 2. Assemble the single post object (networks + accounts + comments).
            $post = $this->assemblePost($spec, $mediaIds);

            // 3. Submit the publish job.
            $jobId = $client->publishNow($post);

            // Persist the Publer job id IMMEDIATELY (before awaiting) + mark
            // 'publishing'. This is the duplicate guard: if the confirmation
            // poll times out or the worker dies, publer_post_id is already set,
            // so the idempotency check at the top of handle() blocks any retry
            // from publishing a SECOND copy. (Real immediate publishes can take
            // 30-90s; the publish is async on Publer's side.)
            DB::table($sibling->getTable())
                ->where('id', $sibling->id)
                ->update(['publer_post_id' => $jobId, 'status' => 'publishing', 'updated_at' => now()]);

            // 4. Confirm via the async job result (200 + job_id != published).
            $result = $client->awaitPublishResult($jobId);

            if ($result['ok'] ?? false) {
                DB::table($sibling->getTable())
                    ->where('id', $sibling->id)
                    ->update([
                        'publer_post_id' => $result['post_id'] ?? $jobId,
                        'status' => 'published',
                        'published_at' => now(),
                        'updated_at' => now(),
                    ]);
                Log::info('[PublishViaPubler] Published successfully', [
                    'platform' => $this->platform,
                    'sibling_id' => $this->siblingPostId,
                    'publer_post_id' => $result['post_id'] ?? $jobId,
                ]);
                return;
            }

            if ($result['timed_out'] ?? false) {
                // Still processing — NOT a failure. Leave as 'publishing' with
                // the job id set; the post may yet go live and a retry would
                // duplicate it. Operator/reconcile resolves the final state via
                // the Publer job status.
                Log::warning('[PublishViaPubler] Publish still processing — left as publishing (no retry, no dup)', [
                    'platform' => $this->platform,
                    'sibling_id' => $this->siblingPostId,
                    'job_id' => $jobId,
                ]);
                return;
            }

            // Genuine failure (Publer reported per-account problems / job
            // failed). Clear the job id so the operator can retry cleanly.
            $msg = $result['error'] ?? 'Publer publish job reported failure';
            DB::table($sibling->getTable())
                ->where('id', $sibling->id)
                ->update([
                    'publer_post_id' => null,
                    'status' => 'failed',
                    'last_error' => mb_substr($msg, 0, 1000),
                    'updated_at' => now(),
                ]);
            Log::error('[PublishViaPubler] Publish job reported failure — marked failed', [
                'platform' => $this->platform,
                'sibling_id' => $this->siblingPostId,
                'job_id' => $jobId,
                'error' => $msg,
            ]);
            return;
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

    /**
     * Assemble the single Publer post object from the builder spec + the
     * media_ids resolved from the pre-upload step:
     *   { networks: { <net>: {type,text,media:[{id,type:image}]} },
     *     accounts: [{id, scheduled_at:""}], comments?: [...] }
     */
    private function assemblePost(array $spec, array $mediaIds): array
    {
        $networkFields = $spec['network_fields'];
        if (!empty($mediaIds)) {
            $mediaTypes = $spec['media_types'] ?? [];
            $networkFields['media'] = array_map(
                fn ($id, $i) => ['id' => $id, 'type' => $mediaTypes[$i] ?? 'image'],
                $mediaIds,
                array_keys($mediaIds)
            );
        }

        $post = [
            'networks' => [$spec['network'] => $networkFields],
            'accounts' => [['id' => $spec['account_id'], 'scheduled_at' => '']],
        ];

        if (!empty($spec['comments'])) {
            $post['comments'] = $spec['comments'];
        }

        return $post;
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
        // Publer media-from-url concurrency throttle (403 "another download-media
        // job is still running") + rate limit (429) — back off and retry, not a
        // permanent content/plan failure.
        if (stripos($message, 'media busy') !== false
            || stripos($message, 'download media') !== false
            || stripos($message, 'rate limit') !== false) {
            return true;
        }
        // Other 401/403 and 4xx are permanent failures.
        return false;
    }
}
