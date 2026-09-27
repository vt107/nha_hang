<?php

use App\Http\Controllers\Admin\QrPrintController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Customer\QrEntryController;
use App\Http\Controllers\Staff\InvoicePrintController;
use App\Http\Controllers\Webhook\SePayWebhookController;
use App\Livewire\Customer\BillPage;
use App\Livewire\Customer\MenuPage;
use App\Livewire\Customer\OrdersPage;
use App\Livewire\Kitchen\Board as KitchenBoard;
use App\Livewire\Site\ReservationForm;
use App\Livewire\Staff\TableBoard;
use App\Livewire\Staff\TableDetail;
use App\Services\Menu\MenuCatalog;
use Illuminate\Support\Facades\Route;

// Website công khai
Route::get('/', fn (MenuCatalog $catalog) => view('public.home', ['categories' => $catalog->categories()]))->name('home');
Route::livewire('/dat-ban', ReservationForm::class)->name('reservations.create');

// Khách tại bàn (quét QR)
Route::get('/t/{token}', QrEntryController::class)->middleware('throttle:30,1')->name('qr.enter');

Route::middleware('table.session')->group(function () {
    Route::livewire('/menu', MenuPage::class)->name('customer.menu');
    Route::livewire('/mon-da-goi', OrdersPage::class)->name('customer.orders');
    Route::livewire('/thanh-toan', BillPage::class)->name('customer.bill');
});

// Webhook ngân hàng (SePay) báo tiền chuyển khoản về
Route::post('/webhooks/sepay', SePayWebhookController::class)->middleware('throttle:120,1')->name('webhooks.sepay');

// Đăng nhập nhân viên
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:5,1');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

// Nhân viên phục vụ (quản lý / admin cũng dùng được)
Route::middleware(['auth', 'role:admin,manager,waiter'])->prefix('staff')->name('staff.')->group(function () {
    Route::livewire('/', TableBoard::class)->name('tables');
    Route::livewire('/tables/{diningTable}', TableDetail::class)->name('tables.show');
    Route::get('/invoices/{invoice}/print', InvoicePrintController::class)->name('invoices.print');
});

// Bếp
Route::middleware(['auth', 'role:admin,manager,kitchen'])->group(function () {
    Route::livewire('/kitchen', KitchenBoard::class)->name('kitchen.board');
});

// Công cụ admin ngoài Filament
Route::middleware(['auth', 'can:access-admin'])->group(function () {
    Route::get('/qr/print', QrPrintController::class)->name('qr.print');
});
