<?php

namespace Tests\Feature;

use App\Jobs\GenerateHookVideo;
use App\Models\Category;
use App\Models\InstagramPost;
use App\Models\Post;
use App\Services\GeminiGenVideoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase D+E — crosspost:poll-hook-videos.
 *
 * GeminiGen never fires webhooks, so this per-minute command is the SOLE
 * completion driver: it polls /history/{uuid} for IG drafts whose hook video
 * is generating (done = generated_video[0].video_url → finalize/crop/strip →
 * status=done; explicit failure → status=failed) AND recovers failed videos by
 * bounded re-dispatch (cap) so a bad frame can't burn GROK credits forever.
 */
class PollHookVideosCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.geminigen.api_key', 'test-key');
    }

    private function makeIg(array $attrs): InstagramPost
    {
        $category = Category::create(['name' => 'T', 'slug' => 'c-'.uniqid()]);
        $post = Post::create([
            'category_id' => $category->id,
            'slug' => 'p-'.uniqid(),
            'title' => 'T',
            'content' => 'Body.',
        ]);

        return InstagramPost::create(array_merge([
            'post_id' => $post->id,
            'status' => 'awaiting_review',
            'caption' => 'c',
            'hashtags' => [],
        ], $attrs));
    }

    public function test_completion_done_finalizes_and_marks_done(): void
    {
        $ig = $this->makeIg(['hook_video_status' => 'generating', 'hook_video_job_uuid' => 'u1']);

        Http::fake([
            '*/history/u1' => Http::response([
                'status' => 2,
                'generated_video' => [['video_url' => 'https://edge-files.geminigen.ai/v.mp4']],
            ], 200),
        ]);

        $this->mock(GeminiGenVideoService::class, function ($m) use ($ig) {
            $m->shouldReceive('finalizeHookVideo')
                ->once()
                ->andReturn('https://alisadikinma.com/storage/linkedin-carousel/grok-hook-'.$ig->id.'.mp4');
        });

        $this->artisan('crosspost:poll-hook-videos')->assertExitCode(0);

        $ig->refresh();
        $this->assertSame('done', $ig->hook_video_status);
        $this->assertStringContainsString('grok-hook-', $ig->hook_video_url);
    }

    public function test_completion_explicit_failure_marks_failed(): void
    {
        $ig = $this->makeIg(['hook_video_status' => 'generating', 'hook_video_job_uuid' => 'u2']);

        Http::fake([
            '*/history/u2' => Http::response(['status' => 3, 'error_message' => 'render failed'], 200),
        ]);

        $this->artisan('crosspost:poll-hook-videos')->assertExitCode(0);

        $ig->refresh();
        $this->assertSame('failed', $ig->hook_video_status);
        $this->assertNotNull($ig->hook_video_error);
    }

    public function test_in_progress_poll_with_empty_error_keys_stays_generating(): void
    {
        // GeminiGen ALWAYS returns error_code/error_message keys, set to "" while
        // a render is still in progress. The old isset()-based detection treated
        // the present-but-empty key as a failure and marked every hook video
        // 'failed' within ~1 min of dispatch, before GROK finished. Regression:
        // an in-progress poll (status=1, percentage<100, empty error strings, no
        // video_url) must stay 'generating'.
        $ig = $this->makeIg(['hook_video_status' => 'generating', 'hook_video_job_uuid' => 'u-prog']);

        Http::fake([
            '*/history/u-prog' => Http::response([
                'status' => 1,
                'status_percentage' => 50,
                'error_code' => '',
                'error_message' => '',
                'generated_video' => [],
            ], 200),
        ]);

        $this->artisan('crosspost:poll-hook-videos')->assertExitCode(0);

        $ig->refresh();
        $this->assertSame('generating', $ig->hook_video_status);
        $this->assertNull($ig->hook_video_url);
    }

    public function test_recovery_redispatches_failed_under_cap(): void
    {
        Bus::fake();
        Http::fake();

        $ig = $this->makeIg(['hook_video_status' => 'failed', 'hook_video_error' => 'x', 'hook_video_retry_count' => 0]);
        // Past the failed-retry cooldown (timestamps untouched by query update()).
        InstagramPost::where('id', $ig->id)->update(['updated_at' => now()->subMinutes(10)]);

        $this->artisan('crosspost:poll-hook-videos')->assertExitCode(0);

        Bus::assertDispatched(GenerateHookVideo::class, fn ($j) => $j->instagramPostId === $ig->id);
        $ig->refresh();
        $this->assertSame(1, $ig->hook_video_retry_count);
        $this->assertSame('pending', $ig->hook_video_status);
    }

    public function test_recovery_stops_at_cap(): void
    {
        Bus::fake();
        Http::fake();

        $ig = $this->makeIg(['hook_video_status' => 'failed', 'hook_video_error' => 'x', 'hook_video_retry_count' => 2]);
        InstagramPost::where('id', $ig->id)->update(['updated_at' => now()->subMinutes(10)]);

        $this->artisan('crosspost:poll-hook-videos')->assertExitCode(0);

        Bus::assertNotDispatched(GenerateHookVideo::class);
        $ig->refresh();
        $this->assertSame('failed', $ig->hook_video_status);
    }
}
