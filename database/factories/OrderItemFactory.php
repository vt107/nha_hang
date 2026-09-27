<?php

namespace Database\Factories;

use App\Enums\OrderItemStatus;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrderItem>
 */
class OrderItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'menu_item_id' => MenuItem::factory(),
            'item_name' => fn (array $attributes) => MenuItem::find($attributes['menu_item_id'])->name,
            'unit_price' => fn (array $attributes) => MenuItem::find($attributes['menu_item_id'])->price,
            'quantity' => fake()->numberBetween(1, 3),
            'status' => OrderItemStatus::Pending,
        ];
    }

    public function status(OrderItemStatus $status): static
    {
        $column = $status->timestampColumn();

        return $this->state(fn (array $attributes) => ['status' => $status] + ($column ? [$column => now()] : []));
    }
}
