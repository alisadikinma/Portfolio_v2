<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GeminiGenCircuitBreaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class GeminigenCircuitStatusEndpointTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        // Subpath APP_URL workaround per CLAUDE.md known issue (used in May 14 GeminiGenRelayWebhookTest)
        config(['app.url' => 'http://localhost']);
        \Illuminate\Support\Facades\URL::forceRootUrl('http://localhost');

        $user = User::factory()->create();
        $this->token = $user->createToken('test')->plainTextToken;
    }

    public function test_returns_401_when_unauthenticated(): void
    {
        $response = $this->getJson('/api/admin/geminigen/circuit-status');
        $response->assertStatus(401);
    }

    public function test_returns_closed_state_payload(): void
    {
        $response = $this->getJson('/api/admin/geminigen/circuit-status', [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.state', 'closed');
        $response->assertJsonPath('data.failure_count_in_window', 0);
        $this->assertNull($response->json('data.opened_at'));
        $this->assertNull($response->json('data.next_probe_at'));
    }

    public function test_returns_open_state_payload(): void
    {
        $breaker = app(GeminiGenCircuitBreaker::class);
        for ($i = 0; $i < 5; $i++) {
            $breaker->recordFailure(503);
        }

        $response = $this->getJson('/api/admin/geminigen/circuit-status', [
            'Authorization' => 'Bearer ' . $this->token,
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.state', 'open');
        $response->assertJsonPath('data.failure_count_in_window', 5);
        $this->assertNotNull($response->json('data.opened_at'));
        $this->assertNotNull($response->json('data.next_probe_at'));
    }
}
