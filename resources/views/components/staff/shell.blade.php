@props(['title', 'back' => null])
<div class="min-h-screen" x-data x-init="window.listenForAlerts?.('staff')">
    <header class="sticky top-0 z-30 border-b border-stone-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center gap-3 px-4 py-3">
            @if ($back)
                <a href="{{ $back }}" wire:navigate class="-ml-1 rounded-lg p-1.5 text-stone-500 hover:bg-stone-100" aria-label="Quay lại">
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5"/></svg>
                </a>
            @endif
            <h1 class="min-w-0 flex-1 truncate text-lg font-bold">{{ $title }}</h1>
            {{ $actions ?? '' }}
            <div class="flex items-center gap-2 text-sm">
                <span class="hidden text-stone-500 sm:inline">{{ auth()->user()->name }}</span>
                @can('access-admin')
                    <a href="/admin" class="rounded-lg px-2 py-1.5 font-medium text-stone-600 hover:bg-stone-100">Admin</a>
                @endcan
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="rounded-lg px-2 py-1.5 font-medium text-stone-600 hover:bg-stone-100">Đăng xuất</button>
                </form>
            </div>
        </div>
    </header>
    <main class="mx-auto max-w-7xl px-4 py-4">
        {{ $slot }}
    </main>
</div>
