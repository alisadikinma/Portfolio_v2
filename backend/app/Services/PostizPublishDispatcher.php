<?php

namespace App\Services;

use App\Jobs\PublishViaPubler;
use App\Models\FacebookPost;
use App\Models\InstagramPost;
use App\Models\Post;
use App\Models\PostizChannel;
use App\Models\PostizPublishJob;
use App\Models\Setting;
use App\Models\ThreadsPost;
use App\Models\TiktokPost;
use Illuminate\Support\Facades\Log;

/**
 * Decides, at the slot dispatch point, whether a cross-post sibling publishes
 * through Postiz (the local-node pull model) or the legacy Publer path.
 * See docs/plans/2026-06-13-postiz-local-node-crosspost.md (Phase B).
 *
 * Default-OFF: until the operator flips `postiz_enabled='true'`, every sibling
 * keeps dispatching to Publer exactly as before (zero prod impact).
 *
 * When ON, this creates a `postiz_publish_jobs` row (status=ready_to_publish)
 * that the local poller claims under a lease. If no enabled Postiz channel is
 * mapped for the platform we fall back to Publer rather than silently drop a
 * publish — a missing mapping must never strand a post.
 */
class PostizPublishDispatcher
{
    /** platform → sibling model FQCN (carousel cross-post siblings). */
    private const SIBLING_TYPES = [
        'instagram' => InstagramPost::class,
        'tiktok' => TiktokPost::class,
        'threads' => ThreadsPost::class,
        'facebook' => FacebookPost::class,
    ];

    public function dispatchSibling(string $platform, int $siblingPostId): void
    {
        if (! $this->postizEnabled()) {
            PublishViaPubler::dispatch($platform, $siblingPostId);
            return;
        }

        $integrationId = PostizChannel::integrationIdFor($platform);
        if ($integrationId === null) {
            Log::warning('[PostizPublishDispatcher] No enabled Postiz channel mapped — falling back to Publer', [
                'platform' => $platform,
                'sibling_id' => $siblingPostId,
            ]);
            PublishViaPubler::dispatch($platform, $siblingPostId);
            return;
        }

        $siblingType = self::SIBLING_TYPES[$platform] ?? null;
        if ($siblingType === null) {
            Log::warning('[PostizPublishDispatcher] Unknown platform — falling back to Publer', [
                'platform' => $platform,
                'sibling_id' => $siblingPostId,
            ]);
            PublishViaPubler::dispatch($platform, $siblingPostId);
            return;
        }

        // Idempotent: re-dispatching the same (platform, sibling) at a later slot
        // tick must not create a duplicate job. firstOrCreate on the unique key.
        PostizPublishJob::firstOrCreate(
            ['platform' => $platform, 'sibling_post_id' => $siblingPostId],
            [
                'sibling_type' => $siblingType,
                'status' => PostizPublishJob::STATUS_READY,
                'slot_due_at' => now(),
                'postiz_integration_id' => $integrationId,
            ]
        );
    }

    /**
     * Blog→Medium cross-post (canonical backlink) — Phase K. Called from
     * ContentIdeaController::approveAndPublish after a blog Post publishes
     * (non-fatal try/catch at the call site). Gated by postiz_enabled AND
     * postiz_medium_enabled AND a mapped `medium` channel. No Publer fallback
     * exists for Medium (Publer can't post Medium articles) — the watchdog
     * treats medium like IG-video (wait + alert, never fallback).
     *
     * Idempotent firstOrCreate on (medium, post_id). slot_due_at=now (publish-now,
     * not slot-scheduled). The canonical URL + attribution footer are built at
     * `pending` time on the VPS so the URL is always authoritative.
     */
    public function dispatchBlogToMedium(Post $post): void
    {
        if (! $this->postizEnabled() || ! $this->mediumEnabled()) {
            return;
        }

        $integrationId = PostizChannel::integrationIdFor('medium');
        if ($integrationId === null) {
            Log::warning('[PostizPublishDispatcher] postiz_medium_enabled but no mapped medium channel — skipping blog→Medium', [
                'post_id' => $post->id,
            ]);
            return;
        }

        PostizPublishJob::firstOrCreate(
            ['platform' => 'medium', 'sibling_post_id' => $post->id],
            [
                'sibling_type' => Post::class,
                'status' => PostizPublishJob::STATUS_READY,
                'slot_due_at' => now(),
                'postiz_integration_id' => $integrationId,
            ]
        );
    }

    private function postizEnabled(): bool
    {
        return $this->boolSetting('postiz_enabled');
    }

    private function mediumEnabled(): bool
    {
        return $this->boolSetting('postiz_medium_enabled');
    }

    private function boolSetting(string $key): bool
    {
        $value = Setting::where('group', 'postiz')->where('key', $key)->value('value');

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
