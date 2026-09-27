<x-layouts::app :title="$title">
    <main class="flex min-h-screen items-center justify-center p-6">
        <div class="max-w-sm text-center">
            <p class="text-5xl">{{ $icon ?? '📱' }}</p>
            <h1 class="mt-4 text-2xl font-bold">{{ $title }}</h1>
            <p class="mt-2 text-stone-600">{{ $message }}</p>
            <a href="{{ route('home') }}" class="mt-6 inline-block text-sm font-medium text-amber-700 underline">Về trang chủ</a>
        </div>
    </main>
</x-layouts::app>
