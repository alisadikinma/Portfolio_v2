<?php

namespace App\Jobs;

use App\Exceptions\ZernioApiException;
use App\Models\RepurposeJob;
use App\Services\ZernioClient;
use App\Services\ZernioPayloadBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Publish a video_rebrand repurpose carousel to Zernio (Instagram or Threads).
 *
 * Unlike PublishViaZernio (which publishes LinkedIn-carousel siblings), this has
 * NO sibling row — the media is the job's composited video slides and publish
 * state lives in repurpose_jobs.zernio_publish[$platform] (FSM-neutral; the job
 * stays `drafted`). TikTok is intentionally unsupported — it rejects video
 * carousels (single-video-only), validated live 2026-06-15.
 *
 * Idempotency mirrors PublishViaZernio:
 *   1. zernio_publish[$platform].post_id set → skip (a prior attempt published).
 *   2. request_id persisted BEFORE createPost + reused on retry → Zernio's own
 *      x-request-id dedup returns the original post if the worker died mid-flight.
 */
class PublishRepurposeViaZernio implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [60, 300, 900];

    public int $timeout = 600;

    /**
     * @param  int          $repurposeJobId
     * @param  string       $platform         'instagram'|'threads'
     * @param  string|null  $scheduledForIso  ISO-8601 future instant → Zernio scheduledFor; null → publishNow
     */
    public function __construct(
        public int $repurposeJobId,
        public string $platform,
        public ?string $scheduledForIso = null,
    ) {
        $this->onQueue('social-crosspost');
    }

    public function handle(ZernioClient $client, ZernioPayloadBuilder $builder): void
    {
        $job = RepurposeJob::find($this->repurposeJobId);
        if ($job === null) {
            Log::info('[PublishRepurposeViaZernio] Job not found — skipping', $this->ctx());

            return;
        }

        $state = $job->zernioPublishState($this->platform);
        if (! empty($state['post_id'])) {
            Log::info('[PublishRepurposeViaZernio] Already published — skipping (idempotent)', $this->ctx([
                'post_id' => $state['post_id'],
            ]));

            return;
        }

        if (! (bool) config('social-cross-post.zernio.enabled', false)) {
            Log::info('[PublishRepurposeViaZernio] Zernio publishing disabled (master switch) — skipping', $this->ctx());

            return;
        }

        if (! ZernioPayloadBuilder::isPlatformEnabled($this->platform)) {
            Log::info('[PublishRepurposeViaZernio] Platform not configured in Zernio settings — skipping', $this->ctx());

            return;
        }

        $requestId = $state['request_id'] ?? (string) Str::uuid();

        try {
            $payload = $this->applyScheduling($builder->buildRepurposeVideoCarousel($job, $this->platform));

            $this->mergeState(['status' => 'publishing', 'request_id' => $requestId, 'error' => null]);

            $result = $client->forPlatform($this->platform)->createPost($payload, $requestId);

            if (($result['duplicate'] ?? false) === true) {
                $existingId = $result['existingPostId'] ?? null;
                if ($existingId === null) {
                    Log::warning('[PublishRepurposeViaZernio] 409 duplicate without existingPostId — storing request id as fallback', $this->ctx());
                }
                $this->markPublished($existingId ?? $requestId, null);

                return;
            }

            $postId = $result['_id'] ?? data_get($result, 'existingPost._id') ?? $requestId;
            $url = data_get($result, 'platforms.0.platformPostUrl')
                ?? data_get($result, 'existingPost.platforms.0.platformPostUrl');

            // A future scheduledFor means Zernio is HOLDING the post (status
            // "scheduled"), not publishing now — label it accordingly so the UI
            // chip shows the scheduled time instead of a misleading "published".
            $scheduledFor = $this->scheduledForOrNull();
            if ($scheduledFor !== null) {
                $this->markScheduled($postId, $scheduledFor);
            } else {
                $this->markPublished($postId, $url);
            }
        } catch (ZernioApiException $e) {
            $message = $e->getMessage();
            if ($this->isTransientError($message)) {
                Log::warning('[PublishRepurposeViaZernio] Transient error — will retry', $this->ctx(['error' => $message]));
                throw $e;
            }
            $this->markFailed($message);
        } catch (\RuntimeException $e) {
            // Builder threw (no composited clips / missing account) — permanent.
            $this->markFailed($e->getMessage());
        }
    }

    /** publishNow, OR a FUTURE scheduledFor when the operator scheduled it. */
    private function applyScheduling(array $payload): array
    {
        $scheduledFor = $this->scheduledForOrNull();

        if ($scheduledFor !== null) {
            $payload['scheduledFor'] = $scheduledFor;
            $payload['timezone'] = config('app.timezone', 'UTC');
            $payload['publishNow'] = false;
        } else {
            $payload['publishNow'] = true;
        }

        return $payload;
    }

    /**
     * The normalized FUTURE scheduledFor ISO instant, or null when this is a
     * publish-now (no schedule, scheduling disabled, or a past instant). Single
     * source of truth for both the payload and the persisted status label.
     */
    private function scheduledForOrNull(): ?string
    {
        if (! (bool) config('social-cross-post.zernio.schedule_enabled', true) || ! $this->scheduledForIso) {
            return null;
        }
        $when = Carbon::parse($this->scheduledForIso);

        return $when->isFuture() ? $when->toIso8601String() : null;
    }

    private function markPublished(string $postId, ?string $url): void
    {
        $this->mergeState(['status' => 'published', 'post_id' => $postId, 'url' => $url, 'scheduled_for' => null, 'error' => null]);
        Log::info('[PublishRepurposeViaZernio] Published', $this->ctx(['post_id' => $postId]));
    }

    private function markScheduled(string $postId, string $scheduledFor): void
    {
        $this->mergeState(['status' => 'scheduled', 'post_id' => $postId, 'url' => null, 'scheduled_for' => $scheduledFor, 'error' => null]);
        Log::info('[PublishRepurposeViaZernio] Scheduled', $this->ctx(['post_id' => $postId, 'scheduled_for' => $scheduledFor]));
    }

    private function markFailed(string $message): void
    {
        $this->mergeState(['status' => 'failed', 'error' => mb_substr($message, 0, 1000)]);
        Log::error('[PublishRepurposeViaZernio] Marked failed', $this->ctx(['error' => $message]));
    }

    /**
     * Merge a patch into zernio_publish[$platform] under a row lock so a
     * concurrent platform job (IG vs Threads) can't clobber the other's key.
     */
    private function mergeState(array $patch): void
    {
        DB::transaction(function () use ($patch) {
            $job = RepurposeJob::lockForUpdate()->find($this->repurposeJobId);
            if ($job === null) {
                return;
            }
            $state = $job->zernio_publish ?? [];
            $state[$this->platform] = array_merge(
                $state[$this->platform] ?? [],
                $patch,
                ['updated_at' => now()->toIso8601String()],
            );
            $job->zernio_publish = $state;
            $job->save();
        });
    }

    public function failed(\Throwable $e): void
    {
        try {
            $job = RepurposeJob::find($this->repurposeJobId);
            if ($job !== null && empty($job->zernioPublishState($this->platform)['post_id'])) {
                $this->markFailed($e->getMessage());
            }
        } catch (\Throwable $inner) {
            Log::error('[PublishRepurposeViaZernio] Could not mark failed in failed()', ['inner' => $inner->getMessage()]);
        }
    }

    private function isTransientError(string $message): bool
    {
        if (preg_match('/\(5[0-9]{2}\)/', $message)) {
            return true;
        }

        return stripos($message, 'connection') !== false || stripos($message, 'timeout') !== false;
    }

    private function ctx(array $extra = []): array
    {
        return array_merge(['job_id' => $this->repurposeJobId, 'platform' => $this->platform], $extra);
    }
}
