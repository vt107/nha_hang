<?php

namespace App\Livewire\Customer;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Livewire\Customer\Concerns\UsesTableSession;
use App\Models\Category;
use App\Models\MenuItem;
use App\Services\Menu\MenuCatalog;
use App\Services\Ordering\CartService;
use App\Services\Ordering\OrderService;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Menu')]
class MenuPage extends Component
{
    use UsesTableSession;

    public string $orderNote = '';

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Category>
     */
    #[Computed]
    public function categories()
    {
        return app(MenuCatalog::class)->categories();
    }

    /**
     * @return Collection<int, MenuItem>
     */
    #[Computed]
    public function itemsById(): Collection
    {
        return $this->categories->flatMap->menuItems->keyBy('id');
    }

    /**
     * @return list<array{id: int, name: string, price: int, quantity: int, note: ?string, amount: int, orderable: bool}>
     */
    #[Computed]
    public function cartLines(): array
    {
        $lines = [];

        foreach (app(CartService::class)->lines($this->tableSession, $this->deviceId) as $id => $line) {
            $item = $this->itemsById->get($id);

            $lines[] = [
                'id' => $id,
                'name' => $item?->name ?? 'Món không còn bán',
                'price' => $item?->price ?? 0,
                'quantity' => $line['quantity'],
                'note' => $line['note'],
                'amount' => ($item?->price ?? 0) * $line['quantity'],
                'orderable' => (bool) $item?->is_available,
            ];
        }

        return $lines;
    }

    #[Computed]
    public function cartCount(): int
    {
        return array_sum(array_column($this->cartLines, 'quantity'));
    }

    #[Computed]
    public function cartTotal(): int
    {
        return array_sum(array_column($this->cartLines, 'amount'));
    }

    public function add(int $menuItemId): void
    {
        $this->changeQuantity($menuItemId, +1);
    }

    public function decrement(int $menuItemId): void
    {
        $this->changeQuantity($menuItemId, -1);
    }

    public function remove(int $menuItemId): void
    {
        app(CartService::class)->setQuantity($this->tableSession, $this->deviceId, $menuItemId, 0);
        unset($this->cartLines);
    }

    public function updateNote(int $menuItemId, ?string $note): void
    {
        app(CartService::class)->setNote($this->tableSession, $this->deviceId, $menuItemId, $note);
        unset($this->cartLines);
    }

    public function placeOrder(OrderService $orders): void
    {
        if (! $this->ensureSessionOpen() || ! $this->throttle()) {
            return;
        }

        try {
            $order = $orders->placeFromCart($this->tableSession, $this->deviceId, trim($this->orderNote));
        } catch (BusinessException $e) {
            $this->toast($e->getMessage(), 'error');

            return;
        }

        $this->orderNote = '';

        session()->flash('toast', $order->status === OrderStatus::Pending
            ? 'Đã gửi order! Nhân viên sẽ xác nhận trong giây lát.'
            : 'Đã gửi order, bếp đã nhận món!');

        $this->redirectRoute('customer.orders', navigate: true);
    }

    private function changeQuantity(int $menuItemId, int $delta): void
    {
        $item = $this->itemsById->get($menuItemId);

        if ($delta > 0 && (! $item || ! $item->is_available)) {
            $this->toast('Món này tạm hết, bạn chọn món khác nhé.', 'warning');

            return;
        }

        $cart = app(CartService::class);
        $current = $cart->lines($this->tableSession, $this->deviceId)[$menuItemId]['quantity'] ?? 0;

        try {
            $cart->setQuantity($this->tableSession, $this->deviceId, $menuItemId, max(0, $current + $delta));
        } catch (BusinessException $e) {
            $this->toast($e->getMessage(), 'warning');
        }

        unset($this->cartLines);
    }

    public function render(): View
    {
        return view('livewire.customer.menu-page');
    }
}
