@use('App\Enums\OrderItemStatus')
@php
    $columns = [
        ['status' => OrderItemStatus::Queued, 'title' => 'Đơn mới', 'accent' => 'border-sky-500', 'badge' => 'bg-sky-500'],
        ['status' => OrderItemStatus::Cooking, 'title' => 'Đang làm', 'accent' => 'border-amber-500', 'badge' => 'bg-amber-500'],
        ['status' => OrderItemStatus::Ready, 'title' => 'Hoàn thành', 'accent' => 'border-emerald-500', 'badge' => 'bg-emerald-500'],
    ];
@endphp

<div wire:poll.30s="refreshBoard" x-data="{ soldOut: false }" x-init="window.listenForAlerts?.('kitchen')" class="flex min-h-screen flex-col">
    <header class="flex items-center gap-3 border-b border-stone-800 px-4 py-3">
        <h1 class="text-xl font-extrabold tracking-tight">🔥 Bếp</h1>
        <span class="text-sm text-stone-400" x-data="{ now: '' }" x-init="const t = () => now = new Date().toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' }); t(); setInterval(t, 10000)" x-text="now"></span>
        <div class="no-scrollbar flex flex-1 gap-2 overflow-x-auto">
            @foreach ($this->totals as $name => $qty)
                <button type="button" wire:click="startAllOf({{ \Illuminate\Support\Js::from($name) }})" wire:confirm="Bắt đầu làm tất cả {{ $name }} đang chờ?"
                    class="shrink-0 rounded-full bg-stone-800 px-3 py-1 text-sm hover:bg-stone-700" title="Bắt đầu làm tất cả">
                    <span class="font-bold text-amber-400">{{ $qty }}</span> {{ $name }}
                </button>
            @endforeach
        </div>
        <button type="button" x-on:click="soldOut = true" class="shrink-0 rounded-lg bg-stone-800 px-3 py-2 text-sm font-semibold hover:bg-stone-700">Báo hết món</button>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button class="rounded-lg px-2 py-2 text-sm text-stone-400 hover:text-white">Đăng xuất</button>
        </form>
    </header>

    <div class="grid flex-1 gap-3 p-3 md:grid-cols-3">
        @foreach ($columns as $column)
            @php($items = $this->items->where('status', $column['status']))
            <section class="flex min-h-0 flex-col rounded-2xl bg-stone-900">
                <h2 class="flex items-center justify-between border-b-4 {{ $column['accent'] }} px-4 py-3 text-lg font-bold">
                    {{ $column['title'] }}
                    <span class="rounded-full {{ $column['badge'] }} px-2.5 text-base text-stone-950">{{ $items->sum('quantity') }}</span>
                </h2>
                <div class="flex-1 space-y-2 overflow-y-auto p-2">
                    @forelse ($items as $item)
                        @php($since = ($column['status'] === OrderItemStatus::Cooking ? $item->cooking_at : ($column['status'] === OrderItemStatus::Ready ? $item->ready_at : $item->queued_at)) ?? $item->created_at)
                        <article wire:key="k-{{ $item->id }}"
                            x-data="{ minutes: 0, tick() { this.minutes = Math.max(0, Math.floor((Date.now() / 1000 - {{ $since->timestamp }}) / 60)) } }"
                            x-init="tick(); setInterval(() => tick(), 15000)"
                            :class="minutes >= {{ $this->warnAfterMinutes }} && {{ $column['status'] === OrderItemStatus::Ready ? 'false' : 'true' }} ? 'ring-2 ring-red-500 bg-red-950/40' : 'bg-stone-800'"
                            class="rounded-xl p-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="text-2xl font-extrabold leading-tight">
                                        <span class="text-amber-400">{{ $item->quantity }}×</span> {{ $item->item_name }}
                                    </p>
                                    @if ($item->note)
                                        <p class="mt-1 rounded bg-yellow-300 px-2 py-0.5 text-sm font-bold text-stone-950">⚠ {{ $item->note }}</p>
                                    @endif
                                    @if ($item->order->note)
                                        <p class="mt-1 text-sm text-stone-400">Order: “{{ $item->order->note }}”</p>
                                    @endif
                                </div>
                                <div class="shrink-0 text-right">
                                    <p class="rounded-lg bg-stone-950 px-2 py-1 text-lg font-extrabold">{{ $item->order->tableSession->diningTable->code }}</p>
                                    <p class="mt-1 text-sm text-stone-400"><span x-text="minutes"></span> phút</p>
                                </div>
                            </div>
                            <div class="mt-3 flex gap-2">
                                @if ($column['status'] === OrderItemStatus::Queued)
                                    <button type="button" wire:click="start({{ $item->id }})" class="flex-1 rounded-lg bg-amber-500 py-3 text-base font-bold text-stone-950 active:scale-[.98]">Bắt đầu</button>
                                    <button type="button"
                                        x-on:click="const r = prompt('Lý do hủy (báo cho phục vụ):', 'Hết nguyên liệu'); if (r !== null) $wire.cancel({{ $item->id }}, r)"
                                        class="rounded-lg bg-stone-700 px-3 text-sm font-semibold">Hủy</button>
                                @elseif ($column['status'] === OrderItemStatus::Cooking)
                                    <button type="button" wire:click="finish({{ $item->id }})" class="flex-1 rounded-lg bg-emerald-500 py-3 text-base font-bold text-stone-950 active:scale-[.98]">Xong</button>
                                    <button type="button"
                                        x-on:click="const r = prompt('Lý do hủy (báo cho phục vụ):', 'Hết nguyên liệu'); if (r !== null) $wire.cancel({{ $item->id }}, r)"
                                        class="rounded-lg bg-stone-700 px-3 text-sm font-semibold">Hủy</button>
                                @else
                                    <p class="flex-1 rounded-lg bg-stone-700/60 py-2 text-center text-sm text-stone-300">Chờ phục vụ mang ra</p>
                                @endif
                            </div>
                        </article>
                    @empty
                        <p class="py-10 text-center text-stone-500">Không có món.</p>
                    @endforelse
                </div>
            </section>
        @endforeach
    </div>

    {{-- Báo hết món --}}
    <div x-show="soldOut" x-cloak class="fixed inset-0 z-40 flex justify-end">
        <div class="absolute inset-0 bg-black/60" x-on:click="soldOut = false"></div>
        <aside class="relative flex h-full w-full max-w-md flex-col bg-stone-900">
            <div class="flex items-center gap-2 border-b border-stone-800 p-4">
                <input type="search" wire:model.live.debounce.300ms="menuSearch" placeholder="Tìm món..." class="min-w-0 flex-1 rounded-lg border border-stone-700 bg-stone-800 px-3 py-2 text-white placeholder:text-stone-500">
                <button type="button" x-on:click="soldOut = false" class="px-2 text-sm font-semibold text-stone-400">Đóng</button>
            </div>
            <ul class="flex-1 divide-y divide-stone-800 overflow-y-auto">
                @foreach ($this->menuItems as $menuItem)
                    <li wire:key="avail-{{ $menuItem->id }}" class="flex items-center justify-between gap-3 px-4 py-3">
                        <span @class(['font-medium', 'text-stone-500 line-through' => ! $menuItem->is_available])>{{ $menuItem->name }}</span>
                        <button type="button" wire:click="toggleAvailable({{ $menuItem->id }})"
                            @class(['rounded-full px-3 py-1 text-sm font-bold', 'bg-emerald-600 text-white' => $menuItem->is_available, 'bg-red-600 text-white' => ! $menuItem->is_available])>
                            {{ $menuItem->is_available ? 'Còn' : 'Hết' }}
                        </button>
                    </li>
                @endforeach
            </ul>
        </aside>
    </div>
</div>
