<?php

namespace App\Services\Reports;

use App\Enums\InvoiceStatus;
use App\Enums\OrderItemStatus;
use App\Enums\PaymentMethod;
use App\Models\Invoice;
use App\Models\OrderItem;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Doanh thu = tổng invoices.total có status paid, tính theo paid_at.
 */
class RevenueReport
{
    /**
     * @return array{revenue: int, invoices: int, average: int, gross: int, discount: int, guests: int, by_method: array<string, int>}
     */
    public function summary(DateRange $range): array
    {
        $row = $this->paidInvoices($range)
            ->selectRaw('COUNT(*) as invoices, COALESCE(SUM(total), 0) as revenue, COALESCE(SUM(subtotal), 0) as gross, COALESCE(SUM(discount_amount), 0) as discount')
            ->first();

        $guests = (int) $this->paidInvoices($range)
            ->join('table_sessions', 'table_sessions.id', '=', 'invoices.table_session_id')
            ->sum('table_sessions.guest_count');

        $byMethod = Payment::query()
            ->join('invoices', 'invoices.id', '=', 'payments.invoice_id')
            ->where('invoices.status', InvoiceStatus::Paid)
            ->whereBetween('invoices.paid_at', [$range->from, $range->to])
            ->groupBy('payments.method')
            ->selectRaw('payments.method, SUM(payments.amount) as amount')
            ->pluck('amount', 'method');

        $invoices = (int) $row->invoices;
        $revenue = (int) $row->revenue;

        return [
            'revenue' => $revenue,
            'invoices' => $invoices,
            'average' => $invoices ? intdiv($revenue, $invoices) : 0,
            'gross' => (int) $row->gross,
            'discount' => (int) $row->discount,
            'guests' => $guests,
            'by_method' => collect(PaymentMethod::cases())
                ->mapWithKeys(fn (PaymentMethod $method) => [$method->value => (int) ($byMethod[$method->value] ?? 0)])
                ->all(),
        ];
    }

    /**
     * Doanh thu theo ngày (đủ mọi ngày trong khoảng, ngày không bán = 0).
     *
     * @return array<string, int> 'Y-m-d' => doanh thu
     */
    public function byDay(DateRange $range): array
    {
        $rows = $this->paidInvoices($range)
            ->selectRaw('DATE(paid_at) as day, SUM(total) as revenue')
            ->groupBy('day')
            ->pluck('revenue', 'day');

        $result = [];

        for ($day = $range->from->copy()->startOfDay(); $day->lte($range->to); $day->addDay()) {
            $result[$day->toDateString()] = (int) ($rows[$day->toDateString()] ?? 0);
        }

        return $result;
    }

    /**
     * @return array<int, int> giờ (0-23) => doanh thu
     */
    public function byHour(DateRange $range): array
    {
        $rows = $this->paidInvoices($range)
            ->selectRaw('HOUR(paid_at) as hour, SUM(total) as revenue')
            ->groupBy('hour')
            ->pluck('revenue', 'hour');

        return collect(range(0, 23))->mapWithKeys(fn (int $hour) => [$hour => (int) ($rows[$hour] ?? 0)])->all();
    }

    /**
     * Món bán chạy (theo số phần) trong các hóa đơn đã thanh toán (theo món, không tách theo size / topping).
     *
     * @return Collection<int, array{name: string, quantity: int, amount: int}>
     */
    public function topItems(DateRange $range, int $limit = 10): Collection
    {
        return OrderItem::query()
            ->join('invoices', 'invoices.id', '=', 'order_items.invoice_id')
            ->where('invoices.status', InvoiceStatus::Paid)
            ->whereBetween('invoices.paid_at', [$range->from, $range->to])
            ->whereIn('order_items.status', OrderItemStatus::billable())
            ->groupBy('order_items.item_name')
            ->selectRaw('order_items.item_name as name, SUM(order_items.quantity) as quantity, SUM(order_items.quantity * order_items.unit_price) as amount')
            ->orderByDesc('quantity')
            ->orderByDesc('amount')
            ->limit($limit)
            ->toBase()
            ->get()
            ->map(fn ($row) => ['name' => $row->name, 'quantity' => (int) $row->quantity, 'amount' => (int) $row->amount]);
    }

    /**
     * @return Builder<Invoice>
     */
    private function paidInvoices(DateRange $range)
    {
        return Invoice::query()
            ->where('invoices.status', InvoiceStatus::Paid)
            ->whereBetween('invoices.paid_at', [$range->from, $range->to]);
    }
}
