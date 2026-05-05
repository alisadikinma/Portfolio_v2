<?php

namespace App\Traits;

use App\Jobs\GenerateImageVariantsJob;

/**
 * Auto-dispatches WebP variant generation when the model's image source
 * column changes. Models declare which column holds their source via
 * imageVariantSource(). On save (created or source-column dirty), a queued
 * job runs ImageVariantService and writes variants back into the
 * `image_variants` JSON column.
 *
 * Frontend BaseImage falls back to the original src when image_variants
 * is null, so partial backfill is non-breaking.
 *
 * Models using this trait must:
 *   - have an `image_variants` JSON column (migration 2026_05_05_000004)
 *   - cast `image_variants` to 'array' in $casts
 *   - implement imageVariantSource(): string returning the source col name
 */
trait HasImageVariants
{
    abstract public function imageVariantSource(): string;

    public static function bootHasImageVariants(): void
    {
        static::saved(function ($model) {
            $col = $model->imageVariantSource();

            // Fire when source column was just set/changed AND value is non-empty.
            if (! $model->wasChanged($col)) {
                return;
            }
            if (empty($model->{$col})) {
                return;
            }

            GenerateImageVariantsJob::dispatch(
                modelClass: get_class($model),
                modelId: $model->getKey(),
            );
        });
    }
}
