<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * pending → queued → cooking → ready → served; cancelled được từ mọi bước trước served.
 */
enum OrderItemStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Queued = 'queued';
    case Cooking = 'cooking';
    case Ready = 'ready';
    case Served = 'served';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xác nhận',
            self::Queued => 'Đơn mới',
            self::Cooking => 'Đang làm',
            self::Ready => 'Hoàn thành',
            self::Served => 'Đã phục vụ',
            self::Cancelled => 'Đã hủy',
        };
    }

    /** Nhãn cho khách xem trên trang "Món đã gọi" (không dùng từ nội bộ của bếp). */
    public function customerLabel(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xác nhận',
            self::Queued => 'Chờ bếp',
            self::Cooking => 'Đang nấu',
            self::Ready => 'Đang mang ra',
            self::Served => 'Đã lên món',
            self::Cancelled => 'Đã hủy',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Queued => 'info',
            self::Cooking => 'warning',
            self::Ready => 'success',
            self::Served => 'primary',
            self::Cancelled => 'danger',
        };
    }

    /**
     * @return list<self>
     */
    public function nextStatuses(): array
    {
        return match ($this) {
            self::Pending => [self::Queued, self::Cancelled],
            self::Queued => [self::Cooking, self::Cancelled],
            self::Cooking => [self::Ready, self::Cancelled],
            self::Ready => [self::Served, self::Cancelled],
            self::Served, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $status): bool
    {
        return in_array($status, $this->nextStatuses(), true);
    }

    /** Cột thời điểm tương ứng trên order_items. */
    public function timestampColumn(): ?string
    {
        return match ($this) {
            self::Pending => null,
            self::Queued => 'queued_at',
            self::Cooking => 'cooking_at',
            self::Ready => 'ready_at',
            self::Served => 'served_at',
            self::Cancelled => 'cancelled_at',
        };
    }

    /**
     * Các cột trên màn hình bếp: Đơn mới / Đang làm / Hoàn thành.
     *
     * @return list<self>
     */
    public static function kitchenBoard(): array
    {
        return [self::Queued, self::Cooking, self::Ready];
    }

    /**
     * Món được tính tiền vào hóa đơn.
     *
     * @return list<self>
     */
    public static function billable(): array
    {
        return [self::Queued, self::Cooking, self::Ready, self::Served];
    }
}
