<?php

namespace App\Http\Resources;

use Cron\CronExpression;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Phase E — admin scheduler tab.
 *
 * Maps a {@see \App\Models\ScheduledCommand} into the JSON shape the
 * /admin/scheduler frontend expects. Calls into dragonmantank/cron-expression
 * (already a Laravel transitive dep) for cron validation + next_run_at
 * computation. Bad cron strings degrade gracefully to null.
 */
class ScheduledCommandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'signature' => $this->signature,
            'display_name' => $this->display_name,
            'description' => $this->description,
            'category' => $this->category,
            'cron_expression' => $this->cron_expression,
            'cron_human_readable' => $this->cronHumanReadable(),
            'timezone' => $this->timezone,
            'enabled' => (bool) $this->enabled,
            'is_placeholder' => (bool) $this->is_placeholder,
            'without_overlapping_minutes' => $this->without_overlapping_minutes,
            'run_in_background' => (bool) $this->run_in_background,
            'arguments' => $this->arguments ?? [],
            'last_run_at' => optional($this->last_run_at)->toIso8601String(),
            'last_finished_at' => optional($this->last_finished_at)->toIso8601String(),
            'last_status' => $this->last_status,
            'last_duration_ms' => $this->last_duration_ms,
            'last_error' => $this->last_error,
            'next_run_at' => $this->nextRunAt(),
            'sort_order' => $this->sort_order,
        ];
    }

    /**
     * Human-readable summary of the cron expression. Kept intentionally
     * simple — full NLP descriptions belong to the frontend if needed.
     * Returns null for unparseable expressions so the UI can degrade.
     */
    protected function cronHumanReadable(): ?string
    {
        try {
            // Validate by constructing — throws on garbage input.
            new CronExpression($this->cron_expression);
        } catch (\Throwable $e) {
            return null;
        }

        return sprintf('Cron: %s (%s)', $this->cron_expression, $this->timezone);
    }

    /**
     * Compute the next firing time in ISO-8601 (ATOM) format,
     * evaluated in the row's stored timezone. Null on malformed cron.
     */
    protected function nextRunAt(): ?string
    {
        try {
            $cron = new CronExpression($this->cron_expression);
            $tz = $this->timezone ?: config('app.timezone', 'UTC');
            $next = $cron->getNextRunDate(now()->setTimezone($tz));

            return $next->format(\DateTimeInterface::ATOM);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
