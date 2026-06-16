<?php

namespace Tests\Feature;

use App\Models\RepurposeJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase D — PUT /admin/repurpose/{id}/captions edits the per-platform IG +
 * Threads captions the Zernio publish ships. Threads capped at 500; gated to
 * video_rebrand; auth required.
 */
class RepurposeCaptionUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create();
    }

    public function test_updates_both_captions_and_caps_threads(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'rewritten' => null]);

        $res = $this->actingAs($this->actor(), 'sanctum')
            ->putJson("/api/admin/repurpose/{$job->id}/captions", [
                'instagram' => 'My IG caption',
                'threads' => str_repeat('y', 600),
            ])
            ->assertOk();

        $this->assertSame('My IG caption', $res->json('data.caption_instagram'));
        $this->assertSame(500, mb_strlen($res->json('data.caption_threads')));

        $fresh = $job->fresh();
        $this->assertSame('My IG caption', $fresh->captionFor('instagram'));
        $this->assertSame(500, mb_strlen($fresh->captionFor('threads')));
    }

    public function test_partial_update_only_touches_given_platform(): void
    {
        $job = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand',
            'rewritten' => ['caption_instagram' => 'IG keep', 'caption_threads' => 'TH keep'],
        ]);

        $this->actingAs($this->actor(), 'sanctum')
            ->putJson("/api/admin/repurpose/{$job->id}/captions", ['threads' => 'TH new'])
            ->assertOk();

        $fresh = $job->fresh();
        $this->assertSame('IG keep', $fresh->captionFor('instagram')); // untouched
        $this->assertSame('TH new', $fresh->captionFor('threads'));
    }

    public function test_rejects_non_video_rebrand(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'carousel']);

        $this->actingAs($this->actor(), 'sanctum')
            ->putJson("/api/admin/repurpose/{$job->id}/captions", ['instagram' => 'x'])
            ->assertStatus(422);
    }

    public function test_requires_auth(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand']);
        $this->putJson("/api/admin/repurpose/{$job->id}/captions", ['instagram' => 'x'])
            ->assertStatus(401);
    }
}
