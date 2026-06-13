<?php

namespace Tests\Feature;

use App\Jobs\PublishViaPubler;
use App\Models\PostizChannel;
use App\Models\PostizPublishJob;
use App\Models\Setting;
use App\Services\PostizPublishDispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Phase B — slot dispatch branches to a Postiz job vs the Publer fallback.
 * See docs/plans/2026-06-13-postiz-local-node-crosspost.md.
 */
class PostizPublishDispatcherTest extends TestCase
{
    use RefreshDatabase;

    private function setPostizEnabled(bool $on): void
    {
        Setting::updateOrCreate(
            ['group' => 'postiz', 'key' => 'postiz_enabled'],
            ['value' => $on ? 'true' : 'false', 'type' => 'text']
        );
    }

    public function test_enabled_with_mapped_channel_creates_job_and_does_not_dispatch_publer(): void
    {
        Queue::fake();
        $this->setPostizEnabled(true);
        PostizChannel::factory()->create([
            'platform' => 'instagram',
            'postiz_integration_id' => '77',
            'enabled' => true,
        ]);

        app(PostizPublishDispatcher::class)->dispatchSibling('instagram', 555);

        $this->assertDatabaseHas('postiz_publish_jobs', [
            'platform' => 'instagram',
            'sibling_post_id' => 555,
            'status' => 'ready_to_publish',
            'postiz_integration_id' => '77',
        ]);
        Queue::assertNotPushed(PublishViaPubler::class);
    }

    public function test_disabled_dispatches_publer_and_creates_no_job(): void
    {
        Queue::fake();
        $this->setPostizEnabled(false);

        app(PostizPublishDispatcher::class)->dispatchSibling('instagram', 556);

        $this->assertDatabaseMissing('postiz_publish_jobs', ['sibling_post_id' => 556]);
        Queue::assertPushed(PublishViaPubler::class, fn ($j) => $j->platform === 'instagram' && $j->siblingPostId === 556);
    }

    public function test_enabled_but_no_channel_mapping_falls_back_to_publer(): void
    {
        Queue::fake();
        $this->setPostizEnabled(true);
        // No PostizChannel for instagram.

        app(PostizPublishDispatcher::class)->dispatchSibling('instagram', 557);

        $this->assertDatabaseMissing('postiz_publish_jobs', ['sibling_post_id' => 557]);
        Queue::assertPushed(PublishViaPubler::class, fn ($j) => $j->siblingPostId === 557);
    }

    public function test_redispatch_same_sibling_is_idempotent(): void
    {
        Queue::fake();
        $this->setPostizEnabled(true);
        PostizChannel::factory()->create(['platform' => 'instagram', 'postiz_integration_id' => '77', 'enabled' => true]);

        $dispatcher = app(PostizPublishDispatcher::class);
        $dispatcher->dispatchSibling('instagram', 558);
        $dispatcher->dispatchSibling('instagram', 558);

        $this->assertSame(1, PostizPublishJob::where('sibling_post_id', 558)->count());
    }
}
