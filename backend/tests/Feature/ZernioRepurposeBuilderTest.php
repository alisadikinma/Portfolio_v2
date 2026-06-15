<?php

namespace Tests\Feature;

use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Models\Setting;
use App\Services\ZernioPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase B — ZernioPayloadBuilder::buildRepurposeVideoCarousel (IG + Threads).
 */
class ZernioRepurposeBuilderTest extends TestCase
{
    use RefreshDatabase;

    private function jobWithSlides(int $n): RepurposeJob
    {
        $job = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand', 'status' => 'drafted',
            'extracted' => ['caption' => 'My carousel caption'],
        ]);
        for ($i = 0; $i < $n; $i++) {
            RepurposeVideoSlide::create([
                'repurpose_job_id' => $job->id, 'slide_index' => $i,
                'role' => $i === 0 ? 'hook' : 'tool',
                'composited_status' => 'done', 'composited_path' => "https://alisadikinma.com/storage/repurpose/{$job->id}/composited/slide_{$i}.mp4",
            ]);
        }

        return $job->fresh();
    }

    public function test_instagram_builds_video_media_items(): void
    {
        Setting::create(['group' => 'zernio', 'key' => 'zernio_instagram_account_id', 'value' => 'ig_acc']);
        $job = $this->jobWithSlides(9);

        $payload = (new ZernioPayloadBuilder)->buildRepurposeVideoCarousel($job, 'instagram');

        $this->assertCount(9, $payload['mediaItems']);
        $this->assertSame('video', $payload['mediaItems'][0]['type']);
        $this->assertSame('instagram', $payload['platforms'][0]['platform']);
        $this->assertSame('ig_acc', $payload['platforms'][0]['accountId']);
        $this->assertSame('My carousel caption', $payload['content']);
    }

    public function test_threads_caption_capped_at_500(): void
    {
        Setting::create(['group' => 'zernio', 'key' => 'zernio_threads_account_id', 'value' => 'th_acc']);
        $job = $this->jobWithSlides(3);
        $job->update(['extracted' => ['caption' => str_repeat('x', 800)]]);

        $payload = (new ZernioPayloadBuilder)->buildRepurposeVideoCarousel($job->fresh(), 'threads');

        $this->assertSame(500, mb_strlen($payload['content']));
        $this->assertSame('threads', $payload['platforms'][0]['platform']);
    }

    public function test_caps_media_items_at_10(): void
    {
        Setting::create(['group' => 'zernio', 'key' => 'zernio_instagram_account_id', 'value' => 'ig_acc']);
        $job = $this->jobWithSlides(13);

        $payload = (new ZernioPayloadBuilder)->buildRepurposeVideoCarousel($job, 'instagram');

        $this->assertCount(10, $payload['mediaItems']);
    }

    public function test_throws_when_no_composited_slides(): void
    {
        Setting::create(['group' => 'zernio', 'key' => 'zernio_instagram_account_id', 'value' => 'ig_acc']);
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'drafted']);

        $this->expectException(\RuntimeException::class);
        (new ZernioPayloadBuilder)->buildRepurposeVideoCarousel($job, 'instagram');
    }
}
