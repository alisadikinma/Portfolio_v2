<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Coverage for the CV Master Markdown API.
 *
 * Verifies:
 *   - Auth gate (no token -> 401)
 *   - Ability gate (token without cv:read -> 403)
 *   - Successful 200 response with text/markdown content type
 */
class CvMasterMarkdownApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Default APP_URL contains the XAMPP subpath which trips relative
        // route resolution under sqlite -- same workaround other Feature
        // tests use (mirrors CvExportApiTest).
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');
    }

    /** @test */
    public function returns_401_without_token(): void
    {
        $this->getJson('/api/cv/master.md')->assertStatus(401);
    }

    /** @test */
    public function returns_403_when_token_missing_cv_read_ability(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['some:other:ability']);

        $this->getJson('/api/cv/master.md')->assertStatus(403);
    }

    /** @test */
    public function returns_200_with_markdown_content_type_when_authorized(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user, ['cv:read']);

        $response = $this->get('/api/cv/master.md');

        $response->assertOk();
        $this->assertStringContainsString(
            'text/markdown',
            $response->headers->get('Content-Type') ?? ''
        );
    }
}
