<?php

namespace App\Console\Commands;

use App\Models\ContentIdea;
use App\Services\TelegramNotificationService;
use App\Services\TopicDedupService;
use App\Services\TrendingTopicService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PullTrendingDaily extends Command
{
    protected $signature = 'content:pull-trending-daily
        {--threshold= : Override content.trending.virality_threshold for this run}
        {--dry-run : Score and filter but skip the DB writes}';

    protected $description = 'Pull trending topics daily, AI-score them, and import only those above the virality threshold as auto_mode content ideas';

    public function handle(TrendingTopicService $trending, TopicDedupService $dedup, TelegramNotificationService $telegram): int
    {
        $threshold = $this->option('threshold') !== null
            ? (int) $this->option('threshold')
            : (int) config('content.trending.virality_threshold', 70);
        $threshold = max(0, min(100, $threshold));
        $dryRun = (bool) $this->option('dry-run');
        $pickLimit = (int) config('content.trending.daily_pick_limit', 5);

        $this->info("Fetching trending topics from all sources… (virality threshold: {$threshold}, pick limit: {$pickLimit}" . ($dryRun ? ', DRY RUN' : '') . ')');

        try {
            // getScoredTopics() runs TopicScoringService::scoreBatch under the
            // hood — adds virality_score (Sonnet), momentum_score (mechanical),
            // triggers[], composite_score, display_score to each topic. Without
            // this step the import inserts unscored rows that bypass the
            // editorial gate completely.
            $scored = $trending->getScoredTopics();
        } catch (\Throwable $e) {
            $this->error('Failed to fetch + score trends: ' . $e->getMessage());
            Log::warning('[TrendingDaily] Fetch+score failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (empty($scored)) {
            $this->warn('No trends returned.');
            return self::SUCCESS;
        }

        // --- Best-N selection ---
        // 1. Discard topics below the virality threshold (editorial floor).
        // 2. Sort the survivors by virality_score DESC so the purest AI
        //    signal (not the composite with heat/tier noise) drives picking.
        // 3. Cap at daily_pick_limit — import only the BEST topics each day.
        //    A duplicate slot is simply dropped; no auto-backfill from
        //    lower-ranked topics so the daily cadence stays intentional.
        $skippedLowVirality = 0;
        $candidates = [];
        foreach ($scored as $trend) {
            $virality = (int) ($trend['virality_score'] ?? 0);
            if ($virality < $threshold) {
                $skippedLowVirality++;
                continue;
            }
            $candidates[] = $trend;
        }

        usort($candidates, fn ($a, $b) => ($b['virality_score'] ?? 0) <=> ($a['virality_score'] ?? 0));
        $candidates = array_slice($candidates, 0, $pickLimit);

        $this->line("  Candidates after threshold+sort+cap: " . count($candidates) . " (skipped low-virality: {$skippedLowVirality})");

        $imported = 0;
        $skippedDup = 0;
        $errors = 0;

        foreach ($candidates as $trend) {
            $title = trim((string) ($trend['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            $virality = (int) ($trend['virality_score'] ?? 0);

            try {
                $duplicate = $dedup->isDuplicate($title);
                if ($duplicate !== null) {
                    $skippedDup++;
                    $this->line("  SKIP (dup of #{$duplicate->id}): {$title}");
                    continue;
                }

                if ($dryRun) {
                    $imported++;
                    $this->line("  WOULD IMPORT (virality={$virality}): {$title}");
                    continue;
                }

                ContentIdea::create([
                    'title' => $title,
                    'source' => $trend['source'] ?? 'trending',
                    'pillar' => 'general',
                    'status' => 'draft',
                    'auto_mode' => true,
                    'source_data' => $trend,
                    'virality_score' => $virality,
                    // STEPPS triggers + carousel_fit from TopicScoringService —
                    // preserved on the row so the admin tooltip can explain WHY
                    // a topic scored high (social_currency, high_arousal, etc).
                    'virality_breakdown' => is_array($trend['triggers'] ?? null)
                        ? array_merge($trend['triggers'], [
                            'carousel_fit' => (bool) ($trend['carousel_fit'] ?? false),
                        ])
                        : null,
                ]);
                $imported++;
                $this->line("  IMPORT (virality={$virality}): {$title}");
            } catch (\Throwable $e) {
                $errors++;
                Log::warning("[TrendingDaily] Failed to import '{$title}': " . $e->getMessage());
            }
        }

        $summary = sprintf(
            'Imported=%d Skipped(dup)=%d Skipped(low_virality)=%d Errors=%d',
            $imported, $skippedDup, $skippedLowVirality, $errors
        );
        $this->info("Done. {$summary}");
        Log::info('[TrendingDaily] ' . $summary);

        // Heartbeat to operator's Telegram so silent cron failures surface
        // within 24h instead of going unnoticed for days. No-op when toggle
        // off or in --dry-run (a dry-run heartbeat would be misleading).
        if (!$dryRun) {
            $telegram->sendTrendingDailyHeartbeat($imported, $skippedDup, $skippedLowVirality, $errors);
        }

        return self::SUCCESS;
    }
}
