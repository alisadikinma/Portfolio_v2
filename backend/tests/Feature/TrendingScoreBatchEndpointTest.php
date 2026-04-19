<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\User;
use App\Services\TopicScoringService;
use App\Services\TrendingTopicService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class TrendingScoreBatchEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Neutralize APP_URL subpath so $this->postJson('/api/...') matches routes.
        // The project's .env points APP_URL at a XAMPP subfolder for production
        // URL generation, which confuses the HTTP test client.
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        return $user;
    }

    private function bindTrendingMock(?string $expectedSource, array $scoredReturn): void
    {
        $mock = Mockery::mock(TrendingTopicService::class);
        $mock->shouldReceive('getScoredTopics')
            ->once()
            ->with($expectedSource)
            ->andReturn($scoredReturn);

        app()->instance(TrendingTopicService::class, $mock);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->postJson('/api/admin/content-engine/trending/score-batch');

        $response->assertStatus(401);
    }

    public function test_returns_scored_data_sorted_by_composite_score(): void
    {
        $this->actingAsAdmin();

        $scored = [
            [
                'title' => 'Top Viral Topic',
                'source' => 'google_news',
                'composite_score' => 88,
                'momentum_score' => 75,
                'virality_score' => 95,
                'triggers' => [
                    'social_currency' => true, 'high_arousal' => true,
                    'practical_utility' => false, 'identity_signaling' => true,
                    'cognitive_gap' => false,
                ],
            ],
            [
                'title' => 'Mid Topic',
                'source' => 'google_news',
                'composite_score' => 55,
                'momentum_score' => 50,
                'virality_score' => 58,
                'triggers' => [
                    'social_currency' => false, 'high_arousal' => false,
                    'practical_utility' => true, 'identity_signaling' => false,
                    'cognitive_gap' => true,
                ],
            ],
        ];

        $this->bindTrendingMock('google_news', $scored);

        $response = $this->postJson('/api/admin/content-engine/trending/score-batch', [
            'source' => 'google_news',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                ['title' => 'Top Viral Topic', 'composite_score' => 88],
                ['title' => 'Mid Topic', 'composite_score' => 55],
            ],
        ]);
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => ['title', 'source', 'composite_score', 'momentum_score', 'virality_score', 'triggers'],
            ],
        ]);
    }

    public function test_source_filter_is_optional(): void
    {
        $this->actingAsAdmin();

        $this->bindTrendingMock(null, [
            [
                'title' => 'Any',
                'source' => 'google_trends',
                'composite_score' => 60,
                'momentum_score' => 50,
                'virality_score' => 67,
                'triggers' => [],
            ],
        ]);

        $response = $this->postJson('/api/admin/content-engine/trending/score-batch');

        $response->assertStatus(200);
        $response->assertJsonPath('data.0.title', 'Any');
    }

    public function test_empty_result_returns_empty_data_array(): void
    {
        $this->actingAsAdmin();

        $this->bindTrendingMock('tiktok', []);

        $response = $this->postJson('/api/admin/content-engine/trending/score-batch', [
            'source' => 'tiktok',
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [],
        ]);
    }

    public function test_import_persists_virality_score_and_breakdown(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/content-engine/trending/import', [
            'topics' => [[
                'title' => 'Scored Topic',
                'source' => 'google_news',
                'composite_score' => 88,
                'momentum_score' => 75,
                'virality_score' => 95,
                'triggers' => [
                    'social_currency' => true,
                    'high_arousal' => true,
                    'practical_utility' => false,
                    'identity_signaling' => true,
                    'cognitive_gap' => false,
                ],
            ]],
        ]);

        $response->assertStatus(201);

        $idea = ContentIdea::where('title', 'Scored Topic')->first();
        $this->assertNotNull($idea);
        $this->assertSame(88, (int) $idea->virality_score);
        $this->assertIsArray($idea->virality_breakdown);
        $this->assertSame(75, $idea->virality_breakdown['momentum']);
        $this->assertSame(95, $idea->virality_breakdown['virality']);
        $this->assertTrue($idea->virality_breakdown['triggers']['social_currency']);
        $this->assertTrue($idea->virality_breakdown['triggers']['identity_signaling']);
        $this->assertFalse($idea->virality_breakdown['triggers']['cognitive_gap']);
    }

    public function test_import_without_score_leaves_fields_null(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/content-engine/trending/import', [
            'topics' => [[
                'title' => 'Legacy Unscored Topic',
                'source' => 'google_news',
            ]],
        ]);

        $response->assertStatus(201);

        $idea = ContentIdea::where('title', 'Legacy Unscored Topic')->first();
        $this->assertNotNull($idea);
        $this->assertNull($idea->virality_score);
        $this->assertNull($idea->virality_breakdown);
    }
}
