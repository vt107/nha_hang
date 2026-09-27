<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>In mã QR - {{ $restaurantName }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; margin: 0; padding: 16px; color: #1f2937; background: #f3f4f6; }
        .toolbar { display: flex; justify-content: space-between; align-items: center; max-width: 190mm; margin: 0 auto 16px; }
        .toolbar button { background: #d97706; color: #fff; border: 0; border-radius: 8px; padding: 10px 18px; font-size: 15px; cursor: pointer; }
        .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6mm; max-width: 190mm; margin: 0 auto; }
        .card { background: #fff; border: 1px dashed #9ca3af; border-radius: 4mm; padding: 5mm; text-align: center; break-inside: avoid; }
        .card .brand { font-size: 12pt; font-weight: 700; letter-spacing: .02em; }
        .card img { width: 100%; max-width: 48mm; aspect-ratio: 1; display: block; margin: 3mm auto; }
        .card .table { font-size: 18pt; font-weight: 800; }
        .card .hint { font-size: 9pt; color: #4b5563; margin-top: 1mm; }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none; }
            @page { size: A4; margin: 10mm; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>{{ $tables->count() }} bàn</div>
        <button onclick="window.print()">In ngay</button>
    </div>
    <div class="grid">
        @foreach ($tables as $table)
            <div class="card">
                <div class="brand">{{ $restaurantName }}</div>
                <img src="{{ \App\Support\QrImage::dataUri($table->qrUrl()) }}" alt="QR {{ $table->code }}">
                <div class="table">{{ $table->displayName() }}</div>
                <div class="hint">Quét mã để xem menu &amp; gọi món</div>
            </div>
        @endforeach
    </div>
</body>
</html>
