<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarouselDraft extends Model
{
    use HasFactory;

    protected $table = 'carousel_drafts';

    protected $fillable = [
        'title', 'source_url', 'shortcode', 'source_images', 'source_analysis',
        'creative_direction', 'status', 'target_platform', 'hook_category', 'captions',
        'scheduled_at', 'posted_at', 'post_result', 'rejection_reason',
    ];

    protected $casts = [
        'source_images' => 'array',
        'source_analysis' => 'array',
        'captions' => 'array',
        'post_result' => 'array',
        'scheduled_at' => 'datetime',
        'posted_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function slides(): HasMany
    {
        return $this->hasMany(CarouselSlide::class, 'carousel_draft_id')
            ->orderBy('slide_index', 'asc');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByPlatform($query, $platform)
    {
        return $query->where('target_platform', $platform);
    }

    public function scopePendingApproval($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
