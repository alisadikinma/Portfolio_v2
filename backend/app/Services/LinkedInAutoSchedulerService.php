<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PostingTimeRule;
use Carbon\Carbon;

/**
 * Walks forward through `posting_time_rules` to find the next free high-quality
 * slot for an auto-scheduled LinkedIn post.
 *
 * "High-quality" defaults to score >= 85 (matches design doc). Slots already
 * occupied (within ±30 min of an awaiting_publish or published row) are
 * skipped via LinkedInScheduleConflictService. In-tick collisions are
 * prevented by the caller passing previously-assigned ISO timestamps in
 * $excludingIso8601.
 *
 * Returns null when the 14-day lookahead is exhausted (signals to the cron
 * that backlog has outpaced ideal capacity — operator gets Telegram alert).
 */
class LinkedInAutoSchedulerService
{
    public const DEFAULT_MIN_SCORE = 85;
    public const DEFAULT_LOOKAHEAD_DAYS = 14;
    public const LEAD_TIME_MINUTES = 30;
    public const LINKEDIN_AUDIENCE = 'b2b_tech';

    public function __construct(
        private readonly LinkedInScheduleConflictService $conflictService,
    ) {
    }

    /**
     * @param Carbon $after The earliest acceptable slot (typically now()).
     * @param array<int, string> $excludingIso8601 Already-assigned slots from
     *   the same cron tick (prevents collision when N drafts are promoted in
     *   one pass).
     * @param int $minScore Minimum posting_time_rules.score required.
     * @param int $lookaheadDays Walk this many days forward before giving up.
     */
    public function nextAvailableSlot(
        Carbon $after,
        array $excludingIso8601 = [],
        int $minScore = self::DEFAULT_MIN_SCORE,
        int $lookaheadDays = self::DEFAULT_LOOKAHEAD_DAYS
    ): ?Carbon {
        $earliestAcceptable = $after->copy()->addMinutes(self::LEAD_TIME_MINUTES);

        for ($dayOffset = 0; $dayOffset <= $lookaheadDays; $dayOffset++) {
            $day = $after->copy()->addDays($dayOffset)->timezone('Asia/Jakarta');

            $idealHours = PostingTimeRule::query()
                ->forPlatform('linkedin')
                ->forAudience(self::LINKEDIN_AUDIENCE)
                ->forDayOfWeek($day->dayOfWeek)
                ->optimal($minScore)
                ->orderBy('hour')
                ->pluck('hour');

            foreach ($idealHours as $hour) {
                $candidate = $day->copy()->setTime($hour, 0, 0);

                if ($candidate->lt($earliestAcceptable)) {
                    continue;
                }
                if (in_array($candidate->toIso8601String(), $excludingIso8601, true)) {
                    continue;
                }
                if ($this->conflictService->hasConflict($candidate)) {
                    continue;
                }

                return $candidate;
            }
        }

        return null;
    }
}
