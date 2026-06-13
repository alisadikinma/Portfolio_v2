<?php

namespace App\Http\Controllers\Api\Automation;

use App\Http\Controllers\Controller;
use App\Models\PostizChannel;
use App\Models\PostizPublishJob;
use App\Models\Setting;
use App\Services\PublerPayloadBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Postiz pull-model coordination endpoints (token-auth automation routes).
 * See docs/plans/2026-06-13-postiz-local-node-crosspost.md (Phases C/D/E).
 *
 *   GET  /automation/postiz/pending        → atomic CLAIM + lease, hand the
 *                                             local poller real media + caption
 *   POST /automation/postiz/{job}/result   → callback (accepted|published|failed)
 *   POST /automation/postiz/channels/sync  → upsert platform→integration mapping
 *
 * The local node is dumb: it claims, fetches media, publishes via Postiz, and
 * callbacks. ALL scheduling + anti-double-publish authority stays here.
 */
class PostizPublishController extends Controller
{
    public function __construct(private readonly PublerPayloadBuilder $builder)
    {
    }

    /**
     * Atomic claim: inside a transaction, lock claimable jobs, mark them
     * claimed under a lease, and return a publish-ready payload per job. Jobs
     * whose media can't be resolved (e.g. a slide leaked an expiring URL) are
     * NOT claimed — last_error is recorded and they stay ready for the watchdog.
     */
    public function pending(Request $request): JsonResponse
    {
        $worker = (string) $request->query('worker', 'local');
        if (strlen($worker) > 64) {
            $worker = substr($worker, 0, 64);
        }
        $limit = min(max((int) $request->query('limit', 10), 1), 50);
        $leaseMinutes = (int) (Setting::where('group', 'postiz')->where('key', 'postiz_lease_minutes')->value('value') ?? 10);

        $jobs = [];

        DB::transaction(function () use (&$jobs, $worker, $limit, $leaseMinutes) {
            $candidates = PostizPublishJob::query()
                ->claimable()
                ->orderBy('slot_due_at')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            foreach ($candidates as $job) {
                $payload = $this->buildJobPayload($job);
                if ($payload === null) {
                    continue; // media unresolved — left ready, last_error set
                }

                $job->update([
                    'status' => PostizPublishJob::STATUS_CLAIMED,
                    'claimed_by' => $worker,
                    'claimed_at' => now(),
                    'publish_lease_until' => now()->addMinutes($leaseMinutes),
                ]);

                $payload['lease_until'] = $job->publish_lease_until->toIso8601String();
                $jobs[] = $payload;
            }
        });

        // Read-only prefetch hint: ready, unclaimed, due within 48h.
        $upcoming = PostizPublishJob::query()
            ->where('status', PostizPublishJob::STATUS_READY)
            ->whereNull('publish_lease_until')
            ->whereBetween('slot_due_at', [now(), now()->addHours(48)])
            ->orderBy('slot_due_at')
            ->limit(50)
            ->get(['id', 'platform', 'slot_due_at'])
            ->map(fn ($j) => [
                'job_id' => $j->id,
                'platform' => $j->platform,
                'slot_due_at' => $j->slot_due_at?->toIso8601String(),
            ])
            ->all();

        return response()->json(['jobs' => $jobs, 'upcoming' => $upcoming]);
    }

