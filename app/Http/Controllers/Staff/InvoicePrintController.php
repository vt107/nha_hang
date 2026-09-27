<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\OrderItem;
use Illuminate\View\View;

class InvoicePrintController extends Controller
{
    public function __invoke(Invoice $invoice): View
    {
        $invoice->load(['payments', 'cashier', 'tableSession.diningTable']);

        $lines = $invoice->tableSession->orderItems()
            ->billable()
            ->orderBy('order_items.id')
            ->get()
            ->groupBy(fn (OrderItem $item) => $item->item_name.'|'.$item->unit_price)
            ->map(fn ($items) => [
                'name' => $items->first()->item_name,
                'unit_price' => $items->first()->unit_price,
                'quantity' => $items->sum('quantity'),
                'amount' => $items->sum(fn (OrderItem $item) => $item->line_total),
            ])
            ->values();

        return view('staff.invoice-print', ['invoice' => $invoice, 'lines' => $lines]);
    }
}
