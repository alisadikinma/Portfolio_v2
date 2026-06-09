<?php

namespace Tests\Feature;

use App\Jobs\GenerateFacebookPost;
use App\Jobs\GenerateInstagramPost;
use App\Jobs\GenerateThreadsPost;
use App\Jobs\GenerateTiktokPost;
use App\Models\LinkedInPost;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase E — default-carousel plan (2026-06-09).
 * Cross-post fan-out must dispatch the 4 platform caption-gen jobs onto the
 * dedicated `social-crosspost` queue so a worker pool can run them in parallel.
 */
class CrossPostQueueRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_fanout_dispatches_platform_jobs_on_social_crosspost_queue(): void
    {
        Queue::fake();

        $draft = LinkedInPost::factory()->create([
            'format' => 'carousel',
            'status' => 'manual_review',
            'carousel_slides' => [
                ['slide_number' => 1, 'layout_hint' => 'cover', 'image_status' => 'done', 'image_url' => 'https://x/1.png', 'image_job_uuid' => 'u1'],
                ['slide_number' => 2, 'layout_hint' => 'cta', 'image_status' => 'done', 'image_url' => 'https://x/2.png', 'image_job_uuid' => 'u2'],
            ],
            'updated_at' => now(),
        ]);

        Artisan::call('social-cross-post:scan', ['--draft-id' => $draft->id]);

        Queue::assertPushedOn('social-crosspost', GenerateInstagramPost::class);
        Queue::assertPushedOn('social-crosspost', GenerateTiktokPost::class);
        Queue::assertPushedOn('social-crosspost', GenerateThreadsPost::class);
        Queue::assertPushedOn('social-crosspost', GenerateFacebookPost::class);
    }
}
