<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\User;
use App\Services\TopicScoringService;
use App\Services\TrendingTopicService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * Phase A end-to-end: scored trending endpoint → import → idea persistence.
 * Exercises the full chain integrators will hit in production, with a
 * mocked TrendingTopicService::getScoredTopics at the edge so we don't
 * call real Google News / Sonnet in CI.
 */
class PhaseATrendingScoringE2ETest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_full_pipeline_scored_fetch_then_import_persists_chosen_topic(): void
    {
        // 1) Authenticate as admin (real Sanctum token via actingAs)
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');

        // 2) Mock TrendingTopicService::getScoredTopics at the edge so we
        //    don't hit real Google News / Sonnet / cache during the test.
        //    Everything downstream (controller, route, DB write) is real.
        $trendingPayload = [
            [
                'title' => 'Claude 4.8 drops with 2M context',
                'source' => 'google_news',
                'publisher' => 'Anthropic',
                'publisher_tier' => 1,
                'pub_date' => now()->subHours(2)->toIso8601String(),
                'publisher_count' => 8,
                'heat' => 'hot',
                'momentum_score' => 85,
                'virality_score' => 95,
                'composite_score' => 91,
                'triggers' => [
                    'social_currency' => true,
                    'high_arousal' => true,
                    'practical_utility' => true,
                    'identity_signaling' => true,
                    'cognitive_gap' => false,
                ],
            ],
            [
                'title' => 'Cursor vs Windsurf comparison 2026',
                'source' => 'google_news',
                'publisher' => 'TechCrunch',
                'publisher_tier' => 1,
                'pub_date' => now()->subHours(8)->toIso8601String(),
                'publisher_count' => 4,
                'momentum_score' => 65,
                'virality_score' => 72,
                'composite_score' => 69,
                'triggers' => [
                    'social_currency' => false,
                    'high_arousal' => false,
                    'practical_utility' => true,
                    'identity_signaling' => true,
                    'cognitive_gap' => true,
                ],
            ],
            [
                'title' => 'Niche blog rehash',
                'source' => 'google_news',
                'publisher' => 'Some Blog',
                'publisher_tier' => 3,
                'pub_date' => now()->subDays(3)->toIso8601String(),
                'publisher_count' => 1,
                'momentum_score' => 35,
                'virality_score' => 28,
                'composite_score' => 31,
                'triggers' => [
                    'social_currency' => false,
                    'high_arousal' => false,
                    'practical_utility' => false,
                    'identity_signaling' => false,
                    'cognitive_gap' => false,
                ],
            ],
        ];

        $trendingMock = Mockery::mock(TrendingTopicService::class);
        $trendingMock->shouldReceive('getScoredTopics')
            ->once()
            ->with('google_news')
            ->andReturn($trendingPayload);
        app()->instance(TrendingTopicService::class, $trendingMock);

        // 3) Hit the real scored endpoint — controller + route + response envelope.
        $scoredResponse = $this->postJson(
            '/api/admin/content-engine/trending/score-batch?source=google_news'
        );

        $scoredResponse->assertStatus(200);
        $scoredResponse->assertJsonPath('success', true);
        $scoredResponse->assertJsonCount(3, 'data');
        // Endpoint returns sorted topics — top should be the 91 composite.
        $scoredResponse->assertJsonPath('data.0.composite_score', 91);
        $scoredResponse->assertJsonPath('data.0.title', 'Claude 4.8 drops with 2M context');

        // 4) User picks the top-scored topic and imports it.
        $top = $scoredResponse->json('data.0');
        $importResponse = $this->postJson(
            '/api/admin/content-engine/trending/import',
            ['topics' => [$top]]
        );

        $importResponse->assertStatus(201);
        $importResponse->assertJsonPath('success', true);

        // 5) Verify ContentIdea row carries the scored signals end-to-end.
        $idea = ContentIdea::where('title', 'Claude 4.8 drops with 2M context')->first();
        $this->assertNotNull($idea, 'Imported idea should exist in DB');
        $this->assertSame(91, (int) $idea->virality_score);
        $this->assertSame('draft', $idea->status);
        $this->assertSame('google_news', $idea->source);

        $this->assertIsArray($idea->virality_breakdown);
        $this->assertSame(85, $idea->virality_breakdown['momentum']);
        $this->assertSame(95, $idea->virality_breakdown['virality']);
        $this->assertSame(91, $idea->virality_breakdown['composite']);
        $this->assertIsArray($idea->virality_breakdown['triggers']);
        $this->assertTrue($idea->virality_breakdown['triggers']['social_currency']);
        $this->assertTrue($idea->virality_breakdown['triggers']['practical_utility']);
        $this->assertFalse($idea->virality_breakdown['triggers']['cognitive_gap']);

        // 6) source_data should preserve the raw enrichment too (publisher,
        //    heat, pub_date, etc.) for downstream display — a Phase A
        //    invariant verifying we haven't regressed the fix from
        //    commit 847b15a6.
        $this->assertIsArray($idea->source_data);
        $this->assertSame('Anthropic', $idea->source_data['publisher']);
        $this->assertSame(1, $idea->source_data['publisher_tier']);
        $this->assertSame('hot', $idea->source_data['heat']);
    }
}
