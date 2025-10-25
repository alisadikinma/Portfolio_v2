<?php

namespace Database\Factories;

use App\Models\Award;
use App\Models\Gallery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Gallery>
 */
class GalleryFactory extends Factory
{
    protected $model = Gallery::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'company' => fake()->company(),
            'period' => fake()->year(),
            'thumbnail' => null,
            'award_id' => null,
            'is_active' => fake()->boolean(80),
            'sort_order' => fake()->numberBetween(1, 100),
        ];
    }

    /**
     * Indicate that the gallery is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the gallery is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the gallery belongs to an award.
     */
    public function forAward(?Award $award = null): static
    {
        return $this->state(fn (array $attributes) => [
            'award_id' => $award?->id ?? Award::factory(),
        ]);
    }
}
