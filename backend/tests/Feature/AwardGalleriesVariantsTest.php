<?php

namespace Tests\Feature;

use App\Models\Award;
use App\Models\Gallery;
use App\Models\GalleryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AwardGalleriesVariantsTest extends TestCase
{
    use RefreshDatabase;

    public function test_award_galleries_items_expose_image_variants(): void
    {
        $award = Award::factory()->create(['title' => 'Test Award', 'organization' => 'Org', 'received_at' => '2026-01-01', 'is_active' => true]);
        $gallery = Gallery::factory()->create(['award_id' => $award->id, 'is_active' => true]);
        $variants = ['320w' => '/storage/v/a-320.webp', '1920w' => '/storage/v/a-1920.webp'];
        GalleryItem::factory()->create(['gallery_id' => $gallery->id, 'image_variants' => $variants]);
        GalleryItem::factory()->create(['gallery_id' => $gallery->id, 'image_variants' => null]);

        $items = $this->getJson("/api/awards/{$award->id}/galleries")
            ->assertOk()
            ->json('data.galleries.0.items');

        $this->assertCount(2, $items);
        $byVariants = collect($items)->pluck('image_variants');
        $this->assertContains($variants, $byVariants->all());
        $this->assertContains(null, $byVariants->all());
        $this->assertArrayHasKey('image_variants', $items[0]);
    }
}
