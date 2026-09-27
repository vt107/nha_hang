<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<MenuItem>
 */
class MenuItemFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(3, true);

        return [
            'category_id' => Category::factory(),
            'name' => Str::ucfirst($name),
            'slug' => Str::slug($name),
            'price' => fake()->numberBetween(2, 50) * 5000,
            'is_available' => true,
            'is_active' => true,
            'is_featured' => false,
            'sort_order' => 0,
        ];
    }

    public function soldOut(): static
    {
        return $this->state(fn (array $attributes) => ['is_available' => false]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
