<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentMethod;
use App\Services\Reports\DateRange;
use App\Services\Reports\RevenueReport;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class PaymentMethodChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Hình thức thanh toán';

    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $byMethod = app(RevenueReport::class)->summary(DateRange::fromFilters($this->pageFilters))['by_method'];

        return [
            'datasets' => [[
                'data' => array_values($byMethod),
                'backgroundColor' => ['rgb(16, 185, 129)', 'rgb(14, 165, 233)'],
            ]],
            'labels' => array_map(fn (string $method) => PaymentMethod::from($method)->getLabel(), array_keys($byMethod)),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
