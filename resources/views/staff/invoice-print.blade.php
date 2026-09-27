@use('App\Support\Money')
@use('App\Models\Setting')
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Hóa đơn {{ $invoice->code }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'Courier New', ui-monospace, monospace; font-size: 12px; color: #000; margin: 0; background: #e7e5e4; }
        .receipt { width: 80mm; margin: 12px auto; background: #fff; padding: 4mm; }
        .center { text-align: center; }
        .bold { font-weight: 700; }
        .big { font-size: 16px; }
        hr { border: 0; border-top: 1px dashed #000; margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 1px 0; }
        .right { text-align: right; white-space: nowrap; }
        .toolbar { text-align: center; margin: 12px; }
        .toolbar button { font: inherit; padding: 8px 16px; cursor: pointer; }
        @media print {
            body { background: #fff; }
            .receipt { margin: 0; width: auto; padding: 0; }
            .toolbar { display: none; }
            @page { size: 80mm auto; margin: 3mm; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="toolbar"><button onclick="window.print()">In hóa đơn</button></div>
    <div class="receipt">
        <div class="center bold big">{{ Setting::get('restaurant.name', config('app.name')) }}</div>
        <div class="center">{{ Setting::get('restaurant.address') }}</div>
        <div class="center">ĐT: {{ Setting::get('restaurant.phone') }}</div>
        <hr>
        <div class="center bold">HÓA ĐƠN THANH TOÁN</div>
        <div>Số: {{ $invoice->code }}</div>
        <div>{{ $invoice->tableSession->diningTable->displayName() }} · Vào: {{ $invoice->tableSession->opened_at->format('H:i') }}</div>
        <div>Thanh toán: {{ $invoice->paid_at?->format('H:i d/m/Y') }}</div>
        <div>Thu ngân: {{ $invoice->cashier?->name }}</div>
        <hr>
        <table>
            @foreach ($lines as $line)
                <tr><td colspan="2">{{ $line['name'] }}</td></tr>
                <tr><td>&nbsp;&nbsp;{{ $line['quantity'] }} x {{ number_format($line['unit_price'], 0, ',', '.') }}</td><td class="right">{{ number_format($line['amount'], 0, ',', '.') }}</td></tr>
            @endforeach
        </table>
        <hr>
        <table>
            <tr><td>Tạm tính</td><td class="right">{{ Money::format($invoice->subtotal) }}</td></tr>
            @if ($invoice->discount_amount)<tr><td>Giảm giá</td><td class="right">-{{ Money::format($invoice->discount_amount) }}</td></tr>@endif
            @if ($invoice->service_charge_amount)<tr><td>Phí phục vụ</td><td class="right">{{ Money::format($invoice->service_charge_amount) }}</td></tr>@endif
            @if ($invoice->vat_amount)<tr><td>VAT</td><td class="right">{{ Money::format($invoice->vat_amount) }}</td></tr>@endif
            <tr class="bold big"><td>TỔNG CỘNG</td><td class="right">{{ Money::format($invoice->total) }}</td></tr>
            @foreach ($invoice->payments as $payment)
                <tr><td>{{ $payment->method->getLabel() }}</td><td class="right">{{ Money::format($payment->received_amount ?? $payment->amount) }}</td></tr>
                @if ($payment->changeAmount())<tr><td>Tiền thối</td><td class="right">{{ Money::format($payment->changeAmount()) }}</td></tr>@endif
            @endforeach
        </table>
        <hr>
        <div class="center">Cảm ơn quý khách, hẹn gặp lại!</div>
    </div>
</body>
</html>