    /**
     * Per-platform completion callback.
     *  accepted  → Postiz/Temporal owns it (store post_id, extend lease, hand off)
     *  published → flip job + mirror sibling published columns + store permalink
     *  failed    → pre-accepted: clear lease (fallback-eligible);
     *              post-accepted: mark failed + operator-alert log, NO auto-Publer
     */
    public function result(Request $request, PostizPublishJob $job): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:accepted,published,failed',
            'postiz_post_id' => 'nullable|string|max:191',
            'permalink' => 'nullable|string|max:1000',
            'error' => 'nullable|string|max:2000',
        ]);

        // Idempotency: terminal job is immutable.
        if (in_array($job->status, [PostizPublishJob::STATUS_PUBLISHED, PostizPublishJob::STATUS_FAILED], true)) {
            return response()->json(['ok' => true, 'status' => $job->status, 'idempotent' => true]);
        }

        $leaseMinutes = (int) (Setting::where('group', 'postiz')->where('key', 'postiz_lease_minutes')->value('value') ?? 10);

        switch ($data['status']) {
            case 'accepted':
                $job->update([
                    'status' => PostizPublishJob::STATUS_ACCEPTED,
                    'postiz_post_id' => $data['postiz_post_id'] ?? $job->postiz_post_id,
                    'publish_lease_until' => now()->addMinutes($leaseMinutes),
                ]);
                break;

            case 'published':
                $job->update([
                    'status' => PostizPublishJob::STATUS_PUBLISHED,
                    'permalink' => $data['permalink'] ?? null,
                    'publish_lease_until' => null,
                ]);
                $this->mirrorSiblingPublished($job, $data['permalink'] ?? null);
                break;

            case 'failed':
                $reachedPostiz = $job->hasReachedPostiz();
                $job->update([
                    'status' => PostizPublishJob::STATUS_FAILED,
                    'last_error' => $data['error'] ?? 'Postiz publish failed',
                    // Pre-accepted enqueue failure → clear lease so the watchdog
                    // can fire a Publer fallback. Post-accepted ERROR → keep the
                    // hand-off intact (postiz_post_id set) so the watchdog NEVER
                    // re-publishes; operator handles the tail.
                    'publish_lease_until' => $reachedPostiz ? $job->publish_lease_until : null,
                ]);
                if ($reachedPostiz) {
                    // System-level alert — DispatchTelegramNotification is keyed
                    // to ContentIdea; follow the established slot-orchestrator
                    // precedent of surfacing system events via WARNING log until
                    // a system-level Telegram method exists.
                    Log::warning('[postiz:result] Post-accepted Temporal ERROR — manual handling required, NO Publer fallback', [
                        'job_id' => $job->id,
                        'platform' => $job->platform,
                        'postiz_post_id' => $job->postiz_post_id,
                        'error' => $data['error'] ?? null,
                    ]);
                }
                break;
        }

        return response()->json(['ok' => true, 'status' => $job->fresh()->status]);
    }

    /**
     * Upsert the platform→integration mapping reported by the local poller.
     * Channels present in DB but absent from the payload are disabled (audit),
     * never deleted.
     */
    public function channelsSync(Request $request): JsonResponse
    {
        $data = $request->validate([
            'channels' => 'present|array',
            'channels.*.platform' => 'required|string|max:32',
            'channels.*.handle' => 'required|string|max:191',
            'channels.*.integration_id' => 'required|string|max:191',
            'channels.*.enabled' => 'nullable|boolean',
        ]);

        $seen = [];
        foreach ($data['channels'] as $ch) {
            PostizChannel::updateOrCreate(
                ['platform' => $ch['platform'], 'handle' => $ch['handle']],
                [
                    'postiz_integration_id' => $ch['integration_id'],
                    'enabled' => $ch['enabled'] ?? true,
                    'last_synced_at' => now(),
                ]
            );
            $seen[] = $ch['platform'] . '|' . $ch['handle'];
        }

        // Disable channels that vanished from Postiz (don't delete — audit trail).
        // GUARD: only run the disable-sweep on a NON-EMPTY payload. A transient
        // Postiz GET /integrations returning [] (restart / partial outage) must
        // NOT cascade into disabling every channel (which would strand publishes
        // to Publer fallback / Medium). An empty sync is treated as a no-op here.
        if (! empty($data['channels'])) {
            PostizChannel::query()->where('enabled', true)->get()->each(function (PostizChannel $c) use ($seen) {
                if (! in_array($c->platform . '|' . $c->handle, $seen, true)) {
                    $c->update(['enabled' => false]);
                }
            });
        }

        return response()->json(['ok' => true, 'synced' => count($data['channels'])]);
    }

    /**
     * Build the publish payload for a claimed job, or null when media can't be
     * resolved (records last_error so the operator/watchdog can act).
     *
     * @return array<string,mixed>|null
     */
    private function buildJobPayload(PostizPublishJob $job): ?array
    {
        $sibling = $job->sibling_type::find($job->sibling_post_id);
        if ($sibling === null) {
            $job->update(['last_error' => 'Sibling row not found: ' . class_basename($job->sibling_type) . " #{$job->sibling_post_id}"]);
            return null;
        }

        // Medium = blog article (Post), not a carousel — distinct payload shape
        // (title + HTML body + canonical + reader-visible backlink footer).
        if ($job->platform === 'medium' && $sibling instanceof \App\Models\Post) {
            return $this->buildMediumPayload($job, $sibling);
        }

        try {
            $content = $this->builder->resolveSiblingContent($sibling);
        } catch (\Throwable $e) {
            $job->update(['last_error' => 'Media unresolved: ' . mb_substr($e->getMessage(), 0, 500)]);
            return null;
        }

        return [
            'job_id' => $job->id,
            'platform' => $job->platform,
            'postiz_integration_id' => $job->postiz_integration_id,
            'media_urls' => $content['media_urls'],
            'media_types' => $content['media_types'],
            'caption' => $content['caption'],
            'hashtags' => $content['hashtags'],
            'link_comment' => $content['link_comment'],
            'post_type' => $job->platform === 'instagram' ? 'post' : null,
        ];
    }

    /**
     * Build the Medium publish payload from a blog Post — dual SEO:
     *  - canonical = the blog permalink (rel=canonical signal, consolidates rank)
     *  - content = primary-translation HTML + a server-built attribution footer
     *    with a real clickable backlink (reader-visible referral driver)
     * The URL is authoritative (built here, never hardcoded in the poller).
     *
     * @return array<string,mixed>
     */
    private function buildMediumPayload(PostizPublishJob $job, \App\Models\Post $post): array
    {
        $slug = $post->slug;
        $frontendUrl = rtrim((string) config('app.frontend_url', 'https://alisadikinma.com'), '/');
        $canonical = $frontendUrl . '/blog/' . $slug;

        $translation = $post->relationLoaded('translations')
            ? $post->translations->first()
            : $post->translations()->first();

        $title = $translation->title ?? $slug;
        $body = (string) ($translation->content ?? '');

        $footer = '<hr><p><em>Originally published at '
            . '<a href="' . $canonical . '">alisadikinma.com</a></em></p>';

        return [
            'job_id' => $job->id,
            'platform' => 'medium',
            'postiz_integration_id' => $job->postiz_integration_id,
            'title' => $title,
            'content' => $body . "\n" . $footer,
            'canonical' => $canonical,
            'media_urls' => [],
            'media_types' => [],
            'post_type' => null,
        ];
    }

    /**
     * Mirror the published state onto the sibling row so Social Studio reflects
     * it. Uses DB::table() (mirrors PublishViaPubler — bypasses Eloquent casting).
     *
     * The published value is `published` for ALL four sibling tables: migration
     * 2026_05_08_100001 renamed instagram/tiktok `published_externally →
     * published` (threads/facebook were always `published`), and
     * 2026_05_13_000001 patched the sqlite CHECK to match. So `published` is the
     * authoritative current enum value everywhere — same value PublishViaPubler
     * writes. permalink → the sibling's external_url column when present.
     */
    private function mirrorSiblingPublished(PostizPublishJob $job, ?string $permalink): void
    {
        $sibling = $job->sibling_type::find($job->sibling_post_id);
        if ($sibling === null) {
            return;
        }

        $table = $sibling->getTable();
        $update = ['status' => 'published', 'published_at' => now(), 'updated_at' => now()];
        if ($permalink !== null && Schema::hasColumn($table, 'external_url')) {
            $update['external_url'] = $permalink;
        }

        DB::table($table)->where('id', $sibling->id)->update($update);
    }
}
