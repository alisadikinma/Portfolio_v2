<?php

namespace Tests\Unit;

use App\Services\IndonesianHolidayService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Phase B — docs/plans/2026-06-12-social-studio-status-sync-telegram-scheduling.md
 *
 * Static (no-API) Indonesian holiday lookup with missing-year weekend-only
 * fallback + warning log.
 */
class IndonesianHolidayServiceTest extends TestCase
{
    private IndonesianHolidayService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new IndonesianHolidayService();
    }

    public function test_known_weekday_holiday_is_true(): void
    {
        // 2026-08-17 = Hari Kemerdekaan RI (Monday).
        $this->assertTrue($this->service->isHoliday(Carbon::parse('2026-08-17')));
    }

    public function test_ordinary_weekday_is_false(): void
    {
        // 2026-08-18 = ordinary Tuesday.
        $this->assertFalse($this->service->isHoliday(Carbon::parse('2026-08-18')));
    }

    public function test_weekend_falling_holiday_still_in_list(): void
    {
        // 2026-03-21 = Idulfitri (Saturday) — kept verbatim from the SKB list.
        $this->assertTrue($this->service->isHoliday(Carbon::parse('2026-03-21')));
    }

    public function test_missing_year_returns_false_and_logs_warning(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn ($msg) => str_contains((string) $msg, '2099'));

        $this->assertFalse($this->service->isHoliday(Carbon::parse('2099-01-01')));
    }

    public function test_year_with_data_does_not_log_warning(): void
    {
        Log::shouldReceive('warning')->never();

        $this->service->isHoliday(Carbon::parse('2026-12-25')); // Natal — present
    }
}
