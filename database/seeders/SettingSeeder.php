<?php

namespace Database\Seeders;

use App\Enums\OrderConfirmMode;
use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'restaurant.name' => 'Nhà Hàng Demo',
            'restaurant.phone' => '0900 000 000',
            'restaurant.address' => '123 Đường ABC, Quận 1, TP.HCM',
            'restaurant.opening_hours' => '10:00 - 22:00',

            'order.confirm_mode' => OrderConfirmMode::FirstOrder->value,
            'order.max_quantity_per_item' => 20,

            'billing.vat_percent' => 0,
            'billing.service_charge_percent' => 0,

            // Hiển thị VietQR tĩnh cho khách chuyển khoản; nhân viên kiểm tra và xác nhận tay.
            'bank.bin' => '970436',
            'bank.account_number' => '0000000000',
            'bank.account_name' => 'NHA HANG DEMO',

            'kitchen.warn_after_minutes' => 15,

            'reservation.max_party_size' => 20,
            'reservation.default_duration_minutes' => 120,
            'reservation.min_lead_minutes' => 60,
        ];

        foreach ($defaults as $name => $value) {
            [$group, $key] = explode('.', $name, 2);

            Setting::firstOrCreate(['group' => $group, 'key' => $key], ['value' => $value]);
        }
    }
}
