<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\CarouselDraft;
use App\Models\ContentIdea;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * ContentIdea and CarouselDraft scheduled_at must hit the DB in UTC, and the
 * admin UI's naive datetime-local value is a WIB wall-clock.
 */
class ContentIdeaCarouselScheduleTimezoneTest extends TestCase
{
    use RefreshDatabase;

    private function raw(string $table, int $id): ?string
    {
        return DB::table($table)->where('id', $id)->value('scheduled_at');
    }

    private function makeIdea(): ContentIdea
    {
        return ContentIdea::create(['title' => 'T', 'pillar' => 'general', 'priority' => 'low', 'status' => 'draft']);
    }

    public function test_wib_carbon_is_stored_as_utc_on_content_idea(): void
    {
        $idea = ContentIdea::create([
            'title' => 'T', 'pillar' => 'general', 'priority' => 'low', 'status' => 'draft',
            'scheduled_at' => Carbon::parse('2026-05-13 12:00', 'Asia/Jakarta'),
        ]);

        $this->assertSame('2026-05-13 05:00:00', $this->raw('content_ideas', $idea->id));
    }

    public function test_wib_carbon_is_stored_as_utc_on_carousel_draft(): void
    {
        $draft = CarouselDraft::create([
            'title' => 'T', 'status' => 'approved', 'target_platform' => 'instagram',
            'scheduled_at' => Carbon::parse('2026-05-13 12:00', 'Asia/Jakarta'),
        ]);

        $this->assertSame('2026-05-13 05:00:00', $this->raw('carousel_drafts', $draft->id));
    }

    public function test_offset_string_is_converted_to_utc(): void
    {
        $idea = ContentIdea::create([
            'title' => 'T', 'pillar' => 'general', 'priority' => 'low', 'status' => 'draft',
            'scheduled_at' => '2026-05-13T12:00:00+07:00',
        ]);

        $this->assertSame('2026-05-13 05:00:00', $this->raw('content_ideas', $idea->id));
    }

    public function test_idea_store_treats_naive_datetime_as_wib(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $id = $this->postJson('/api/admin/content-engine/ideas', [
            'title' => 'Naive', 'scheduled_at' => '2026-05-13T12:00',
        ])->assertStatus(201)->json('data.id');

        $this->assertSame('2026-05-13 05:00:00', $this->raw('content_ideas', $id));
    }

    public function test_idea_update_treats_naive_datetime_as_wib_and_offset_as_is(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $idea = $this->makeIdea();

        $this->putJson("/api/admin/content-engine/ideas/{$idea->id}", ['scheduled_at' => '2026-05-13 12:00:00'])->assertOk();
        $this->assertSame('2026-05-13 05:00:00', $this->raw('content_ideas', $idea->id));

        $this->putJson("/api/admin/content-engine/ideas/{$idea->id}", ['scheduled_at' => '2026-05-14T03:00:00Z'])->assertOk();
        $this->assertSame('2026-05-14 03:00:00', $this->raw('content_ideas', $idea->id));
    }

    public function test_carousel_schedule_treats_naive_datetime_as_wib(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $draft = CarouselDraft::create(['title' => 'T', 'status' => 'approved', 'target_platform' => 'instagram']);

        $this->postJson("/api/admin/carousel-drafts/{$draft->id}/schedule", ['scheduled_at' => '2026-05-13T12:00'])->assertOk();

        $this->assertSame('2026-05-13 05:00:00', $this->raw('carousel_drafts', $draft->id));
    }

    public function test_carousel_resource_serializes_schedule_with_explicit_offset(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $draft = CarouselDraft::create(['title' => 'T', 'status' => 'approved', 'target_platform' => 'instagram']);
        $this->postJson("/api/admin/carousel-drafts/{$draft->id}/schedule", ['scheduled_at' => '2026-05-13T12:00'])->assertOk();

        $value = $this->getJson("/api/admin/carousel-drafts/{$draft->id}")->json('data.scheduled_at');

        $this->assertMatchesRegularExpression('/(Z|[+-]\d{2}:\d{2})$/', $value);
        $this->assertSame('2026-05-13T05:00:00+00:00', Carbon::parse($value)->toIso8601String());
    }

    public function test_carousel_actions_accept_patch_as_the_admin_ui_sends(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $draft = CarouselDraft::create(['title' => 'T', 'status' => 'draft', 'target_platform' => 'instagram']);

        $this->patchJson("/api/admin/carousel-drafts/{$draft->id}/approve")->assertOk();
        $this->patchJson("/api/admin/carousel-drafts/{$draft->id}/schedule", ['scheduled_at' => '2026-05-13T05:00:00.000Z'])->assertOk();

        $this->assertSame('2026-05-13 05:00:00', $this->raw('carousel_drafts', $draft->id));
    }
}
