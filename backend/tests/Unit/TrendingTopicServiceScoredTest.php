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

    public function test_get_scored_topics_caps_input_at_batch_size(): void
    {
        $rawTrends = [];
        for ($i = 0; $i < 30; $i++) {
            $rawTrends[] = ['title' => "topic-{$i}", 'source' => 'google_news'];
        }

        $trendingService = Mockery::mock(TrendingTopicService::class)->makePartial();
        $trendingService->shouldReceive('getAllTrends')->once()->andReturn($rawTrends);

        $scoringMock = Mockery::mock(TopicScoringService::class);
        $scoringMock->shouldReceive('scoreBatch')
            ->once()
            ->withArgs(function ($topics) {
                // Enforce that getScoredTopics caps at MAX_BATCH_SIZE before
                // dispatching to scoreBatch. Prevents AI cost blowout.
                return count($topics) === TopicScoringService::MAX_BATCH_SIZE;
            })
            ->andReturnUsing(function ($topics) {
                return array_map(fn ($t) => array_merge($t, ['composite_score' => 50, 'momentum_score' => 50, 'virality_score' => 50, 'triggers' => []]), $topics);
            });
        app()->instance(TopicScoringService::class, $scoringMock);

        $result = $trendingService->getScoredTopics();

        $this->assertCount(TopicScoringService::MAX_BATCH_SIZE, $result);
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
}
