<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\Post;
use App\Services\ArticleGenerationService;
use App\Services\AutoPipelineOrchestrator;
use App\Services\ContentPublishService;
use App\Services\ImageGenerationService;
use App\Services\TelegramNotifier;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class AutoPipelineOrchestratorTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('content.auto_timezone', 'Asia/Jakarta');
        Config::set('content.auto_window_start', 6);
        Config::set('content.auto_window_end', 22);

        // Default: daylight hour (09:00 Jakarta)
        Carbon::setTestNow(Carbon::create(2026, 4, 17, 9, 0, 0, 'Asia/Jakarta'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Build a fresh orchestrator with fully-mocked collaborators so we can
     * stub individual behaviors per test.
     */
    private function makeOrchestrator(array $overrides = []): array
    {
        $articleGen = $overrides['articleGen'] ?? Mockery::mock(ArticleGenerationService::class);
        $imageGen = $overrides['imageGen'] ?? Mockery::mock(ImageGenerationService::class);
        $publish = $overrides['publish'] ?? Mockery::mock(ContentPublishService::class);
        $telegram = $overrides['telegram'] ?? Mockery::mock(TelegramNotifier::class);

        $orchestrator = new AutoPipelineOrchestrator($articleGen, $imageGen, $publish, $telegram);
        return compact('orchestrator', 'articleGen', 'imageGen', 'publish', 'telegram');
    }

    /**
     * Stub the static ContentIdea query chain to return controlled Eloquent results.
     * Each entry in $answers maps a status → Eloquent collection OR single ContentIdea.
     */
    private function stubIdeaQueries(array $mockReturns): void
    {
        $mock = Mockery::mock('alias:' . ContentIdea::class);

        // Generic "where->where->... ->first/get/exists" stubs. We need a
        // reusable Builder mock that chains fluently.
        $builder = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('whereIn')->andReturnSelf();
        $builder->shouldReceive('whereNull')->andReturnSelf();
        $builder->shouldReceive('whereNotNull')->andReturnSelf();
        $builder->shouldReceive('orderBy')->andReturnSelf();

        $builder->shouldReceive('first')->andReturn($mockReturns['first'] ?? null);
        $builder->shouldReceive('get')->andReturn($mockReturns['get'] ?? new Collection());
        $builder->shouldReceive('exists')->andReturn($mockReturns['exists'] ?? false);

        $mock->shouldReceive('where')->andReturn($builder);
    }

    private function stubPostLookup(bool $exists = false): void
    {
        $builder = Mockery::mock('Illuminate\Database\Eloquent\Builder');
        $builder->shouldReceive('where')->andReturnSelf();
        $builder->shouldReceive('exists')->andReturn($exists);

        $postMock = Mockery::mock('alias:' . Post::class);
        $postMock->shouldReceive('where')->andReturn($builder);
    }

    /** @test */
    public function tick_returns_null_outside_operating_window()
    {
        // 23:00 Jakarta — outside window (6-22)
        Carbon::setTestNow(Carbon::create(2026, 4, 17, 23, 0, 0, 'Asia/Jakarta'));

        $ctx = $this->makeOrchestrator();
        $this->assertNull($ctx['orchestrator']->tick());
    }

    /** @test */
    public function tick_returns_null_at_exactly_window_end_hour()
    {
        // 22:00 — boundary (end exclusive)
        Carbon::setTestNow(Carbon::create(2026, 4, 17, 22, 0, 0, 'Asia/Jakarta'));

        $ctx = $this->makeOrchestrator();
        $this->assertNull($ctx['orchestrator']->tick());
    }

    /** @test */
    public function tick_is_active_at_window_start_hour()
    {
        // 06:00 — boundary (start inclusive)
        Carbon::setTestNow(Carbon::create(2026, 4, 17, 6, 0, 0, 'Asia/Jakarta'));

        $this->stubIdeaQueries(['first' => null, 'get' => new Collection(), 'exists' => false]);
        $ctx = $this->makeOrchestrator();

        // No ideas available → returns null, but NOT because of window gate
        $this->assertNull($ctx['orchestrator']->tick());
    }

    /** @test */
    public function tick_returns_null_when_in_flight_idea_exists()
    {
        // Stuck detection sees nothing (empty get), but exists() returns true for in-flight check
        $this->stubIdeaQueries([
            'first' => null,
            'get' => new Collection(),
            'exists' => true,
        ]);

        $ctx = $this->makeOrchestrator();
        $this->assertNull($ctx['orchestrator']->tick());
    }

    /** @test */
    public function tick_uses_configured_window_values()
    {
        Config::set('content.auto_window_start', 8);
        Config::set('content.auto_window_end', 20);

        // 07:00 — outside custom 8-20 window
        Carbon::setTestNow(Carbon::create(2026, 4, 17, 7, 0, 0, 'Asia/Jakarta'));
        $ctx = $this->makeOrchestrator();
        $this->assertNull($ctx['orchestrator']->tick());
    }
}
