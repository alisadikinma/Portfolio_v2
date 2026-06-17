<?php

namespace App\Models;

use App\Enums\FacebookPostStatus;
use App\Traits\HasStatusTransitions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Facebook Page cross-post draft (May 8, 2026 cross-post pipeline).
 *
 * Sibling model to InstagramPost + TiktokPost. Two divergences:
 *   1. `format` ENUM column (text|carousel) — FB receives both LinkedIn
 *      output formats. Caption authoring branches on this:
 *        text → clone $linkedinPost->content
 *        carousel → reuse /instagram-gen plugin output
 *   2. `link_url` column — populated for text format (Publer/FB unfurls
 *      preview), NULL for carousel
 *
 * Slides for carousel format read live via $this->linkedinPost->carousel_slides.
 *
 * App-level invariant: one live (deleted_at IS NULL) row per post_id, enforced
 * by FacebookDraftController::regenerate (Phase E). Same precedent as
 * LinkedInPost + InstagramPost + TiktokPost.
 */
class FacebookPost extends Model
{
    use HasFactory;
    use HasStatusTransitions;
    use SoftDeletes;

    protected $table = 'facebook_posts';

    protected $fillable = [
        'linkedin_post_id',
        'post_id',
        'status',
        'format',
        'title',
        'caption',
        'hashtags',
        'scheduled_at',
        'published_at',
        'external_url',
        'link_url',
        'publer_post_id',
        'publer_job_id',
        'publer_status',
        'publer_account_id',
        'zernio_post_id',
        'zernio_request_id',
        'last_error',
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
        return FacebookPostStatus::class;
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
        return $q->whereIn('status', FacebookPostStatus::feedStatuses());
    }

    public function scopeInQueue(Builder $q): Builder
    {
        return $q->whereIn('status', FacebookPostStatus::queueStatuses());
    }

    public function scopeOfFormat(Builder $q, string $format): Builder
    {
        return $q->where('format', $format);
    }
}
