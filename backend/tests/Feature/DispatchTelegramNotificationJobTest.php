<?php

namespace Tests\Feature;

use App\Jobs\DispatchTelegramNotification;
use App\Models\ContentIdea;
use App\Services\TelegramNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class DispatchTelegramNotificationJobTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function dispatch_pushes_job_to_queue(): void
    {
        Bus::fake();

        $idea = ContentIdea::create([
            'pillar' => 'ai_agents',
            'title' => 'Test idea',
            'status' => 'draft',
            'priority' => 'medium',
        ]);

        DispatchTelegramNotification::dispatch($idea, 'manifest_needed');

        Bus::assertDispatched(DispatchTelegramNotification::class, function ($job) use ($idea) {
            return $job->contentIdeaId === $idea->id
                && $job->notificationType === 'manifest_needed';
        });
    }

    /** @test */
    public function handle_calls_manifest_alert_service_method(): void
    {
        $idea = ContentIdea::create([
            'pillar' => 'ai_agents',
            'title' => 'Test idea',
            'status' => 'draft',
            'priority' => 'medium',
        ]);

        $mock = Mockery::mock(TelegramNotificationService::class);
        $mock->shouldReceive('sendManifestAlert')
            ->once()
            ->withArgs(fn($arg) => $arg instanceof ContentIdea && $arg->id === $idea->id)
            ->andReturn(true);

        $job = new DispatchTelegramNotification($idea->id, 'manifest_needed');
        $job->handle($mock);

        // Mockery verifies expectations on close; the once() call above is
        // the real assertion. addToAssertionCount keeps PHPUnit from flagging
        // this test as risky (no assertions performed).
        $this->addToAssertionCount(1);
        Mockery::close();
    }

    /** @test */
    public function handle_calls_generation_failed_service_method_for_matching_type(): void
    {
        $idea = ContentIdea::create([
            'pillar' => 'ai_agents',
            'title' => 'Test',
            'status' => 'draft',
            'priority' => 'medium',
        ]);

        $mock = Mockery::mock(TelegramNotificationService::class);
        $mock->shouldReceive('sendGenerationFailed')->once()->andReturn(true);
        $mock->shouldNotReceive('sendManifestAlert');
        $mock->shouldNotReceive('sendPublishSuccess');

        $job = new DispatchTelegramNotification($idea->id, 'generation_failed');
        $job->handle($mock);

        $this->addToAssertionCount(1);
        Mockery::close();
    }

    /** @test */
    public function handle_silently_returns_when_content_idea_missing(): void
    {
        $mock = Mockery::mock(TelegramNotificationService::class);
        $mock->shouldNotReceive('sendManifestAlert');

        $job = new DispatchTelegramNotification(999999, 'manifest_needed');
        $job->handle($mock);

        Mockery::close();
        $this->assertTrue(true); // no exception = pass
    }
}
