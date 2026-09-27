@use('App\Support\Money')

<div x-data="{ cartOpen: false }">
    <x-customer.shell :session="$this->tableSession" active="menu">
        <x-slot:header>
            <div class="no-scrollbar flex gap-2 overflow-x-auto px-4 pb-3">
                @foreach ($this->categories as $category)
                    <a href="#cat-{{ $category->id }}"
                        class="shrink-0 rounded-full border border-stone-200 bg-white px-3.5 py-1.5 text-sm font-medium text-stone-700 active:bg-amber-50">
                        {{ $category->name }}
                    </a>
                @endforeach
            </div>
        </x-slot:header>

        @php($ordering = $site->qrOrderingEnabled())
        <div class="space-y-6 px-4 py-4">
            @if ($welcome = $site->get('menu.welcome_message'))
                <p class="rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">{!! nl2br(e($welcome)) !!}</p>
            @endif
            @unless ($ordering)
                <p class="rounded-2xl bg-stone-900 px-4 py-3 text-sm text-white">Mời quý khách xem menu và <strong>gọi nhân viên</strong> để gọi món.</p>
            @endunless

            @forelse ($this->categories as $category)
                <section id="cat-{{ $category->id }}" class="scroll-mt-32">
                    <h2 class="mb-2 text-base font-bold text-stone-800">{{ $category->name }}</h2>
                    <div class="divide-y divide-stone-100 overflow-hidden rounded-2xl bg-white ring-1 ring-stone-200">
                        @foreach ($category->menuItems as $item)
                            @php($qty = $this->quantitiesByItem[$item->id] ?? 0)
                            @php($hasOptions = $item->optionGroups->isNotEmpty())
                            <article wire:key="item-{{ $item->id }}" @class(['flex gap-3 p-3', 'opacity-60' => ! $item->is_available])>
                                <div class="min-w-0 flex-1">
                                    <h3 class="font-semibold leading-snug">
                                        {{ $item->name }}
                                        @if ($item->is_featured)
                                            <span class="ml-1 rounded bg-amber-100 px-1.5 py-0.5 align-middle text-[10px] font-bold uppercase text-amber-800">Nổi bật</span>
                                        @endif
                                    </h3>
                                    @if ($item->description)
                                        <p class="mt-0.5 line-clamp-2 text-sm text-stone-500">{{ $item->description }}</p>
                                    @endif
                                    <p class="mt-1.5 font-bold text-amber-700">
                                        {{ Money::format($item->price) }}
                                        @if ($hasOptions)<span class="text-xs font-medium text-stone-500">· có tùy chọn</span>@endif
                                    </p>
                                </div>
                                <div class="relative size-24 shrink-0">
                                    @if ($item->image_url)
                                        <img src="{{ $item->image_url }}" alt="{{ $item->name }}" loading="lazy" @class(['size-24 rounded-xl object-cover', 'grayscale' => ! $item->is_available])>
                                    @else
                                        <div class="flex size-24 items-center justify-center rounded-xl bg-gradient-to-br from-amber-100 to-orange-200 text-3xl">🍽️</div>
                                    @endif

                                    @if (! $item->is_available)
                                        <span class="absolute inset-x-1 bottom-1 rounded-lg bg-stone-900/80 py-1 text-center text-xs font-semibold text-white">Tạm hết</span>
                                    @elseif (! $ordering)
                                        {{-- Chỉ xem menu: không có nút thêm --}}
                                    @elseif ($qty > 0 && ! $hasOptions)
                                        <div class="absolute inset-x-1 -bottom-2 flex items-center justify-between rounded-full bg-amber-600 p-0.5 text-white shadow">
                                            <button type="button" wire:click="decrement({{ $item->id }})" class="flex size-7 items-center justify-center rounded-full text-lg font-bold active:bg-amber-700" aria-label="Bớt">−</button>
                                            <span class="text-sm font-bold">{{ $qty }}</span>
                                            <button type="button" wire:click="add({{ $item->id }})" class="flex size-7 items-center justify-center rounded-full text-lg font-bold active:bg-amber-700" aria-label="Thêm">+</button>
                                        </div>
                                    @else
                                        <button type="button" wire:click="add({{ $item->id }})"
                                            class="absolute -bottom-2 -right-1 flex size-9 items-center justify-center rounded-full bg-amber-600 text-2xl font-bold text-white shadow-md active:scale-90"
                                            aria-label="Thêm {{ $item->name }}">+</button>
                                        @if ($qty > 0)
                                            <span class="absolute -right-1 -top-1 flex size-6 items-center justify-center rounded-full bg-stone-900 text-xs font-bold text-white">{{ $qty }}</span>
                                        @endif
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @empty
                <p class="py-16 text-center text-stone-500">Menu đang được cập nhật.</p>
            @endforelse
        </div>

        <x-slot:footer>
            @if ($ordering && $this->cartCount > 0)
                <div class="fixed inset-x-0 bottom-16 z-20 px-4 pb-[env(safe-area-inset-bottom)]">
                    <button type="button" x-on:click="cartOpen = true"
                        class="mx-auto flex w-full max-w-lg items-center justify-between rounded-2xl bg-stone-900 px-4 py-3.5 text-white shadow-xl active:scale-[.98]">
                        <span class="flex items-center gap-2 font-semibold">
                            <span class="flex size-7 items-center justify-center rounded-full bg-amber-500 text-sm">{{ $this->cartCount }}</span>
                            Xem giỏ hàng
                        </span>
                        <span class="font-bold">{{ Money::format($this->cartTotal) }}</span>
                    </button>
                </div>
            @endif
        </x-slot:footer>
    </x-customer.shell>

    {{-- Giỏ hàng (bottom sheet) --}}
    <div x-show="cartOpen" x-cloak class="fixed inset-0 z-40" x-on:keydown.escape.window="cartOpen = false">
        <div x-show="cartOpen" x-transition.opacity class="absolute inset-0 bg-black/40" x-on:click="cartOpen = false"></div>
        <div x-show="cartOpen" x-transition:enter="transition duration-200" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0"
            class="absolute inset-x-0 bottom-0 mx-auto flex max-h-[85vh] max-w-lg flex-col rounded-t-3xl bg-white pb-[env(safe-area-inset-bottom)]">
            <div class="flex items-center justify-between border-b border-stone-100 px-5 py-4">
                <h2 class="text-lg font-bold">Giỏ hàng của bạn</h2>
                <button type="button" x-on:click="cartOpen = false" class="rounded-full p-1 text-stone-500" aria-label="Đóng">
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="flex-1 space-y-4 overflow-y-auto px-5 py-4">
                @forelse ($this->cartLines as $line)
                    <div wire:key="cart-{{ $line['key'] }}" class="space-y-2">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p @class(['font-semibold', 'text-red-600 line-through' => ! $line['orderable']])>{{ $line['name'] }}</p>
                                @if ($line['options'])
                                    <p class="text-xs text-stone-500">{{ $line['options'] }}</p>
                                @endif
                                @if (! $line['orderable'])
                                    <p class="text-xs text-red-600">Món / tùy chọn đã hết, vui lòng bỏ khỏi giỏ.</p>
                                @else
                                    <p class="text-sm text-stone-500">{{ Money::format($line['price']) }}</p>
                                @endif
                            </div>
                            <div class="flex shrink-0 items-center gap-1 rounded-full bg-stone-100 p-0.5">
                                <button type="button" wire:click="{{ $line['orderable'] && $line['quantity'] > 1 ? 'decrementLine' : 'removeLine' }}('{{ $line['key'] }}')" class="flex size-8 items-center justify-center rounded-full text-lg font-bold active:bg-stone-200">−</button>
                                <span class="w-6 text-center font-bold">{{ $line['quantity'] }}</span>
                                <button type="button" wire:click="incrementLine('{{ $line['key'] }}')" @disabled(! $line['orderable']) class="flex size-8 items-center justify-center rounded-full text-lg font-bold active:bg-stone-200 disabled:opacity-30">+</button>
                            </div>
                        </div>
                        @if ($line['orderable'])
                            <input type="text" value="{{ $line['note'] }}" maxlength="200" placeholder="Ghi chú: ít cay, không hành..."
                                wire:change="updateNote('{{ $line['key'] }}', $event.target.value)"
                                class="w-full rounded-lg border border-stone-200 bg-stone-50 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none">
                        @endif
                    </div>
                @empty
                    <p class="py-8 text-center text-stone-500">Giỏ hàng trống.</p>
                @endforelse

                @if ($this->cartCount > 0)
                    <textarea wire:model="orderNote" rows="2" maxlength="500" placeholder="Ghi chú cho cả order (không bắt buộc)"
                        class="w-full rounded-lg border border-stone-200 bg-stone-50 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none"></textarea>
                @endif
            </div>

            @if ($this->cartCount > 0)
                <div class="border-t border-stone-100 px-5 py-4">
                    <div class="mb-3 flex items-center justify-between">
                        <span class="text-stone-600">Tạm tính ({{ $this->cartCount }} món)</span>
                        <span class="text-lg font-bold">{{ Money::format($this->cartTotal) }}</span>
                    </div>
                    <button type="button" wire:click="placeOrder" wire:loading.attr="disabled" wire:target="placeOrder"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-amber-600 py-3.5 text-base font-bold text-white active:scale-[.98] disabled:opacity-60">
                        <span wire:loading.remove wire:target="placeOrder">Gửi order</span>
                        <span wire:loading wire:target="placeOrder">Đang gửi...</span>
                    </button>
                </div>
            @endif
        </div>
    </div>

    @include('partials.option-picker')
</div>
