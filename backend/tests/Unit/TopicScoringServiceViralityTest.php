<?php

namespace Tests\Unit;

use App\Services\ArticleGenerationService;
use App\Services\TopicScoringService;
use Mockery;
use Tests\TestCase;

class TopicScoringServiceViralityTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Bind a Mockery mock of ArticleGenerationService into the Laravel
     * container so TopicScoringService::scoreViralityBatch() pulls the mock
     * when it calls app(ArticleGenerationService::class).
     */
    private function bindArticleGenMock(array $runSonnetSyncReturn): void
    {
        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldReceive('runSonnetSync')
            ->andReturn($runSonnetSyncReturn);

        app()->instance(ArticleGenerationService::class, $mock);
    }

    public function test_happy_path_parses_batch_scores(): void
    {
        $aiJson = json_encode([
            ['title' => 't1', 'virality_score' => 80, 'triggers' => [
                'social_currency' => true,
                'high_arousal' => true,
                'practical_utility' => false,
                'identity_signaling' => true,
                'cognitive_gap' => false,
            ]],
            ['title' => 't2', 'virality_score' => 45, 'triggers' => [
                'social_currency' => false,
                'high_arousal' => false,
                'practical_utility' => true,
                'identity_signaling' => false,
                'cognitive_gap' => true,
            ]],
        ]);

        $this->bindArticleGenMock([
            'success' => true,
            'output' => $aiJson,
            'error' => null,
        ]);

        $service = new TopicScoringService();
        $result = $service->scoreViralityBatch([
            ['title' => 't1', 'source' => 'google_news', 'description' => 'first'],
            ['title' => 't2', 'source' => 'google_trends', 'description' => 'second'],
        ]);

        $this->assertCount(2, $result);
        $this->assertSame(80, $result[0]['virality_score']);
        $this->assertTrue($result[0]['triggers']['social_currency']);
        $this->assertSame(45, $result[1]['virality_score']);
        $this->assertTrue($result[1]['triggers']['practical_utility']);
    }

    public function test_throws_when_batch_exceeds_cap(): void
    {
        $service = new TopicScoringService();
        $topics = [];
        for ($i = 0; $i < 21; $i++) {
            $topics[] = ['title' => "topic-{$i}", 'source' => 'google_news'];
        }

        $this->expectException(\InvalidArgumentException::class);
        $service->scoreViralityBatch($topics);
    }

    public function test_returns_zero_scores_when_ai_call_fails(): void
    {
        $this->bindArticleGenMock([
            'success' => false,
            'output' => '',
            'error' => 'SSH timeout',
        ]);

        $service = new TopicScoringService();
        $result = $service->scoreViralityBatch([
            ['title' => 't1', 'source' => 'google_news'],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame(0, $result[0]['virality_score']);
        $this->assertIsArray($result[0]['triggers']);
        $this->assertFalse($result[0]['triggers']['social_currency']);
        $this->assertFalse($result[0]['triggers']['high_arousal']);
        $this->assertFalse($result[0]['triggers']['practical_utility']);
        $this->assertFalse($result[0]['triggers']['identity_signaling']);
        $this->assertFalse($result[0]['triggers']['cognitive_gap']);
    }

    public function test_returns_zero_scores_when_ai_output_malformed(): void
    {
        $this->bindArticleGenMock([
            'success' => true,
            'output' => 'not valid json whatsoever {{{',
            'error' => null,
        ]);

        $service = new TopicScoringService();
        $result = $service->scoreViralityBatch([
            ['title' => 't1', 'source' => 'google_news'],
            ['title' => 't2', 'source' => 'google_trends'],
        ]);

        $this->assertCount(2, $result);
        $this->assertSame(0, $result[0]['virality_score']);
        $this->assertSame(0, $result[1]['virality_score']);
    }

    public function test_empty_batch_returns_empty_array(): void
    {
        $service = new TopicScoringService();
        $this->assertSame([], $service->scoreViralityBatch([]));
    }

    public function test_prompt_actually_contains_topic_titles_and_count(): void
    {
        // Regression: PHP heredocs require {$var} — a bare {var} is a literal.
        // This test captures the prompt text sent to Sonnet and asserts the
        // topic titles + count are interpolated, not left as placeholders.
        $capturedPrompt = null;

        $mock = Mockery::mock(ArticleGenerationService::class);
        $mock->shouldReceive('runSonnetSync')
            ->once()
            ->andReturnUsing(function ($prompt) use (&$capturedPrompt) {
                $capturedPrompt = $prompt;
                return [
                    'success' => true,
                    'output' => '[{"title":"alpha","virality_score":50,"triggers":{"social_currency":false,"high_arousal":false,"practical_utility":false,"identity_signaling":false,"cognitive_gap":false}},{"title":"beta","virality_score":50,"triggers":{"social_currency":false,"high_arousal":false,"practical_utility":false,"identity_signaling":false,"cognitive_gap":false}}]',
                    'error' => null,
                ];
            });
        app()->instance(ArticleGenerationService::class, $mock);

        $service = new TopicScoringService();
        $service->scoreViralityBatch([
            ['title' => 'alpha-topic-unique-marker', 'source' => 'google_news'],
            ['title' => 'beta-topic-unique-marker', 'source' => 'google_trends'],
        ]);

        $this->assertNotNull($capturedPrompt);
        $this->assertStringContainsString('alpha-topic-unique-marker', $capturedPrompt);
        $this->assertStringContainsString('beta-topic-unique-marker', $capturedPrompt);
        $this->assertStringContainsString('You are scoring 2 trending topics', $capturedPrompt);
        // Catch any future heredoc-placeholder regression
        $this->assertStringNotContainsString('{count}', $capturedPrompt);
        $this->assertStringNotContainsString('{listed}', $capturedPrompt);
    }
}
