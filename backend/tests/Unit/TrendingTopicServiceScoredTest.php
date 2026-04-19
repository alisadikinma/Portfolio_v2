<?php

namespace Tests\Unit;

use App\Services\TopicScoringService;
use App\Services\TrendingTopicService;
use Mockery;
use Tests\TestCase;

class TrendingTopicServiceScoredTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_get_scored_topics_sorts_by_composite_score_desc(): void
    {
        $rawTrends = [
            ['title' => 'low', 'source' => 'google_news'],
            ['title' => 'high', 'source' => 'google_news'],
            ['title' => 'mid', 'source' => 'google_news'],
        ];

        $trendingService = Mockery::mock(TrendingTopicService::class)->makePartial();
        $trendingService->shouldReceive('getAllTrends')
            ->once()
            ->with(null)
            ->andReturn($rawTrends);

        $scoredReturn = [
            ['title' => 'low', 'source' => 'google_news', 'composite_score' => 30, 'momentum_score' => 30, 'virality_score' => 30, 'triggers' => []],
            ['title' => 'high', 'source' => 'google_news', 'composite_score' => 90, 'momentum_score' => 80, 'virality_score' => 95, 'triggers' => []],
            ['title' => 'mid', 'source' => 'google_news', 'composite_score' => 55, 'momentum_score' => 50, 'virality_score' => 58, 'triggers' => []],
        ];

        $scoringMock = Mockery::mock(TopicScoringService::class);
        $scoringMock->shouldReceive('scoreBatch')
            ->once()
            ->andReturn($scoredReturn);
        app()->instance(TopicScoringService::class, $scoringMock);

        $result = $trendingService->getScoredTopics();

        $this->assertCount(3, $result);
        $this->assertSame('high', $result[0]['title']);
        $this->assertSame('mid', $result[1]['title']);
        $this->assertSame('low', $result[2]['title']);
        $this->assertSame(90, $result[0]['composite_score']);
    }

    public function test_get_scored_topics_chunks_large_input_into_max_batch_calls(): void
    {
        // 50 trends, default max_scored=60 → all 50 processed across ceil(50/20) = 3 chunks.
        $rawTrends = [];
        for ($i = 0; $i < 50; $i++) {
            $rawTrends[] = ['title' => "topic-{$i}", 'source' => 'google_news'];
        }

        $trendingService = Mockery::mock(TrendingTopicService::class)->makePartial();
        $trendingService->shouldReceive('getAllTrends')->once()->andReturn($rawTrends);

        $scoringMock = Mockery::mock(TopicScoringService::class);
        $scoringMock->shouldReceive('scoreBatch')
            ->times(3)
            ->withArgs(function ($topics) {
                // No chunk exceeds MAX_BATCH_SIZE — prompt-safe boundary preserved.
                return count($topics) <= TopicScoringService::MAX_BATCH_SIZE;
            })
            ->andReturnUsing(function ($topics) {
                return array_map(fn ($t) => array_merge($t, ['composite_score' => 50, 'momentum_score' => 50, 'virality_score' => 50, 'triggers' => []]), $topics);
            });
        app()->instance(TopicScoringService::class, $scoringMock);

        $result = $trendingService->getScoredTopics();

        $this->assertCount(50, $result);
    }

    public function test_get_scored_topics_caps_at_max_scored_config(): void
    {
        // Raise ceiling artificially low via config to prove the cap is honored.
        config(['content.trending.max_scored' => 25]);

        $rawTrends = [];
        for ($i = 0; $i < 100; $i++) {
            $rawTrends[] = ['title' => "topic-{$i}", 'source' => 'google_news'];
        }

        $trendingService = Mockery::mock(TrendingTopicService::class)->makePartial();
        $trendingService->shouldReceive('getAllTrends')->once()->andReturn($rawTrends);

        $scoringMock = Mockery::mock(TopicScoringService::class);
        $scoringMock->shouldReceive('scoreBatch')
            ->times(2) // 25 topics → chunks of 20 + 5
            ->andReturnUsing(function ($topics) {
                return array_map(fn ($t) => array_merge($t, ['composite_score' => 50, 'momentum_score' => 50, 'virality_score' => 50, 'triggers' => []]), $topics);
            });
        app()->instance(TopicScoringService::class, $scoringMock);

        $result = $trendingService->getScoredTopics();

        $this->assertCount(25, $result);
    }

    public function test_get_scored_topics_passes_source_filter_through(): void
    {
        $trendingService = Mockery::mock(TrendingTopicService::class)->makePartial();
        $trendingService->shouldReceive('getAllTrends')
            ->once()
            ->with('tiktok')
            ->andReturn([
                ['title' => 'only', 'source' => 'tiktok'],
            ]);

        $scoringMock = Mockery::mock(TopicScoringService::class);
        $scoringMock->shouldReceive('scoreBatch')
            ->once()
            ->andReturn([
                ['title' => 'only', 'source' => 'tiktok', 'composite_score' => 70, 'momentum_score' => 70, 'virality_score' => 70, 'triggers' => []],
            ]);
        app()->instance(TopicScoringService::class, $scoringMock);

        $result = $trendingService->getScoredTopics('tiktok');

        $this->assertCount(1, $result);
        $this->assertSame('tiktok', $result[0]['source']);
    }

    public function test_get_scored_topics_empty_when_no_trends(): void
    {
        $trendingService = Mockery::mock(TrendingTopicService::class)->makePartial();
        $trendingService->shouldReceive('getAllTrends')->once()->andReturn([]);

        $scoringMock = Mockery::mock(TopicScoringService::class);
        $scoringMock->shouldNotReceive('scoreBatch');
        app()->instance(TopicScoringService::class, $scoringMock);

        $result = $trendingService->getScoredTopics();
        $this->assertSame([], $result);
    }

    public function test_display_score_folds_heat_and_tier_boosts_and_sorts_by_display_score(): void
    {
        // Three topics with identical composite_score but different heat/tier.
        // Without boosts they'd tie at 70. With boosts:
        //   A: 70 + 15 (hot)     + 5  (tier 1) = 90
        //   B: 70 + 8  (trending)+ 2  (tier 2) = 80
        //   C: 70 + 0  (standard)+ 0  (tier 3) = 70
        // Expected order after sort: A, B, C. Regression guards that the
        // admin UI never again surfaces a score-tie item above a hot item.
        $rawTrends = [
            ['title' => 'A-hot-t1', 'source' => 'google_news'],
            ['title' => 'B-trending-t2', 'source' => 'google_news'],
            ['title' => 'C-cold-t3', 'source' => 'google_news'],
        ];

        $trendingService = Mockery::mock(TrendingTopicService::class)->makePartial();
        $trendingService->shouldReceive('getAllTrends')->once()->andReturn($rawTrends);

        $scoringMock = Mockery::mock(TopicScoringService::class);
        $scoringMock->shouldReceive('scoreBatch')->once()->andReturn([
            ['title' => 'A-hot-t1', 'source' => 'google_news', 'heat' => 'hot', 'publisher_tier' => 1,
                'composite_score' => 70, 'momentum_score' => 70, 'virality_score' => 70, 'triggers' => []],
            ['title' => 'B-trending-t2', 'source' => 'google_news', 'heat' => 'trending', 'publisher_tier' => 2,
                'composite_score' => 70, 'momentum_score' => 70, 'virality_score' => 70, 'triggers' => []],
            ['title' => 'C-cold-t3', 'source' => 'google_news', 'heat' => 'standard', 'publisher_tier' => 3,
                'composite_score' => 70, 'momentum_score' => 70, 'virality_score' => 70, 'triggers' => []],
        ]);
        app()->instance(TopicScoringService::class, $scoringMock);

        $result = $trendingService->getScoredTopics();

        $this->assertCount(3, $result);
        $this->assertSame('A-hot-t1', $result[0]['title']);
        $this->assertSame(90, $result[0]['display_score']);
        $this->assertSame(15, $result[0]['heat_boost']);
        $this->assertSame(5, $result[0]['tier_boost']);

        $this->assertSame('B-trending-t2', $result[1]['title']);
        $this->assertSame(80, $result[1]['display_score']);

        $this->assertSame('C-cold-t3', $result[2]['title']);
        $this->assertSame(70, $result[2]['display_score']);
        $this->assertSame(0, $result[2]['heat_boost']);
        $this->assertSame(0, $result[2]['tier_boost']);

        // composite_score stays intact so the tooltip can still break the
        // number down into its AI-score + boost components.
        $this->assertSame(70, $result[0]['composite_score']);
    }

    public function test_display_score_clamps_to_max_100(): void
    {
        // composite 95 + hot (+15) + tier-1 (+5) = 115 → must clamp to 100.
        $trendingService = Mockery::mock(TrendingTopicService::class)->makePartial();
        $trendingService->shouldReceive('getAllTrends')->once()->andReturn([
            ['title' => 'runaway', 'source' => 'google_news'],
        ]);

        $scoringMock = Mockery::mock(TopicScoringService::class);
        $scoringMock->shouldReceive('scoreBatch')->once()->andReturn([
            ['title' => 'runaway', 'source' => 'google_news', 'heat' => 'hot', 'publisher_tier' => 1,
                'composite_score' => 95, 'momentum_score' => 95, 'virality_score' => 95, 'triggers' => []],
        ]);
        app()->instance(TopicScoringService::class, $scoringMock);

        $result = $trendingService->getScoredTopics();

        $this->assertSame(100, $result[0]['display_score']);
    }
}
