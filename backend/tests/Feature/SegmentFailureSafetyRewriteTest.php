<?php

namespace Tests\Feature;

use App\Jobs\RetryImageSegmentJob;
use App\Models\ContentIdea;
use App\Models\ImageGenerationJob;
use App\Services\ArticleGenerationService;
use App\Services\ImageGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Safety-aware retry path. When GeminiGen returns a refusal like
 * PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD, the same prompt would fail
 * deterministically — handleSegmentFailure must rewrite the segment
 * (drop face_refs, sanitize VD via Claude) before scheduling the
 * next attempt, otherwise the idea sits stuck at retry_count=3.
 */
class SegmentFailureSafetyRewriteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeJob(string $uuid, string $error): ImageGenerationJob
    {
        $job = new ImageGenerationJob();
        $job->uuid = $uuid;
        $job->type = 'hero';
        $job->prompt = 'Cover';
        $job->status = 'failed';
        $job->error_message = $error;
        return $job;
    }

    /** @test */
    public function safety_detector_matches_known_refusal_codes(): void
    {
        $service = app(ImageGenerationService::class);

        $this->assertTrue($service->isSafetyError('PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD'));
        $this->assertTrue($service->isSafetyError('Currently, we do not allow uploading images of minors, prominent people, sexual content, or unsafe content.'));
        $this->assertTrue($service->isSafetyError('Content blocked by safety filter'));
        $this->assertTrue($service->isSafetyError('content policy violation'));

        $this->assertFalse($service->isSafetyError('timeout'));
        $this->assertFalse($service->isSafetyError('boom'));
        $this->assertFalse($service->isSafetyError(null));
        $this->assertFalse($service->isSafetyError(''));
    }

    /** @test */
    public function safety_failure_rewrites_prompt_and_drops_face_refs_before_retry(): void
    {
        Queue::fake();

        // Mock ArticleGenerationService so we don't SSH to Claude CLI.
        $articleGen = Mockery::mock(ArticleGenerationService::class);
        $articleGen->shouldReceive('rewriteVisualDirectionForSafety')
            ->once()
            ->andReturn([
                'success' => true,
                'rewritten_vd' => 'A confident tech executive in a modern office, generic descriptors, photorealistic, 8K detail.',
                'error' => null,
            ]);
        $this->app->instance(ArticleGenerationService::class, $articleGen);

        $idea = ContentIdea::create([
            'title' => 'Mark Zuckerberg AI Agent yang Bikin CEO Lain Tertinggal',
            'pillar' => 'ai_agents',
            'priority' => 'medium',
            'status' => 'generating_images',
            'auto_mode' => true,
            'generated_article' => [
                'image_prompts' => [
                    [
                        'type' => 'cover',
                        'visual_direction' => 'Split-composition cinematic portrait of Mark Zuckerberg.',
                        'prompt_text' => 'Split-composition cinematic portrait of Mark Zuckerberg.',
                        'status' => 'generating',
                        'retry_count' => 0,
                        'failure_history' => [],
                        'terminal_at' => null,
                        'face_refs' => ['https://example.com/zuckerberg.jpg'],
                        'entity_refs' => [['entity_type' => 'person', 'name' => 'Mark Zuckerberg']],
                        'variations' => [
                            ['url' => null, 'job_uuid' => 'safety-fail-1', 'status' => 'generating'],
                        ],
                    ],
                ],
            ],
        ]);

        app(ImageGenerationService::class)->handleSegmentFailure(
            $this->makeJob('safety-fail-1', 'PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD'),
            'PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD'
        );

        $idea->refresh();
        $segment = $idea->generated_article['image_prompts'][0];

        $this->assertSame(1, $segment['retry_count']);
        $this->assertSame('A confident tech executive in a modern office, generic descriptors, photorealistic, 8K detail.', $segment['visual_direction']);
        $this->assertSame('A confident tech executive in a modern office, generic descriptors, photorealistic, 8K detail.', $segment['prompt_text']);
        $this->assertSame('Split-composition cinematic portrait of Mark Zuckerberg.', $segment['visual_direction_pre_safety']);
        $this->assertSame([], $segment['face_refs'], 'face_refs must be dropped on safety rewrite');
        $this->assertSame([], $segment['entity_refs'], 'entity_refs must be dropped on safety rewrite');

        $latest = end($segment['failure_history']);
        $this->assertTrue($latest['safety_detected']);
        $this->assertTrue($latest['rewritten_for_safety']);

        Queue::assertPushed(RetryImageSegmentJob::class, function ($pushed) use ($idea) {
            return $pushed->contentIdeaId === $idea->id && $pushed->segmentIndex === 0;
        });
    }

    /** @test */
    public function safety_rewrite_skipped_when_feature_flag_off(): void
    {
        Queue::fake();
        config(['services.article_generation.use_safety_rewrite' => false]);

        // Mock should never be called when flag is off.
        $articleGen = Mockery::mock(ArticleGenerationService::class);
        $articleGen->shouldNotReceive('rewriteVisualDirectionForSafety');
        $this->app->instance(ArticleGenerationService::class, $articleGen);

        $idea = ContentIdea::create([
            'title' => 'Public figure article',
            'pillar' => 'ai_agents',
            'priority' => 'medium',
            'status' => 'generating_images',
            'auto_mode' => true,
            'generated_article' => [
                'image_prompts' => [
                    [
                        'type' => 'cover',
                        'visual_direction' => 'Cover with named entity',
                        'prompt_text' => 'Cover with named entity',
                        'status' => 'generating',
                        'retry_count' => 0,
                        'failure_history' => [],
                        'terminal_at' => null,
                        'face_refs' => ['https://example.com/face.jpg'],
                        'variations' => [['url' => null, 'job_uuid' => 'flagged-off', 'status' => 'generating']],
                    ],
                ],
            ],
        ]);

        app(ImageGenerationService::class)->handleSegmentFailure(
            $this->makeJob('flagged-off', 'PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD'),
            'PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD'
        );

        $idea->refresh();
        $segment = $idea->generated_article['image_prompts'][0];

        $this->assertSame('Cover with named entity', $segment['prompt_text'], 'prompt unchanged when flag off');
        $this->assertSame(['https://example.com/face.jpg'], $segment['face_refs'], 'face_refs preserved when flag off');
    }

    /** @test */
    public function non_safety_failure_does_not_invoke_rewriter(): void
    {
        Queue::fake();

        $articleGen = Mockery::mock(ArticleGenerationService::class);
        $articleGen->shouldNotReceive('rewriteVisualDirectionForSafety');
        $this->app->instance(ArticleGenerationService::class, $articleGen);

        $idea = ContentIdea::create([
            'title' => 'Transient failure',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => 'generating_images',
            'auto_mode' => true,
            'generated_article' => [
                'image_prompts' => [
                    [
                        'type' => 'cover',
                        'prompt_text' => 'Original prompt',
                        'visual_direction' => 'Original VD',
                        'status' => 'generating',
                        'retry_count' => 0,
                        'failure_history' => [],
                        'terminal_at' => null,
                        'face_refs' => ['https://example.com/face.jpg'],
                        'variations' => [['url' => null, 'job_uuid' => 'transient', 'status' => 'generating']],
                    ],
                ],
            ],
        ]);

        app(ImageGenerationService::class)->handleSegmentFailure(
            $this->makeJob('transient', 'connection timeout'),
            'connection timeout'
        );

        $idea->refresh();
        $segment = $idea->generated_article['image_prompts'][0];

        $this->assertSame('Original prompt', $segment['prompt_text']);
        $this->assertSame(['https://example.com/face.jpg'], $segment['face_refs']);
        $latest = end($segment['failure_history']);
        $this->assertFalse($latest['safety_detected']);
        $this->assertFalse($latest['rewritten_for_safety']);
    }

    /** @test */
    public function rewriter_failure_falls_back_gracefully_without_breaking_fsm(): void
    {
        Queue::fake();

        // Rewriter returns failure — handler must still update retry_count
        // and dispatch retry job (next attempt will fail again with same
        // safety reason and try to rewrite again).
        $articleGen = Mockery::mock(ArticleGenerationService::class);
        $articleGen->shouldReceive('rewriteVisualDirectionForSafety')
            ->once()
            ->andReturn(['success' => false, 'rewritten_vd' => null, 'error' => 'Claude CLI down']);
        $this->app->instance(ArticleGenerationService::class, $articleGen);

        $idea = ContentIdea::create([
            'title' => 'Rewriter down',
            'pillar' => 'ai_agents',
            'priority' => 'medium',
            'status' => 'generating_images',
            'auto_mode' => true,
            'generated_article' => [
                'image_prompts' => [
                    [
                        'type' => 'cover',
                        'prompt_text' => 'Original prompt with public figure',
                        'visual_direction' => 'Original VD',
                        'status' => 'generating',
                        'retry_count' => 0,
                        'failure_history' => [],
                        'terminal_at' => null,
                        'face_refs' => ['https://example.com/face.jpg'],
                        'variations' => [['url' => null, 'job_uuid' => 'fallback', 'status' => 'generating']],
                    ],
                ],
            ],
        ]);

        app(ImageGenerationService::class)->handleSegmentFailure(
            $this->makeJob('fallback', 'PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD'),
            'PUBLIC_ERROR_PROMINENT_PEOPLE_UPLOAD'
        );

        $idea->refresh();
        $segment = $idea->generated_article['image_prompts'][0];

        $this->assertSame(1, $segment['retry_count'], 'retry_count still bumped even if rewrite failed');
        $this->assertSame('Original prompt with public figure', $segment['prompt_text'], 'prompt left unchanged when rewriter failed');
        $latest = end($segment['failure_history']);
        $this->assertTrue($latest['safety_detected']);
        $this->assertFalse($latest['rewritten_for_safety']);

        Queue::assertPushed(RetryImageSegmentJob::class);
    }
}
