<?php

namespace Database\Factories;

use App\Models\PostizChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostizChannel>
 */
class PostizChannelFactory extends Factory
{
    protected $model = PostizChannel::class;

    public function definition(): array
    {
        return [
            'platform' => 'instagram',
            'handle' => $this->faker->unique()->userName(),
            'postiz_integration_id' => (string) $this->faker->numberBetween(1, 99),
            'enabled' => true,
            'last_synced_at' => now(),
        ];
    }
}
