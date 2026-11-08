<?php

namespace App\Console\Commands;

use App\Enums\ContentIdeaStatus;
use App\Jobs\DispatchTelegramNotification;
use App\Models\ContentIdea;
use App\Services\ArticleGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledIdeas extends Command
{
    /** Ideas overdue by more than this are stale and never auto-started. */
    public const STALE_AFTER_HOURS = 24;

    /** Trigger failures tolerated before the idea is parked as failed. */
    public const MAX_TRIGGER_FAILURES = 3;

    protected $signature = 'content:process-scheduled';
    protected $description = 'Process scheduled content ideas — starts research for ideas whose scheduled_at has passed';

    public function handle(ArticleGenerationService $articleGen): int
    {
        $candidates = ContentIdea::where('status', 'draft')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        // Stale schedules (e.g. Content Engine was disabled for days) must not
        // mass-start the moment the cron comes back.
        $staleCutoff = now()->subHours(self::STALE_AFTER_HOURS);
        [$stale, $due] = $candidates->partition(fn (ContentIdea $i) => $i->scheduled_at->lt($staleCutoff));
        foreach ($stale as $idea) {
            Log::warning("[Scheduler] Skipping stale scheduled idea #{$idea->id}: scheduled_at is more than ".self::STALE_AFTER_HOURS.'h in the past', [
                'scheduled_at' => $idea->scheduled_at->toIso8601String(),
            ]);
            $this->warn("  Skipped stale idea #{$idea->id} (scheduled: ".\App\Support\WibTime::label($idea->scheduled_at).')');
        }

        if ($due->isEmpty()) {
            $this->info('No scheduled ideas due.');
            return 0;
        }

        $this->info("Found {$due->count()} scheduled ideas to process.");

        foreach ($due as $idea) {
            $this->line("  Processing: {$idea->title} (scheduled: ".\App\Support\WibTime::label($idea->scheduled_at).")");

            try {
                // Keep earlier entries: the scheduled_failed ones are the failure counter.
                $idea->transitionTo(ContentIdeaStatus::Researching, 'scheduled_start', [
                    'output_types' => ['blog_article'],
                    'progress_percentage' => 0,
                    'current_step' => 'starting',
                    'progress_log' => array_merge($idea->progress_log ?? [], [[
                        'timestamp' => now()->toISOString(),
                        'step' => 'scheduled_start',
                        'percentage' => 0,
                        'message' => 'Scheduled generation started automatically',
                    ]]),
                ]);

                // Trigger article generation via SSH/local CLI
                // Default primary language is Indonesian (matches AutoPipelineOrchestrator + ContentPublishService fallback)
                $config = [
                    'topic' => $idea->title,
                    'languages' => $idea->languages ?? ['id'],
                    'instructions' => $idea->instructions ?? '',
                ];

                // Same pipeline selection as ContentIdeaController::startResearch.
                $skillSplit = (bool) config('services.article_generation.skill_split_enabled', false);
                $legacySplit = ! $skillSplit && ! empty(config('services.article_generation.refs_prep'));

                if ($skillSplit) {
                    $result = $articleGen->triggerResearch($idea->id, $config, $articleGen->resolveResearchTier($idea->fresh()));
                } elseif ($legacySplit) {
                    $result = $articleGen->triggerPrep($idea->id, $config);
                } else {
                    $result = $articleGen->triggerGeneration($idea->id, $config);
                }

                if (! $result['success']) {
                    throw new \RuntimeException('Article generation trigger failed: ' . ($result['error'] ?? 'unknown error'));
                }

                // Same workflow record as ContentIdeaController::startResearch.
                $idea->update([
                    'process_pid' => $result['pid'],
                    'workflows' => [[
                        'type' => 'blog_article',
                        'driver' => 'claude_cli',
                        'pipeline' => $skillSplit ? 'research_split' : ($legacySplit ? 'split' : 'single'),
                        'pid' => $result['pid'],
                        'status' => 'running',
                        'created_at' => now()->toISOString(),
                    ]],
                ]);

                $this->info("  Started research for idea #{$idea->id}");
                Log::info("[Scheduler] Started research for scheduled idea #{$idea->id}: {$idea->title}");

            } catch (\Exception $e) {
                $this->error("  Failed: {$e->getMessage()}");
                Log::error("[Scheduler] Failed to start idea #{$idea->id}: {$e->getMessage()}");

                $failureEntry = [
                    'timestamp' => now()->toISOString(),
                    'step' => 'scheduled_failed',
                    'percentage' => 0,
                    'message' => 'Scheduled start failed: ' . $e->getMessage(),
                ];
                $log = array_merge($idea->fresh()->progress_log ?? [], [$failureEntry]);
                $failures = collect($log)->where('step', 'scheduled_failed')->count();

                if ($failures >= self::MAX_TRIGGER_FAILURES) {
                    // Give up: park as failed so the cron never retries it, tell the operator.
                    $idea->transitionTo(ContentIdeaStatus::Failed, 'scheduled_trigger_failed', [
                        'current_step' => 'failed',
                        'progress_log' => $log,
                    ]);
                    Log::error("[Scheduler] Idea #{$idea->id} failed to start {$failures} times; marked failed");
                    DispatchTelegramNotification::dispatch($idea, 'scheduled_trigger_failed');
                    continue;
                }

                // Rollback to draft so the scheduler can try again next tick.
                // researching → draft isn't in the allow-map by default (the
                // pipeline normally ends at completed/failed/archived), so
                // use direct update here — this is an exception recovery
                // path, not a normal state transition.
                $idea->update([
                    'status' => 'draft',
                    'progress_log' => $log,
                ]);
            }
        }

        $this->info('Done.');
        return 0;
    }
}
