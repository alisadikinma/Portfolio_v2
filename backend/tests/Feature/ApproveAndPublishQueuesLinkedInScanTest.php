<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentIdea;
use App\Services\ContentPublishService;
use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Event-driven LinkedIn draft ingest (June 10, 2026). Publishing a Content
 * Engine idea must auto-queue a targeted `linkedin:scan-blog --post-id={id}`
 * so the LinkedIn draft surfaces without the daily cron / manual button.
 *
 * The dispatch lives in ContentPublishService::publish — the SHARED chokepoint
 * for BOTH the manual operator path (ContentIdeaController::approveAndPublish)
 * AND the autonomous factory (AutoPipelineOrchestrator::publishReady). Testing
 * the service directly proves both callers get the behavior. The dispatch is
 * non-fatal — a failure to enqueue must NOT break the publish.
 *
 * @see \App\Services\ContentPublishService::publish
 */
class ApproveAndPublishQueuesLinkedInScanTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** Build a publishable ContentIdea (real article content, no image segments). */
    private function makePublishableIdea(): ContentIdea
    {
        Category::create(['name' => 'Tech', 'slug' => 'tech-' . uniqid()]);

        return ContentIdea::create([
            'title' => 'Scan after publish',
            'pillar' => 'ai_generalist',
            'status' => 'images_ready',
            'generated_article' => [
                'language' => 'en',
                'en' => ['title' => 'Published Article', 'content' => '<p>Body.</p>'],
            ],
        ]);
    }

    public function test_publish_queues_targeted_linkedin_scan(): void
    {
        $idea = $this->makePublishableIdea();

        Queue::fake();

        $result = app(ContentPublishService::class)->publish($idea);
        $postId = $result['post']->id;

        // Exactly one QueuedCommand — the scan. featured_image is null so the
        // HasImageVariants job never dispatches and can't pollute the count.
        Queue::assertPushed(QueuedCommand::class, 1);

        Queue::assertPushed(QueuedCommand::class, function (QueuedCommand $job) use ($postId) {
            // Kernel::queue($command, $parameters) → QueuedCommand::dispatch(func_get_args()),
            // so $data is POSITIONAL: [0 => command string, 1 => parameters array].
            $data = $this->extractQueuedCommandData($job);

            return ($data[0] ?? null) === 'linkedin:scan-blog'
                && (string) (($data[1] ?? [])['--post-id'] ?? null) === (string) $postId;
        });
    }

    public function test_publish_succeeds_when_scan_dispatch_throws(): void
    {
        $idea = $this->makePublishableIdea();

        // Make the queue dispatch blow up; publish() must swallow it and still
        // return the created post.
        Artisan::shouldReceive('queue')->andThrow(new \RuntimeException('queue down'));

        $result = app(ContentPublishService::class)->publish($idea);

        $this->assertNotNull($result['post']->id);
        $this->assertTrue($result['post']->published);
    }

    /**
     * QueuedCommand stores the Artisan::queue($command, $parameters) payload in a
     * protected $data array (POSITIONAL: [0 => command, 1 => parameters]). Read it
     * via reflection so the assertion targets the real command + --post-id value.
     */
    private function extractQueuedCommandData(QueuedCommand $job): array
    {
        $ref = new \ReflectionObject($job);

        if ($ref->hasProperty('data')) {
            $prop = $ref->getProperty('data');
            $prop->setAccessible(true);
            $data = $prop->getValue($job);

            return is_array($data) ? $data : [];
        }

        return [];
    }
}
