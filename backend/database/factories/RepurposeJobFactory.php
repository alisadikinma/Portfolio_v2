<?php

namespace Database\Factories;

use App\Models\RepurposeJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RepurposeJob>
 */
class RepurposeJobFactory extends Factory
{
    protected $model = RepurposeJob::class;

    public function definition(): array
    {
        return [
            'source_url' => 'https://instagram.com/p/' . $this->faker->lexify('???????') . '/',
            'angle' => null,
            'mode' => null,
            'status' => 'received',
            'chat_id' => '99',
        ];
    }
}
