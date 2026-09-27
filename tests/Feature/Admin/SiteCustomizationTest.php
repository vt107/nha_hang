<?php

namespace Tests\Feature\Admin;

use App\Enums\TableSessionSource;
use App\Exceptions\BusinessException;
use App\Filament\Pages\ManageSettings;
use App\Livewire\Customer\MenuPage;
use App\Livewire\Site\ReservationForm;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Reservation;
use App\Models\Setting;
use App\Models\User;
use App\Services\Billing\BillingService;
use App\Services\Billing\PaymentLine;
use App\Services\Ordering\CartService;
use App\Services\Ordering\OrderService;
use App\Services\Tables\TableSessionService;
use Database\Seeders\SettingSeeder;
use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SiteCustomizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingSeeder::class);
    }

    public function test_admin_saves_branding_seo_and_uploads(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSettings::class)
            ->fillForm([
                'restaurant' => ['name' => 'Quán Ngon', 'slogan' => 'Ngon như mẹ nấu'],
                'brand' => ['color' => '#1d4ed8', 'logo' => UploadedFile::fake()->image('logo.png', 300, 120)],
                'seo' => ['title' => 'Quán Ngon - Món Việt Quận 1', 'keywords' => ['phở', 'bún chả'], 'google_analytics_id' => 'G-ABC123XYZ'],
                'home' => ['about_text' => "Đoạn 1\nĐoạn 2"],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Quán Ngon', Setting::get('restaurant.name'));
        $this->assertSame(['phở', 'bún chả'], Setting::get('seo.keywords'));
        $this->assertStringStartsWith('branding/', Setting::get('brand.logo'));
        Storage::disk('public')->assertExists(Setting::get('brand.logo'));
    }

    public function test_settings_validation(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ManageSettings::class)
            ->fillForm(['brand' => ['color' => 'đỏ'], 'seo' => ['google_analytics_id' => 'UA-123']])
            ->call('save')
            ->assertHasFormErrors(['brand.color', 'seo.google_analytics_id']);
    }

    public function test_home_page_uses_branding_and_seo(): void
    {
        Setting::set('restaurant.name', 'Quán Ngon');
        Setting::set('seo.title', 'Quán Ngon - Món Việt Quận 1');
        Setting::set('seo.description', 'Phở, bún chả, cơm tấm ngon nhất Quận 1');
        Setting::set('seo.keywords', ['phở', 'bún chả']);
        Setting::set('brand.color', '#1d4ed8');
        Setting::set('brand.logo', 'branding/logo.png');
        Setting::set('seo.google_analytics_id', 'G-ABC123XYZ');
        Setting::set('seo.google_site_verification', 'verify-123');
        Setting::set('home.hero_title', 'Hương vị Hà Nội');
        Setting::set('home.about_text', "Mở cửa từ 1998.\nGia truyền ba đời.");
        Setting::set('social.zalo', '0901 234 567');
        Setting::set('social.facebook', 'https://facebook.com/quanngon');

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Quán Ngon - Món Việt Quận 1</title>', false)
            ->assertSee('<meta name="description" content="Phở, bún chả, cơm tấm ngon nhất Quận 1">', false)
            ->assertSee('<meta name="keywords" content="phở, bún chả">', false)
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('property="og:title"', false)
            ->assertSee('--brand: #1d4ed8', false)
            ->assertSee('/storage/branding/logo.png', false)
            ->assertSee('gtag/js?id=G-ABC123XYZ', false)
            ->assertSee('content="verify-123"', false)
            ->assertSee('Hương vị Hà Nội')
            ->assertSee('Gia truyền ba đời.')
            ->assertSee('https://zalo.me/0901234567', false)
            ->assertSee('"@type":"Restaurant"', false);
    }

    public function test_default_color_is_not_overridden(): void
    {
        $this->get('/')->assertDontSee('--brand:', false);
    }

    public function test_internal_pages_are_noindex_and_indexing_can_be_disabled(): void
    {
        $table = DiningTable::factory()->create();
        $this->get('/t/'.$table->qr_token);
        $this->get('/menu')->assertSee('<meta name="robots" content="noindex, nofollow">', false)->assertDontSee('gtag/js', false);
        $this->get('/login')->assertSee('noindex, nofollow', false);

        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /admin')->assertSee('Sitemap:');
        $this->get('/sitemap.xml')->assertOk()->assertSee('/dat-ban');

        Setting::set('seo.allow_indexing', false);

        $this->get('/')->assertSee('noindex, nofollow', false);
        $this->get('/robots.txt')->assertSee("Disallow: /\n", false);
    }

    public function test_qr_ordering_can_be_disabled(): void
    {
        Setting::set('menu.welcome_message', 'Wifi: QuanNgon / 12345678');
        Setting::set('order.qr_ordering_enabled', false);
        $item = MenuItem::factory()->create();
        $table = DiningTable::factory()->create();
        $this->get('/t/'.$table->qr_token);

        Livewire::test(MenuPage::class)
            ->assertSee('Wifi: QuanNgon / 12345678')
            ->assertSee('gọi nhân viên')
            ->assertDontSee('aria-label="Thêm '.$item->name.'"', false)
            ->call('add', $item->id)
            ->assertSet('cartCount', 0);

        // Chặn cả ở service (request tự dựng)
        $session = $table->openSession;
        Setting::set('order.qr_ordering_enabled', true);
        app(CartService::class)->add($session, 'p', $item->id);
        Setting::set('order.qr_ordering_enabled', false);

        $this->expectException(BusinessException::class);
        app(OrderService::class)->placeFromCart($session, 'p');
    }

    public function test_staff_can_still_order_when_qr_ordering_disabled(): void
    {
        Setting::set('order.qr_ordering_enabled', false);
        $session = app(TableSessionService::class)->openForTable(DiningTable::factory()->create(), TableSessionSource::Staff);

        $order = app(OrderService::class)->placeByStaff($session, User::factory()->create(), [MenuItem::factory()->create()->id => ['quantity' => 1]]);

        $this->assertNotNull($order->id);
    }

    public function test_reservations_can_be_disabled(): void
    {
        Setting::set('reservation.enabled', false);

        $this->get('/')->assertDontSee('Đặt bàn ngay');
        $this->get('/dat-ban')->assertOk()->assertSee('tạm ngưng nhận đặt bàn');

        Livewire::test(ReservationForm::class)
            ->set('customer_name', 'A')->set('customer_phone', '0901234567')
            ->set('date', today()->addDay()->toDateString())->set('time', '19:00')
            ->call('submit');

        $this->assertSame(0, Reservation::count());
    }

    public function test_filament_uses_brand_name_and_color(): void
    {
        Setting::set('restaurant.name', 'Quán Ngon');
        Setting::set('brand.color', '#1d4ed8');

        $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk()->assertSee('Quán Ngon');
        $this->assertNotEquals(Color::Amber, Filament::getPanel('admin')->getColors()['primary']);
    }

    public function test_invoice_and_qr_print_use_settings(): void
    {
        Setting::set('invoice.tax_code', '0312345678');
        Setting::set('invoice.footer_note', 'Hẹn gặp lại quý khách!');
        Setting::set('restaurant.name', 'Quán Ngon');
        $admin = User::factory()->admin()->create();
        DiningTable::factory()->create();

        $this->actingAs($admin)->get(route('qr.print'))->assertSee('Quán Ngon');

        $session = app(TableSessionService::class)->openForTable(DiningTable::factory()->create(), TableSessionSource::Staff);
        $order = app(OrderService::class)->placeByStaff($session, $admin, [MenuItem::factory()->create()->id => ['quantity' => 1]]);
        $order->items()->update(['status' => 'served']);
        $invoice = app(BillingService::class)->checkout($session, $admin, [PaymentLine::cash()]);

        $this->get(route('staff.invoices.print', $invoice))->assertSee('MST: 0312345678')->assertSee('Hẹn gặp lại quý khách!');
    }
}
