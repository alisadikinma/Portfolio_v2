<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LinkedInPostStatus;
use App\Models\Category;
use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Calendar days are WIB days. A post at 05:00 WIB on 13 May is 22:00 UTC on
 * 12 May; cutting ranges at UTC midnight hid it from the 13 May cell.
 */
class CalendarWibDayBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(): Post
    {
        $cat = Category::create(['name' => 'T', 'slug' => 't-'.Str::random(4)]);

        return Post::create([
            'category_id' => $cat->id, 'title' => 'T', 'content' => 'B',
            'slug' => 'cal-'.Str::random(6), 'published' => true, 'published_at' => now(),
        ]);
    }

    private function linkedIn(string $utc): LinkedInPost
    {
        $draft = LinkedInPost::factory()->create([
            'post_id' => $this->makePost()->id,
            'status' => LinkedInPostStatus::AwaitingPublish->value,
            'format' => 'text',
        ]);
        DB::table('linkedin_posts')->where('id', $draft->id)->update([
            'scheduled_at' => $utc, 'cancel_window_ends_at' => null, 'published_at' => null,
        ]);

        return $draft;
    }

    public function test_linkedin_calendar_includes_wib_early_morning_post_on_from_day(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $in = $this->linkedIn('2026-05-12 22:00:00');   // 05:00 WIB, 13 May
        $out = $this->linkedIn('2026-05-13 18:00:00');  // 01:00 WIB, 14 May

        $ids = collect($this->getJson('/api/admin/linkedin-posts/calendar?from=2026-05-13&to=2026-05-13')
            ->assertOk()->json('data.items'))->pluck('id');

        $this->assertTrue($ids->contains($in->id), 'post at 05:00 WIB 13 May must be on 13 May');
        $this->assertFalse($ids->contains($out->id), 'post at 01:00 WIB 14 May must not be on 13 May');
    }

    public function test_linkedin_calendar_meta_range_is_wib_midnight_in_utc(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $data = $this->getJson('/api/admin/linkedin-posts/calendar?from=2026-05-13&to=2026-05-13')->json('data');

        $this->assertSame('2026-05-12T17:00:00+00:00', $data['from']);
        $this->assertSame('2026-05-13T16:59:59+00:00', $data['to']);
    }

    public function test_cross_post_calendar_uses_wib_day_boundaries(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $parent = $this->linkedIn('2026-05-20 00:00:00');
        $make = fn (string $utc) => InstagramPost::create([
            'linkedin_post_id' => $parent->id, 'post_id' => $parent->post_id,
            'status' => 'awaiting_review', 'format' => 'text', 'scheduled_at' => $utc,
        ]);
        $in = $make('2026-05-12 22:00:00');
        $out = $make('2026-05-13 18:00:00');

        $ids = collect($this->getJson('/api/admin/instagram-drafts/calendar?from=2026-05-13&to=2026-05-13')
            ->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($in->id));
        $this->assertFalse($ids->contains($out->id));
    }

    public function test_calendar_schedule_fields_carry_an_explicit_offset(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->linkedIn('2026-05-12 22:00:00');

        $item = $this->getJson('/api/admin/linkedin-posts/calendar?from=2026-05-13&to=2026-05-13')->json('data.items.0');

        $this->assertMatchesRegularExpression('/(Z|[+-]\d{2}:\d{2})$/', $item['scheduled_at']);
        $this->assertMatchesRegularExpression('/(Z|[+-]\d{2}:\d{2})$/', $item['pin_at']);
    }

    public function test_cross_post_default_range_ends_at_end_of_next_month_on_a_31st(): void
    {
        Sanctum::actingAs(User::factory()->create());
        \Carbon\Carbon::setTestNow(\Carbon\Carbon::parse('2026-01-31 12:00:00', 'Asia/Jakarta'));
        $parent = $this->linkedIn('2026-01-31 00:00:00');
        $make = fn (string $utc) => InstagramPost::create([
            'linkedin_post_id' => $parent->id, 'post_id' => $parent->post_id,
            'status' => 'awaiting_review', 'format' => 'text', 'scheduled_at' => $utc,
        ]);
        $inFeb = $make('2026-02-27 05:00:00');
        $inMarch = $make('2026-03-02 05:00:00'); // old code overflowed Jan 31 + 1 month into 3 Mar

        $ids = collect($this->getJson('/api/admin/instagram-drafts/calendar')->assertOk()->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($inFeb->id));
        $this->assertFalse($ids->contains($inMarch->id), 'range must stop at end of February');
        \Carbon\Carbon::setTestNow();
    }
}
