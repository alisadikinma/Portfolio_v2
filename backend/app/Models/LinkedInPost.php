<?php

namespace App\Models;

use App\Enums\LinkedInPostStatus;
use App\Traits\HasStatusTransitions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class LinkedInPost extends Model
{
    use HasFactory;
    use HasStatusTransitions;
    use SoftDeletes;

    protected $table = 'linkedin_posts';

    protected $fillable = [
        'post_id',
        'linkedin_account_id',
        'format',
        'content',
        'link_comment',
        'hashtags',
        'carousel_slides',
        'carousel_pdf_path',
        'depth_score',
        'validation_log',
        'scheduled_at',
        'cancel_window_ends_at',
        'published_at',
        'linkedin_post_urn',
        'linkedin_asset_urn',
        'thumbnail_asset_urn',
        'slide_asset_urns',
        'linkedin_post_url',
        'status',
        'pipeline_state_log',
        'last_error',
        'retry_count',
        'auto_retry_count',
        'last_classified_error_class',
        'progress_percentage',
        'current_step',
        'progress_log',
    ];

    protected $casts = [
        'hashtags' => 'array',
        'carousel_slides' => 'array',
        'slide_asset_urns' => 'array',
        'validation_log' => 'array',
        'pipeline_state_log' => 'array',
        'progress_log' => 'array',
        'progress_percentage' => 'integer',
        'auto_retry_count' => 'integer',
        'scheduled_at' => 'datetime',
        'cancel_window_ends_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    protected function statusEnumClass(): string
    {
        return LinkedInPostStatus::class;
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(LinkedInAccount::class, 'linkedin_account_id');
    }

    // Cross-post siblings — one row per platform fanned out from this
    // LinkedIn draft. Each carries its OWN caption + hashtags + status,
    // but shares the source carousel slides via the parent LinkedIn post.
    // The detail page surfaces these so the operator can flip platform
    // tabs in-place to compare/edit per-platform copy.
    public function facebookPost(): HasOne
    {
        return $this->hasOne(FacebookPost::class, 'linkedin_post_id');
    }

    public function instagramPost(): HasOne
    {
        return $this->hasOne(InstagramPost::class, 'linkedin_post_id');
    }

    public function tiktokPost(): HasOne
    {
        return $this->hasOne(TiktokPost::class, 'linkedin_post_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', LinkedInPostStatus::Published->value);
    }

    public function scopeScheduled(Builder $q): Builder
    {
        return $q->where('status', LinkedInPostStatus::AwaitingPublish->value);
    }

    public function scopeInFeed(Builder $q): Builder
    {
        return $q->whereIn('status', LinkedInPostStatus::feedStatuses());
    }

    public function scopeInQueue(Builder $q): Builder
    {
        return $q->whereIn('status', LinkedInPostStatus::queueStatuses());
    }

    /**
     * Defense-in-depth guard: legacy drafts created before commit c64e9c31
     * (Apr 29, 2026) may have a non-URL link_comment (e.g., a stale
     * pull_quote / insight sentence) because LinkedInGenerationService::
     * resolveLinkComment didn't exist yet. PostLinkedInFirstComment posts
     * link_comment as-is, so without this guard a legacy draft published
     * post-fix would put the insight sentence in the comment instead of
     * the blog URL — defeating the whole point of the link-in-comment
     * automation (avoiding LinkedIn's 60% reach penalty on body links).
     *
     * Idempotent. No-op when link_comment already contains http(s)://.
     * Returns true when the field was rewritten.
     */
    public function ensureLinkCommentHasUrl(): bool
    {
        $current = trim((string) $this->link_comment);
        if ($current !== '' && preg_match('#https?://#i', $current) === 1) {
            return false;
        }
        $appUrl = rtrim((string) config('app.url'), '/');
        $slug = (string) ($this->post?->slug ?? '');
        if ($appUrl === '' || $slug === '') {
            return false;
        }
        $this->update(['link_comment' => "Full article: {$appUrl}/blog/{$slug}"]);
        return true;
    }
}
