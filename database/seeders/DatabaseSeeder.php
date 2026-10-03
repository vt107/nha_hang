<?php

namespace Database\Seeders;

use App\Support\Demo\DemoMode;
use Database\Seeders\Demo\DemoSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            SettingSeeder::class,
            UserSeeder::class,
            DiningTableSeeder::class,
            MenuSeeder::class,
            OptionSeeder::class,
        ]);

        // Bản demo chỉ xem: thêm dữ liệu mẫu đầy đủ (lịch sử 90 ngày, bàn đang phục vụ, đặt bàn...).
        if (DemoMode::enabled()) {
            $this->call(DemoSeeder::class);
        }
    }
}
