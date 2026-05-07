<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\LinkedInPostStatus;
use App\Models\LinkedInPost;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Schedule conflict detection for LinkedIn drafts.
 *
 * A conflict is any non-deleted LinkedInPost whose `scheduled_at` falls within
 * ±$windowMinutes of a proposed publish time AND whose status is one of the
 * "live on the schedule" set: AwaitingPublish or Published.
 *
 * Extracted from LinkedInDraftController::checkConflict so the auto-scheduler
 * cron (linkedin:auto-schedule) can reuse the same logic without duplicating
 * the query.
 */
class LinkedInScheduleConflictService
{
    public const DEFAULT_WINDOW_MINUTES = 30;

    public function hasConflict(
        Carbon $proposed,
        ?int $excludeDraftId = null,
        int $windowMinutes = self::DEFAULT_WINDOW_MINUTES
    ): bool {
        return $this->buildQuery($proposed, $excludeDraftId, $windowMinutes)->exists();
    }

    /**
     * @return Collection<int, array{id:int,post_title:string,scheduled_at:string,minutes_apart:int}>
     */
    public function findConflicts(
        Carbon $proposed,
        ?int $excludeDraftId = null,
        int $windowMinutes = self::DEFAULT_WINDOW_MINUTES
    ): Collection {
        return $this->buildQuery($proposed, $excludeDraftId, $windowMinutes)
            ->with('post.translations')
            ->get()
            ->map(function (LinkedInPost $other) use ($proposed): array {
                $title = $other->post?->translations?->first()?->title ?? 'Untitled draft';

                return [
                    'id' => $other->id,
                    'post_title' => $title,
                    'scheduled_at' => $other->scheduled_at->toIso8601String(),
                    'minutes_apart' => (int) abs($other->scheduled_at->diffInMinutes($proposed, false)),
                ];
            })
            ->values();
    }

    private function buildQuery(Carbon $proposed, ?int $excludeDraftId, int $windowMinutes)
    {
        $query = LinkedInPost::query()
            ->whereIn('status', [
                LinkedInPostStatus::AwaitingPublish->value,
                LinkedInPostStatus::Published->value,
            ])
            ->whereNotNull('scheduled_at')
            ->whereBetween('scheduled_at', [
                $proposed->copy()->subMinutes($windowMinutes),
                $proposed->copy()->addMinutes($windowMinutes),
            ]);

        if ($excludeDraftId !== null) {
            $query->where('id', '!=', $excludeDraftId);
        }

        return $query;
    }
}
