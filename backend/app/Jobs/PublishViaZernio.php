<?php

namespace App\Jobs;

use App\Exceptions\ZernioApiException;
use App\Models\InstagramPost;
use App\Models\ThreadsPost;
use App\Models\TiktokPost;
use App\Services\ZernioClient;
use App\Services\ZernioPayloadBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Publish a cross-post sibling (Instagram/TikTok/Threads) via Zernio.
 *
 * Mirror of PublishViaPubler with three simplifications enabled by Zernio:
 *   - NO media pre-upload/poll — Zernio fetches public CDN URLs directly.
 *   - createPost is synchronous (publishNow returns platformPostUrl), so there
 *     is no await-job-result step.
 *   - Per-platform workspace key is selected by ZernioClient::forPlatform().
 *
 * Idempotency (two layers, mirroring the Publer guard):
 *   1. zernio_post_id already set → skip (a prior attempt published).
 *   2. zernio_request_id is persisted BEFORE createPost and reused on retry, so
 *      Zernio's own x-request-id dedup returns the original post instead of a
 *      duplicate if the worker dies mid-flight.
 *
 * Status writes use DB::table() (NOT $model->transitionTo) — same rationale as
 * PublishViaPubler: bypass Eloquent enum casting AND SQLite's stale CHECK
 * constraint while staying semantically correct on MySQL.
 */
