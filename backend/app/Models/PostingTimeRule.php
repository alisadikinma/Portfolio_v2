<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Per docs/plans/2026-05-06-linkedin-calendar-and-ai-time-rules.md §5.
 *
 * AI-researched posting time rules — one row per (platform, day_of_week, hour,
 * audience, timezone). Seeded via `posting-rules:research` artisan command.
 * Read by the calendar heatmap (PostingRuleController@index) and the conflict
 * check endpoint (LinkedInDraftController@checkConflict suggested_alternatives).
 */
class PostingTimeRule extends Model
{
    protected $fillable = [
        'platform',
        'day_of_week',
        'hour',
        'timezone',
        'score',
        'audience',
        'source_url',
        'source_title',
        'notes',
        'last_researched_at',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'hour' => 'integer',
        'score' => 'integer',
        'last_researched_at' => 'datetime',
    ];

    public function scopeForPlatform(Builder $query, string $platform): Builder
    {
        return $query->where('platform', $platform);
    }

    public function scopeForAudience(Builder $query, string $audience): Builder
    {
        return $query->where('audience', $audience);
    }

    /** Rules whose score >= threshold (default 80 = "optimal" tier). */
    public function scopeOptimal(Builder $query, int $minScore = 80): Builder
    {
        return $query->where('score', '>=', $minScore);
    }

    public function scopeForDayOfWeek(Builder $query, int $dow): Builder
    {
        return $query->where('day_of_week', $dow);
    }
}
