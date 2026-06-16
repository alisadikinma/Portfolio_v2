<?php

namespace Tests\Unit;

use App\Models\RepurposeJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Per-platform caption resolution for video_rebrand Zernio publishing. The
 * editor + the publisher BOTH read captionFor() so what the operator edits is
 * exactly what ships (the bug was the publisher using the raw source caption
 * while the draft showed the branded one). Resolution:
 *   rewritten["caption_$platform"] → rewritten['caption'] → igCaption()
 * always non-empty (a video post never ships caption-less).
 */
class RepurposeJobCaptionForTest extends TestCase
{
    use RefreshDatabase;

    public function test_caption_for_prefers_per_platform_then_branded_then_source(): void
    {
        $job = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand',
            'extracted' => ['caption' => 'SOURCE caption from IG'],
            'rewritten' => [
                'caption' => 'BRANDED caption',
                'caption_instagram' => 'IG-specific caption',
            ],
        ]);

        // per-platform wins
        $this->assertSame('IG-specific caption', $job->captionFor('instagram'));
        // threads has no per-platform key → falls back to branded
        $this->assertSame('BRANDED caption', $job->captionFor('threads'));
    }

    public function test_caption_for_falls_back_to_source_when_no_branded(): void
    {
        $job = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand',
            'extracted' => ['caption' => 'SOURCE caption'],
            'rewritten' => null,
        ]);

        $this->assertSame('SOURCE caption', $job->captionFor('instagram'));
        $this->assertSame('SOURCE caption', $job->captionFor('threads'));
    }

    public function test_set_caption_persists_and_caps_threads_at_500(): void
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'rewritten' => null]);

        $job->setCaption('instagram', 'My IG caption');
        $long = str_repeat('x', 600);
        $job->setCaption('threads', $long);

        $fresh = $job->fresh();
        $this->assertSame('My IG caption', $fresh->captionFor('instagram'));
        $this->assertSame(500, mb_strlen($fresh->captionFor('threads')));
    }
}
