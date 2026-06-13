<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Postiz cross-post publish job (local-node pull model).
 * See docs/plans/2026-06-13-postiz-local-node-crosspost.md (Phase A).
 *
 * Decoupled from LinkedInPostStatus on purpose — its own minimal status FSM
 * (ready_to_publish → claimed → accepted → published|failed) so the cross-post
 * backbone can swap Publer↔Postiz without touching the LinkedIn pipeline.
 */
class PostizPublishJob extends Model
{
    use HasFactory;

    public const STATUS_READY = 'ready_to_publish';
    public const STATUS_CLAIMED = 'claimed';
    public const STATUS_ACCEPTED = 'accepted';   // Postiz/Temporal owns it — watchdog hand-off
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FAILED = 'failed';
    // Ambiguous tail: the poller TOUCHED the job (claimed-then-silent, or reported
    // a failure that might have still reached Postiz) past its deadline. NEVER
    // auto-Publer (could double-publish) and NOT claimable (poller won't re-take
    // it) — operator inspects whether it actually went live on Postiz.
    public const STATUS_NEEDS_REVIEW = 'needs_review';

    protected $fillable = [
        'platform',
        'sibling_post_id',
        'sibling_type',
        'status',
        'claimed_by',
        'claimed_at',
        'publish_lease_until',
        'slot_due_at',
        'postiz_integration_id',
        'postiz_post_id',
        'permalink',
        'last_error',
        'fallback_fired_at',
    ];

    protected $casts = [
        'sibling_post_id' => 'integer',
        'claimed_at' => 'datetime',
        'publish_lease_until' => 'datetime',
        'slot_due_at' => 'datetime',
        'fallback_fired_at' => 'datetime',
    ];

    /**
     * Jobs the local poller may claim: ready AND (never leased OR lease expired).
     * Watchdog mirrors this lease check so it never races an active publish.
     */
    public function scopeClaimable(Builder $q): Builder
    {
        return $q->where('status', self::STATUS_READY)
            ->where(function (Builder $w) {
                $w->whereNull('publish_lease_until')
                    ->orWhere('publish_lease_until', '<', now());
            });
    }

    /** True once the poller got an OK from POST /posts — Postiz/Temporal owns it. */
    public function hasReachedPostiz(): bool
    {
        return $this->postiz_post_id !== null;
    }
}
