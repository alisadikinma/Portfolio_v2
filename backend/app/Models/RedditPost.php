<?php

namespace App\Models;

use App\Enums\RedditPostStatus;
use App\Traits\HasStatusTransitions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Reddit cross-post draft (2026-06-16 — 4th Zernio platform).
 *
 * Carousel path = image gallery (Reddit has no multi-video carousel). Reddit
 * is ZERNIO-ONLY (reuses the Threads workspace key via ZernioClient::forPlatform).
 * Slides not stored here — read live via $this->linkedinPost->carousel_slides.
 * Reddit-specific: a required `title` (≤300) + a `subreddit` (snapshot at create,
 * default u_alisadikinma — own profile, zero moderation).
 */
class RedditPost extends Model
{
    use HasFactory;
    use HasStatusTransitions;
    use SoftDeletes;

    protected $table = 'reddit_posts';

    protected $fillable = [
        'linkedin_post_id',
        'post_id',
        'status',
        'format',
        'title',
        'caption',
        'hashtags',
        'subreddit',
        'flair_id',
        'scheduled_at',
        'published_at',
        'external_url',
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
        return RedditPostStatus::class;
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
        return $q->whereIn('status', RedditPostStatus::feedStatuses());
    }

    public function scopeInQueue(Builder $q): Builder
    {
        return $q->whereIn('status', RedditPostStatus::queueStatuses());
    }
}
