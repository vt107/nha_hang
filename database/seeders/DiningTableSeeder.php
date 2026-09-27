<?php

namespace Database\Seeders;

use App\Models\Area;
use Illuminate\Database\Seeder;

class DiningTableSeeder extends Seeder
{
    public function run(): void
    {
        $layout = [
            ['name' => 'Tầng 1', 'prefix' => 'A', 'count' => 8, 'capacity' => 4],
            ['name' => 'Tầng 2', 'prefix' => 'B', 'count' => 6, 'capacity' => 6],
            ['name' => 'Sân vườn', 'prefix' => 'S', 'count' => 4, 'capacity' => 8],
        ];

        foreach ($layout as $order => $row) {
            $area = Area::firstOrCreate(['name' => $row['name']], ['sort_order' => $order]);

            for ($i = 1; $i <= $row['count']; $i++) {
                $area->diningTables()->firstOrCreate(
                    ['code' => sprintf('%s%02d', $row['prefix'], $i)],
                    ['capacity' => $row['capacity'], 'sort_order' => $i],
                );
            }
        }
    }
}
