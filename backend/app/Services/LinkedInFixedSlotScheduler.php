<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NoAvailableSlotException;
use App\Models\LinkedInPost;
use App\Models\Setting;
use Carbon\Carbon;

/**
 * Finds the next available LinkedIn publish slot from a hardcoded hour list
 * (default 5/6/7/12/17/18/19/20 WIB), enforcing 1-post-per-slot FIFO collision.
 *
 * Replaces the AI-researched posting_time_rules.score>=85 lookup of
 * LinkedInAutoSchedulerService for the auto-publish cadence. The
 * posting_time_rules table is retained for Calendar heatmap visualization.
 *
 * Slots resolve from setting `linkedin_publish_slots` (JSON array of ints 0-23).
 * Lead time from `linkedin_slot_lead_time_minutes` (default 5 minutes — the
 * slot must be at least lead_time minutes in the future to be eligible).
 *
 * Collision policy: a slot is "occupied" when any non-trashed LinkedInPost
 * row has scheduled_at within the slot hour AND status IN
 * ['awaiting_publish','manual_review','awaiting_review','generating',
 *  'validating','pending_generation']. Terminal statuses (published,
 * cancelled, failed) free the slot.
 */
class LinkedInFixedSlotScheduler
{
    private const DEFAULT_SLOTS = [5, 6, 7, 12, 17, 18, 19, 20];
    private const DEFAULT_LEAD_TIME_MINUTES = 5;
    private const LOOKAHEAD_DAYS = 14;

    /**
     * Statuses that occupy a slot. Anything in this list blocks the hour.
     * Terminal statuses (published / cancelled / failed) are deliberately
     * excluded so re-published slots free up correctly.
     */
    private const OCCUPYING_STATUSES = [
        'awaiting_publish',
        'manual_review',
        'awaiting_review',
        'generating',
        'validating',
        'pending_generation',
    ];

    /** @var int[]|null sorted ascending, resolved lazily on first nextAvailableSlot() call */
    private ?array $slots = null;

    private ?int $leadTimeMinutes = null;

    /** @var int[]|null operator override of configured slots */
    private readonly ?array $slotsOverride;

    private readonly ?int $leadTimeOverride;

    /**
     * @param int[]|null $slots Override the configured slot hours (0-23).
     *                         If null, reads from `linkedin_publish_slots` setting on first use.
     * @param int|null $leadTimeMinutes Override lead time. Null reads from setting on first use.
     */
    public function __construct(?array $slots = null, ?int $leadTimeMinutes = null)
    {
        // Defer DB-backed setting lookups to first use so service can be
        // constructed by Laravel container without an active DB connection
        // (e.g., during test bootstrap before RefreshDatabase migrates).
        $this->slotsOverride = $slots;
        $this->leadTimeOverride = $leadTimeMinutes;
    }

    private function ensureResolved(): void
    {
        if ($this->slots === null) {
            $this->slots = $this->resolveSlots($this->slotsOverride);
        }
        if ($this->leadTimeMinutes === null) {
            $this->leadTimeMinutes = $this->leadTimeOverride ?? $this->resolveLeadTime();
        }
    }

    /**
     * Find the next available slot at or after $from (default: now() + lead_time).
     *
     * @throws NoAvailableSlotException when lookahead window exhausted
     */
    public function nextAvailableSlot(?Carbon $from = null): Carbon
    {
        $this->ensureResolved();
        $from = $from?->copy() ?? Carbon::now('Asia/Jakarta');
        $earliest = $from->copy()->setTimezone('Asia/Jakarta')->addMinutes($this->leadTimeMinutes);

        $cursor = $earliest->copy()->startOfDay();

        for ($day = 0; $day < self::LOOKAHEAD_DAYS; $day++) {
            $dayDate = $cursor->copy()->addDays($day);

            foreach ($this->slots as $hour) {
                $candidate = $dayDate->copy()->setHour($hour)->setMinute(0)->setSecond(0);

                if ($candidate->lessThan($earliest)) {
                    continue;
                }

                if ($this->isOccupied($candidate)) {
                    continue;
                }

                return $candidate;
            }
        }

        throw new NoAvailableSlotException(self::LOOKAHEAD_DAYS, $this->slots);
    }

    /**
     * @return int[] sorted ascending, deduped, valid 0-23
     */
    public function getSlots(): array
    {
        $this->ensureResolved();
        return $this->slots;
    }

    public function getLeadTimeMinutes(): int
    {
        $this->ensureResolved();
        return $this->leadTimeMinutes;
    }

    private function isOccupied(Carbon $slot): bool
    {
        $hourEnd = $slot->copy()->addHour();

        return LinkedInPost::query()
            ->whereBetween('scheduled_at', [$slot, $hourEnd->copy()->subSecond()])
            ->whereIn('status', self::OCCUPYING_STATUSES)
            ->whereNull('deleted_at')
            ->exists();
    }

    /**
     * @param int[]|null $override
     * @return int[]
     */
    private function resolveSlots(?array $override): array
    {
        if ($override !== null) {
            return $this->normalizeSlots($override);
        }

        $row = Setting::where('group', 'linkedin')
            ->where('key', 'linkedin_publish_slots')
            ->first();

        if ($row === null || $row->value === null) {
            return self::DEFAULT_SLOTS;
        }

        $decoded = json_decode((string) $row->value, true);
        if (! is_array($decoded)) {
            return self::DEFAULT_SLOTS;
        }

        return $this->normalizeSlots($decoded);
    }

    /**
     * @param array<mixed> $raw
     * @return int[]
     */
    private function normalizeSlots(array $raw): array
    {
        $clean = [];
        foreach ($raw as $h) {
            $int = is_int($h) ? $h : (int) $h;
            if ($int >= 0 && $int <= 23) {
                $clean[$int] = true;
            }
        }
        if (empty($clean)) {
            return self::DEFAULT_SLOTS;
        }
        $keys = array_keys($clean);
        sort($keys);
        return $keys;
    }

    private function resolveLeadTime(): int
    {
        $row = Setting::where('group', 'linkedin')
            ->where('key', 'linkedin_slot_lead_time_minutes')
            ->first();
        if ($row === null || $row->value === null) {
            return self::DEFAULT_LEAD_TIME_MINUTES;
        }
        $value = (int) $row->value;
        return $value >= 0 ? $value : self::DEFAULT_LEAD_TIME_MINUTES;
    }
}
