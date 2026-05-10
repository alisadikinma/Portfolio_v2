<?php

namespace App\Models;

use App\Enums\TiktokPostStatus;
use App\Traits\HasStatusTransitions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * TikTok cross-post draft (May 7, 2026 cross-post pipeline).
 *
 * Sibling model to InstagramPost — same FSM, same lifecycle. Two divergences:
 *   1. Title hard-caps at VARCHAR(100) at DB level — TikTok native field limit.
 *   2. music_suggestion column carries operator hint authored by /tiktok-gen
 *      plugin using vocabulary "[genre] | [mood] | [tempo]". Operator picks
 *      actual track in TikTok app (no API for music selection).
 */
class TiktokPost extends Model
{
    use HasFactory;
    use HasStatusTransitions;
    use SoftDeletes;

    protected $table = 'tiktok_posts';

    protected $fillable = [
        'linkedin_post_id',
        'post_id',
        'status',
        'title',
        'caption',
        'link_comment',
        'hashtags',
        'music_suggestion',
        'scheduled_at',
        'published_at',
        'external_url',
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
        return TiktokPostStatus::class;
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
        return $q->whereIn('status', TiktokPostStatus::feedStatuses());
    }

    public function scopeInQueue(Builder $q): Builder
    {
        return $q->whereIn('status', TiktokPostStatus::queueStatuses());
    }
}
