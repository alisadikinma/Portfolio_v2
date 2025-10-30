<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ServiceApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    /** @test */
    public function it_can_list_all_services()
    {
        Service::factory()->count(5)->create(['active' => true]);
        Service::factory()->count(2)->create(['active' => false]);

        $response = $this->getJson('/api/services');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'slug',
                        'description',
                        'content',
                        'icon',
                        'features',
                        'active',
                        'order',
                        'created_at',
                        'updated_at',
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_filter_active_services()
    {
        Service::factory()->count(3)->create(['active' => true]);
        Service::factory()->count(2)->create(['active' => false]);

        $response = $this->getJson('/api/services?active=1');

        $response->assertOk();
        $this->assertCount(3, $response->json('data'));
    }

    /** @test */
    public function it_can_search_services()
    {
        Service::factory()->create(['title' => 'Web Development']);
        Service::factory()->create(['title' => 'Mobile App Development']);
        Service::factory()->create(['title' => 'UI/UX Design']);

        $response = $this->getJson('/api/services?search=Development');

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    /** @test */
    public function it_can_show_a_service_by_slug()
    {
        $service = Service::factory()->create(['slug' => 'web-development']);

        $response = $this->getJson("/api/services/web-development");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'data' => [
                    'slug' => 'web-development',
                ]
            ]);
    }

    /** @test */
    public function it_returns_404_for_non_existent_service()
    {
        $response = $this->getJson('/api/services/non-existent-slug');

        $response->assertNotFound();
    }

    /** @test */
    public function authenticated_user_can_create_service()
    {
        $serviceData = [
            'title' => 'Web Development',
            'description' => 'Professional web development services',
            'content' => 'Full content here',
            'icon' => 'code',
            'features' => ['Feature 1', 'Feature 2', 'Feature 3'],
            'active' => true,
            'order' => 1,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/services', $serviceData);

        $response->assertCreated()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'id',
                    'title',
                    'slug',
                    'description',
                    'content',
                    'icon',
                    'features',
                    'active',
                    'order',
                ]
            ]);

        $this->assertDatabaseHas('services', [
            'title' => 'Web Development',
            'slug' => 'web-development',
        ]);
    }

    /** @test */
    public function guest_cannot_create_service()
    {
        $serviceData = [
            'title' => 'Web Development',
            'description' => 'Test',
        ];

        $response = $this->postJson('/api/admin/services', $serviceData);

        $response->assertUnauthorized();
    }

    /** @test */
    public function it_validates_required_fields_when_creating()
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/services', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    /** @test */
    public function authenticated_user_can_update_service()
    {
        $service = Service::factory()->create(['slug' => 'old-service']);

        $updateData = [
            'title' => 'Updated Service',
            'description' => 'Updated description',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/admin/services/{$service->slug}", $updateData);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Service updated successfully',
            ]);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'title' => 'Updated Service',
        ]);
    }

    /** @test */
    public function authenticated_user_can_delete_service()
    {
        $service = Service::factory()->create();

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/admin/services/{$service->slug}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Service deleted successfully',
            ]);

        $this->assertDatabaseMissing('services', [
            'id' => $service->id,
        ]);
    }

    /** @test */
    public function it_auto_generates_slug_from_title()
    {
        $serviceData = [
            'title' => 'Custom Web Development',
            'description' => 'Test',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/services', $serviceData);

        $response->assertCreated();

        $this->assertDatabaseHas('services', [
            'title' => 'Custom Web Development',
            'slug' => 'custom-web-development',
        ]);
    }

    /** @test */
    public function it_sets_default_order_if_not_provided()
    {
        Service::factory()->create(['order' => 5]);

        $serviceData = [
            'title' => 'New Service',
            'description' => 'Test',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/services', $serviceData);

        $response->assertCreated();

        $service = Service::where('title', 'New Service')->first();
        $this->assertEquals(6, $service->order);
    }

    /** @test */
    public function it_can_order_services_by_different_fields()
    {
        Service::factory()->create(['title' => 'A Service', 'order' => 3]);
        Service::factory()->create(['title' => 'B Service', 'order' => 1]);
        Service::factory()->create(['title' => 'C Service', 'order' => 2]);

        $response = $this->getJson('/api/services?order_by=order&order_dir=asc');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertEquals(1, $data[0]['order']);
        $this->assertEquals(2, $data[1]['order']);
        $this->assertEquals(3, $data[2]['order']);
    }

    /** @test */
    public function it_supports_pagination()
    {
        Service::factory()->count(15)->create();

        $response = $this->getJson('/api/services?per_page=10');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data',
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'from', 'last_page', 'per_page', 'to', 'total'],
            ]);

        $this->assertEquals(10, count($response->json('data')));
        $this->assertEquals(15, $response->json('meta.total'));
    }

    /** @test */
    public function it_can_return_all_services_without_pagination()
    {
        Service::factory()->count(15)->create();

        $response = $this->getJson('/api/services?all=true');

        $response->assertOk()
            ->assertJsonMissing(['links', 'meta']);

        $this->assertEquals(15, count($response->json('data')));
    }
}
