@props(['status', 'label' => null])
{{-- Badge theo màu Filament của enum (HasColor): gray / info / warning / success / primary / danger. --}}
@php
    $classes = match ($status->getColor()) {
        'info' => 'bg-sky-100 text-sky-800 ring-sky-200',
        'warning' => 'bg-amber-100 text-amber-800 ring-amber-200',
        'success' => 'bg-emerald-100 text-emerald-800 ring-emerald-200',
        'primary' => 'bg-indigo-100 text-indigo-800 ring-indigo-200',
        'danger' => 'bg-red-100 text-red-700 ring-red-200',
        default => 'bg-stone-100 text-stone-600 ring-stone-200',
    };
@endphp
<span {{ $attributes->class(['inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-xs font-semibold ring-1 ring-inset', $classes]) }}>
    {{ $label ?? $status->getLabel() }}
</span>
