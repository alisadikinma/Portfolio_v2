<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProjectTranslation Model
 *
 * Handles translations for Project model
 * Supports multiple languages (en, id, etc.)
 */
class ProjectTranslation extends Model
{
    protected $table = 'project_translations';

    protected $fillable = [
        'project_id',
        'language',
        'title',
        'slug',
        'description',
        'content',
        'meta_title',
        'meta_description',
        'og_title',
        'og_description',
        'canonical_url',
        'ai_summary',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function scopeForLanguage($query, string $language)
    {
        return $query->where('language', $language);
    }
}
