<?php

namespace Tests\Feature;

use App\Models\EntityReference;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EntityRefsLookupEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost']);
        url()->forceRootUrl('http://localhost');
        $this->actingAs(User::factory()->create(), 'sanctum');
    }

    /** @test */
    public function lookup_returns_cached_entity_when_exists(): void
    {
        EntityReference::create([
            'qid' => 'Q35525',
            'name' => 'White House',
            'entity_type' => 'landmark',
            'local_path' => 'entity-refs/landmark/Q35525_white-house.jpg',
            'local_url' => 'https://alisadikinma.com/storage/entity-refs/landmark/Q35525_white-house.jpg',
            'license' => 'PD-USGov',
            'source' => 'wikimedia',
            'fetched_at' => now(),
        ]);

        // Seed the name-to-QID cache so findOrFetch doesn't need to hit SPARQL
        cache()->put('wikidata_qid:white house', 'Q35525', now()->addDays(30));

        Http::fake();

        $response = $this->getJson('/api/automation/entity-refs/lookup?' . http_build_query([
            'name' => 'White House',
            'type' => 'landmark',
        ]));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'cached' => true,
        ]);

        $data = $response->json('data');
        $this->assertSame('Q35525', $data['qid']);
        $this->assertSame('PD-USGov', $data['license']);

        Http::assertNothingSent();
    }

    /** @test */
    public function lookup_returns_cached_false_when_entity_not_found(): void
    {
        cache()->put('wikidata_qid:unknown entity', null, now()->addDays(30));

        Http::fake();

        $response = $this->getJson('/api/automation/entity-refs/lookup?' . http_build_query([
            'name' => 'Unknown Entity',
        ]));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'cached' => false,
        ]);
        $this->assertNull($response->json('data'));
    }

    /** @test */
    public function lookup_requires_name_param(): void
    {
        $response = $this->getJson('/api/automation/entity-refs/lookup');
        $response->assertStatus(400);
        $response->assertJson(['success' => false]);
    }
}
