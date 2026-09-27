<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\OperationsOverview;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\TopItemsTable;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Tổng quan';

    public static function getNavigationLabel(): string
    {
        return 'Tổng quan';
    }

    public function getWidgets(): array
    {
        return [
            OperationsOverview::class,
            RevenueChart::class,
            TopItemsTable::class,
        ];
    }
}
