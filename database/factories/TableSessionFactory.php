<?php

namespace Database\Factories;

use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use App\Models\DiningTable;
use App\Models\TableSession;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TableSession>
 */
class TableSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'dining_table_id' => DiningTable::factory(),
            'code' => 'S'.fake()->unique()->numerify('########'),
            'token' => Str::random(32),
            'status' => TableSessionStatus::Open,
            'source' => TableSessionSource::Qr,
            'opened_at' => now(),
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => TableSessionStatus::Closed,
            'closed_at' => now(),
        ]);
    }
}
