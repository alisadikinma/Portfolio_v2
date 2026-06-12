<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Exceptions\NoAvailableSlotException;
use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\Setting;
use App\Services\IndonesianHolidayService;
use App\Services\LinkedInFixedSlotScheduler;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkedInFixedSlotSchedulerTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;
    private Post $post;

    protected function setUp(): void
    {
        parent::setUp();

        Setting::firstOrCreate(
            ['group' => 'linkedin', 'key' => 'linkedin_publish_slots'],
            ['value' => '[5,6,7,12,17,18,19,20]', 'type' => 'json']
        );
        Setting::firstOrCreate(
            ['group' => 'linkedin', 'key' => 'linkedin_slot_lead_time_minutes'],
            ['value' => '5', 'type' => 'integer']
        );

        $this->category = Category::create(['name' => 'Test', 'slug' => 'test']);
        $this->post = Post::create([
            'category_id' => $this->category->id,
            'title' => 'Fixed Slot Test ' . uniqid(),
            'slug' => 'fixed-slot-test-' . uniqid(),
            'content' => 'Test content',
            'published' => true,
            'published_at' => now(),
        ]);
    }

    private function makeScheduler(?array $slots = null, ?int $leadTime = null): LinkedInFixedSlotScheduler
    {
        // Inject a no-op holiday service so these pure slot-logic tests stay
        // decoupled from the real Indonesian holiday calendar (holiday skipping
        // is covered separately in LinkedInFixedSlotSchedulerHolidayTest).
        return new LinkedInFixedSlotScheduler($slots, $leadTime, null, $this->noHolidays());
    }

    private function noHolidays(): IndonesianHolidayService
    {
        return new class extends IndonesianHolidayService {
            public function isHoliday(Carbon $date): bool
            {
                return false;
            }
        };
    }

    public function test_returns_next_hour_in_slot_list(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-13 04:00:00', 'Asia/Jakarta'));

        $slot = $this->makeScheduler()->nextAvailableSlot();

        $this->assertSame('2026-05-13 05:00:00', $slot->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
    }

    public function test_weekdays_only_skips_weekend_to_monday(): void
    {
        // Friday 2026-05-15 21:00 WIB — all of Friday's slots have passed.
        Carbon::setTestNow(Carbon::parse('2026-05-15 21:00:00', 'Asia/Jakarta'));

        $scheduler = new LinkedInFixedSlotScheduler([5, 12, 18], null, true, $this->noHolidays());
        $slot = $scheduler->nextAvailableSlot();

        // Sat 16 + Sun 17 skipped → first slot Monday 18 at 05:00.
        $this->assertSame('2026-05-18 05:00:00', $slot->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
    }

    public function test_weekdays_only_off_allows_saturday(): void
    {
        // Same Friday 21:00, but weekdays-only disabled → Saturday is eligible.
        Carbon::setTestNow(Carbon::parse('2026-05-15 21:00:00', 'Asia/Jakarta'));

        $scheduler = new LinkedInFixedSlotScheduler([5, 12, 18], null, false, $this->noHolidays());
        $slot = $scheduler->nextAvailableSlot();

        $this->assertSame('2026-05-16 05:00:00', $slot->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
    }

    public function test_skips_past_slots_to_next_hour(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-13 13:00:00', 'Asia/Jakarta'));

        $slot = $this->makeScheduler()->nextAvailableSlot();

        // 12:00 already passed → next is 17:00
        $this->assertSame('2026-05-13 17:00:00', $slot->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
    }

    public function test_lead_time_guard_skips_slot_too_soon(): void
    {
        // Now = 04:58 WIB, lead=5 → eligible from 05:03, so 05:00 slot disqualified
        Carbon::setTestNow(Carbon::parse('2026-05-13 04:58:00', 'Asia/Jakarta'));

        $slot = $this->makeScheduler()->nextAvailableSlot();

        $this->assertSame('2026-05-13 06:00:00', $slot->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
    }

    public function test_rolls_to_next_day_when_all_today_slots_passed(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-13 21:00:00', 'Asia/Jakarta'));

        $slot = $this->makeScheduler()->nextAvailableSlot();

        $this->assertSame('2026-05-14 05:00:00', $slot->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
    }

    public function test_collision_with_awaiting_publish_skips_slot(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-13 04:00:00', 'Asia/Jakarta'));

        LinkedInPost::factory()->create([
            'post_id' => $this->post->id,
            'status' => 'awaiting_publish',
            'scheduled_at' => Carbon::parse('2026-05-13 05:00:00', 'Asia/Jakarta'),
        ]);

        $slot = $this->makeScheduler()->nextAvailableSlot();

        $this->assertSame('2026-05-13 06:00:00', $slot->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
    }

    public function test_collision_with_awaiting_review_also_skips(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-13 04:00:00', 'Asia/Jakarta'));

        LinkedInPost::factory()->create([
            'post_id' => $this->post->id,
            'status' => 'manual_review',
            'scheduled_at' => Carbon::parse('2026-05-13 05:00:00', 'Asia/Jakarta'),
        ]);

        $slot = $this->makeScheduler()->nextAvailableSlot();

        // manual_review with assigned slot also blocks
        $this->assertSame('2026-05-13 06:00:00', $slot->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
    }

    public function test_custom_slots_override_setting(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-13 04:00:00', 'Asia/Jakarta'));

        $slot = $this->makeScheduler(slots: [9, 14])->nextAvailableSlot();

        $this->assertSame('2026-05-13 09:00:00', $slot->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i:s'));
    }

    public function test_throws_when_lookahead_exhausted(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-05-13 21:00:00', 'Asia/Jakarta'));

        // Fill every slot for next 14 days
        $slots = [5, 6, 7, 12, 17, 18, 19, 20];
        $start = Carbon::parse('2026-05-14 00:00:00', 'Asia/Jakarta');
        for ($day = 0; $day < 14; $day++) {
            foreach ($slots as $hour) {
                LinkedInPost::factory()->create([
                    'post_id' => $this->post->id,
                    'status' => 'awaiting_publish',
                    'scheduled_at' => $start->copy()->addDays($day)->setHour($hour),
                ]);
            }
        }

        $this->expectException(NoAvailableSlotException::class);
        $this->makeScheduler()->nextAvailableSlot();
    }
}
