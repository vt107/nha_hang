<?php

namespace App\Livewire\Customer;

use App\Enums\OrderStatus;
use App\Livewire\Customer\Concerns\UsesTableSession;
use App\Models\Order;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Món cả bàn đã gọi và trạng thái (realtime). Order gửi từ điện thoại này được đánh dấu "Của bạn".
 */
#[Title('Món đã gọi')]
class OrdersPage extends Component
{
    use UsesTableSession;

    public function mount(): void
    {
        if ($message = session('toast')) {
            $this->toast($message, 'success');
        }
    }

    /**
     * @return Collection<int, Order>
     */
    #[Computed]
    public function orders(): Collection
    {
        return $this->tableSession->orders()->with('items')->latest('id')->get();
    }

    #[Computed]
    public function pendingCount(): int
    {
        return $this->orders->where('status', OrderStatus::Pending)->count();
    }

    #[On('echo:table-session.{sessionToken},.session.updated')]
    public function refreshFromBroadcast(): void
    {
        unset($this->tableSession, $this->orders);
        $this->ensureSessionOpen();
    }

    public function render(): View
    {
        return view('livewire.customer.orders-page');
    }
}
