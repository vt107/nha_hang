{{-- Bảng chọn size / topping. Cần trait App\Livewire\Concerns\ConfiguresMenuOptions + method addConfigured(). --}}
@if ($item = $this->configuringItem)
    <div class="fixed inset-0 z-50 flex items-end justify-center sm:items-center" wire:key="option-picker-{{ $item->id }}">
        <div class="absolute inset-0 bg-black/50" wire:click="cancelConfiguring"></div>
        <div class="relative flex max-h-[90vh] w-full max-w-lg flex-col rounded-t-3xl bg-white text-stone-900 sm:rounded-3xl">
            <div class="flex items-start justify-between gap-3 border-b border-stone-100 px-5 py-4">
                <div>
                    <h2 class="text-lg font-bold">{{ $item->name }}</h2>
                    <p class="text-sm text-stone-500">{{ \App\Support\Money::format($item->price) }}</p>
                </div>
                <button type="button" wire:click="cancelConfiguring" class="rounded-full p-1 text-stone-500" aria-label="Đóng">
                    <svg class="size-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="flex-1 space-y-5 overflow-y-auto px-5 py-4">
                @foreach ($item->optionGroups as $group)
                    <fieldset wire:key="group-{{ $group->id }}">
                        <legend class="mb-2 flex w-full items-baseline justify-between gap-2">
                            <span class="font-semibold">{{ $group->name }}</span>
                            <span @class(['text-xs', 'font-semibold text-amber-700' => $group->isRequired(), 'text-stone-500' => ! $group->isRequired()])>{{ $group->selectionHint() }}</span>
                        </legend>
                        <div class="space-y-1.5">
                            @foreach ($group->options as $option)
                                @php($checked = in_array($option->id, $selectedOptions[$group->id] ?? [], true))
                                <button type="button" wire:key="opt-{{ $option->id }}" wire:click="toggleOption({{ $group->id }}, {{ $option->id }})" @disabled(! $option->is_available)
                                    @class([
                                        'flex w-full items-center justify-between gap-3 rounded-xl px-3 py-2.5 text-left ring-1 transition',
                                        'bg-amber-50 ring-2 ring-amber-500' => $checked,
                                        'ring-stone-200' => ! $checked,
                                        'opacity-40' => ! $option->is_available,
                                    ])>
                                    <span class="flex items-center gap-2.5">
                                        <span @class([
                                            'flex size-5 shrink-0 items-center justify-center border-2',
                                            'rounded-full' => $group->isSingle(),
                                            'rounded-md' => ! $group->isSingle(),
                                            'border-amber-600 bg-amber-600 text-white' => $checked,
                                            'border-stone-300' => ! $checked,
                                        ])>
                                            @if ($checked)<svg class="size-3" fill="none" viewBox="0 0 24 24" stroke-width="4" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>@endif
                                        </span>
                                        <span class="font-medium">{{ $option->name }}</span>
                                        @unless ($option->is_available)<span class="text-xs text-red-600">Hết</span>@endunless
                                    </span>
                                    @if ($option->price_delta)
                                        <span class="text-sm text-stone-600">+{{ \App\Support\Money::format($option->price_delta) }}</span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </fieldset>
                @endforeach

                <input type="text" wire:model="configNote" maxlength="200" placeholder="Ghi chú: ít cay, không hành..."
                    class="w-full rounded-lg border border-stone-200 bg-stone-50 px-3 py-2 text-sm focus:border-amber-400 focus:outline-none">
            </div>

            <div class="flex items-center gap-3 border-t border-stone-100 px-5 py-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                <div class="flex items-center gap-1 rounded-full bg-stone-100 p-0.5">
                    <button type="button" wire:click="changeConfigQuantity(-1)" class="flex size-9 items-center justify-center rounded-full text-lg font-bold">−</button>
                    <span class="w-6 text-center font-bold">{{ $configQuantity }}</span>
                    <button type="button" wire:click="changeConfigQuantity(1)" class="flex size-9 items-center justify-center rounded-full text-lg font-bold">+</button>
                </div>
                <button type="button" wire:click="addConfigured" wire:loading.attr="disabled"
                    class="flex-1 rounded-2xl bg-amber-600 py-3 font-bold text-white active:scale-[.98] disabled:opacity-60">
                    Thêm · {{ \App\Support\Money::format($this->configuredUnitPrice * $configQuantity) }}
                </button>
            </div>
        </div>
    </div>
@endif
