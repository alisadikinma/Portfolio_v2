<?php

namespace App\Traits;

use App\Enums\ContentIdeaStatus;
use App\Exceptions\InvalidStateTransitionException;

trait HasStatusTransitions
{
    public function transitionTo(ContentIdeaStatus|string $next, ?string $reason = null): self
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

        $this->update([
            'status' => $nextEnum->value,
            'pipeline_state_log' => $log,
        ]);

        return $this;
    }
}
