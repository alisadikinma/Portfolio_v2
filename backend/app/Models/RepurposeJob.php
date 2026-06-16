<?php

namespace App\Models;

use App\Enums\LinkedInPostStatus;
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

    /** Threads hard caption cap (mirrors ZernioPayloadBuilder::THREADS_CHAR_LIMIT). */
    public const THREADS_CAPTION_LIMIT = 500;

    /**
     * Resolve the caption used for a platform's Zernio video-carousel publish.
     * The editor AND the publisher both call this so what the operator edits is
     * exactly what ships. Resolution (always non-empty):
     *   rewritten["caption_$platform"] → rewritten['caption'] → igCaption()
     */
    public function captionFor(string $platform): string
    {
        $perPlatform = trim((string) ($this->rewritten["caption_{$platform}"] ?? ''));
        if ($perPlatform !== '') {
            return $perPlatform;
        }
        $branded = trim((string) ($this->rewritten['caption'] ?? ''));

        return $branded !== '' ? $branded : $this->igCaption();
    }

    /**
     * Persist a per-platform caption onto rewritten["caption_$platform"]. Threads
     * is hard-capped at 500 chars (the platform limit) so the stored value never
     * diverges from what Zernio will accept. Saves immediately.
     */
    public function setCaption(string $platform, string $text): void
    {
        $text = trim($text);
        if ($platform === 'threads') {
            $text = mb_substr($text, 0, self::THREADS_CAPTION_LIMIT);
        }
        $this->update(['rewritten' => array_merge((array) $this->rewritten, ["caption_{$platform}" => $text])]);
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
     * Resolve the linked video_carousel calendar anchor (a LinkedInPost), or null
     * when this job has no anchor / the anchor isn't a video_carousel row.
     */
    public function videoAnchor(): ?LinkedInPost
    {
        if (! $this->linkedin_post_id) {
            return null;
        }
        $anchor = $this->linkedinPost()->first();

        return $anchor && $anchor->isVideoCarousel() ? $anchor : null;
    }

    /**
     * Mirror a Zernio schedule / publish-now action onto the video_carousel anchor so
     * it lands on the LinkedIn-tab calendar grid. The publisher guard
     * (scopeExcludeVideoCarousel) keeps awaiting_publish inert for LinkedIn — Zernio is
     * the only real publisher.
     */
    public function mirrorAnchorScheduled(?\Illuminate\Support\Carbon $scheduledFor): void
    {
        if ($this->mode !== 'video_rebrand') {
            return;
        }
        // Lazily materialize the anchor so a job finalized BEFORE this feature
        // shipped (linkedin_post_id NULL) still lands on the calendar when
        // scheduled, instead of the schedule silently skipping the calendar.
        $anchor = app(\App\Services\VideoCarouselAnchorService::class)->ensureFor($this);
        $when = $scheduledFor ?? now();

        if ($anchor->status === LinkedInPostStatus::ManualReview->value) {
            $anchor->transitionTo(LinkedInPostStatus::AwaitingPublish, 'zernio_scheduled', [
                'scheduled_at' => $when,
                'cancel_window_ends_at' => $when,
            ]);
        } else {
            // Already awaiting_publish (re-schedule) — just move the pin date.
            $anchor->update(['scheduled_at' => $when, 'cancel_window_ends_at' => $when]);
        }
    }

    /**
     * Flip the anchor to published only once EVERY dispatched Zernio platform has
     * published (the keys in zernio_publish ARE the dispatched platforms). A still-
     * pending/scheduled/failed platform keeps the anchor un-published. Idempotent.
     */
    public function mirrorAnchorPublishedIfComplete(): void
    {
        if ($this->mode !== 'video_rebrand') {
            return;
        }
        $anchor = app(\App\Services\VideoCarouselAnchorService::class)->ensureFor($this);
        if ($anchor->status === LinkedInPostStatus::Published->value) {
            return;
        }
        $states = $this->zernio_publish ?? [];
        if ($states === [] || ! collect($states)->every(fn ($s) => ($s['status'] ?? null) === 'published')) {
            return;
        }
        // Bridge a publish-now anchor still in manual_review through awaiting_publish
        // (published is only reachable from awaiting_publish in the FSM).
        if ($anchor->status === LinkedInPostStatus::ManualReview->value) {
            $anchor->transitionTo(LinkedInPostStatus::AwaitingPublish, 'zernio_published_bridge');
        }
        $anchor->transitionTo(LinkedInPostStatus::Published, 'zernio_published', [
            'published_at' => now(),
        ]);
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
