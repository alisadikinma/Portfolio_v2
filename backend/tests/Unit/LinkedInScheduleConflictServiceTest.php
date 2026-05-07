<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\LinkedInPostStatus;
use App\Models\LinkedInPost;
use App\Models\Post;
use App\Services\LinkedInScheduleConflictService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LinkedInScheduleConflictServiceTest extends TestCase
{
    use RefreshDatabase;

    private LinkedInScheduleConflictService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new LinkedInScheduleConflictService();
    }

    /** @test */
    public function it_detects_conflict_within_30_minute_window(): void
    {
        $proposed = Carbon::now()->addHours(2);

        LinkedInPost::factory()->create([
            'status' => LinkedInPostStatus::AwaitingPublish->value,
            'scheduled_at' => $proposed->copy()->subMinutes(15),
        ]);

        $this->assertTrue($this->service->hasConflict($proposed));
    }

    /** @test */
    public function it_returns_no_conflict_outside_window(): void
    {
        $proposed = Carbon::now()->addHours(2);

        LinkedInPost::factory()->create([
            'status' => LinkedInPostStatus::AwaitingPublish->value,
            'scheduled_at' => $proposed->copy()->subMinutes(45),
        ]);

        $this->assertFalse($this->service->hasConflict($proposed));
    }

    /** @test */
    public function it_excludes_specified_draft_id(): void
    {
        $proposed = Carbon::now()->addHours(2);

        $draft = LinkedInPost::factory()->create([
            'status' => LinkedInPostStatus::AwaitingPublish->value,
            'scheduled_at' => $proposed->copy()->subMinutes(15),
        ]);

        $this->assertFalse($this->service->hasConflict($proposed, $draft->id));
    }

    /** @test */
    public function it_only_considers_awaiting_publish_and_published_statuses(): void
    {
        $proposed = Carbon::now()->addHours(2);

        $nonConflictingStatuses = [
            LinkedInPostStatus::ManualReview->value,
            LinkedInPostStatus::Cancelled->value,
            LinkedInPostStatus::Failed->value,
        ];

        foreach ($nonConflictingStatuses as $status) {
            LinkedInPost::factory()->create([
                'status' => $status,
                'scheduled_at' => $proposed->copy()->subMinutes(10),
            ]);
        }

        $this->assertFalse($this->service->hasConflict($proposed));
    }

    /** @test */
    public function it_finds_multiple_conflicts_with_minutes_apart_diff(): void
    {
        $proposed = Carbon::now()->addHours(2);

        LinkedInPost::factory()->create([
            'status' => LinkedInPostStatus::AwaitingPublish->value,
            'scheduled_at' => $proposed->copy()->subMinutes(10),
        ]);

        LinkedInPost::factory()->create([
            'status' => LinkedInPostStatus::Published->value,
            'scheduled_at' => $proposed->copy()->addMinutes(20),
        ]);

        $conflicts = $this->service->findConflicts($proposed);

        $this->assertCount(2, $conflicts);
        $minutesApart = $conflicts->pluck('minutes_apart')->sort()->values()->all();
        $this->assertSame(10, $minutesApart[0]);
        $this->assertSame(20, $minutesApart[1]);
    }
}
