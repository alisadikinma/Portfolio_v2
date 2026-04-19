<?php

namespace App\Console\Commands;

use App\Models\ContentIdea;
use App\Services\TopicScoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Backfill virality_score + display_score for content ideas imported before
 * the scoring pipeline was reliable (or before display_score was introduced).
 *
 * Replays TopicScoringService::scoreBatch against each idea's source_data
 * payload, then applies the same heat + tier boost logic used in
 * TrendingTopicService::getScoredTopics so the resulting display_score
 * matches what newly-imported ideas see.
 *
 * Idempotent: skips ideas that already have a virality_score unless --force.
 */
class BackfillViralityScores extends Command
{
    protected $signature = 'content-engine:backfill-virality
        {--force : Re-score even ideas that already have virality_score}
        {--dry-run : Preview without writing to DB}
        {--limit= : Cap the number of ideas processed (for testing)}';

    protected $description = 'Re-run virality + display score for content ideas missing scores';

    public function handle(TopicScoringService $scoring): int
    {
        $query = ContentIdea::query()->orderBy('id');
        if (!$this->option('force')) {
            $query->whereNull('virality_score');
        }
        if ($limit = (int) $this->option('limit')) {
            $query->limit($limit);
        }

        $ideas = $query->get();
        $total = $ideas->count();

        if ($total === 0) {
            $this->info('No ideas need backfill. Run with --force to re-score everything.');
            return self::SUCCESS;
        }

        $this->info("Found {$total} ideas to score" . ($this->option('dry-run') ? ' (DRY RUN)' : ''));

        $heatBoosts = config('content.trending.heat_boost', ['hot' => 15, 'trending' => 8, 'standard' => 0]);
        $tierBoosts = config('content.trending.tier_boost', [1 => 5, 2 => 2, 3 => 0]);

        $chunks = $ideas->chunk(TopicScoringService::MAX_BATCH_SIZE);
        $updated = 0;
        $skipped = 0;
        $failed = 0;

        $bar = $this->output->createProgressBar($total);
        $bar->start();

        foreach ($chunks as $chunk) {
            // Build the scoreBatch() input shape from source_data. Only title is
            // strictly required; the rest improve AI score quality. Fall back to
            // idea.title when source_data is absent (manual ideas).
            $batch = [];
            $mapping = [];
            foreach ($chunk as $idea) {
                $sd = is_array($idea->source_data) ? $idea->source_data : [];
                $payload = [
                    'title' => $sd['title'] ?? $idea->title,
                    'source' => $sd['source'] ?? $idea->source ?? 'manual',
                    'description' => $sd['description'] ?? '',
                    'publisher' => $sd['publisher'] ?? '',
                    'publisher_tier' => $sd['publisher_tier'] ?? 3,
                    'publisher_count' => $sd['publisher_count'] ?? 1,
                    'trusted_publisher_count' => $sd['trusted_publisher_count'] ?? 0,
                    'pub_date' => $sd['pub_date'] ?? null,
                    'age_hours' => $sd['age_hours'] ?? null,
                    'heat' => $sd['heat'] ?? 'standard',
                ];
                $batch[] = $payload;
                $mapping[] = $idea;
            }

            try {
                $scored = $scoring->scoreBatch($batch);
            } catch (\Throwable $e) {
                Log::warning('[BackfillVirality] scoreBatch failed for chunk', [
                    'error' => $e->getMessage(),
                    'chunk_size' => count($batch),
                ]);
                $this->newLine();
                $this->warn("Chunk failed: {$e->getMessage()} — skipping " . count($batch) . ' ideas');
                $failed += count($batch);
                $bar->advance(count($batch));
                continue;
            }

            foreach ($scored as $i => $result) {
                $idea = $mapping[$i] ?? null;
                if (!$idea) {
                    $bar->advance();
                    continue;
                }

                $composite = (int) ($result['composite_score'] ?? 0);
                $heat = $result['heat'] ?? ($idea->source_data['heat'] ?? 'standard');
                $tier = (int) ($result['publisher_tier'] ?? ($idea->source_data['publisher_tier'] ?? 3));
                $heatBoost = (int) ($heatBoosts[$heat] ?? 0);
                $tierBoost = (int) ($tierBoosts[$tier] ?? 0);
                $displayScore = max(0, min(100, $composite + $heatBoost + $tierBoost));

                if ($composite === 0 && ($result['virality_score'] ?? 0) === 0) {
                    // AI returned zeros — most likely a scoring miss rather than a
                    // real 0. Skip the write so the column stays NULL and surfaces
                    // as "—" in the UI, prompting a manual re-pull if desired.
                    $skipped++;
                    $bar->advance();
                    continue;
                }

                if ($this->option('dry-run')) {
                    $this->newLine();
                    $this->line("  [{$idea->id}] '{$idea->title}' → composite={$composite} display={$displayScore}");
                    $updated++;
                    $bar->advance();
                    continue;
                }

                // Merge scores into source_data so the UI tooltip can render the
                // full breakdown (Virality / Momentum / boosts / final). Preserve
                // any non-score keys already present.
                $mergedSd = array_merge(
                    is_array($idea->source_data) ? $idea->source_data : [],
                    [
                        'composite_score' => $composite,
                        'virality_score' => (int) ($result['virality_score'] ?? 0),
                        'momentum_score' => (int) ($result['momentum_score'] ?? 0),
                        'triggers' => $result['triggers'] ?? null,
                        'heat_boost' => $heatBoost,
                        'tier_boost' => $tierBoost,
                        'display_score' => $displayScore,
                    ]
                );

                $idea->virality_score = $composite;
                $idea->source_data = $mergedSd;
                $idea->save();

                $updated++;
                $bar->advance();
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Updated: {$updated}");
        if ($skipped > 0) {
            $this->warn("Skipped (AI returned zero — likely failed scoring): {$skipped}");
        }
        if ($failed > 0) {
            $this->warn("Failed (chunk-level errors): {$failed}");
        }

        return self::SUCCESS;
    }
}
