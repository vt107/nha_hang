<?php

namespace App\Livewire\Staff;

use App\Enums\OrderItemStatus;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Enums\ServiceRequestStatus;
use App\Models\Area;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Sơ đồ bàn theo khu vực, cập nhật realtime qua kênh private "staff".
 */
#[Title('Sơ đồ bàn')]
class TableBoard extends Component
{
    /**
     * @return Collection<int, Area>
     */
    #[Computed]
    public function areas(): Collection
    {
        return Area::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->with(['diningTables' => fn ($query) => $query->active()->with(['openSession' => fn ($query) => $query->withCount([
                'orders as pending_orders_count' => fn ($query) => $query->where('status', OrderStatus::Pending),
                'orderItems as ready_items_count' => fn ($query) => $query->where('order_items.status', OrderItemStatus::Ready),
                'serviceRequests as pending_requests_count' => fn ($query) => $query->where('status', ServiceRequestStatus::Pending),
            ])])])
            ->get();
    }

    /**
     * @return array{occupied: int, total: int, pending_orders: int, ready_items: int, requests: int}
     */
    #[Computed]
    public function stats(): array
    {
        $sessions = $this->areas->flatMap->diningTables->pluck('openSession')->filter();

        return [
            'occupied' => $sessions->count(),
            'total' => $this->areas->sum(fn (Area $area) => $area->diningTables->count()),
            'pending_orders' => $sessions->sum('pending_orders_count'),
            'ready_items' => $sessions->sum('ready_items_count'),
            'requests' => $sessions->sum('pending_requests_count'),
        ];
    }

    /**
     * @return Collection<int, Reservation>
     */
    #[Computed]
    public function upcomingReservations(): Collection
    {
        return Reservation::query()
            ->whereIn('status', ReservationStatus::upcoming())
            ->whereBetween('reserved_at', [now()->subHour(), now()->endOfDay()])
            ->with('diningTable')
            ->orderBy('reserved_at')
            ->get();
    }

    #[On('echo-private:staff,.session.updated')]
    #[On('echo-private:staff,.staff.alert')]
    public function refreshBoard(): void
    {
        unset($this->areas, $this->stats, $this->upcomingReservations);
    }

    public function render(): View
    {
        return view('livewire.staff.table-board');
    }
}
