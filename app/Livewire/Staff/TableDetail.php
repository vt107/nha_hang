<?php

namespace App\Livewire\Staff;

use App\Enums\BankTransactionStatus;
use App\Enums\OrderItemStatus;
use App\Enums\PaymentMethod;
use App\Enums\ReservationStatus;
use App\Enums\ServiceRequestStatus;
use App\Enums\TableSessionSource;
use App\Livewire\Concerns\ConfiguresMenuOptions;
use App\Livewire\Concerns\RunsBusinessActions;
use App\Models\BankTransaction;
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
use App\Services\Billing\PaymentLine;
use App\Services\Menu\MenuCatalog;
use App\Services\Ordering\CartService;
use App\Services\Ordering\OptionResolver;
use App\Services\Ordering\OrderItemService;
use App\Services\Ordering\OrderService;
use App\Services\Tables\TableSessionService;
use App\Support\Money;
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
    use ConfiguresMenuOptions;
    use RunsBusinessActions;

    public DiningTable $diningTable;

    public int $guestCount = 2;

    /**
     * Gọi món hộ khách, key = CartService::lineKey().
     *
     * @var array<string, array{menu_item_id: int, option_ids: list<int>, quantity: int, note: ?string}>
     */
    public array $picked = [];

    public string $search = '';

    public string $staffNote = '';

    public ?int $transferTo = null;

    public int|string|null $discount = 0;

    /** Tách hóa đơn theo món: chỉ thu các món được chọn. */
    public bool $splitMode = false;

    /** @var array<int, int> order_item_id => số phần */
    public array $splitSelection = [];

    /**
     * Các khoản khách trả; amount trống = phần còn lại (chỉ khoản cuối).
     *
     * @var list<array{method: string, amount: int|string|null, received: int|string|null, reference: string, bank_transaction_id: ?int}>
     */
    public array $payments = [];

    public ?int $lastInvoiceId = null;

    public function mount(DiningTable $diningTable): void
    {
        $this->diningTable = $diningTable;
        $this->guestCount = min(4, $diningTable->capacity);
        $this->resetPayments();
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

    /**
     * @return list<array{key: string, name: string, options: string, quantity: int, amount: int}>
     */
    #[Computed]
    public function pickedLines(): array
    {
        $items = app(MenuCatalog::class)->categories()->flatMap->menuItems->keyBy('id');

        return collect($this->picked)->map(function (array $line, string $key) use ($items) {
            $item = $items->get($line['menu_item_id']);
            $options = $item?->optionGroups->flatMap->options->whereIn('id', $line['option_ids']) ?? collect();

            return [
                'key' => $key,
                'name' => $item?->name ?? '?',
                'options' => $options->pluck('name')->join(', '),
                'quantity' => $line['quantity'],
                'amount' => (($item?->price ?? 0) + $options->sum('price_delta')) * $line['quantity'],
            ];
        })->values()->all();
    }

    #[Computed]
    public function pickedTotal(): int
    {
        return array_sum(array_column($this->pickedLines, 'amount'));
    }

    /** Tổng số phần đã chọn theo món, để hiện trên danh sách món. @return array<int, int> */
    #[Computed]
    public function pickedByItem(): array
    {
        return collect($this->picked)->groupBy('menu_item_id')->map->sum('quantity')->all();
    }

    #[Computed]
    public function summary(): ?BillSummary
    {
        return $this->tableSession
            ? app(BillingService::class)->summarize($this->tableSession, (int) $this->discount, $this->splitMode ? $this->splitSelection : null)
            : null;
    }

    /** Tổng còn phải thu của cả bàn (không tính giảm giá), hiện trên nút Thanh toán. */
    #[Computed]
    public function remainingTotal(): int
    {
        return $this->tableSession ? app(BillingService::class)->summarize($this->tableSession)->total : 0;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    #[Computed]
    public function unbilledItems(): Collection
    {
        return $this->tableSession?->orderItems()->unbilled()->orderBy('order_items.id')->get() ?? new Collection;
    }

    /**
     * @return Collection<int, Invoice>
     */
    #[Computed]
    public function sessionInvoices(): Collection
    {
        return $this->tableSession?->invoices()->with('payments')->latest('id')->get() ?? new Collection;
    }

    /**
     * Chuyển khoản báo về qua webhook đã khớp bàn nhưng chưa tự ghi nhận được.
     *
     * @return Collection<int, BankTransaction>
     */
    #[Computed]
    public function pendingTransfers(): Collection
    {
        return $this->tableSession
            ? BankTransaction::query()->where('table_session_id', $this->tableSession->id)->where('status', BankTransactionStatus::Matched)->latest('id')->get()
            : new Collection;
    }

    /** Tổng các khoản đã nhập số tiền; khoản cuối để trống sẽ nhận phần còn lại. */
    #[Computed]
    public function paymentsRemaining(): int
    {
        $total = $this->summary?->total ?? 0;
        $known = collect($this->payments)->sum(fn (array $row) => filled($row['amount']) ? (int) $row['amount'] : 0);

        return $total - $known;
    }

    #[Computed]
    public function transferQr(): ?string
    {
        $bin = (string) Setting::get('bank.bin');
        $account = (string) Setting::get('bank.account_number');

        if (! $this->summary?->total || blank($bin) || blank($account)) {
            return null;
        }

        return QrImage::dataUri(VietQr::payload($bin, $account, $this->summary->total, $this->tableSession->paymentCode()));
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

    /** Bấm +/- trên món: món có tùy chọn thì mở bảng chọn (khi +). */
    public function pick(int $menuItemId, int $delta): void
    {
        $item = app(MenuCatalog::class)->categories()->flatMap->menuItems->firstWhere('id', $menuItemId);

        if ($delta > 0 && $item?->optionGroups->isNotEmpty()) {
            $this->startConfiguring($menuItemId);

            return;
        }

        $this->changePicked(CartService::lineKey($menuItemId), $delta, $menuItemId);
    }

    public function changePicked(string $key, int $delta, ?int $menuItemId = null): void
    {
        $line = $this->picked[$key] ?? ['menu_item_id' => $menuItemId, 'option_ids' => [], 'quantity' => 0, 'note' => null];

        if ($line['menu_item_id'] === null) {
            return;
        }

        $line['quantity'] = min(99, max(0, $line['quantity'] + $delta));

        if ($line['quantity'] === 0) {
            unset($this->picked[$key]);
        } else {
            $this->picked[$key] = $line;
        }

        unset($this->pickedLines, $this->pickedTotal, $this->pickedByItem);
    }

    public function addConfigured(OptionResolver $resolver): void
    {
        $item = $this->configuringItem;

        if (! $item || ! $this->attempt(fn () => $resolver->resolve($item, $this->configuredOptionIds()) ?: true)) {
            return;
        }

        $key = CartService::lineKey($item->id, $this->configuredOptionIds());
        $this->picked[$key] = [
            'menu_item_id' => $item->id,
            'option_ids' => $this->configuredOptionIds(),
            'quantity' => min(99, ($this->picked[$key]['quantity'] ?? 0) + $this->configQuantity),
            'note' => filled($this->configNote) ? trim($this->configNote) : null,
        ];

        unset($this->pickedLines, $this->pickedTotal, $this->pickedByItem);
        $this->cancelConfiguring();
    }

    public function submitStaffOrder(OrderService $orders): void
    {
        if (! $this->tableSession) {
            return;
        }

        $lines = array_values($this->picked);

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

    public function toggleSplit(): void
    {
        $this->splitMode = ! $this->splitMode;
        $this->splitSelection = [];
        $this->resetPayments();
        $this->forgetBilling();
    }

    public function setSplitQuantity(int $itemId, int $quantity): void
    {
        $item = $this->unbilledItems->firstWhere('id', $itemId);

        if (! $item) {
            return;
        }

        $quantity = max(0, min($item->quantity, $quantity));

        if ($quantity === 0) {
            unset($this->splitSelection[$itemId]);
        } else {
            $this->splitSelection[$itemId] = $quantity;
        }

        $this->forgetBilling();
    }

    public function addPayment(string $method = 'cash'): void
    {
        $this->payments[] = ['method' => $method, 'amount' => null, 'received' => null, 'reference' => '', 'bank_transaction_id' => null];
        unset($this->paymentsRemaining);
    }

    public function removePayment(int $index): void
    {
        unset($this->payments[$index]);
        $this->payments = array_values($this->payments) ?: [];

        if ($this->payments === []) {
            $this->resetPayments();
        }

        unset($this->paymentsRemaining);
    }

    /** Chia đều hóa đơn cho N người (tiền mặt mặc định, sửa từng khoản được); phần lẻ cộng vào khoản đầu. */
    public function splitEvenly(int $people): void
    {
        $people = max(2, min(20, $people));
        $total = $this->summary?->total ?? 0;
        $share = intdiv($total, $people);

        $this->payments = [];

        for ($i = 0; $i < $people; $i++) {
            $this->addPayment();
            $this->payments[$i]['amount'] = $share + ($i === 0 ? $total - $share * $people : 0);
        }
    }

    /** Dùng khoản chuyển khoản webhook đã báo về làm khoản thanh toán. */
    public function useTransfer(int $transactionId): void
    {
        $transaction = $this->pendingTransfers->firstWhere('id', $transactionId);

        if (! $transaction) {
            return;
        }

        $this->payments = [[
            'method' => PaymentMethod::BankTransfer->value,
            'amount' => $transaction->amount,
            'received' => null,
            'reference' => (string) $transaction->reference_code,
            'bank_transaction_id' => $transaction->id,
        ]];

        if ($transaction->amount !== ($this->summary?->total ?? 0)) {
            $this->toast('Số tiền chuyển khác tổng hóa đơn: thêm khoản khác hoặc điều chỉnh giảm giá.', 'warning');
        }

        $this->dispatch('open-checkout');
        unset($this->paymentsRemaining);
    }

    public function checkout(BillingService $billing): void
    {
        $this->validate([
            'discount' => ['nullable', 'integer', 'min:0'],
            'payments' => ['required', 'array', 'min:1'],
            'payments.*.method' => ['required', 'in:'.implode(',', array_column(PaymentMethod::cases(), 'value'))],
            'payments.*.amount' => ['nullable', 'integer', 'min:1'],
            'payments.*.received' => ['nullable', 'integer', 'min:0'],
            'payments.*.reference' => ['nullable', 'string', 'max:100'],
        ], attributes: ['payments.*.amount' => 'số tiền', 'payments.*.received' => 'tiền khách đưa']);

        if (! $this->tableSession) {
            return;
        }

        $lines = array_map(function (array $row) {
            $method = PaymentMethod::from($row['method']);

            return new PaymentLine(
                $method,
                filled($row['amount']) ? (int) $row['amount'] : null,
                $method === PaymentMethod::Cash && filled($row['received']) ? (int) $row['received'] : null,
                $method === PaymentMethod::BankTransfer && filled($row['reference']) ? trim($row['reference']) : null,
                $row['bank_transaction_id'] ?? null,
            );
        }, $this->payments);

        $invoice = $this->attempt(fn () => $billing->checkout(
            $this->tableSession,
            auth()->user(),
            $lines,
            (int) $this->discount,
            $this->splitMode ? $this->splitSelection : null,
        ));

        if ($invoice) {
            $this->lastInvoiceId = $invoice->id;
            $this->reset('discount', 'splitMode', 'splitSelection');
            $this->resetPayments();
            $this->dispatch('close-checkout');
            $this->refreshTable();

            $this->toast($this->tableSession
                ? 'Đã thu hóa đơn tách, bàn còn '.Money::format($this->remainingTotal).' chưa thu'
                : 'Đã thanh toán, bàn đã trống', 'success');

            return;
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
            $this->freeTables, $this->lastInvoice, $this->sessionInvoices, $this->pendingTransfers,
        );
        $this->forgetBilling();
    }

    public function updatedDiscount(): void
    {
        $this->forgetBilling();
    }

    public function updatedPayments(): void
    {
        unset($this->paymentsRemaining);
    }

    private function forgetBilling(): void
    {
        unset($this->summary, $this->remainingTotal, $this->unbilledItems, $this->transferQr, $this->paymentsRemaining);
    }

    private function resetPayments(): void
    {
        $this->payments = [['method' => PaymentMethod::Cash->value, 'amount' => null, 'received' => null, 'reference' => '', 'bank_transaction_id' => null]];
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
