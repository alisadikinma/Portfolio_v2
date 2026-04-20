<?php

namespace App\Console\Commands;

use App\Models\ContentIdea;
use App\Models\ImageGenerationJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Backfill historical variation rows whose UI status drifted from the
 * authoritative ImageGenerationJob status. Two known root causes the live
 * sync paths can NEVER fix retroactively:
 *
 *   1. Pre-fix late webhook landed when the idea had already advanced to
 *      'completed' — old whitelist excluded that status, so the variation
 *      row stayed 'generating' forever (fixed in 68001a38, but only catches
 *      jobs that ARRIVE after the fix; finalised jobs never re-fire).
 *
 *   2. ProcessPendingImages only scans where status='processing' — once a
 *      job is final ('completed' or 'failed'), it is never reprocessed.
 *      So manual operators who saw the symptom and went looking for a
 *      reset button found none.
 *
 * This command scans every idea in (article_ready, generating_images,
 * images_ready, completed) status, walks each variation, and overwrites
 * UI status from the matching job table row when they disagree. Cosmetic
 * only — never touches FSM, never re-dispatches jobs.
 *
 * Run as: php artisan content-engine:resync-stuck-variations [--dry-run] [--idea=N]
 */
class ResyncStuckVariations extends Command
{
    protected $signature = 'content-engine:resync-stuck-variations
        {--dry-run : Print intended changes without writing}
        {--idea= : Limit to a single content_idea ID}';

    protected $description = 'Backfill image_prompts[].variations[] status from authoritative ImageGenerationJob rows';

    public function handle(): int
    {
        $query = ContentIdea::query()
            ->whereIn('status', ['article_ready', 'generating_images', 'images_ready', 'completed'])
            ->whereNotNull('generated_article');

        if ($ideaId = $this->option('idea')) {
            $query->where('id', (int) $ideaId);
        }

        $ideas = $query->get();
        $this->info("Scanning {$ideas->count()} ideas...");

        $totalDrift = 0;
        $totalFixed = 0;

        foreach ($ideas as $idea) {
            $article = $idea->generated_article;
            $prompts = $article['image_prompts'] ?? [];
            if (empty($prompts)) {
                continue;
            }

            $changedThisIdea = false;

            foreach ($prompts as $pi => $prompt) {
                $variations = $prompt['variations'] ?? [];
                foreach ($variations as $vi => $v) {
                    $uuid = $v['job_uuid'] ?? null;
                    if (!$uuid) {
                        continue;
                    }

                    $job = ImageGenerationJob::where('uuid', $uuid)->first();
                    if (!$job) {
                        // Job row deleted/missing — leave variation alone.
                        continue;
                    }

                    $uiStatus = $v['status'] ?? null;
                    $jobStatus = $job->status;

                    // Map job table values → variation UI values.
                    $expectedUi = match ($jobStatus) {
                        'completed' => 'done',
                        'failed' => 'failed',
                        default => null, // 'processing'/other → leave as-is
                    };

                    if ($expectedUi === null || $uiStatus === $expectedUi) {
                        continue;
                    }

                    $totalDrift++;
                    $this->line(sprintf(
                        '  idea=%d seg=%d var=%d uuid=%s ui=%s job=%s%s',
                        $idea->id,
                        $pi,
                        $vi,
                        substr($uuid, 0, 8),
                        $uiStatus ?? 'null',
                        $jobStatus,
                        $job->image_url ? ' (has url)' : ''
                    ));

                    if ($this->option('dry-run')) {
                        continue;
                    }

                    $prompts[$pi]['variations'][$vi]['status'] = $expectedUi;
                    if ($expectedUi === 'done' && !empty($job->image_url)) {
                        $prompts[$pi]['variations'][$vi]['url'] = $job->image_url;
                    }
                    $changedThisIdea = true;
                    $totalFixed++;
                }
            }

            if ($changedThisIdea) {
                $article['image_prompts'] = $prompts;
                $idea->generated_article = $article;
                $idea->save();
            }
        }

        $this->newLine();
        if ($this->option('dry-run')) {
            $this->info("Dry-run: {$totalDrift} drifted variation(s) detected. No changes written.");
        } else {
            $this->info("Resynced {$totalFixed} variation(s) across all ideas.");
            Log::info('[ResyncStuckVariations] Backfilled drifted variations', [
                'drift_count' => $totalDrift,
                'fixed_count' => $totalFixed,
                'limited_to_idea' => $this->option('idea'),
            ]);
        }

        return 0;
    }
}
