<?php

namespace Tests\Unit;

use App\Services\ArticleGenerationService;
use App\Services\TopicScoringService;
use Illuminate\Support\Facades\Cache;
use Mockery;
use Tests\TestCase;

class TopicScoringServiceBatchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        Cache::flush();
        parent::tearDown();
    }

    private function bindArticleGenMock(int $expectCalls, array $aiScores = null): \Mockery\MockInterface
    {
        $aiScores ??= [
            ['title' => 'alpha', 'virality_score' => 90, 'triggers' => [
                'social_currency' => true, 'high_arousal' => true,
                'practical_utility' => false, 'identity_signaling' => true,
                'cognitive_gap' => false,
            ]],
            ['title' => 'beta', 'virality_score' => 50, 'triggers' => [
                'social_currency' => false, 'high_arousal' => false,
                'practical_utility' => true, 'identity_signaling' => false,
                'cognitive_gap' => true,
            ]],
            ['title' => 'gamma', 'virality_score' => 20, 'triggers' => [
                'social_currency' => false, 'high_arousal' => false,
                'practical_utility' => false, 'identity_signaling' => false,
                'cognitive_gap' => false,
            ]],
        ];

        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldReceive('runSonnetSync')
            ->times($expectCalls)
            ->andReturn([
                'success' => true,
                'output' => json_encode($aiScores),
                'error' => null,
            ]);

        app()->instance(ArticleGenerationService::class, $mock);
        return $mock;
    }

    public function test_composite_score_formula_is_correct(): void
    {
        $this->bindArticleGenMock(1);
        $service = new TopicScoringService();

        $topics = [
            ['title' => 'alpha', 'source' => 'google_news', 'publisher_tier' => 1, 'pub_date' => now()->subHours(2)->toIso8601String(), 'publisher_count' => 5],
            ['title' => 'beta', 'source' => 'google_trends', 'publisher_tier' => null, 'pub_date' => now()->subDays(2)->toIso8601String(), 'publisher_count' => 1],
            ['title' => 'gamma', 'source' => 'unknown', 'publisher_tier' => null, 'pub_date' => null, 'publisher_count' => 1],
        ];

        $result = $service->scoreBatch($topics);

        $this->assertCount(3, $result);
        foreach ($result as $row) {
            $this->assertArrayHasKey('momentum_score', $row);
            $this->assertArrayHasKey('virality_score', $row);
            $this->assertArrayHasKey('composite_score', $row);
            $this->assertArrayHasKey('triggers', $row);

            $expected = (int) round($row['momentum_score'] * 0.4 + $row['virality_score'] * 0.6);
            $this->assertSame($expected, $row['composite_score']);
            $this->assertGreaterThanOrEqual(0, $row['composite_score']);
            $this->assertLessThanOrEqual(100, $row['composite_score']);
        }
    }

    public function test_cache_hit_skips_second_ai_call(): void
    {
        // Expect runSonnetSync to be called exactly ONCE across two invocations
        // with the same title set — second call must hit cache.
        $this->bindArticleGenMock(1);

        $service = new TopicScoringService();
        $topics = [
            ['title' => 'alpha', 'source' => 'google_news'],
            ['title' => 'beta', 'source' => 'google_trends'],
            ['title' => 'gamma', 'source' => 'tiktok'],
        ];

        $first = $service->scoreBatch($topics);
        $second = $service->scoreBatch($topics);

        $this->assertCount(3, $first);
        $this->assertCount(3, $second);
        $this->assertSame($first[0]['composite_score'], $second[0]['composite_score']);
        // Mockery ->times(1) enforces the call-count invariant at tearDown
    }

    public function test_different_title_sets_produce_independent_caches(): void
    {
        // Two different title sets → two separate cache keys → two AI calls
        $this->bindArticleGenMock(2);

        $service = new TopicScoringService();

        $setA = [
            ['title' => 'alpha', 'source' => 'google_news'],
            ['title' => 'beta', 'source' => 'google_trends'],
            ['title' => 'gamma', 'source' => 'tiktok'],
        ];
        $setB = [
            ['title' => 'delta', 'source' => 'google_news'],
            ['title' => 'epsilon', 'source' => 'google_trends'],
            ['title' => 'zeta', 'source' => 'tiktok'],
        ];

        $service->scoreBatch($setA);
        $service->scoreBatch($setB);

        // Again, Mockery ->times(2) enforces the invariant
        $this->assertTrue(true);
    }

    public function test_preserves_original_topic_fields(): void
    {
        $this->bindArticleGenMock(1);
        $service = new TopicScoringService();

        $topics = [
            ['title' => 'alpha', 'source' => 'google_news', 'heat' => 'hot', 'publisher' => 'Reuters', 'publisher_tier' => 1, 'pub_date' => now()->subHours(1)->toIso8601String(), 'publisher_count' => 3],
            ['title' => 'beta', 'source' => 'google_trends'],
            ['title' => 'gamma', 'source' => 'tiktok'],
        ];

        $result = $service->scoreBatch($topics);

        $this->assertSame('hot', $result[0]['heat']);
        $this->assertSame('Reuters', $result[0]['publisher']);
        $this->assertSame('google_news', $result[0]['source']);
    }

    public function test_empty_batch_returns_empty(): void
    {
        $service = new TopicScoringService();
        $this->assertSame([], $service->scoreBatch([]));
    }

    public function test_ai_failure_does_not_poison_cache_for_subsequent_calls(): void
    {
        // Simulate AI failure on the FIRST call — should return zero-scores
        // without caching. The SECOND call should re-invoke Sonnet (now
        // succeeding) and produce the real scores. Under the old behavior,
        // the second call would get cached zeros and never invoke Sonnet.
        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldReceive('runSonnetSync')
            ->twice()
            ->andReturnValues([
                ['success' => false, 'output' => '', 'error' => 'ssh timeout'],
                [
                    'success' => true,
                    'output' => json_encode([
                        ['title' => 'alpha', 'virality_score' => 90, 'triggers' => [
                            'social_currency' => true, 'high_arousal' => true,
                            'practical_utility' => false, 'identity_signaling' => true,
                            'cognitive_gap' => false,
                        ]],
                        ['title' => 'beta', 'virality_score' => 55, 'triggers' => [
                            'social_currency' => false, 'high_arousal' => false,
                            'practical_utility' => true, 'identity_signaling' => false,
                            'cognitive_gap' => true,
                        ]],
                        ['title' => 'gamma', 'virality_score' => 30, 'triggers' => [
                            'social_currency' => false, 'high_arousal' => false,
                            'practical_utility' => false, 'identity_signaling' => false,
                            'cognitive_gap' => false,
                        ]],
                    ]),
                    'error' => null,
                ],
            ]);
        app()->instance(ArticleGenerationService::class, $mock);

        $service = new TopicScoringService();
        $topics = [
            ['title' => 'alpha', 'source' => 'google_news'],
            ['title' => 'beta', 'source' => 'google_trends'],
            ['title' => 'gamma', 'source' => 'tiktok'],
        ];

        $firstResult = $service->scoreBatch($topics);
        // First call: AI failed → zero virality scores returned, NOT cached
        $this->assertSame(0, $firstResult[0]['virality_score']);
        $this->assertSame(0, $firstResult[1]['virality_score']);

        $secondResult = $service->scoreBatch($topics);
        // Second call: re-invokes Sonnet (success), returns real scores
        $this->assertSame(90, $secondResult[0]['virality_score']);
        $this->assertSame(55, $secondResult[1]['virality_score']);
        $this->assertSame(30, $secondResult[2]['virality_score']);
    }
}
