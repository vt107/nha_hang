<?php

namespace Database\Seeders\Demo;

use Illuminate\Database\Seeder;

/**
 * Dữ liệu cho bản demo chỉ xem (DEMO_MODE=true, gọi từ DatabaseSeeder). Ngày giờ tương đối so với lúc seed
 * vì `php artisan demo:reset` dựng lại database mỗi ngày.
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            DemoCatalogSeeder::class,
            DemoHistorySeeder::class,
            DemoReservationSeeder::class,
            DemoLiveSeeder::class,
        ]);
    }
}
