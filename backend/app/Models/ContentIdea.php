<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentIdea extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'source',
        'pillar',
        'priority',
        'tags',
        'languages',
        'output_types',
        'instructions',
        'niche',
        'status',
        'research_data',
        'workflows',
        'source_data',
    ];

    protected $casts = [
        'tags' => 'array',
        'languages' => 'array',
        'output_types' => 'array',
        'research_data' => 'array',
        'workflows' => 'array',
        'source_data' => 'array',
    ];

    /**
     * Scope: exclude archived ideas.
     */
    public function scopeActive($query)
    {
        return $query->where('status', '!=', 'archived');
    }

    /**
     * Scope: filter by content pillar.
     */
    public function scopeByPillar($query, string $pillar)
    {
        return $query->where('pillar', $pillar);
    }

    /**
     * Scope: filter by status.
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
