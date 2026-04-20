<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EntityReference extends Model
{
    use HasFactory;

    protected $fillable = [
        'qid',
        'name',
        'entity_type',
        'local_path',
        'local_url',
        'wikimedia_source_url',
        'license',
        'attribution',
        'source',
        'fetched_at',
        'last_used_at',
        'use_count',
        'refresh_after',
    ];

    protected $casts = [
        'fetched_at' => 'datetime',
        'last_used_at' => 'datetime',
        'refresh_after' => 'datetime',
        'use_count' => 'integer',
    ];

    // DB default is 1 via migration, but Eloquent doesn't hydrate defaults
    // back onto a freshly-created model unless you refresh(). Set the attribute
    // default here so $ref->use_count is 1 immediately after create() — matches
    // what the DB would return on next read.
    protected $attributes = [
        'use_count' => 1,
        'source' => 'wikimedia',
    ];

    /**
     * Bump use_count and stamp last_used_at. Called on cache hit during
     * EntityReferenceService::findOrFetch so we can track most-used entities
     * for future admin curation.
     */
    public function incrementUseCount(): void
    {
        $this->increment('use_count');
        $this->update(['last_used_at' => now()]);
    }
}
