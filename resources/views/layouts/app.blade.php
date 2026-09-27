<!DOCTYPE html>
<html lang="vi" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#b45309">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ \App\Models\Setting::get('restaurant.name', config('app.name')) }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="{{ $bodyClass ?? 'min-h-full bg-stone-50 text-stone-900 antialiased' }}">
    {{ $slot }}

    <x-toast />
    @livewireScripts
</body>
</html>
