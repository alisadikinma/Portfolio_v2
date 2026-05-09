<?php

namespace App\Services;

use App\Enums\ThreadsPostStatus;
use App\Models\ThreadsPost;
use Illuminate\Support\Facades\Log;

/**
 * Threads cross-post caption authoring (May 10, 2026 ship).
 *
 * Tier-2 platform — pure reuse, NO plugin call (saves /instagram-gen SSH
 * spend per draft). Trade-off: caption is a Threads-tone-transformed
 * LinkedIn caption, not a fresh first-line-hook authored by an LLM.
 * Acceptable for v1 since:
 *   - Threads sweet spot 280-500 char (LinkedIn 1100-1300 truncates cleanly)
 *   - Threads minimal hashtag culture (1-3) — strip LinkedIn's 5+
 *   - Threads carousel reuses same 4:5 PNGs from /carousel-gen (already rendered)
 *
 * If engagement data later justifies a dedicated /threads-gen plugin, this
 * service can be swapped for an LLM-call version mirroring InstagramGenerationService.
 *
 * FSM:
 *   PendingGeneration|Failed|Cancelled → Generating → AwaitingReview
 *                                                ↘ Failed (any error)
 */
class ThreadsGenerationService
{
    public function __construct(
        private readonly PipelineGuard $guard,
    ) {
    }

    /**
     * @return array{success: bool, draft_id: int, status: string, error?: string|null}
     */
    public function generate(ThreadsPost $draft): array
    {
        if ($draft->status === ThreadsPostStatus::Generating->value) {
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => $draft->status,
                'error' => "Draft already in-flight (status={$draft->status})",
            ];
        }

        try {
            $this->guard->advance($draft, ThreadsPostStatus::Generating, 'plugin_dispatch_start');
        } catch (\App\Exceptions\InvalidStateTransitionException $e) {
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => $draft->status,
                'error' => $e->getMessage(),
            ];
        }

        if (!$draft->relationLoaded('linkedinPost')) {
            $draft->loadMissing('linkedinPost');
        }
        $linkedinPost = $draft->linkedinPost;
        if ($linkedinPost === null) {
            $this->markFailed($draft, 'Source LinkedIn post missing');
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => ThreadsPostStatus::Failed->value,
                'error' => 'Source missing',
            ];
        }

        $sourceContent = trim((string) $linkedinPost->content);
        if ($sourceContent === '') {
            $this->markFailed($draft, 'Source LinkedIn content is empty');
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => ThreadsPostStatus::Failed->value,
                'error' => 'Empty source',
            ];
        }

        $caption = $this->fitForThreads($sourceContent);

        $hashtags = is_array($linkedinPost->hashtags ?? null)
            ? array_slice(array_values(array_map('strval', $linkedinPost->hashtags)), 0, 3)
            : [];

        $title = trim((string) $linkedinPost->title);
        if ($title === '') {
            $title = $this->extractFirstLine($caption);
        }

        $draft->update([
            'title' => mb_substr($title, 0, 200),
            'caption' => $caption,
            'hashtags' => $hashtags,
        ]);

        try {
            $this->guard->advance(
                $draft,
                ThreadsPostStatus::AwaitingReview,
                'reuse_complete',
                ['source' => 'linkedin_post.content', 'format' => $draft->format]
            );
        } catch (\App\Exceptions\InvalidStateTransitionException $e) {
            Log::error('[ThreadsGenerationService] FSM transition failed', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'draft_id' => $draft->id,
                'status' => $draft->status,
            ];
        }

        $this->maybeCascadeToPublisher($draft);

        return [
            'success' => true,
            'draft_id' => $draft->id,
            'status' => $draft->fresh()->status ?? ThreadsPostStatus::AwaitingReview->value,
        ];
    }

    /**
     * Inline cascade — Threads service doesn't extend BaseSocialGenerationService
     * (no plugin invocation needed) so we replicate the publish-all check
     * locally. Same behavior: when parent LinkedIn has auto_approve_cross_posts
     * set, advance AwaitingReview → Publishing + dispatch Publer. Non-fatal.
     */
    private function maybeCascadeToPublisher(ThreadsPost $draft): void
    {
        try {
            if (!$draft->relationLoaded('linkedinPost')) {
                $draft->loadMissing('linkedinPost');
            }
            $linkedinPost = $draft->linkedinPost;
            if ($linkedinPost === null || !$linkedinPost->shouldAutoApproveCrossPosts()) {
                return;
            }

            $this->guard->advance(
                $draft,
                ThreadsPostStatus::Publishing,
                'auto_approve_cascade',
                ['source' => 'linkedin_publish_all_flag']
            );

            \App\Jobs\PublishViaPubler::dispatch(ThreadsPost::class, $draft->id);

            Log::info('[ThreadsGenerationService] Cascade promoted to Publishing', [
                'draft_id' => $draft->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[ThreadsGenerationService] Cascade promotion failed (non-fatal)', [
                'draft_id' => $draft->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Threads sweet spot 280-500 char. Hard cap 1000.
     * Strategy: take first paragraph (typically the hook + setup),
     * truncate at sentence boundary nearest to 480 char.
     */
    private function fitForThreads(string $content): string
    {
        $content = trim($content);
        if (mb_strlen($content) <= 500) {
            return $content;
        }

        // Keep up to first ~480 char, prefer to break at sentence terminator.
        $window = mb_substr($content, 0, 500);
        $lastPeriod = max(
            mb_strrpos($window, '.'),
            mb_strrpos($window, '!'),
            mb_strrpos($window, '?'),
            mb_strrpos($window, "\n\n")
        );

        if ($lastPeriod !== false && $lastPeriod >= 200) {
            return rtrim(mb_substr($content, 0, $lastPeriod + 1));
        }

        // No good break — hard truncate with ellipsis
        return rtrim(mb_substr($content, 0, 497)) . '...';
    }

    private function extractFirstLine(string $text): string
    {
        $first = trim(strtok($text, "\n"));
        return $first !== '' ? $first : '';
    }

    private function markFailed(ThreadsPost $draft, string $reason): void
    {
        try {
            $draft->update(['last_error' => $reason]);
            $this->guard->advance($draft, ThreadsPostStatus::Failed, 'generation_failed', ['reason' => $reason]);
        } catch (\Throwable $e) {
            Log::error('[ThreadsGenerationService] markFailed transition failed', [
                'draft_id' => $draft->id,
                'reason' => $reason,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
