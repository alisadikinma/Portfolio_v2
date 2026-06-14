<?php

namespace App\Models;

use App\Enums\RepurposeJobStatus;
use App\Traits\HasStatusTransitions;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// Same App\Models namespace, but imported explicitly for PSR-12 + static analysis.
use App\Models\ContentIdea;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\RepurposeVideoSlide;

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
        'asset_retry_count',
        'pipeline_state_log',
        'chat_id',
    ];

    protected $casts = [
        'extracted' => 'array',
        'research' => 'array',
        'rewritten' => 'array',
        'pipeline_state_log' => 'array',
        'asset_retry_count' => 'integer',
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
     * Per-slide rows for the video_rebrand mode (ordered by carousel position).
     */
    public function videoSlides(): HasMany
    {
        return $this->hasMany(RepurposeVideoSlide::class)->orderBy('slide_index');
    }

    /**
     * Human topic label for operator-facing notifications ("gak tau topik apa").
     * Priority: video_rebrand tool-slide header titles (the actual carousel topic)
     * → rewritten title → first non-empty source-caption line → source_url host.
     * Always returns a non-empty, ≤80-char string.
     */
    public function displayTopic(): string
    {
        if ($this->mode === 'video_rebrand') {
            $titles = $this->videoSlides()
                ->where('role', RepurposeVideoSlide::ROLE_TOOL)
                ->orderBy('slide_index')
                ->pluck('header_title')
                ->filter()
                ->implode(', ');
            if ($titles !== '') {
                return mb_substr($titles, 0, 80);
            }
        }

        $rewrittenTitle = trim((string) ($this->rewritten['title'] ?? ''));
        if ($rewrittenTitle !== '') {
            return mb_substr($rewrittenTitle, 0, 80);
        }

        $caption = (string) ($this->extracted['caption'] ?? '');
        foreach (preg_split('/\r\n|\r|\n/', $caption) as $line) {
            $line = trim($line);
            if ($line !== '') {
                return mb_substr($line, 0, 80);
            }
        }

        $host = parse_url((string) $this->source_url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : ('job #'.$this->id);
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
