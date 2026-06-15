<?php

namespace App\Models;

use App\Enums\ThreadsPostStatus;
use App\Traits\HasStatusTransitions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Threads cross-post draft (May 10, 2026 cross-post pipeline).
 *
 * Tier-2 platform: text format reuses LinkedIn caption (truncated 500 char,
 * 1-3 hashtags); carousel reuses /instagram-gen output. No dedicated plugin.
 *
 * Slides not stored here — read live via $this->linkedinPost->carousel_slides
 * (same PNGs reused across all 5 platforms post-/carousel-gen render).
 */
class ThreadsPost extends Model
{
    use HasFactory;
    use HasStatusTransitions;
    use SoftDeletes;

    protected $table = 'threads_posts';

    protected $fillable = [
        'linkedin_post_id',
        'post_id',
        'status',
        'format',
        'title',
        'caption',
        'link_comment',
        'hashtags',
        'scheduled_at',
        'published_at',
        'external_url',
        'publer_post_id',
        'publer_job_id',
        'publer_status',
        'publer_account_id',
        'zernio_post_id',
        'zernio_request_id',
        'last_error',
        'auto_retry_count',
        'last_classified_error_class',
        'pipeline_state_log',
        'created_by_user_id',
    ];

    protected $casts = [
        'hashtags' => 'array',
        'pipeline_state_log' => 'array',
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    protected function statusEnumClass(): string
    {
        return ThreadsPostStatus::class;
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function linkedinPost(): BelongsTo
    {
        return $this->belongsTo(LinkedInPost::class, 'linkedin_post_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function scopeInFeed(Builder $q): Builder
    {
        return $q->whereIn('status', ThreadsPostStatus::feedStatuses());
    }

    public function scopeInQueue(Builder $q): Builder
    {
        return $q->whereIn('status', ThreadsPostStatus::queueStatuses());
    }
}
