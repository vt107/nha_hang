@use('App\Enums\TableSessionStatus')

<div wire:poll.30s="refreshBoard">
    <x-staff.shell title="Sơ đồ bàn">
        <div class="mb-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div class="rounded-xl bg-white p-3 ring-1 ring-stone-200">
                <p class="text-xs text-stone-500">Bàn có khách</p>
                <p class="text-2xl font-bold">{{ $this->stats['occupied'] }}<span class="text-base font-medium text-stone-400">/{{ $this->stats['total'] }}</span></p>
            </div>
            <div @class(['rounded-xl p-3 ring-1', 'bg-red-50 ring-red-200' => $this->stats['pending_orders'], 'bg-white ring-stone-200' => ! $this->stats['pending_orders']])>
                <p class="text-xs text-stone-500">Order chờ duyệt</p>
                <p @class(['text-2xl font-bold', 'text-red-600' => $this->stats['pending_orders']])>{{ $this->stats['pending_orders'] }}</p>
            </div>
            <div @class(['rounded-xl p-3 ring-1', 'bg-sky-50 ring-sky-200' => $this->stats['ready_items'], 'bg-white ring-stone-200' => ! $this->stats['ready_items']])>
                <p class="text-xs text-stone-500">Món xong chờ bưng</p>
                <p @class(['text-2xl font-bold', 'text-sky-700' => $this->stats['ready_items']])>{{ $this->stats['ready_items'] }}</p>
            </div>
            <div @class(['rounded-xl p-3 ring-1', 'bg-amber-50 ring-amber-200' => $this->stats['requests'], 'bg-white ring-stone-200' => ! $this->stats['requests']])>
                <p class="text-xs text-stone-500">Khách gọi / thanh toán</p>
                <p @class(['text-2xl font-bold', 'text-amber-700' => $this->stats['requests']])>{{ $this->stats['requests'] }}</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_18rem]">
            <div class="space-y-6">
                @foreach ($this->areas as $area)
                    <section wire:key="area-{{ $area->id }}">
                        <h2 class="mb-2 font-bold text-stone-700">{{ $area->name }}</h2>
                        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 xl:grid-cols-5">
                            @foreach ($area->diningTables as $table)
                                @php($session = $table->openSession)
                                <a href="{{ route('staff.tables.show', $table) }}" wire:navigate wire:key="table-{{ $table->id }}"
                                    @class([
                                        'relative flex min-h-28 flex-col justify-between rounded-2xl p-3 ring-2 transition active:scale-[.98]',
                                        'bg-white ring-stone-200 hover:ring-stone-300' => ! $session,
                                        'bg-emerald-50 ring-emerald-300' => $session?->status === TableSessionStatus::Open,
                                        'bg-amber-50 ring-amber-400' => $session?->status === TableSessionStatus::PaymentRequested,
                                    ])>
                                    <div class="flex items-start justify-between gap-2">
                                        <span class="text-xl font-extrabold">{{ $table->code }}</span>
                                        <span class="text-xs text-stone-500">{{ $table->capacity }} ghế</span>
                                    </div>
                                    @if ($session)
                                        <div class="space-y-1">
                                            <p class="text-xs text-stone-600">
                                                {{ $session->status->getLabel() }} · {{ $session->opened_at->diffForHumans(short: true, syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) }}
                                            </p>
                                            <div class="flex flex-wrap gap-1">
                                                @if ($session->pending_orders_count)
                                                    <span class="rounded-full bg-red-600 px-2 py-0.5 text-xs font-bold text-white">{{ $session->pending_orders_count }} order chờ</span>
                                                @endif
                                                @if ($session->ready_items_count)
                                                    <span class="rounded-full bg-sky-600 px-2 py-0.5 text-xs font-bold text-white">{{ $session->ready_items_count }} món xong</span>
                                                @endif
                                                @if ($session->pending_requests_count)
                                                    <span class="rounded-full bg-amber-500 px-2 py-0.5 text-xs font-bold text-white">🔔 {{ $session->pending_requests_count }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <p class="text-sm text-stone-400">Trống</p>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            <aside class="space-y-3">
                <h2 class="font-bold text-stone-700">Đặt bàn hôm nay</h2>
                @forelse ($this->upcomingReservations as $reservation)
                    <div wire:key="res-{{ $reservation->id }}" class="rounded-xl bg-white p-3 text-sm ring-1 ring-stone-200">
                        <div class="flex items-center justify-between">
                            <span class="font-bold">{{ $reservation->reserved_at->format('H:i') }}</span>
                            <x-status-pill :status="$reservation->status" />
                        </div>
                        <p class="mt-1 font-medium">{{ $reservation->customer_name }} · {{ $reservation->party_size }} người</p>
                        <p class="text-stone-500">{{ $reservation->customer_phone }} · {{ $reservation->diningTable?->code ?? 'Chưa gán bàn' }}</p>
                        @if ($reservation->note)
                            <p class="mt-1 text-xs text-stone-500">“{{ $reservation->note }}”</p>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-stone-500">Không có đặt bàn nào.</p>
                @endforelse
            </aside>
        </div>
    </x-staff.shell>
</div>
