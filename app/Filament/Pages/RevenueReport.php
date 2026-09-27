<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\PaymentMethodChart;
use App\Filament\Widgets\RevenueChart;
use App\Filament\Widgets\RevenueStats;
use App\Filament\Widgets\TopItemsTable;
use App\Services\Reports\DateRange;
use BackedEnum;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class RevenueReport extends BaseDashboard
{
    use HasFiltersForm;

    protected static string $routePath = 'revenue';

    protected static ?string $title = 'Doanh thu';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Báo cáo';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return 'Doanh thu';
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(3)
                ->columnSpanFull()
                ->schema([
                    Select::make('preset')
                        ->label('Khoảng thời gian')
                        ->options(DateRange::PRESETS)
                        ->default('last_7_days')
                        ->selectablePlaceholder(false)
                        ->live(),
                    DatePicker::make('from')
                        ->label('Từ ngày')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->maxDate(today())
                        ->visible(fn (Get $get) => $get('preset') === 'custom'),
                    DatePicker::make('to')
                        ->label('Đến ngày')
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->maxDate(today())
                        ->visible(fn (Get $get) => $get('preset') === 'custom'),
                ]),
        ]);
    }

    public function getWidgets(): array
    {
        return [
            RevenueStats::class,
            RevenueChart::class,
            TopItemsTable::class,
            PaymentMethodChart::class,
        ];
    }
}
