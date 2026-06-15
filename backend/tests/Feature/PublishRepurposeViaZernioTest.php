<?php

namespace Tests\Feature;

use App\Jobs\PublishRepurposeViaZernio;
use App\Models\RepurposeJob;
use App\Models\RepurposeVideoSlide;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Phase C — PublishRepurposeViaZernio: publishNow + scheduledFor, idempotency,
 * 4xx-fail, master-switch gate. Status lives in repurpose_jobs.zernio_publish.
 */
class PublishRepurposeViaZernioTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'https://alisadikinma.com']);
        config(['social-cross-post.zernio.enabled' => true]);
        Setting::create(['group' => 'zernio', 'key' => 'zernio_api_key_igtt', 'value' => Crypt::encryptString('sk_igtt')]);
        Setting::create(['group' => 'zernio', 'key' => 'zernio_instagram_account_id', 'value' => 'ig_acc']);
    }

    private function job(): RepurposeJob
    {
        $job = RepurposeJob::factory()->create([
            'mode' => 'video_rebrand', 'status' => 'drafted',
            'extracted' => ['caption' => 'cap'],
        ]);
        foreach ([0, 1, 2] as $i) {
            RepurposeVideoSlide::create([
                'repurpose_job_id' => $job->id, 'slide_index' => $i, 'role' => $i === 0 ? 'hook' : 'tool',
                'composited_status' => 'done', 'composited_path' => "https://alisadikinma.com/storage/repurpose/{$job->id}/composited/s{$i}.mp4",
            ]);
        }

        return $job->fresh();
    }

    public function test_publishes_now_persists_post_id_and_url(): void
    {
        Http::fake(['zernio.com/api/v1/posts' => Http::response([
            'post' => ['_id' => 'z-1', 'platforms' => [['platformPostUrl' => 'https://instagram.com/p/abc']]],
        ], 201)]);

        $job = $this->job();
        PublishRepurposeViaZernio::dispatchSync($job->id, 'instagram');

        $state = $job->fresh()->zernioPublishState('instagram');
        $this->assertSame('published', $state['status']);
        $this->assertSame('z-1', $state['post_id']);
        $this->assertSame('https://instagram.com/p/abc', $state['url']);
        Http::assertSent(fn ($r) => ($r['publishNow'] ?? false) === true);
    }

    public function test_scheduled_future_sends_scheduled_for(): void
    {
        Http::fake(['zernio.com/api/v1/posts' => Http::response(['post' => ['_id' => 'z-2']], 201)]);

        $job = $this->job();
        PublishRepurposeViaZernio::dispatchSync($job->id, 'instagram', now()->addDay()->toIso8601String());

        Http::assertSent(fn ($r) => isset($r['scheduledFor']) && ! ($r['publishNow'] ?? false));
    }

    public function test_4xx_marks_failed(): void
    {
        Http::fake(['zernio.com/api/v1/posts' => Http::response(['error' => 'bad'], 400)]);

        $job = $this->job();
        PublishRepurposeViaZernio::dispatchSync($job->id, 'instagram');

        $this->assertSame('failed', $job->fresh()->zernioPublishState('instagram')['status']);
    }

    public function test_idempotent_skip_when_already_published(): void
    {
        Http::fake();
        $job = $this->job();
        $job->update(['zernio_publish' => ['instagram' => ['status' => 'published', 'post_id' => 'already']]]);

        PublishRepurposeViaZernio::dispatchSync($job->id, 'instagram');

        Http::assertNothingSent();
        $this->assertSame('already', $job->fresh()->zernioPublishState('instagram')['post_id']);
    }

    public function test_master_switch_off_skips(): void
    {
        config(['social-cross-post.zernio.enabled' => false]);
        Http::fake();

        $job = $this->job();
        PublishRepurposeViaZernio::dispatchSync($job->id, 'instagram');

        Http::assertNothingSent();
        $this->assertNull($job->fresh()->zernioPublishState('instagram'));
    }
}
