<?php

namespace Tests\Unit;

use App\Enums\OrderItemStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class OrderItemStatusTest extends TestCase
{
    /**
     * @return array<string, array{OrderItemStatus, OrderItemStatus, bool}>
     */
    public static function transitions(): array
    {
        return [
            'nhân viên xác nhận' => [OrderItemStatus::Pending, OrderItemStatus::Queued, true],
            'bếp bắt đầu làm' => [OrderItemStatus::Queued, OrderItemStatus::Cooking, true],
            'bếp làm xong' => [OrderItemStatus::Cooking, OrderItemStatus::Ready, true],
            'đã bưng ra' => [OrderItemStatus::Ready, OrderItemStatus::Served, true],
            'hủy khi đang làm' => [OrderItemStatus::Cooking, OrderItemStatus::Cancelled, true],
            'không bỏ qua bước bếp' => [OrderItemStatus::Pending, OrderItemStatus::Ready, false],
            'không lùi trạng thái' => [OrderItemStatus::Ready, OrderItemStatus::Cooking, false],
            'không hủy món đã phục vụ' => [OrderItemStatus::Served, OrderItemStatus::Cancelled, false],
            'món đã hủy là trạng thái cuối' => [OrderItemStatus::Cancelled, OrderItemStatus::Queued, false],
        ];
    }

    #[DataProvider('transitions')]
    public function test_transition_rules(OrderItemStatus $from, OrderItemStatus $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    public function test_pending_and_cancelled_items_are_not_billable(): void
    {
        $this->assertNotContains(OrderItemStatus::Pending, OrderItemStatus::billable());
        $this->assertNotContains(OrderItemStatus::Cancelled, OrderItemStatus::billable());
    }
}
