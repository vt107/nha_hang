<?php

namespace App\Livewire\Kitchen;

use App\Enums\OrderItemStatus;
use App\Livewire\Concerns\RunsBusinessActions;
use App\Models\MenuItem;
use App\Models\OrderItem;
use App\Models\Setting;
use App\Services\Ordering\OrderItemService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Màn hình bếp (không chia trạm): Đơn mới → Đang làm → Hoàn thành, làm theo từng món.
 */
#[Title('Bếp')]
#[Layout('layouts::app', ['bodyClass' => 'min-h-full bg-stone-950 text-stone-100 antialiased'])]
class Board extends Component
{
    use RunsBusinessActions;

    public string $menuSearch = '';

    /**
     * @return Collection<int, OrderItem>
     */
    #[Computed]
    public function items(): Collection
    {
        return OrderItem::query()
            ->onKitchenBoard()
            ->with(['order.tableSession.diningTable'])
            ->get();
    }

    /**
     * Tổng số phần cần làm theo món (Đơn mới + Đang làm), để bếp gom làm một lượt.
     *
     * @return \Illuminate\Support\Collection<string, int>
     */
    #[Computed]
    public function totals(): \Illuminate\Support\Collection
    {
        return $this->items
            ->whereIn('status', [OrderItemStatus::Queued, OrderItemStatus::Cooking])
            ->groupBy('item_name')
            ->map(fn ($items) => $items->sum('quantity'))
            ->sortDesc();
    }

    /**
     * @return Collection<int, MenuItem>
     */
    #[Computed]
    public function menuItems(): Collection
    {
        $search = Str::lower(Str::ascii(trim($this->menuSearch)));

        return MenuItem::query()
            ->visible()
            ->orderBy('is_available')
            ->orderBy('name')
            ->get()
            ->filter(fn (MenuItem $item) => $search === '' || str_contains(Str::lower(Str::ascii($item->name)), $search))
            ->values();
    }

    #[Computed]
    public function warnAfterMinutes(): int
    {
        return (int) Setting::get('kitchen.warn_after_minutes', 15);
    }

    public function start(int $itemId, OrderItemService $service): void
    {
        $this->move($itemId, OrderItemStatus::Cooking, $service);
    }

    public function finish(int $itemId, OrderItemService $service): void
    {
        $this->move($itemId, OrderItemStatus::Ready, $service);
    }

    public function startAllOf(string $itemName, OrderItemService $service): void
    {
        $this->items
            ->where('status', OrderItemStatus::Queued)
            ->where('item_name', $itemName)
            ->each(fn (OrderItem $item) => $this->attempt(fn () => $service->transition($item, OrderItemStatus::Cooking, auth()->user())));

        $this->refreshBoard();
    }

    public function cancel(int $itemId, ?string $reason, OrderItemService $service): void
    {
        $this->attempt(
            fn () => $service->transition(OrderItem::findOrFail($itemId), OrderItemStatus::Cancelled, auth()->user(), filled($reason) ? Str::limit($reason, 250) : 'Bếp hủy'),
            'Đã hủy món, nhân viên phục vụ sẽ được báo',
        );
        $this->refreshBoard();
    }

    public function toggleAvailable(int $menuItemId): void
    {
        $item = MenuItem::findOrFail($menuItemId);
        $item->update(['is_available' => ! $item->is_available]);

        $this->toast($item->is_available ? "{$item->name}: còn món" : "{$item->name}: đã báo hết món", $item->is_available ? 'success' : 'warning');
        unset($this->menuItems);
    }

    #[On('echo-private:kitchen,.kitchen.updated')]
    public function refreshBoard(): void
    {
        unset($this->items, $this->totals);
    }

    private function move(int $itemId, OrderItemStatus $to, OrderItemService $service): void
    {
        $this->attempt(fn () => $service->transition(OrderItem::findOrFail($itemId), $to, auth()->user()));
        $this->refreshBoard();
    }

    public function render(): View
    {
        return view('livewire.kitchen.board');
    }
}
