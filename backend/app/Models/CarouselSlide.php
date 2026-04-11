<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarouselSlide extends Model
{
    use HasFactory;

    protected $table = 'carousel_slides';

    protected $fillable = [
        'carousel_draft_id', 'slide_index', 'slide_type', 'source_image_url',
        'prompt', 'generated_image_url', 'generation_uuid', 'generation_status',
        'wow_score', 'error_message',
    ];

    protected $casts = [
        'wow_score' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function carouselDraft(): BelongsTo
    {
        return $this->belongsTo(CarouselDraft::class, 'carousel_draft_id');
    }

    public function scopeByGenerationStatus($query, $status)
    {
        return $query->where('generation_status', $status);
    }

    public function scopePending($query)
    {
        return $query->whereIn('generation_status', ['pending', 'processing']);
    }
}
