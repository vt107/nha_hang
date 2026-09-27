<?php

namespace Database\Factories;

use App\Enums\ReservationSource;
use App\Enums\ReservationStatus;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => 'R'.fake()->unique()->numerify('########'),
            'customer_name' => fake()->name(),
            'customer_phone' => '09'.fake()->numerify('########'),
            'party_size' => fake()->numberBetween(2, 10),
            'reserved_at' => now()->addDay()->setTime(19, 0),
            'duration_minutes' => 120,
            'status' => ReservationStatus::Pending,
            'source' => ReservationSource::Web,
        ];
    }
}
