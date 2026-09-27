<?php

namespace Tests\Feature;

use App\Enums\OrderItemStatus;
use App\Enums\TableSessionStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Menu\MenuCatalog;
use Filament\Facades\Filament;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_dining_table_gets_random_qr_token(): void
    {
        $table = DiningTable::factory()->create();

        $this->assertSame(32, strlen($table->qr_token));
        $this->assertStringEndsWith('/t/'.$table->qr_token, $table->qrUrl());

        $oldToken = $table->qr_token;
        $table->regenerateQrToken();

        $this->assertNotSame($oldToken, $table->fresh()->qr_token);
    }

    public function test_a_table_cannot_have_two_open_sessions(): void
    {
        $table = DiningTable::factory()->create();
        TableSession::factory()->for($table)->create();

        $this->expectException(UniqueConstraintViolationException::class);

        TableSession::factory()->for($table)->create();
    }

    public function test_a_table_can_have_many_closed_sessions_and_one_open(): void
    {
        $table = DiningTable::factory()->create();
        TableSession::factory()->for($table)->closed()->count(2)->create();
        $open = TableSession::factory()->for($table)->create();

        $this->assertTrue($table->openSession->is($open));
        $this->assertSame(TableSessionStatus::Open, $open->status);
        $this->assertSame(3, $table->sessions()->count());
    }

    public function test_closing_a_session_frees_the_table(): void
    {
        $table = DiningTable::factory()->create();
        $session = TableSession::factory()->for($table)->create();

        $session->update(['status' => TableSessionStatus::Closed, 'closed_at' => now()]);
        TableSession::factory()->for($table)->create();

        $this->assertSame(1, $table->sessions()->open()->count());
    }

    public function test_menu_hides_inactive_items_and_categories(): void
    {
        $visible = MenuItem::factory()->create();
        $soldOut = MenuItem::factory()->soldOut()->create();
        MenuItem::factory()->hidden()->create();
        MenuItem::factory()->for(Category::factory()->state(['is_active' => false]))->create();

        $this->assertEqualsCanonicalizing([$visible->id, $soldOut->id], MenuItem::visible()->pluck('id')->all());
        $this->assertSame([$visible->id], MenuItem::orderable()->pluck('id')->all());
    }

    public function test_order_item_keeps_price_snapshot(): void
    {
        $item = OrderItem::factory()->status(OrderItemStatus::Queued)->create(['quantity' => 3]);
        $snapshot = $item->unit_price;

        $item->menuItem->update(['price' => $snapshot + 10000]);

        $this->assertSame($snapshot * 3, $item->fresh()->line_total);
        $this->assertNotNull($item->queued_at);
    }

    public function test_settings_are_read_by_group_and_key(): void
    {
        Setting::set('billing.vat_percent', 8);

        $this->assertSame(8, Setting::get('billing.vat_percent'));
        $this->assertSame('x', Setting::get('billing.missing', 'x'));
    }

    public function test_only_admin_and_manager_can_access_filament(): void
    {
        $panel = Filament::getPanel('admin');

        $this->assertTrue(User::factory()->admin()->make()->canAccessPanel($panel));
        $this->assertTrue(User::factory()->role(UserRole::Manager)->make()->canAccessPanel($panel));
        $this->assertFalse(User::factory()->role(UserRole::Waiter)->make()->canAccessPanel($panel));
        $this->assertFalse(User::factory()->role(UserRole::Kitchen)->make()->canAccessPanel($panel));
        $this->assertFalse(User::factory()->admin()->make(['is_active' => false])->canAccessPanel($panel));
    }

    public function test_menu_catalog_survives_redis_round_trip(): void
    {
        $item = MenuItem::factory()->create(['price' => 65000]);
        $catalog = app(MenuCatalog::class);

        $catalog->categories();                       // ghi cache
        $cached = $catalog->categories()->first();    // đọc lại từ Redis

        $this->assertInstanceOf(Category::class, $cached);
        $this->assertTrue($cached->menuItems->first()->is($item));
        $this->assertSame(65000, $cached->menuItems->first()->price);
        $this->assertTrue($cached->menuItems->first()->is_available);
    }
}
