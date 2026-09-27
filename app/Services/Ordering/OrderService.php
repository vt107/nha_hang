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
use App\Support\Site;
use Illuminate\Support\Facades\DB;

class OrderService
{
    public function __construct(
        private CartService $cart,
        private MenuCatalog $catalog,
        private OptionResolver $options,
    ) {}

    /** Khách gửi giỏ hàng của điện thoại mình. */
    public function placeFromCart(TableSession $session, string $deviceId, ?string $note = null): Order
    {
        if (! app(Site::class)->qrOrderingEnabled()) {
            throw new BusinessException('Nhà hàng đang tạm ngưng gọi món qua QR, vui lòng gọi nhân viên.');
        }

        $order = $this->place($session, $this->cart->lines($session, $deviceId), OrderSource::Qr, $note, deviceId: $deviceId);

        $this->cart->clear($session, $deviceId);

        return $order;
    }

    /**
     * Nhân viên gọi hộ khách: vào bếp ngay, không cần duyệt.
     *
     * @param  array<int|string, array{menu_item_id?: int, option_ids?: list<int>, quantity: int, note?: ?string}>  $lines
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
     * Mỗi dòng: menu_item_id, option_ids, quantity, note. Thiếu menu_item_id thì key của mảng là menu_item_id.
     *
     * @param  array<int|string, array{menu_item_id?: int, option_ids?: list<int>, quantity: int, note?: ?string}>  $lines
     */
    private function place(
        TableSession $session,
        array $lines,
        OrderSource $source,
        ?string $note,
        ?string $deviceId = null,
        ?User $createdBy = null,
    ): Order {
        $lines = collect($lines)
            ->map(fn (array $line, int|string $key) => [
                'menu_item_id' => (int) ($line['menu_item_id'] ?? $key),
                'option_ids' => $line['option_ids'] ?? [],
                'quantity' => (int) ($line['quantity'] ?? 0),
                'note' => $line['note'] ?? null,
            ])
            ->filter(fn (array $line) => $line['quantity'] > 0)
            ->values()
            ->all();

        if ($lines === []) {
            throw new BusinessException('Giỏ hàng đang trống.');
        }

        $max = (int) Setting::get('order.max_quantity_per_item', 20);

        foreach ($lines as $line) {
            if ($line['quantity'] > $max) {
                throw new BusinessException("Mỗi món gọi tối đa {$max} phần một lần.");
            }
        }

        $menuItemIds = array_values(array_unique(array_column($lines, 'menu_item_id')));
        $menuItems = $this->catalog->orderableItems($menuItemIds);

        if ($missing = array_diff($menuItemIds, $menuItems->keys()->all())) {
            $names = MenuItem::withTrashed()->whereKey($missing)->pluck('name')->join(', ');

            throw new BusinessException("Món đã hết hoặc ngừng bán: {$names}. Vui lòng bỏ khỏi giỏ hàng.");
        }

        // Kiểm tra tùy chọn + tính giá trước khi mở transaction.
        foreach ($lines as $index => $line) {
            $lines[$index]['resolved'] = $this->options->resolve($menuItems[$line['menu_item_id']], $line['option_ids']);
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

            foreach ($lines as $line) {
                $menuItem = $menuItems[$line['menu_item_id']];

                $order->items()->create([
                    'menu_item_id' => $menuItem->id,
                    'item_name' => $menuItem->name,
                    'unit_price' => $menuItem->price + $line['resolved']['extra'],
                    'options' => $line['resolved']['options'] ?: null,
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
            event(new KitchenBoardUpdated("{$tableName}: ".array_sum(array_column($lines, 'quantity')).' món mới'));
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
