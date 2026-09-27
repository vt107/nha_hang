<?php

namespace Database\Factories;

use App\Models\Area;
use App\Models\DiningTable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DiningTable>
 */
class DiningTableFactory extends Factory
{
    public function definition(): array
    {
        return [
            'area_id' => Area::factory(),
            'code' => 'B'.fake()->unique()->numberBetween(100, 9999),
            'capacity' => fake()->randomElement([2, 4, 6, 8]),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
