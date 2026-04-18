<?php

namespace App\Console\Commands;

use App\Models\ContentIdea;
use Illuminate\Console\Command;

class MigrateImageModelDefault extends Command
{
    protected $signature = 'content:migrate-image-model
        {--from=nano-banana-2 : Old model value to replace}
        {--to=nano-banana-pro : New model value}
        {--include-completed : Also update ideas already published (default: skip)}
        {--dry-run : Print what would change without writing}';

    protected $description = 'One-time migration: update image_prompts[].model in content_ideas.generated_article from old default to new default.';

    public function handle(): int
    {
        $from = $this->option('from');
        $to = $this->option('to');
        $includeCompleted = $this->option('include-completed');
        $dryRun = $this->option('dry-run');

        $query = ContentIdea::whereNotNull('generated_article');
        if (!$includeCompleted) {
            $query->where('status', '!=', 'completed');
        }

        $updated = 0;
        $promptsChanged = 0;
        $ideas = $query->get();

        foreach ($ideas as $idea) {
            $article = $idea->generated_article ?? [];
            $prompts = $article['image_prompts'] ?? [];
            if (!is_array($prompts) || empty($prompts)) continue;

            $ideaChanged = false;
            foreach ($prompts as $i => $p) {
                if (($p['model'] ?? null) === $from) {
                    $prompts[$i]['model'] = $to;
                    $ideaChanged = true;
                    $promptsChanged++;
                }
            }

            if ($ideaChanged) {
                $this->line(sprintf(
                    '  #%d "%s" — updated %d prompt(s)',
                    $idea->id,
                    mb_strimwidth($idea->title ?? '(untitled)', 0, 70, '...'),
                    count(array_filter($prompts, fn($p) => ($p['model'] ?? null) === $to))
                ));

                if (!$dryRun) {
                    $article['image_prompts'] = $prompts;
                    $idea->generated_article = $article;
                    $idea->save();
                }
                $updated++;
            }
        }

        $verb = $dryRun ? 'Would update' : 'Updated';
        $this->info(sprintf(
            '%s %d idea(s), %d image_prompt entries (%s → %s)%s',
            $verb,
            $updated,
            $promptsChanged,
            $from,
            $to,
            $includeCompleted ? ' [including completed]' : ''
        ));

        return self::SUCCESS;
    }
}
