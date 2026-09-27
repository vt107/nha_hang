<?php

namespace Database\Factories;

use App\Models\OptionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OptionGroup>
 */
class OptionGroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Size',
            'min_select' => 1,
            'max_select' => 1,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    /** Nhóm không bắt buộc, chọn tối đa $max. */
    public function optional(int $max = 3): static
    {
        return $this->state(fn (array $attributes) => ['name' => 'Topping', 'min_select' => 0, 'max_select' => $max]);
    }
}
