<?php

namespace Tests\Feature;

use App\Jobs\DispatchTelegramNotification;
use App\Models\ContentIdea;
use App\Models\Post;
use App\Services\ArticleGenerationService;
use App\Services\AutoPipelineOrchestrator;
use App\Services\ContentPublishService;
use App\Services\ImageGenerationService;
use App\Services\TelegramNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * Tests for the Phase C translate-before-publish gate inside
 * AutoPipelineOrchestrator::publishReady. Gate is behind
 * config('services.article_generation.use_translate_phase', false) —
 * default-off preserves pre-Phase-C behavior.
 */
class AutoPipelineTranslateGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }

        // Pin time inside the operating window so tick() doesn't short-circuit
        Carbon::setTestNow(Carbon::parse('2026-04-21 10:00:00', 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    private function makeIdea(array $generatedArticle, string $status = 'completed'): ContentIdea
    {
        return ContentIdea::create([
            'title' => 'Gate idea',
            'pillar' => 'ai_automation',
            'priority' => 'medium',
            'status' => $status,
            'auto_mode' => true,
            'generated_article' => $generatedArticle,
        ]);
    }

    private function orchestrator(
        ArticleGenerationService $articleGen,
        ?ContentPublishService $publish = null
    ): AutoPipelineOrchestrator {
        $publish ??= Mockery::mock(ContentPublishService::class);
        $imageGen = Mockery::mock(ImageGenerationService::class);
        $telegram = Mockery::mock(TelegramNotifier::class)->shouldIgnoreMissing();
        return new AutoPipelineOrchestrator($articleGen, $imageGen, $publish, $telegram);
    }

    /** @test */
    public function gate_proceeds_immediately_when_flag_is_off(): void
    {
        Config::set('services.article_generation.use_translate_phase', false);

        $idea = $this->makeIdea([
            'language' => 'id',
            'id' => ['title' => 'x', 'content' => '<p>x</p>'],
            'title' => 'x',
            'content' => '<p>x</p>',
        ]);

        $articleGen = Mockery::mock(ArticleGenerationService::class);
        $articleGen->shouldNotReceive('triggerTranslatePreflight');

        $publish = Mockery::mock(ContentPublishService::class);
        $publish->shouldReceive('publish')
            ->once()
            ->andReturn(['post' => new Post(['id' => 42, 'slug' => 'x'])]);

        $orch = $this->orchestrator($articleGen, $publish);
        $result = $orch->tick();

        $this->assertNotNull($result, 'Idea should have been published when flag is off');
    }

    /** @test */
    public function gate_publishes_when_translation_already_present(): void
    {
        Config::set('services.article_generation.use_translate_phase', true);

        $idea = $this->makeIdea([
            'language' => 'id',
            'id' => ['title' => 'Judul', 'content' => '<p>id</p>'],
            'en' => ['title' => 'Title', 'content' => '<p>en</p>'],
            'title' => 'Judul',
            'content' => '<p>id</p>',
        ]);

        $articleGen = Mockery::mock(ArticleGenerationService::class);
        $articleGen->shouldNotReceive('triggerTranslatePreflight');

        $publish = Mockery::mock(ContentPublishService::class);
        $publish->shouldReceive('publish')
            ->once()
            ->andReturn(['post' => new Post(['id' => 42, 'slug' => 'x'])]);

        $orch = $this->orchestrator($articleGen, $publish);
        $orch->tick();

        $idea->refresh();
        $this->assertNotNull($idea->translation_ready_at, 'translation_ready_at must be stamped on published idea');
    }

    /** @test */
    public function gate_defers_publish_and_runs_preflight_when_en_missing(): void
    {
        Config::set('services.article_generation.use_translate_phase', true);

        $idea = $this->makeIdea([
            'language' => 'id',
            'id' => ['title' => 'Judul', 'content' => '<p>id</p>'],
            'title' => 'Judul',
            'content' => '<p>id</p>',
        ]);

        $articleGen = Mockery::mock(ArticleGenerationService::class);
        $articleGen->shouldReceive('triggerTranslatePreflight')
            ->once()
            ->with(Mockery::on(fn ($i) => $i->id === $idea->id))
            ->andReturn(['success' => false, 'error' => 'SSH boom']);

        $publish = Mockery::mock(ContentPublishService::class);
        $publish->shouldNotReceive('publish');

        $orch = $this->orchestrator($articleGen, $publish);
        $result = $orch->tick();

        $this->assertNull($result, 'Tick must defer (no advance) when preflight fails and not exhausted');

        $idea->refresh();
        $this->assertSame(1, $idea->translation_attempts_auto);
        $this->assertSame('completed', $idea->status);
        $this->assertNull($idea->result_post_id);
    }

    /** @test */
    public function gate_publishes_when_preflight_succeeds(): void
    {
        Config::set('services.article_generation.use_translate_phase', true);

        $idea = $this->makeIdea([
            'language' => 'id',
            'id' => ['title' => 'Judul', 'content' => '<p>id</p>'],
            'title' => 'Judul',
            'content' => '<p>id</p>',
        ]);

        $articleGen = Mockery::mock(ArticleGenerationService::class);
        $articleGen->shouldReceive('triggerTranslatePreflight')
            ->once()
            ->andReturnUsing(function ($idea) {
                // Simulate preflight writing translated content into DB. The
                // orchestrator (not the preflight) is responsible for the
                // attempts counter; mirror the production contract here.
                $article = $idea->generated_article;
                $article['en'] = ['title' => 'EN', 'content' => '<p>en</p>'];
                $idea->update(['generated_article' => $article]);
                return ['success' => true];
            });

        $publish = Mockery::mock(ContentPublishService::class);
        $publish->shouldReceive('publish')
            ->once()
            ->andReturn(['post' => new Post(['id' => 42, 'slug' => 'x'])]);

        $orch = $this->orchestrator($articleGen, $publish);
        $orch->tick();

        $idea->refresh();
        $this->assertSame(1, $idea->translation_attempts_auto);
        $this->assertNotNull($idea->translation_ready_at);
    }

    /** @test */
    public function gate_gracefully_degrades_and_alerts_after_cap(): void
    {
        Config::set('services.article_generation.use_translate_phase', true);
        Queue::fake();

        $idea = $this->makeIdea([
            'language' => 'id',
            'id' => ['title' => 'Judul', 'content' => '<p>id</p>'],
            'title' => 'Judul',
            'content' => '<p>id</p>',
        ]);
        $idea->update(['translation_attempts_auto' => 3]);

        $articleGen = Mockery::mock(ArticleGenerationService::class);
        $articleGen->shouldNotReceive('triggerTranslatePreflight');

        $publish = Mockery::mock(ContentPublishService::class);
        $publish->shouldReceive('publish')
            ->once()
            ->andReturn(['post' => new Post(['id' => 42, 'slug' => 'x'])]);

        $orch = $this->orchestrator($articleGen, $publish);
        $orch->tick();

        $idea->refresh();
        $this->assertNotNull($idea->translation_ready_at, 'Graceful-degrade must still set translation_ready_at to sentinel');

        Queue::assertPushed(DispatchTelegramNotification::class, function ($pushed) use ($idea) {
            return $pushed->contentIdeaId === $idea->id
                && $pushed->notificationType === 'auto_translate_exhausted';
        });
    }

    /** @test */
    public function gate_does_not_run_preflight_for_non_auto_mode_ideas(): void
    {
        Config::set('services.article_generation.use_translate_phase', true);

        $idea = $this->makeIdea([
            'language' => 'id',
            'id' => ['title' => 'Judul', 'content' => '<p>id</p>'],
            'title' => 'Judul',
            'content' => '<p>id</p>',
        ]);
        $idea->update(['auto_mode' => false]);

        $articleGen = Mockery::mock(ArticleGenerationService::class);
        $articleGen->shouldNotReceive('triggerTranslatePreflight');

        $publish = Mockery::mock(ContentPublishService::class);
        $publish->shouldNotReceive('publish');

        $orch = $this->orchestrator($articleGen, $publish);
        $result = $orch->tick();

        $this->assertNull($result);
    }
}
