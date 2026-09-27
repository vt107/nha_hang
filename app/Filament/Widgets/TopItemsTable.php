<?php

namespace App\Filament\Widgets;

use App\Services\Reports\DateRange;
use App\Services\Reports\RevenueReport;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\TableWidget;

class TopItemsTable extends TableWidget
{
    use InteractsWithPageFilters;

    public function table(Table $table): Table
    {
        return $table
            ->heading('Món bán chạy')
            ->records(fn () => app(RevenueReport::class)
                ->topItems(DateRange::fromFilters($this->pageFilters))
                ->values()
                ->mapWithKeys(fn (array $row, int $index) => [$index + 1 => [...$row, 'rank' => $index + 1]])
                ->all())
            ->paginated(false)
            ->emptyStateHeading('Chưa có món nào được bán')
            ->columns([
                TextColumn::make('rank')->label('#')->width('3rem'),
                TextColumn::make('name')->label('Món')->weight('medium'),
                TextColumn::make('quantity')->label('Số phần')->alignEnd(),
                TextColumn::make('amount')->label('Doanh số')->money('VND', locale: 'vi')->alignEnd(),
            ]);
    }
}
