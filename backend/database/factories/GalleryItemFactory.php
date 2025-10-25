<?php

namespace Database\Factories;

use App\Models\Gallery;
use App\Models\GalleryItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GalleryItem>
 */
class GalleryItemFactory extends Factory
{
    protected $model = GalleryItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'gallery_id' => Gallery::factory(),
            'type' => 'image',
            'file_path' => 'gallery_items/' . fake()->uuid() . '.jpg',
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'sequence' => fake()->numberBetween(1, 100),
        ];
    }

    /**
     * Indicate that the item is an image.
     */
    public function image(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'image',
            'file_path' => 'gallery_items/' . fake()->uuid() . '.jpg',
        ]);
    }

    /**
     * Indicate that the item is a video.
     */
    public function video(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'video',
            'file_path' => 'gallery_items/' . fake()->uuid() . '.mp4',
        ]);
    }

    /**
     * Set a specific gallery.
     */
    public function forGallery(Gallery $gallery): static
    {
        return $this->state(fn (array $attributes) => [
            'gallery_id' => $gallery->id,
        ]);
    }

    /**
     * Set a specific sequence.
     */
    public function sequence(int $sequence): static
    {
        return $this->state(fn (array $attributes) => [
            'sequence' => $sequence,
        ]);
    }
}
