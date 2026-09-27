<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\OptionGroup;
use Illuminate\Database\Seeder;

class OptionSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            [
                'group' => ['name' => 'Size', 'internal_name' => 'Size - đồ uống', 'min_select' => 1, 'max_select' => 1],
                'options' => [['Vừa (M)', 0, true], ['Lớn (L)', 10000, false]],
                'items' => ['Cà phê sữa đá', 'Nước cam ép'],
            ],
            [
                'group' => ['name' => 'Thêm', 'internal_name' => 'Topping phở', 'min_select' => 0, 'max_select' => 3],
                'options' => [['Trứng trần', 10000, false], ['Thêm bánh phở', 10000, false], ['Thêm thịt bò', 25000, false]],
                'items' => ['Phở bò tái nạm'],
            ],
            [
                'group' => ['name' => 'Độ cay', 'internal_name' => 'Độ cay - lẩu', 'min_select' => 1, 'max_select' => 1],
                'options' => [['Không cay', 0, false], ['Cay vừa', 0, true], ['Rất cay', 0, false]],
                'items' => ['Lẩu thái hải sản'],
            ],
        ];

        foreach ($groups as $order => $row) {
            $group = OptionGroup::firstOrCreate(['internal_name' => $row['group']['internal_name']], [...$row['group'], 'sort_order' => $order]);

            foreach ($row['options'] as $index => [$name, $price, $default]) {
                $group->options()->firstOrCreate(['name' => $name], ['price_delta' => $price, 'is_default' => $default, 'sort_order' => $index]);
            }

            $group->menuItems()->syncWithoutDetaching(MenuItem::query()->whereIn('name', $row['items'])->pluck('id'));
        }
    }
}
