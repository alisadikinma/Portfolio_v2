<?php

namespace Database\Factories;

use App\Models\InstagramPost;
use App\Models\PostizPublishJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PostizPublishJob>
 */
class PostizPublishJobFactory extends Factory
{
    protected $model = PostizPublishJob::class;

    public function definition(): array
    {
        return [
            'platform' => 'instagram',
            'sibling_post_id' => $this->faker->unique()->numberBetween(1, 1_000_000),
            'sibling_type' => InstagramPost::class,
            'status' => PostizPublishJob::STATUS_READY,
            'claimed_by' => null,
            'claimed_at' => null,
            'publish_lease_until' => null,
            'slot_due_at' => now(),
            'postiz_integration_id' => (string) $this->faker->numberBetween(1, 99),
            'postiz_post_id' => null,
            'permalink' => null,
            'last_error' => null,
            'fallback_fired_at' => null,
        ];
    }

    public function claimed(): static
    {
        return $this->state(fn () => [
            'status' => PostizPublishJob::STATUS_CLAIMED,
            'claimed_by' => 'local-1',
            'claimed_at' => now(),
            'publish_lease_until' => now()->addMinutes(10),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => [
            'status' => PostizPublishJob::STATUS_ACCEPTED,
            'postiz_post_id' => (string) $this->faker->uuid(),
            'publish_lease_until' => now()->addMinutes(10),
        ]);
    }
}
