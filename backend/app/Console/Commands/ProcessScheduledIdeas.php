<?php

namespace App\Console\Commands;

use App\Enums\ContentIdeaStatus;
use App\Models\ContentIdea;
use App\Services\ArticleGenerationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledIdeas extends Command
{
    protected $signature = 'content:process-scheduled';
    protected $description = 'Process scheduled content ideas — starts research for ideas whose scheduled_at has passed';

    public function handle(ArticleGenerationService $articleGen): int
    {
        $due = ContentIdea::where('status', 'draft')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->get();

        if ($due->isEmpty()) {
            $this->info('No scheduled ideas due.');
            return 0;
        }

        $this->info("Found {$due->count()} scheduled ideas to process.");

        foreach ($due as $idea) {
            $this->line("  Processing: {$idea->title} (scheduled: {$idea->scheduled_at})");

            try {
                $idea->transitionTo(ContentIdeaStatus::Researching, 'scheduled_start', [
                    'progress_percentage' => 0,
                    'current_step' => 'starting',
                    'progress_log' => [[
                        'timestamp' => now()->toISOString(),
                        'step' => 'scheduled_start',
                        'percentage' => 0,
                        'message' => 'Scheduled generation started automatically',
                    ]],
                ]);

                // Trigger article generation via SSH/local CLI
                // Default primary language is Indonesian (matches AutoPipelineOrchestrator + ContentPublishService fallback)
                $languages = $idea->languages ?? ['id'];
                $articleGen->trigger($idea->id, $idea->title, implode(',', $languages), $idea->instructions ?? '');

                $this->info("  Started research for idea #{$idea->id}");
                Log::info("[Scheduler] Started research for scheduled idea #{$idea->id}: {$idea->title}");

            } catch (\Exception $e) {
                $this->error("  Failed: {$e->getMessage()}");
                Log::error("[Scheduler] Failed to start idea #{$idea->id}: {$e->getMessage()}");

                // Rollback to draft so the scheduler can try again next tick.
                // researching → draft isn't in the allow-map by default (the
                // pipeline normally ends at completed/failed/archived), so
                // use direct update here — this is an exception recovery
                // path, not a normal state transition.
                $idea->update([
                    'status' => 'draft',
                    'progress_log' => array_merge($idea->progress_log ?? [], [[
                        'timestamp' => now()->toISOString(),
                        'step' => 'scheduled_failed',
                        'percentage' => 0,
                        'message' => 'Scheduled start failed: ' . $e->getMessage(),
                    ]]),
                ]);
            }
        }

        $this->info('Done.');
        return 0;
    }
}
