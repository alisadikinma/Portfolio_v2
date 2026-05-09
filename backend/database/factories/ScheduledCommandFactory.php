<?php

namespace Database\Factories;

use App\Models\ScheduledCommand;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ScheduledCommand>
 */
class ScheduledCommandFactory extends Factory
{
    protected $model = ScheduledCommand::class;

    public function definition(): array
    {
        $slug = Str::lower($this->faker->unique()->lexify('????????'));

        return [
            'signature' => $slug . ':test',
            'display_name' => $this->faker->sentence(3),
            'description' => $this->faker->optional(0.6)->paragraph(),
            'category' => 'system',
            'cron_expression' => '0 0 * * *',
            'timezone' => 'Asia/Jakarta',
            'enabled' => true,
            'is_placeholder' => false,
            'without_overlapping_minutes' => null,
            'run_in_background' => false,
            'arguments' => null,
            'last_run_at' => null,
            'last_finished_at' => null,
            'last_status' => 'never',
            'last_duration_ms' => null,
            'last_error' => null,
            'sort_order' => 0,
        ];
    }
}
