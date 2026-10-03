<?php

namespace Tests\Feature;

use App\Enums\TableSessionSource;
use App\Enums\UserRole;
use App\Filament\Pages\Auth\Login;
use App\Filament\Resources\Categories\Pages\EditCategory;
use App\Livewire\Kitchen\Board;
use App\Models\Area;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\User;
use App\Services\Tables\TableSessionService;
use App\Support\Demo\DemoMode;
use App\Support\Demo\DemoModeException;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Chế độ demo chỉ xem (DEMO_MODE=true). App phải boot với demo bật (middleware, guard SQL, route /demo),
 * guard SQL chỉ chặn request web nên test (chạy CLI) bật bằng DemoMode::forceGuard().
 */
class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $kitchen;

    protected function setUp(): void
    {
        $this->setDemoEnv('true');

        parent::setUp();

        $this->seed(SettingSeeder::class);
        $this->admin = User::factory()->admin()->create(['email' => 'admin@nhahang.test']);
        $this->kitchen = User::factory()->role(UserRole::Kitchen)->create(['email' => 'kitchen@nhahang.test']);
        User::factory()->role(UserRole::Waiter)->create(['email' => 'waiter@nhahang.test']);

        DemoMode::forceGuard();
    }

    protected function tearDown(): void
    {
        DemoMode::forceGuard(false);
        $this->setDemoEnv('false');

        parent::tearDown();
    }

    public function test_sql_writes_are_blocked_except_bypass(): void
    {
        try {
            Area::create(['name' => 'Tầng 3']);
            $this->fail('Lệnh ghi phải bị chặn');
        } catch (DemoModeException) {
        }

        $this->assertSame(0, Area::count());

        DemoMode::bypass(fn () => Area::create(['name' => 'Tầng 3']));
        $this->assertSame(1, Area::count());
    }

    public function test_widget_is_injected_and_logins_are_prefilled(): void
    {
        $this->get('/')->assertOk()->assertSee('id="dmw"', false)->assertSee('Đăng nhập với tài khoản này');
        $this->get('/login')->assertOk()->assertSee('value="waiter@nhahang.test"', false);
        $this->get('/login?demo=kitchen')->assertOk()->assertSee('value="kitchen@nhahang.test"', false);
        $this->get('/admin/login')->assertOk()->assertSee('id="dmw"', false);

        Livewire::test(Login::class)->assertSet('data.email', 'admin@nhahang.test');
        Livewire::withQueryParams(['demo' => 'manager'])->test(Login::class)->assertSet('data.email', 'manager@nhahang.test');
    }

    public function test_demo_switch_logs_out_and_opens_login_of_portal(): void
    {
        $this->actingAs($this->admin)
            ->get('/demo/switch/kitchen')
            ->assertRedirect(url('/login').'?demo=kitchen');

        $this->assertGuest();
        $this->get('/demo/switch/khong-co')->assertNotFound();
    }

    public function test_staff_login_still_works(): void
    {
        $this->post('/login', ['email' => 'kitchen@nhahang.test', 'password' => 'password'])
            ->assertRedirect(route('kitchen.board'));

        $this->assertAuthenticatedAs($this->kitchen);
        $this->assertNull($this->kitchen->fresh()->last_login_at);
    }

    public function test_post_forms_and_webhook_are_blocked(): void
    {
        $this->postJson('/webhooks/sepay', ['id' => 1, 'transferAmount' => 100000, 'transferType' => 'in'], [
            'Authorization' => 'Apikey '.config('services.sepay.webhook_key'),
        ])->assertForbidden()->assertJson(['message' => DemoMode::message()]);

        $this->actingAs($this->admin)
            ->from('/qr/print')
            ->post('/horizon/api/jobs/retry/1')
            ->assertRedirect('/qr/print')
            ->assertSessionHas('demo_blocked');

        $this->assertDatabaseCount('bank_transactions', 0);
    }

    public function test_filament_save_is_blocked_with_toast(): void
    {
        $category = DemoMode::bypass(fn () => Category::factory()->create(['name' => 'Khai vị']));

        $this->actingAs($this->admin);

        Livewire::test(EditCategory::class, ['record' => $category->getRouteKey()])
            ->fillForm(['name' => 'Đã sửa'])
            ->call('save')
            ->assertDispatched('demo-blocked');

        $this->assertSame('Khai vị', $category->fresh()->name);
    }

    public function test_kitchen_action_is_blocked_with_toast(): void
    {
        $item = DemoMode::bypass(fn () => MenuItem::factory()->create(['is_available' => true]));

        Livewire::actingAs($this->kitchen)
            ->test(Board::class)
            ->call('toggleAvailable', $item->id)
            ->assertDispatched('demo-blocked');

        $this->assertTrue($item->fresh()->is_available);
    }

    public function test_customer_qr_uses_open_session_without_writing(): void
    {
        [$busy, $free] = DemoMode::bypass(function () {
            $busy = DiningTable::factory()->create();
            app(TableSessionService::class)->openForTable($busy, TableSessionSource::Qr);

            return [$busy, DiningTable::factory()->create()];
        });

        $this->get("/t/{$busy->qr_token}")->assertRedirect(route('customer.menu'));
        $this->get('/menu')->assertOk()->assertSee('id="dmw"', false);

        // Bàn trống: không mở được phiên mới, hiện hướng dẫn thay vì trang lỗi.
        $this->get("/t/{$free->qr_token}")->assertOk()->assertSee('Bản demo chỉ xem');
        $this->assertSame(0, $free->sessions()->count());
    }

    private function setDemoEnv(string $value): void
    {
        putenv("DEMO_MODE={$value}");
        $_ENV['DEMO_MODE'] = $value;
        $_SERVER['DEMO_MODE'] = $value;
    }
}
