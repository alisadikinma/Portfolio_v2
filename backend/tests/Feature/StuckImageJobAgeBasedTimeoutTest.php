<?php

namespace Tests\Feature;

use App\Jobs\RetryImageSegmentJob;
use App\Models\ContentIdea;
use App\Models\ImageGenerationJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Regression: ProcessPendingImages must treat jobs stuck in 'processing'
 * longer than MAX_JOB_AGE_MINUTES as failed, even when GeminiGen's
 * history API keeps returning status != 2/3. Previously the poller
 * would log "Still processing" forever, leaving the admin UI stuck on
 * "Generating…" with no way to unstick without manual DB intervention.
 *
 * Symptom reported by user: GeminiGen failed internally without firing
 * the webhook, poller saw status=1 from history API, job stayed in
 * 'processing' indefinitely.
 */
class StuckImageJobAgeBasedTimeoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.geminigen.api_key', 'test-key');
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }
    }

    /** @test */
    public function poller_flips_stale_job_to_failed_after_max_age(): void
    {
        Queue::fake();

        // GeminiGen keeps reporting "still processing" (status=1)
        Http::fake([
            'api.snapgen.ai/uapi/v1/history/*' => Http::response([
                'status' => 1,
            ], 200),
        ]);

        $idea = ContentIdea::create([
            'title' => 'Stuck job idea',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'generating_images',
            'auto_mode' => true,
            'generated_article' => [
                'image_prompts' => [
                    [
                        'type' => 'cover',
                        'prompt_text' => 'Cover',
                        'status' => 'generating',
                        'retry_count' => 0,
                        'failure_history' => [],
                        'terminal_at' => null,
                        'variations' => [
                            ['url' => null, 'job_uuid' => 'stuck-uuid', 'status' => 'generating'],
                        ],
                    ],
                ],
            ],
        ]);

        // Job was created 15 minutes ago — well past the 10-min threshold
        $job = ImageGenerationJob::create([
            'uuid' => 'stuck-uuid',
            'type' => 'hero',
            'prompt' => 'Cover',
            'status' => 'processing',
        ]);
        $job->created_at = now()->subMinutes(15);
        $job->save();

        $this->artisan('blog:process-images')->assertExitCode(0);

        $job->refresh();
        $this->assertSame('failed', $job->status, 'Stuck job must be flipped to failed');
        $this->assertStringContainsString('Stuck in', $job->error_message ?? '');

        $idea->refresh();
        $segment = $idea->generated_article['image_prompts'][0];
        $this->assertSame(1, $segment['retry_count'], 'handleSegmentFailure must have been driven');
        $this->assertNotEmpty($segment['failure_history']);
    }

    /** @test */
    public function poller_leaves_fresh_job_alone_when_still_processing(): void
    {
        Queue::fake();

        Http::fake([
            'api.snapgen.ai/uapi/v1/history/*' => Http::response(['status' => 1], 200),
        ]);

        $idea = ContentIdea::create([
            'title' => 'Fresh job idea',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'generating_images',
            'auto_mode' => true,
            'generated_article' => [
                'image_prompts' => [
                    [
                        'type' => 'cover',
                        'prompt_text' => 'Cover',
                        'status' => 'generating',
                        'retry_count' => 0,
                        'variations' => [
                            ['url' => null, 'job_uuid' => 'fresh-uuid', 'status' => 'generating'],
                        ],
                    ],
                ],
            ],
        ]);

        $job = ImageGenerationJob::create([
            'uuid' => 'fresh-uuid',
            'type' => 'hero',
            'prompt' => 'Cover',
            'status' => 'processing',
        ]);
        // Job only 2 min old — below the 10-min threshold
        $job->created_at = now()->subMinutes(2);
        $job->save();

        $this->artisan('blog:process-images')->assertExitCode(0);

        $job->refresh();
        $this->assertSame('processing', $job->status, 'Fresh job must not be prematurely failed');
    }

    /** @test */
    public function poller_flips_stale_job_to_failed_on_persistent_http_errors(): void
    {
        Queue::fake();

        // GeminiGen API returning 502 (persistent outage scenario)
        Http::fake([
            'api.snapgen.ai/uapi/v1/history/*' => Http::response(null, 502),
        ]);

        $idea = ContentIdea::create([
            'title' => 'HTTP-err idea',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'generating_images',
            'auto_mode' => false,
            'generated_article' => [
                'image_prompts' => [
                    [
                        'type' => 'cover',
                        'prompt_text' => 'Cover',
                        'status' => 'generating',
                        'retry_count' => 0,
                        'variations' => [
                            ['url' => null, 'job_uuid' => 'http-err-uuid', 'status' => 'generating'],
                        ],
                    ],
                ],
            ],
        ]);

        $job = ImageGenerationJob::create([
            'uuid' => 'http-err-uuid',
            'type' => 'hero',
            'prompt' => 'Cover',
            'status' => 'processing',
        ]);
        $job->created_at = now()->subMinutes(15);
        $job->save();

        $this->artisan('blog:process-images')->assertExitCode(0);

        $job->refresh();
        $this->assertSame('failed', $job->status);
        $this->assertStringContainsString('GeminiGen HTTP 502', $job->error_message ?? '');
    }
}
