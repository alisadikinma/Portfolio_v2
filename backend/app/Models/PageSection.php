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
        'is_active',
        'sequence',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sequence' => 'integer',
    ];

    /**
     * Get sections for specific page type
     */
    public function scopeForPage($query, $pageType)
    {
        return $query->where('page_type', $pageType);
    }

    /**
     * Get only active sections
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get sections ordered by sequence
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sequence');
    }
}
