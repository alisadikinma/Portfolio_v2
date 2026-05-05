<?php

namespace App\Console\Commands;

use App\Jobs\GenerateImageVariantsJob;
use App\Models\GalleryItem;
use App\Models\Post;
use App\Models\Project;
use Illuminate\Console\Command;

/**
 * One-shot backfill for the image_variants pipeline. Walks every model
 * row that has a source image but no variants yet and dispatches
 * GenerateImageVariantsJob per row.
 *
 * Use after the migration ships and the variant pipeline goes live, to
 * generate WebP variants for the historical inventory of images.
 *
 * Examples:
 *   php artisan images:generate-variants
 *   php artisan images:generate-variants --model=Post --limit=100
 *   php artisan images:generate-variants --dry-run
 */
class GenerateImageVariants extends Command
{
    protected $signature = 'images:generate-variants
                            {--model=all : Project|Post|GalleryItem|all}
                            {--limit=1000 : Max rows to enqueue per model}
                            {--dry-run : Report counts without dispatching jobs}';

    protected $description = 'Backfill WebP image variants + LQIP for projects, posts, gallery items.';

    public function handle(): int
    {
        $modelFilter = $this->option('model');
        $limit = (int) $this->option('limit');
        $dryRun = (bool) $this->option('dry-run');

        $candidates = match ($modelFilter) {
            'Project' => [Project::class],
            'Post' => [Post::class],
            'GalleryItem' => [GalleryItem::class],
            'all', null, '' => [Project::class, Post::class, GalleryItem::class],
            default => null,
        };

        if ($candidates === null) {
            $this->error("Unknown --model={$modelFilter}. Use Project|Post|GalleryItem|all.");
            return self::FAILURE;
        }

        $totalDispatched = 0;

        foreach ($candidates as $cls) {
            $instance = new $cls;
            $col = $instance->imageVariantSource();

            $base = $cls::query()
                ->whereNull('image_variants')
                ->whereNotNull($col)
                ->where($col, '!=', '');

            $count = (clone $base)->count();
            $this->info(sprintf('%s — %d rows pending variant generation', class_basename($cls), $count));

            if ($dryRun || $count === 0) {
                continue;
            }

            $bar = $this->output->createProgressBar(min($count, $limit));
            $bar->start();

            $base->limit($limit)->chunkById(50, function ($rows) use (&$totalDispatched, $bar) {
                foreach ($rows as $row) {
                    GenerateImageVariantsJob::dispatch(
                        modelClass: get_class($row),
                        modelId: $row->getKey(),
                    );
                    $totalDispatched++;
                    $bar->advance();
                }
            });

            $bar->finish();
            $this->newLine();
        }

        $this->info(sprintf(
            '%s %d job(s).',
            $dryRun ? 'Would dispatch' : 'Dispatched',
            $totalDispatched,
        ));

        return self::SUCCESS;
    }
}
