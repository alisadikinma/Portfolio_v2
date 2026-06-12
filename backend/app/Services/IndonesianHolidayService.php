<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Static (no-API) Indonesian public-holiday lookup, backed by config/id_holidays.php.
 *
 * Phase B — docs/plans/2026-06-12-social-studio-status-sync-telegram-scheduling.md.
 * Consumed by LinkedInFixedSlotScheduler so a posting slot is never suggested on
 * a national holiday / cuti bersama. National holidays + cuti bersama are both
 * treated as non-working.
 *
 * Fallback: a date in a year with no config entry returns false (degrade to
 * weekend-only) AND logs a warning so the operator knows to add + redeploy the
 * new year before that year's scheduling runs. Fully deterministic — no network.
 */
class IndonesianHolidayService
{
    /** @var array<int, array<string, true>> year => ['Y-m-d' => true] lookup, memoized per year. */
    private array $memo = [];

    public function isHoliday(Carbon $date): bool
    {
        $year = (int) $date->year;

        if (! array_key_exists($year, $this->memo)) {
            $yearMap = config("id_holidays.{$year}");

            if (! is_array($yearMap)) {
                Log::warning("[IndonesianHoliday] no data for year {$year} — weekend-only");
                $this->memo[$year] = [];
            } else {
                // Flip to a 'Y-m-d' => true set for O(1) membership.
                $this->memo[$year] = array_fill_keys(array_keys($yearMap), true);
            }
        }

        return isset($this->memo[$year][$date->format('Y-m-d')]);
    }
}
