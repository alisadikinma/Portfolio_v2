<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\ContentIdea;
use App\Models\Post;
use App\Models\User;
use App\Services\ContentPublishService;
use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Tests\TestCase;

/**
 * Phase B (June 10, 2026): publishing a Content Engine idea must auto-queue a
 * targeted `linkedin:scan-blog --post-id={id}` so the LinkedIn draft surfaces
 * without the daily cron / manual "Scan blog now" button. The dispatch is
 * non-fatal — a failure to enqueue must NOT break the publish response.
 *
 * @see \App\Http\Controllers\Api\Admin\ContentIdeaController::approveAndPublish
 */
class ApproveAndPublishQueuesLinkedInScanTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Local dev `.env` has APP_URL=http://localhost/Portfolio_v2/backend/public,
        // which makes Laravel's test client prepend that subpath to every URL so
        // `/api/admin/content-engine/ideas/{id}/publish` no longer matches the
        // registered route. Reset the URL generator (mirrors GeminiGenRelayWebhookTest).
        config(['app.url' => 'http://localhost']);
        \URL::forceRootUrl('http://localhost');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Build a publishable ContentIdea (status reaches publish, no unresolved
     * image segments) and a real saved Post the mocked publish() returns.
     */
    private function makePublishableIdeaAndPost(): array
    {
        $category = Category::create([
            'name' => 'Tech',
            'slug' => 'tech-' . uniqid(),
        ]);

        // Created BEFORE Queue::fake() so any HasImageVariants saved() side-effect
        // never lands in the faked queue we assert against. featured_image is null
        // so the variant job isn't dispatched at all.
        $post = Post::create([
            'category_id' => $category->id,
            'title' => 'Published Article',
            'slug' => 'published-article-' . uniqid(),
            'content' => '<p>Body.</p>',
            'published' => true,
            'published_at' => now(),
        ]);

        // No image_prompts → the image-completion guard in approveAndPublish is
        // skipped, so the request reaches publishService->publish().
        $idea = ContentIdea::create([
            'title' => 'Scan after publish',
            'pillar' => 'ai_generalist',
            'status' => 'images_ready',
            'generated_article' => ['language' => 'en', 'en' => ['title' => 'X', 'content' => 'Y']],
        ]);

        return [$idea, $post];
    }

    public function test_publish_queues_targeted_linkedin_scan(): void
    {
        [$idea, $post] = $this->makePublishableIdeaAndPost();

        // Bind a mock ContentPublishService so we don't exercise the full publish
        // path — return the real saved Post + translation flag the controller reads.
        $mock = Mockery::mock(ContentPublishService::class);
        $mock->shouldReceive('publish')
            ->once()
            ->andReturn(['post' => $post, 'translation_pending' => false]);
        $this->instance(ContentPublishService::class, $mock);

        Queue::fake();

        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/publish");

        $response->assertStatus(200)->assertJson(['success' => true]);

        Queue::assertPushed(QueuedCommand::class, function (QueuedCommand $job) use ($post) {
            $data = $this->extractQueuedCommandData($job);

            return ($data['command'] ?? null) === 'linkedin:scan-blog'
                && (string) (($data['parameters'] ?? [])['--post-id'] ?? null) === (string) $post->id;
        });
    }

    public function test_publish_succeeds_when_scan_dispatch_throws(): void
    {
        [$idea, $post] = $this->makePublishableIdeaAndPost();

        $mock = Mockery::mock(ContentPublishService::class);
        $mock->shouldReceive('publish')
            ->once()
            ->andReturn(['post' => $post, 'translation_pending' => false]);
        $this->instance(ContentPublishService::class, $mock);

        // Partial-mock the Artisan facade so the queue dispatch blows up; the
        // controller's try/catch must swallow it and still return 200 success.
        Artisan::shouldReceive('queue')
            ->andThrow(new \RuntimeException('queue down'));

        Sanctum::actingAs(User::factory()->create());

        $response = $this->postJson("/api/admin/content-engine/ideas/{$idea->id}/publish");

        $response->assertStatus(200);
        $this->assertTrue($response->json('success') === true);
    }

    /**
     * QueuedCommand stores the Artisan::queue($command, $parameters) payload in a
     * protected $data array shaped ['command' => ..., 'parameters' => ...]. Read it
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
