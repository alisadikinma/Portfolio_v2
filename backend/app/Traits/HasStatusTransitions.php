<?php

namespace App\Traits;

use App\Enums\ContentIdeaStatus;
use App\Exceptions\InvalidStateTransitionException;

trait HasStatusTransitions
{
    /**
     * Atomically transition status + optionally update other fields.
     *
     * @param  ContentIdeaStatus|string  $next     target status
     * @param  string|null               $reason   audit reason appended to pipeline_state_log
     * @param  array                     $extra    additional fields to merge into the update call
     *
     * @throws InvalidStateTransitionException when the transition is not in TRANSITIONS map
     */
    public function transitionTo(ContentIdeaStatus|string $next, ?string $reason = null, array $extra = []): self
    {
        $nextEnum = is_string($next) ? ContentIdeaStatus::from($next) : $next;
        $currentEnum = ContentIdeaStatus::from($this->status);

        if (!$currentEnum->canTransitionTo($nextEnum)) {
            throw new InvalidStateTransitionException(
                "Cannot transition {$this->status} → {$nextEnum->value} on idea #{$this->id}"
            );
        }

        $log = $this->pipeline_state_log ?? [];
        $log[] = [
            'from' => $this->status,
            'to' => $nextEnum->value,
            'reason' => $reason,
            'timestamp' => now()->toIso8601String(),
        ];
        if (count($log) > 20) {
            $log = array_values(array_slice($log, -20));
        }

        // Mirror the final state onto the in-memory model so callers
        // reading `$model->status` without calling refresh() see the new
        // value. Also makes the contract work with Mockery partial mocks
        // whose `update()` stub doesn't mutate attributes.
        $this->status = $nextEnum->value;
        $this->pipeline_state_log = $log;

        $this->update(array_merge($extra, [
            'status' => $nextEnum->value,
            'pipeline_state_log' => $log,
        ]));

        return $this;
    }
}
