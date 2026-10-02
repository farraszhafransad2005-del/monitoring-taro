<?php

namespace Database\Factories;

use App\Models\Machine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Machine>
 */
class MachineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = Machine::normalizeCode(fake()->unique()->numberBetween(1, 99));

        return [
            'code' => $code,
            'name' => "Mesin {$code}",
            'type' => fake()->randomElement(['Kawashima', 'Volumetric']),
            'production_house' => fake()->randomElement(['PH1', 'PH2']),
        ];
    }
}
