<?php

namespace App\Services;

use App\Models\ContentIdea;
use App\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Advances auto_mode ContentIdea instances through the publication pipeline
 * one stage per tick. Strict sequential gating: only touches the queue when
 * no idea is mid-async-stage (researching / generating_images).
 *
 * Called by the content:auto-pipeline artisan command every minute.
 *
 * Stages (priority order):
 *   1. Retry failed ideas whose next_retry_at has arrived
 *   2. draft + auto_mode → trigger /article-prep (SSH to VPS)
 *   3. article_ready → trigger image generation batch
 *   4. completed + not-yet-published → call ContentPublishService + Telegram
 *
 * Timeout detection:
 *   - researching > 15min without updated_at change → flip to failed
 *   - generating_images > 10min without updated_at change → flip to failed
 */
class AutoPipelineOrchestrator
{
    private const MAX_ATTEMPTS = 3;
    private const RETRY_DELAY_MINUTES = 5;
    private const RESEARCH_TIMEOUT_MINUTES = 15;
    private const IMAGES_TIMEOUT_MINUTES = 10;

    public function __construct(
        private ArticleGenerationService $articleGen,
        private ImageGenerationService $imageGen,
        private ContentPublishService $publishService,
        private TelegramNotifier $telegram
    ) {
    }

    /**
     * Execute one tick of the pipeline. Returns the advanced idea (or null if no-op).
     *
     * Gates short-circuit in this order: operating hours → stuck detection → in-flight check.
     */
    public function tick(): ?ContentIdea
    {
        if (!$this->isWithinOperatingWindow()) {
            return null;
        }

        // Stuck-detection runs BEFORE in-flight check so timed-out ideas get
        // flipped to failed, freeing the in-flight slot for the next tick.
        $this->detectAndFlipStuckIdeas();

        if ($this->hasInFlightIdea()) {
            return null;
        }

        // Priority 1: retry a failed idea whose delay has elapsed
        $retried = $this->retryDueFailed();
        if ($retried) return $retried;

        // Priority 2: start research on a draft idea
        $started = $this->startNextDraft();
        if ($started) return $started;

        // Priority 3: advance article_ready → image generation
        $imaged = $this->dispatchImagesForReady();
        if ($imaged) return $imaged;

        // Priority 4: publish a completed idea not yet published
        $published = $this->publishReady();
        if ($published) return $published;

        return null;
    }

    private function isWithinOperatingWindow(): bool
    {
        $tz = config('content.auto_timezone', 'Asia/Jakarta');
        $start = (int) config('content.auto_window_start', 6);
        $end = (int) config('content.auto_window_end', 22);

        $now = Carbon::now($tz);
        $hour = (int) $now->format('G');

        return $hour >= $start && $hour < $end;
    }

    private function hasInFlightIdea(): bool
    {
        return ContentIdea::where('auto_mode', true)
            ->whereIn('status', ['researching', 'generating_images'])
            ->exists();
    }

    private function detectAndFlipStuckIdeas(): void
    {
        // researching stuck
        $stuckResearch = ContentIdea::where('auto_mode', true)
            ->where('status', 'researching')
            ->where('updated_at', '<=', now()->subMinutes(self::RESEARCH_TIMEOUT_MINUTES))
            ->get();

        foreach ($stuckResearch as $idea) {
            $this->markFailed($idea, 'research', 'Timed out after ' . self::RESEARCH_TIMEOUT_MINUTES . 'min');
        }

        // generating_images stuck
        $stuckImages = ContentIdea::where('auto_mode', true)
            ->where('status', 'generating_images')
            ->where('updated_at', '<=', now()->subMinutes(self::IMAGES_TIMEOUT_MINUTES))
            ->get();

        foreach ($stuckImages as $idea) {
            $this->markFailed($idea, 'images', 'Timed out after ' . self::IMAGES_TIMEOUT_MINUTES . 'min');
        }
    }

    private function retryDueFailed(): ?ContentIdea
    {
        $idea = ContentIdea::where('auto_mode', true)
            ->where('status', 'failed')
            ->where('pipeline_attempts', '<', self::MAX_ATTEMPTS)
            ->whereNotNull('pipeline_next_retry_at')
            ->where('pipeline_next_retry_at', '<=', now())
            ->orderBy('pipeline_next_retry_at')
            ->first();

        if (!$idea) return null;

        Log::info("[AutoPipeline] Retrying idea #{$idea->id} stage={$idea->pipeline_failed_stage} attempt=" . ($idea->pipeline_attempts + 1));

        // Resume from the exact sub-step that failed — don't restart from scratch
        return $this->resumeIdea($idea);
    }

