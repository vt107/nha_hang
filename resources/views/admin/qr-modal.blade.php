<div style="display:flex;flex-direction:column;align-items:center;gap:.75rem;text-align:center">
    <img src="{{ \App\Support\QrImage::dataUri($table->qrUrl()) }}" alt="QR {{ $table->code }}" style="width:16rem;height:16rem">
    <a href="{{ $table->qrUrl() }}" target="_blank" style="font-size:.875rem;text-decoration:underline;word-break:break-all">{{ $table->qrUrl() }}</a>
    <p style="font-size:.75rem;opacity:.7">Khách quét mã này để mở menu và gọi món tại {{ $table->displayName() }}.</p>
</div>
