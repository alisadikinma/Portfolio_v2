<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsletterSend extends Model
{
    protected $fillable = [
        'sent_at',
        'subscriber_count',
        'posts_count',
        'post_ids',
        'status',
        'error_message',
        'triggered_by',
        'created_by_user_id',
        'test_recipient',
        'duration_seconds',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'post_ids' => 'array',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
