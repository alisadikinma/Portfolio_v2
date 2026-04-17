<?php

namespace App\Console\Commands;

use App\Models\ContentIdea;
use App\Services\TopicDedupService;
use App\Services\TrendingTopicService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PullTrendingDaily extends Command
{
    protected $signature = 'content:pull-trending-daily';

    protected $description = 'Pull trending topics daily and import unique ones as auto_mode content ideas';

    public function handle(TrendingTopicService $trending, TopicDedupService $dedup): int
    {
        $this->info('Fetching trending topics from all sources…');

        try {
            $trends = $trending->getAllTrends();
        } catch (\Throwable $e) {
            $this->error('Failed to fetch trends: ' . $e->getMessage());
            Log::warning('[TrendingDaily] Fetch failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        if (empty($trends)) {
            $this->warn('No trends returned.');
            return self::SUCCESS;
        }

        $imported = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($trends as $trend) {
            $title = trim((string) ($trend['title'] ?? ''));
            if ($title === '') {
                continue;
            }

            try {
                $duplicate = $dedup->isDuplicate($title);
                if ($duplicate !== null) {
                    $skipped++;
                    $this->line("  SKIP (dup of #{$duplicate->id}): {$title}");
                    continue;
                }

                ContentIdea::create([
                    'title' => $title,
                    'slug' => Str::slug($title),
                    'source' => $trend['source'] ?? 'trending',
                    'pillar' => 'general',
                    'status' => 'draft',
                    'auto_mode' => true,
                    'source_data' => $trend,
                ]);
                $imported++;
                $this->line("  IMPORT: {$title}");
            } catch (\Throwable $e) {
                $errors++;
                Log::warning("[TrendingDaily] Failed to import '{$title}': " . $e->getMessage());
            }
        }

        $this->info(sprintf('Done. Imported: %d | Skipped: %d | Errors: %d', $imported, $skipped, $errors));
        Log::info('[TrendingDaily] ' . sprintf('Imported=%d Skipped=%d Errors=%d', $imported, $skipped, $errors));

        return self::SUCCESS;
    }
}