    /**
     * Resume a stuck/failed idea from the last incomplete sub-step.
     * Inspects generated_article data to decide which trigger to invoke:
     *   - no prep_data          → re-run prep (Steps 1-3)
     *   - no title/content      → re-run write (Step 4)
     *   - article_ready + no images → dispatch images
     *   - completed + no post   → publish
     *   - otherwise             → re-run score (Step 5)
     *
     * Callable from admin "Resume" endpoint (manual ideas) and from
     * retryDueFailed (auto-mode ideas). Clears failed state and schedules
     * the next step directly.
     */
    public function resumeIdea(ContentIdea $idea): ContentIdea
    {
        $article = $idea->generated_article ?? [];
        $hasPrep = !empty(data_get($article, 'prep_data.outline'));
        $hasTitle = !empty(data_get($article, 'title'));
        $hasContent = !empty(data_get($article, 'content'));
        $hasScored = !empty(data_get($article, 'combined_score')) || !empty(data_get($article, 'virality_score'));

        // Clear failed scheduling before dispatching
        $idea->update([
            'pipeline_next_retry_at' => null,
            'pipeline_failed_stage' => null,
        ]);

        try {
            if (!$hasPrep) {
                return $this->resumeAtPrep($idea);
            }
            if (!$hasTitle || !$hasContent) {
                return $this->resumeAtWrite($idea);
            }
            if (!$hasScored && $idea->status !== 'article_ready' && $idea->status !== 'completed') {
                return $this->resumeAtScore($idea);
            }
            if ($idea->status === 'article_ready') {
                // Images stage
                $dispatched = $this->imageGen->triggerForIdea($idea);
                $idea->update([
                    'status' => $dispatched > 0 ? 'generating_images' : 'completed',
                    'pipeline_last_attempt_at' => now(),
                ]);
                Log::info("[AutoPipeline] Resume: idea #{$idea->id} dispatched {$dispatched} image jobs");
                return $idea;
            }
            if ($idea->status === 'completed' && !Post::where('source_idea_id', $idea->id)->exists()) {
                $result = $this->publishService->publish($idea);
                $this->telegram->notifyPublishSuccess($result['post']);
                Log::info("[AutoPipeline] Resume: idea #{$idea->id} published");
                return $idea;
            }
            // Fallback: article data exists + status unclear → retry score
            return $this->resumeAtScore($idea);
        } catch (\Throwable $e) {
            Log::error("[AutoPipeline] Resume failed for idea #{$idea->id}: " . $e->getMessage());
            throw $e;
        }
    }

    private function resumeAtPrep(ContentIdea $idea): ContentIdea
    {
        $languages = $idea->languages ?? ['id'];
        $result = $this->articleGen->triggerPrep($idea->id, [
            'topic' => $idea->title,
            'languages' => $languages,
            'instructions' => $idea->instructions ?? '',
        ]);
        if (!($result['success'] ?? false)) {
            throw new \RuntimeException('triggerPrep returned unsuccessful: ' . ($result['error'] ?? 'unknown'));
        }
        $idea->update([
            'status' => 'researching',
            'progress_percentage' => 0,
            'current_step' => 'prep_resume',
            'process_pid' => $result['pid'] ?? null,
            'pipeline_last_attempt_at' => now(),
        ]);
        Log::info("[AutoPipeline] Resume: idea #{$idea->id} re-started at prep (pid={$result['pid']})");
        return $idea;
    }

    private function resumeAtWrite(ContentIdea $idea): ContentIdea
    {
        $result = $this->articleGen->triggerWrite($idea->id);
        if (!($result['success'] ?? false)) {
            throw new \RuntimeException('triggerWrite returned unsuccessful: ' . ($result['error'] ?? 'unknown'));
        }
        $idea->update([
            'status' => 'researching',
            'progress_percentage' => 50,
            'current_step' => 'write_resume',
            'process_pid' => $result['pid'] ?? null,
            'pipeline_last_attempt_at' => now(),
        ]);
        Log::info("[AutoPipeline] Resume: idea #{$idea->id} re-started at write (pid={$result['pid']})");
        return $idea;
    }

    private function resumeAtScore(ContentIdea $idea): ContentIdea
    {
        $result = $this->articleGen->triggerScore($idea->id);
        if (!($result['success'] ?? false)) {
            throw new \RuntimeException('triggerScore returned unsuccessful: ' . ($result['error'] ?? 'unknown'));
        }
        $idea->update([
            'status' => 'researching',
            'progress_percentage' => 85,
            'current_step' => 'score_resume',
            'process_pid' => $result['pid'] ?? null,
            'pipeline_last_attempt_at' => now(),
        ]);
        Log::info("[AutoPipeline] Resume: idea #{$idea->id} re-started at score (pid={$result['pid']})");
        return $idea;
    }

