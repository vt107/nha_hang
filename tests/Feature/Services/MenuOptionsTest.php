<?php

namespace Tests\Feature\Services;

use App\Enums\TableSessionSource;
use App\Enums\UserRole;
use App\Exceptions\BusinessException;
use App\Filament\Resources\OptionGroups\Pages\CreateOptionGroup;
use App\Livewire\Customer\MenuPage;
use App\Livewire\Kitchen\Board;
use App\Livewire\Staff\TableDetail;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Option;
use App\Models\OptionGroup;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Ordering\CartService;
use App\Services\Ordering\OrderService;
use App\Services\Tables\TableSessionService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MenuOptionsTest extends TestCase
{
    use RefreshDatabase;

    private MenuItem $tea;

    private Option $sizeM;

    private Option $sizeL;

    private Option $pearl;

    private Option $pudding;

    private TableSession $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
        $this->tea = MenuItem::factory()->create(['name' => 'Trà sữa', 'price' => 30000]);

        $size = OptionGroup::factory()->create(['name' => 'Size']);
        $this->sizeM = Option::factory()->for($size, 'group')->create(['name' => 'M', 'is_default' => true]);
        $this->sizeL = Option::factory()->for($size, 'group')->create(['name' => 'L', 'price_delta' => 10000]);

        $topping = OptionGroup::factory()->optional(2)->create();
        $this->pearl = Option::factory()->for($topping, 'group')->create(['name' => 'Trân châu', 'price_delta' => 5000]);
        $this->pudding = Option::factory()->for($topping, 'group')->create(['name' => 'Pudding', 'price_delta' => 7000]);
        Option::factory()->for($topping, 'group')->create(['name' => 'Thạch', 'price_delta' => 5000]);

        $this->tea->optionGroups()->attach([$size->id, $topping->id]);
        $this->session = app(TableSessionService::class)->openForTable(DiningTable::factory()->create(['code' => 'A01']), TableSessionSource::Qr);
    }

    public function test_order_snapshots_options_and_price(): void
    {
        $order = app(OrderService::class)->placeByStaff($this->session, User::factory()->create(), [
            ['menu_item_id' => $this->tea->id, 'option_ids' => [$this->sizeL->id, $this->pearl->id], 'quantity' => 2],
        ]);

        $item = $order->items->first();
        $this->assertSame(45000, $item->unit_price);
        // assertEquals: cột JSON của MySQL tự sắp lại thứ tự key.
        $this->assertEquals([
            ['group' => 'Size', 'name' => 'L', 'price_delta' => 10000],
            ['group' => 'Topping', 'name' => 'Trân châu', 'price_delta' => 5000],
        ], $item->options);
        $this->assertSame('Trà sữa (L, Trân châu)', $item->display_name);

        $this->sizeL->update(['price_delta' => 99000]);
        $this->assertSame(90000, $item->fresh()->line_total, 'Giá đã gọi không đổi khi admin sửa giá tùy chọn');
    }

    /**
     * @return array<string, array{\Closure(self): list<int>, string}>
     */
    public static function invalidSelections(): array
    {
        return [
            'thiếu size bắt buộc' => [fn (self $t) => [$t->pearl->id], 'Vui lòng chọn Size'],
            'chọn 2 size' => [fn (self $t) => [$t->sizeM->id, $t->sizeL->id], 'tối đa 1'],
            'quá số topping' => [fn (self $t) => [$t->sizeM->id, ...$t->tea->optionGroups()->where('name', 'Topping')->first()->options->pluck('id')], 'tối đa 2'],
            'tùy chọn của món khác' => [fn (self $t) => [$t->sizeM->id, Option::factory()->create()->id], 'không hợp lệ'],
        ];
    }

    /**
     * @param  \Closure(self): list<int>  $options
     */
    #[DataProvider('invalidSelections')]
    public function test_invalid_option_selection_is_rejected(\Closure $options, string $message): void
    {
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage($message);

        app(OrderService::class)->placeByStaff($this->session, User::factory()->create(), [
            ['menu_item_id' => $this->tea->id, 'option_ids' => $options($this), 'quantity' => 1],
        ]);
    }

    public function test_sold_out_option_is_rejected(): void
    {
        $this->pearl->update(['is_available' => false]);

        $this->expectExceptionMessage('Trân châu');

        app(OrderService::class)->placeByStaff($this->session, User::factory()->create(), [
            ['menu_item_id' => $this->tea->id, 'option_ids' => [$this->sizeM->id, $this->pearl->id], 'quantity' => 1],
        ]);
    }

    public function test_same_item_with_different_options_are_separate_cart_lines(): void
    {
        $cart = app(CartService::class);
        $cart->add($this->session, 'p', $this->tea->id, 1, [$this->sizeM->id]);
        $cart->add($this->session, 'p', $this->tea->id, 1, [$this->sizeL->id]);
        $cart->add($this->session, 'p', $this->tea->id, 2, [$this->sizeL->id]);

        $lines = $cart->lines($this->session, 'p');
        $this->assertCount(2, $lines);
        $this->assertSame(3, $lines[CartService::lineKey($this->tea->id, [$this->sizeL->id])]['quantity']);
    }

    public function test_customer_configures_options_in_menu(): void
    {
        $this->get('/t/'.$this->session->diningTable->qr_token);

        Livewire::withCookie('device_id', 'phone-1')
            ->test(MenuPage::class)
            ->call('add', $this->tea->id)
            ->assertSet('configuringId', $this->tea->id)
            ->assertSet('selectedOptions', [$this->sizeM->option_group_id => [$this->sizeM->id], $this->pearl->option_group_id => []])
            ->assertSee('Bắt buộc, chọn 1')
            ->call('toggleOption', $this->sizeL->option_group_id, $this->sizeL->id)
            ->call('toggleOption', $this->pearl->option_group_id, $this->pearl->id)
            ->call('toggleOption', $this->pearl->option_group_id, $this->pudding->id)
            ->call('changeConfigQuantity', 1)
            ->assertSee('104.000 ₫')
            ->call('addConfigured')
            ->assertSet('configuringId', null)
            ->assertSet('cartTotal', 104000)
            ->call('placeOrder');

        $item = $this->session->orderItems()->sole();
        $this->assertSame(52000, $item->unit_price);
        $this->assertSame(2, $item->quantity);
    }

    public function test_required_single_group_cannot_be_unselected(): void
    {
        $this->get('/t/'.$this->session->diningTable->qr_token);

        Livewire::test(MenuPage::class)
            ->call('add', $this->tea->id)
            ->call('toggleOption', $this->sizeM->option_group_id, $this->sizeM->id)
            ->assertSet('selectedOptions.'.$this->sizeM->option_group_id, [$this->sizeM->id]);
    }

    public function test_staff_orders_with_options_and_kitchen_sees_them(): void
    {
        $waiter = User::factory()->create();

        Livewire::actingAs($waiter)
            ->test(TableDetail::class, ['diningTable' => $this->session->diningTable])
            ->call('pick', $this->tea->id, 1)
            ->assertSet('configuringId', $this->tea->id)
            ->call('toggleOption', $this->sizeL->option_group_id, $this->sizeL->id)
            ->call('addConfigured')
            ->assertSee('40.000 ₫')
            ->call('submitStaffOrder');

        Livewire::actingAs(User::factory()->role(UserRole::Kitchen)->create())
            ->test(Board::class)
            ->assertSee('Trà sữa')
            ->assertSee('L')
            ->assertSet('totals', collect(['Trà sữa (L)' => 1]));

        $this->assertSame('Trà sữa (L)', app(BillingService::class)->summarize($this->session)->lines[0]['name']);
    }

    public function test_admin_creates_option_group(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateOptionGroup::class)
            ->fillForm([
                'name' => 'Đá',
                'min_select' => 1,
                'max_select' => 1,
                'menuItems' => [$this->tea->id],
                'options' => [
                    ['name' => 'Ít đá', 'price_delta' => 0, 'is_available' => true],
                    ['name' => 'Không đá', 'price_delta' => 0, 'is_available' => true],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $group = OptionGroup::firstWhere('name', 'Đá');
        $this->assertSame(['Ít đá', 'Không đá'], $group->options->pluck('name')->all());
        $this->assertTrue($this->tea->optionGroups()->whereKey($group->id)->exists());
        $this->actingAs(User::factory()->admin()->create())->get('/admin/option-groups')->assertOk()->assertSee('Bắt buộc, chọn 1');
    }
}
