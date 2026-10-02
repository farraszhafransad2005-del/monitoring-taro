<?php

namespace Database\Factories;

use App\Models\Machine;
use App\Models\OeeReading;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OeeReading>
 */
class OeeReadingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $availability = fake()->randomFloat(2, 80, 100);
        $performance = fake()->randomFloat(2, 60, 100);
        $quality = fake()->randomFloat(2, 97, 100);

        return [
            'machine_id' => Machine::factory(),
            'recorded_at' => now(),
            'availability' => $availability,
            'performance' => $performance,
            'quality' => $quality,
            'oee' => round($availability * $performance * $quality / 10000, 2),
            'speed_ppm' => fake()->numberBetween(60, 90),
            'source' => 'api',
        ];
    }
}
