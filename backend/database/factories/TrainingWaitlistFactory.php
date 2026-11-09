<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\TrainingWaitlist>
 */
class TrainingWaitlistFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'company' => $this->faker->company(),
            'email' => $this->faker->unique()->safeEmail(),
            'whatsapp_number' => null,
            'interest' => $this->faker->randomElement(['inhouse', 'workshop', 'public_class']),
            'note' => null,
            'source_page' => '/id/pelatihan-ai',
            'locale' => 'id',
            'newsletter_opt_in' => false,
            'client_key' => $this->faker->uuid(),
        ];
    }
}
