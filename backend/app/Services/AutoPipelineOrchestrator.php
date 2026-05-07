<?php

namespace App\Services;

use App\Enums\ContentIdeaStatus;
use App\Models\ContentIdea;
use App\Models\Post;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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
    private const MAX_TRANSLATE_ATTEMPTS_AUTO = 3;
    private const LAST_START_CACHE_KEY = 'auto_pipeline:last_start_at';

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

        // Priority 1 (ALWAYS — not gated by in-flight): publish a completed
        // idea. Publishing is a local DB write + Telegram ping — no SSH, no
        // shared compute. Running it concurrently with a researching idea is
        // safe and prevents completed articles from stalling in the queue
        // while the pipeline works on the next one.
        $published = $this->publishReady();
        if ($published) return $published;

        // Remaining priorities compete for the single SSH/GeminiGen slot.
        if ($this->hasInFlightIdea()) {
            return null;
        }

        // Priority 2: retry a failed idea whose delay has elapsed
        $retried = $this->retryDueFailed();
        if ($retried) return $retried;

        // Priority 3: start research on a draft idea
        $started = $this->startNextDraft();
        if ($started) return $started;

        // Priority 4: advance article_ready → image generation
        $imaged = $this->dispatchImagesForReady();
        if ($imaged) return $imaged;

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
                if ($dispatched === 0) {
                    // No prompts → skip straight to completed (edge path)
                    $idea->transitionTo(ContentIdeaStatus::Completed, 'resume_no_images', [
                        'pipeline_last_attempt_at' => now(),
                    ]);
                }
                // When dispatched > 0, triggerForIdea already flipped status to
                // generating_images; just stamp the last_attempt timestamp.
                if ($dispatched > 0) {
                    $idea->update(['pipeline_last_attempt_at' => now()]);
                }
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
        $languages = $idea->languages ?? ['id', 'en'];
        $result = $this->articleGen->triggerPrep($idea->id, [
            'topic' => $idea->title,
            'languages' => $languages,
            'instructions' => $idea->instructions ?? '',
        ]);
        if (!($result['success'] ?? false)) {
            throw new \RuntimeException('triggerPrep returned unsuccessful: ' . ($result['error'] ?? 'unknown'));
        }
        $idea->transitionTo(ContentIdeaStatus::Researching, 'resume_prep', [
            'languages' => $languages,
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
        $idea->transitionTo(ContentIdeaStatus::Researching, 'resume_write', [
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
        $idea->transitionTo(ContentIdeaStatus::Researching, 'resume_score', [
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
        // Throttle: enforce minimum gap between article starts. Keeps the
        // blog feed from being flooded with back-to-back posts. Cache key
        // is stamped after every successful start below.
        $intervalMinutes = (int) config('content.auto_start_interval_minutes', 30);
        if ($intervalMinutes > 0) {
            $lastStart = Cache::get(self::LAST_START_CACHE_KEY);
            if ($lastStart instanceof Carbon) {
                $elapsed = $lastStart->diffInMinutes(now());
                if ($elapsed < $intervalMinutes) {
                    return null;
                }
            }
        }

        // Priority: match the "Virality" badge shown in the admin Content
        // Engine UI, which is source_data.display_score = composite_score +
        // heat_boost + tier_boost. Sorting by the raw virality_score column
        // would diverge from UI order (tier-1 publishers get +5 boost).
        //
        // Order: display_score DESC → virality_score DESC → created_at ASC.
        // COALESCE handles legacy ideas without source_data.display_score
        // (fall back to the column). MySQL DESC sorts NULL last naturally.
        // SQLite (test-only) lacks JSON_UNQUOTE — fall back to the column
        // sort. Production always runs MySQL.
        $query = ContentIdea::where('auto_mode', true)
            ->where('status', 'draft')
            ->whereNull('scheduled_at');

        if (\Illuminate\Support\Facades\DB::connection()->getDriverName() === 'mysql') {
            $query->orderByRaw("COALESCE(CAST(JSON_UNQUOTE(JSON_EXTRACT(source_data, '$.display_score')) AS UNSIGNED), virality_score, 0) DESC");
        }

        $idea = $query->orderBy('virality_score', 'desc')
            ->orderBy('created_at')
            ->first();

        if (!$idea) return null;

        try {
            $languages = $idea->languages ?? ['id', 'en'];
            $result = $this->articleGen->triggerPrep($idea->id, [
                'topic' => $idea->title,
                'languages' => $languages,
                'instructions' => $idea->instructions ?? '',
            ]);

            if (!($result['success'] ?? false)) {
                throw new \RuntimeException('triggerPrep returned unsuccessful: ' . ($result['error'] ?? 'unknown'));
            }

            $idea->transitionTo(ContentIdeaStatus::Researching, 'auto_pipeline_start', [
                'languages' => $languages,
                'progress_percentage' => 0,
                'current_step' => 'auto_pipeline_start',
                'process_pid' => $result['pid'] ?? null,
                'pipeline_last_attempt_at' => now(),
            ]);

            // Stamp the throttle gate so the next startNextDraft() tick knows
            // the interval timer. 7-day TTL is effectively forever for this
            // use case; the value is overwritten on the next successful start.
            Cache::put(self::LAST_START_CACHE_KEY, now(), now()->addDays(7));

            Log::info("[AutoPipeline] Started research for idea #{$idea->id} pid={$result['pid']}");

            // Fire Telegram start notification — gives operator visibility on
            // which topic just entered the pipeline. Swallow errors so notification
            // infra never blocks pipeline progression.
            try {
                $this->telegram->notifyPipelineStart($idea->fresh());
            } catch (\Throwable $e) {
                Log::warning('[AutoPipeline] Telegram start notification threw: ' . $e->getMessage());
            }

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
                $idea->transitionTo(ContentIdeaStatus::Completed, 'no_images_to_dispatch');
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

        // Phase C gate: ensure translation is present (or waived after
        // 3 failed attempts) before committing to the publish path.
        if (!$this->ensureTranslationBeforePublish($idea)) {
            return null; // still working — try again next tick
        }

        try {
            $result = $this->publishService->publish($idea);
            $post = $result['post'];

            // Fire Telegram success notification
            $this->telegram->notifyPublishSuccess($post);

            // Gate Completed transition on translation readiness. When
            // use_translate_phase is on, publish() deliberately leaves the
            // idea at images_ready so the translation-complete callback /
            // exhausted fallback can make the "truly done" call. Here in
            // the orchestrator path, if we reached this branch then
            // ensureTranslationBeforePublish already stamped
            // translation_ready_at (either because EN landed or because
            // 3-attempt cap exhausted) — so flipping Completed now is
            // semantically correct. Guard against already-completed to
            // keep the transition idempotent.
            $idea->refresh();
            if ($idea->status !== 'completed') {
                $currentEnum = ContentIdeaStatus::from($idea->status);
                if ($currentEnum->canTransitionTo(ContentIdeaStatus::Completed)) {
                    $idea->transitionTo(
                        ContentIdeaStatus::Completed,
                        'auto_pipeline_publish',
                        ['result_post_id' => $post->id]
                    );
                }
            }

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

    /**
     * Phase C translate-before-publish gate. Returns true when the idea is
     * ready to publish (EN content populated, or cap reached and we've
     * degraded gracefully). Returns false to defer publish to next tick.
     *
     * Branches:
     *  - flag off              → return true (no gating)
     *  - en content already    → stamp translation_ready_at, return true
     *  - attempts ≥ cap (3)    → dispatch Telegram alert, set sentinel,
     *                            return true (publish monolingual)
     *  - otherwise             → call preflight (sync SSH ~30-60s). On
     *                            success return true, on failure increment
     *                            attempts and return false (retry next tick)
     */
    private function ensureTranslationBeforePublish(ContentIdea $idea): bool
    {
        if (!config('services.article_generation.use_translate_phase', false)) {
            return true;
        }

        $article = $idea->generated_article ?? [];
        $primaryLang = $article['language'] ?? 'id';
        $targetLang = $primaryLang === 'id' ? 'en' : 'id';

        if (!empty($article[$targetLang]['content'] ?? '')) {
            if (!$idea->translation_ready_at) {
                $idea->update(['translation_ready_at' => now()]);
            }
            return true;
        }

        $attempts = (int) ($idea->translation_attempts_auto ?? 0);
        if ($attempts >= self::MAX_TRANSLATE_ATTEMPTS_AUTO) {
            Log::warning("[AutoPipeline] Idea #{$idea->id} exhausted auto-translate retries ({$attempts}), publishing monolingual");

            \App\Jobs\DispatchTelegramNotification::dispatch($idea->id, 'auto_translate_exhausted');
            $idea->update(['translation_ready_at' => now()]);
            return true;
        }

        // Cache-lock guards against overlapping cron ticks: the translate
        // SSH call runs ~30-60s, and if the scheduler fires every minute,
        // a slow SSH can cause two ticks to enter this block simultaneously
        // on the same idea. Without the lock both ticks increment
        // translation_attempts_auto, prematurely exhausting the 3-attempt
        // cap after just 1 real attempt. Lock expires after 120s (> max
        // expected SSH duration) so a crashed process can't deadlock the
        // idea permanently.
        $lockKey = "auto_pipeline:translate_preflight:{$idea->id}";
        $lock = Cache::lock($lockKey, 120);

        if (!$lock->get()) {
            Log::info("[AutoPipeline] Idea #{$idea->id} translate preflight already running (lock held), skipping this tick");
            return false;
        }

        try {
            $result = $this->articleGen->triggerTranslatePreflight($idea);
            $idea->increment('translation_attempts_auto');
            $idea->update(['pipeline_last_attempt_at' => now()]);

            if (($result['success'] ?? false) === true) {
                Log::info("[AutoPipeline] Idea #{$idea->id} auto-translated (attempt " . $idea->fresh()->translation_attempts_auto . ')');
                $idea->update(['translation_ready_at' => now()]);
                return true;
            }

            Log::warning("[AutoPipeline] Auto-translate failed for idea #{$idea->id}: " . ($result['error'] ?? 'unknown'));
            return false;
        } catch (\Throwable $e) {
            Log::error("[AutoPipeline] Auto-translate threw for idea #{$idea->id}: " . $e->getMessage());
            $idea->increment('translation_attempts_auto');
            return false;
        } finally {
            $lock->release();
        }
    }

    private function markFailed(ContentIdea $idea, string $stage, string $errorMessage): void
    {
        $attempts = (int) ($idea->pipeline_attempts ?? 0) + 1;
        $exhausted = $attempts >= self::MAX_ATTEMPTS;

        // Phase D — classifier-driven retry decision. Layered ON TOP of the
        // existing pipeline_attempts budget: TRANSIENT / DETERMINISTIC_LLM
        // schedule a retry as before; POLICY_* / PERMANENT / UNKNOWN skip
        // the retry-schedule entirely and surface to the operator (auto would
        // refuse the same way next attempt — wasted Sonnet + SSH calls).
        $errorClass = (new PipelineErrorClassifier())->classify($errorMessage);
        $autoRetryEligible = in_array($errorClass, [
            \App\Enums\PipelineErrorClass::Transient,
            \App\Enums\PipelineErrorClass::DeterministicLlm,
        ], true);

        $log = $idea->progress_log ?? [];
        $log[] = [
            'timestamp' => now()->toISOString(),
            'step' => 'failed',
            'percentage' => $idea->progress_percentage ?? 0,
            'message' => "[{$stage}] " . $errorMessage,
        ];

        // pipeline_next_retry_at controls whether retryReadyIdeas() will pick
        // this row up. NULL = no retry; non-null timestamp = eligible at that
        // time. Compute based on BOTH the attempt budget AND the error class.
        $shouldScheduleRetry = !$exhausted && $autoRetryEligible;
        $nextRetryAt = $shouldScheduleRetry ? now()->addMinutes(self::RETRY_DELAY_MINUTES) : null;

        // Use direct update when already failed (self-transition not in map);
        // otherwise route through transitionTo so the log records the reason.
        $extra = [
            'pipeline_attempts' => $attempts,
            'pipeline_last_attempt_at' => now(),
            'pipeline_next_retry_at' => $nextRetryAt,
            'pipeline_failed_stage' => $stage,
            'progress_log' => $log,
            'auto_retry_count' => $shouldScheduleRetry
                ? (int) ($idea->auto_retry_count ?? 0) + 1
                : (int) ($idea->auto_retry_count ?? 0),
            'last_classified_error_class' => $errorClass->value,
        ];

        if ($idea->status === 'failed') {
            $idea->update($extra);
        } else {
            $idea->transitionTo(ContentIdeaStatus::Failed, "markFailed_{$stage}", $extra);
        }

        Log::warning(
            "[AutoPipeline] Idea #{$idea->id} failed at {$stage} (attempt {$attempts}/"
            . self::MAX_ATTEMPTS
            . ", class={$errorClass->value}, retry_scheduled="
            . ($shouldScheduleRetry ? 'yes' : 'no')
            . "): {$errorMessage}"
        );

        // Telegram fires on either: (a) attempt budget exhausted, or (b)
        // classifier rejected this error class entirely (no auto-retry path).
        if ($exhausted || !$autoRetryEligible) {
            Log::error("[AutoPipeline] Idea #{$idea->id} pipeline halted — sending Telegram failure ping");
            try {
                $this->telegram->notifyFinalFailure($idea->fresh());
            } catch (\Throwable $e) {
                Log::warning('[AutoPipeline] Telegram failure notification threw: ' . $e->getMessage());
            }
        }
    }
}
