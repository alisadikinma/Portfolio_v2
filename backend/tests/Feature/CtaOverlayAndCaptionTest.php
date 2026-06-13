<?php

namespace Tests\Feature;

use App\Jobs\FinalizeRepurpose;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Models\User;
use App\Services\TelegramNotificationService;
use App\Services\VideoRebrandComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase D (#3 CTA ask) — the CTA Veo clip gets a baked Follow/Save/Comment ask
 * overlay (VideoRebrandComposer::overlayCta), and the post caption carries the
 * same ask (FinalizeRepurpose) and is surfaced in the admin detail. No
 * comment→DM promise anywhere.
 *
 * See docs/plans/2026-06-13-video-rebrand-quality-pass.md Phase D.
 */
class CtaOverlayAndCaptionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Isolate the public disk per test — RefreshDatabase resets ids (job 1
        // reused) but not the real disk, so a stale slide_3.mp4 would leak across.
        Storage::fake('public');
    }

    public function test_overlay_cta_runs_ffmpeg_overlay_and_returns_public_url(): void
    {
        Process::fake(['*' => Process::result(output: '', exitCode: 0)]);

        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'generating_assets']);
        $cta = RepurposeVideoSlide::create([
            'repurpose_job_id' => $job->id, 'slide_index' => 3, 'role' => 'cta',
            'composited_status' => 'done',
        ]);

        // The finalized CTA clip must exist on the public disk (overlayCta reads it).
        $inRel = "repurpose/{$job->id}/composited/slide_3.mp4";
        Storage::disk('public')->put($inRel, 'fake-mp4-bytes');

        $url = app(VideoRebrandComposer::class)->overlayCta($cta, '/tmp/cta_overlay.png');

        $this->assertNotNull($url);
        $this->assertStringContainsString("repurpose/{$job->id}/composited/slide_3_cta.mp4", $url);

        Process::assertRan(function ($process) {
            $cmd = is_array($process->command) ? implode(' ', $process->command) : $process->command;

            return str_contains($cmd, 'overlay=0:0')
                && str_contains($cmd, 'cta_overlay.png')
                && str_contains($cmd, 'libx264');
        });
    }

    public function test_overlay_cta_returns_null_when_clip_missing(): void
    {
        Process::fake(['*' => Process::result(output: '', exitCode: 0)]);

        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'generating_assets']);
        $cta = RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 3, 'role' => 'cta']);

        // No finalized clip on disk → graceful null (caller keeps plain clip).
        $this->assertNull(app(VideoRebrandComposer::class)->overlayCta($cta, '/tmp/cta_overlay.png'));
    }

    private function composedVideoJob(): RepurposeJob
    {
        $job = RepurposeJob::factory()->create(['mode' => 'video_rebrand', 'status' => 'composed']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 0, 'role' => 'hook', 'composited_status' => 'done', 'composited_path' => 'https://x/0.mp4']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 1, 'role' => 'tool', 'header_title' => 'Stitch', 'composited_status' => 'done', 'composited_path' => 'https://x/1.mp4']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 2, 'role' => 'tool', 'header_title' => 'Cursor', 'composited_status' => 'done', 'composited_path' => 'https://x/2.mp4']);
        RepurposeVideoSlide::create(['repurpose_job_id' => $job->id, 'slide_index' => 3, 'role' => 'cta', 'composited_status' => 'done', 'composited_path' => 'https://x/3.mp4']);

        return $job;
    }

    public function test_finalize_builds_caption_with_ask_and_no_dm_promise(): void
    {
        $job = $this->composedVideoJob();

        $this->mock(TelegramNotificationService::class, function ($m) {
            $m->shouldReceive('sendRepurposeDrafted')->once();
        });

        (new FinalizeRepurpose($job->id))->handle();

        $caption = (string) ($job->refresh()->rewritten['caption'] ?? '');

        $this->assertNotSame('', $caption);
        $this->assertStringContainsStringIgnoringCase('Follow', $caption);
        $this->assertStringContainsStringIgnoringCase('Save', $caption);
        $this->assertStringContainsStringIgnoringCase('Comment', $caption);
        $this->assertStringContainsString('@alisadikinma', $caption);
        $this->assertStringContainsString('Stitch', $caption);
        // No comment→DM auto-delivery promise.
        $this->assertDoesNotMatchRegularExpression('/\bDM\b/i', $caption);
        $this->assertStringNotContainsStringIgnoringCase('inbox', $caption);
    }

    public function test_caption_surfaced_in_admin_detail(): void
    {
        $job = $this->composedVideoJob();

        $this->mock(TelegramNotificationService::class, function ($m) {
            $m->shouldReceive('sendRepurposeDrafted')->once();
        });
        (new FinalizeRepurpose($job->id))->handle();

        $user = User::factory()->create();
        $resp = $this->actingAs($user, 'sanctum')->getJson("/api/admin/repurpose/{$job->id}");

        $resp->assertStatus(200);
        $caption = $resp->json('data.caption');
        $this->assertNotNull($caption);
        $this->assertStringContainsStringIgnoringCase('Follow', $caption);
    }
}
