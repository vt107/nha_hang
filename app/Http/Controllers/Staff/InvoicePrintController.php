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
        $invoice->load(['payments', 'cashier', 'tableSession.diningTable', 'items']);

        $lines = $invoice->items
            ->sortBy('id')
            ->groupBy(fn (OrderItem $item) => $item->item_name.'|'.$item->unit_price.'|'.json_encode($item->options))
            ->map(fn ($items) => [
                'name' => $items->first()->display_name,
                'unit_price' => $items->first()->unit_price,
                'quantity' => $items->sum('quantity'),
                'amount' => $items->sum(fn (OrderItem $item) => $item->line_total),
            ])
            ->values();

        return view('staff.invoice-print', ['invoice' => $invoice, 'lines' => $lines]);
    }
}
