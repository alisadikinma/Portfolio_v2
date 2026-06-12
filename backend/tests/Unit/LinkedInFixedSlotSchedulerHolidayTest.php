<?php

namespace Tests\Unit;

use App\Services\IndonesianHolidayService;
use App\Services\LinkedInFixedSlotScheduler;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase C — docs/plans/2026-06-12-social-studio-status-sync-telegram-scheduling.md
 *
 * Holiday-aware slot skipping + nextAvailableSlots(N) for the Telegram prompt.
 */
class LinkedInFixedSlotSchedulerHolidayTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_holiday_day_is_skipped_even_when_weekdays_only_off(): void
    {
        // 2026-08-17 (Mon) = Hari Kemerdekaan — a weekday holiday.
        Carbon::setTestNow(Carbon::parse('2026-08-17 00:00:00', 'Asia/Jakarta'));

        $scheduler = new LinkedInFixedSlotScheduler([12], 0, false, new IndonesianHolidayService());
        $slot = $scheduler->nextAvailableSlot();

        // Must skip the holiday Monday and land on Tue 2026-08-18 12:00.
        $this->assertSame('2026-08-18 12:00', $slot->format('Y-m-d H:i'));
    }

    public function test_next_available_slots_returns_three_distinct_ascending(): void
    {
        // 2026-08-18 (Tue) — no holiday nearby.
        Carbon::setTestNow(Carbon::parse('2026-08-18 00:00:00', 'Asia/Jakarta'));

        $scheduler = new LinkedInFixedSlotScheduler([12, 18], 0, false, new IndonesianHolidayService());
        $slots = $scheduler->nextAvailableSlots(3);

        $this->assertCount(3, $slots);
        $formatted = array_map(fn ($s) => $s->format('Y-m-d H:i'), $slots);
        $this->assertSame([
            '2026-08-18 12:00',
            '2026-08-18 18:00',
            '2026-08-19 12:00',
        ], $formatted);
    }
}
