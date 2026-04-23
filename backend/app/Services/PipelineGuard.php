<?php

namespace App\Services;

use App\Exceptions\InvalidStateTransitionException;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Generic FSM transition wrapper with uniform logging.
 *
 * The model must use the HasStatusTransitions trait (or expose a
 * transitionTo(BackedEnum, string) method) and have an integer `id`
 * attribute suitable for log line formatting.
 */
class PipelineGuard
{
    public function advance(Model $model, BackedEnum $next, string $reason, array $context = []): Model
    {
        $previous = $model->status;
        $modelName = class_basename($model);

        try {
            $model->transitionTo($next, $reason);
            Log::info("[PipelineGuard] {$modelName} #{$model->id} {$reason}: {$previous} → {$next->value}", $context);
            return $model;
        } catch (InvalidStateTransitionException $e) {
            Log::error("[PipelineGuard] illegal transition on {$modelName} #{$model->id}: {$e->getMessage()}", $context);
            throw $e;
        }
    }
}
