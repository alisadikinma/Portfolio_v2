<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Newsletter>
 */
class NewsletterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'email' => $this->faker->unique()->safeEmail(),
            'name' => $this->faker->name(),
            'whatsapp_number' => '+62' . $this->faker->unique()->numerify('8##########'),
            'consent_given_at' => now(),
            'source' => $this->faker->randomElement([
                'blog_inline',
                'inline_card',
                'floating_banner',
                'footer_bar',
            ]),
            'is_subscribed' => true,
            'subscribed_at' => now(),
        ];
    }
}
