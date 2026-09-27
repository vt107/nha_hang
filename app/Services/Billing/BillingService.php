<?php

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Events\TableSessionUpdated;
use App\Exceptions\BusinessException;
use App\Models\Invoice;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Tables\TableSessionService;
use App\Support\Code;
use Illuminate\Support\Facades\DB;

class BillingService
{
    public function __construct(private TableSessionService $sessions) {}

    /**
     * Tạm tính: chỉ món billable (đã xác nhận, chưa hủy), gộp theo tên + đơn giá.
     * Phí phục vụ tính trên (tạm tính - giảm giá); VAT tính trên (tạm tính - giảm giá + phí phục vụ).
     */
    public function summarize(TableSession $session, int $discount = 0): BillSummary
    {
        $lines = $session->orderItems()
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
            ->values()
            ->all();

        $subtotal = array_sum(array_column($lines, 'amount'));
        $discount = max(0, min($discount, $subtotal));
        $serviceChargePercent = (int) Setting::get('billing.service_charge_percent', 0);
        $vatPercent = (int) Setting::get('billing.vat_percent', 0);

        $serviceCharge = (int) round(($subtotal - $discount) * $serviceChargePercent / 100);
        $vat = (int) round(($subtotal - $discount + $serviceCharge) * $vatPercent / 100);

        return new BillSummary(
            lines: $lines,
            subtotal: $subtotal,
            discount: $discount,
            serviceChargePercent: $serviceChargePercent,
            serviceCharge: $serviceCharge,
            vatPercent: $vatPercent,
            vat: $vat,
            total: $subtotal - $discount + $serviceCharge + $vat,
        );
    }

    /**
     * Nhân viên xác nhận đã nhận đủ tiền: tạo hóa đơn đã thanh toán, ghi khoản thu, đóng phiên bàn.
     */
    public function checkout(
        TableSession $session,
        User $cashier,
        PaymentMethod $method,
        int $discount = 0,
        ?int $receivedAmount = null,
        ?string $reference = null,
        ?string $note = null,
    ): Invoice {
        $invoice = DB::transaction(function () use ($session, $cashier, $method, $discount, $receivedAmount, $reference, $note) {
            $session = TableSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $session->isOpen()) {
                throw new BusinessException('Phiên bàn đã đóng.');
            }

            $unfinished = $session->orderItems()
                ->whereIn('order_items.status', [OrderItemStatus::Queued, OrderItemStatus::Cooking, OrderItemStatus::Ready])
                ->count();

            if ($unfinished > 0) {
                throw new BusinessException("Còn {$unfinished} món chưa phục vụ. Hãy đánh dấu đã phục vụ hoặc hủy trước khi thanh toán.");
            }

            $summary = $this->summarize($session, $discount);

            if ($summary->subtotal === 0) {
                throw new BusinessException('Bàn chưa có món nào để thanh toán. Dùng "Đóng bàn" nếu khách không dùng bữa.');
            }

            if ($method === PaymentMethod::Cash && $receivedAmount !== null && $receivedAmount < $summary->total) {
                throw new BusinessException('Tiền khách đưa chưa đủ.');
            }

            // Order khách gửi nhưng chưa ai duyệt: không làm, không tính tiền.
            $session->orders()->where('status', OrderStatus::Pending)->each(function ($order) use ($cashier) {
                $order->update(['status' => OrderStatus::Rejected, 'rejected_reason' => 'Đã thanh toán']);
                $order->items()->update([
                    'status' => OrderItemStatus::Cancelled,
                    'cancel_reason' => 'Đã thanh toán',
                    'cancelled_by' => $cashier->id,
                    'cancelled_at' => now(),
                ]);
            });

            $invoice = Invoice::create([
                'table_session_id' => $session->id,
                'code' => Code::make('HD'),
                'subtotal' => $summary->subtotal,
                'discount_amount' => $summary->discount,
                'service_charge_amount' => $summary->serviceCharge,
                'vat_amount' => $summary->vat,
                'total' => $summary->total,
                'status' => InvoiceStatus::Paid,
                'paid_at' => now(),
                'cashier_id' => $cashier->id,
                'note' => $note,
            ]);

            $invoice->payments()->create([
                'method' => $method,
                'amount' => $summary->total,
                'received_amount' => $method === PaymentMethod::Cash ? ($receivedAmount ?? $summary->total) : null,
                'reference' => $reference,
                'confirmed_by' => $cashier->id,
                'confirmed_at' => now(),
            ]);

            $this->sessions->markClosed($session, $cashier);

            return $invoice;
        });

        event(new TableSessionUpdated($session->refresh()));

        return $invoice;
    }
}
