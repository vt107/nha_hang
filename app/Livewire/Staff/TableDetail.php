<?php

namespace App\Livewire\Staff;

use App\Enums\OrderItemStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\TableSessionSource;
use App\Livewire\Concerns\RunsBusinessActions;
use App\Models\DiningTable;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Reservation;
use App\Models\ServiceRequest;
use App\Models\Setting;
use App\Models\TableSession;
use App\Services\Billing\BillingService;
use App\Services\Billing\BillSummary;
use App\Services\Menu\MenuCatalog;
use App\Services\Ordering\OrderItemService;
use App\Services\Ordering\OrderService;
use App\Services\Tables\TableSessionService;
use App\Support\QrImage;
use App\Support\VietQr;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Nhân viên xử lý một bàn: mở bàn, duyệt order, bưng món, gọi món hộ, chuyển bàn, thu tiền.
 */
class TableDetail extends Component
{
    use RunsBusinessActions;

    public DiningTable $diningTable;

    public int $guestCount = 2;

    /** Gọi món hộ khách: menu_item_id => số lượng. @var array<int, int> */
    public array $picked = [];

    public string $search = '';

    public string $staffNote = '';

    public ?int $transferTo = null;

    public int|string|null $discount = 0;

    public string $method = 'cash';

    public int|string|null $received = null;

    public string $reference = '';

    public ?int $lastInvoiceId = null;

    public function mount(DiningTable $diningTable): void
    {
        $this->diningTable = $diningTable;
        $this->guestCount = min(4, $diningTable->capacity);
    }

    #[Computed]
    public function tableSession(): ?TableSession
    {
        return $this->diningTable->openSession()->with('reservation')->first();
    }

    /**
     * @return Collection<int, Order>
     */
    #[Computed]
    public function orders(): Collection
    {
        return $this->tableSession?->orders()->with(['items', 'creator'])->latest('id')->get() ?? new Collection;
    }

    /**
     * @return Collection<int, ServiceRequest>
     */
    #[Computed]
    public function pendingRequests(): Collection
    {
        return $this->tableSession?->serviceRequests()->where('status', ServiceRequestStatus::Pending)->oldest()->get() ?? new Collection;
    }

    #[Computed]
    public function readyCount(): int
    {
        return $this->orders->flatMap->items->where('status', OrderItemStatus::Ready)->count();
    }

    #[Computed]
    public function unfinishedCount(): int
    {
        return $this->orders->flatMap->items
            ->whereIn('status', [OrderItemStatus::Queued, OrderItemStatus::Cooking, OrderItemStatus::Ready])
            ->count();
    }

    /**
     * @return Collection<int, DiningTable>
     */
    #[Computed]
    public function freeTables(): Collection
    {
        return DiningTable::query()
            ->active()
            ->whereKeyNot($this->diningTable->id)
            ->whereDoesntHave('openSession')
            ->orderBy('code')
            ->get();
    }

    /**
     * @return Collection<int, Reservation>
     */
    #[Computed]
    public function reservationsToday(): Collection
    {
        return $this->diningTable->reservations()
            ->whereIn('status', ReservationStatus::upcoming())
            ->whereDate('reserved_at', today())
            ->orderBy('reserved_at')
            ->get();
    }

    #[Computed]
    public function menu(): \Illuminate\Support\Collection
    {
        $search = Str::lower(Str::ascii(trim($this->search)));

        return app(MenuCatalog::class)->categories()
            ->map(fn ($category) => [
                'name' => $category->name,
                'items' => $category->menuItems->filter(fn ($item) => $search === ''
                    || str_contains(Str::lower(Str::ascii($item->name)), $search)),
            ])
            ->filter(fn ($category) => $category['items']->isNotEmpty());
    }

    #[Computed]
    public function pickedTotal(): int
    {
        $items = app(MenuCatalog::class)->categories()->flatMap->menuItems->keyBy('id');

        return collect($this->picked)->sum(fn (int $qty, int $id) => ($items[$id]->price ?? 0) * $qty);
    }

    #[Computed]
    public function summary(): ?BillSummary
    {
        return $this->tableSession ? app(BillingService::class)->summarize($this->tableSession, (int) $this->discount) : null;
    }

    #[Computed]
    public function transferQr(): ?string
    {
        $bin = (string) Setting::get('bank.bin');
        $account = (string) Setting::get('bank.account_number');

        if (! $this->summary?->total || blank($bin) || blank($account)) {
            return null;
        }

        return QrImage::dataUri(VietQr::payload($bin, $account, $this->summary->total, VietQr::cleanDescription($this->tableSession->code)));
    }

    #[Computed]
    public function lastInvoice(): ?Invoice
    {
        return $this->lastInvoiceId ? Invoice::with('payments')->find($this->lastInvoiceId) : null;
    }

    public function openTable(TableSessionService $sessions): void
    {
        $this->validate(['guestCount' => ['required', 'integer', 'min:1', 'max:100']]);

        $this->attempt(
            fn () => $sessions->openForTable($this->diningTable, TableSessionSource::Staff, auth()->user(), guestCount: $this->guestCount),
            "Đã mở {$this->diningTable->displayName()}",
        );

        $this->lastInvoiceId = null;
        $this->refreshTable();
    }

