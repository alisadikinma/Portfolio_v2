<?php

namespace App\Models;

use Database\Factories\ScheduledCommandFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Phase A — admin scheduler tab foundation.
 *
 * One row per artisan command (or placeholder slot) the operator
 * can toggle/edit from /admin/scheduler. Mirrors what the kernel
 * hook will read in Phase B to materialize the schedule.
 *
 * @property int $id
 * @property string $signature
 * @property string $display_name
 * @property string|null $description
 * @property string $category
 * @property string $cron_expression
 * @property string $timezone
 * @property bool $enabled
 * @property bool $is_placeholder
 * @property int|null $without_overlapping_minutes
 * @property bool $run_in_background
 * @property array|null $arguments
 * @property \Illuminate\Support\Carbon|null $last_run_at
 * @property \Illuminate\Support\Carbon|null $last_finished_at
 * @property string $last_status
 * @property int|null $last_duration_ms
 * @property string|null $last_error
 * @property int $sort_order
 */
class ScheduledCommand extends Model
{
    use HasFactory;

    protected $fillable = [
        'signature',
        'display_name',
        'description',
        'category',
        'cron_expression',
        'timezone',
        'enabled',
        'is_placeholder',
        'without_overlapping_minutes',
        'run_in_background',
        'arguments',
        'last_run_at',
        'last_finished_at',
        'last_status',
        'last_duration_ms',
        'last_error',
        'sort_order',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'is_placeholder' => 'boolean',
        'run_in_background' => 'boolean',
        'arguments' => 'array',
        'last_run_at' => 'datetime',
        'last_finished_at' => 'datetime',
    ];

    /**
     * Only enabled commands (operator hasn't toggled OFF).
     */
    public function scopeEnabled($query)
    {
        return $query->where('enabled', true);
    }

    /**
     * Only real commands; excludes placeholder slots reserved
     * for future skills (LinkedIn → IG → TikTok roadmap).
     */
    public function scopeNotPlaceholder($query)
    {
        return $query->where('is_placeholder', false);
    }

    /**
     * Filter to a single category bucket (e.g. 'linkedin').
     */
    public function scopeForCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    protected static function newFactory(): ScheduledCommandFactory
    {
        return ScheduledCommandFactory::new();
    }
}
