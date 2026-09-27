<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    @include('partials.head')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    @stack('head')
</head>
<body class="{{ $bodyClass ?? 'min-h-full bg-stone-50 text-stone-900 antialiased' }}">
    {{ $slot }}

    <x-toast />
    @livewireScripts
</body>
</html>
