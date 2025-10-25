<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\Gallery;
use App\Models\GalleryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GalleryApiTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Storage::fake('public');
    }

    /** @test */
    public function it_can_list_all_galleries()
    {
        Gallery::factory()->count(5)->create(['is_active' => true]);

        $response = $this->getJson('/api/galleries');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'title',
                        'description',
                        'company',
                        'period',
                        'thumbnail',
                        'award_id',
                        'is_active',
                        'sort_order',
                    ]
                ]
            ]);
    }

    /** @test */
    public function it_can_filter_galleries_by_award()
    {
        $award1 = Award::factory()->create();
        $award2 = Award::factory()->create();

        Gallery::factory()->count(3)->create(['award_id' => $award1->id]);
        Gallery::factory()->count(2)->create(['award_id' => $award2->id]);

        $response = $this->getJson("/api/galleries?award_id={$award1->id}");

        $response->assertOk();
        $this->assertCount(3, $response->json('data.data'));
    }

    /** @test */
    public function it_can_show_a_gallery_with_items()
    {
        $gallery = Gallery::factory()->create();
        $items = GalleryItem::factory()->count(5)->create([
            'gallery_id' => $gallery->id
        ]);

        $response = $this->getJson("/api/galleries/{$gallery->id}");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'title',
                    'items' => [
                        '*' => [
                            'id',
                            'gallery_id',
                            'type',
                            'file_path',
                            'title',
                            'description',
                            'sequence',
                        ]
                    ]
                ]
            ]);
    }

    /** @test */
    public function authenticated_user_can_create_gallery()
    {
        $award = Award::factory()->create();

        $galleryData = [
            'title' => 'My Gallery',
            'description' => 'Gallery description',
            'company' => 'ACME Corp',
            'period' => '2024',
            'award_id' => $award->id,
            'is_active' => true,
            'sort_order' => 1,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/admin/galleries', $galleryData);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Gallery created successfully',
            ]);

        $this->assertDatabaseHas('galleries', [
            'title' => 'My Gallery',
            'company' => 'ACME Corp',
            'award_id' => $award->id,
        ]);
    }

    /** @test */
    public function authenticated_user_can_update_gallery()
    {
        $gallery = Gallery::factory()->create();

        $updateData = [
            'title' => 'Updated Gallery',
            'company' => 'New Company',
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/admin/galleries/{$gallery->id}", $updateData);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Gallery updated successfully',
            ]);

        $this->assertDatabaseHas('galleries', [
            'id' => $gallery->id,
            'title' => 'Updated Gallery',
            'company' => 'New Company',
        ]);
    }

    /** @test */
    public function deleting_gallery_cascades_to_items()
    {
        $gallery = Gallery::factory()->create();
        $items = GalleryItem::factory()->count(5)->create([
            'gallery_id' => $gallery->id
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/admin/galleries/{$gallery->id}")
            ->assertOk();

        $this->assertDatabaseMissing('galleries', ['id' => $gallery->id]);
        $this->assertDatabaseMissing('gallery_items', ['gallery_id' => $gallery->id]);
    }

    /** @test */
    public function it_can_list_gallery_items()
    {
        $gallery = Gallery::factory()->create();
        GalleryItem::factory()->count(10)->create([
            'gallery_id' => $gallery->id
        ]);

        $response = $this->getJson("/api/galleries/{$gallery->id}/items");

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'id',
                        'gallery_id',
                        'type',
                        'file_path',
                        'sequence',
                    ]
                ]
            ]);
    }

    /** @test */
    public function authenticated_user_can_add_gallery_item()
    {
        $gallery = Gallery::factory()->create();
        $file = UploadedFile::fake()->image('photo.jpg');

        $itemData = [
            'file' => $file,
            'title' => 'My Photo',
            'description' => 'Photo description',
            'type' => 'image',
            'sequence' => 1,
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/admin/galleries/{$gallery->id}/items", $itemData);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => 'Gallery item added successfully',
            ]);

        $this->assertDatabaseHas('gallery_items', [
            'gallery_id' => $gallery->id,
            'title' => 'My Photo',
            'type' => 'image',
        ]);
    }

    /** @test */
    public function authenticated_user_can_bulk_upload_gallery_items()
    {
        $gallery = Gallery::factory()->create();
        $files = [
            UploadedFile::fake()->image('photo1.jpg'),
            UploadedFile::fake()->image('photo2.jpg'),
            UploadedFile::fake()->image('photo3.jpg'),
        ];

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/admin/galleries/{$gallery->id}/items/bulk-upload", [
                'files' => $files,
                'titles' => ['Photo 1', 'Photo 2', 'Photo 3'],
                'descriptions' => ['Desc 1', 'Desc 2', 'Desc 3'],
            ]);

        $response->assertCreated()
            ->assertJson([
                'success' => true,
                'message' => '3 items uploaded successfully',
            ]);

        $this->assertEquals(3, $gallery->items()->count());
    }

    /** @test */
    public function authenticated_user_can_delete_gallery_item()
    {
        $gallery = Gallery::factory()->create();
        $item = GalleryItem::factory()->create(['gallery_id' => $gallery->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/admin/galleries/{$gallery->id}/items/{$item->id}");

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Gallery item deleted successfully',
            ]);

        $this->assertDatabaseMissing('gallery_items', ['id' => $item->id]);
    }

    /** @test */
    public function gallery_can_belong_to_award()
    {
        $award = Award::factory()->create();
        $gallery = Gallery::factory()->create(['award_id' => $award->id]);

        $this->assertInstanceOf(Award::class, $gallery->award);
        $this->assertEquals($award->id, $gallery->award->id);
    }

    /** @test */
    public function award_can_have_many_galleries()
    {
        $award = Award::factory()->create();
        $galleries = Gallery::factory()->count(3)->create(['award_id' => $award->id]);

        $this->assertEquals(3, $award->galleries()->count());
    }

    /** @test */
    public function gallery_has_many_items()
    {
        $gallery = Gallery::factory()->create();
        $items = GalleryItem::factory()->count(5)->create(['gallery_id' => $gallery->id]);

        $this->assertEquals(5, $gallery->items()->count());
    }

    /** @test */
    public function it_validates_bulk_upload_max_files()
    {
        $gallery = Gallery::factory()->create();
        $files = [];
        for ($i = 0; $i < 25; $i++) {
            $files[] = UploadedFile::fake()->image("photo{$i}.jpg");
        }

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/admin/galleries/{$gallery->id}/items/bulk-upload", [
                'files' => $files,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['files']);
    }

    /** @test */
    public function gallery_items_are_ordered_by_sequence()
    {
        $gallery = Gallery::factory()->create();
        GalleryItem::factory()->create(['gallery_id' => $gallery->id, 'sequence' => 3]);
        GalleryItem::factory()->create(['gallery_id' => $gallery->id, 'sequence' => 1]);
        GalleryItem::factory()->create(['gallery_id' => $gallery->id, 'sequence' => 2]);

        $items = $gallery->items;

        $this->assertEquals(1, $items[0]->sequence);
        $this->assertEquals(2, $items[1]->sequence);
        $this->assertEquals(3, $items[2]->sequence);
    }
}
