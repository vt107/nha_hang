@props(['session', 'active'])
@php
    $tabs = [
        'menu' => ['route' => 'customer.menu', 'label' => 'Menu', 'icon' => 'M4 6h16M4 12h16M4 18h10'],
        'orders' => ['route' => 'customer.orders', 'label' => 'Món đã gọi', 'icon' => 'M9 5h10M9 12h10M9 19h10M5 5h.01M5 12h.01M5 19h.01'],
        'bill' => ['route' => 'customer.bill', 'label' => 'Thanh toán', 'icon' => 'M3 7h18v10H3zM3 10h18M7 15h3'],
    ];
@endphp
<div class="mx-auto flex min-h-screen max-w-lg flex-col bg-stone-50 pb-20">
    <header class="sticky top-0 z-30 border-b border-stone-200 bg-white/95 backdrop-blur">
        <div class="flex items-center justify-between gap-3 px-4 py-3">
            <div class="flex min-w-0 items-center gap-2.5">
                @if ($logo = $site->logoUrl())
                    <img src="{{ $logo }}" alt="" class="h-10 w-auto max-w-24 shrink-0 object-contain">
                @endif
                <div class="min-w-0">
                    <p class="truncate text-xs font-medium uppercase tracking-wide text-amber-700">{{ $site->name() }}</p>
                    <h1 class="truncate text-lg font-bold">{{ $session->diningTable->displayName() }}</h1>
                </div>
            </div>
            <button type="button" wire:click="callWaiter" wire:loading.attr="disabled"
                class="flex shrink-0 items-center gap-1.5 rounded-full border border-amber-300 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-800 active:scale-95">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"/></svg>
                Gọi nhân viên
            </button>
        </div>
        {{ $header ?? '' }}
    </header>

    <main class="flex-1">
        {{ $slot }}
    </main>

    {{ $footer ?? '' }}

    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-stone-200 bg-white pb-[env(safe-area-inset-bottom)]">
        <div class="mx-auto grid max-w-lg grid-cols-3">
            @foreach ($tabs as $key => $tab)
                <a href="{{ route($tab['route']) }}" wire:navigate
                    @class([
                        'flex flex-col items-center gap-0.5 py-2 text-xs font-medium',
                        'text-amber-700' => $active === $key,
                        'text-stone-500' => $active !== $key,
                    ])>
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $tab['icon'] }}"/></svg>
                    {{ $tab['label'] }}
                </a>
            @endforeach
        </div>
    </nav>
</div>
