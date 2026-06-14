<?php

namespace App\Traits;

use App\Exceptions\InvalidStateTransitionException;
use BackedEnum;

/**
 * Generic FSM trait — works with any BackedEnum that exposes a
 * TRANSITIONS constant (array<string, array<string>>) and a
 * canTransitionTo(self $next): bool method.
 *
 * Models using this trait MUST implement statusEnumClass() returning
 * the fully qualified enum class name.
 *
 * Example (ContentIdea):
 *   protected function statusEnumClass(): string
 *   {
 *       return \App\Enums\ContentIdeaStatus::class;
 *   }
 */
trait HasStatusTransitions
{
    /**
     * Each consuming model MUST declare which enum class governs its status column.
     */
    abstract protected function statusEnumClass(): string;

    /**
     * Atomically transition status + optionally update other fields.
     *
     * @param  BackedEnum|string  $next     target status (enum instance or string value)
     * @param  string|null        $reason   audit reason appended to pipeline_state_log
     * @param  array              $extra    additional fields to merge into the update call
     *
     * @throws InvalidStateTransitionException when the transition is not in TRANSITIONS map
     */
    public function transitionTo(BackedEnum|string $next, ?string $reason = null, array $extra = []): self
    {
        $enumClass = $this->statusEnumClass();

        $nextEnum = is_string($next) ? $enumClass::from($next) : $next;
        $currentEnum = $enumClass::from($this->status);

        if (!$currentEnum->canTransitionTo($nextEnum)) {
            throw new InvalidStateTransitionException(
                "Cannot transition {$this->status} → {$nextEnum->value} on " . static::class . " #{$this->id}"
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

    /**
     * Operator-forced status set that BYPASSES the FSM adjacency check. For a
     * deliberate admin re-run from a terminal/any state (e.g. "Regenerate all
     * slides" on a `drafted` repurpose job, where no legal forward edge exists).
     * Still appends an audited pipeline_state_log entry tagged `forced => true`.
     * Use sparingly — normal pipeline movement must go through transitionTo().
     */
    public function forceStatus(BackedEnum|string $next, ?string $reason = null, array $extra = []): self
    {
        $enumClass = $this->statusEnumClass();
        $nextEnum = is_string($next) ? $enumClass::from($next) : $next;

        $log = $this->pipeline_state_log ?? [];
        $log[] = [
            'from' => $this->status,
            'to' => $nextEnum->value,
            'reason' => $reason,
            'timestamp' => now()->toIso8601String(),
            'forced' => true,
        ];
        if (count($log) > 20) {
            $log = array_values(array_slice($log, -20));
        }

        $this->status = $nextEnum->value;
        $this->pipeline_state_log = $log;

        $this->update(array_merge($extra, [
            'status' => $nextEnum->value,
            'pipeline_state_log' => $log,
        ]));

        return $this;
    }
}
