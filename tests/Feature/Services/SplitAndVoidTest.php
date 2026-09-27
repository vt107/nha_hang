<?php

namespace Tests\Feature\Services;

use App\Enums\InvoiceStatus;
use App\Enums\OrderItemStatus;
use App\Enums\PaymentMethod;
use App\Enums\TableSessionSource;
use App\Enums\TableSessionStatus;
use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Filament\Resources\Invoices\Pages\ListInvoices;
use App\Livewire\Customer\BillPage;
use App\Livewire\Staff\TableDetail;
use App\Models\DiningTable;
use App\Models\Invoice;
use App\Models\MenuItem;
use App\Models\OrderItem;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Billing\PaymentLine;
use App\Services\Ordering\OrderService;
use App\Services\Reports\DateRange;
use App\Services\Reports\RevenueReport;
use App\Services\Tables\TableSessionService;
use Database\Seeders\SettingSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SplitAndVoidTest extends TestCase
{
    use RefreshDatabase;

    private User $waiter;

    private TableSession $session;

    private OrderItem $beer;

    private OrderItem $pho;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
        $this->waiter = User::factory()->create();
        $this->session = app(TableSessionService::class)->openForTable(DiningTable::factory()->create(['code' => 'A01']), TableSessionSource::Staff);

        $beer = MenuItem::factory()->create(['name' => 'Bia', 'price' => 25000]);
        $pho = MenuItem::factory()->create(['name' => 'Phở', 'price' => 65000]);
        $order = app(OrderService::class)->placeByStaff($this->session, $this->waiter, [
            $beer->id => ['quantity' => 3],
            $pho->id => ['quantity' => 2],
        ]);
        $order->items()->update(['status' => OrderItemStatus::Served]);
        [$this->beer, $this->pho] = $order->items()->orderBy('id')->get()->all();
    }

    public function test_split_bill_by_items_including_partial_quantity(): void
    {
        $billing = app(BillingService::class);

        // Khách 1 trả 1 bia + 1 phở
        $first = $billing->checkout($this->session, $this->waiter, [PaymentLine::cash()], selection: [
            $this->beer->id => 1,
            $this->pho->id => 1,
        ]);

        $this->assertSame(90000, $first->total);
        $this->assertTrue($this->session->fresh()->isOpen(), 'Còn món chưa thu thì bàn vẫn mở');
        $this->assertSame(115000, $billing->summarize($this->session)->total, 'Còn 2 bia + 1 phở');
        $this->assertSame(2, $this->beer->fresh()->quantity, 'Dòng bia gốc còn 2, 1 phần tách sang dòng mới');
        $this->assertSame(1, (int) $first->items()->where('item_name', 'Bia')->sum('quantity'));

        // Khách 2 trả phần còn lại → đóng bàn
        $second = $billing->checkout($this->session, $this->waiter, [PaymentLine::transfer()]);

        $this->assertSame(115000, $second->total);
        $this->assertFalse($this->session->fresh()->isOpen());
        $this->assertSame(2, $this->session->invoices()->count());
        $this->assertSame(0, $this->session->orderItems()->unbilled()->count());

        $report = app(RevenueReport::class);
        $this->assertSame(205000, $report->summary(DateRange::fromFilters(['preset' => 'today']))['revenue']);
        $this->assertSame(
            ['name' => 'Bia', 'quantity' => 3, 'amount' => 75000],
            $report->topItems(DateRange::fromFilters(['preset' => 'today']))->firstWhere('name', 'Bia'),
            'Món bán chạy không bị đếm trùng khi bàn có nhiều hóa đơn',
        );
    }

    public function test_split_selection_validation(): void
    {
        $this->expectExceptionMessage('chỉ còn 3 phần');

        app(BillingService::class)->checkout($this->session, $this->waiter, [PaymentLine::cash()], selection: [$this->beer->id => 4]);
    }

    public function test_multiple_payments_must_match_total(): void
    {
        $billing = app(BillingService::class);

        try {
            $billing->checkout($this->session, $this->waiter, [PaymentLine::cash(100000), PaymentLine::transfer(50000)]);
            $this->fail('Thiếu tiền phải báo lỗi');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('còn thiếu 55.000', $e->getMessage());
        }

        $invoice = $billing->checkout($this->session, $this->waiter, [PaymentLine::cash(100000, 200000), PaymentLine::transfer()]);

        $this->assertSame(205000, $invoice->total);
        $this->assertSame([100000, 105000], $invoice->payments->pluck('amount')->all());
        $this->assertSame([PaymentMethod::Cash, PaymentMethod::BankTransfer], $invoice->payments->pluck('method')->all());
        $this->assertSame(100000, $invoice->payments->first()->changeAmount());
    }

    public function test_staff_screen_split_evenly_and_split_items(): void
    {
        $component = Livewire::actingAs($this->waiter)
            ->test(TableDetail::class, ['diningTable' => $this->session->diningTable])
            ->call('toggleSplit')
            ->call('setSplitQuantity', $this->beer->id, 2)
            ->assertSee('50.000 ₫')
            ->call('splitEvenly', 3);

        $this->assertSame([16668, 16666, 16666], array_column($component->get('payments'), 'amount'));

        $component->call('checkout')->assertSee('Hóa đơn đã thu');

        $invoice = Invoice::sole();
        $this->assertSame(50000, $invoice->total);
        $this->assertCount(3, $invoice->payments);
        $this->assertTrue($this->session->fresh()->isOpen());
    }

    public function test_customer_bill_shows_paid_part(): void
    {
        app(BillingService::class)->checkout($this->session, $this->waiter, [PaymentLine::cash()], selection: [$this->pho->id => 2]);
        $this->withSession(['table_session_token' => $this->session->token]);

        Livewire::test(BillPage::class)
            ->assertSee('Đã thanh toán trước')
            ->assertSee('130.000 ₫')
            ->assertSee('Còn phải trả')
            ->assertSee('75.000 ₫');
    }

    public function test_void_and_reopen_table_to_charge_again(): void
    {
        $billing = app(BillingService::class);
        $invoice = $billing->checkout($this->session, $this->waiter, [PaymentLine::cash()]);
        $manager = User::factory()->role(UserRole::Manager)->create();

        $billing->void($invoice, $manager, 'Sai giảm giá');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Void, $invoice->status);
        $this->assertTrue($invoice->voider->is($manager));
        $this->assertSame(TableSessionStatus::Open, $this->session->fresh()->status);
        $this->assertSame(205000, $billing->summarize($this->session)->total, 'Món được thu lại');
        $this->assertSame(0, app(RevenueReport::class)->summary(DateRange::fromFilters(['preset' => 'today']))['revenue']);

        $again = $billing->checkout($this->session, $this->waiter, [PaymentLine::cash()], discount: 5000);
        $this->assertSame(200000, $again->total);
    }

    public function test_void_without_reopen_is_a_refund(): void
    {
        $billing = app(BillingService::class);
        $invoice = $billing->checkout($this->session, $this->waiter, [PaymentLine::cash()]);

        $billing->void($invoice, User::factory()->admin()->create(), 'Món bị lỗi, miễn phí', reopen: false);

        $this->assertFalse($this->session->fresh()->isOpen());
        $this->assertSame(5, (int) $invoice->items()->sum('quantity'), 'Món vẫn gắn với hóa đơn đã hủy, không thu lại');
    }

    public function test_void_rules(): void
    {
        $billing = app(BillingService::class);
        $invoice = $billing->checkout($this->session, $this->waiter, [PaymentLine::cash()]);

        try {
            $billing->void($invoice, $this->waiter, 'x');
            $this->fail('Phục vụ không được hủy hóa đơn');
        } catch (BusinessException) {
        }

        // Bàn đã có khách mới → không mở lại được
        app(TableSessionService::class)->openForTable($this->session->diningTable, TableSessionSource::Qr);

        try {
            $billing->void($invoice, User::factory()->admin()->create(), 'Sai');
            $this->fail('Bàn có khách khác thì không mở lại được');
        } catch (BusinessException $e) {
            $this->assertStringContainsString('đang có khách khác', $e->getMessage());
        }

        $this->assertSame(InvoiceStatus::Paid, $invoice->fresh()->status, 'Lỗi thì không hủy nửa chừng');
    }

    public function test_void_from_admin_invoice_list(): void
    {
        $invoice = app(BillingService::class)->checkout($this->session, $this->waiter, [PaymentLine::cash()]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListInvoices::class)
            ->callAction(TestAction::make('void')->table($invoice), ['reason' => 'Thu nhầm', 'reopen' => true])
            ->assertHasNoFormErrors();

        $this->assertSame(InvoiceStatus::Void, $invoice->fresh()->status);
        $this->assertSame('Thu nhầm', $invoice->fresh()->void_reason);
        $this->get(route('staff.invoices.print', $invoice))->assertSee('ĐÃ HỦY');
    }
}
