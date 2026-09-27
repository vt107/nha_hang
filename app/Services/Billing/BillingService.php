<?php

namespace App\Services\Billing;

use App\Enums\BankTransactionStatus;
use App\Enums\InvoiceStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\TableSessionStatus;
use App\Enums\UserRole;
use App\Events\TableSessionUpdated;
use App\Exceptions\BusinessException;
use App\Models\BankTransaction;
use App\Models\Invoice;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Tables\TableSessionService;
use App\Support\Code;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Thu tiền: một phiên bàn có thể tách nhiều hóa đơn (theo món), mỗi hóa đơn nhận nhiều khoản thanh toán
 * (chia đều, nửa tiền mặt nửa chuyển khoản). Phiên đóng khi không còn món nào phải thu.
 */
class BillingService
{
    public function __construct(private TableSessionService $sessions) {}

    /**
     * Tạm tính các món còn phải thu (chưa vào hóa đơn nào), hoặc chỉ các món được chọn khi tách bill.
     * Phí phục vụ tính trên (tạm tính - giảm giá); VAT tính trên (tạm tính - giảm giá + phí phục vụ).
     *
     * @param  array<int, int>|null  $selection  order_item_id => số phần tách ra
     */
    public function summarize(TableSession $session, int $discount = 0, ?array $selection = null): BillSummary
    {
        $items = $session->orderItems()->unbilled()->orderBy('order_items.id')->get();

        if ($selection !== null) {
            $items = $items
                ->filter(fn (OrderItem $item) => ($selection[$item->id] ?? 0) > 0)
                ->each(fn (OrderItem $item) => $item->quantity = min($item->quantity, (int) $selection[$item->id]));
        }

        return $this->summarizeItems($items, $discount);
    }

    /**
     * Nhân viên xác nhận đã nhận tiền (hoặc webhook ngân hàng, khi $cashier = null).
     *
     * @param  list<PaymentLine>  $payments
     * @param  array<int, int>|null  $selection  null = thu toàn bộ món còn lại; có giá trị = tách bill theo món
     */
    public function checkout(
        TableSession $session,
        ?User $cashier,
        array $payments,
        int $discount = 0,
        ?array $selection = null,
        ?string $note = null,
    ): Invoice {
        [$invoice, $closed] = DB::transaction(function () use ($session, $cashier, $payments, $discount, $selection, $note) {
            $session = TableSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $session->isOpen()) {
                throw new BusinessException('Phiên bàn đã đóng.');
            }

            $unbilled = $session->orderItems()->unbilled()->orderBy('order_items.id')->lockForUpdate()->get();
            $items = $selection === null ? $unbilled : $this->takeSelection($unbilled, $selection);

            if ($items->isEmpty()) {
                throw new BusinessException($selection === null
                    ? 'Bàn chưa có món nào để thanh toán. Dùng "Đóng bàn" nếu khách không dùng bữa.'
                    : 'Hãy chọn món cần tách hóa đơn.');
            }

            $isFinal = $this->remainingQuantity($unbilled, $selection) === 0;

            if ($isFinal) {
                $this->ensureNothingUnfinished($session);
            }

            $summary = $this->summarizeItems($items, $discount);
            $lines = $this->resolvePayments($payments, $summary->total);

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
                'cashier_id' => $cashier?->id,
                'note' => $note,
            ]);

            OrderItem::query()->whereKey($items->modelKeys())->update(['invoice_id' => $invoice->id]);

            foreach ($lines as $line) {
                $invoice->payments()->create([
                    'method' => $line->method,
                    'amount' => $line->amount,
                    'received_amount' => $line->method === PaymentMethod::Cash ? ($line->received ?? $line->amount) : null,
                    'reference' => $line->reference,
                    'bank_transaction_id' => $line->bankTransactionId,
                    'confirmed_by' => $cashier?->id,
                    'confirmed_at' => now(),
                ]);

                if ($line->bankTransactionId) {
                    BankTransaction::query()->whereKey($line->bankTransactionId)->update([
                        'status' => BankTransactionStatus::Applied,
                        'table_session_id' => $session->id,
                        'invoice_id' => $invoice->id,
                        'handled_by' => $cashier?->id,
                    ]);
                }
            }

