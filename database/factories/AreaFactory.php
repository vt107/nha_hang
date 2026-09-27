<?php

namespace Database\Factories;

use App\Models\Area;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Area>
 */
class AreaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Tầng '.fake()->unique()->numberBetween(1, 99),
            'sort_order' => 0,
            'is_active' => true,
        ];
    }
}