class PublishViaZernio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public int $timeout = 600;

    /**
     * @param  string  $platform       'instagram'|'tiktok'|'threads'
     * @param  int     $siblingPostId  PK of the platform-specific draft row
     */
    public function __construct(
        public string $platform,
        public int $siblingPostId
    ) {
        // Same dedicated worker pool as the Publer path.
        $this->onQueue('social-crosspost');
    }

    public function handle(ZernioClient $client, ZernioPayloadBuilder $builder): void
    {
        $sibling = $this->loadSibling();

        if ($sibling === null) {
            Log::info('[PublishViaZernio] Sibling not found — skipping', [
                'platform' => $this->platform, 'sibling_id' => $this->siblingPostId,
            ]);

            return;
        }

        if ($sibling->zernio_post_id !== null) {
            Log::info('[PublishViaZernio] Already published — skipping (idempotent)', [
                'platform' => $this->platform, 'sibling_id' => $this->siblingPostId,
                'zernio_post_id' => $sibling->zernio_post_id,
            ]);

            return;
        }

        // Master kill-switch: ZERNIO_PUBLISH_ENABLED=false stops all Zernio
        // publishing (draft stays at its current state, re-dispatchable later).
        if (! (bool) config('social-cross-post.zernio.enabled', false)) {
            Log::info('[PublishViaZernio] Zernio publishing disabled (master switch) — skipping', [
                'platform' => $this->platform, 'sibling_id' => $this->siblingPostId,
            ]);

            return;
        }

        // Defense-in-depth: never publish to a platform with no Zernio account id
        // (a stale queued job could outlive a settings change). Skip silently.
        if (! ZernioPayloadBuilder::isPlatformEnabled($this->platform)) {
            Log::info('[PublishViaZernio] Platform not configured in Zernio settings — skipping', [
                'platform' => $this->platform, 'sibling_id' => $this->siblingPostId,
            ]);

            return;
        }

        // Persist a stable x-request-id BEFORE the call so a retry reuses it.
        $requestId = $sibling->zernio_request_id ?: (string) Str::uuid();

        try {
            $payload = match ($this->platform) {
                'instagram' => $builder->buildInstagram($sibling),
                'tiktok'    => $builder->buildTiktok($sibling),
                'threads'   => $builder->buildThreads($sibling),
            };
            $payload = $this->applyScheduling($payload, $sibling);

            DB::table($sibling->getTable())->where('id', $sibling->id)->update([
                'zernio_request_id' => $requestId,
                'status' => 'publishing',
                'updated_at' => now(),
            ]);

            $result = $client->forPlatform($this->platform)->createPost($payload, $requestId);

            // HTTP 409 content-dedup → already live on the platform; idempotent success.
            if (($result['duplicate'] ?? false) === true) {
                $existingId = $result['existingPostId'] ?? null;
                if ($existingId === null) {
                    Log::warning('[PublishViaZernio] 409 duplicate without existingPostId — storing request id as fallback', [
                        'platform' => $this->platform, 'sibling_id' => $this->siblingPostId,
                    ]);
                }
                $this->markPublished($sibling, $existingId ?? $requestId, null);

                return;
            }

            $postId = $result['_id'] ?? data_get($result, 'existingPost._id') ?? $requestId;
            $url = data_get($result, 'platforms.0.platformPostUrl')
                ?? data_get($result, 'existingPost.platforms.0.platformPostUrl');

            $this->markPublished($sibling, $postId, $url);
        } catch (ZernioApiException $e) {
            $message = $e->getMessage();

            if ($this->isTransientError($message)) {
                Log::warning('[PublishViaZernio] Transient error — will retry', [
                    'platform' => $this->platform, 'sibling_id' => $this->siblingPostId, 'error' => $message,
                ]);
                throw $e; // queue retry
            }

            $this->markFailed($sibling, $message);
        } catch (\RuntimeException $e) {
            // Builder threw (e.g. a slide lacks an app-hosted URL) — permanent.
            $this->markFailed($sibling, $e->getMessage());
        }
    }

    /** Add publishNow OR scheduledFor+timezone to the builder payload. */
    private function applyScheduling(array $payload, object $sibling): array
    {
        $scheduleEnabled = (bool) config('social-cross-post.zernio.schedule_enabled', true);

        // Only hand Zernio a FUTURE scheduledFor. In the local slot-orchestrator
        // model the cron holds the post until its slot, then fires this job — by
        // which point scheduled_at is in the past. A past scheduledFor would be
        // rejected by Zernio, so treat a non-future schedule as publish-now.
        if ($scheduleEnabled && $sibling->scheduled_at !== null && $sibling->scheduled_at->isFuture()) {
            $payload['scheduledFor'] = $sibling->scheduled_at->toIso8601String();
            $payload['timezone'] = config('app.timezone', 'UTC');
            $payload['publishNow'] = false;
        } else {
            $payload['publishNow'] = true;
        }

        return $payload;
    }

    private function markPublished(object $sibling, string $postId, ?string $url): void
    {
        DB::table($sibling->getTable())->where('id', $sibling->id)->update([
            'zernio_post_id' => $postId,
            'external_url' => $url,
            'status' => 'published',
            'published_at' => now(),
            'updated_at' => now(),
        ]);
        Log::info('[PublishViaZernio] Published', [
            'platform' => $this->platform, 'sibling_id' => $this->siblingPostId, 'zernio_post_id' => $postId,
        ]);
    }

    private function markFailed(object $sibling, string $message): void
    {
        DB::table($sibling->getTable())->where('id', $sibling->id)->update([
            'zernio_post_id' => null,
            'status' => 'failed',
            'last_error' => mb_substr($message, 0, 1000),
            'updated_at' => now(),
        ]);
        Log::error('[PublishViaZernio] Marked failed', [
            'platform' => $this->platform, 'sibling_id' => $this->siblingPostId, 'error' => $message,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        try {
            $sibling = $this->loadSibling();
            if ($sibling !== null && $sibling->zernio_post_id === null) {
                $this->markFailed($sibling, $e->getMessage());
            }
        } catch (\Throwable $inner) {
            Log::error('[PublishViaZernio] Could not mark failed in failed()', ['inner' => $inner->getMessage()]);
        }
    }

    private function loadSibling(): InstagramPost|TiktokPost|ThreadsPost|null
    {
        return match ($this->platform) {
            'instagram' => InstagramPost::find($this->siblingPostId),
            'tiktok'    => TiktokPost::find($this->siblingPostId),
            'threads'   => ThreadsPost::find($this->siblingPostId),
            default     => throw new \InvalidArgumentException(
                "Unknown platform: {$this->platform}. Zernio handles instagram|tiktok|threads."
            ),
        };
    }

    /**
     * 5xx/network errors are transient (already retried inside ZernioClient, but
     * a final throw after exhaustion should let the queue back off + retry too).
     * Everything else (401/403/validation 4xx) is permanent → mark failed.
     */
    private function isTransientError(string $message): bool
    {
        if (preg_match('/\(5[0-9]{2}\)/', $message)) {
            return true;
        }

        return stripos($message, 'connection') !== false
            || stripos($message, 'timeout') !== false;
    }
}
