<?php

namespace Tests\Feature;

use App\Models\InstagramPost;
use App\Models\LinkedInPost;
use App\Models\TiktokPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase D — default-carousel plan (2026-06-09).
 * The widened reaper gate fans out a CAROUSEL draft as soon as its slides are
 * 'done', regardless of whether the LinkedIn FSM reached awaiting_publish.
 * Terminal drafts stay excluded; targeted scans bypass the status gate.
 */
class CrossPostScanWidenedGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // platform Generate*Post jobs are dispatched on fan-out
    }

    private function doneSlides(): array
    {
        return [
            ['slide_number' => 1, 'layout_hint' => 'cover', 'image_status' => 'done', 'image_url' => 'https://x/1.png', 'image_job_uuid' => 'u1'],
            ['slide_number' => 2, 'layout_hint' => 'cta', 'image_status' => 'done', 'image_url' => 'https://x/2.png', 'image_job_uuid' => 'u2'],
        ];
    }

    public function test_carousel_in_validating_with_slides_done_fans_out(): void
    {
        $draft = LinkedInPost::factory()->create([
            'format' => 'carousel',
            'status' => 'validating',
            'carousel_slides' => $this->doneSlides(),
            'updated_at' => now(),
        ]);

        // --min-virality=0 isolates the status-gate widening from the virality gate.
        Artisan::call('social-cross-post:scan', ['--min-virality' => 0]);

        $this->assertTrue(InstagramPost::where('linkedin_post_id', $draft->id)->exists());
        $this->assertTrue(TiktokPost::where('linkedin_post_id', $draft->id)->exists());
    }

    public function test_cancelled_carousel_is_skipped(): void
    {
        $draft = LinkedInPost::factory()->create([
            'format' => 'carousel',
            'status' => 'cancelled',
            'carousel_slides' => $this->doneSlides(),
            'updated_at' => now(),
        ]);

        Artisan::call('social-cross-post:scan', ['--min-virality' => 0]);

        $this->assertFalse(InstagramPost::where('linkedin_post_id', $draft->id)->exists());
    }

    public function test_carousel_with_unrendered_slides_is_not_fanned_out(): void
    {
        $draft = LinkedInPost::factory()->create([
            'format' => 'carousel',
            'status' => 'validating',
            'carousel_slides' => [
                ['slide_number' => 1, 'layout_hint' => 'cover', 'image_status' => 'done', 'image_job_uuid' => 'u1'],
                ['slide_number' => 2, 'layout_hint' => 'cta', 'image_status' => 'generating', 'image_job_uuid' => 'u2'],
            ],
            'updated_at' => now(),
        ]);

        Artisan::call('social-cross-post:scan', ['--min-virality' => 0]);

        $this->assertFalse(InstagramPost::where('linkedin_post_id', $draft->id)->exists());
    }

    public function test_skips_draft_when_fanout_lock_is_held(): void
    {
        $draft = LinkedInPost::factory()->create([
            'format' => 'carousel',
            'status' => 'validating',
            'carousel_slides' => $this->doneSlides(),
            'updated_at' => now(),
        ]);

        // Simulate a concurrent scan already processing this draft.
        $lock = Cache::lock("crosspost-fanout:{$draft->id}", 60);
        $this->assertTrue($lock->get());

        Artisan::call('social-cross-post:scan', ['--min-virality' => 0]);

        // Lock held → this scan must skip, no siblings created.
        $this->assertFalse(InstagramPost::where('linkedin_post_id', $draft->id)->exists());

        $lock->release();
    }

    public function test_targeted_scan_bypasses_status_gate(): void
    {
        $draft = LinkedInPost::factory()->create([
            'format' => 'carousel',
            'status' => 'manual_review',
            'carousel_slides' => $this->doneSlides(),
            'updated_at' => now()->subDays(30), // outside the reaper window — only targeted reaches it
        ]);

        Artisan::call('social-cross-post:scan', ['--draft-id' => $draft->id]);

        $this->assertTrue(InstagramPost::where('linkedin_post_id', $draft->id)->exists());
    }
}
