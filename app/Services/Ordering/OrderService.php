<?php

namespace App\Services\Ordering;

use App\Enums\OrderConfirmMode;
use App\Enums\OrderItemStatus;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Events\KitchenBoardUpdated;
use App\Events\TableSessionUpdated;
use App\Exceptions\BusinessException;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Setting;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Menu\MenuCatalog;
use App\Support\Code;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private CartService $cart,
        private MenuCatalog $catalog,
    ) {}

    /** Khách gửi giỏ hàng của điện thoại mình. */
    public function placeFromCart(TableSession $session, string $deviceId, ?string $note = null): Order
    {
        $order = $this->place($session, $this->cart->lines($session, $deviceId), OrderSource::Qr, $note, deviceId: $deviceId);

        $this->cart->clear($session, $deviceId);

        return $order;
    }

    /**
     * Nhân viên gọi hộ khách: vào bếp ngay, không cần duyệt.
     *
     * @param  array<int, array{quantity: int, note?: ?string}>  $lines
     */
    public function placeByStaff(TableSession $session, User $staff, array $lines, ?string $note = null): Order
    {
        return $this->place($session, $lines, OrderSource::Staff, $note, createdBy: $staff);
    }

    public function confirm(Order $order, User $staff): Order
    {
        DB::transaction(function () use ($order, $staff) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== OrderStatus::Pending) {
                throw new BusinessException('Order này đã được xử lý.');
            }

            $order->update([
                'status' => OrderStatus::Confirmed,
                'confirmed_by' => $staff->id,
                'confirmed_at' => now(),
            ]);

            $order->items()->where('status', OrderItemStatus::Pending)->update([
                'status' => OrderItemStatus::Queued,
                'queued_at' => now(),
            ]);
        });

        $order->refresh();
        $table = $order->tableSession->diningTable;

        event(new TableSessionUpdated($order->tableSession));
        event(new KitchenBoardUpdated("{$table->displayName()}: {$order->items()->count()} món mới"));

        return $order;
    }

    public function reject(Order $order, User $staff, ?string $reason = null): Order
    {
        DB::transaction(function () use ($order, $staff, $reason) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== OrderStatus::Pending) {
                throw new BusinessException('Order này đã được xử lý.');
            }

            $order->update([
                'status' => OrderStatus::Rejected,
                'confirmed_by' => $staff->id,
                'rejected_reason' => $reason,
            ]);

            $order->items()->update([
                'status' => OrderItemStatus::Cancelled,
                'cancel_reason' => $reason ?? 'Nhân viên từ chối order',
                'cancelled_by' => $staff->id,
                'cancelled_at' => now(),
            ]);
        });

        event(new TableSessionUpdated($order->tableSession));

        return $order->refresh();
    }

    /**
     * @param  array<int, array{quantity: int, note?: ?string}>  $lines  menu_item_id => dòng
     */
    private function place(
        TableSession $session,
        array $lines,
        OrderSource $source,
        ?string $note,
        ?string $deviceId = null,
        ?User $createdBy = null,
    ): Order {
        $lines = array_filter($lines, fn (array $line) => ($line['quantity'] ?? 0) > 0);

        if ($lines === []) {
            throw new BusinessException('Giỏ hàng đang trống.');
        }

        $max = (int) Setting::get('order.max_quantity_per_item', 20);

        foreach ($lines as $line) {
            if ($line['quantity'] > $max) {
                throw new BusinessException("Mỗi món gọi tối đa {$max} phần một lần.");
            }
        }

        $menuItems = $this->catalog->orderableItems(array_keys($lines));

        if ($missing = array_diff(array_keys($lines), $menuItems->keys()->all())) {
            $names = MenuItem::withTrashed()->whereKey($missing)->pluck('name')->join(', ');

            throw new BusinessException("Món đã hết hoặc ngừng bán: {$names}. Vui lòng bỏ khỏi giỏ hàng.");
        }

        [$order, $needsConfirmation] = DB::transaction(function () use ($session, $lines, $source, $note, $deviceId, $createdBy, $menuItems) {
            $session = TableSession::query()->lockForUpdate()->findOrFail($session->id);

            if (! $session->isOpen()) {
                throw new BusinessException('Phiên bàn đã kết thúc, vui lòng quét lại mã QR trên bàn.');
            }

            $needsConfirmation = $source === OrderSource::Qr && $this->needsConfirmation($session);

            $order = $session->orders()->create([
                'code' => Code::make('O'),
                'source' => $source,
                'status' => $needsConfirmation ? OrderStatus::Pending : OrderStatus::Confirmed,
                'device_id' => $deviceId,
                'note' => filled($note) ? $note : null,
                'created_by' => $createdBy?->id,
                'confirmed_by' => $needsConfirmation ? null : $createdBy?->id,
                'confirmed_at' => $needsConfirmation ? null : now(),
            ]);

            foreach ($lines as $menuItemId => $line) {
                $menuItem = $menuItems[$menuItemId];

                $order->items()->create([
                    'menu_item_id' => $menuItem->id,
                    'item_name' => $menuItem->name,
                    'unit_price' => $menuItem->price,
                    'quantity' => $line['quantity'],
                    'note' => $line['note'] ?? null,
                    'status' => $needsConfirmation ? OrderItemStatus::Pending : OrderItemStatus::Queued,
                    'queued_at' => $needsConfirmation ? null : now(),
                ]);
            }

            // Khách gọi thêm sau khi đã bấm thanh toán: quay lại trạng thái đang phục vụ.
            if ($session->status === TableSessionStatus::PaymentRequested) {
                $session->update(['status' => TableSessionStatus::Open]);
            }

            return [$order, $needsConfirmation];
        });

        $tableName = $session->diningTable->displayName();

        event(new TableSessionUpdated(
            $session->refresh(),
            match (true) {
                $needsConfirmation => "{$tableName} có order mới cần xác nhận",
                $source === OrderSource::Qr => "{$tableName} gọi thêm món",
                default => null,
            },
        ));

        if (! $needsConfirmation) {
            event(new KitchenBoardUpdated("{$tableName}: ".count($lines).' món mới'));
        }

        return $order->load('items');
    }

    private function needsConfirmation(TableSession $session): bool
    {
        $mode = OrderConfirmMode::tryFrom((string) Setting::get('order.confirm_mode')) ?? OrderConfirmMode::FirstOrder;

        return match ($mode) {
            OrderConfirmMode::Always => true,
            OrderConfirmMode::Never => false,
            OrderConfirmMode::FirstOrder => ! $session->orders()->where('status', OrderStatus::Confirmed)->exists(),
        };
    }
}
