<?php

namespace App\Jobs;

use App\Services\ImageVariantService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Queued WebP variant generation. Dispatched by HasImageVariants trait
 * when a model's source image column changes, or by the
 * images:generate-variants artisan backfill.
 *
 * Uses saveQuietly() to write variants back so we don't re-fire the
 * trait's saved() listener and create an infinite dispatch loop.
 */
class GenerateImageVariantsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 180;

    public int $tries = 2;

    public function __construct(
        public string $modelClass,
        public int|string $modelId,
    ) {
    }

    public function handle(ImageVariantService $service): void
    {
        /** @var Model|null $model */
        $model = ($this->modelClass)::query()->find($this->modelId);
        if ($model === null) {
            return;
        }

        if (! method_exists($model, 'imageVariantSource')) {
            Log::warning('[GenerateImageVariantsJob] model missing imageVariantSource()', [
                'class' => $this->modelClass,
                'id' => $this->modelId,
            ]);
            return;
        }

        $sourceColumn = $model->imageVariantSource();
        $sourceValue = $model->getAttribute($sourceColumn);

        $relativePath = ImageVariantService::normalizePath($sourceValue);
        if ($relativePath === null) {
            return;
        }

        $variants = $service->generate($relativePath);
        if (empty($variants)) {
            return;
        }

        // saveQuietly avoids re-firing HasImageVariants' saved() observer
        // and triggering an infinite dispatch loop.
        $model->forceFill(['image_variants' => $variants])->saveQuietly();
    }
}
