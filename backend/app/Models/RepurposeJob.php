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
        'zernio_publish',
    ];

    protected $casts = [
        'extracted' => 'array',
        'research' => 'array',
        'rewritten' => 'array',
        'pipeline_state_log' => 'array',
        'asset_retry_count' => 'integer',
        'zernio_publish' => 'array',
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
     * Title text for the video_rebrand hook overlay, sourced from the ORIGINAL IG
     * carousel ("dari IG source asli"): the captured source-hook headline
     * (preserved by VideoSlideExtractor before its row is dropped), falling back to
     * the first non-empty line of the IG caption. Empty string → render no title
     * (hook ships as a plain clip).
     */
    public function videoHookTitle(): string
    {
        $fromHook = trim((string) ($this->extracted['source_hook_title'] ?? ''));
        if ($fromHook !== '') {
            return mb_substr($fromHook, 0, 90);
        }

        $caption = (string) ($this->extracted['caption'] ?? '');
        foreach (preg_split('/\r\n|\r|\n/', $caption) as $line) {
            $line = trim($line);
            if ($line !== '') {
                return mb_substr($line, 0, 90);
            }
        }

        return '';
    }

    /**
     * Ordered public MP4 URLs of the composited video slides — the mediaItems a
     * Zernio video carousel publishes. Only `done` clips with a stored path,
     * ordered by slide_index (hook → tools → cta).
     *
     * @return array<int,string>
     */
    public function compositedVideoUrls(): array
    {
        return $this->videoSlides()
            ->where('composited_status', 'done')
            ->whereNotNull('composited_path')
            ->orderBy('slide_index')
            ->pluck('composited_path')
            ->filter(fn ($u) => is_string($u) && $u !== '')
            ->values()
            ->all();
    }

    /**
     * Caption for the cross-posted video carousel. Prefers the source IG caption,
     * falls back to the topic label so the post never ships caption-less.
     */
    public function igCaption(): string
    {
        $caption = trim((string) ($this->extracted['caption'] ?? ''));

        return $caption !== '' ? $caption : $this->displayTopic();
    }

    /**
     * Per-platform Zernio publish state, or null if this platform was never
     * dispatched. See migration 2026_06_15_000002 for the entry shape.
     *
     * @return array<string,mixed>|null
     */
    public function zernioPublishState(string $platform): ?array
    {
        return ($this->zernio_publish ?? [])[$platform] ?? null;
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
