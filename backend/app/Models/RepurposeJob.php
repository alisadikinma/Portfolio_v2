<?php

namespace App\Models;

use App\Enums\RepurposeJobStatus;
use App\Traits\HasStatusTransitions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// Same App\Models namespace, but imported explicitly for PSR-12 + static analysis.
use App\Models\ContentIdea;
use App\Models\LinkedInPost;
use App\Models\Post;

/**
 * IG repurpose pipeline job — see
 * docs/plans/2026-06-10-telegram-ig-repurpose-carousel.md (Phase 0).
 */
class RepurposeJob extends Model
{
    use HasFactory;
    use HasStatusTransitions;

    protected $fillable = [
        'source_url',
        'angle',
        'mode',
        'status',
        'slides_path',
        'extracted',
        'research',
        'rewritten',
        'content_idea_id',
        'linkedin_post_id',
        'anchor_post_id',
        'last_error',
        'pipeline_state_log',
        'chat_id',
    ];

    protected $casts = [
        'extracted' => 'array',
        'research' => 'array',
        'rewritten' => 'array',
        'pipeline_state_log' => 'array',
    ];

    protected function statusEnumClass(): string
    {
        return RepurposeJobStatus::class;
    }

    public function contentIdea(): BelongsTo
    {
        return $this->belongsTo(ContentIdea::class);
    }

    public function linkedinPost(): BelongsTo
    {
        return $this->belongsTo(LinkedInPost::class);
    }

    public function anchorPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'anchor_post_id');
    }
}
