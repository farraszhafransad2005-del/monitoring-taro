<?php

namespace Database\Factories;

use App\Models\Machine;
use App\Models\ProductionRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductionRecord>
 */
class ProductionRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'machine_id' => Machine::factory(),
            'production_date' => fake()->dateTimeBetween('-30 days')->format('Y-m-d'),
            'shift' => fake()->numberBetween(1, 3),
            'shift_type' => 'Full',
            'break_type' => 'Bergantian',
            'crew_group' => (string) fake()->numberBetween(1, 3),
            'line' => (string) fake()->numberBetween(1, 5),
            'part_no' => 'A00'.fake()->numberBetween(100, 999),
            'item' => 'Taro Net '.fake()->randomElement(['Seaweed', 'Potato BBQ', 'Cowboy Steak']).' 60 pack x 8 gr',
            'speed_ppm' => 80,
            'pcs_per_carton' => 60,
            'kg_per_carton' => 0.48,
            'planned_minutes' => 450,
            'unavailable_minutes' => 0,
            'downtime_minutes' => 0,
            'target_cartons' => 576,
            'actual_cartons' => fake()->numberBetween(300, 576),
            'actual_kg' => fn (array $attributes) => $attributes['actual_cartons'] * 0.48,
            'reject_total_pcs' => fake()->numberBetween(0, 200),
        ];
    }
}