    private function startNextDraft(): ?ContentIdea
    {
        $idea = ContentIdea::where('auto_mode', true)
            ->where('status', 'draft')
            ->whereNull('scheduled_at')
            ->orderBy('created_at')
            ->first();

        if (!$idea) return null;

        try {
            $languages = $idea->languages ?? ['id'];
            $result = $this->articleGen->triggerPrep($idea->id, [
                'topic' => $idea->title,
                'languages' => $languages,
                'instructions' => $idea->instructions ?? '',
            ]);

            if (!($result['success'] ?? false)) {
                throw new \RuntimeException('triggerPrep returned unsuccessful: ' . ($result['error'] ?? 'unknown'));
            }

            $idea->update([
                'status' => 'researching',
                'progress_percentage' => 0,
                'current_step' => 'auto_pipeline_start',
                'process_pid' => $result['pid'] ?? null,
                'pipeline_last_attempt_at' => now(),
            ]);

            Log::info("[AutoPipeline] Started research for idea #{$idea->id} pid={$result['pid']}");
            return $idea;
        } catch (\Throwable $e) {
            $this->markFailed($idea, 'research', $e->getMessage());
            return $idea;
        }
    }

    private function dispatchImagesForReady(): ?ContentIdea
    {
        $idea = ContentIdea::where('auto_mode', true)
            ->where('status', 'article_ready')
            ->orderBy('updated_at')
            ->first();

        if (!$idea) return null;

        try {
            $dispatched = $this->imageGen->triggerForIdea($idea);
            if ($dispatched === 0) {
                // No prompts to dispatch but status was article_ready → skip to completed
                Log::warning("[AutoPipeline] Idea #{$idea->id} had no dispatchable prompts, advancing to completed");
                $idea->update(['status' => 'completed']);
                return $idea;
            }
            $idea->update(['pipeline_last_attempt_at' => now()]);
            Log::info("[AutoPipeline] Dispatched {$dispatched} image jobs for idea #{$idea->id}");
            return $idea;
        } catch (\Throwable $e) {
            $this->markFailed($idea, 'images', $e->getMessage());
            return $idea;
        }
    }

    private function publishReady(): ?ContentIdea
    {
        $idea = ContentIdea::where('auto_mode', true)
            ->where('status', 'completed')
            ->whereNull('result_post_id')
            ->orderBy('updated_at')
            ->first();

        if (!$idea) return null;

        // Double-check via posts.source_idea_id to avoid re-publishing on
        // older ideas where result_post_id was never populated.
        if (Post::where('source_idea_id', $idea->id)->exists()) {
            return null;
        }

        try {
            $result = $this->publishService->publish($idea);
            $post = $result['post'];

            // Fire Telegram success notification
            $this->telegram->notifyPublishSuccess($post);

            $idea->update([
                'pipeline_attempts' => 0,
                'pipeline_next_retry_at' => null,
                'pipeline_failed_stage' => null,
                'pipeline_last_attempt_at' => now(),
            ]);

            Log::info("[AutoPipeline] Published idea #{$idea->id} as post #{$post->id}");
            return $idea;
        } catch (\Throwable $e) {
            $this->markFailed($idea, 'publish', $e->getMessage());
            return $idea;
        }
    }

    private function markFailed(ContentIdea $idea, string $stage, string $errorMessage): void
    {
        $attempts = (int) ($idea->pipeline_attempts ?? 0) + 1;
        $exhausted = $attempts >= self::MAX_ATTEMPTS;

        $log = $idea->progress_log ?? [];
        $log[] = [
            'timestamp' => now()->toISOString(),
            'step' => 'failed',
            'percentage' => $idea->progress_percentage ?? 0,
            'message' => "[{$stage}] " . $errorMessage,
        ];

        $idea->update([
            'status' => 'failed',
            'pipeline_attempts' => $attempts,
            'pipeline_last_attempt_at' => now(),
            'pipeline_next_retry_at' => $exhausted ? null : now()->addMinutes(self::RETRY_DELAY_MINUTES),
            'pipeline_failed_stage' => $stage,
            'progress_log' => $log,
        ]);

        Log::warning("[AutoPipeline] Idea #{$idea->id} failed at {$stage} (attempt {$attempts}/" . self::MAX_ATTEMPTS . "): {$errorMessage}");

        if ($exhausted) {
            Log::error("[AutoPipeline] Idea #{$idea->id} exhausted retries — sending Telegram failure ping");
            try {
                $this->telegram->notifyFinalFailure($idea->fresh());
            } catch (\Throwable $e) {
                Log::warning('[AutoPipeline] Telegram failure notification threw: ' . $e->getMessage());
            }
        }
    }
}
