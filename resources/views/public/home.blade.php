@use('App\Support\Money')

<x-layouts::app>
    @push('head')
        <script type="application/ld+json">{!! json_encode($site->structuredData(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) !!}</script>
    @endpush

    @php($heroImage = $site->get('home.hero_image'))
    <header class="relative overflow-hidden bg-stone-900 text-white">
        @if ($heroImage)
            <img src="/storage/{{ ltrim($heroImage, '/') }}" alt="" class="absolute inset-0 size-full object-cover opacity-45">
            <div class="absolute inset-0 bg-gradient-to-t from-stone-950/80 via-stone-900/40 to-stone-900/20"></div>
        @else
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,var(--color-amber-600),transparent_60%)] opacity-50"></div>
        @endif

        <nav class="relative mx-auto flex max-w-5xl items-center justify-between px-4 py-4">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-bold">
                @if ($logo = $site->logoUrl())
                    {{-- Nền trắng: logo chữ tối vẫn đọc được trên header tối --}}
                    <span class="rounded-xl bg-white/95 px-2.5 py-1.5 shadow-sm"><img src="{{ $logo }}" alt="{{ $site->name() }}" class="h-8 w-auto"></span>
                @else
                    {{ $site->name() }}
                @endif
            </a>
            <div class="flex items-center gap-4 text-sm font-medium">
                @if ($site->get('home.show_menu', true))
                    <a href="#menu" class="hidden hover:text-amber-300 sm:inline">Thực đơn</a>
                @endif
                <a href="#lien-he" class="hidden hover:text-amber-300 sm:inline">Liên hệ</a>
                @if ($site->reservationsEnabled())
                    <a href="{{ route('reservations.create') }}" class="rounded-full bg-white/15 px-4 py-2 backdrop-blur hover:bg-white/25">Đặt bàn</a>
                @endif
            </div>
        </nav>

        <div class="relative mx-auto max-w-5xl px-4 pb-16 pt-10 sm:pb-24 sm:pt-16">
            <p class="text-sm font-semibold uppercase tracking-widest text-amber-400">{{ $site->get('home.hero_eyebrow', 'Chào mừng đến với') }}</p>
            <h1 class="mt-2 text-4xl font-extrabold sm:text-6xl">{{ $site->get('home.hero_title', $site->name()) }}</h1>
            <p class="mt-4 max-w-xl text-lg text-stone-200">
                {{ $site->get('home.hero_subtitle', $site->slogan() ?? 'Quét mã QR tại bàn để gọi món, hoặc đặt bàn trước để có chỗ đẹp nhất.') }}
            </p>
            <div class="mt-8 flex flex-wrap gap-3">
                @if ($site->reservationsEnabled())
                    <a href="{{ route('reservations.create') }}" class="rounded-full bg-amber-600 px-6 py-3 font-bold text-white shadow-lg hover:bg-amber-700">{{ $site->get('home.cta_text', 'Đặt bàn ngay') }}</a>
                @endif
                @if ($site->get('home.show_menu', true))
                    <a href="#menu" class="rounded-full border border-white/30 px-6 py-3 font-semibold hover:bg-white/10">Xem thực đơn</a>
                @endif
                @if ($phone = $site->get('restaurant.phone'))
                    <a href="tel:{{ preg_replace('/[^0-9+]/', '', $phone) }}" class="rounded-full border border-white/30 px-6 py-3 font-semibold hover:bg-white/10">Gọi {{ $phone }}</a>
                @endif
            </div>
        </div>
    </header>

    @if ($about = $site->get('home.about_text'))
        <section class="mx-auto max-w-3xl px-4 py-14 text-center">
            <h2 class="text-3xl font-bold">{{ $site->get('home.about_title', 'Về chúng tôi') }}</h2>
            <div class="mt-4 space-y-3 text-lg leading-relaxed text-stone-600">
                @foreach (preg_split('/\R{2,}|\R/', trim($about)) as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </div>
        </section>
    @endif

    @if ($site->get('home.show_menu', true))
        <section id="menu" class="mx-auto max-w-5xl scroll-mt-4 px-4 py-12">
            <h2 class="text-3xl font-bold">Thực đơn</h2>
            <div class="mt-6 grid gap-8 md:grid-cols-2">
                @foreach ($categories as $category)
                    <div>
                        <h3 class="border-b-2 border-amber-500 pb-1 text-lg font-bold">{{ $category->name }}</h3>
                        <ul class="mt-3 space-y-3">
                            @foreach ($category->menuItems as $item)
                                <li>
                                    <div class="flex items-baseline gap-2">
                                        <span class="font-medium">{{ $item->name }}</span>
                                        <span class="flex-1 border-b border-dotted border-stone-300"></span>
                                        <span class="font-semibold text-amber-700">{{ Money::format($item->price) }}</span>
                                    </div>
                                    @if ($item->description)
                                        <p class="text-sm text-stone-500">{{ $item->description }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section id="lien-he" class="bg-stone-900 text-stone-200">
        <div class="mx-auto grid max-w-5xl gap-8 px-4 py-12 sm:grid-cols-3">
            <div>
                <h2 class="text-xl font-bold text-white">{{ $site->name() }}</h2>
                @if ($site->slogan())
                    <p class="mt-1 text-stone-400">{{ $site->slogan() }}</p>
                @endif
            </div>
            <dl class="space-y-3 text-sm">
                @foreach (['Địa chỉ' => 'restaurant.address', 'Giờ mở cửa' => 'restaurant.opening_hours', 'Điện thoại' => 'restaurant.phone', 'Email' => 'restaurant.email'] as $label => $key)
                    @if ($value = $site->get($key))
                        <div><dt class="text-stone-400">{{ $label }}</dt><dd class="font-semibold text-white">{{ $value }}</dd></div>
                    @endif
                @endforeach
            </dl>
            @if ($links = $site->socialLinks())
                <div class="flex flex-wrap content-start gap-2">
                    @foreach ($links as $label => $url)
                        <a href="{{ $url }}" target="_blank" rel="noopener" class="rounded-full border border-white/20 px-4 py-2 text-sm font-medium hover:bg-white/10">{{ $label }}</a>
                    @endforeach
                </div>
            @endif
        </div>
        <div class="border-t border-white/10 py-5 text-center text-xs text-stone-500">
            © {{ now()->year }} {{ $site->name() }} · <a href="{{ route('login') }}" class="hover:text-stone-300">Nhân viên</a>
        </div>
    </section>
</x-layouts::app>
