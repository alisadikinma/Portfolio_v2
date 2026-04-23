<?php

namespace App\Models;

use App\Enums\LinkedInPostStatus;
use App\Traits\HasStatusTransitions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'linkedin_post_url',
        'status',
        'pipeline_state_log',
        'last_error',
        'retry_count',
    ];

    protected $casts = [
        'hashtags' => 'array',
        'carousel_slides' => 'array',
        'validation_log' => 'array',
        'pipeline_state_log' => 'array',
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
}
