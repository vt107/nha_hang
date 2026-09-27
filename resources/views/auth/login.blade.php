<x-layouts::app title="Đăng nhập nhân viên">
    <main class="flex min-h-screen items-center justify-center p-4">
        <form method="POST" action="{{ route('login') }}" class="w-full max-w-sm space-y-4 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-stone-200">
            @csrf
            <div class="text-center">
                <h1 class="text-xl font-bold">{{ \App\Models\Setting::get('restaurant.name', config('app.name')) }}</h1>
                <p class="text-sm text-stone-500">Đăng nhập nhân viên</p>
            </div>

            @error('email')
                <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{{ $message }}</p>
            @enderror

            <label class="block">
                <span class="text-sm font-medium">Email</span>
                <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                    class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2.5 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-none">
            </label>
            <label class="block">
                <span class="text-sm font-medium">Mật khẩu</span>
                <input type="password" name="password" required autocomplete="current-password"
                    class="mt-1 w-full rounded-lg border border-stone-300 px-3 py-2.5 focus:border-amber-500 focus:ring-2 focus:ring-amber-200 focus:outline-none">
            </label>
            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="remember" value="1" class="rounded border-stone-300"> Ghi nhớ trên máy này
            </label>
            <button class="w-full rounded-lg bg-amber-600 py-2.5 font-semibold text-white hover:bg-amber-700">Đăng nhập</button>
        </form>
    </main>
</x-layouts::app>
