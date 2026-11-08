<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GeminiGenRelayTokenTest extends TestCase
{
    use RefreshDatabase;

    private function cb(): string
    {
        return rtrim(strtr(base64_encode('https://caller.example/cb'), '+/', '-_'), '=');
    }

    public function test_array_token_is_rejected_with_401(): void
    {
        $token = User::factory()->create()->createToken('relay', ['geminigen:relay'])->plainTextToken;

        $r = $this->postJson('/api/automation/geminigen/webhook?token[]='.$token.'&cb='.$this->cb(), []);

        $r->assertStatus(401)->assertJsonPath('error.code', 'MISSING_TOKEN');
    }

    public function test_valid_string_token_passes_token_gate(): void
    {
        $token = User::factory()->create()->createToken('relay', ['geminigen:relay'])->plainTextToken;

        // Token gate passes; request then stops at the signature gate (403), proving it got past 401.
        $r = $this->postJson('/api/automation/geminigen/webhook?token='.$token.'&cb='.$this->cb(), []);

        $r->assertStatus(403)->assertJsonPath('error.code', 'MISSING_SIGNATURE');
    }
}
