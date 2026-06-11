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

    /**
     * Single source of truth: does this Post / LinkedIn draft originate from an
     * IG-repurpose job? Repurpose carousels anchor an UNPUBLISHED Post purely to
     * generate slides — that post's /blog/{slug} URL 404s, so NO platform should
     * emit a "Full article" first-comment for it (LinkedInPost::isRepurpose,
     * blogUrl(), and the IG/TikTok/Threads link_comment builders all gate on this).
     *
     * Matches the repurpose by either FK linkage (RepurposeJob.linkedin_post_id /
     * anchor_post_id) or a ContentIdea(source='instagram') anchoring the post.
     */
    public static function isRepurposePost(?int $postId, ?int $linkedinPostId = null): bool
    {
        if (!$postId && !$linkedinPostId) {
            return false;
        }

        $linked = static::query()
            ->where(function ($q) use ($postId, $linkedinPostId) {
                if ($linkedinPostId) {
                    $q->orWhere('linkedin_post_id', $linkedinPostId);
                }
                if ($postId) {
                    $q->orWhere('anchor_post_id', $postId);
                }
            })
            ->exists();

        if ($linked) {
            return true;
        }

        return (bool) ($postId && ContentIdea::query()
            ->where('result_post_id', $postId)
            ->where('source', 'instagram')
            ->exists());
    }
}
