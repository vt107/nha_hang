<?php

namespace App\Filament\Widgets;

use App\Services\Reports\DateRange;
use App\Services\Reports\RevenueReport;
use Filament\Support\RawJs;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Doanh thu theo ngày; nếu lọc 1 ngày thì theo giờ. Không có bộ lọc (trang Tổng quan) = 7 ngày qua.
 */
class RevenueChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $maxHeight = '300px';

    protected int|string|array $columnSpan = 'full';

    public function getHeading(): string
    {
        $range = DateRange::fromFilters($this->pageFilters);

        return $range->isSingleDay() ? 'Doanh thu theo giờ · '.$range->label() : 'Doanh thu theo ngày · '.$range->label();
    }

    protected function getData(): array
    {
        $range = DateRange::fromFilters($this->pageFilters);
        $report = app(RevenueReport::class);

        if ($range->isSingleDay()) {
            $data = array_slice($report->byHour($range), 6, 18, preserve_keys: true);
            $labels = array_map(fn (int $hour) => sprintf('%02dh', $hour), array_keys($data));
        } else {
            $data = $report->byDay($range);
            $labels = array_map(fn (string $day) => date('d/m', strtotime($day)), array_keys($data));
        }

        return [
            'datasets' => [[
                'label' => 'Doanh thu',
                'data' => array_values($data),
                'backgroundColor' => 'rgba(217, 119, 6, 0.7)',
                'borderColor' => 'rgb(217, 119, 6)',
                'borderRadius' => 4,
            ]],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): RawJs
    {
        return RawJs::make(<<<'JS'
            {
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (ctx) => ctx.parsed.y.toLocaleString('vi-VN') + ' ₫' } },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        suggestedMax: 100000,
                        ticks: {
                            precision: 0,
                            callback: (value) => value >= 1000000
                                ? (value / 1000000).toLocaleString('vi-VN') + 'tr'
                                : (value >= 1000 ? (value / 1000).toLocaleString('vi-VN') + 'k' : value),
                        },
                    },
                },
            }
        JS);
    }
}