            if ($isFinal) {
                $this->rejectPendingOrders($session, $cashier);
                $this->sessions->markClosed($session, $cashier);
            }

            return [$invoice, $isFinal];
        });

        event(new TableSessionUpdated($session->refresh(), $cashier === null
            ? "{$session->diningTable->displayName()}: đã nhận chuyển khoản ".number_format($invoice->total, 0, ',', '.').' ₫'.($closed ? ', bàn đã trống' : '')
            : null));

        return $invoice->load('payments');
    }

    /**
     * Hủy hóa đơn đã thu (chỉ admin / quản lý). $reopen = true: gỡ món khỏi hóa đơn để thu lại,
     * mở lại bàn nếu phiên đã đóng (bàn phải đang trống). $reopen = false: hoàn tiền / miễn phí, món không thu lại.
     */
    public function void(Invoice $invoice, User $by, string $reason, bool $reopen = true): Invoice
    {
        if (! $by->hasRole(UserRole::Admin, UserRole::Manager)) {
            throw new BusinessException('Chỉ quản lý hoặc admin được hủy hóa đơn.');
        }

        if (blank($reason)) {
            throw new BusinessException('Vui lòng nhập lý do hủy hóa đơn.');
        }

        try {
            DB::transaction(function () use ($invoice, $by, $reason, $reopen) {
                $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);

                if ($invoice->status !== InvoiceStatus::Paid) {
                    throw new BusinessException('Chỉ hủy được hóa đơn đã thanh toán.');
                }

                $invoice->update([
                    'status' => InvoiceStatus::Void,
                    'voided_at' => now(),
                    'voided_by' => $by->id,
                    'void_reason' => mb_substr(trim($reason), 0, 250),
                ]);

                if (! $reopen) {
                    return;
                }

                $invoice->items()->update(['invoice_id' => null]);

                $session = TableSession::query()->lockForUpdate()->findOrFail($invoice->table_session_id);

                if (! $session->isOpen()) {
                    // open_table_id UNIQUE: bàn đang có khách khác thì ném UniqueConstraintViolationException.
                    $session->update(['status' => TableSessionStatus::Open, 'closed_at' => null, 'closed_by' => null]);
                }
            });
        } catch (UniqueConstraintViolationException) {
            throw new BusinessException('Bàn đang có khách khác nên không mở lại được. Hãy chuyển khách hiện tại sang bàn khác, hoặc hủy hóa đơn mà không mở lại bàn.');
        }

        $invoice->refresh();
        event(new TableSessionUpdated($invoice->tableSession, $reopen
            ? "Hóa đơn {$invoice->code} đã bị hủy, {$invoice->tableSession->diningTable->displayName()} mở lại để thu lại"
            : null));

        return $invoice;
    }

    /**
     * @param  Collection<int, OrderItem>  $items
     */
    private function summarizeItems(Collection $items, int $discount): BillSummary
    {
        $lines = $items
            ->groupBy(fn (OrderItem $item) => $item->item_name.'|'.$item->unit_price.'|'.json_encode($item->options))
            ->map(fn ($group) => [
                'name' => $group->first()->display_name,
                'unit_price' => $group->first()->unit_price,
                'quantity' => $group->sum('quantity'),
                'amount' => $group->sum(fn (OrderItem $item) => $item->line_total),
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
     * Món được chọn để tách bill; chọn một phần số lượng thì tách dòng order_item làm hai.
     *
     * @param  Collection<int, OrderItem>  $unbilled
     * @param  array<int, int>  $selection
     * @return Collection<int, OrderItem>
     */
    private function takeSelection(Collection $unbilled, array $selection): Collection
    {
        $taken = new Collection;

        foreach ($selection as $itemId => $quantity) {
            $quantity = (int) $quantity;

            if ($quantity <= 0) {
                continue;
            }

            $item = $unbilled->firstWhere('id', (int) $itemId);

            if (! $item) {
                throw new BusinessException('Món được chọn không còn trong danh sách cần thu.');
            }

            if ($quantity > $item->quantity) {
                throw new BusinessException("{$item->display_name} chỉ còn {$item->quantity} phần.");
            }

            if ($quantity < $item->quantity) {
                $part = $item->replicate(['laravel_through_key']);
                $part->quantity = $quantity;
                $part->save();

                $item->newQuery()->whereKey($item->id)->update(['quantity' => $item->quantity - $quantity]);
                $taken->push($part);
            } else {
                $taken->push($item);
            }
        }

        return $taken;
    }

    /**
     * @param  Collection<int, OrderItem>  $unbilled
     * @param  array<int, int>|null  $selection
     */
    private function remainingQuantity(Collection $unbilled, ?array $selection): int
    {
        if ($selection === null) {
            return 0;
        }

        return $unbilled->sum(fn (OrderItem $item) => $item->quantity - min($item->quantity, (int) ($selection[$item->id] ?? 0)));
    }

    /**
     * @param  list<PaymentLine>  $payments
     * @return list<PaymentLine> với amount đã điền
     */
    private function resolvePayments(array $payments, int $total): array
    {
        if ($payments === []) {
            throw new BusinessException('Chưa nhập khoản thanh toán.');
        }

        $known = array_sum(array_map(fn (PaymentLine $line) => $line->amount ?? 0, $payments));
        $resolved = [];

        foreach ($payments as $index => $line) {
            if ($line->amount === null && $index !== array_key_last($payments)) {
                throw new BusinessException('Chỉ khoản cuối được để trống số tiền.');
            }

            $amount = $line->amount ?? ($total - $known);

            if ($amount <= 0) {
                throw new BusinessException('Số tiền mỗi khoản thanh toán phải lớn hơn 0.');
            }

            if ($line->method === PaymentMethod::Cash && $line->received !== null && $line->received < $amount) {
                throw new BusinessException('Tiền khách đưa chưa đủ.');
            }

            $resolved[] = new PaymentLine($line->method, $amount, $line->received, $line->reference, $line->bankTransactionId);
        }

        $sum = array_sum(array_map(fn (PaymentLine $line) => $line->amount, $resolved));

        if ($sum !== $total) {
            throw new BusinessException($sum < $total
                ? 'Các khoản thanh toán còn thiếu '.number_format($total - $sum, 0, ',', '.').' ₫.'
                : 'Các khoản thanh toán dư '.number_format($sum - $total, 0, ',', '.').' ₫.');
        }

        return $resolved;
    }

    private function ensureNothingUnfinished(TableSession $session): void
    {
        $unfinished = $session->orderItems()
            ->whereIn('order_items.status', [OrderItemStatus::Queued, OrderItemStatus::Cooking, OrderItemStatus::Ready])
            ->count();

        if ($unfinished > 0) {
            throw new BusinessException("Còn {$unfinished} món chưa phục vụ. Hãy đánh dấu đã phục vụ hoặc hủy trước khi thanh toán.");
        }
    }

    /** Order khách gửi nhưng chưa ai duyệt: không làm, không tính tiền. */
    private function rejectPendingOrders(TableSession $session, ?User $by): void
    {
        $session->orders()->where('status', OrderStatus::Pending)->each(function ($order) use ($by) {
            $order->update(['status' => OrderStatus::Rejected, 'rejected_reason' => 'Đã thanh toán']);
            $order->items()->update([
                'status' => OrderItemStatus::Cancelled,
                'cancel_reason' => 'Đã thanh toán',
                'cancelled_by' => $by?->id,
                'cancelled_at' => now(),
            ]);
        });
    }
}
