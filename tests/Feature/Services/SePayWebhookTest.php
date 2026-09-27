<?php

namespace Tests\Feature\Services;

use App\Enums\BankTransactionStatus;
use App\Enums\OrderItemStatus;
use App\Enums\PaymentMethod;
use App\Enums\TableSessionSource;
use App\Filament\Resources\BankTransactions\Pages\ListBankTransactions;
use App\Livewire\Staff\TableDetail;
use App\Models\BankTransaction;
use App\Models\DiningTable;
use App\Models\Invoice;
use App\Models\MenuItem;
use App\Models\Setting;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Ordering\OrderService;
use App\Services\Tables\TableSessionService;
use Database\Seeders\SettingSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SePayWebhookTest extends TestCase
{
    use RefreshDatabase;

    private TableSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.sepay.webhook_key' => 'secret-key']);
        $this->seed(SettingSeeder::class);
        Setting::set('bank.account_number', '0123456789');

        $this->session = app(TableSessionService::class)->openForTable(DiningTable::factory()->create(['code' => 'A01']), TableSessionSource::Qr);
        $item = MenuItem::factory()->create(['price' => 135000]);
        app(OrderService::class)->placeByStaff($this->session, User::factory()->create(), [$item->id => ['quantity' => 1]])
            ->items()->update(['status' => OrderItemStatus::Served]);
    }

    public function test_rejects_missing_or_wrong_api_key(): void
    {
        $this->postJson('/webhooks/sepay', $this->payload())->assertUnauthorized();
        $this->postJson('/webhooks/sepay', $this->payload(), ['Authorization' => 'Apikey wrong'])->assertUnauthorized();

        config(['services.sepay.webhook_key' => null]);
        $this->postJson('/webhooks/sepay', $this->payload(), $this->auth())->assertStatus(503);

        $this->assertSame(0, BankTransaction::count());
    }

    public function test_exact_transfer_closes_table_automatically(): void
    {
        $this->postJson('/webhooks/sepay', $this->payload(), $this->auth())
            ->assertOk()
            ->assertJson(['success' => true, 'status' => 'applied']);

        $transaction = BankTransaction::sole();
        $invoice = Invoice::sole();

        $this->assertFalse($this->session->fresh()->isOpen());
        $this->assertSame(135000, $invoice->total);
        $this->assertNull($invoice->cashier_id);
        $this->assertSame(PaymentMethod::BankTransfer, $invoice->payments->first()->method);
        $this->assertSame('FT26270123', $invoice->payments->first()->reference);
        $this->assertTrue($invoice->payments->first()->bankTransaction->is($transaction));
        $this->assertTrue($transaction->invoice->is($invoice));
    }

    public function test_duplicate_webhook_is_processed_once(): void
    {
        $this->postJson('/webhooks/sepay', $this->payload(), $this->auth())->assertOk();
        $this->postJson('/webhooks/sepay', $this->payload(), $this->auth())->assertOk()->assertJson(['status' => 'applied']);

        $this->assertSame(1, BankTransaction::count());
        $this->assertSame(1, Invoice::count());
    }

    public function test_bank_mangled_content_still_matches(): void
    {
        $code = $this->session->paymentCode();
        $content = 'MBVCB.1234.'.substr($code, 0, 4).' '.substr($code, 4).'.CT tu 0987654321 NGUYEN VAN A';

        $this->postJson('/webhooks/sepay', $this->payload(['content' => $content]), $this->auth())->assertJson(['status' => 'applied']);
    }

    public function test_underpaid_transfer_waits_for_staff_and_staff_uses_it(): void
    {
        $this->postJson('/webhooks/sepay', $this->payload(['transferAmount' => 100000]), $this->auth())
            ->assertJson(['status' => 'matched']);

        $transaction = BankTransaction::sole();
        $this->assertSame('Thiếu 35.000 ₫', $transaction->note);
        $this->assertTrue($this->session->fresh()->isOpen());

        Livewire::actingAs(User::factory()->create())
            ->test(TableDetail::class, ['diningTable' => $this->session->diningTable])
            ->assertSee('Đã nhận chuyển khoản 100.000 ₫')
            ->call('useTransfer', $transaction->id)
            ->assertDispatched('toast', type: 'warning')
            ->call('addPayment', 'cash')
            ->call('checkout');

        $this->assertFalse($this->session->fresh()->isOpen());
        $this->assertSame(BankTransactionStatus::Applied, $transaction->fresh()->status);
        $this->assertSame([100000, 35000], Invoice::sole()->payments->pluck('amount')->all());
    }

    public function test_unmatched_outgoing_and_other_account(): void
    {
        $this->postJson('/webhooks/sepay', $this->payload(['id' => 1, 'content' => 'chuyen tien an trua']), $this->auth())->assertJson(['status' => 'unmatched']);
        $this->postJson('/webhooks/sepay', $this->payload(['id' => 2, 'transferType' => 'out']), $this->auth())->assertJson(['status' => 'ignored']);
        $this->postJson('/webhooks/sepay', $this->payload(['id' => 3, 'accountNumber' => '999']), $this->auth())->assertJson(['status' => 'ignored']);

        $this->assertTrue($this->session->fresh()->isOpen());
    }

    public function test_auto_confirm_can_be_turned_off(): void
    {
        Setting::set('bank.auto_confirm', false);

        $this->postJson('/webhooks/sepay', $this->payload(), $this->auth())->assertJson(['status' => 'matched']);

        $this->assertTrue($this->session->fresh()->isOpen());
    }

    public function test_unfinished_items_block_auto_close(): void
    {
        $this->session->orderItems()->update(['order_items.status' => OrderItemStatus::Cooking]);

        $this->postJson('/webhooks/sepay', $this->payload(), $this->auth())->assertJson(['status' => 'matched']);

        $this->assertStringContainsString('chưa phục vụ', BankTransaction::sole()->note);
    }

    public function test_admin_assigns_unmatched_transaction_to_table(): void
    {
        $this->postJson('/webhooks/sepay', $this->payload(['content' => 'ck tien an']), $this->auth());
        $transaction = BankTransaction::sole();
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListBankTransactions::class)
            ->assertSee('Không khớp bàn')
            ->callAction(TestAction::make('assign')->table($transaction), ['table_session_id' => $this->session->id]);

        $this->assertSame(BankTransactionStatus::Applied, $transaction->fresh()->status);
        $this->assertFalse($this->session->fresh()->isOpen());
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'id' => 92704,
            'gateway' => 'Vietcombank',
            'transactionDate' => now()->format('Y-m-d H:i:s'),
            'accountNumber' => '0123456789',
            'code' => null,
            'content' => $this->session->paymentCode().' thanh toan',
            'transferType' => 'in',
            'transferAmount' => 135000,
            'accumulated' => 19077000,
            'subAccount' => null,
            'referenceCode' => 'FT26270123',
            'description' => '',
            ...$overrides,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function auth(): array
    {
        return ['Authorization' => 'Apikey secret-key'];
    }
}
