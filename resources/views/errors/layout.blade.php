<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title')</title>
    {{-- CSS nội tuyến: trang lỗi phải hiện được cả khi asset Vite / database lỗi. --}}
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; background: #fafaf9; color: #1c1917; padding: 24px; box-sizing: border-box; }
        .box { max-width: 420px; text-align: center; }
        .code { font-size: 64px; font-weight: 800; color: #d97706; line-height: 1; }
        h1 { font-size: 22px; margin: 16px 0 8px; }
        p { color: #57534e; line-height: 1.5; margin: 0 0 20px; }
        a { display: inline-block; background: #d97706; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 999px; font-weight: 600; }
    </style>
</head>
<body>
    <div class="box">
        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <a href="@yield('link', url('/'))">@yield('action', 'Về trang chủ')</a>
    </div>
</body>
</html>
