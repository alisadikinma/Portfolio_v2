<?php

namespace Tests\Feature;

use App\Models\ContentIdea;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SaveResearchEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // APP_URL subpath workaround (same pattern as Phase A.6 + Phase C tests).
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');

        // SQLite in tests rejects the later MySQL-only status enum ALTERs.
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = ON');
        }
    }

    protected function tearDown(): void
    {
        // Reset the PRAGMA so sibling tests in the same artisan process
        // (notably ResearchTierOverrideMigrationTest's enum-rejection
        // assertion) see a clean SQLite session with CHECK constraints
        // re-enabled. PRAGMAs are session-local and persist across tests
        // otherwise.
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA ignore_check_constraints = OFF');
        }
        parent::tearDown();
    }

    private function authenticate(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
    }

    private function makeIdea(): ContentIdea
    {
        return ContentIdea::create([
            'title' => 'Claude Design vs ChatGPT for Solo Founders',
            'source' => 'manual',
            'status' => 'researching',
            'progress_percentage' => 15,
            'current_step' => 'research',
        ]);
    }

    private function samplePayload(): array
    {
        return [
            'research_data' => [
                'data_points' => [
                    [
                        'stat' => '47% of 2,400 devs use AI coding assistants',
                        'source' => 'Stack Overflow Survey 2026',
                        'url' => 'https://example.com/survey',
                        'year' => 2026,
                    ],
                ],
                'quotes' => [
                    [
                        'text' => 'Claude Design removes the designer-developer handoff',
                        'attribution' => 'Dario Amodei',
                        'url' => 'https://example.com/blog',
                    ],
                ],
                'entities' => [
                    [
                        'name' => 'ChatGPT',
                        'url' => 'https://chat.openai.com',
                        'visual_style' => 'Green-teal + dark grey palette, speech bubbles, Söhne sans-serif, centered send button, clinical mood.',
                    ],
                ],
                'personas' => [
                    [
                        'name' => 'Overwhelmed Solo Founder',
                        'pain' => 'No designer on team',
                        'emotion' => 'Anxious',
                        'voice' => 'I can ship features but my landing page looks like 2015',
                    ],
                ],
                'written_guides' => [
                    [
                        'task' => 'Setup X',
                        'steps' => ['Step 1', 'Step 2'],
                        'source_url' => 'https://example.com/docs',
                    ],
                ],
            ],
        ];
    }

    public function test_happy_path_persists_full_research_payload(): void
    {
        $this->authenticate();
        $idea = $this->makeIdea();
        $payload = $this->samplePayload();

        $response = $this->putJson("/api/automation/content-ideas/{$idea->id}/save-research", $payload);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);

        $fresh = ContentIdea::find($idea->id);
        $this->assertIsArray($fresh->research_data);
        $this->assertEquals($payload['research_data'], $fresh->research_data);

        // Deep spot-checks confirming every schema branch survived round-trip
        $this->assertSame('47% of 2,400 devs use AI coding assistants', $fresh->research_data['data_points'][0]['stat']);
        $this->assertSame('Dario Amodei', $fresh->research_data['quotes'][0]['attribution']);
        $this->assertSame('ChatGPT', $fresh->research_data['entities'][0]['name']);
        $this->assertSame('Overwhelmed Solo Founder', $fresh->research_data['personas'][0]['name']);
        $this->assertSame(['Step 1', 'Step 2'], $fresh->research_data['written_guides'][0]['steps']);
    }

    public function test_malformed_body_missing_research_data_returns_400(): void
    {
        $this->authenticate();
        $idea = $this->makeIdea();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) use ($idea) {
                return $message === '[save-research] rejected malformed body'
                    && ($context['idea_id'] ?? null) == $idea->id;
            });

        // Send some junk top-level key so the request isn't empty — mimics
        // shell-quoting corruption where body ends up as {"<url>": null}.
        $response = $this->putJson(
            "/api/automation/content-ideas/{$idea->id}/save-research",
            ['not_research_data' => 'garbage']
        );

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
        $this->assertStringContainsString('Missing or invalid research_data', $response->json('message'));
    }

    public function test_malformed_body_missing_data_points_returns_400(): void
    {
        $this->authenticate();
        $idea = $this->makeIdea();

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message) {
                return $message === '[save-research] rejected malformed body';
            });

        // research_data present but missing required data_points anchor key.
        $response = $this->putJson(
            "/api/automation/content-ideas/{$idea->id}/save-research",
            ['research_data' => ['quotes' => []]]
        );

        $response->assertStatus(400);
        $response->assertJsonPath('success', false);
    }

    public function test_non_existent_idea_returns_404(): void
    {
        $this->authenticate();

        $response = $this->putJson(
            '/api/automation/content-ideas/99999999/save-research',
            $this->samplePayload()
        );

        $response->assertStatus(404);
        $response->assertJsonPath('success', false);
    }

    public function test_idempotent_second_call_overwrites_first(): void
    {
        $this->authenticate();
        $idea = $this->makeIdea();

        // First call — seed with sample payload
        $first = $this->samplePayload();
        $this->putJson("/api/automation/content-ideas/{$idea->id}/save-research", $first)
            ->assertStatus(200);

        $afterFirst = ContentIdea::find($idea->id)->research_data;
        $this->assertSame('ChatGPT', $afterFirst['entities'][0]['name']);

        // Second call — distinct payload, must fully overwrite (not merge)
        $second = [
            'research_data' => [
                'data_points' => [
                    [
                        'stat' => '92% adoption among Fortune 500',
                        'source' => 'Gartner 2026',
                        'url' => 'https://example.com/gartner',
                        'year' => 2026,
                    ],
                ],
                'quotes' => [],
                'entities' => [
                    [
                        'name' => 'Claude',
                        'url' => 'https://claude.ai',
                        'visual_style' => 'Warm terracotta palette, conversational layout.',
                    ],
                ],
                'personas' => [],
                'written_guides' => [],
            ],
        ];

        $this->putJson("/api/automation/content-ideas/{$idea->id}/save-research", $second)
            ->assertStatus(200);

        $afterSecond = ContentIdea::find($idea->id)->research_data;
        $this->assertEquals($second['research_data'], $afterSecond);
        $this->assertSame('Claude', $afterSecond['entities'][0]['name']);
        $this->assertSame('92% adoption among Fortune 500', $afterSecond['data_points'][0]['stat']);
        // Confirm NO stale data from the first call leaked through
        $this->assertCount(0, $afterSecond['quotes']);
        $this->assertCount(0, $afterSecond['personas']);
    }
}
