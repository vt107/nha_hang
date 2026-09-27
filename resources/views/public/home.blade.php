@use('App\Support\Money')
@use('App\Models\Setting')

<x-layouts::app>
    <header class="relative overflow-hidden bg-stone-900 text-white">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(217,119,6,.45),transparent_60%)]"></div>
        <div class="relative mx-auto max-w-5xl px-4 py-16 sm:py-24">
            <p class="text-sm font-semibold uppercase tracking-widest text-amber-400">Chào mừng đến với</p>
            <h1 class="mt-2 text-4xl font-extrabold sm:text-6xl">{{ Setting::get('restaurant.name', config('app.name')) }}</h1>
            <p class="mt-4 max-w-xl text-stone-300">Món Việt đậm vị, nguyên liệu tươi mỗi ngày. Quét mã QR tại bàn để gọi món, hoặc đặt bàn trước để có chỗ đẹp nhất.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('reservations.create') }}" class="rounded-full bg-amber-500 px-6 py-3 font-bold text-stone-900 hover:bg-amber-400">Đặt bàn ngay</a>
                <a href="#menu" class="rounded-full border border-white/30 px-6 py-3 font-semibold hover:bg-white/10">Xem menu</a>
            </div>
            <dl class="mt-10 grid gap-4 text-sm sm:grid-cols-3">
                <div><dt class="text-stone-400">Giờ mở cửa</dt><dd class="font-semibold">{{ Setting::get('restaurant.opening_hours') }}</dd></div>
                <div><dt class="text-stone-400">Điện thoại</dt><dd class="font-semibold">{{ Setting::get('restaurant.phone') }}</dd></div>
                <div><dt class="text-stone-400">Địa chỉ</dt><dd class="font-semibold">{{ Setting::get('restaurant.address') }}</dd></div>
            </dl>
        </div>
    </header>

    <section id="menu" class="mx-auto max-w-5xl px-4 py-12">
        <h2 class="text-3xl font-bold">Thực đơn</h2>
        <div class="mt-6 grid gap-8 md:grid-cols-2">
            @foreach ($categories as $category)
                <div>
                    <h3 class="border-b-2 border-amber-500 pb-1 text-lg font-bold">{{ $category->name }}</h3>
                    <ul class="mt-3 space-y-3">
                        @foreach ($category->menuItems as $item)
                            <li class="flex items-baseline gap-2">
                                <span class="font-medium">{{ $item->name }}</span>
                                <span class="flex-1 border-b border-dotted border-stone-300"></span>
                                <span class="font-semibold text-amber-700">{{ Money::format($item->price) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    <footer class="border-t border-stone-200 py-8 text-center text-sm text-stone-500">
        © {{ now()->year }} {{ Setting::get('restaurant.name', config('app.name')) }} ·
        <a href="{{ route('login') }}" class="hover:text-stone-700">Nhân viên</a>
    </footer>
</x-layouts::app>
