<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\Categories\Pages\CreateCategory;
use App\Filament\Resources\DiningTables\Pages\ListDiningTables;
use App\Filament\Resources\MenuItems\Pages\CreateMenuItem;
use App\Filament\Resources\MenuItems\Pages\ListMenuItems;
use App\Filament\Resources\Reservations\Pages\ListReservations;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\User;
use App\Services\Menu\MenuCatalog;
use Database\Seeders\SettingSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function adminUrls(): array
    {
        return [
            'dashboard' => ['/admin'],
            'danh mục' => ['/admin/categories'],
            'món' => ['/admin/menu-items'],
            'thêm món' => ['/admin/menu-items/create'],
            'khu vực' => ['/admin/areas'],
            'bàn' => ['/admin/dining-tables'],
            'nhân viên' => ['/admin/users'],
            'đặt bàn' => ['/admin/reservations'],
            'cài đặt' => ['/admin/settings'],
            'size / topping' => ['/admin/option-groups'],
            'chuyển khoản' => ['/admin/bank-transactions'],
            'hóa đơn' => ['/admin/invoices'],
        ];
    }

    #[DataProvider('adminUrls')]
    public function test_admin_pages_render(string $url): void
    {
        DiningTable::factory()->count(2)->create();
        MenuItem::factory()->count(2)->create();
        Reservation::factory()->create();

        $this->actingAs(User::factory()->admin()->create())->get($url)->assertOk();
    }

    public function test_waiter_and_kitchen_cannot_open_admin(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Waiter)->create())->get('/admin')->assertForbidden();
        $this->actingAs(User::factory()->role(UserRole::Kitchen)->create())->get('/admin/menu-items')->assertForbidden();
    }

    public function test_manager_cannot_open_settings_or_edit_admins(): void
    {
        $manager = User::factory()->role(UserRole::Manager)->create();
        $admin = User::factory()->admin()->create();
        $waiter = User::factory()->create();

        $this->actingAs($manager);

        $this->get('/admin/settings')->assertForbidden();
        $this->get("/admin/users/{$admin->id}/edit")->assertForbidden();
        $this->get("/admin/users/{$waiter->id}/edit")->assertOk();
    }

    public function test_manager_can_only_create_waiter_or_kitchen_accounts(): void
    {
        $this->actingAs(User::factory()->role(UserRole::Manager)->create());

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'X', 'email' => 'x@test.vn', 'password' => 'password123', 'role' => 'admin'])
            ->call('create')
            ->assertHasFormErrors(['role']);

        Livewire::test(CreateUser::class)
            ->fillForm(['name' => 'Bếp 2', 'email' => 'bep2@test.vn', 'password' => 'password123', 'role' => 'kitchen'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(UserRole::Kitchen, User::firstWhere('email', 'bep2@test.vn')->role);
    }

    public function test_editing_user_without_password_keeps_old_password(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $waiter = User::factory()->create();
        $hash = $waiter->password;

        Livewire::test(EditUser::class, ['record' => $waiter->getRouteKey()])
            ->fillForm(['name' => 'Tên mới', 'password' => ''])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Tên mới', $waiter->fresh()->name);
        $this->assertSame($hash, $waiter->fresh()->password);
    }

    public function test_create_menu_item_and_menu_cache_is_flushed(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $category = Category::factory()->create();
        $catalog = app(MenuCatalog::class);

        $this->assertCount(0, $catalog->categories());

        Livewire::test(CreateMenuItem::class)
            ->fillForm([
                'name' => 'Phở bò',
                'slug' => 'pho-bo',
                'category_id' => $category->id,
                'price' => 65000,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(['Phở bò'], $catalog->categories()->first()->menuItems->pluck('name')->all());
    }

    public function test_sold_out_bulk_action(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $items = MenuItem::factory()->count(2)->create();

        Livewire::test(ListMenuItems::class)
            ->selectTableRecords($items->pluck('id')->all())
            ->callAction(TestAction::make('markSoldOut')->table()->bulk());

        $this->assertSame(0, MenuItem::where('is_available', true)->count());
    }

    public function test_category_slug_is_generated_from_vietnamese_name(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(CreateCategory::class)
            ->fillForm(['name' => 'Đồ uống'])
            ->assertSchemaStateSet(['slug' => 'do-uong']);
    }

    public function test_regenerate_qr_token(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $table = DiningTable::factory()->create();
        $old = $table->qr_token;

        Livewire::test(ListDiningTables::class)
            ->callAction(TestAction::make('regenerateQr')->table($table));

        $this->assertNotSame($old, $table->fresh()->qr_token);
    }

    public function test_qr_print_page_lists_tables_for_admin_only(): void
    {
        $tables = DiningTable::factory()->count(2)->create();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('qr.print', ['ids' => $tables->first()->id]))
            ->assertOk()
            ->assertSee($tables->first()->displayName())
            ->assertDontSee($tables->last()->displayName())
            ->assertSee('data:image/svg+xml;base64', false);

        $this->actingAs(User::factory()->create())->get(route('qr.print'))->assertForbidden();
    }

    public function test_settings_page_saves_values(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSettings::class)
            ->fillForm([
                'restaurant' => ['phone' => '0901234567'],
                'billing' => ['vat_percent' => '8'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('0901234567', Setting::get('restaurant.phone'));
        $this->assertSame(8, Setting::get('billing.vat_percent'));
    }

    public function test_confirm_reservation_action(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $reservation = Reservation::factory()->create();

        Livewire::test(ListReservations::class)
            ->callAction(TestAction::make('confirm')->table($reservation));

        $this->assertSame('confirmed', $reservation->fresh()->status->value);
    }
}
