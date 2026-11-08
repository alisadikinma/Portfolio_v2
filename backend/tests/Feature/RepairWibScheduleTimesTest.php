<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LinkedInPostStatus;
use App\Models\Category;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\ThreadsPost;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RepairWibScheduleTimesTest extends TestCase
{
    use RefreshDatabase;

    private Category $cat;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-07-01 00:00:00', 'UTC'));
        $this->cat = Category::create(['name' => 'T', 'slug' => 't-'.Str::random(4)]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Insert raw so the model trait cannot alter the stored value. */
    private function draft(string $status, string $rawScheduled, ?string $publishAt): LinkedInPost
    {
        $post = Post::create([
            'category_id' => $this->cat->id, 'title' => 'T', 'content' => 'B',
            'slug' => 'r-'.Str::random(6), 'published' => true, 'published_at' => now(),
        ]);
        $log = $publishAt === null
            ? []
            : [['from' => 'manual_review', 'to' => 'awaiting_publish', 'reason' => 'x', 'publish_at' => $publishAt]];

        $draft = LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'status' => $status,
            'format' => 'text',
            'pipeline_state_log' => $log,
        ]);
        DB::table('linkedin_posts')->where('id', $draft->id)->update([
            'scheduled_at' => $rawScheduled, 'cancel_window_ends_at' => $rawScheduled,
        ]);

        return $draft;
    }

    private function raw(string $table, int $id, string $col = 'scheduled_at'): ?string
    {
        return DB::table($table)->where('id', $id)->value($col);
    }

    public function test_seven_hour_shifted_row_is_fixed_with_siblings(): void
    {
        $d = $this->draft(LinkedInPostStatus::AwaitingPublish->value, '2026-07-06 12:00:00', '2026-07-06T12:00:00+07:00');
        $t = ThreadsPost::create([
            'linkedin_post_id' => $d->id, 'post_id' => $d->post_id,
            'status' => 'pending_generation', 'format' => 'text',
        ]);
        DB::table('threads_posts')->where('id', $t->id)->update(['scheduled_at' => '2026-07-06 12:00:00']);

        $this->artisan('linkedin:repair-wib-schedules')->assertSuccessful();

        $this->assertSame('2026-07-06 05:00:00', $this->raw('linkedin_posts', $d->id));
        $this->assertSame('2026-07-06 05:00:00', $this->raw('linkedin_posts', $d->id, 'cancel_window_ends_at'));
        $this->assertSame('2026-07-06 05:00:00', $this->raw('threads_posts', $t->id));
    }

    public function test_correct_row_is_untouched(): void
    {
        $d = $this->draft(LinkedInPostStatus::AwaitingPublish->value, '2026-07-06 05:00:00', '2026-07-06T12:00:00+07:00');

        $this->artisan('linkedin:repair-wib-schedules')->assertSuccessful();

        $this->assertSame('2026-07-06 05:00:00', $this->raw('linkedin_posts', $d->id));
    }

    public function test_row_without_logged_publish_at_is_listed_only(): void
    {
        $d = $this->draft(LinkedInPostStatus::AwaitingPublish->value, '2026-07-06 12:00:00', null);

        $this->artisan('linkedin:repair-wib-schedules')
            ->expectsOutputToContain('no logged publish_at')
            ->assertSuccessful();

        $this->assertSame('2026-07-06 12:00:00', $this->raw('linkedin_posts', $d->id));
    }

    public function test_row_differing_by_other_amount_is_listed_only(): void
    {
        $d = $this->draft(LinkedInPostStatus::AwaitingPublish->value, '2026-07-06 10:00:00', '2026-07-06T12:00:00+07:00');

        $this->artisan('linkedin:repair-wib-schedules')->assertSuccessful();

        $this->assertSame('2026-07-06 10:00:00', $this->raw('linkedin_posts', $d->id));
    }

    public function test_published_row_is_ignored(): void
    {
        $d = $this->draft(LinkedInPostStatus::Published->value, '2026-07-06 12:00:00', '2026-07-06T12:00:00+07:00');

        $this->artisan('linkedin:repair-wib-schedules')->assertSuccessful();

        $this->assertSame('2026-07-06 12:00:00', $this->raw('linkedin_posts', $d->id));
    }

    public function test_dry_run_writes_nothing(): void
    {
        $d = $this->draft(LinkedInPostStatus::AwaitingPublish->value, '2026-07-06 12:00:00', '2026-07-06T12:00:00+07:00');

        $this->artisan('linkedin:repair-wib-schedules', ['--dry-run' => true])
            ->expectsOutputToContain('would fix')
            ->assertSuccessful();

        $this->assertSame('2026-07-06 12:00:00', $this->raw('linkedin_posts', $d->id));
        $this->assertSame('2026-07-06 12:00:00', $this->raw('linkedin_posts', $d->id, 'cancel_window_ends_at'));
    }

    public function test_last_logged_publish_at_wins(): void
    {
        $d = $this->draft(LinkedInPostStatus::AwaitingPublish->value, '2026-07-07 12:00:00', '2026-07-06T12:00:00+07:00');
        DB::table('linkedin_posts')->where('id', $d->id)->update(['pipeline_state_log' => json_encode([
            ['to' => 'awaiting_publish', 'publish_at' => '2026-07-06T12:00:00+07:00'],
            ['to' => 'awaiting_publish', 'publish_at' => '2026-07-07T12:00:00+07:00'],
        ])]);

        $this->artisan('linkedin:repair-wib-schedules')->assertSuccessful();

        $this->assertSame('2026-07-07 05:00:00', $this->raw('linkedin_posts', $d->id));
    }
}
