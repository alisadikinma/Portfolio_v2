<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\LinkedInPostStatus;
use App\Models\LinkedInPost;
use App\Models\PostingTimeRule;
use App\Services\LinkedInAutoSchedulerService;
use App\Services\LinkedInScheduleConflictService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkedInAutoSchedulerServiceTest extends TestCase
{
    use RefreshDatabase;

    private LinkedInAutoSchedulerService $scheduler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scheduler = new LinkedInAutoSchedulerService(
            new LinkedInScheduleConflictService()
        );
    }

    private function seedRule(int $dow, int $hour, int $score = 90): void
    {
        PostingTimeRule::create([
            'platform' => 'linkedin',
            'day_of_week' => $dow,
            'hour' => $hour,
            'timezone' => 'Asia/Jakarta',
            'score' => $score,
            'audience' => 'b2b_tech',
            'last_researched_at' => now(),
        ]);
    }

    /** @test */
    public function next_slot_returns_earliest_ideal_hour_today_when_free(): void
    {
        // Freeze "now" at Monday 04:30 WIB so today's 09:00 + 17:00 are future
        Carbon::setTestNow(Carbon::parse('2026-05-11 04:30:00', 'Asia/Jakarta'));
        $now = Carbon::now();
        $todayDow = $now->dayOfWeek;

        $this->seedRule($todayDow, 9, 90);
        $this->seedRule($todayDow, 17, 88);

        $slot = $this->scheduler->nextAvailableSlot($now);

        $this->assertNotNull($slot);
        $this->assertSame(9, $slot->hour);
        $this->assertSame($now->toDateString(), $slot->toDateString());
    }

    /** @test */
    public function next_slot_skips_to_tomorrow_when_today_full(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-11 04:30:00', 'Asia/Jakarta'));
        $now = Carbon::now();
        $todayDow = $now->dayOfWeek;
        $tomorrowDow = $now->copy()->addDay()->dayOfWeek;

        $this->seedRule($todayDow, 9, 90);
        $this->seedRule($tomorrowDow, 9, 90);

        // Block today's 09:00 with an existing awaiting_publish post
        LinkedInPost::factory()->create([
            'status' => LinkedInPostStatus::AwaitingPublish->value,
            'scheduled_at' => $now->copy()->setTime(9, 0, 0),
        ]);

        $slot = $this->scheduler->nextAvailableSlot($now);

        $this->assertNotNull($slot);
        $this->assertSame(9, $slot->hour);
        $this->assertSame($now->copy()->addDay()->toDateString(), $slot->toDateString());
    }

    /** @test */
    public function next_slot_respects_thirty_minute_lead_time(): void
    {
        // Now at 04:50, slot at 05:00 is only 10 min away → must skip.
        // Same dow has another slot at 09:00 → that should win.
        Carbon::setTestNow(Carbon::parse('2026-05-11 04:50:00', 'Asia/Jakarta'));
        $now = Carbon::now();
        $todayDow = $now->dayOfWeek;

        $this->seedRule($todayDow, 5, 90);
        $this->seedRule($todayDow, 9, 90);

        $slot = $this->scheduler->nextAvailableSlot($now);

        $this->assertNotNull($slot);
        $this->assertSame(9, $slot->hour);
    }

    /** @test */
    public function next_slot_returns_null_when_lookahead_exhausted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-11 04:30:00', 'Asia/Jakarta'));

        // Zero rules above the score threshold — no slots ever qualify
        // (all 14 days return empty pluck → loop completes → null).

        $slot = $this->scheduler->nextAvailableSlot(Carbon::now());

        $this->assertNull($slot);
    }

    /** @test */
    public function next_slot_skips_excluded_iso_strings(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-11 04:30:00', 'Asia/Jakarta'));
        $now = Carbon::now();
        $todayDow = $now->dayOfWeek;

        $this->seedRule($todayDow, 9, 90);
        $this->seedRule($todayDow, 17, 88);

        $excludeFirst = $now->copy()->setTime(9, 0, 0)->toIso8601String();
        $slot = $this->scheduler->nextAvailableSlot($now, [$excludeFirst]);

        $this->assertNotNull($slot);
        $this->assertSame(17, $slot->hour);
    }
}
