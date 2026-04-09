<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PageSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_type',
        'section_type',
        'title',
        'description',
        'video_url',
        'content',
        'is_active',
        'sequence',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sequence' => 'integer',
        'content' => 'array',
    ];

    public function scopeForPage($query, $pageType)
    {
        return $query->where('page_type', $pageType);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sequence');
    }
}
