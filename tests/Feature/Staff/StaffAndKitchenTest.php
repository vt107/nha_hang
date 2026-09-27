<?php

namespace Tests\Feature\Staff;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\ServiceRequestType;
use App\Enums\TableSessionSource;
use App\Enums\UserRole;
use App\Livewire\Kitchen\Board;
use App\Livewire\Staff\TableBoard;
use App\Livewire\Staff\TableDetail;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Ordering\CartService;
use App\Services\Ordering\OrderService;
use App\Services\Tables\TableSessionService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StaffAndKitchenTest extends TestCase
{
    use RefreshDatabase;

    private User $waiter;

    private User $kitchen;

    private DiningTable $table;

    private MenuItem $pho;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
        $this->waiter = User::factory()->role(UserRole::Waiter)->create(['email' => 'waiter@test.vn']);
        $this->kitchen = User::factory()->role(UserRole::Kitchen)->create();
        $this->table = DiningTable::factory()->create(['code' => 'A01']);
        $this->pho = MenuItem::factory()->create(['name' => 'Phở bò', 'price' => 65000]);
    }

    public function test_login_redirects_by_role_and_blocks_inactive(): void
    {
        $this->post('/login', ['email' => 'waiter@test.vn', 'password' => 'password'])->assertRedirect(route('staff.tables'));
        $this->post('/logout');

        $this->post('/login', ['email' => $this->kitchen->email, 'password' => 'password'])->assertRedirect(route('kitchen.board'));
        $this->post('/logout');

        $this->waiter->update(['is_active' => false]);
        $this->post('/login', ['email' => 'waiter@test.vn', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_role_access(): void
    {
        $this->get('/staff')->assertRedirect('/login');

        $this->actingAs($this->kitchen)->get('/staff')->assertForbidden();
        $this->actingAs($this->kitchen)->get('/kitchen')->assertOk()->assertSee('Bếp');
        $this->actingAs($this->waiter)->get('/kitchen')->assertForbidden();
        $this->actingAs($this->waiter)->get('/staff')->assertOk()->assertSee('A01');
        $this->actingAs($this->waiter)->get(route('staff.tables.show', $this->table))->assertOk()->assertSee('Bàn đang trống');
    }

    public function test_board_shows_pending_orders_and_requests(): void
    {
        $session = $this->customerOrders();
        app(TableSessionService::class)->requestService($session, ServiceRequestType::CallWaiter);

        Livewire::actingAs($this->waiter)
            ->test(TableBoard::class)
            ->assertSee('1 order chờ')
            ->assertSee('🔔 1')
            ->assertSet('stats.pending_orders', 1);
    }

    public function test_staff_opens_table_and_orders_for_customer(): void
    {
        Livewire::actingAs($this->waiter)
            ->test(TableDetail::class, ['diningTable' => $this->table])
            ->set('guestCount', 3)
            ->call('openTable')
            ->assertSee('Gọi món hộ khách')
            ->call('pick', $this->pho->id, 1)
            ->call('pick', $this->pho->id, 1)
            ->assertSet('picked', [CartService::lineKey($this->pho->id) => ['menu_item_id' => $this->pho->id, 'option_ids' => [], 'quantity' => 2, 'note' => null]])
            ->set('staffNote', 'Làm nhanh')
            ->call('submitStaffOrder')
            ->assertSet('picked', []);

        $session = $this->table->openSession;
        $this->assertSame(3, $session->guest_count);
        $this->assertSame(TableSessionSource::Staff, $session->source);
        $this->assertSame(OrderItemStatus::Queued, $session->orderItems()->first()->status);
        $this->assertSame(2, $session->orderItems()->first()->quantity);
    }

    public function test_staff_confirms_and_rejects_customer_orders(): void
    {
        $session = $this->customerOrders();
        $order = $session->orders()->first();

        Livewire::actingAs($this->waiter)
            ->test(TableDetail::class, ['diningTable' => $this->table])
            ->assertSee('Xác nhận')
            ->call('confirmOrder', $order->id)
            ->assertDispatched('toast', type: 'success');

        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);

        app(CartService::class)->add($session, 'phone-1', $this->pho->id);
        $second = app(OrderService::class)->placeFromCart($session, 'phone-1');
        $this->assertSame(OrderStatus::Confirmed, $second->status, 'Order thứ 2 không cần duyệt (first_order)');
    }

    public function test_staff_cannot_touch_orders_of_another_table(): void
    {
        $otherSession = $this->customerOrders(DiningTable::factory()->create());
        app(TableSessionService::class)->openForTable($this->table, TableSessionSource::Staff);

        Livewire::actingAs($this->waiter)
            ->test(TableDetail::class, ['diningTable' => $this->table])
            ->call('confirmOrder', $otherSession->orders()->first()->id)
            ->assertStatus(404);
    }

    public function test_kitchen_moves_items_and_waiter_serves(): void
    {
        $session = app(TableSessionService::class)->openForTable($this->table, TableSessionSource::Staff);
        $order = app(OrderService::class)->placeByStaff($session, $this->waiter, [$this->pho->id => ['quantity' => 2, 'note' => 'ít bánh']]);
        $item = $order->items->first();

        $kitchen = Livewire::actingAs($this->kitchen)
            ->test(Board::class)
            ->assertSee('Phở bò')
            ->assertSee('ít bánh')
            ->assertSee('A01')
            ->call('start', $item->id);
        $this->assertSame(OrderItemStatus::Cooking, $item->fresh()->status);

        $kitchen->call('finish', $item->id);
        $this->assertSame(OrderItemStatus::Ready, $item->fresh()->status);

        Livewire::actingAs($this->waiter)
            ->test(TableDetail::class, ['diningTable' => $this->table])
            ->assertSee('1 món bếp đã làm xong')
            ->call('serveAllReady');

        $this->assertSame(OrderItemStatus::Served, $item->fresh()->status);
        Livewire::actingAs($this->kitchen)->test(Board::class)->assertDontSee('ít bánh');
    }

    public function test_kitchen_start_all_of_same_dish(): void
    {
        $session = app(TableSessionService::class)->openForTable($this->table, TableSessionSource::Staff);
        app(OrderService::class)->placeByStaff($session, $this->waiter, [$this->pho->id => ['quantity' => 1]]);
        app(OrderService::class)->placeByStaff($session, $this->waiter, [$this->pho->id => ['quantity' => 2]]);

        Livewire::actingAs($this->kitchen)
            ->test(Board::class)
            ->assertSet('totals', collect(['Phở bò' => 3]))
            ->call('startAllOf', 'Phở bò');

        $this->assertSame(2, $session->orderItems()->where('order_items.status', OrderItemStatus::Cooking)->count());
    }

    public function test_kitchen_marks_item_sold_out(): void
    {
        Livewire::actingAs($this->kitchen)->test(Board::class)->call('toggleAvailable', $this->pho->id);

        $this->assertFalse($this->pho->fresh()->is_available);
    }

    public function test_transfer_table(): void
    {
        app(TableSessionService::class)->openForTable($this->table, TableSessionSource::Staff);
        $target = DiningTable::factory()->create(['code' => 'B02']);

        Livewire::actingAs($this->waiter)
            ->test(TableDetail::class, ['diningTable' => $this->table])
            ->set('transferTo', $target->id)
            ->call('transfer')
            ->assertRedirect(route('staff.tables.show', $target));

        $this->assertNull($this->table->openSession()->first());
        $this->assertNotNull($target->openSession()->first());
    }

    public function test_close_empty_table(): void
    {
        $session = $this->customerOrders();

        Livewire::actingAs($this->waiter)
            ->test(TableDetail::class, ['diningTable' => $this->table])
            ->call('closeWithoutPayment');

        $this->assertFalse($session->fresh()->isOpen());
        $this->assertSame(OrderStatus::Rejected, $session->orders()->first()->status);
    }

    private function customerOrders(?DiningTable $table = null): TableSession
    {
        $session = app(TableSessionService::class)->openForTable($table ?? $this->table, TableSessionSource::Qr);
        app(CartService::class)->add($session, 'phone-1', $this->pho->id);
        app(OrderService::class)->placeFromCart($session, 'phone-1');

        return $session;
    }
}
