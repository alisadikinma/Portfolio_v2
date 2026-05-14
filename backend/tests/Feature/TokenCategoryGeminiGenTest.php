<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Coverage for the geminigen_relay token category (Phase A).
 *
 * Verifies that the `geminigen_relay` row is wired into the same
 * TokenController::CATEGORIES registry that drives the existing
 * `automation` and `cv` surfaces, and that all 3 admin endpoints
 * (categories, store, index) honor the new slug end-to-end.
 *
 * Contract per row:
 *   slug      → 'geminigen_relay'
 *   prefix    → 'geminigen-'
 *   abilities → ['geminigen:relay']
 */
class TokenCategoryGeminiGenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Default APP_URL contains the XAMPP subpath which trips relative
        // route resolution under sqlite — match the workaround in
        // CvExportApiTest so the admin routes resolve cleanly.
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');
    }

    /** @test */
    public function test_categories_endpoint_includes_geminigen_relay(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/admin/automation/categories');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $categories = collect($response->json('data'));
        $row = $categories->firstWhere('slug', 'geminigen_relay');

        $this->assertNotNull($row, 'geminigen_relay row missing from /categories response');
        $this->assertSame('geminigen-', $row['prefix']);
        $this->assertSame(['geminigen:relay'], $row['abilities']);
        $this->assertArrayHasKey('label', $row);
        $this->assertArrayHasKey('description', $row);
    }

    /** @test */
    public function test_mint_token_with_geminigen_relay_category_auto_prefixes_name(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/admin/automation/tokens', [
            'name' => 'prod',
            'category' => 'geminigen_relay',
            'abilities' => ['geminigen:relay'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'geminigen-prod')
            ->assertJsonPath('data.category', 'geminigen_relay');

        $this->assertContains('geminigen:relay', $response->json('data.abilities'));
    }

    /** @test */
    public function test_cross_category_ability_rejected(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/admin/automation/tokens', [
            'name' => 'invalid',
            'category' => 'geminigen_relay',
            'abilities' => ['post:write'],
        ]);

        $response->assertStatus(422);
    }

    /** @test */
    public function test_list_filter_by_geminigen_relay_category(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        // Mint one token in each of the 3 categories so we can verify the
        // ?category=geminigen_relay filter narrows to exactly the new bucket.
        $this->postJson('/api/admin/automation/tokens', [
            'name' => 'n8n',
            'category' => 'automation',
            'abilities' => ['post:read'],
        ])->assertStatus(201);

        $this->postJson('/api/admin/automation/tokens', [
            'name' => 'jobhunter',
            'category' => 'cv',
            'abilities' => ['cv:read'],
        ])->assertStatus(201);

        $this->postJson('/api/admin/automation/tokens', [
            'name' => 'relay',
            'category' => 'geminigen_relay',
            'abilities' => ['geminigen:relay'],
        ])->assertStatus(201);

        $response = $this->getJson('/api/admin/automation/tokens?category=geminigen_relay');

        $response->assertOk()
            ->assertJsonPath('success', true);

        $tokens = collect($response->json('data'));
        $this->assertCount(1, $tokens, 'Filter should narrow to exactly 1 geminigen_relay token');
        $this->assertSame('geminigen-relay', $tokens->first()['name']);
        $this->assertSame('geminigen_relay', $tokens->first()['category']);
    }
}
