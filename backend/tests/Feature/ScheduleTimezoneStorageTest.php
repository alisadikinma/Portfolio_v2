<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LinkedInPostStatus;
use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\ThreadsPost;
use App\Services\LinkedInSchedulingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Schedule timestamps must hit the DB in app.timezone (UTC) no matter which
 * zone the writer's Carbon carries. Regression: WIB wall-clock stored as UTC
 * made scheduled posts publish 7 hours late.
 */
class ScheduleTimezoneStorageTest extends TestCase
{
    use RefreshDatabase;

    private function makeDraft(): LinkedInPost
    {
        $cat = Category::create(['name' => 'T', 'slug' => 't-'.Str::random(4)]);
        $post = Post::create([
            'category_id' => $cat->id, 'title' => 'T', 'content' => 'B',
            'slug' => 'tz-'.Str::random(6), 'published' => true, 'published_at' => now(),
        ]);

        return LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'status' => LinkedInPostStatus::ManualReview->value,
            'format' => 'text',
        ]);
    }

    private function raw(string $table, int $id, string $col): ?string
    {
        return DB::table($table)->where('id', $id)->value($col);
    }

    public function test_wib_carbon_is_stored_as_utc_on_draft_and_sibling(): void
    {
        $draft = $this->makeDraft();
        $threads = ThreadsPost::create([
            'linkedin_post_id' => $draft->id, 'post_id' => $draft->post_id,
            'status' => 'pending_generation', 'format' => 'text',
        ]);

        $slot = Carbon::parse('2026-05-13 12:00', 'Asia/Jakarta');
        app(LinkedInSchedulingService::class)->scheduleAt($draft, $slot, 'test');

        $this->assertSame('2026-05-13 05:00:00', $this->raw('linkedin_posts', $draft->id, 'scheduled_at'));
        $this->assertSame('2026-05-13 05:00:00', $this->raw('linkedin_posts', $draft->id, 'cancel_window_ends_at'));
        $this->assertSame('2026-05-13 05:00:00', $this->raw('threads_posts', $threads->id, 'scheduled_at'));
    }

    public function test_utc_carbon_is_stored_unchanged(): void
    {
        $draft = $this->makeDraft();
        app(LinkedInSchedulingService::class)
            ->scheduleAt($draft, Carbon::parse('2026-05-13 05:00', 'UTC'), 'test');

        $this->assertSame('2026-05-13 05:00:00', $this->raw('linkedin_posts', $draft->id, 'scheduled_at'));
    }

    public function test_caller_carbon_is_not_mutated(): void
    {
        $draft = $this->makeDraft();
        $slot = Carbon::parse('2026-05-13 12:00', 'Asia/Jakarta');
        app(LinkedInSchedulingService::class)->scheduleAt($draft, $slot, 'test');

        $this->assertSame('Asia/Jakarta', $slot->getTimezone()->getName());
        $this->assertSame('2026-05-13 12:00:00', $slot->format('Y-m-d H:i:s'));
    }

    public function test_string_and_null_values_are_unchanged(): void
    {
        $draft = $this->makeDraft();
        $draft->update(['scheduled_at' => '2026-05-13 12:00:00', 'cancel_window_ends_at' => null]);

        $this->assertSame('2026-05-13 12:00:00', $this->raw('linkedin_posts', $draft->id, 'scheduled_at'));
        $this->assertNull($this->raw('linkedin_posts', $draft->id, 'cancel_window_ends_at'));
    }
}
