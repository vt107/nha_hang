{{--
    <head> dùng chung: SEO, Open Graph, favicon, màu thương hiệu, Google Analytics.
    Chỉ trang công khai (trang chủ, đặt bàn) được Google index; menu QR / nhân viên / bếp luôn noindex.
--}}
@php
    $pageTitle = $site->pageTitle($title ?? null);
    $description = $description ?? $site->description();
    $indexable = $site->allowsIndexing() && request()->routeIs('home', 'reservations.create');
    $keywords = $site->get('seo.keywords');
    $image = $site->shareImageUrl();
@endphp
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="{{ $site->color() }}">
<title>{{ $pageTitle }}</title>
<meta name="robots" content="{{ $indexable ? 'index, follow' : 'noindex, nofollow' }}">
@if ($description)
    <meta name="description" content="{{ \Illuminate\Support\Str::limit($description, 300) }}">
@endif
@if ($indexable)
    @if (is_array($keywords) && $keywords)
        <meta name="keywords" content="{{ implode(', ', $keywords) }}">
    @endif
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $site->name() }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="vi_VN">
    @if ($description)
        <meta property="og:description" content="{{ \Illuminate\Support\Str::limit($description, 300) }}">
    @endif
    @if ($image)
        <meta property="og:image" content="{{ $image }}">
        <meta name="twitter:card" content="summary_large_image">
    @endif
    @if ($verification = $site->get('seo.google_site_verification'))
        <meta name="google-site-verification" content="{{ $verification }}">
    @endif
@endif
@if ($favicon = $site->faviconUrl())
    <link rel="icon" href="{{ $favicon }}">
    <link rel="apple-touch-icon" href="{{ $favicon }}">
@endif
@if ($site->hasCustomColor())
    {{-- Màu chủ đạo: giao diện dùng thang màu amber của Tailwind, ghi đè biến màu bằng các sắc độ trộn từ màu admin chọn. --}}
    <style>
        :root {
            --brand: {{ $site->color() }};
            --color-amber-50: color-mix(in oklab, var(--brand) 7%, white);
            --color-amber-100: color-mix(in oklab, var(--brand) 15%, white);
            --color-amber-200: color-mix(in oklab, var(--brand) 30%, white);
            --color-amber-300: color-mix(in oklab, var(--brand) 50%, white);
            --color-amber-400: color-mix(in oklab, var(--brand) 72%, white);
            --color-amber-500: color-mix(in oklab, var(--brand) 88%, white);
            --color-amber-600: var(--brand);
            --color-amber-700: color-mix(in oklab, var(--brand) 82%, black);
            --color-amber-800: color-mix(in oklab, var(--brand) 66%, black);
            --color-amber-900: color-mix(in oklab, var(--brand) 52%, black);
            --color-orange-200: color-mix(in oklab, var(--brand) 38%, white);
        }
    </style>
@endif
@if ($indexable && ($gaId = $site->googleAnalyticsId()))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', '{{ $gaId }}');
    </script>
@endif
