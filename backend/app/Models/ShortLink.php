<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Branded URL shortener row — `https://alisadikinma.com/r/{code}` redirects
 * to `target_url` (which already contains any UTM params).
 *
 * Created via `App\Services\ShortLinkService::forBlogPost($post, $platform)` —
 * idempotent per (post_id, source_platform) pair.
 */
class ShortLink extends Model
{
    protected $table = 'short_links';

    protected $fillable = [
        'code',
        'target_url',
        'post_id',
        'source_platform',
        'hits',
        'last_hit_at',
    ];

    protected $casts = [
        'hits' => 'integer',
        'last_hit_at' => 'datetime',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function shortUrl(): string
    {
        $base = rtrim((string) config('app.url', 'https://alisadikinma.com'), '/');
        return $base . '/r/' . $this->code;
    }
}
