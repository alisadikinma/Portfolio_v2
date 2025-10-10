<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PostTranslation Model
 *
 * Handles translations for Post model
 * Supports multiple languages (en, id, etc.)
 */
class PostTranslation extends Model
{
    protected $table = 'post_translations';

    protected $fillable = [
        'post_id',
        'language',
        'title',
        'slug',
        'excerpt',
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

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function scopeForLanguage($query, string $language)
    {
        return $query->where('language', $language);
    }
}
