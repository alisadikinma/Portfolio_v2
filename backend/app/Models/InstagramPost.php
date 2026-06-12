<?php

namespace App\Models;

use App\Enums\InstagramPostStatus;
use App\Traits\HasStatusTransitions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Instagram cross-post draft (May 7, 2026 cross-post pipeline).
 *
 * Slides are NOT stored on this model — read live via $this->linkedinPost->carousel_slides
 * which contains the rendered PNG URLs from the LinkedIn /carousel-gen pipeline.
 *
 * App-level invariant: one live (deleted_at IS NULL) row per post_id, enforced
 * by InstagramDraftController::regenerate (Phase E). MySQL doesn't support
 * partial unique indexes filtered on deleted_at — same precedent as LinkedInPost.
 */
class InstagramPost extends Model
{
    use HasFactory;
    use HasStatusTransitions;
    use SoftDeletes;

    protected $table = 'instagram_posts';

    protected $fillable = [
        'linkedin_post_id',
        'post_id',
        'status',
        'title',
        'caption',
        'text_only_caption',
        'link_comment',
        'hashtags',
        'scheduled_at',
        'published_at',
        'external_url',
        'hook_video_url',
        'hook_video_status',
        'hook_video_job_uuid',
        'hook_video_error',
        'publer_post_id',
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
        return InstagramPostStatus::class;
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
        return $q->whereIn('status', InstagramPostStatus::feedStatuses());
    }

    public function scopeInQueue(Builder $q): Builder
    {
        return $q->whereIn('status', InstagramPostStatus::queueStatuses());
    }
}