    public function confirmOrder(int $orderId, OrderService $orders): void
    {
        $this->attempt(fn () => $orders->confirm($this->findOrder($orderId), auth()->user()), 'Đã xác nhận, món đã chuyển xuống bếp');
        $this->refreshTable();
    }

    public function rejectOrder(int $orderId, ?string $reason, OrderService $orders): void
    {
        $this->attempt(fn () => $orders->reject($this->findOrder($orderId), auth()->user(), filled($reason) ? Str::limit($reason, 250) : null), 'Đã từ chối order');
        $this->refreshTable();
    }

    public function markServed(int $itemId, OrderItemService $items): void
    {
        $this->attempt(fn () => $items->transition($this->findItem($itemId), OrderItemStatus::Served, auth()->user()));
        $this->refreshTable();
    }

    public function serveAllReady(OrderItemService $items): void
    {
        $this->orders->flatMap->items
            ->where('status', OrderItemStatus::Ready)
            ->each(fn ($item) => $this->attempt(fn () => $items->transition($item, OrderItemStatus::Served, auth()->user())));

        $this->toast('Đã bưng tất cả món xong', 'success');
        $this->refreshTable();
    }

    public function cancelItem(int $itemId, ?string $reason, OrderItemService $items): void
    {
        $this->attempt(
            fn () => $items->transition($this->findItem($itemId), OrderItemStatus::Cancelled, auth()->user(), filled($reason) ? Str::limit($reason, 250) : null),
            'Đã hủy món',
        );
        $this->refreshTable();
    }

    public function resolveRequest(int $requestId, TableSessionService $sessions): void
    {
        $request = $this->tableSession?->serviceRequests()->findOrFail($requestId);
        $sessions->resolveServiceRequest($request, auth()->user());
        $this->refreshTable();
    }

    public function pick(int $menuItemId, int $delta): void
    {
        $quantity = max(0, ($this->picked[$menuItemId] ?? 0) + $delta);

        if ($quantity === 0) {
            unset($this->picked[$menuItemId]);
        } else {
            $this->picked[$menuItemId] = min($quantity, 99);
        }
    }

    public function submitStaffOrder(OrderService $orders): void
    {
        if (! $this->tableSession) {
            return;
        }

        $lines = collect($this->picked)->map(fn (int $qty) => ['quantity' => $qty])->all();

        $order = $this->attempt(
            fn () => $orders->placeByStaff($this->tableSession, auth()->user(), $lines, trim($this->staffNote)),
            'Đã gửi món xuống bếp',
        );

        if ($order) {
            $this->reset('picked', 'staffNote', 'search');
            $this->dispatch('close-picker');
        }

        $this->refreshTable();
    }

    public function transfer(TableSessionService $sessions): void
    {
        $target = $this->freeTables->firstWhere('id', $this->transferTo);

        if (! $this->tableSession || ! $target) {
            $this->toast('Hãy chọn bàn trống để chuyển.', 'warning');

            return;
        }

        if ($this->attempt(fn () => $sessions->transfer($this->tableSession, $target), "Đã chuyển sang {$target->displayName()}")) {
            $this->redirectRoute('staff.tables.show', $target, navigate: true);
        }
    }

    public function checkout(BillingService $billing): void
    {
        $this->validate([
            'discount' => ['nullable', 'integer', 'min:0'],
            'method' => ['required', 'in:'.implode(',', array_column(PaymentMethod::cases(), 'value'))],
            'received' => ['nullable', 'integer', 'min:0'],
            'reference' => ['nullable', 'string', 'max:100'],
        ]);

        if (! $this->tableSession) {
            return;
        }

        $method = PaymentMethod::from($this->method);

        $invoice = $this->attempt(fn () => $billing->checkout(
            $this->tableSession,
            auth()->user(),
            $method,
            (int) $this->discount,
            $method === PaymentMethod::Cash && filled($this->received) ? (int) $this->received : null,
            $method === PaymentMethod::BankTransfer && filled($this->reference) ? trim($this->reference) : null,
        ), 'Đã thanh toán, bàn đã trống');

        if ($invoice) {
            $this->lastInvoiceId = $invoice->id;
            $this->reset('discount', 'received', 'reference', 'method');
            $this->dispatch('close-checkout');
        }

        $this->refreshTable();
    }

    public function closeWithoutPayment(TableSessionService $sessions): void
    {
        if ($this->tableSession) {
            $this->attempt(fn () => $sessions->closeWithoutPayment($this->tableSession, auth()->user()), 'Đã đóng bàn');
        }

        $this->refreshTable();
    }

    #[On('echo-private:staff,.session.updated')]
    public function refreshTable(): void
    {
        unset(
            $this->tableSession, $this->orders, $this->pendingRequests, $this->readyCount, $this->unfinishedCount,
            $this->freeTables, $this->summary, $this->transferQr, $this->lastInvoice,
        );
    }

    private function findOrder(int $orderId): Order
    {
        return $this->tableSession?->orders()->findOrFail($orderId) ?? abort(404);
    }

    private function findItem(int $itemId): OrderItem
    {
        return $this->tableSession?->orderItems()->where('order_items.id', $itemId)->firstOrFail() ?? abort(404);
    }

    public function render(): View
    {
        return view('livewire.staff.table-detail')->title($this->diningTable->displayName());
    }
}
