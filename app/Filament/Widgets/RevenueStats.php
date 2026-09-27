<?php

namespace App\Filament\Widgets;

use App\Enums\PaymentMethod;
use App\Services\Reports\DateRange;
use App\Services\Reports\RevenueReport;
use App\Support\Money;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueStats extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $range = DateRange::fromFilters($this->pageFilters);
        $summary = app(RevenueReport::class)->summary($range);

        return [
            Stat::make('Doanh thu', Money::format($summary['revenue']))
                ->description($range->label())
                ->color('success'),
            Stat::make('Số hóa đơn', number_format($summary['invoices'], 0, ',', '.'))
                ->description($summary['guests'] ? number_format($summary['guests'], 0, ',', '.').' lượt khách' : null),
            Stat::make('Trung bình / hóa đơn', Money::format($summary['average'])),
            Stat::make('Giảm giá', Money::format($summary['discount']))
                ->description('Tiền món trước giảm: '.Money::format($summary['gross'])),
            Stat::make(PaymentMethod::Cash->getLabel(), Money::format($summary['by_method'][PaymentMethod::Cash->value])),
            Stat::make(PaymentMethod::BankTransfer->getLabel(), Money::format($summary['by_method'][PaymentMethod::BankTransfer->value])),
        ];
    }
}
