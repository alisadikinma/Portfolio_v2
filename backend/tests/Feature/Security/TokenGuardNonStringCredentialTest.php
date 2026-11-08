<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * CVE-2026-114509: non-string API credentials must never authenticate.
 * Only the sanctum guard is configured here (no "token" driver in config/auth.php),
 * so this is a regression guard for the auth:sanctum admin routes.
 */
class TokenGuardNonStringCredentialTest extends TestCase
{
    use RefreshDatabase;

    private const ROUTE = '/api/admin/dashboard/stats';

    public function test_array_api_token_in_query_string_is_rejected(): void
    {
        $this->getJson(self::ROUTE.'?api_token[]=x')->assertStatus(401);
    }

    public function test_array_api_token_in_json_body_is_rejected(): void
    {
        $this->json('GET', self::ROUTE, ['api_token' => ['x']])->assertStatus(401);
    }

    public function test_nested_array_token_in_query_string_is_rejected(): void
    {
        $this->getJson(self::ROUTE.'?api_token[a][b]=x&token[]=y')->assertStatus(401);
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
