<?php

namespace Tests\Feature\Services;

use App\Enums\OrderConfirmMode;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\ServiceRequestType;
use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use App\Enums\UserRole;
use App\Events\KitchenBoardUpdated;
use App\Events\TableSessionUpdated;
use App\Exceptions\BusinessException;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Ordering\CartService;
use App\Services\Ordering\OrderItemService;
use App\Services\Ordering\OrderService;
use App\Services\Tables\TableSessionService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderingTest extends TestCase
{
    use RefreshDatabase;

    private TableSession $session;

    private MenuItem $pho;

    private MenuItem $tra;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
        $this->pho = MenuItem::factory()->create(['name' => 'Phở bò', 'price' => 65000]);
        $this->tra = MenuItem::factory()->create(['name' => 'Trà đá', 'price' => 5000]);
        $this->session = app(TableSessionService::class)->openForTable(DiningTable::factory()->create(), TableSessionSource::Qr);
    }

    public function test_scanning_qr_twice_reuses_the_open_session(): void
    {
        $again = app(TableSessionService::class)->openForTable($this->session->diningTable, TableSessionSource::Qr);

        $this->assertTrue($again->is($this->session));
    }

    public function test_inactive_table_cannot_be_opened(): void
    {
        $this->expectException(BusinessException::class);

        app(TableSessionService::class)->openForTable(DiningTable::factory()->create(['is_active' => false]), TableSessionSource::Qr);
    }

    public function test_each_device_has_its_own_cart(): void
    {
        $cart = app(CartService::class);
        $cart->add($this->session, 'phone-a', $this->pho->id, 2);
        $cart->add($this->session, 'phone-b', $this->tra->id);
        $cart->setNote($this->session, 'phone-a', $this->pho->id, 'ít hành');

        $this->assertSame([$this->pho->id => ['quantity' => 2, 'note' => 'ít hành']], $cart->lines($this->session, 'phone-a'));
        $this->assertSame(1, $cart->count($this->session, 'phone-b'));
    }

    public function test_cart_rejects_quantity_over_limit(): void
    {
        Setting::set('order.max_quantity_per_item', 3);

        $this->expectException(BusinessException::class);

        app(CartService::class)->setQuantity($this->session, 'phone-a', $this->pho->id, 4);
    }

    public function test_first_qr_order_waits_for_staff_then_next_orders_go_straight_to_kitchen(): void
    {
        Event::fake([TableSessionUpdated::class, KitchenBoardUpdated::class]);
        $orders = app(OrderService::class);
        $cart = app(CartService::class);

        $cart->add($this->session, 'phone-a', $this->pho->id, 2);
        $first = $orders->placeFromCart($this->session, 'phone-a');

        $this->assertSame(OrderStatus::Pending, $first->status);
        $this->assertSame(OrderItemStatus::Pending, $first->items->first()->status);
        $this->assertSame('Phở bò', $first->items->first()->item_name);
        $this->assertSame(65000, $first->items->first()->unit_price);
        $this->assertSame([], $cart->lines($this->session, 'phone-a'));
        Event::assertDispatched(TableSessionUpdated::class, fn ($e) => str_contains((string) $e->alert, 'cần xác nhận'));
        Event::assertNotDispatched(KitchenBoardUpdated::class);

        $orders->confirm($first, User::factory()->create());

        $this->assertSame(OrderItemStatus::Queued, $first->items()->first()->status);
        Event::assertDispatched(KitchenBoardUpdated::class);

        $cart->add($this->session, 'phone-b', $this->tra->id);
        $second = $orders->placeFromCart($this->session, 'phone-b');

        $this->assertSame(OrderStatus::Confirmed, $second->status);
        $this->assertSame(OrderItemStatus::Queued, $second->items->first()->status);
    }

    public function test_confirm_mode_always_and_never(): void
    {
        $orders = app(OrderService::class);
        $cart = app(CartService::class);

        Setting::set('order.confirm_mode', OrderConfirmMode::Never->value);
        $cart->add($this->session, 'a', $this->pho->id);
        $this->assertSame(OrderStatus::Confirmed, $orders->placeFromCart($this->session, 'a')->status);

        Setting::set('order.confirm_mode', OrderConfirmMode::Always->value);
        $cart->add($this->session, 'a', $this->pho->id);
        $this->assertSame(OrderStatus::Pending, $orders->placeFromCart($this->session, 'a')->status);
    }

    public function test_staff_order_skips_confirmation(): void
    {
        $order = app(OrderService::class)->placeByStaff(
            $this->session,
            User::factory()->create(),
            [$this->pho->id => ['quantity' => 1, 'note' => 'không hành']],
        );

        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame('không hành', $order->items->first()->note);
    }

    public function test_sold_out_item_cannot_be_ordered(): void
    {
        $cart = app(CartService::class);
        $cart->add($this->session, 'a', $this->pho->id);
        $this->pho->update(['is_available' => false]);

        try {
            app(OrderService::class)->placeFromCart($this->session, 'a');
            $this->fail('Expected BusinessException');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('Phở bò', $e->getMessage());
        }

        $this->assertSame(0, $this->session->orders()->count());
        $this->assertNotEmpty($cart->lines($this->session, 'a'), 'Giỏ hàng giữ nguyên khi gửi lỗi');
    }

    public function test_cannot_order_on_closed_session(): void
    {
        $this->session->update(['status' => TableSessionStatus::Closed, 'closed_at' => now()]);

        $this->expectException(BusinessException::class);

        app(OrderService::class)->placeByStaff($this->session, User::factory()->create(), [$this->pho->id => ['quantity' => 1]]);
    }

    public function test_reject_cancels_items(): void
    {
        $cart = app(CartService::class);
        $cart->add($this->session, 'a', $this->pho->id);
        $order = app(OrderService::class)->placeFromCart($this->session, 'a');

        app(OrderService::class)->reject($order, User::factory()->create(), 'Khách đặt nhầm');

        $this->assertSame(OrderStatus::Rejected, $order->fresh()->status);
        $this->assertSame(OrderItemStatus::Cancelled, $order->items()->first()->status);
        $this->assertSame(0, $this->session->orderItems()->billable()->count());
    }

    public function test_kitchen_flow_and_cancel_rules(): void
    {
        $order = app(OrderService::class)->placeByStaff($this->session, User::factory()->create(), [
            $this->pho->id => ['quantity' => 1],
            $this->tra->id => ['quantity' => 1],
        ]);
        [$pho, $tra] = $order->items->all();
        $kitchen = User::factory()->role(UserRole::Kitchen)->create();
        $waiter = User::factory()->create();
        $items = app(OrderItemService::class);

        $items->transition($pho, OrderItemStatus::Cooking, $kitchen);
        $items->transition($pho, OrderItemStatus::Ready, $kitchen);
        $items->transition($pho, OrderItemStatus::Served, $waiter);

        $pho->refresh();
        $this->assertSame(OrderItemStatus::Served, $pho->status);
        $this->assertNotNull($pho->cooking_at);
        $this->assertNotNull($pho->ready_at);
        $this->assertNotNull($pho->served_at);

        $items->transition($tra, OrderItemStatus::Cooking, $kitchen);

        try {
            $items->transition($tra, OrderItemStatus::Cancelled, $waiter);
            $this->fail('Phục vụ không được hủy món đang làm');
        } catch (BusinessException) {
        }

        $items->transition($tra, OrderItemStatus::Cancelled, $kitchen, 'Hết nguyên liệu');
        $this->assertSame('Hết nguyên liệu', $tra->fresh()->cancel_reason);

        $this->expectException(BusinessException::class);
        $items->transition($pho, OrderItemStatus::Cancelled, $kitchen);
    }

    public function test_request_bill_is_not_duplicated_and_new_order_reopens_session(): void
    {
        $sessions = app(TableSessionService::class);

        $sessions->requestService($this->session, ServiceRequestType::RequestBill);
        $sessions->requestService($this->session, ServiceRequestType::RequestBill);

        $this->assertSame(1, $this->session->serviceRequests()->count());
        $this->assertSame(TableSessionStatus::PaymentRequested, $this->session->fresh()->status);

        app(OrderService::class)->placeByStaff($this->session, User::factory()->create(), [$this->tra->id => ['quantity' => 1]]);

        $this->assertSame(TableSessionStatus::Open, $this->session->fresh()->status);
    }

    public function test_transfer_to_occupied_table_fails(): void
    {
        $occupied = app(TableSessionService::class)->openForTable(DiningTable::factory()->create(), TableSessionSource::Staff);
        $free = DiningTable::factory()->create();

        app(TableSessionService::class)->transfer($this->session, $free);
        $this->assertTrue($this->session->fresh()->diningTable->is($free));

        $this->expectException(BusinessException::class);
        app(TableSessionService::class)->transfer($this->session, $occupied->diningTable);
    }
}
