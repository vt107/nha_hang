<?php

namespace Tests\Feature\Services;

use App\Enums\InvoiceStatus;
use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use App\Exceptions\BusinessException;
use App\Filament\Widgets\RevenueStats;
use App\Filament\Widgets\TopItemsTable;
use App\Livewire\Staff\TableDetail;
use App\Models\DiningTable;
use App\Models\Invoice;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Billing\PaymentLine;
use App\Services\Ordering\CartService;
use App\Services\Ordering\OrderService;
use App\Services\Reports\DateRange;
use App\Services\Reports\RevenueReport;
use App\Services\Tables\TableSessionService;
use App\Support\VietQr;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private User $waiter;

    private TableSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
        $this->waiter = User::factory()->create();
        $this->session = app(TableSessionService::class)->openForTable(DiningTable::factory()->create(), TableSessionSource::Staff);
    }

    public function test_summary_applies_discount_service_charge_and_vat(): void
    {
        Setting::set('billing.service_charge_percent', 5);
        Setting::set('billing.vat_percent', 8);
        $this->serve(['Phở' => [2, 65000], 'Trà đá' => [3, 5000]]);

        $summary = app(BillingService::class)->summarize($this->session, 5000);

        $this->assertSame(145000, $summary->subtotal);
        $this->assertSame(5000, $summary->discount);
        $this->assertSame(7000, $summary->serviceCharge);      // 5% × 140.000
        $this->assertSame(11760, $summary->vat);               // 8% × 147.000
        $this->assertSame(158760, $summary->total);
        $this->assertCount(2, $summary->lines);
    }

    public function test_discount_cannot_exceed_subtotal(): void
    {
        $this->serve(['Phở' => [1, 65000]]);

        $this->assertSame(0, app(BillingService::class)->summarize($this->session, 999999)->total);
    }

    public function test_cancelled_and_unconfirmed_items_are_not_billed(): void
    {
        $this->serve(['Phở' => [1, 65000]]);
        $item = MenuItem::factory()->create(['price' => 30000]);
        app(CartService::class)->add($this->session, 'phone', $item->id);
        Setting::set('order.confirm_mode', 'always');
        app(OrderService::class)->placeFromCart($this->session, 'phone');

        $this->assertSame(65000, app(BillingService::class)->summarize($this->session)->subtotal);
    }

    public function test_cash_checkout_closes_session_and_records_change(): void
    {
        $this->serve(['Phở' => [2, 65000]]);
        app(CartService::class)->add($this->session, 'phone', MenuItem::factory()->create()->id);
        Setting::set('order.confirm_mode', 'always');
        $pending = app(OrderService::class)->placeFromCart($this->session, 'phone');

        $invoice = app(BillingService::class)->checkout($this->session, $this->waiter, [PaymentLine::cash(received: 200000)]);

        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(130000, $invoice->total);
        $this->assertSame(70000, $invoice->payments->first()->changeAmount());
        $this->assertSame(TableSessionStatus::Closed, $this->session->fresh()->status);
        $this->assertNull($this->session->diningTable->openSession()->first());
        $this->assertSame(OrderStatus::Rejected, $pending->fresh()->status, 'Order chưa duyệt bị hủy khi thanh toán');
    }

    public function test_checkout_rules(): void
    {
        $billing = app(BillingService::class);

        try {
            $billing->checkout($this->session, $this->waiter, [PaymentLine::cash()]);
            $this->fail('Bàn chưa có món');
        } catch (BusinessException) {
        }

        $this->serve(['Phở' => [1, 65000]], OrderItemStatus::Cooking);

        try {
            $billing->checkout($this->session, $this->waiter, [PaymentLine::cash()]);
            $this->fail('Còn món đang làm');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('chưa phục vụ', $e->getMessage());
        }

        $this->session->orderItems()->update(['order_items.status' => OrderItemStatus::Served]);

        try {
            $billing->checkout($this->session, $this->waiter, [PaymentLine::cash(received: 50000)]);
            $this->fail('Tiền khách đưa thiếu');
        } catch (BusinessException) {
        }

        $invoice = $billing->checkout($this->session, $this->waiter, [PaymentLine::transfer(reference: 'FT123')]);
        $this->assertSame('FT123', $invoice->payments->first()->reference);

        $this->expectException(BusinessException::class);
        $billing->checkout($this->session, $this->waiter, [PaymentLine::cash()]);
    }

    public function test_staff_checkout_screen(): void
    {
        $this->serve(['Phở' => [2, 65000]]);

        Livewire::actingAs($this->waiter)
            ->test(TableDetail::class, ['diningTable' => $this->session->diningTable])
            ->assertSee('130.000 ₫')
            ->set('discount', 10000)
            ->set('payments.0.method', 'cash')
            ->set('payments.0.received', 200000)
            ->assertSee('Thối lại: 80.000 ₫')
            ->call('checkout')
            ->assertSee('Đã thanh toán 120.000 ₫')
            ->assertSee('Bàn đang trống');

        $invoice = Invoice::sole();
        $this->assertSame(10000, $invoice->discount_amount);

        $this->get(route('staff.invoices.print', $invoice))->assertOk()->assertSee('HÓA ĐƠN THANH TOÁN')->assertSee('Phở');
    }

    public function test_revenue_report(): void
    {
        $this->serve(['Phở' => [2, 65000], 'Trà đá' => [1, 5000]]);
        app(BillingService::class)->checkout($this->session, $this->waiter, [PaymentLine::cash()]);

        $session2 = app(TableSessionService::class)->openForTable(DiningTable::factory()->create(), TableSessionSource::Qr);
        $this->serve(['Phở' => [1, 65000]], session: $session2);
        app(BillingService::class)->checkout($session2, $this->waiter, [PaymentLine::transfer()]);

        $report = app(RevenueReport::class);
        $today = DateRange::fromFilters(['preset' => 'today']);

        $summary = $report->summary($today);
        $this->assertSame(200000, $summary['revenue']);
        $this->assertSame(2, $summary['invoices']);
        $this->assertSame(100000, $summary['average']);
        $this->assertSame(['cash' => 135000, 'bank_transfer' => 65000], $summary['by_method']);

        $this->assertSame(['name' => 'Phở', 'quantity' => 3, 'amount' => 195000], $report->topItems($today)->first());
        $this->assertSame(200000, $report->byDay(DateRange::fromFilters(['preset' => 'last_7_days']))[today()->toDateString()]);
        $this->assertCount(7, $report->byDay(DateRange::fromFilters(['preset' => 'last_7_days'])));
        $this->assertSame(0, $report->summary(DateRange::fromFilters(['preset' => 'yesterday']))['revenue']);
    }

    public function test_revenue_pages_render(): void
    {
        $this->serve(['Phở' => [1, 65000]]);
        app(BillingService::class)->checkout($this->session, $this->waiter, [PaymentLine::cash()]);
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Tổng quan');
        $this->actingAs($admin)->get('/admin/revenue')->assertOk()->assertSee('Doanh thu');
        $this->actingAs($admin)->get('/admin/invoices')->assertOk();

        Livewire::actingAs($admin)
            ->test(TopItemsTable::class, ['pageFilters' => ['preset' => 'today']])
            ->assertSee('Phở');
        Livewire::actingAs($admin)
            ->test(RevenueStats::class, ['pageFilters' => ['preset' => 'today']])
            ->assertSee('65.000 ₫');
    }

    public function test_vietqr_payload(): void
    {
        $this->assertSame('29B1', VietQr::crc16('123456789'));

        $payload = VietQr::payload('970436', '0123456789', 150000, 'S260927-7KQ2 bàn A01');

        $this->assertStringStartsWith('000201010212', $payload);
        $this->assertStringContainsString('0010A000000727', $payload);
        $this->assertStringContainsString('0006970436', $payload);
        $this->assertStringContainsString('01100123456789', $payload);
        $this->assertStringContainsString('5406150000', $payload);
        $this->assertStringContainsString('S260927 7KQ2 BAN A01', $payload);
        $this->assertSame(VietQr::crc16(substr($payload, 0, -4)), substr($payload, -4));
    }

    /**
     * @param  array<string, array{int, int}>  $items  tên => [số lượng, giá]
     */
    private function serve(array $items, OrderItemStatus $status = OrderItemStatus::Served, ?TableSession $session = null): void
    {
        $session ??= $this->session;
        $lines = [];

        foreach ($items as $name => [$quantity, $price]) {
            $lines[MenuItem::factory()->create(['name' => $name, 'price' => $price])->id] = ['quantity' => $quantity];
        }

        app(OrderService::class)->placeByStaff($session, $this->waiter, $lines)->items()->update([
            'status' => $status,
        ]);
    }
}
