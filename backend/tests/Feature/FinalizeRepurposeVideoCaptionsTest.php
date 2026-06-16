<?php

namespace Tests\Feature;

use App\Jobs\FinalizeRepurpose;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Services\TelegramNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B — finalize seeds BOTH per-platform caption keys so the draft editor +
 * the Zernio publisher have independent, non-empty starting values. IG gets the
 * full branded caption; Threads gets it pre-capped to 500.
 */
class FinalizeRepurposeVideoCaptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_finalize_seeds_per_platform_captions(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'composed', 'rewritten' => null]);
        // A long tool title list so the branded caption exceeds 500 (proves the cap).
        foreach (range(1, 30) as $i) {
            RepurposeVideoSlide::create([
                'repurpose_job_id' => $job->id, 'slide_index' => $i, 'role' => 'tool',
                'header_title' => "Tool Number {$i} With A Reasonably Long Name",
                'composited_status' => 'done', 'composited_path' => "https://x/{$i}.mp4",
            ]);
        }

        $this->mock(TelegramNotificationService::class, function ($m) {
            $m->shouldReceive('sendRepurposeDrafted')->once();
        });

        (new FinalizeRepurpose($job->id))->handle();

        $job->refresh();
        $ig = $job->captionFor('instagram');
        $th = $job->captionFor('threads');

        $this->assertNotSame('', trim($ig));
        $this->assertSame($job->rewritten['caption'], $ig, 'IG seeds the full branded caption');
        $this->assertLessThanOrEqual(500, mb_strlen($th), 'Threads pre-capped at 500');
        // Both stored as explicit per-platform keys, not just resolved.
        $this->assertArrayHasKey('caption_instagram', $job->rewritten);
        $this->assertArrayHasKey('caption_threads', $job->rewritten);
    }
}
