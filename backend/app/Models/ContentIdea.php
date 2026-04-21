<?php

namespace App\Models;

use App\Traits\HasStatusTransitions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentIdea extends Model
{
    use HasFactory;
    use HasStatusTransitions;

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
        'virality_score',
        'virality_breakdown',
        'mechanical_scores_snapshot',
        'research_tier_override',
        'result_post_id',
        'generated_article',
        'generated_images',
        'image_instructions',
        'image_references',
        'progress_percentage',
        'current_step',
        'progress_log',
        'process_pid',
        'auto_mode',
        'scheduled_at',
        'pipeline_attempts',
        'pipeline_last_attempt_at',
        'pipeline_next_retry_at',
        'pipeline_failed_stage',
        'pending_manifest',
        'pipeline_state_log',
        'translation_attempts_auto',
        'translation_ready_at',
    ];

    protected $casts = [
        'tags' => 'array',
        'auto_mode' => 'boolean',
        'scheduled_at' => 'datetime',
        'languages' => 'array',
        'output_types' => 'array',
        'research_data' => 'array',
        'workflows' => 'array',
        'source_data' => 'array',
        'virality_breakdown' => 'array',
        'mechanical_scores_snapshot' => 'array',
        'generated_article' => 'array',
        'generated_images' => 'array',
        'image_references' => 'array',
        'progress_log' => 'array',
        'pipeline_last_attempt_at' => 'datetime',
        'pipeline_next_retry_at' => 'datetime',
        'pending_manifest' => 'array',
        'pipeline_state_log' => 'array',
        'translation_ready_at' => 'datetime',
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

    /**
     * The Post created when this idea was published (if any).
     * Set in ContentPublishService::publish via result_post_id FK.
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'result_post_id');
    }
}
