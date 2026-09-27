<?php

namespace Tests\Feature\Customer;

use App\Enums\OrderStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\Customer\BillPage;
use App\Livewire\Customer\MenuPage;
use App\Livewire\Customer\OrdersPage;
use App\Livewire\Site\ReservationForm;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Reservation;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Ordering\OrderService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
    }

    public function test_home_page_shows_menu(): void
    {
        MenuItem::factory()->create(['name' => 'Bún chả']);

        $this->get('/')->assertOk()->assertSee('Bún chả')->assertSee('Đặt bàn ngay');
    }

    public function test_invalid_qr_shows_friendly_error(): void
    {
        $this->get('/t/khong-ton-tai')->assertNotFound()->assertSee('Mã QR không hợp lệ');
    }

    public function test_menu_requires_scanning_qr(): void
    {
        $this->get('/menu')->assertForbidden()->assertSee('Vui lòng quét mã QR');
    }

    public function test_scan_qr_opens_session_and_shows_menu(): void
    {
        $table = DiningTable::factory()->create(['code' => 'A01']);
        MenuItem::factory()->create(['name' => 'Cơm tấm']);

        $this->get('/t/'.$table->qr_token)->assertRedirect(route('customer.menu'));

        $session = $table->openSession;
        $this->assertNotNull($session);
        $this->assertSame($session->token, session('table_session_token'));

        $this->get('/menu')->assertOk()->assertSee('Bàn A01')->assertSee('Cơm tấm')->assertCookie('device_id');
    }

    public function test_customer_adds_to_cart_and_places_order(): void
    {
        [$session] = $this->scan();
        $pho = MenuItem::factory()->create(['name' => 'Phở', 'price' => 50000]);
        $soldOut = MenuItem::factory()->soldOut()->create();

        Livewire::withCookie('device_id', 'phone-1')
            ->test(MenuPage::class)
            ->call('add', $pho->id)
            ->call('add', $pho->id)
            ->call('add', $soldOut->id)
            ->assertDispatched('toast', type: 'warning')
            ->assertSet('cartCount', 2)
            ->assertSee('100.000 ₫')
            ->call('updateNote', $pho->id, 'không hành')
            ->set('orderNote', 'Mang ra nhanh giúp')
            ->call('placeOrder')
            ->assertRedirect(route('customer.orders'));

        $order = $session->orders()->with('items')->sole();
        $this->assertSame(OrderStatus::Pending, $order->status);
        $this->assertSame('phone-1', $order->device_id);
        $this->assertSame('Mang ra nhanh giúp', $order->note);
        $this->assertSame(2, $order->items->first()->quantity);
        $this->assertSame('không hành', $order->items->first()->note);
    }

    public function test_orders_page_marks_own_orders(): void
    {
        [$session] = $this->scan();
        $session->orders()->create(['code' => 'O1', 'source' => 'qr', 'status' => 'pending', 'device_id' => 'phone-1']);

        Livewire::withCookie('device_id', 'phone-1')
            ->test(OrdersPage::class)
            ->assertSee('Của bạn')
            ->assertSee('Order đang chờ nhân viên xác nhận');
    }

    public function test_bill_page_shows_vietqr_and_request_bill(): void
    {
        [$session] = $this->scan();
        $item = MenuItem::factory()->create(['price' => 120000]);
        app(OrderService::class)->placeByStaff($session, User::factory()->create(), [$item->id => ['quantity' => 1]]);

        Livewire::test(BillPage::class)
            ->assertSee('120.000 ₫')
            ->assertSee('Vietcombank')
            ->call('requestBill')
            ->assertSee('Nhân viên đang tới thanh toán');

        $this->assertSame(TableSessionStatus::PaymentRequested, $session->fresh()->status);
    }

    public function test_closed_session_shows_thank_you(): void
    {
        [$session] = $this->scan();
        $session->update(['status' => TableSessionStatus::Closed, 'closed_at' => now()]);

        $this->get('/menu')->assertOk()->assertSee('Cảm ơn quý khách');
        $this->get('/menu')->assertForbidden();
    }

    public function test_call_waiter_is_rate_limited(): void
    {
        $this->scan();
        $component = Livewire::test(MenuPage::class);

        for ($i = 0; $i < 12; $i++) {
            $component->call('callWaiter');
        }

        $component->call('callWaiter')->assertDispatched('toast', type: 'warning');
    }

    public function test_web_reservation(): void
    {
        Livewire::test(ReservationForm::class)
            ->set('customer_name', 'Nguyễn Văn A')
            ->set('customer_phone', '0901234567')
            ->set('party_size', 4)
            ->set('date', today()->addDay()->toDateString())
            ->set('time', '19:30')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSee('Đã nhận yêu cầu đặt bàn');

        $reservation = Reservation::sole();
        $this->assertSame('pending', $reservation->status->value);
        $this->assertSame('19:30', $reservation->reserved_at->format('H:i'));
    }

    public function test_reservation_validation(): void
    {
        Livewire::test(ReservationForm::class)
            ->set('customer_name', '')
            ->set('customer_phone', '123')
            ->set('party_size', 99)
            ->set('date', today()->subDay()->toDateString())
            ->call('submit')
            ->assertHasErrors(['customer_name', 'customer_phone', 'party_size', 'date']);

        Livewire::test(ReservationForm::class)
            ->set('customer_name', 'A')
            ->set('customer_phone', '0901234567')
            ->set('date', today()->toDateString())
            ->set('time', now()->addMinutes(10)->format('H:i'))
            ->call('submit')
            ->assertHasErrors(['time']);
    }

    /**
     * @return array{TableSession}
     */
    private function scan(): array
    {
        $table = DiningTable::factory()->create();
        $this->get('/t/'.$table->qr_token);

        return [$table->openSession];
    }
}
