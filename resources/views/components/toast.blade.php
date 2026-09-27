{{-- Thông báo nổi: $dispatch('toast', { message, type }) từ Livewire / JS. --}}
<div
    x-data="{ toasts: [] }"
    x-on:toast.window="
        const t = { id: Date.now() + Math.random(), message: $event.detail.message ?? $event.detail[0]?.message, type: $event.detail.type ?? $event.detail[0]?.type ?? 'info' };
        toasts.push(t);
        setTimeout(() => toasts = toasts.filter(x => x.id !== t.id), 5000);
    "
    class="pointer-events-none fixed inset-x-0 top-3 z-[100] flex flex-col items-center gap-2 px-4"
>
    <template x-for="t in toasts" :key="t.id">
        <div
            x-transition.opacity
            class="pointer-events-auto w-full max-w-sm rounded-xl px-4 py-3 text-sm font-medium shadow-lg ring-1"
            :class="{
                'bg-emerald-600 text-white ring-emerald-700': t.type === 'success',
                'bg-red-600 text-white ring-red-700': t.type === 'error',
                'bg-amber-500 text-white ring-amber-600': t.type === 'warning',
                'bg-stone-900 text-white ring-stone-800': t.type === 'info',
            }"
            x-text="t.message"
        ></div>
    </template>
</div>
