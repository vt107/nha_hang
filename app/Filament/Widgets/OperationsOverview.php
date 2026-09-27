<?php

namespace App\Filament\Widgets;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Models\DiningTable;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\TableSession;
use App\Services\Reports\DateRange;
use App\Services\Reports\RevenueReport;
use App\Support\Money;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Tình hình trong ngày trên trang Tổng quan.
 */
class OperationsOverview extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $report = app(RevenueReport::class);
        $today = $report->summary(new DateRange(today(), today()->endOfDay()));
        $yesterday = $report->summary(new DateRange(today()->subDay(), today()->subDay()->endOfDay()));
        $diff = $today['revenue'] - $yesterday['revenue'];

        $occupied = TableSession::query()->open()->count();
        $tables = DiningTable::query()->active()->count();

        return [
            Stat::make('Doanh thu hôm nay', Money::format($today['revenue']))
                ->description(($diff >= 0 ? '+' : '-').Money::format(abs($diff)).' so với hôm qua · '.$today['invoices'].' hóa đơn')
                ->descriptionIcon($diff >= 0 ? Heroicon::ArrowTrendingUp : Heroicon::ArrowTrendingDown)
                ->color($diff >= 0 ? 'success' : 'danger')
                ->chart(array_values($report->byDay(new DateRange(today()->subDays(6), today()->endOfDay())))),
            Stat::make('Bàn đang phục vụ', "{$occupied} / {$tables}")
                ->description($tables ? round($occupied / $tables * 100).'% công suất' : null)
                ->icon(Heroicon::OutlinedSquares2x2),
            Stat::make('Order chờ duyệt', Order::query()->where('status', OrderStatus::Pending)->count())
                ->icon(Heroicon::OutlinedBellAlert)
                ->color('warning'),
            Stat::make('Món trong bếp', (int) OrderItem::query()->whereIn('status', [OrderItemStatus::Queued, OrderItemStatus::Cooking])->sum('quantity'))
                ->icon(Heroicon::OutlinedFire),
            Stat::make('Đặt bàn hôm nay', Reservation::query()
                ->whereIn('status', [...ReservationStatus::upcoming(), ReservationStatus::Seated])
                ->whereDate('reserved_at', today())
                ->count())
                ->icon(Heroicon::OutlinedCalendarDays),
        ];
    }
}
