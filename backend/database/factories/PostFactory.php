<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Post>
 */
class PostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * `posts.category_id` is a NOT NULL FK with no default — leaving it unset
     * made every `Post::factory()` (incl. the LinkedInPostFactory fallback)
     * throw "NOT NULL constraint failed: posts.category_id" on the sqlite CI
     * DB. Provide a category + slug so a bare `Post::factory()->create()` is
     * valid; callers still override either field freely.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'slug' => 'post-' . Str::random(8) . '-' . $this->faker->unique()->numberBetween(1, 999999),
        ];
    }
}
