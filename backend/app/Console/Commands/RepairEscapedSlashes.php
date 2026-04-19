<?php

namespace App\Console\Commands;

use App\Models\ContentIdea;
use App\Support\HtmlSlashSanitizer;
use Illuminate\Console\Command;

/**
 * Repair ContentIdea rows where stored HTML content contains literal `\/`
 * instead of `/`. The pattern leaks in through plugin double-escaping and
 * makes closing tags like `<\/p>` render as visible text while the opening
 * tag never closes — the whole article cascades into the prior heading's
 * styling.
 *
 * Scans generated_article + research_data JSON columns. Idempotent.
 */
class RepairEscapedSlashes extends Command
{
    protected $signature = 'content-engine:repair-escaped-slashes
        {--dry-run : Preview without writing to DB}
        {--ids= : Comma-separated idea IDs to repair (skip full scan)}';

    protected $description = 'Strip literal \\/ from generated_article + research_data on content ideas';

    public function handle(): int
    {
        $query = ContentIdea::query();
        if ($idsOpt = $this->option('ids')) {
            $ids = array_filter(array_map('intval', explode(',', $idsOpt)));
            $query->whereIn('id', $ids);
        }

        $ideas = $query->get();
        $this->info('Scanning ' . $ideas->count() . ' ideas' . ($this->option('dry-run') ? ' (DRY RUN)' : ''));

        $repaired = 0;
        foreach ($ideas as $idea) {
            $dirty = false;

            $article = is_array($idea->generated_article) ? $idea->generated_article : null;
            if ($article && $this->containsEscape($article)) {
                if (!$this->option('dry-run')) {
                    $idea->generated_article = HtmlSlashSanitizer::apply($article);
                }
                $dirty = true;
            }

            $research = is_array($idea->research_data) ? $idea->research_data : null;
            if ($research && $this->containsEscape($research)) {
                if (!$this->option('dry-run')) {
                    $idea->research_data = HtmlSlashSanitizer::apply($research);
                }
                $dirty = true;
            }

            if ($dirty) {
                if (!$this->option('dry-run')) {
                    $idea->save();
                }
                $repaired++;
                $this->line("  [{$idea->id}] {$idea->title}");
            }
        }

        $this->info('Repaired: ' . $repaired . ($this->option('dry-run') ? ' (dry run — not saved)' : ''));
        return self::SUCCESS;
    }

    private function containsEscape(array $payload): bool
    {
        foreach ($payload as $value) {
            if (is_array($value)) {
                if ($this->containsEscape($value)) return true;
            } elseif (is_string($value) && strpos($value, '\\/') !== false) {
                return true;
            }
        }
        return false;
    }
}
