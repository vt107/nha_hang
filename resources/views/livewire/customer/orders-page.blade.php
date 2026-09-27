@use('App\Support\Money')
@use('App\Enums\OrderStatus')

<div>
    <x-customer.shell :session="$this->tableSession" active="orders">
        <div class="space-y-4 px-4 py-4">
            @if ($this->pendingCount > 0)
                <div class="flex items-center gap-3 rounded-2xl bg-amber-50 p-4 text-sm text-amber-900 ring-1 ring-amber-200">
                    <span class="relative flex size-3 shrink-0"><span class="absolute inline-flex size-full animate-ping rounded-full bg-amber-400 opacity-75"></span><span class="relative inline-flex size-3 rounded-full bg-amber-500"></span></span>
                    Order đang chờ nhân viên xác nhận. Trang sẽ tự cập nhật.
                </div>
            @endif

            @forelse ($this->orders as $order)
                <section wire:key="order-{{ $order->id }}" class="overflow-hidden rounded-2xl bg-white ring-1 ring-stone-200">
                    <div class="flex items-center justify-between gap-2 border-b border-stone-100 bg-stone-50 px-4 py-2.5">
                        <div class="text-sm">
                            <span class="font-semibold">Lần gọi #{{ $this->orders->count() - $loop->index }}</span>
                            <span class="text-stone-500">· {{ $order->created_at->format('H:i') }}</span>
                            @if ($order->device_id === $deviceId)
                                <span class="ml-1 rounded bg-stone-900 px-1.5 py-0.5 text-[10px] font-bold uppercase text-white">Của bạn</span>
                            @endif
                        </div>
                        @if ($order->status !== OrderStatus::Confirmed)
                            <x-status-pill :status="$order->status" />
                        @endif
                    </div>
                    <ul class="divide-y divide-stone-100">
                        @foreach ($order->items as $item)
                            <li wire:key="order-item-{{ $item->id }}" class="flex items-start justify-between gap-3 px-4 py-3">
                                <div class="min-w-0">
                                    <p @class(['font-medium', 'text-stone-400 line-through' => $item->status === \App\Enums\OrderItemStatus::Cancelled])>
                                        <span class="font-bold text-amber-700">{{ $item->quantity }}×</span> {{ $item->item_name }}
                                    </p>
                                    @if ($item->note)
                                        <p class="text-xs text-stone-500">“{{ $item->note }}”</p>
                                    @endif
                                    @if ($item->cancel_reason)
                                        <p class="text-xs text-red-600">{{ $item->cancel_reason }}</p>
                                    @endif
                                </div>
                                <x-status-pill :status="$item->status" :label="$item->status->customerLabel()" />
                            </li>
                        @endforeach
                    </ul>
                    @if ($order->status === OrderStatus::Rejected && $order->rejected_reason)
                        <p class="border-t border-stone-100 px-4 py-2 text-sm text-red-600">Lý do: {{ $order->rejected_reason }}</p>
                    @endif
                </section>
            @empty
                <div class="py-16 text-center">
                    <p class="text-4xl">🍜</p>
                    <p class="mt-2 text-stone-500">Bàn chưa gọi món nào.</p>
                    <a href="{{ route('customer.menu') }}" wire:navigate class="mt-4 inline-block rounded-full bg-amber-600 px-5 py-2.5 font-semibold text-white">Xem menu</a>
                </div>
            @endforelse
        </div>
    </x-customer.shell>
</div>
