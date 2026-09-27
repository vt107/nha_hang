<div class="min-h-screen bg-stone-50">
    <div class="mx-auto max-w-lg px-4 py-8">
        <a href="{{ route('home') }}" class="text-sm font-medium text-amber-700">← {{ $site->name() }}</a>
        <h1 class="mt-2 text-3xl font-bold">Đặt bàn</h1>
        <p class="mt-1 text-stone-600">Nhà hàng sẽ gọi lại xác nhận trong thời gian sớm nhất.</p>
        @if ($note = $site->get('reservation.form_note'))
            <p class="mt-3 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">{{ $note }}</p>
        @endif

        @if (! $site->reservationsEnabled())
            <div class="mt-6 rounded-2xl bg-white p-5 text-center ring-1 ring-stone-200">
                <p class="font-semibold">Nhà hàng đang tạm ngưng nhận đặt bàn online.</p>
                @if ($phone = $site->get('restaurant.phone'))
                    <p class="mt-1 text-stone-600">Vui lòng gọi <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="font-semibold text-amber-700">{{ $phone }}</a> để đặt bàn.</p>
                @endif
            </div>
        @elseif ($booked)
            <div class="mt-6 rounded-2xl bg-emerald-50 p-5 ring-1 ring-emerald-200">
                <p class="text-lg font-bold text-emerald-800">Đã nhận yêu cầu đặt bàn!</p>
                <p class="mt-1 text-emerald-900">Mã đặt bàn: <span class="font-mono font-bold">{{ $booked->code }}</span></p>
                <p class="text-emerald-900">{{ $booked->party_size }} người · {{ $booked->reserved_at->format('H:i \n\g\à\y d/m/Y') }}</p>
                <button type="button" wire:click="$set('bookedCode', null)" class="mt-3 text-sm font-medium text-emerald-800 underline">Đặt thêm bàn khác</button>
            </div>
        @else
            <form wire:submit="submit" class="mt-6 space-y-4 rounded-2xl bg-white p-5 ring-1 ring-stone-200">
                @php($input = 'mt-1 w-full rounded-lg border border-stone-300 px-3 py-2.5 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-none')

                <label class="block">
                    <span class="text-sm font-medium">Họ tên *</span>
                    <input type="text" wire:model="customer_name" autocomplete="name" class="{{ $input }}">
                    @error('customer_name') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-sm font-medium">Số điện thoại *</span>
                    <input type="tel" wire:model="customer_phone" autocomplete="tel" inputmode="tel" class="{{ $input }}">
                    @error('customer_phone') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
                <div class="grid grid-cols-3 gap-3">
                    <label class="block">
                        <span class="text-sm font-medium">Số người *</span>
                        <input type="number" min="1" wire:model="party_size" class="{{ $input }}">
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">Ngày *</span>
                        <input type="date" wire:model="date" min="{{ today()->toDateString() }}" class="{{ $input }}">
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium">Giờ *</span>
                        <input type="time" wire:model="time" step="900" class="{{ $input }}">
                    </label>
                </div>
                @foreach (['party_size', 'date', 'time'] as $field)
                    @error($field) <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                @endforeach
                <label class="block">
                    <span class="text-sm font-medium">Email</span>
                    <input type="email" wire:model="customer_email" autocomplete="email" class="{{ $input }}">
                    @error('customer_email') <span class="text-sm text-red-600">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="text-sm font-medium">Ghi chú</span>
                    <textarea wire:model="note" rows="2" placeholder="Sinh nhật, cần ghế trẻ em..." class="{{ $input }}"></textarea>
                </label>
                <button class="w-full rounded-xl bg-amber-600 py-3 font-bold text-white hover:bg-amber-700 disabled:opacity-60" wire:loading.attr="disabled">
                    <span wire:loading.remove>Gửi yêu cầu đặt bàn</span>
                    <span wire:loading>Đang gửi...</span>
                </button>
            </form>
        @endif
    </div>
</div>
