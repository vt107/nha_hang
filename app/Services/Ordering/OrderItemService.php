<?php

namespace App\Services\Ordering;

use App\Enums\OrderItemStatus;
use App\Enums\UserRole;
use App\Events\KitchenBoardUpdated;
use App\Events\TableSessionUpdated;
use App\Exceptions\BusinessException;
use App\Models\OrderItem;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Chuyển trạng thái từng món: bếp (Đơn mới → Đang làm → Hoàn thành), nhân viên (Đã phục vụ, Hủy).
 */
class OrderItemService
{
    public function transition(OrderItem $item, OrderItemStatus $to, User $by, ?string $reason = null): OrderItem
    {
        $from = DB::transaction(function () use ($item, $to, $by, $reason) {
            $item = OrderItem::query()->lockForUpdate()->findOrFail($item->id);
            $from = $item->status;

            if (! $from->canTransitionTo($to)) {
                throw new BusinessException("Món \"{$item->item_name}\" đang ở trạng thái {$from->getLabel()}, không chuyển sang {$to->getLabel()} được.");
            }

            if ($to === OrderItemStatus::Cancelled
                && in_array($from, [OrderItemStatus::Cooking, OrderItemStatus::Ready], true)
                && ! $by->hasRole(UserRole::Admin, UserRole::Manager, UserRole::Kitchen)) {
                throw new BusinessException('Món đã vào bếp làm, cần bếp hoặc quản lý hủy.');
            }

            $item->update(array_filter([
                'status' => $to,
                $to->timestampColumn() => now(),
                'cancel_reason' => $to === OrderItemStatus::Cancelled ? ($reason ?: null) : null,
                'cancelled_by' => $to === OrderItemStatus::Cancelled ? $by->id : null,
            ], fn ($value) => $value !== null));

            return $from;
        });

        $item->refresh();
        $session = $item->order->tableSession;
        $tableName = $session->diningTable->displayName();

        event(new TableSessionUpdated($session, match ($to) {
            OrderItemStatus::Ready => "{$tableName}: {$item->item_name} đã xong, mang ra bàn",
            OrderItemStatus::Cancelled => $by->role === UserRole::Kitchen ? "Bếp hủy {$item->item_name} ({$tableName})" : null,
            default => null,
        }));

        $board = OrderItemStatus::kitchenBoard();

        if (in_array($from, $board, true) || in_array($to, $board, true)) {
            event(new KitchenBoardUpdated(
                $to === OrderItemStatus::Cancelled && $by->role !== UserRole::Kitchen ? "Hủy {$item->item_name} ({$tableName})" : null,
            ));
        }

        return $item;
    }
}
