<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\LinkedInPostStatus;
use App\Jobs\GenerateLinkedInCarouselImages;
use App\Models\ContentIdea;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Models\PostingTimeRule;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class AutoScheduleManualReviewLinkedInPostsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-05-11 04:30:00', 'Asia/Jakarta'));
    }

    private function enableKillSwitch(bool $enabled = true): void
    {
        Setting::updateOrCreate(
            ['group' => 'linkedin', 'key' => 'linkedin_auto_approve_enabled'],
            ['value' => $enabled ? 'true' : 'false', 'type' => 'text']
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
            'last_researched_at' => Carbon::now(),
        ]);
    }

    private function makeManualReviewDraft(?int $virality = null, string $format = 'text'): LinkedInPost
    {
        $post = Post::factory()->create();
        if ($virality !== null) {
            ContentIdea::factory()->create([
                'result_post_id' => $post->id,
                'virality_score' => $virality,
            ]);
        }
        return LinkedInPost::factory()->create([
            'post_id' => $post->id,
            'status' => LinkedInPostStatus::ManualReview->value,
            'format' => $format,
        ]);
    }

    /** @test */
    public function command_skips_when_kill_switch_off(): void
    {
        $this->enableKillSwitch(false);
        $draft = $this->makeManualReviewDraft();

        $this->artisan('linkedin:auto-schedule')
            ->expectsOutputToContain('kill switch off')
            ->assertSuccessful();

        $this->assertSame(LinkedInPostStatus::ManualReview->value, $draft->fresh()->status);
    }

    /** @test */
    public function command_promotes_high_virality_drafts_first(): void
    {
        $this->enableKillSwitch();
        $todayDow = Carbon::now()->dayOfWeek;

        // Two ideal slots today: 09:00 (earliest) + 17:00
        $this->seedRule($todayDow, 9, 90);
        $this->seedRule($todayDow, 17, 88);

        $low = $this->makeManualReviewDraft(40);
        $high = $this->makeManualReviewDraft(90);
        $mid = $this->makeManualReviewDraft(60);

        $this->artisan('linkedin:auto-schedule')->assertSuccessful();

        // Highest virality wins the earliest slot (09:00)
        $this->assertSame(LinkedInPostStatus::AwaitingPublish->value, $high->fresh()->status);
        $this->assertSame(9, $high->fresh()->cancel_window_ends_at->timezone('Asia/Jakarta')->hour);

        // Mid takes the next slot (17:00)
        $this->assertSame(LinkedInPostStatus::AwaitingPublish->value, $mid->fresh()->status);
        $this->assertSame(17, $mid->fresh()->cancel_window_ends_at->timezone('Asia/Jakarta')->hour);

        // Low virality goes to tomorrow's first slot — only 2 slots seeded today
        // so it walks forward. Since tomorrow has no rules seeded, low stays
        // manual_review (lookahead exhausted). This is acceptable behavior.
        $this->assertSame(LinkedInPostStatus::ManualReview->value, $low->fresh()->status);
    }

    /** @test */
    public function command_skips_drafts_demoted_from_kill_switch_within_24h(): void
    {
        $this->enableKillSwitch();
        $todayDow = Carbon::now()->dayOfWeek;
        $this->seedRule($todayDow, 9, 90);

        $draft = $this->makeManualReviewDraft(80);
        $draft->pipeline_state_log = [[
            'from' => 'awaiting_publish',
            'to' => 'manual_review',
            'reason' => 'kill_switch_demotion',
            'timestamp' => Carbon::now()->subHours(2)->toIso8601String(),
        ]];
        $draft->save();

        $this->artisan('linkedin:auto-schedule')->assertSuccessful();

        $this->assertSame(LinkedInPostStatus::ManualReview->value, $draft->fresh()->status);
    }

    /** @test */
    public function command_dispatches_carousel_image_gen_when_slides_pending(): void
    {
        Bus::fake([GenerateLinkedInCarouselImages::class]);

        $this->enableKillSwitch();
        $todayDow = Carbon::now()->dayOfWeek;
        $this->seedRule($todayDow, 9, 90);

        $draft = $this->makeManualReviewDraft(80, 'carousel');
        $draft->carousel_slides = [
            ['slide_number' => 1, 'image_status' => 'pending', 'image_url' => null],
            ['slide_number' => 2, 'image_status' => 'pending', 'image_url' => null],
        ];
        $draft->save();

        $this->artisan('linkedin:auto-schedule')->assertSuccessful();

        Bus::assertDispatched(GenerateLinkedInCarouselImages::class, function ($job) use ($draft) {
            return $job->draftId === $draft->id;
        });
    }

    /** @test */
    public function dry_run_logs_planned_promotions_without_state_change(): void
    {
        $this->enableKillSwitch();
        $todayDow = Carbon::now()->dayOfWeek;
        $this->seedRule($todayDow, 9, 90);

        $draft = $this->makeManualReviewDraft(80);

        $this->artisan('linkedin:auto-schedule', ['--dry-run' => true])
            ->expectsOutputToContain('would promote draft')
            ->assertSuccessful();

        // Status unchanged — dry-run never persisted
        $this->assertSame(LinkedInPostStatus::ManualReview->value, $draft->fresh()->status);
        $this->assertNull($draft->fresh()->cancel_window_ends_at);
    }
}
