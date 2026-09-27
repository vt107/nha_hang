<?php

namespace App\Livewire\Customer;

use App\Enums\OrderStatus;
use App\Exceptions\BusinessException;
use App\Livewire\Concerns\ConfiguresMenuOptions;
use App\Livewire\Customer\Concerns\UsesTableSession;
use App\Models\Category;
use App\Models\MenuItem;
use App\Services\Menu\MenuCatalog;
use App\Services\Ordering\CartService;
use App\Services\Ordering\OptionResolver;
use App\Services\Ordering\OrderService;
use App\Support\Site;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Menu')]
class MenuPage extends Component
{
    use ConfiguresMenuOptions;
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
     * @return list<array{key: string, id: int, name: string, options: string, price: int, quantity: int, note: ?string, amount: int, orderable: bool}>
     */
    #[Computed]
    public function cartLines(): array
    {
        $lines = [];

        foreach (app(CartService::class)->lines($this->tableSession, $this->deviceId) as $key => $line) {
            $item = $this->itemsById->get($line['menu_item_id']);
            $options = $item?->optionGroups->flatMap->options->whereIn('id', $line['option_ids']) ?? collect();
            $price = $item ? $item->price + $options->sum('price_delta') : 0;

            $lines[] = [
                'key' => $key,
                'id' => $line['menu_item_id'],
                'name' => $item?->name ?? 'Món không còn bán',
                'options' => $options->pluck('name')->join(', '),
                'price' => $price,
                'quantity' => $line['quantity'],
                'note' => $line['note'],
                'amount' => $price * $line['quantity'],
                'orderable' => (bool) $item?->is_available && $options->count() === count($line['option_ids']),
            ];
        }

        return $lines;
    }

    /**
     * Tổng số phần theo món (cộng mọi bộ tùy chọn), để hiện trên thẻ món.
     *
     * @return Collection<int, int>
     */
    #[Computed]
    public function quantitiesByItem(): Collection
    {
        return collect($this->cartLines)->groupBy('id')->map->sum('quantity');
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

    /** Bấm "+" trên thẻ món: món có tùy chọn thì mở bảng chọn, không thì thêm luôn. */
    public function add(int $menuItemId): void
    {
        if (! app(Site::class)->qrOrderingEnabled()) {
            $this->toast('Vui lòng gọi nhân viên để gọi món.', 'info');

            return;
        }

        $item = $this->itemsById->get($menuItemId);

        if (! $item || ! $item->is_available) {
            $this->toast('Món này tạm hết, bạn chọn món khác nhé.', 'warning');

            return;
        }

        if ($item->optionGroups->isNotEmpty()) {
            $this->startConfiguring($menuItemId);

            return;
        }

        $this->addToCart($menuItemId, 1);
    }

    /** Bớt 1 phần của món không có tùy chọn (thẻ món). */
    public function decrement(int $menuItemId): void
    {
        $this->changeLine(CartService::lineKey($menuItemId), -1);
    }

    public function addConfigured(OptionResolver $resolver): void
    {
        $item = $this->configuringItem;

        if (! $item) {
            return;
        }

        try {
            $resolver->resolve($item, $this->configuredOptionIds());
        } catch (BusinessException $e) {
            $this->toast($e->getMessage(), 'warning');

            return;
        }

        if ($this->addToCart($item->id, $this->configQuantity, $this->configuredOptionIds(), $this->configNote)) {
            $this->toast("Đã thêm {$item->name} vào giỏ", 'success');
            $this->cancelConfiguring();
        }
    }

    public function incrementLine(string $key): void
    {
        $this->changeLine($key, +1);
    }

    public function decrementLine(string $key): void
    {
        $this->changeLine($key, -1);
    }

    public function removeLine(string $key): void
    {
        app(CartService::class)->setQuantity($this->tableSession, $this->deviceId, $key, 0);
        $this->forgetCart();
    }

    public function updateNote(string $key, ?string $note): void
    {
        app(CartService::class)->setNote($this->tableSession, $this->deviceId, $key, $note);
        $this->forgetCart();
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

    /**
     * @param  list<int>  $optionIds
     */
    private function addToCart(int $menuItemId, int $quantity, array $optionIds = [], ?string $note = null): bool
    {
        try {
            app(CartService::class)->add($this->tableSession, $this->deviceId, $menuItemId, $quantity, $optionIds, $note);
        } catch (BusinessException $e) {
            $this->toast($e->getMessage(), 'warning');

            return false;
        } finally {
            $this->forgetCart();
        }

        return true;
    }

    private function changeLine(string $key, int $delta): void
    {
        $cart = app(CartService::class);
        $current = $cart->lines($this->tableSession, $this->deviceId)[$key]['quantity'] ?? 0;

        try {
            $cart->setQuantity($this->tableSession, $this->deviceId, $key, $current + $delta);
        } catch (BusinessException $e) {
            $this->toast($e->getMessage(), 'warning');
        }

        $this->forgetCart();
    }

    private function forgetCart(): void
    {
        unset($this->cartLines, $this->quantitiesByItem, $this->cartCount, $this->cartTotal);
    }

    public function render(): View
    {
        return view('livewire.customer.menu-page');
    }
}
