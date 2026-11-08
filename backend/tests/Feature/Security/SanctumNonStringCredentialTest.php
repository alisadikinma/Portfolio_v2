<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression guard inspired by CVE-2026-114509 (Laravel TokenGuard accepted
 * non-string API credentials). This app has no "token" guard driver — admin
 * API auth is Sanctum — so the guard here is: a VALID token only works as a
 * plain Bearer string; wrapped in an array or moved to a query/body field it
 * must never authenticate. The positive control proves the route accepts a
 * correct token, so the 401 cases cannot pass by rejecting everything.
 */
class SanctumNonStringCredentialTest extends TestCase
{
    use RefreshDatabase;

    private const ROUTE = '/api/admin/dashboard/stats';

    private function validToken(): string
    {
        return User::factory()->create()->createToken('pf2-cve-guard')->plainTextToken;
    }

    public function test_positive_control_valid_bearer_token_is_accepted(): void
    {
        $this->withToken($this->validToken())->getJson(self::ROUTE)->assertStatus(200);
    }

    public function test_valid_token_wrapped_in_query_array_is_rejected(): void
    {
        $t = $this->validToken();
        $this->getJson(self::ROUTE.'?api_token[]='.urlencode($t))->assertStatus(401);
        $this->getJson(self::ROUTE.'?token[]='.urlencode($t))->assertStatus(401);
    }

    public function test_valid_token_wrapped_in_json_body_array_is_rejected(): void
    {
        $t = $this->validToken();
        $this->json('GET', self::ROUTE, ['api_token' => [$t]])->assertStatus(401);
        $this->json('GET', self::ROUTE, ['token' => [$t]])->assertStatus(401);
    }

    public function test_valid_token_as_plain_query_string_is_rejected(): void
    {
        // Sanctum reads only the Authorization header; a query token must not log in.
        $this->getJson(self::ROUTE.'?api_token='.urlencode($this->validToken()))->assertStatus(401);
    }

    public function test_empty_and_malformed_bearer_headers_are_rejected(): void
    {
        foreach (['Bearer', 'Bearer ', 'Bearer []', 'Bearer 0', 'Bearer x[]', 'Basic eDp4'] as $header) {
            $this->withHeaders(['Authorization' => $header])
                ->getJson(self::ROUTE)
                ->assertStatus(401);
        }
    }

    public function test_no_credentials_is_rejected(): void
    {
        $this->getJson(self::ROUTE)->assertStatus(401);
    }
}
