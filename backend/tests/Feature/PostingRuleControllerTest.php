<?php

namespace Tests\Feature;

use App\Models\PostingTimeRule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Coverage for /api/admin/posting-rules — the calendar heatmap data source.
 *
 * Per docs/plans/2026-05-06-linkedin-calendar-and-ai-time-rules.md §6.1:
 *   GET  ?platform=linkedin -> rules + day_summaries + sources
 *   POST /refresh           -> dispatches the research artisan via Artisan::queue
 */
class PostingRuleControllerTest extends TestCase
{
    use RefreshDatabase;

    private function actingAsAdmin(): User
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);
        return $user;
    }

    private function seedRule(array $overrides = []): PostingTimeRule
    {
        return PostingTimeRule::create(array_merge([
            'platform' => 'linkedin',
            'day_of_week' => 2,
            'hour' => 9,
            'timezone' => 'Asia/Jakarta',
            'score' => 95,
            'audience' => 'b2b_tech',
            'source_url' => 'https://hootsuite.com/research/2026',
            'source_title' => 'Hootsuite 2026 Best Posting Times',
            'notes' => 'B2B sweet spot',
            'last_researched_at' => Carbon::now(),
        ], $overrides));
    }

    public function test_index_requires_auth(): void
    {
        $response = $this->getJson('/api/admin/posting-rules?platform=linkedin');
        $response->assertStatus(401);
    }

    public function test_index_returns_rules_with_day_summaries_and_sources(): void
    {
        $this->actingAsAdmin();

        // Seed Tuesday at multiple hours + Thursday peak
        $this->seedRule(['day_of_week' => 2, 'hour' => 9, 'score' => 95]);
        $this->seedRule(['day_of_week' => 2, 'hour' => 10, 'score' => 90]);
        $this->seedRule(['day_of_week' => 2, 'hour' => 14, 'score' => 82]);
        $this->seedRule(['day_of_week' => 2, 'hour' => 23, 'score' => 30]); // not optimal
        $this->seedRule(['day_of_week' => 4, 'hour' => 11, 'score' => 88]);

        $response = $this->getJson('/api/admin/posting-rules?platform=linkedin');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'platform',
                    'audience',
                    'rules' => [['day_of_week', 'hour', 'score', 'note']],
                    'day_summaries' => [['day_of_week', 'best_score', 'best_hours']],
                    'sources' => [['url', 'title']],
                    'last_researched_at',
                    'rule_count',
                ],
            ]);

        $data = $response->json('data');
        $this->assertSame('linkedin', $data['platform']);
        $this->assertSame(5, $data['rule_count']);

        // Tuesday day summary: best_score=95, best_hours=[9,10,14] (the three >=80, sorted)
        $tueSummary = collect($data['day_summaries'])->firstWhere('day_of_week', 2);
        $this->assertNotNull($tueSummary);
        $this->assertSame(95, $tueSummary['best_score']);
        $this->assertSame([9, 10, 14], $tueSummary['best_hours']);

        // hour=23 with score=30 should NOT appear in best_hours (below 80 threshold)
        $this->assertNotContains(23, $tueSummary['best_hours']);
    }

    public function test_index_filters_by_platform_query_param(): void
    {
        $this->actingAsAdmin();

        $this->seedRule(['platform' => 'linkedin', 'day_of_week' => 1, 'hour' => 9]);
        $this->seedRule(['platform' => 'instagram', 'day_of_week' => 5, 'hour' => 19]);

        $response = $this->getJson('/api/admin/posting-rules?platform=instagram');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertSame('instagram', $data['platform']);
        $this->assertSame(1, $data['rule_count']);
        $this->assertSame(5, $data['rules'][0]['day_of_week']);
    }

    public function test_index_rejects_invalid_platform_with_422(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/posting-rules?platform=myspace');
        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'INVALID_PLATFORM');
    }

    public function test_refresh_dispatches_research_command(): void
    {
        $this->actingAsAdmin();

        Artisan::shouldReceive('queue')
            ->once()
            ->with('posting-rules:research', \Mockery::on(function ($args) {
                return ($args['--platform'] ?? null) === 'linkedin'
                    && ($args['--force'] ?? null) === true;
            }));

        $response = $this->postJson('/api/admin/posting-rules/refresh', [
            'platform' => 'linkedin',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => ['dispatched_at', 'platform', 'audience', 'eta_seconds'],
            ]);
    }

    public function test_refresh_validates_platform_required(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/posting-rules/refresh', []);
        $response->assertStatus(422);
    }

    public function test_refresh_validates_platform_enum(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/admin/posting-rules/refresh', [
            'platform' => 'myspace',
        ]);
        $response->assertStatus(422);
    }

    public function test_index_returns_empty_lists_when_no_rules_seeded(): void
    {
        $this->actingAsAdmin();

        $response = $this->getJson('/api/admin/posting-rules?platform=linkedin');
        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertSame(0, $data['rule_count']);
        $this->assertSame([], $data['rules']);
        $this->assertSame([], $data['day_summaries']);
        $this->assertSame([], $data['sources']);
        $this->assertNull($data['last_researched_at']);
    }
}
