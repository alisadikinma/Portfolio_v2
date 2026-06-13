<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Postiz channel mapping (platform → integration_id), auto-synced from the
 * local poller. See docs/plans/2026-06-13-postiz-local-node-crosspost.md (Phase A / E).
 */
class PostizChannel extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform',
        'handle',
        'postiz_integration_id',
        'enabled',
        'last_synced_at',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    /**
     * Resolve the enabled Postiz integration id for a platform (first match).
     * Single-operator → 1:1; multi-account picks the first enabled mapping.
     */
    public static function integrationIdFor(string $platform): ?string
    {
        return static::query()
            ->where('platform', $platform)
            ->where('enabled', true)
            ->value('postiz_integration_id');
    }
}
